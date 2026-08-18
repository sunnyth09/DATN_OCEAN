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
  final String? dateText;

  const BookingSummaryBar({
    super.key,
    this.slots = const [],
    this.orderedSelectedIndexes = const [],
    required this.totalAmount,
    required this.money,
    this.onBook,
    this.isLoading = false,
    this.dateText,
    int? slotAmount,
    int? selectedSlotCount,
    VoidCallback? onContinue,
  });

  @override
  Widget build(BuildContext context) {
    final hasSelection = orderedSelectedIndexes.isNotEmpty || totalAmount > 0;
    String timeRange = '';

    if (orderedSelectedIndexes.isNotEmpty && slots.isNotEmpty) {
      final first = orderedSelectedIndexes.first;
      final last = orderedSelectedIndexes.last;
      if (first < slots.length && last < slots.length) {
        timeRange = ' (${slots[first].startTime}-${slots[last].endTime})';
      }
    }

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 20,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            // Left Info: Slots Count & Total Price
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Flexible(
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                          decoration: BoxDecoration(
                            color: hasSelection ? const Color(0xFFFFF0F5) : const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            orderedSelectedIndexes.isNotEmpty
                                ? '${orderedSelectedIndexes.length} khung giờ$timeRange'
                                : '0 khung giờ',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: hasSelection ? AppColors.primary : const Color(0xFF64748B),
                            ),
                          ),
                        ),
                      ),
                      if (dateText != null) ...[
                        const SizedBox(width: 4),
                        Text(
                          '• $dateText',
                          style: const TextStyle(fontSize: 11, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 2),
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerLeft,
                    child: Text(
                      money.format(totalAmount),
                      style: const TextStyle(
                        fontSize: 19,
                        fontWeight: FontWeight.w900,
                        color: AppColors.primary,
                        letterSpacing: -0.3,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(width: 12),

            // Right Button: Action Button
            SizedBox(
              height: 44,
              child: InkWell(
                onTap: hasSelection && !isLoading ? onBook : null,
                borderRadius: BorderRadius.circular(16),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  decoration: BoxDecoration(
                    gradient: hasSelection && !isLoading
                        ? const LinearGradient(
                            colors: [Color(0xFFE63B6F), Color(0xFFFF6584)],
                            begin: Alignment.centerLeft,
                            end: Alignment.centerRight,
                          )
                        : null,
                    color: hasSelection && !isLoading ? null : const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: hasSelection && !isLoading
                        ? [
                            BoxShadow(
                              color: AppColors.primary.withValues(alpha: 0.35),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ]
                        : null,
                  ),
                  child: Center(
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
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                'Đặt Sân Ngay',
                                style: TextStyle(
                                  fontSize: 14.5,
                                  fontWeight: FontWeight.w800,
                                  color: Colors.white,
                                  letterSpacing: 0.2,
                                ),
                              ),
                              SizedBox(width: 6),
                              Icon(Icons.arrow_forward_rounded, size: 16, color: Colors.white),
                            ],
                          ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
