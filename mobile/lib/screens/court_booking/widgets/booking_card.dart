import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../config/app_theme.dart';
import '../../../models/court_booking_models.dart';
import 'court_booking_chips.dart';

/// Card hiển thị một booking (dùng chung tab "Lịch của tôi" và tab nhân viên).
/// Mọi hành động được truyền lên qua callback; State cha giữ logic gọi service.
class BookingCard extends StatelessWidget {
  final CourtBooking booking;
  final bool staffMode;
  final NumberFormat money;

  // Hành động của khách.
  final VoidCallback onShowQr;
  final VoidCallback onPay;
  final VoidCallback onCancel;

  // Hành động của nhân viên.
  final VoidCallback onConfirm;
  final VoidCallback onCheckIn;
  final VoidCallback onQrCheckIn;
  final VoidCallback onAddService;
  final VoidCallback onExtend;
  final VoidCallback onStaffPay;
  final VoidCallback onCheckout;
  final VoidCallback onStaffCancel;

  const BookingCard({
    super.key,
    required this.booking,
    required this.staffMode,
    required this.money,
    required this.onShowQr,
    required this.onPay,
    required this.onCancel,
    required this.onConfirm,
    required this.onCheckIn,
    required this.onQrCheckIn,
    required this.onAddService,
    required this.onExtend,
    required this.onStaffPay,
    required this.onCheckout,
    required this.onStaffCancel,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: courtCardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  booking.code,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              BookingStatusChip(booking.status),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            '${booking.courtName ?? 'San #${booking.courtId}'} - ${_formatDate(booking.date)}',
          ),
          const SizedBox(height: 4),
          Text(
            '${booking.startTime} - ${booking.endTime}  |  ${booking.durationMinutes} phut',
            style: const TextStyle(color: AppColors.textSecondary),
          ),
          if (booking.customerName != null || booking.customerPhone != null) ...[
            const SizedBox(height: 4),
            Text(
              '${booking.customerName ?? 'Khach'} ${booking.customerPhone ?? ''}',
              style: const TextStyle(color: AppColors.textSecondary),
            ),
          ],
          const Divider(height: 20),
          Row(
            children: [
              Expanded(
                child: Text(
                  '${money.format(booking.paidAmount)} / ${money.format(booking.totalAmount)}',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              PaymentStatusChip(booking.paymentStatus),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: staffMode ? _staffActions() : _customerActions(),
          ),
        ],
      ),
    );
  }

  List<Widget> _customerActions() {
    return [
      OutlinedButton.icon(
        onPressed: onShowQr,
        icon: const Icon(Icons.qr_code_2),
        label: const Text('QR'),
      ),
      if (booking.amountDue > 0 &&
          !['cancelled', 'completed', 'expired'].contains(booking.status))
        OutlinedButton.icon(
          onPressed: onPay,
          icon: const Icon(Icons.payments_outlined),
          label: const Text('Thanh toan'),
        ),
      if (['pending', 'confirmed'].contains(booking.status))
        TextButton.icon(
          onPressed: onCancel,
          icon: const Icon(Icons.cancel_outlined),
          label: const Text('Huy'),
        ),
    ];
  }

  List<Widget> _staffActions() {
    return [
      if (booking.status == 'pending')
        ElevatedButton.icon(
          onPressed: onConfirm,
          icon: const Icon(Icons.verified_outlined),
          label: const Text('Xac nhan'),
        ),
      if (booking.status == 'confirmed')
        ElevatedButton.icon(
          onPressed: onCheckIn,
          icon: const Icon(Icons.login),
          label: const Text('Check-in'),
        ),
      if (booking.status == 'confirmed')
        OutlinedButton.icon(
          onPressed: onQrCheckIn,
          icon: const Icon(Icons.qr_code_scanner),
          label: const Text('QR in'),
        ),
      if (['checked_in', 'playing', 'extended'].contains(booking.status))
        OutlinedButton.icon(
          onPressed: onAddService,
          icon: const Icon(Icons.add_shopping_cart),
          label: const Text('Dich vu'),
        ),
      if (['checked_in', 'playing', 'extended'].contains(booking.status))
        OutlinedButton.icon(
          onPressed: onExtend,
          icon: const Icon(Icons.more_time),
          label: const Text('Gia han'),
        ),
      if (booking.amountDue > 0 &&
          !['cancelled', 'completed'].contains(booking.status))
        OutlinedButton.icon(
          onPressed: onStaffPay,
          icon: const Icon(Icons.payments_outlined),
          label: const Text('Thu tien'),
        ),
      if (['checked_in', 'playing', 'extended'].contains(booking.status))
        ElevatedButton.icon(
          onPressed: onCheckout,
          icon: const Icon(Icons.logout),
          label: const Text('Check-out'),
        ),
      if (['pending', 'confirmed'].contains(booking.status))
        TextButton.icon(
          onPressed: onStaffCancel,
          icon: const Icon(Icons.cancel_outlined),
          label: const Text('Huy'),
        ),
    ];
  }

  String _formatDate(String date) {
    final parsed = DateTime.tryParse(date);
    return parsed == null ? date : DateFormat('dd/MM/yyyy').format(parsed);
  }
}
