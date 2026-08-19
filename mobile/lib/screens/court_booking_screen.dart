import 'dart:async';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../config/app_theme.dart';
import '../models/court_booking_models.dart';
import '../providers/auth_provider.dart';
import '../services/api_client.dart';
import '../services/court_booking_service.dart';
import '../services/realtime_service.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/app_toast.dart';
import 'court_booking/widgets/booking_card.dart';
import 'court_booking/widgets/booking_summary_bar.dart';
import 'court_booking/widgets/court_header.dart';
import 'court_booking/widgets/qr_check_in_dialog.dart';
import 'court_booking/widgets/slot_grid.dart';
import 'court_booking/widgets/stepper_counter.dart';

class CourtBookingScreen extends StatefulWidget {
  const CourtBookingScreen({super.key});

  @override
  State<CourtBookingScreen> createState() => _CourtBookingScreenState();
}

class _CourtBookingScreenState extends State<CourtBookingScreen> {
  final CourtBookingService _service = CourtBookingService();
  final NumberFormat _money = NumberFormat.currency(locale: 'vi_VN', symbol: 'đ');
  final TextEditingController _staffSearchController = TextEditingController();

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
  final String _paymentMethod = 'cash';
  final String _staffStatus = 'all';
  String _myBookingFilter = 'all';

  // Realtime & Lock Management
  String? _subscribedCourtChannel;
  String? _subscribedDateChannel;
  Timer? _pollingTimer;
  Timer? _lockDebounce;
  Timer? _lockCountdownTimer;
  String? _activeLockToken;
  DateTime? _lockExpiresAt;
  int _lockSecondsRemaining = 0;

  String get _dateParam => DateFormat('yyyy-MM-dd').format(_selectedDate);

  List<int> get _orderedSelectedIndexes {
    final indexes = _selectedSlotIndexes.toList()..sort();
    return indexes;
  }

  int get _slotAmount => _orderedSelectedIndexes.fold(
      0, (sum, index) => sum + (_slots.length > index ? _slots[index].price : 0));

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
    _startPolling();
  }

  @override
  void dispose() {
    _pollingTimer?.cancel();
    _lockDebounce?.cancel();
    _lockCountdownTimer?.cancel();
    _unsubscribeRealtime();
    _releaseCurrentLock();
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

      _setupRealtimeSubscription();
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

  Future<void> _loadAvailability({bool silent = false}) async {
    if (_selectedCourtId == null) return;
    try {
      final slots = await _service.getAvailability(_selectedCourtId!, _dateParam);
      if (!mounted) return;
      setState(() {
        _slots = slots;
        // Clean any selection that is no longer available and not locked by me
        _selectedSlotIndexes.removeWhere((index) {
          if (index >= slots.length) return true;
          final s = slots[index];
          return !s.isAvailable && !s.isMyLock;
        });
      });
    } catch (_) {}
  }

  Future<void> _loadMyBookings() async {
    final loggedIn = context.read<AuthProvider>().isAuthenticated;
    if (!loggedIn) return;
    try {
      final bookings = await _service.getMyBookings();
      if (!mounted) return;
      setState(() => _myBookings = bookings);
    } catch (_) {}
  }

  Future<void> _loadStaffBookings() async {
    if (!_isStaff) return;
    try {
      final bookings = await _service.getStaffBookings(
        date: _dateParam,
        status: _staffStatus == 'all' ? null : _staffStatus,
        search: _staffSearchController.text.trim().isEmpty
            ? null
            : _staffSearchController.text.trim(),
      );
      if (!mounted) return;
      setState(() => _staffBookings = bookings);
    } catch (_) {}
  }

  // ─── REALTIME SYNC ───────────────────────────────────────────────

  void _setupRealtimeSubscription() {
    if (_selectedCourtId == null) return;

    final newCourtChannel = 'court-booking.court.$_selectedCourtId.$_dateParam';
    final newDateChannel = 'court-booking.$_dateParam';

    _unsubscribeRealtime();

    _subscribedCourtChannel = newCourtChannel;
    _subscribedDateChannel = newDateChannel;

    void onRealtimeChange(String event, dynamic data) {
      debugPrint('[CourtBooking] Realtime event received: $event on $_subscribedCourtChannel');
      if (mounted) {
        _loadAvailability(silent: true);
      }
    }

    RealtimeService().subscribe(newCourtChannel, '*', onRealtimeChange);
    RealtimeService().subscribe(newCourtChannel, 'CourtSlotLocked', onRealtimeChange);
    RealtimeService().subscribe(newCourtChannel, 'CourtSlotReleased', onRealtimeChange);
    RealtimeService().subscribe(newCourtChannel, 'CourtBookingCreated', onRealtimeChange);
    RealtimeService().subscribe(newCourtChannel, 'CourtBookingCancelled', onRealtimeChange);
    RealtimeService().subscribe(newCourtChannel, 'CourtBookingStatusChanged', onRealtimeChange);

    RealtimeService().subscribe(newDateChannel, '*', onRealtimeChange);
    RealtimeService().subscribe(newDateChannel, 'CourtSlotLocked', onRealtimeChange);
    RealtimeService().subscribe(newDateChannel, 'CourtSlotReleased', onRealtimeChange);
    RealtimeService().subscribe(newDateChannel, 'CourtBookingCreated', onRealtimeChange);
    RealtimeService().subscribe(newDateChannel, 'CourtBookingCancelled', onRealtimeChange);
  }

  void _unsubscribeRealtime() {
    if (_subscribedCourtChannel != null) {
      RealtimeService().unsubscribe(_subscribedCourtChannel!);
      _subscribedCourtChannel = null;
    }
    if (_subscribedDateChannel != null) {
      RealtimeService().unsubscribe(_subscribedDateChannel!);
      _subscribedDateChannel = null;
    }
  }

  void _startPolling() {
    _pollingTimer?.cancel();
    _pollingTimer = Timer.periodic(const Duration(seconds: 12), (_) {
      if (mounted) {
        _loadAvailability(silent: true);
      }
    });
  }

  // ─── SLOT SELECTION & LOCK ────────────────────────────────────────

  bool _hasContinuousSlots(List<int> sortedIndexes) {
    if (sortedIndexes.length <= 1) return true;
    for (int i = 0; i < sortedIndexes.length - 1; i++) {
      if (sortedIndexes[i + 1] != sortedIndexes[i] + 1) {
        return false;
      }
    }
    return true;
  }

  void _onToggleSlot(int index) {
    final loggedIn = context.read<AuthProvider>().isAuthenticated;
    if (!loggedIn) {
      AppToast.showInfo(
        context,
        message: 'Vui lòng đăng nhập để giữ chỗ và đặt sân',
      );
      context.push('/login');
      return;
    }

    final slot = _slots[index];
    if (!slot.isAvailable && !slot.isMyLock && !_selectedSlotIndexes.contains(index)) {
      if (slot.isLocked) {
        AppToast.showWarning(
          context,
          message: 'Khung giờ này đang có khách giữ chỗ. Vui lòng chọn khung giờ khác!',
        );
      }
      return;
    }

    final tempSet = Set<int>.from(_selectedSlotIndexes);
    if (tempSet.contains(index)) {
      tempSet.remove(index);
    } else {
      tempSet.add(index);
    }

    final sorted = tempSet.toList()..sort();
    if (sorted.length > 1 && !_hasContinuousSlots(sorted)) {
      AppToast.showWarning(
        context,
        message: 'Vui lòng chọn các khung giờ liền kề nhau!',
      );
      return;
    }

    setState(() {
      _selectedSlotIndexes.clear();
      _selectedSlotIndexes.addAll(tempSet);
    });

    // Debounce calling lock on server
    _lockDebounce?.cancel();
    _lockDebounce = Timer(const Duration(milliseconds: 300), () {
      _syncLockWithBackend();
    });
  }

  Future<void> _syncLockWithBackend() async {
    if (_orderedSelectedIndexes.isEmpty) {
      await _releaseCurrentLock();
      return;
    }

    if (_selectedCourtId == null) return;
    final firstIndex = _orderedSelectedIndexes.first;
    final lastIndex = _orderedSelectedIndexes.last;
    final startTime = _slots[firstIndex].startTime;
    final endTime = _slots[lastIndex].endTime;

    try {
      final lockData = await _service.lockSlot(
        courtId: _selectedCourtId!,
        date: _dateParam,
        startTime: startTime,
        endTime: endTime,
      );

      final token = lockData['lock_token']?.toString();
      final expiresAtStr = lockData['expires_at']?.toString();

      if (token != null && mounted) {
        setState(() {
          _activeLockToken = token;
          if (expiresAtStr != null) {
            _lockExpiresAt = DateTime.tryParse(expiresAtStr);
          } else {
            _lockExpiresAt = DateTime.now().add(const Duration(minutes: 5));
          }
        });
        _startLockCountdown();
      }
    } catch (e) {
      if (mounted) {
        AppToast.showError(
          context,
          message: _service.errorMessage(e),
        );
        setState(() {
          _selectedSlotIndexes.clear();
          _activeLockToken = null;
        });
        _clearLockTimer();
        _loadAvailability();
      }
    }
  }

  void _startLockCountdown() {
    _lockCountdownTimer?.cancel();
    if (_lockExpiresAt == null) return;

    void tick() {
      final diff = _lockExpiresAt!.difference(DateTime.now()).inSeconds;
      if (diff <= 0) {
        _clearLockTimer();
        if (mounted) {
          setState(() {
            _selectedSlotIndexes.clear();
            _activeLockToken = null;
            _lockSecondsRemaining = 0;
          });
          AppToast.showWarning(
            context,
            message: 'Thời gian giữ chỗ (5 phút) đã hết. Vui lòng chọn lại khung giờ!',
          );
          _loadAvailability();
        }
      } else {
        if (mounted) {
          setState(() {
            _lockSecondsRemaining = diff;
          });
        }
      }
    }

    tick();
    _lockCountdownTimer = Timer.periodic(const Duration(seconds: 1), (_) => tick());
  }

  void _clearLockTimer() {
    _lockCountdownTimer?.cancel();
    _lockCountdownTimer = null;
    _lockSecondsRemaining = 0;
  }

  Future<void> _releaseCurrentLock() async {
    if (_activeLockToken != null) {
      final token = _activeLockToken!;
      _activeLockToken = null;
      _clearLockTimer();
      try {
        await _service.releaseLock(token);
      } catch (_) {}
    }
  }

  Future<void> _handleBookCourt() async {
    final loggedIn = context.read<AuthProvider>().isAuthenticated;
    if (!loggedIn) {
      AppToast.showInfo(
        context,
        message: 'Vui lòng đăng nhập để đặt sân',
      );
      context.push('/login');
      return;
    }

    if (_selectedCourtId == null || _orderedSelectedIndexes.isEmpty) {
      AppToast.showWarning(
        context,
        message: 'Vui lòng chọn ít nhất một khung giờ trống',
      );
      return;
    }

    final firstIndex = _orderedSelectedIndexes.first;
    final lastIndex = _orderedSelectedIndexes.last;
    final startTime = _slots[firstIndex].startTime;
    final endTime = _slots[lastIndex].endTime;

    setState(() => _actionLoading = true);

    try {
      final booking = await _service.createBooking(
        courtId: _selectedCourtId!,
        date: _dateParam,
        startTime: startTime,
        endTime: endTime,
        paymentMethod: _paymentMethod,
        lockToken: _activeLockToken,
        services: _serviceQuantities,
      );

      if (!mounted) return;

      AppToast.showSuccess(
        context,
        message: 'Đặt sân thành công! Mã đơn: #${booking.code}',
      );

      setState(() {
        _selectedSlotIndexes.clear();
        _serviceQuantities.clear();
        _activeLockToken = null;
      });
      _clearLockTimer();

      await Future.wait([
        _loadAvailability(),
        _loadMyBookings(),
        if (_isStaff) _loadStaffBookings(),
      ]);
    } catch (e) {
      if (mounted) {
        AppToast.showError(
          context,
          message: _service.errorMessage(e),
        );
      }
    } finally {
      if (mounted) setState(() => _actionLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: _isStaff ? 3 : 2,
      child: Scaffold(
        backgroundColor: AppColors.background,
        appBar: AppBar(
          title: const Text(
            'Đặt Sân Thể Thao',
            style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18),
          ),
          bottom: TabBar(
            labelColor: AppColors.primary,
            unselectedLabelColor: AppColors.textSecondary,
            indicatorColor: AppColors.primary,
            tabs: [
              const Tab(text: 'Chọn sân & giờ'),
              const Tab(text: 'Lịch sử của tôi'),
              if (_isStaff) const Tab(text: 'Quản lý (Staff)'),
            ],
          ),
        ),
        body: _loading
            ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
            : _error != null
                ? AppEmptyState(
                    icon: Icons.error_outline_rounded,
                    title: 'Lỗi tải dữ liệu sân',
                    message: _error!,
                    buttonText: 'Thử lại',
                    onAction: _bootstrap,
                  )
                : TabBarView(
                    children: [
                      _buildBookingTab(),
                      _buildMyBookingsTab(),
                      if (_isStaff) _buildStaffTab(),
                    ],
                  ),
      ),
    );
  }

  Widget _buildBookingTab() {
    if (_courts.isEmpty) {
      return AppEmptyState(
        icon: Icons.sports_tennis_rounded,
        title: 'Chưa có sân khả dụng',
        message: 'Hệ thống hiện chưa có sân nào được kích hoạt.',
        buttonText: 'Tải lại',
        onAction: _bootstrap,
      );
    }

    return Stack(
      children: [
        RefreshIndicator(
          color: AppColors.primary,
          onRefresh: () => Future.wait([
            _loadAvailability(),
            _loadMyBookings(),
          ]),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 160),
            children: [
              // Court Selector Header
              CourtHeader(
                courts: _courts,
                selectedCourtId: _selectedCourtId,
                onSelectCourt: (id) {
                  _releaseCurrentLock();
                  setState(() {
                    _selectedCourtId = id;
                    _selectedSlotIndexes.clear();
                  });
                  _setupRealtimeSubscription();
                  _loadAvailability();
                },
              ),

              const SizedBox(height: 16),

              // Date Picker Ribbon
              _buildDateRibbon(),

              const SizedBox(height: 12),

              // Live Lock Countdown Banner
              _buildLockCountdownBanner(),

              // Time Slot Grid
              if (_slots.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(32),
                  child: Center(
                    child: Text(
                      'Không có khung giờ nào cho ngày đã chọn.',
                      style: TextStyle(color: AppColors.textMuted),
                    ),
                  ),
                )
              else
                SlotGrid(
                  slots: _slots,
                  selectedIndexes: _selectedSlotIndexes,
                  money: _money,
                  selectedDate: _selectedDate,
                  onToggle: _onToggleSlot,
                ),

              const SizedBox(height: 16),

              // Extra Services
              if (_extraServices.isNotEmpty) _buildExtraServices(),
            ],
          ),
        ),

        // Sticky Summary Bar
        Positioned(
          bottom: 0,
          left: 0,
          right: 0,
          child: BookingSummaryBar(
            slots: _slots,
            orderedSelectedIndexes: _orderedSelectedIndexes,
            totalAmount: _totalAmount,
            money: _money,
            dateText: DateFormat('dd/MM').format(_selectedDate),
            isLoading: _actionLoading,
            onBook: _handleBookCourt,
          ),
        ),
      ],
    );
  }

  Widget _buildLockCountdownBanner() {
    if (_lockSecondsRemaining <= 0 || _orderedSelectedIndexes.isEmpty) {
      return const SizedBox.shrink();
    }
    final mins = (_lockSecondsRemaining ~/ 60).toString().padLeft(2, '0');
    final secs = (_lockSecondsRemaining % 60).toString().padLeft(2, '0');

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFFFFBEB), Color(0xFFFEF3C7)],
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFF59E0B), width: 1.2),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFF59E0B).withValues(alpha: 0.18),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(7),
            decoration: const BoxDecoration(
              color: Color(0xFFF59E0B),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.timer_rounded, color: Colors.white, size: 16),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: RichText(
              text: TextSpan(
                style: const TextStyle(fontSize: 12.5, color: Color(0xFF92400E)),
                children: [
                  const TextSpan(text: 'Đang tạm giữ chỗ: '),
                  TextSpan(
                    text: '$mins:$secs',
                    style: const TextStyle(
                      fontWeight: FontWeight.w900,
                      color: Color(0xFFB45309),
                      fontSize: 14,
                    ),
                  ),
                  const TextSpan(text: ' • Vui lòng xác nhận trước khi hết giờ!'),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDateRibbon() {
    final now = DateTime.now();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(Icons.calendar_today_rounded, size: 15, color: AppColors.primary),
                  SizedBox(width: 6),
                  Text(
                    'Chọn ngày đặt sân',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: Color(0xFF1E293B)),
                  ),
                ],
              ),
              InkWell(
                onTap: () async {
                  final picked = await showDatePicker(
                    context: context,
                    initialDate: _selectedDate,
                    firstDate: DateTime.now(),
                    lastDate: DateTime.now().add(const Duration(days: 60)),
                    builder: (context, child) {
                      return Theme(
                        data: Theme.of(context).copyWith(
                          colorScheme: const ColorScheme.light(
                            primary: AppColors.primary,
                            onPrimary: Colors.white,
                            onSurface: Color(0xFF1E293B),
                          ),
                        ),
                        child: child!,
                      );
                    },
                  );
                  if (picked != null) {
                    _releaseCurrentLock();
                    setState(() {
                      _selectedDate = picked;
                      _selectedSlotIndexes.clear();
                    });
                    _setupRealtimeSubscription();
                    _loadAvailability();
                  }
                },
                borderRadius: BorderRadius.circular(8),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF0F5),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.edit_calendar_rounded, size: 13, color: AppColors.primary),
                      SizedBox(width: 3),
                      Text(
                        'Lịch tháng',
                        style: TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w800,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 54,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              itemCount: 21,
              itemBuilder: (context, index) {
                final date = now.add(Duration(days: index));
                final isSelected = DateFormat('yyyy-MM-dd').format(date) == _dateParam;
                final dayLabel = index == 0
                    ? 'Hôm nay'
                    : index == 1
                        ? 'Ngày mai'
                        : 'T${date.weekday == 7 ? 'CN' : date.weekday + 1}';

                return GestureDetector(
                  onTap: () {
                    _releaseCurrentLock();
                    setState(() {
                      _selectedDate = date;
                      _selectedSlotIndexes.clear();
                    });
                    _setupRealtimeSubscription();
                    _loadAvailability();
                  },
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    width: 58,
                    margin: const EdgeInsets.only(right: 6),
                    decoration: BoxDecoration(
                      gradient: isSelected
                          ? const LinearGradient(
                              colors: [Color(0xFFE63B6F), Color(0xFFFF6584)],
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                            )
                          : null,
                      color: isSelected ? null : const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: isSelected ? Colors.transparent : const Color(0xFFE2E8F0),
                      ),
                      boxShadow: isSelected
                          ? [
                              BoxShadow(
                                color: AppColors.primary.withValues(alpha: 0.28),
                                blurRadius: 6,
                                offset: const Offset(0, 2),
                              ),
                            ]
                          : null,
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          dayLabel,
                          style: TextStyle(
                            fontSize: 9.5,
                            fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                            color: isSelected ? Colors.white.withValues(alpha: 0.95) : const Color(0xFF64748B),
                          ),
                        ),
                        const SizedBox(height: 1),
                        Text(
                          '${date.day}',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w900,
                            color: isSelected ? Colors.white : const Color(0xFF1E293B),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildExtraServices() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.add_shopping_cart_rounded, size: 16, color: AppColors.primary),
              ),
              const SizedBox(width: 8),
              const Text(
                'Dịch vụ & Thiết bị thuê thêm',
                style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w800, color: Color(0xFF1E293B)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ..._extraServices.map((service) {
            final quantity = _serviceQuantities[service.id] ?? 0;
            final lowerName = service.name.toLowerCase();

            IconData sIcon = Icons.sports_tennis_rounded;
            if (lowerName.contains('cầu')) {
              sIcon = Icons.sports_volleyball_outlined;
            } else if (lowerName.contains('nước')) {
              sIcon = Icons.local_drink_rounded;
            }

            return Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              decoration: BoxDecoration(
                color: quantity > 0 ? const Color(0xFFFFF0F5) : const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: quantity > 0 ? const Color(0xFFFFB6C1) : const Color(0xFFE2E8F0),
                ),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(7),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(sIcon, size: 18, color: AppColors.primary),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          service.name,
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF1E293B)),
                        ),
                        Text(
                          '${_money.format(service.unitPrice)} / ${service.unit}',
                          style: const TextStyle(color: AppColors.primary, fontSize: 11.5, fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                  ),
                  StepperCounter(
                    value: quantity,
                    onChanged: (val) {
                      setState(() {
                        if (val <= 0) {
                          _serviceQuantities.remove(service.id);
                        } else {
                          _serviceQuantities[service.id] = val;
                        }
                      });
                    },
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildMyBookingsTab() {
    final loggedIn = context.watch<AuthProvider>().isAuthenticated;
    if (!loggedIn) {
      return AppEmptyState(
        icon: Icons.lock_outline_rounded,
        title: 'Bạn chưa đăng nhập',
        message: 'Đăng nhập để xem lịch sử đặt sân của bạn.',
        buttonText: 'Đăng nhập',
        onAction: () => context.push('/login'),
      );
    }

    final filteredList = _myBookings.where((b) {
      if (_myBookingFilter == 'all') return true;
      if (_myBookingFilter == 'checked_in') {
        return b.status == 'checked_in' || b.status == 'playing' || b.status == 'extended';
      }
      return b.status == _myBookingFilter;
    }).toList();

    final filterOptions = [
      {'key': 'all', 'label': 'Tất cả'},
      {'key': 'pending', 'label': 'Chờ xác nhận'},
      {'key': 'confirmed', 'label': 'Đã xác nhận'},
      {'key': 'checked_in', 'label': 'Đang chơi'},
      {'key': 'completed', 'label': 'Hoàn thành'},
      {'key': 'cancelled', 'label': 'Đã hủy'},
    ];

    return Column(
      children: [
        // Filter Ribbon
        Container(
          color: Colors.white,
          height: 48,
          child: ListView.separated(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            scrollDirection: Axis.horizontal,
            itemCount: filterOptions.length,
            separatorBuilder: (_, _) => const SizedBox(width: 8),
            itemBuilder: (context, index) {
              final opt = filterOptions[index];
              final isSelected = _myBookingFilter == opt['key'];
              return GestureDetector(
                onTap: () {
                  setState(() => _myBookingFilter = opt['key']!);
                },
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                  decoration: BoxDecoration(
                    color: isSelected ? AppColors.primary : const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Center(
                    child: Text(
                      opt['label']!,
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                        color: isSelected ? Colors.white : const Color(0xFF64748B),
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
        const Divider(height: 1, color: Color(0xFFF1F5F9)),

        Expanded(
          child: filteredList.isEmpty
              ? _myBookings.isEmpty
                  ? AppEmptyState(
                      icon: Icons.sports_tennis_rounded,
                      title: 'Chưa có lịch đặt sân',
                      message: 'Bạn chưa đặt sân nào. Hãy chọn sân và khung giờ phù hợp nhé!',
                      buttonText: 'Đặt sân ngay',
                      onAction: () {
                        DefaultTabController.of(context).animateTo(0);
                      },
                    )
                  : AppEmptyState(
                      icon: Icons.filter_alt_off_rounded,
                      title: 'Không có đơn phù hợp',
                      message: 'Không tìm thấy đơn đặt sân nào thuộc bộ lọc này.',
                      buttonText: 'Tất cả đơn',
                      onAction: () => setState(() => _myBookingFilter = 'all'),
                    )
              : RefreshIndicator(
                  color: AppColors.primary,
                  onRefresh: _loadMyBookings,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: filteredList.length,
                    itemBuilder: (context, index) {
                      final booking = filteredList[index];
                      return BookingCard(
                        booking: booking,
                        money: _money,
                        onShowQr: () => _showQrCheckInDialog(booking),
                        onPay: () {
                          AppToast.showInfo(
                            context,
                            message: 'Vui lòng thanh toán trực tiếp tại quầy hoặc liên hệ nhân viên!',
                          );
                        },
                        onCancel: () => _cancelMyBooking(booking.id),
                      );
                    },
                  ),
                ),
        ),
      ],
    );
  }

  void _showQrCheckInDialog(CourtBooking booking) {
    final courtName = booking.courtName ?? 'Sân #${booking.courtId}';
    final dateStr = DateFormat('dd/MM/yyyy').format(DateTime.tryParse(booking.date) ?? DateTime.now());
    final timeStr = '${booking.startTime} - ${booking.endTime}';

    QrCheckInDialog.show(
      context,
      bookingId: booking.id,
      bookingCode: booking.code,
      courtName: courtName,
      dateStr: dateStr,
      timeStr: timeStr,
    );
  }

  Future<void> _cancelMyBooking(int bookingId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Xác nhận hủy đặt sân'),
        content: const Text('Bạn có chắc chắn muốn hủy đơn đặt sân này không?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Không'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.error),
            child: const Text('Hủy sân', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      await _service.cancelBooking(bookingId, reason: 'Khách hàng tự hủy trên mobile');
      if (!mounted) return;
      AppToast.showSuccess(context, message: 'Đã hủy đặt sân thành công');
      _loadMyBookings();
      _loadAvailability();
    } catch (e) {
      if (mounted) {
        AppToast.showError(context, message: _service.errorMessage(e));
      }
    }
  }

  Widget _buildStaffTab() {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: TextField(
            controller: _staffSearchController,
            onSubmitted: (_) => _loadStaffBookings(),
            decoration: InputDecoration(
              hintText: 'Tìm theo mã đơn, tên khách, SĐT...',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: IconButton(
                icon: const Icon(Icons.search),
                onPressed: _loadStaffBookings,
              ),
            ),
          ),
        ),
        Expanded(
          child: _staffBookings.isEmpty
              ? AppEmptyState(
                  icon: Icons.search_off_rounded,
                  title: 'Không tìm thấy đơn nào',
                  message: 'Không có đơn đặt sân nào phù hợp với bộ lọc.',
                  buttonText: 'Tải lại',
                  onAction: _loadStaffBookings,
                )
              : RefreshIndicator(
                  color: AppColors.primary,
                  onRefresh: _loadStaffBookings,
                  child: ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: _staffBookings.length,
                    itemBuilder: (context, index) {
                      final booking = _staffBookings[index];
                      return BookingCard(
                        booking: booking,
                        money: _money,
                        staffMode: true,
                        onConfirm: () => _staffConfirm(booking.id),
                        onCheckIn: () => _staffCheckIn(booking.id),
                        onCheckout: () => _staffCheckOut(booking.id),
                        onStaffCancel: () => _staffCancel(booking.id),
                      );
                    },
                  ),
                ),
        ),
      ],
    );
  }

  Future<void> _staffConfirm(int id) async {
    try {
      await _service.confirmBooking(id);
      _loadStaffBookings();
    } catch (e) {
      if (mounted) AppToast.showError(context, message: _service.errorMessage(e));
    }
  }

  Future<void> _staffCheckIn(int id) async {
    try {
      await _service.checkIn(id);
      _loadStaffBookings();
    } catch (e) {
      if (mounted) AppToast.showError(context, message: _service.errorMessage(e));
    }
  }

  Future<void> _staffCheckOut(int id) async {
    try {
      await _service.checkOut(bookingId: id);
      _loadStaffBookings();
    } catch (e) {
      if (mounted) AppToast.showError(context, message: _service.errorMessage(e));
    }
  }

  Future<void> _staffCancel(int id) async {
    try {
      await _service.staffCancelBooking(id, reason: 'Staff hủy');
      _loadStaffBookings();
    } catch (e) {
      if (mounted) AppToast.showError(context, message: _service.errorMessage(e));
    }
  }
}
