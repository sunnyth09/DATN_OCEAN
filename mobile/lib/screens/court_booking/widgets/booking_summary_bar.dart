import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../config/app_theme.dart';
import '../../../models/court_booking_models.dart';

class BookingSummaryBar extends StatelessWidget {
  final List<CourtSlot> slots;
  final List<int> orderedSelectedIndexes;
  final int totalAmount;
  final NumberFormat money;
  final VoidCallback? onBook;
  final bool isLoading;

  const BookingSummaryBar({
    super.key,
    this.slots = const [],
    this.orderedSelectedIndexes = const [],
    required this.totalAmount,
    required this.money,
    this.onBook,
    this.isLoading = false,
    int? slotAmount,
    int? selectedSlotCount,
    VoidCallback? onContinue,
  });

  @override
  Widget build(BuildContext context) {
    final hasSelection = orderedSelectedIndexes.isNotEmpty || totalAmount > 0;
    String timeRange = 'Chưa chọn khung giờ';

    if (orderedSelectedIndexes.isNotEmpty && slots.isNotEmpty) {
      final first = orderedSelectedIndexes.first;
      final last = orderedSelectedIndexes.last;
      if (first < slots.length && last < slots.length) {
        timeRange = '${slots[first].startTime} - ${slots[last].endTime}';
      }
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  timeRange,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  money.format(totalAmount),
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: AppColors.primary,
                    letterSpacing: -0.5,
                  ),
                ),
              ],
            ),
            SizedBox(
              height: 48,
              child: ElevatedButton(
                onPressed: hasSelection && !isLoading ? onBook : null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                  elevation: 0,
                ),
                child: isLoading
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          color: Colors.white,
                          strokeWidth: 2,
                        ),
                      )
                    : const Row(
                        children: [
                          Text(
                            'Đặt sân ngay',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                              color: Colors.white,
                            ),
                          ),
                          SizedBox(width: 6),
                          Icon(Icons.arrow_forward_rounded, size: 18, color: Colors.white),
                        ],
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
