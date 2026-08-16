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
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        border: const Border(top: BorderSide(color: Color(0xFFF1F5F9))),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, -3),
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
                  'Tổng thanh toán',
                  style: TextStyle(fontSize: 11.5, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
                ),
                Text(
                  FormatUtils.formatPrice(grandTotal),
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                    color: Color(0xFFE63B6F),
                  ),
                ),
              ],
            ),
            SizedBox(
              height: 44,
              child: ElevatedButton(
                onPressed: isPlacing ? null : onPlaceOrder,
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 28,
                    vertical: 0,
                  ),
                  backgroundColor: const Color(0xFFE63B6F),
                  disabledBackgroundColor: const Color(0xFFE63B6F).withValues(alpha: 0.6),
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: isPlacing
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(
                          color: Colors.white,
                          strokeWidth: 2,
                        ),
                      )
                    : const Text(
                        'Đặt hàng',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                          fontSize: 14.5,
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
