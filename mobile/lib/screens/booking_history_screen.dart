import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../config/app_theme.dart';
import '../models/court_booking_models.dart';
import '../services/api_client.dart';
import 'court_booking/widgets/booking_card.dart';
import 'court_booking/widgets/qr_check_in_dialog.dart';

class BookingHistoryScreen extends StatefulWidget {
  const BookingHistoryScreen({super.key});

  @override
  State<BookingHistoryScreen> createState() => _BookingHistoryScreenState();
}

class _BookingHistoryScreenState extends State<BookingHistoryScreen>
    with SingleTickerProviderStateMixin {
  final NumberFormat _money = NumberFormat.currency(locale: 'vi_VN', symbol: '₫');
  List<CourtBooking> bookings = [];
  bool isLoading = true;
  String? errorMessage;
  String selectedFilter = 'all';

  late TabController _tabController;

  final List<Map<String, String>> _filterTabs = [
    {'key': 'all', 'label': 'Tất cả'},
    {'key': 'pending', 'label': 'Chờ duyệt'},
    {'key': 'confirmed', 'label': 'Đã xác nhận'},
    {'key': 'checked_in', 'label': 'Đang chơi'},
    {'key': 'completed', 'label': 'Hoàn thành'},
    {'key': 'cancelled', 'label': 'Đã hủy'},
  ];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _filterTabs.length, vsync: this);
    _tabController.addListener(() {
      if (!_tabController.indexIsChanging) {
        setState(() {
          selectedFilter = _filterTabs[_tabController.index]['key']!;
        });
      }
    });
    fetchBookings();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> fetchBookings() async {
    try {
      setState(() {
        isLoading = true;
        errorMessage = null;
      });
      final res = await ApiClient().dio.get('/court-bookings');
      final data = res.data;
      List<dynamic> list = [];
      if (data is List) {
        list = data;
      } else if (data['data'] is List) {
        list = data['data'];
      } else if (data['data'] is Map && data['data']['data'] is List) {
        list = data['data']['data'];
      }

      final parsed = list
          .whereType<Map>()
          .map((item) => CourtBooking.fromJson(Map<String, dynamic>.from(item)))
          .toList();

      if (mounted) {
        setState(() {
          bookings = parsed;
          isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          errorMessage = 'Không thể tải lịch sử đặt sân';
          isLoading = false;
        });
      }
    }
  }

  List<CourtBooking> get filteredBookings {
    if (selectedFilter == 'all') return bookings;
    return bookings.where((b) {
      if (selectedFilter == 'checked_in') {
        return b.status == 'checked_in' || b.status == 'playing' || b.status == 'extended';
      }
      return b.status == selectedFilter;
    }).toList();
  }

  Future<void> _cancelBooking(int bookingId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.warning_amber_rounded, color: AppColors.error, size: 28),
            SizedBox(width: 10),
            Text('Hủy lịch đặt sân?', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
          ],
        ),
        content: const Text(
          'Bạn có chắc chắn muốn hủy lịch đặt sân này không? Chỗ sẽ được mở lại cho người khác.',
          style: TextStyle(fontSize: 14, color: AppColors.textSecondary),
        ),
        actionsPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        actions: [
          TextButton(
            onPressed: () => context.pop(false),
            child: const Text('Giữ lại', style: TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF64748B))),
          ),
          ElevatedButton(
            onPressed: () => context.pop(true),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.error,
              foregroundColor: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: const Text('Xác nhận hủy', style: TextStyle(fontWeight: FontWeight.w800)),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    try {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (_) => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
      );
      await ApiClient().dio.post('/court-bookings/$bookingId/cancel', data: {'reason': 'Khách hàng yêu cầu hủy trên app'});
      if (mounted) {
        context.pop(); // dismiss loading
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('Đã hủy lịch đặt sân thành công!'),
            backgroundColor: AppColors.success,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
        fetchBookings();
      }
    } catch (e) {
      if (mounted) {
        context.pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('Không thể hủy lịch đặt sân. Vui lòng thử lại!'),
            backgroundColor: AppColors.error,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
      }
    }
  }

  void _showQrCheckIn(CourtBooking booking) {
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

  @override
  Widget build(BuildContext context) {
    final list = filteredBookings;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text(
          'Lịch Sử Đặt Sân',
          style: TextStyle(
            color: Color(0xFF0F172A),
            fontWeight: FontWeight.w900,
            fontSize: 18,
          ),
        ),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF0F172A),
        iconTheme: const IconThemeData(color: Color(0xFF0F172A)),
        elevation: 0,
        centerTitle: true,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Container(
            color: Colors.white,
            child: TabBar(
              controller: _tabController,
              isScrollable: true,
              tabAlignment: TabAlignment.start,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              indicatorColor: AppColors.primary,
              indicatorWeight: 3,
              indicatorSize: TabBarIndicatorSize.label,
              labelColor: AppColors.primary,
              unselectedLabelColor: const Color(0xFF64748B),
              labelStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5),
              unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5),
              tabs: _filterTabs.map((t) => Tab(text: t['label'])).toList(),
            ),
          ),
        ),
      ),
      body: RefreshIndicator(
        color: AppColors.primary,
        onRefresh: fetchBookings,
        child: isLoading
            ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
            : errorMessage != null
                ? _buildError()
                : list.isEmpty
                    ? _buildEmpty()
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
                        itemCount: list.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 14),
                        itemBuilder: (context, index) {
                          final booking = list[index];
                          return BookingCard(
                            booking: booking,
                            money: _money,
                            onShowQr: () => _showQrCheckIn(booking),
                            onPay: () {
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                  content: Text('Vui lòng thanh toán trực tiếp tại quầy hoặc qua chuyển khoản!'),
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                            },
                            onCancel: () => _cancelBooking(booking.id),
                          );
                        },
                      ),
      ),
    );
  }

  Widget _buildError() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: const BoxDecoration(
                color: Color(0xFFFEE2E2),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.error_outline_rounded, size: 48, color: AppColors.error),
            ),
            const SizedBox(height: 16),
            Text(
              errorMessage!,
              style: const TextStyle(color: Color(0xFF64748B), fontSize: 14, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: fetchBookings,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text('Thử lại'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmpty() {
    return Center(
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: const BoxDecoration(
                color: AppColors.primaryContainer,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.sports_tennis_rounded, size: 52, color: AppColors.primary),
            ),
            const SizedBox(height: 20),
            const Text(
              'Chưa có đơn đặt sân nào',
              style: TextStyle(fontSize: 17, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
            ),
            const SizedBox(height: 8),
            const Text(
              'Khám phá hệ thống sân bãi hiện đại và đặt lịch chơi ngay hôm nay nhé!',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 13.5, color: Color(0xFF64748B), height: 1.4),
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: () => context.pop(),
              icon: const Icon(Icons.calendar_month_rounded, size: 18),
              label: const Text('Đặt sân ngay'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                elevation: 0,
                padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
