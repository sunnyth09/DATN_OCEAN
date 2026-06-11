import 'dart:async';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../config/app_theme.dart';
import '../models/court_booking_models.dart';
import '../services/api_client.dart';
import '../services/court_booking_service.dart';

class CourtBookingScreen extends StatefulWidget {
  const CourtBookingScreen({super.key});

  @override
  State<CourtBookingScreen> createState() => _CourtBookingScreenState();
}

class _CourtBookingScreenState extends State<CourtBookingScreen> {
  final CourtBookingService _service = CourtBookingService();
  final NumberFormat _money = NumberFormat.currency(locale: 'vi_VN', symbol: 'd');
  final TextEditingController _staffSearchController = TextEditingController();

  Timer? _refreshTimer;
  bool _loading = true;
  bool _actionLoading = false;
  bool _isStaff = false;
  String? _error;

  List<Court> _courts = [];
  List<CourtSlot> _slots = [];
  List<CourtExtraService> _extraServices = [];
  List<CourtBooking> _myBookings = [];
  List<CourtBooking> _staffBookings = [];

  DateTime _selectedDate = DateTime.now();
  int? _selectedCourtId;
  final Set<int> _selectedSlotIndexes = {};
  final Map<int, int> _serviceQuantities = {};
  String _paymentMethod = 'cash';
  String _staffStatus = 'all';

  String get _dateParam => DateFormat('yyyy-MM-dd').format(_selectedDate);
  Court? get _selectedCourt {
    for (final court in _courts) {
      if (court.id == _selectedCourtId) return court;
    }
    return null;
  }

  List<int> get _orderedSelectedIndexes {
    final indexes = _selectedSlotIndexes.toList()..sort();
    return indexes;
  }

  int get _slotAmount => _orderedSelectedIndexes.fold(0, (sum, index) => sum + _slots[index].price);

  int get _serviceAmount {
    var total = 0;
    for (final service in _extraServices) {
      total += service.unitPrice * (_serviceQuantities[service.id] ?? 0);
    }
    return total;
  }

  int get _totalAmount => _slotAmount + _serviceAmount;

  @override
  void initState() {
    super.initState();
    _bootstrap();
    _refreshTimer = Timer.periodic(const Duration(seconds: 15), (_) => _silentRefresh());
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _staffSearchController.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await _loadRole();
      final courts = await _service.getCourts();
      final services = await _service.getServices();
      if (!mounted) return;
      setState(() {
        _courts = courts;
        _extraServices = services;
        _selectedCourtId = courts.isNotEmpty ? courts.first.id : null;
      });
      await Future.wait([
        _loadAvailability(),
        _loadMyBookings(),
        if (_isStaff) _loadStaffBookings(),
      ]);
    } catch (e) {
      if (mounted) setState(() => _error = _service.errorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadRole() async {
    try {
      final response = await ApiClient().dio.get('/me');
      final user = response.data['user'] ?? response.data;
      final role = user is Map ? user['role']?.toString() : null;
      _isStaff = role == 'admin' || role == 'staff' || role == 'seller';
    } catch (_) {
      _isStaff = false;
    }
  }

  Future<void> _silentRefresh() async {
    if (!mounted || _actionLoading) return;
    try {
      await Future.wait([
        if (_selectedCourtId != null) _loadAvailability(silent: true),
        _loadMyBookings(silent: true),
        if (_isStaff) _loadStaffBookings(silent: true),
      ]);
    } catch (_) {}
  }

  Future<void> _loadAvailability({bool silent = false}) async {
    if (_selectedCourtId == null) return;
    final slots = await _service.getAvailability(_selectedCourtId!, _dateParam);
    if (!mounted) return;
    setState(() {
      _slots = slots;
      _selectedSlotIndexes.removeWhere((index) => index >= slots.length || !slots[index].isAvailable);
    });
  }

  Future<void> _loadMyBookings({bool silent = false}) async {
    final bookings = await _service.getMyBookings();
    if (mounted) setState(() => _myBookings = bookings);
  }

  Future<void> _loadStaffBookings({bool silent = false}) async {
    if (!_isStaff) return;
    final bookings = await _service.getStaffBookings(
      date: _dateParam,
      status: _staffStatus,
      search: _staffSearchController.text,
    );
    if (mounted) setState(() => _staffBookings = bookings);
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 45)),
    );
    if (picked == null) return;
    setState(() {
      _selectedDate = picked;
      _selectedSlotIndexes.clear();
    });
    await Future.wait([
      _loadAvailability(),
      if (_isStaff) _loadStaffBookings(),
    ]);
  }

  void _toggleSlot(int index) {
    final slot = _slots[index];
    if (!slot.isAvailable) return;

    setState(() {
      if (_selectedSlotIndexes.contains(index)) {
        _selectedSlotIndexes.remove(index);
      } else {
        final next = {..._selectedSlotIndexes, index}.toList()..sort();
        var contiguous = true;
        for (var i = 1; i < next.length; i++) {
          if (next[i] != next[i - 1] + 1) contiguous = false;
        }
        if (contiguous) {
          _selectedSlotIndexes.add(index);
        } else {
          _selectedSlotIndexes
            ..clear()
            ..add(index);
        }
      }
    });
  }

  Future<void> _confirmBooking() async {
    if (_selectedCourtId == null || _orderedSelectedIndexes.isEmpty) {
      _showSnack('Chon san va khung gio truoc da bro.', isError: true);
      return;
    }

    final confirmed = await _showBookingConfirmDialog();
    if (confirmed != true) return;

    final indexes = _orderedSelectedIndexes;
    final start = _slots[indexes.first].startTime;
    final end = _slots[indexes.last].endTime;
    String? lockToken;

    setState(() => _actionLoading = true);
    try {
      final lock = await _service.lockSlot(
        courtId: _selectedCourtId!,
        date: _dateParam,
        startTime: start,
        endTime: end,
      );
      lockToken = lock['lock_token']?.toString();
      if (lockToken == null || lockToken.isEmpty) {
        throw Exception('Khong nhan duoc lock token tu may chu.');
      }

      final booking = await _service.createBooking(
        courtId: _selectedCourtId!,
        date: _dateParam,
        startTime: start,
        endTime: end,
        paymentMethod: _paymentMethod,
        lockToken: lockToken,
        services: _serviceQuantities,
      );

      if (!mounted) return;
      _showSnack('Da tao booking ${booking.code}. Cho nhan vien xac nhan nhe.');
      setState(() {
        _selectedSlotIndexes.clear();
        _serviceQuantities.clear();
      });
      await Future.wait([_loadAvailability(), _loadMyBookings(), if (_isStaff) _loadStaffBookings()]);
    } catch (e) {
      if (lockToken != null) {
        try {
          await _service.releaseLock(lockToken);
        } catch (_) {}
      }
      _showSnack(_service.errorMessage(e), isError: true);
    } finally {
      if (mounted) setState(() => _actionLoading = false);
    }
  }

  Future<bool?> _showBookingConfirmDialog() {
    return showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return SafeArea(
              child: Padding(
                padding: EdgeInsets.only(
                  left: 20,
                  right: 20,
                  top: 20,
                  bottom: MediaQuery.of(context).viewInsets.bottom + 20,
                ),
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Xac nhan dat san', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 12),
                      _summaryRow('San', _selectedCourt?.name ?? ''),
                      _summaryRow('Ngay', DateFormat('dd/MM/yyyy').format(_selectedDate)),
                      _summaryRow('Gio', '${_slots[_orderedSelectedIndexes.first].startTime} - ${_slots[_orderedSelectedIndexes.last].endTime}'),
                      _summaryRow('Tien san', _money.format(_slotAmount)),
                      const SizedBox(height: 14),
                      const Text('Dich vu them', style: TextStyle(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 8),
                      if (_extraServices.isEmpty)
                        const Text('Chua co dich vu dang ban.', style: TextStyle(color: AppColors.textMuted))
                      else
                        ..._extraServices.map((service) {
                          final qty = _serviceQuantities[service.id] ?? 0;
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Text('${service.name}\n${_money.format(service.unitPrice)}',
                                      style: const TextStyle(height: 1.35)),
                                ),
                                IconButton(
                                  onPressed: qty > 0
                                      ? () => setSheetState(() {
                                            setState(() => _serviceQuantities[service.id] = qty - 1);
                                          })
                                      : null,
                                  icon: const Icon(Icons.remove_circle_outline),
                                ),
                                SizedBox(width: 28, child: Center(child: Text(qty.toString()))),
                                IconButton(
                                  onPressed: () => setSheetState(() {
                                    setState(() => _serviceQuantities[service.id] = qty + 1);
                                  }),
                                  icon: const Icon(Icons.add_circle_outline),
                                ),
                              ],
                            ),
                          );
                        }),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String>(
                        value: _paymentMethod,
                        decoration: const InputDecoration(labelText: 'Phuong thuc thanh toan'),
                        items: const [
                          DropdownMenuItem(value: 'cash', child: Text('Tien mat tai san')),
                          DropdownMenuItem(value: 'bank_transfer', child: Text('Chuyen khoan')),
                          DropdownMenuItem(value: 'vnpay', child: Text('VNPay')),
                          DropdownMenuItem(value: 'momo', child: Text('MoMo')),
                        ],
                        onChanged: (value) => setSheetState(() => setState(() => _paymentMethod = value ?? 'cash')),
                      ),
                      const SizedBox(height: 14),
                      _summaryRow('Tong cong', _money.format(_totalAmount), strong: true),
                      const SizedBox(height: 18),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: () => Navigator.pop(context, true),
                          icon: const Icon(Icons.event_available),
                          label: const Text('Dat san ngay'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (_error != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Dat san')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.error_outline, size: 48, color: AppColors.error),
                const SizedBox(height: 12),
                Text(_error!, textAlign: TextAlign.center),
                const SizedBox(height: 16),
                ElevatedButton(onPressed: _bootstrap, child: const Text('Thu lai')),
              ],
            ),
          ),
        ),
      );
    }

    final tabCount = _isStaff ? 3 : 2;
    return DefaultTabController(
      length: tabCount,
      child: Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: AppBar(
          title: const Text('Dat san'),
          actions: [
            IconButton(onPressed: _silentRefresh, icon: const Icon(Icons.refresh), tooltip: 'Lam moi'),
          ],
          bottom: TabBar(
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white70,
            indicatorColor: Colors.white,
            tabs: [
              const Tab(icon: Icon(Icons.sports_tennis), text: 'Dat san'),
              const Tab(icon: Icon(Icons.event_note), text: 'Lich cua toi'),
              if (_isStaff) const Tab(icon: Icon(Icons.manage_accounts), text: 'Nhan vien'),
            ],
          ),
        ),
        body: Stack(
          children: [
            TabBarView(
              children: [
                _buildBookingTab(),
                _buildMyBookingsTab(),
                if (_isStaff) _buildStaffTab(),
              ],
            ),
            if (_actionLoading)
              Container(
                color: Colors.black.withOpacity(0.18),
                child: const Center(child: CircularProgressIndicator()),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildBookingTab() {
    return RefreshIndicator(
      onRefresh: _loadAvailability,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _pickDate,
                  icon: const Icon(Icons.calendar_today_outlined),
                  label: Text(DateFormat('dd/MM/yyyy').format(_selectedDate)),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: DropdownButtonFormField<int>(
                  value: _selectedCourtId,
                  decoration: const InputDecoration(labelText: 'San'),
                  items: _courts
                      .map((court) => DropdownMenuItem(value: court.id, child: Text(court.name, overflow: TextOverflow.ellipsis)))
                      .toList(),
                  onChanged: (value) async {
                    setState(() {
                      _selectedCourtId = value;
                      _selectedSlotIndexes.clear();
                    });
                    await _loadAvailability();
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          if (_selectedCourt != null) _courtHeader(_selectedCourt!),
          const SizedBox(height: 14),
          const Text('Khung gio trong ngay', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
          const SizedBox(height: 10),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: _slots.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              childAspectRatio: 2.25,
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
            ),
            itemBuilder: (context, index) => _slotTile(index),
          ),
          const SizedBox(height: 16),
          _bookingSummaryBar(),
        ],
      ),
    );
  }

  Widget _courtHeader(Court court) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _cardDecoration(),
      child: Row(
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(color: AppColors.primarySoft, borderRadius: BorderRadius.circular(14)),
            child: const Icon(Icons.stadium_outlined, color: AppColors.primary),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(court.name, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
                const SizedBox(height: 4),
                Text('${court.code} - ${court.type} - ${court.maxPlayers} nguoi',
                    style: const TextStyle(color: AppColors.textSecondary)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _slotTile(int index) {
    final slot = _slots[index];
    final selected = _selectedSlotIndexes.contains(index);
    final enabled = slot.isAvailable;
    return InkWell(
      onTap: enabled ? () => _toggleSlot(index) : null,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: selected ? AppColors.primary : (enabled ? Colors.white : const Color(0xFFE2E8F0)),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: selected ? AppColors.primary : AppColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text('${slot.startTime} - ${slot.endTime}',
                style: TextStyle(fontWeight: FontWeight.w900, color: selected ? Colors.white : AppColors.textDark)),
            const SizedBox(height: 4),
            Text(enabled ? _money.format(slot.price) : _slotStatusLabel(slot.status),
                style: TextStyle(fontSize: 12, color: selected ? Colors.white70 : AppColors.textSecondary)),
          ],
        ),
      ),
    );
  }

  Widget _bookingSummaryBar() {
    final hasSelection = _orderedSelectedIndexes.isNotEmpty;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(hasSelection
              ? '${_slots[_orderedSelectedIndexes.first].startTime} - ${_slots[_orderedSelectedIndexes.last].endTime}'
              : 'Chua chon khung gio'),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: Text(_money.format(_slotAmount),
                    style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: AppColors.primary)),
              ),
              ElevatedButton.icon(
                onPressed: hasSelection ? _confirmBooking : null,
                icon: const Icon(Icons.check_circle_outline),
                label: const Text('Tiep tuc'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildMyBookingsTab() {
    if (_myBookings.isEmpty) {
      return const Center(child: Text('Ban chua co lich dat san nao.'));
    }

    return RefreshIndicator(
      onRefresh: _loadMyBookings,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _myBookings.length,
        itemBuilder: (context, index) => _bookingCard(_myBookings[index], staffMode: false),
      ),
    );
  }

  Widget _buildStaffTab() {
    return RefreshIndicator(
      onRefresh: _loadStaffBookings,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _pickDate,
                  icon: const Icon(Icons.calendar_today_outlined),
                  label: Text(DateFormat('dd/MM/yyyy').format(_selectedDate)),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: DropdownButtonFormField<String>(
                  value: _staffStatus,
                  decoration: const InputDecoration(labelText: 'Trang thai'),
                  items: const [
                    DropdownMenuItem(value: 'all', child: Text('Tat ca')),
                    DropdownMenuItem(value: 'pending', child: Text('Cho xac nhan')),
                    DropdownMenuItem(value: 'confirmed', child: Text('Da xac nhan')),
                    DropdownMenuItem(value: 'checked_in', child: Text('Da check-in')),
                    DropdownMenuItem(value: 'extended', child: Text('Gia han')),
                    DropdownMenuItem(value: 'completed', child: Text('Hoan thanh')),
                  ],
                  onChanged: (value) async {
                    setState(() => _staffStatus = value ?? 'all');
                    await _loadStaffBookings();
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _staffSearchController,
            decoration: InputDecoration(
              hintText: 'Tim ma booking, ten, SDT',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: IconButton(icon: const Icon(Icons.tune), onPressed: () => _loadStaffBookings()),
            ),
            onSubmitted: (_) => _loadStaffBookings(),
          ),
          const SizedBox(height: 14),
          if (_staffBookings.isEmpty)
            const Padding(
              padding: EdgeInsets.only(top: 80),
              child: Center(child: Text('Khong co booking phu hop.')),
            )
          else
            ..._staffBookings.map((booking) => _bookingCard(booking, staffMode: true)),
        ],
      ),
    );
  }

  Widget _bookingCard(CourtBooking booking, {required bool staffMode}) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: _cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(booking.code, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900)),
              ),
              _statusChip(booking.status),
            ],
          ),
          const SizedBox(height: 8),
          Text('${booking.courtName ?? 'San #${booking.courtId}'} - ${_formatDate(booking.date)}'),
          const SizedBox(height: 4),
          Text('${booking.startTime} - ${booking.endTime}  |  ${booking.durationMinutes} phut',
              style: const TextStyle(color: AppColors.textSecondary)),
          if (booking.customerName != null || booking.customerPhone != null) ...[
            const SizedBox(height: 4),
            Text('${booking.customerName ?? 'Khach'} ${booking.customerPhone ?? ''}',
                style: const TextStyle(color: AppColors.textSecondary)),
          ],
          const Divider(height: 20),
          Row(
            children: [
              Expanded(
                child: Text('${_money.format(booking.paidAmount)} / ${_money.format(booking.totalAmount)}',
                    style: const TextStyle(fontWeight: FontWeight.w800)),
              ),
              _paymentChip(booking.paymentStatus),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: staffMode ? _staffActions(booking) : _customerActions(booking),
          ),
        ],
      ),
    );
  }

  List<Widget> _customerActions(CourtBooking booking) {
    return [
      OutlinedButton.icon(
        onPressed: () => _showQr(booking),
        icon: const Icon(Icons.qr_code_2),
        label: const Text('QR'),
      ),
      if (booking.amountDue > 0 && !['cancelled', 'completed', 'expired'].contains(booking.status))
        OutlinedButton.icon(
          onPressed: () => _showPaymentDialog(booking, staffMode: false),
          icon: const Icon(Icons.payments_outlined),
          label: const Text('Thanh toan'),
        ),
      if (['pending', 'confirmed'].contains(booking.status))
        TextButton.icon(
          onPressed: () => _cancelCustomerBooking(booking),
          icon: const Icon(Icons.cancel_outlined),
          label: const Text('Huy'),
        ),
    ];
  }

  List<Widget> _staffActions(CourtBooking booking) {
    return [
      if (booking.status == 'pending')
        ElevatedButton.icon(
          onPressed: () => _runAction(() => _service.confirmBooking(booking.id)),
          icon: const Icon(Icons.verified_outlined),
          label: const Text('Xac nhan'),
        ),
      if (booking.status == 'confirmed')
        ElevatedButton.icon(
          onPressed: () => _runAction(() => _service.checkIn(booking.id)),
          icon: const Icon(Icons.login),
          label: const Text('Check-in'),
        ),
      if (booking.status == 'confirmed')
        OutlinedButton.icon(
          onPressed: () => _showQrCheckInDialog(booking),
          icon: const Icon(Icons.qr_code_scanner),
          label: const Text('QR in'),
        ),
      if (['checked_in', 'playing', 'extended'].contains(booking.status))
        OutlinedButton.icon(
          onPressed: () => _showAddServiceDialog(booking),
          icon: const Icon(Icons.add_shopping_cart),
          label: const Text('Dich vu'),
        ),
      if (['checked_in', 'playing', 'extended'].contains(booking.status))
        OutlinedButton.icon(
          onPressed: () => _showExtendDialog(booking),
          icon: const Icon(Icons.more_time),
          label: const Text('Gia han'),
        ),
      if (booking.amountDue > 0 && !['cancelled', 'completed'].contains(booking.status))
        OutlinedButton.icon(
          onPressed: () => _showPaymentDialog(booking, staffMode: true),
          icon: const Icon(Icons.payments_outlined),
          label: const Text('Thu tien'),
        ),
      if (['checked_in', 'playing', 'extended'].contains(booking.status))
        ElevatedButton.icon(
          onPressed: () => _showCheckoutDialog(booking),
          icon: const Icon(Icons.logout),
          label: const Text('Check-out'),
        ),
      if (['pending', 'confirmed'].contains(booking.status))
        TextButton.icon(
          onPressed: () => _runAction(() => _service.staffCancelBooking(booking.id, reason: 'Nhan vien huy booking')),
          icon: const Icon(Icons.cancel_outlined),
          label: const Text('Huy'),
        ),
    ];
  }

  Future<void> _cancelCustomerBooking(CourtBooking booking) async {
    final ok = await _confirm('Huy booking ${booking.code}?');
    if (ok == true) {
      await _runAction(() => _service.cancelBooking(booking.id, reason: 'Khach huy tu mobile'));
    }
  }

  Future<void> _showPaymentDialog(CourtBooking booking, {required bool staffMode}) async {
    var method = staffMode ? 'cash' : 'bank_transfer';
    var type = booking.paidAmount > 0 ? 'additional' : 'full';
    final amountController = TextEditingController(text: booking.amountDue > 0 ? booking.amountDue.toString() : '');

    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(staffMode ? 'Thu tien booking' : 'Tao thanh toan'),
        content: StatefulBuilder(
          builder: (context, setDialogState) => Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                value: method,
                decoration: const InputDecoration(labelText: 'Phuong thuc'),
                items: [
                  if (staffMode) const DropdownMenuItem(value: 'cash', child: Text('Tien mat')),
                  const DropdownMenuItem(value: 'bank_transfer', child: Text('Chuyen khoan')),
                  if (staffMode) const DropdownMenuItem(value: 'pos_card', child: Text('The POS')),
                  if (!staffMode) const DropdownMenuItem(value: 'vnpay', child: Text('VNPay')),
                  if (!staffMode) const DropdownMenuItem(value: 'momo', child: Text('MoMo')),
                ],
                onChanged: (value) => setDialogState(() => method = value ?? method),
              ),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                value: type,
                decoration: const InputDecoration(labelText: 'Loai thanh toan'),
                items: const [
                  DropdownMenuItem(value: 'deposit', child: Text('Dat coc')),
                  DropdownMenuItem(value: 'full', child: Text('Toan bo')),
                  DropdownMenuItem(value: 'additional', child: Text('Phat sinh/con lai')),
                ],
                onChanged: (value) => setDialogState(() => type = value ?? type),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: amountController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'So tien'),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Dong')),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Luu')),
        ],
      ),
    );

    final amount = int.tryParse(amountController.text);
    amountController.dispose();
    if (ok != true) return;

    if (staffMode) {
      await _runAction(() => _service.recordStaffPayment(
            bookingId: booking.id,
            paymentMethod: method,
            paymentType: type,
            amount: amount,
            note: 'Thu tien tu mobile staff',
          ));
    } else {
      await _runAction(() => _service.payBooking(
            bookingId: booking.id,
            paymentMethod: method,
            paymentType: type,
            amount: amount,
            note: 'Khach tao thanh toan tu mobile',
          ));
    }
  }

  Future<void> _showQr(CourtBooking booking) async {
    await _runAction(() async {
      final data = await _service.getQrToken(booking.id);
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text('QR ${booking.code}'),
          content: SelectableText('court_booking:${booking.id}:${data['qr_token']}'),
          actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Dong'))],
        ),
      );
    }, reload: false);
  }

  Future<void> _showQrCheckInDialog(CourtBooking booking) async {
    final controller = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('QR check-in'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(labelText: 'QR token hoac chuoi QR'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Dong')),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Check-in')),
        ],
      ),
    );
    final raw = controller.text.trim();
    controller.dispose();
    if (ok != true || raw.isEmpty) return;
    final token = raw.startsWith('court_booking:') ? raw.split(':').last : raw;
    await _runAction(() => _service.qrCheckIn(booking.id, token));
  }

  Future<void> _showAddServiceDialog(CourtBooking booking) async {
    if (_extraServices.isEmpty) {
      _showSnack('Chua co dich vu dang ban.', isError: true);
      return;
    }
    var selectedService = _extraServices.first.id;
    var qty = 1;
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Them dich vu'),
        content: StatefulBuilder(
          builder: (context, setDialogState) => Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<int>(
                value: selectedService,
                items: _extraServices
                    .map((service) => DropdownMenuItem(value: service.id, child: Text(service.name)))
                    .toList(),
                onChanged: (value) => setDialogState(() => selectedService = value ?? selectedService),
              ),
              const SizedBox(height: 10),
              StepperCounter(value: qty, onChanged: (value) => setDialogState(() => qty = value)),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Dong')),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Them')),
        ],
      ),
    );
    if (ok == true) {
      await _runAction(() => _service.addService(bookingId: booking.id, serviceId: selectedService, quantity: qty));
    }
  }

  Future<void> _showExtendDialog(CourtBooking booking) async {
    var minutes = 30;
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Gia han gio choi'),
        content: StatefulBuilder(
          builder: (context, setDialogState) => DropdownButtonFormField<int>(
            value: minutes,
            decoration: const InputDecoration(labelText: 'So phut'),
            items: const [
              DropdownMenuItem(value: 15, child: Text('15 phut')),
              DropdownMenuItem(value: 30, child: Text('30 phut')),
              DropdownMenuItem(value: 60, child: Text('60 phut')),
            ],
            onChanged: (value) => setDialogState(() => minutes = value ?? 30),
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Dong')),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Gia han')),
        ],
      ),
    );
    if (ok == true) await _runAction(() => _service.extendBooking(booking.id, minutes));
  }

  Future<void> _showCheckoutDialog(CourtBooking booking) async {
    var method = booking.amountDue > 0 ? 'cash' : null;
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Check-out tra san'),
        content: StatefulBuilder(
          builder: (context, setDialogState) => Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Con phai thu: ${_money.format(booking.amountDue)}'),
              if (booking.amountDue > 0) ...[
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  value: method,
                  decoration: const InputDecoration(labelText: 'Phuong thuc thu tien'),
                  items: const [
                    DropdownMenuItem(value: 'cash', child: Text('Tien mat')),
                    DropdownMenuItem(value: 'bank_transfer', child: Text('Chuyen khoan')),
                    DropdownMenuItem(value: 'pos_card', child: Text('The POS')),
                    DropdownMenuItem(value: 'pos_transfer', child: Text('POS transfer')),
                  ],
                  onChanged: (value) => setDialogState(() => method = value),
                ),
              ],
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Dong')),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Check-out')),
        ],
      ),
    );
    if (ok == true) {
      await _runAction(() => _service.checkOut(bookingId: booking.id, paymentMethod: method, note: 'Check-out tu mobile'));
    }
  }

  Future<void> _runAction(Future<void> Function() action, {bool reload = true}) async {
    setState(() => _actionLoading = true);
    try {
      await action();
      if (reload) {
        await Future.wait([_loadMyBookings(), if (_isStaff) _loadStaffBookings(), if (_selectedCourtId != null) _loadAvailability()]);
      }
      _showSnack('Thao tac thanh cong.');
    } catch (e) {
      _showSnack(_service.errorMessage(e), isError: true);
    } finally {
      if (mounted) setState(() => _actionLoading = false);
    }
  }

  Future<bool?> _confirm(String text) {
    return showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Xac nhan'),
        content: Text(text),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Dong')),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Dong y')),
        ],
      ),
    );
  }

  Widget _summaryRow(String label, String value, {bool strong = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Expanded(child: Text(label, style: const TextStyle(color: AppColors.textSecondary))),
          Text(value, style: TextStyle(fontWeight: strong ? FontWeight.w900 : FontWeight.w700)),
        ],
      ),
    );
  }

  Widget _statusChip(String status) {
    final color = switch (status) {
      'confirmed' => AppColors.info,
      'checked_in' || 'playing' || 'extended' => AppColors.success,
      'completed' => AppColors.tertiary,
      'cancelled' || 'no_show' || 'expired' => AppColors.error,
      _ => AppColors.warning,
    };
    return Chip(
      label: Text(_statusLabel(status), style: const TextStyle(color: Colors.white, fontSize: 11)),
      backgroundColor: color,
      visualDensity: VisualDensity.compact,
    );
  }

  Widget _paymentChip(String status) {
    final paid = status == 'paid' || status == 'deposit_paid';
    return Chip(
      label: Text(_paymentLabel(status), style: const TextStyle(color: Colors.white, fontSize: 11)),
      backgroundColor: paid ? AppColors.success : AppColors.warning,
      visualDensity: VisualDensity.compact,
    );
  }

  BoxDecoration _cardDecoration() {
    return BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: AppColors.borderLight),
      boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 12, offset: const Offset(0, 6))],
    );
  }

  void _showSnack(String message, {bool isError = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: isError ? AppColors.error : AppColors.success),
    );
  }

  String _formatDate(String date) {
    final parsed = DateTime.tryParse(date);
    return parsed == null ? date : DateFormat('dd/MM/yyyy').format(parsed);
  }

  String _slotStatusLabel(String status) {
    return switch (status) {
      'booked' => 'Da dat',
      'locked' => 'Dang giu',
      'maintenance' => 'Bao tri',
      'past' => 'Da qua',
      'closed' => 'Dong cua',
      _ => status,
    };
  }

  String _statusLabel(String status) {
    return switch (status) {
      'pending' => 'Cho xac nhan',
      'confirmed' => 'Da xac nhan',
      'checked_in' => 'Da check-in',
      'playing' => 'Dang choi',
      'extended' => 'Gia han',
      'completed' => 'Hoan thanh',
      'cancelled' => 'Da huy',
      'no_show' => 'Vang mat',
      'expired' => 'Het han',
      _ => status,
    };
  }

  String _paymentLabel(String status) {
    return switch (status) {
      'unpaid' => 'Chua TT',
      'deposit_paid' => 'Da coc',
      'partially_paid' => 'TT mot phan',
      'paid' => 'Da TT',
      'refunded' => 'Hoan tien',
      'partially_refunded' => 'Hoan mot phan',
      _ => status,
    };
  }
}

class StepperCounter extends StatelessWidget {
  final int value;
  final ValueChanged<int> onChanged;

  const StepperCounter({super.key, required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        IconButton(
          onPressed: value > 1 ? () => onChanged(value - 1) : null,
          icon: const Icon(Icons.remove_circle_outline),
        ),
        SizedBox(width: 48, child: Center(child: Text(value.toString(), style: const TextStyle(fontWeight: FontWeight.w900)))),
        IconButton(
          onPressed: () => onChanged(value + 1),
          icon: const Icon(Icons.add_circle_outline),
        ),
      ],
    );
  }
}
