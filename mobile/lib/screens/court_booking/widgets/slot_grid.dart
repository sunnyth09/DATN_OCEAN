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
      itemBuilder: (context, index) => _slotTile(index),
    );
  }

  Widget _slotTile(int index) {
    final slot = slots[index];
    final selected = selectedIndexes.contains(index);
    final enabled = slot.isAvailable;
    return InkWell(
      onTap: enabled ? () => onToggle(index) : null,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: selected
              ? AppColors.primary
              : (enabled ? Colors.white : const Color(0xFFE2E8F0)),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? AppColors.primary : AppColors.border,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              '${slot.startTime} - ${slot.endTime}',
              style: TextStyle(
                fontWeight: FontWeight.w900,
                color: selected ? Colors.white : AppColors.textDark,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              enabled ? money.format(slot.price) : slotStatusLabel(slot.status),
              style: TextStyle(
                fontSize: 12,
                color: selected ? Colors.white70 : AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
