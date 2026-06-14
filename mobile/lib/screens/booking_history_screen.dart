import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../config/app_theme.dart';
import '../config/app_config.dart';

class BookingHistoryScreen extends StatefulWidget {
  const BookingHistoryScreen({super.key});

  @override
  State<BookingHistoryScreen> createState() => _BookingHistoryScreenState();
}

class _BookingHistoryScreenState extends State<BookingHistoryScreen> {
  List<dynamic> bookings = [];
  bool isLoading = true;
  String? errorMessage;

  @override
  void initState() {
    super.initState();
    fetchBookings();
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
      if (mounted) setState(() { bookings = list; isLoading = false; });
    } catch (e) {
      if (mounted) setState(() { errorMessage = 'Không thể tải lịch sử đặt sân'; isLoading = false; });
    }
  }

  Future<void> _cancelBooking(dynamic bookingId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Hủy lịch đặt sân?', style: TextStyle(fontWeight: FontWeight.w700)),
        content: const Text('Bạn có chắc chắn muốn hủy lịch đặt sân này không?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Giữ lại'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.error, foregroundColor: Colors.white),
            child: const Text('Xác nhận hủy'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      showDialog(context: context, barrierDismissible: false, builder: (_) => const Center(child: CircularProgressIndicator()));
      await ApiClient().dio.post('/court-bookings/$bookingId/cancel', data: {'reason': 'Khách hàng yêu cầu hủy'});
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: const Text('Hủy lịch thành công'), backgroundColor: AppColors.success, behavior: SnackBarBehavior.floating, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
        );
        fetchBookings();
      }
    } catch (e) {
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: const Text('Không thể hủy lịch đặt sân'), backgroundColor: AppColors.error, behavior: SnackBarBehavior.floating, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
        );
      }
    }
  }

  String _formatCurrency(dynamic value) {
    try {
      final num p = num.parse(value.toString());
      final formatted = p.toStringAsFixed(0).replaceAllMapped(
        RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
        (m) => '${m[1]}.',
      );
      return '${formatted}₫';
    } catch (_) {
      return '0₫';
    }
  }

  String _formatDate(dynamic value) {
    if (value == null) return '';
    final d = DateTime.tryParse(value.toString());
    if (d == null) return value.toString();
    return '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}';
  }

  String _formatTime(dynamic value) {
    if (value == null) return '';
    final s = value.toString();
    return s.length >= 5 ? s.substring(0, 5) : s;
  }

  String _getStatusLabel(String? status) {
    switch (status) {
      case 'pending': return 'Chờ duyệt';
      case 'confirmed': return 'Đã xác nhận';
      case 'checked_in': return 'Đang chơi';
      case 'completed': return 'Hoàn thành';
      case 'cancelled': return 'Đã hủy';
      case 'no_show': return 'Không đến';
      case 'extended': return 'Đã gia hạn';
      default: return status ?? 'Không rõ';
    }
  }

  Color _getStatusColor(String? status) {
    switch (status) {
      case 'pending': return const Color(0xFFD97706);
      case 'confirmed': return const Color(0xFF2563EB);
      case 'checked_in': return const Color(0xFF059669);
      case 'completed': return AppColors.success;
      case 'cancelled': return AppColors.error;
      case 'no_show': return const Color(0xFFDC2626);
      case 'extended': return const Color(0xFF7C3AED);
      default: return const Color(0xFF475569);
    }
  }

  Color _getStatusBg(String? status) {
    switch (status) {
      case 'pending': return const Color(0xFFFEF3C7);
      case 'confirmed': return const Color(0xFFDBEAFE);
      case 'checked_in': return const Color(0xFFD1FAE5);
      case 'completed': return const Color(0xFFDCFCE7);
      case 'cancelled': return const Color(0xFFFEE2E2);
      case 'no_show': return const Color(0xFFFEE2E2);
      case 'extended': return const Color(0xFFEDE9FE);
      default: return const Color(0xFFF1F5F9);
    }
  }

  IconData _getStatusIcon(String? status) {
    switch (status) {
      case 'pending': return Icons.hourglass_top;
      case 'confirmed': return Icons.check_circle_outline;
      case 'checked_in': return Icons.play_circle_outline;
      case 'completed': return Icons.check_circle;
      case 'cancelled': return Icons.cancel_outlined;
      case 'no_show': return Icons.error_outline;
      case 'extended': return Icons.update;
      default: return Icons.help_outline;
    }
  }

  int _getStepIndex(String? status) {
    switch (status) {
      case 'pending': return 0;
      case 'confirmed': return 1;
      case 'checked_in': return 2;
      case 'completed': return 3;
      default: return -1;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Lịch Sử Đặt Sân'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
      ),
      body: RefreshIndicator(
        onRefresh: fetchBookings,
        child: isLoading
            ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
            : errorMessage != null
                ? _buildError()
                : bookings.isEmpty
                    ? _buildEmpty()
                    : ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: bookings.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 14),
                        itemBuilder: (context, index) => _buildBookingCard(bookings[index]),
                      ),
      ),
    );
  }

  Widget _buildError() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.error_outline, size: 48, color: Colors.grey),
          const SizedBox(height: 12),
          Text(errorMessage!, style: const TextStyle(color: Colors.grey)),
          const SizedBox(height: 16),
          ElevatedButton(onPressed: fetchBookings, child: const Text('Thử lại')),
        ],
      ),
    );
  }

  Widget _buildEmpty() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: const Color(0xFFFFF0F3),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.sports_tennis, size: 48, color: AppColors.primary),
          ),
          const SizedBox(height: 20),
          const Text('Bạn chưa có lịch đặt sân nào', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Color(0xFF0F172A))),
          const SizedBox(height: 8),
          const Text('Hãy đặt sân đầu tiên để bắt đầu trải nghiệm!', style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
          const SizedBox(height: 24),
          ElevatedButton.icon(
            onPressed: () => Navigator.pop(context),
            icon: const Icon(Icons.sports_tennis, size: 18),
            label: const Text('Đặt sân ngay'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
              elevation: 0,
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBookingCard(Map<String, dynamic> booking) {
    final status = booking['status']?.toString();
    final court = booking['court'] as Map<String, dynamic>?;
    final courtName = court?['court_name'] ?? court?['name'] ?? 'Sân cầu lông';
    final bookingCode = booking['booking_code'] ?? '#${booking['booking_id'] ?? booking['id']}';
    final bookingDate = _formatDate(booking['booking_date']);
    final startTime = _formatTime(booking['start_time']);
    final endTime = _formatTime(booking['end_time']);
    final totalAmount = booking['total_amount'] ?? 0;
    final paymentStatus = booking['payment_status']?.toString();
    final stepIndex = _getStepIndex(status);

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Column(
        children: [
          // Header
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFF8F9FB),
              borderRadius: const BorderRadius.only(topLeft: Radius.circular(16), topRight: Radius.circular(16)),
              border: const Border(bottom: BorderSide(color: Color(0xFFF1F3F5))),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Mã: $bookingCode', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF0F172A))),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: _getStatusBg(status),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: _getStatusColor(status).withOpacity(0.2)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(_getStatusIcon(status), size: 12, color: _getStatusColor(status)),
                      const SizedBox(width: 4),
                      Text(_getStatusLabel(status), style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: _getStatusColor(status))),
                    ],
                  ),
                ),
              ],
            ),
          ),
          // Body
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                Row(
                  children: [
                    Container(
                      width: 48, height: 48,
                      decoration: BoxDecoration(
                        color: AppColors.primary.withOpacity(0.08),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(Icons.sports_tennis, color: AppColors.primary, size: 24),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(courtName, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: Color(0xFF0F172A))),
                          const SizedBox(height: 4),
                          Row(
                            children: [
                              const Icon(Icons.calendar_today, size: 13, color: Color(0xFF64748B)),
                              const SizedBox(width: 4),
                              Text(bookingDate, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                              const SizedBox(width: 8),
                              const Text('•', style: TextStyle(color: Color(0xFFCBD5E1))),
                              const SizedBox(width: 8),
                              const Icon(Icons.access_time, size: 13, color: Color(0xFF64748B)),
                              const SizedBox(width: 4),
                              Text('$startTime - $endTime', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                            ],
                          ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        const Text('Tổng tiền', style: TextStyle(fontSize: 10, color: Color(0xFF94A3B8))),
                        const SizedBox(height: 2),
                        Text(_formatCurrency(totalAmount), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: AppColors.primary)),
                      ],
                    ),
                  ],
                ),
                // Progress steps
                if (status != 'cancelled' && status != 'no_show' && stepIndex >= 0) ...[
                  const SizedBox(height: 16),
                  _buildProgressSteps(stepIndex),
                ],
              ],
            ),
          ),
          // Footer actions
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: const BoxDecoration(
              border: Border(top: BorderSide(color: Color(0xFFF1F3F5))),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                if (paymentStatus != 'paid' && status != 'cancelled') ...[
                  _actionButton('Thanh toán', Icons.credit_card, const Color(0xFF059669), () {}),
                  const SizedBox(width: 8),
                ],
                if (status == 'pending' || status == 'confirmed') ...[
                  _actionButton('QR Check-in', Icons.qr_code, AppColors.info, () {}),
                  const SizedBox(width: 8),
                  _actionButton('Hủy', Icons.close, AppColors.error, () => _cancelBooking(booking['booking_id'] ?? booking['id'])),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _actionButton(String label, IconData icon, Color color, VoidCallback onTap) {
    return OutlinedButton.icon(
      onPressed: onTap,
      icon: Icon(icon, size: 14, color: color),
      label: Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: color)),
      style: OutlinedButton.styleFrom(
        side: BorderSide(color: color, width: 1.2),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      ),
    );
  }

  Widget _buildProgressSteps(int currentStep) {
    final steps = ['Đặt', 'Xác nhận', 'Chơi', 'Xong'];
    return Row(
      children: List.generate(steps.length * 2 - 1, (i) {
        if (i.isOdd) {
          // Connector line
          final stepBefore = i ~/ 2;
          return Expanded(
            child: Container(
              height: 2,
              color: currentStep > stepBefore ? AppColors.primary : const Color(0xFFE2E8F0),
            ),
          );
        } else {
          final stepIdx = i ~/ 2;
          final isActive = currentStep >= stepIdx;
          return Column(
            children: [
              Container(
                width: 22, height: 22,
                decoration: BoxDecoration(
                  color: isActive ? AppColors.primary : const Color(0xFFE2E8F0),
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Text('${stepIdx + 1}', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: isActive ? Colors.white : const Color(0xFF94A3B8))),
                ),
              ),
              const SizedBox(height: 4),
              Text(steps[stepIdx], style: TextStyle(fontSize: 9, fontWeight: FontWeight.w600, color: isActive ? AppColors.primary : const Color(0xFF94A3B8))),
            ],
          );
        }
      }),
    );
  }
}
