import 'package:flutter/material.dart';

/// Box địa chỉ nhận hàng. Nhận [address] để hiển thị, báo yêu cầu đổi địa chỉ
/// qua [onChangeAddress] (State cha xử lý điều hướng + tính lại phí ship).
class CheckoutAddressBox extends StatelessWidget {
  final Map<String, dynamic>? address;
  final VoidCallback onChangeAddress;

  const CheckoutAddressBox({
    super.key,
    required this.address,
    required this.onChangeAddress,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
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
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(
                children: [
                  Icon(
                    Icons.location_on_outlined,
                    color: Color(0xFFE63B6F),
                    size: 20,
                  ),
                  SizedBox(width: 8),
                  Text(
                    'Địa chỉ nhận hàng',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF0F172A),
                      fontSize: 15,
                    ),
                  ),
                ],
              ),
              GestureDetector(
                onTap: onChangeAddress,
                child: Text(
                  address != null ? 'Thay đổi' : 'Thêm mới',
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    color: Color(0xFFE63B6F),
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (address != null) ...[
            Row(
              children: [
                const Icon(
                  Icons.person_outline,
                  size: 14,
                  color: Color(0xFF94A3B8),
                ),
                const SizedBox(width: 6),
                Text(
                  address!['recipient_name'] ?? '',
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
                const SizedBox(width: 12),
                const Icon(
                  Icons.phone_outlined,
                  size: 14,
                  color: Color(0xFF94A3B8),
                ),
                const SizedBox(width: 6),
                Text(
                  address!['phone'] ?? '',
                  style: const TextStyle(
                    fontSize: 13,
                    color: Color(0xFF475569),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  Icons.home_outlined,
                  size: 14,
                  color: Color(0xFF94A3B8),
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    '${address!['address_line']}, ${address!['ward']}, ${address!['district']}, ${address!['province']}',
                    style: const TextStyle(
                      fontSize: 13,
                      color: Color(0xFF475569),
                      height: 1.5,
                    ),
                  ),
                ),
              ],
            ),
          ] else ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.red.shade50,
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Row(
                children: [
                  Icon(
                    Icons.warning_amber_rounded,
                    color: Colors.red,
                    size: 16,
                  ),
                  SizedBox(width: 8),
                  Text(
                    'Bạn chưa có địa chỉ giao hàng. Vui lòng thêm.',
                    style: TextStyle(color: Colors.red, fontSize: 13),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
