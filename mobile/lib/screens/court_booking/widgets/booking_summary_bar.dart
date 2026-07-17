import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../config/app_theme.dart';
import '../../../models/court_booking_models.dart';
import 'court_booking_chips.dart';

/// Thanh tóm tắt lựa chọn khung giờ + nút tiếp tục đặt sân.
class BookingSummaryBar extends StatelessWidget {
  final List<CourtSlot> slots;
  final List<int> orderedSelectedIndexes;
  final int slotAmount;
  final NumberFormat money;
  final VoidCallback? onContinue;

  const BookingSummaryBar({
    super.key,
    required this.slots,
    required this.orderedSelectedIndexes,
    required this.slotAmount,
    required this.money,
    required this.onContinue,
  });

  @override
  Widget build(BuildContext context) {
    final hasSelection = orderedSelectedIndexes.isNotEmpty;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: courtCardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(hasSelection
              ? '${slots[orderedSelectedIndexes.first].startTime} - ${slots[orderedSelectedIndexes.last].endTime}'
              : 'Chua chon khung gio'),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: Text(
                  money.format(slotAmount),
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: AppColors.primary,
                  ),
                ),
              ),
              ElevatedButton.icon(
                onPressed: hasSelection ? onContinue : null,
                icon: const Icon(Icons.check_circle_outline),
                label: const Text('Tiep tuc'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
