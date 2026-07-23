import 'package:flutter/material.dart';

import '../../../config/app_theme.dart';

/// Nhãn trạng thái slot khung giờ.
String slotStatusLabel(String status) {
  return switch (status) {
    'booked' => 'Da dat',
    'locked' => 'Dang giu',
    'maintenance' => 'Bao tri',
    'past' => 'Da qua',
    'closed' => 'Dong cua',
    _ => status,
  };
}

/// Nhãn trạng thái booking.
String bookingStatusLabel(String status) {
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

/// Nhãn trạng thái thanh toán.
String paymentStatusLabel(String status) {
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

/// Chip màu theo trạng thái booking.
class BookingStatusChip extends StatelessWidget {
  final String status;

  const BookingStatusChip(this.status, {super.key});

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'confirmed' => AppColors.info,
      'checked_in' || 'playing' || 'extended' => AppColors.success,
      'completed' => AppColors.tertiary,
      'cancelled' || 'no_show' || 'expired' => AppColors.error,
      _ => AppColors.warning,
    };
    return Chip(
      label: Text(
        bookingStatusLabel(status),
        style: const TextStyle(color: Colors.white, fontSize: 11),
      ),
      backgroundColor: color,
      visualDensity: VisualDensity.compact,
    );
  }
}

/// Chip màu theo trạng thái thanh toán.
class PaymentStatusChip extends StatelessWidget {
  final String status;

  const PaymentStatusChip(this.status, {super.key});

  @override
  Widget build(BuildContext context) {
    final paid = status == 'paid' || status == 'deposit_paid';
    return Chip(
      label: Text(
        paymentStatusLabel(status),
        style: const TextStyle(color: Colors.white, fontSize: 11),
      ),
      backgroundColor: paid ? AppColors.success : AppColors.warning,
      visualDensity: VisualDensity.compact,
    );
  }
}

/// Khung trang trí card dùng chung trong màn đặt sân.
BoxDecoration courtCardDecoration() {
  return BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(16),
    border: Border.all(color: AppColors.borderLight),
    boxShadow: [
      BoxShadow(
        color: Colors.black.withValues(alpha: 0.03),
        blurRadius: 12,
        offset: const Offset(0, 6),
      ),
    ],
  );
}
