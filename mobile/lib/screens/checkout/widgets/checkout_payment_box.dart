import 'package:flutter/material.dart';

/// Box chọn phương thức thanh toán. Thuần trình bày: nhận [selectedPayment]
/// và báo thay đổi qua [onChanged].
class CheckoutPaymentBox extends StatelessWidget {
  final int selectedPayment;
  final ValueChanged<int> onChanged;

  const CheckoutPaymentBox({
    super.key,
    required this.selectedPayment,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.03), blurRadius: 8),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.payment_outlined, color: Color(0xFFE63B6F), size: 20),
              SizedBox(width: 8),
              Text(
                'Phương thức thanh toán',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF0F172A),
                  fontSize: 15,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _buildOption(
            0,
            Icons.delivery_dining_outlined,
            'Thanh toán khi nhận hàng (COD)',
            'Trả tiền mặt cho người giao hàng',
          ),
          _buildOption(
            1,
            Icons.credit_card,
            'VNPay',
            'Chuyển khoản / Thẻ ngân hàng ATM',
          ),
          _buildOption(
            2,
            Icons.account_balance_wallet_outlined,
            'MoMo',
            'Ví điện tử MoMo',
          ),
        ],
      ),
    );
  }

  Widget _buildOption(
    int index,
    IconData icon,
    String title,
    String subtitle,
  ) {
    final isSelected = selectedPayment == index;
    return GestureDetector(
      onTap: () => onChanged(index),
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFF0F9FF) : Colors.grey.shade50,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected
                ? const Color(0xFFE63B6F)
                : const Color(0xFFE2E8F0),
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: isSelected
                    ? const Color(0xFFE63B6F).withValues(alpha: 0.1)
                    : const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(
                icon,
                color: isSelected
                    ? const Color(0xFFE63B6F)
                    : const Color(0xFF475569),
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                      color: isSelected
                          ? const Color(0xFFE63B6F)
                          : const Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 11,
                      color: Color(0xFF64748B),
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              isSelected ? Icons.radio_button_checked : Icons.radio_button_off,
              color: isSelected
                  ? const Color(0xFFE63B6F)
                  : const Color(0xFFCBD5E1),
              size: 20,
            ),
          ],
        ),
      ),
    );
  }
}
