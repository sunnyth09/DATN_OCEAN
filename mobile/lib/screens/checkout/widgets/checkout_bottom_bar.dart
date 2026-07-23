import 'package:flutter/material.dart';

import '../../../utils/format_utils.dart';

/// Thanh sticky đáy: tổng tiền + nút Đặt hàng.
class CheckoutBottomBar extends StatelessWidget {
  final int grandTotal;
  final VoidCallback onPlaceOrder;
  final bool isPlacing;

  const CheckoutBottomBar({
    super.key,
    required this.grandTotal,
    required this.onPlaceOrder,
    this.isPlacing = false,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 12,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Tổng cộng',
                  style: TextStyle(fontSize: 12, color: Color(0xFF475569)),
                ),
                Text(
                  FormatUtils.formatPrice(grandTotal),
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFFE63B6F),
                  ),
                ),
              ],
            ),
            ElevatedButton(
              onPressed: isPlacing ? null : onPlaceOrder,
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(
                  horizontal: 36,
                  vertical: 14,
                ),
                backgroundColor: const Color(0xFFE63B6F),
                disabledBackgroundColor: const Color(0xFFE63B6F).withValues(alpha: 0.6),
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(30),
                ),
              ),
              child: isPlacing
                  ? const SizedBox(
                      height: 22,
                      width: 22,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2.5,
                      ),
                    )
                  : const Text(
                      'Đặt hàng',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
