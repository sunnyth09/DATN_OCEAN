import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../../../config/app_theme.dart';
import '../../../models/court_booking_models.dart';
import '../../../widgets/app_toast.dart';
import 'court_booking_chips.dart';

class BookingCard extends StatelessWidget {
  final CourtBooking booking;
  final bool staffMode;
  final NumberFormat money;

  final VoidCallback? onShowQr;
  final VoidCallback? onPay;
  final VoidCallback? onCancel;

  final VoidCallback? onConfirm;
  final VoidCallback? onCheckIn;
  final VoidCallback? onQrCheckIn;
  final VoidCallback? onAddService;
  final VoidCallback? onExtend;
  final VoidCallback? onStaffPay;
  final VoidCallback? onCheckout;
  final VoidCallback? onStaffCancel;

  const BookingCard({
    super.key,
    required this.booking,
    this.staffMode = false,
    required this.money,
    this.onShowQr,
    this.onPay,
    this.onCancel,
    this.onConfirm,
    this.onCheckIn,
    this.onQrCheckIn,
    this.onAddService,
    this.onExtend,
    this.onStaffPay,
    this.onCheckout,
    this.onStaffCancel,
  });

  @override
  Widget build(BuildContext context) {
    final courtName = booking.courtName ?? 'Sân #${booking.courtId}';
    final parsedDate = DateTime.tryParse(booking.date);
    final dateStr = parsedDate != null ? DateFormat('dd/MM/yyyy').format(parsedDate) : booking.date;
    final isCancelled = booking.status == 'cancelled' || booking.status == 'no_show';

    final hasCustomerActions = !staffMode &&
        ((onCancel != null && ['pending', 'confirmed'].contains(booking.status)) ||
            (onShowQr != null && ['pending', 'confirmed', 'checked_in'].contains(booking.status)) ||
            (onPay != null && booking.amountDue > 0 && !['cancelled', 'completed', 'expired'].contains(booking.status)));

    final hasStaffActions = staffMode &&
        ((onStaffCancel != null && ['pending', 'confirmed'].contains(booking.status)) ||
            (onConfirm != null && booking.status == 'pending') ||
            (onCheckIn != null && booking.status == 'confirmed') ||
            (onAddService != null && ['checked_in', 'playing', 'extended'].contains(booking.status)) ||
            (onCheckout != null && ['checked_in', 'playing', 'extended'].contains(booking.status)));

    final showActions = hasCustomerActions || hasStaffActions;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isCancelled ? const Color(0xFFF1F5F9) : const Color(0xFFE2E8F0),
          width: 1,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isCancelled ? 0.015 : 0.035),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ─── 1. HEADER ROW (Court Name & Status) ─────────────────
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Badminton Icon Box
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: isCancelled ? const Color(0xFFF1F5F9) : const Color(0xFFFFF0F5),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: isCancelled ? const Color(0xFFE2E8F0) : const Color(0xFFFFD1DC),
                      width: 1,
                    ),
                  ),
                  child: Center(
                    child: Icon(
                      Icons.sports_tennis_rounded,
                      size: 22,
                      color: isCancelled ? const Color(0xFF94A3B8) : AppColors.primary,
                    ),
                  ),
                ),
                const SizedBox(width: 12),

                // Court Name & Booking Code
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        courtName,
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: isCancelled ? const Color(0xFF64748B) : const Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 3),
                      InkWell(
                        onTap: () {
                          Clipboard.setData(ClipboardData(text: booking.code));
                          AppToast.showSuccess(
                            context,
                            message: 'Đã sao chép mã đặt sân!',
                          );
                        },
                        borderRadius: BorderRadius.circular(4),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              '#${booking.code}',
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: Color(0xFF64748B),
                              ),
                            ),
                            const SizedBox(width: 4),
                            const Icon(Icons.copy_rounded, size: 12, color: Color(0xFF94A3B8)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),

                // Status Badge
                BookingStatusChip(booking.status),
              ],
            ),

            if (staffMode && (booking.customerName != null || booking.customerPhone != null)) ...[
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.person_rounded, size: 14, color: Color(0xFF64748B)),
                    const SizedBox(width: 6),
                    Text(
                      'Khách: ${booking.customerName ?? '---'} • ${booking.customerPhone ?? '---'}',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF334155)),
                    ),
                  ],
                ),
              ),
            ],

            const SizedBox(height: 12),

            // ─── 2. INFO CONTAINER (Schedule & Amount) ────────────────
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFF1F5F9)),
              ),
              child: Column(
                children: [
                  // Row 1: Date & Time
                  Row(
                    children: [
                      const Icon(Icons.calendar_month_rounded, size: 15, color: AppColors.primary),
                      const SizedBox(width: 6),
                      Text(
                        dateStr,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF1E293B),
                        ),
                      ),
                      Container(
                        width: 3.5,
                        height: 3.5,
                        margin: const EdgeInsets.symmetric(horizontal: 8),
                        decoration: const BoxDecoration(
                          color: Color(0xFFCBD5E1),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const Icon(Icons.access_time_filled_rounded, size: 15, color: AppColors.primary),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          '${booking.startTime} - ${booking.endTime} (${booking.durationMinutes}p)',
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF1E293B),
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 8),
                    child: Divider(height: 1, thickness: 1, color: Color(0xFFEEF2F6)),
                  ),
                  // Row 2: Price & Payment Status
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          const Text(
                            'Tổng tiền: ',
                            style: TextStyle(
                              fontSize: 12.5,
                              color: Color(0xFF64748B),
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                          Text(
                            money.format(booking.totalAmount),
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w900,
                              color: isCancelled ? const Color(0xFF94A3B8) : AppColors.primary,
                            ),
                          ),
                        ],
                      ),
                      PaymentStatusChip(booking.paymentStatus),
                    ],
                  ),
                ],
              ),
            ),

            // ─── 3. ACTION BUTTONS ───────────────────────────────────
            if (showActions) ...[
              const SizedBox(height: 12),
              Align(
                alignment: Alignment.centerRight,
                child: Wrap(
                  alignment: WrapAlignment.end,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: 8,
                  runSpacing: 8,
                  children: staffMode ? _staffActions(context) : _customerActions(context),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  List<Widget> _customerActions(BuildContext context) {
    return [
      if (onCancel != null && ['pending', 'confirmed'].contains(booking.status))
        OutlinedButton.icon(
          onPressed: onCancel,
          icon: const Icon(Icons.close_rounded, size: 14, color: AppColors.error),
          label: const Text(
            'Hủy sân',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.error),
          ),
          style: OutlinedButton.styleFrom(
            side: const BorderSide(color: Color(0xFFFECACA)),
            backgroundColor: const Color(0xFFFEF2F2),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
        ),
      if (onShowQr != null && ['pending', 'confirmed', 'checked_in'].contains(booking.status))
        OutlinedButton.icon(
          onPressed: onShowQr,
          icon: const Icon(Icons.qr_code_2_rounded, size: 15, color: Color(0xFF2563EB)),
          label: const Text(
            'Mã Check-in',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF2563EB)),
          ),
          style: OutlinedButton.styleFrom(
            backgroundColor: const Color(0xFFEFF6FF),
            foregroundColor: const Color(0xFF2563EB),
            side: const BorderSide(color: Color(0xFFBFDBFE)),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
        ),
      if (onPay != null &&
          booking.amountDue > 0 &&
          !['cancelled', 'completed', 'expired'].contains(booking.status))
        ElevatedButton.icon(
          onPressed: onPay,
          icon: const Icon(Icons.payment_rounded, size: 14, color: Colors.white),
          label: const Text(
            'Thanh toán',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Colors.white),
          ),
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.primary,
            foregroundColor: Colors.white,
            elevation: 0,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
        ),
    ];
  }

  List<Widget> _staffActions(BuildContext context) {
    return [
      if (onStaffCancel != null && ['pending', 'confirmed'].contains(booking.status))
        OutlinedButton.icon(
          onPressed: onStaffCancel,
          icon: const Icon(Icons.close_rounded, size: 14, color: AppColors.error),
          label: const Text('Hủy',
              style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: AppColors.error)),
          style: OutlinedButton.styleFrom(
            side: const BorderSide(color: Color(0xFFFECACA)),
            backgroundColor: const Color(0xFFFEF2F2),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
        ),
      if (onConfirm != null && booking.status == 'pending')
        ElevatedButton.icon(
          onPressed: onConfirm,
          icon: const Icon(Icons.verified_rounded, size: 15, color: Colors.white),
          label: const Text('Xác nhận',
              style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800, color: Colors.white)),
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF2563EB),
            elevation: 0,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
        ),
      if (onCheckIn != null && booking.status == 'confirmed')
        ElevatedButton.icon(
          onPressed: onCheckIn,
          icon: const Icon(Icons.login_rounded, size: 15, color: Colors.white),
          label: const Text('Check-in',
              style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800, color: Colors.white)),
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF059669),
            elevation: 0,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
        ),
      if (onAddService != null &&
          ['checked_in', 'playing', 'extended'].contains(booking.status))
        OutlinedButton.icon(
          onPressed: onAddService,
          icon: const Icon(Icons.add_circle_outline_rounded, size: 14, color: Color(0xFF7C3AED)),
          label: const Text('+ Dịch vụ',
              style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: Color(0xFF7C3AED))),
          style: OutlinedButton.styleFrom(
            side: const BorderSide(color: Color(0xFFDDD6FE)),
            backgroundColor: const Color(0xFFF5F3FF),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
        ),
      if (onCheckout != null &&
          ['checked_in', 'playing', 'extended'].contains(booking.status))
        ElevatedButton.icon(
          onPressed: onCheckout,
          icon: const Icon(Icons.logout_rounded, size: 15, color: Colors.white),
          label: const Text('Check-out',
              style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w800, color: Colors.white)),
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFFD97706),
            elevation: 0,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
        ),
    ];
  }
}
