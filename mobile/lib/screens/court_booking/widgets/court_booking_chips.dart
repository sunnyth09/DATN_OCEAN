import 'package:flutter/material.dart';

/// Nhãn trạng thái slot khung giờ.
String slotStatusLabel(String status) {
  return switch (status) {
    'available' => 'Trống',
    'booked' => 'Đã đặt',
    'locked' => 'Đang giữ',
    'maintenance' => 'Bảo trì',
    'past' => 'Đã qua',
    'closed' => 'Đóng cửa',
    _ => status,
  };
}

/// Nhãn trạng thái booking.
String bookingStatusLabel(String status) {
  return switch (status) {
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'checked_in' => 'Đã check-in',
    'playing' => 'Đang chơi',
    'extended' => 'Đã gia hạn',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy',
    'no_show' => 'Vắng mặt',
    'expired' => 'Hết hạn',
    _ => status,
  };
}

/// Nhãn trạng thái thanh toán.
String paymentStatusLabel(String status) {
  return switch (status) {
    'unpaid' => 'Chưa thanh toán',
    'deposit_paid' => 'Đã đặt cọc',
    'partially_paid' => 'TT một phần',
    'paid' => 'Đã thanh toán',
    'refunded' => 'Đã hoàn tiền',
    'partially_refunded' => 'Hoàn một phần',
    _ => status,
  };
}

/// Chip màu theo trạng thái booking với phong cách Tonal Pill cao cấp.
class BookingStatusChip extends StatelessWidget {
  final String status;

  const BookingStatusChip(this.status, {super.key});

  @override
  Widget build(BuildContext context) {
    final (bg, border, text, icon) = switch (status) {
      'confirmed' => (
          const Color(0xFFEFF6FF),
          const Color(0xFFBFDBFE),
          const Color(0xFF1D4ED8),
          Icons.check_circle_rounded,
        ),
      'checked_in' || 'playing' || 'extended' => (
          const Color(0xFFECFDF5),
          const Color(0xFFA7F3D0),
          const Color(0xFF047857),
          Icons.sports_tennis_rounded,
        ),
      'completed' => (
          const Color(0xFFF0FDF4),
          const Color(0xFFBBF7D0),
          const Color(0xFF15803D),
          Icons.verified_rounded,
        ),
      'cancelled' || 'no_show' || 'expired' => (
          const Color(0xFFFEF2F2),
          const Color(0xFFFECACA),
          const Color(0xFFB91C1C),
          Icons.cancel_rounded,
        ),
      _ => (
          const Color(0xFFFFFBEB),
          const Color(0xFFFDE68A),
          const Color(0xFFB45309),
          Icons.schedule_rounded,
        ),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: border, width: 1),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: text),
          const SizedBox(width: 4.5),
          Text(
            bookingStatusLabel(status),
            style: TextStyle(
              color: text,
              fontSize: 11.5,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.2,
            ),
          ),
        ],
      ),
    );
  }
}

/// Chip màu theo trạng thái thanh toán với phong cách Tonal Pill cao cấp.
class PaymentStatusChip extends StatelessWidget {
  final String status;

  const PaymentStatusChip(this.status, {super.key});

  @override
  Widget build(BuildContext context) {
    final paid = status == 'paid';
    final deposit = status == 'deposit_paid';

    final (bg, border, text, icon) = paid
        ? (
            const Color(0xFFECFDF5),
            const Color(0xFFA7F3D0),
            const Color(0xFF047857),
            Icons.check_circle_rounded,
          )
        : deposit
            ? (
                const Color(0xFFEEF2FF),
                const Color(0xFFC7D2FE),
                const Color(0xFF4338CA),
                Icons.account_balance_wallet_rounded,
              )
            : (
                const Color(0xFFFFFBEB),
                const Color(0xFFFDE68A),
                const Color(0xFFB45309),
                Icons.pending_rounded,
              );

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: border, width: 1),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: text),
          const SizedBox(width: 4),
          Text(
            paymentStatusLabel(status),
            style: TextStyle(
              color: text,
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

/// Khung trang trí card dùng chung trong màn đặt sân.
BoxDecoration courtCardDecoration() {
  return BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(20),
    border: Border.all(color: const Color(0xFFE2E8F0)),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.04),
        blurRadius: 14,
        offset: const Offset(0, 4),
      ),
    ],
  );
}
