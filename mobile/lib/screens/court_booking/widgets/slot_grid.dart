import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../config/app_theme.dart';
import '../../../models/court_booking_models.dart';
import 'court_booking_chips.dart';

/// Lưới khung giờ trong ngày. Thuần trình bày: nhận danh sách slot + index đã chọn,
/// báo chọn/bỏ qua [onToggle].
class SlotGrid extends StatelessWidget {
  final List<CourtSlot> slots;
  final Set<int> selectedIndexes;
  final NumberFormat money;
  final ValueChanged<int> onToggle;

  const SlotGrid({
    super.key,
    required this.slots,
    required this.selectedIndexes,
    required this.money,
    required this.onToggle,
  });

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: slots.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        childAspectRatio: 2.25,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
      ),
      itemBuilder: (context, index) => _slotTile(context, index),
    );
  }

  Widget _slotTile(BuildContext context, int index) {
    final slot = slots[index];
    final selected = selectedIndexes.contains(index) || slot.isMyLock;
    final isLockedByOther = slot.isLocked && !slot.isMyLock;
    final isBooked = slot.isBooked;
    final isPast = slot.isPast;
    final enabled = slot.isAvailable || slot.isMyLock;

    Color bgColor;
    Color borderColor;
    Color titleColor;
    Color subtitleColor;
    String statusText;

    if (selected) {
      bgColor = AppColors.primary;
      borderColor = AppColors.primary;
      titleColor = Colors.white;
      subtitleColor = Colors.white.withValues(alpha: 0.85);
      statusText = 'Đang chọn • ${money.format(slot.price)}';
    } else if (isLockedByOther) {
      bgColor = const Color(0xFFFEF3C7);
      borderColor = const Color(0xFFF59E0B);
      titleColor = const Color(0xFF92400E);
      subtitleColor = const Color(0xFFD97706);
      statusText = '🔒 Đang giữ chỗ';
    } else if (isBooked) {
      bgColor = const Color(0xFFFEE2E2);
      borderColor = const Color(0xFFFECACA);
      titleColor = const Color(0xFF991B1B);
      subtitleColor = const Color(0xFFDC2626);
      statusText = 'Đã đặt';
    } else if (isPast) {
      bgColor = const Color(0xFFF8FAFC);
      borderColor = const Color(0xFFE2E8F0);
      titleColor = const Color(0xFF94A3B8);
      subtitleColor = const Color(0xFF94A3B8);
      statusText = 'Đã qua';
    } else if (slot.isMaintenance || slot.isClosed) {
      bgColor = const Color(0xFFF1F5F9);
      borderColor = const Color(0xFFCBD5E1);
      titleColor = const Color(0xFF64748B);
      subtitleColor = const Color(0xFF64748B);
      statusText = slotStatusLabel(slot.status);
    } else {
      // Available
      bgColor = Colors.white;
      borderColor = const Color(0xFFE2E8F0);
      titleColor = AppColors.textDark;
      subtitleColor = AppColors.primary;
      statusText = money.format(slot.price);
    }

    return InkWell(
      onTap: enabled
          ? () => onToggle(index)
          : () {
              if (isLockedByOther) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Khung giờ này đang có khách giữ chỗ. Vui lòng chọn khung giờ khác hoặc đợi hết hạn!'),
                    backgroundColor: Color(0xFFD97706),
                    duration: Duration(seconds: 2),
                    behavior: SnackBarBehavior.floating,
                  ),
                );
              }
            },
      borderRadius: BorderRadius.circular(14),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: bgColor,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: borderColor, width: selected ? 1.5 : 1),
          boxShadow: selected
              ? [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.25),
                    blurRadius: 8,
                    offset: const Offset(0, 3),
                  ),
                ]
              : [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.02),
                    blurRadius: 4,
                    offset: const Offset(0, 1),
                  ),
                ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '${slot.startTime} - ${slot.endTime}',
                  style: TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 13.5,
                    color: titleColor,
                  ),
                ),
                if (isLockedByOther)
                  const Icon(Icons.lock_clock_rounded, size: 14, color: Color(0xFFD97706))
                else if (selected)
                  const Icon(Icons.check_circle_rounded, size: 14, color: Colors.white),
              ],
            ),
            const SizedBox(height: 3),
            Text(
              statusText,
              style: TextStyle(
                fontSize: 11.5,
                fontWeight: selected || enabled ? FontWeight.w700 : FontWeight.w500,
                color: subtitleColor,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
