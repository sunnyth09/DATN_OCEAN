import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:cached_network_image/cached_network_image.dart';

import '../../../config/app_config.dart';
import '../../../utils/format_utils.dart';

/// Box tổng kết đơn: danh sách sản phẩm + các dòng giá (tạm tính, ship, giảm, tổng).
/// Thuần hiển thị, không có callback.
class CheckoutOrderSummary extends StatelessWidget {
  final List<dynamic> cartItems;
  final num subtotal;
  final int shippingFee;
  final bool isCalculatingShip;
  final int discountAmount;
  final Map<String, dynamic>? appliedCoupon;

  const CheckoutOrderSummary({
    super.key,
    required this.cartItems,
    required this.subtotal,
    required this.shippingFee,
    required this.isCalculatingShip,
    required this.discountAmount,
    required this.appliedCoupon,
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
          Row(
            children: [
              const Icon(
                Icons.receipt_outlined,
                color: Color(0xFFE63B6F),
                size: 20,
              ),
              const SizedBox(width: 8),
              Text(
                'Sản phẩm (${cartItems.length})',
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF0F172A),
                  fontSize: 15,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ...cartItems.map(_buildItemRow),
          const Divider(color: Color(0xFFE2E8F0)),
          const SizedBox(height: 8),
          _buildPriceRow('Tạm tính', FormatUtils.formatPrice(subtotal)),
          const SizedBox(height: 6),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Phí vận chuyển',
                style: TextStyle(fontSize: 13, color: Color(0xFF475569)),
              ),
              isCalculatingShip
                  ? const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        SizedBox(
                          width: 12,
                          height: 12,
                          child: CircularProgressIndicator(
                            strokeWidth: 1.5,
                            color: Color(0xFFE63B6F),
                          ),
                        ),
                        SizedBox(width: 6),
                        Text(
                          'Đang tính...',
                          style: TextStyle(
                            fontSize: 13,
                            color: Color(0xFF64748B),
                            fontStyle: FontStyle.italic,
                          ),
                        ),
                      ],
                    )
                  : Text(
                      FormatUtils.formatPrice(shippingFee),
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF0F172A),
                      ),
                    ),
            ],
          ),
          if (discountAmount > 0) ...[
            const SizedBox(height: 6),
            _buildPriceRow(
              'Giảm giá (${appliedCoupon?['code'] ?? ''})',
              '- ${FormatUtils.formatPrice(discountAmount)}',
              valueColor: Colors.green,
            ),
          ],
          const Divider(color: Color(0xFFE2E8F0)),
          const SizedBox(height: 6),
          _buildPriceRow(
            'Tổng cộng',
            FormatUtils.formatPrice(
              (subtotal.toInt() + shippingFee - discountAmount).clamp(
                0,
                double.maxFinite.toInt(),
              ),
            ),
            labelBold: true,
            valueColor: const Color(0xFFE63B6F),
            valueFontSize: 16,
          ),
        ],
      ),
    );
  }

  Widget _buildItemRow(dynamic item) {
    final variantData = item['variant'];
    final productData = item['product'];
    final name =
        variantData?['variant_name'] ?? productData?['name'] ?? 'Sản phẩm';
    final qty = item['quantity']?.toString() ?? '1';
    final lineTotal = FormatUtils.formatPrice(item['line_total'] ?? 0);

    String imageUrl = '';
    if (variantData != null &&
        variantData['image_url'] != null &&
        variantData['image_url'].toString().isNotEmpty) {
      imageUrl = AppConfig.imageUrl(variantData['image_url'].toString());
    }
    if (imageUrl.isEmpty && productData != null) {
      imageUrl = AppConfig.productImageUrl(productData);
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: const Color(0xFFF1F5F9),
              borderRadius: BorderRadius.circular(10),
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: _buildProductImage(imageUrl, 48),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Text(
                  'x$qty',
                  style: const TextStyle(
                    fontSize: 12,
                    color: Color(0xFF64748B),
                  ),
                ),
              ],
            ),
          ),
          Text(
            lineTotal,
            style: const TextStyle(
              fontWeight: FontWeight.bold,
              fontSize: 13,
              color: Color(0xFFE63B6F),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPriceRow(
    String label,
    String value, {
    Color? valueColor,
    bool labelBold = false,
    double valueFontSize = 13,
  }) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 13,
            color: const Color(0xFF475569),
            fontWeight: labelBold ? FontWeight.bold : FontWeight.normal,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: valueFontSize,
            fontWeight: FontWeight.bold,
            color: valueColor ?? const Color(0xFF0F172A),
          ),
        ),
      ],
    );
  }

  Widget _buildProductImage(String imgUrl, double size) {
    if (imgUrl.isEmpty) {
      return SizedBox(
        width: size,
        height: size,
        child: const Icon(
          Icons.image_outlined,
          color: Color(0xFFCBD5E1),
          size: 22,
        ),
      );
    }

    final isSvg = imgUrl.toLowerCase().endsWith('.svg');

    if (isSvg) {
      return SvgPicture.network(
        imgUrl,
        width: size,
        height: size,
        fit: BoxFit.cover,
        placeholderBuilder: (_) => SizedBox(
          width: size,
          height: size,
          child: const Center(
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: Color(0xFFE63B6F),
            ),
          ),
        ),
      );
    }

    return CachedNetworkImage(
      imageUrl: imgUrl,
      width: size,
      height: size,
      fit: BoxFit.cover,
      placeholder: (_, _) => SizedBox(
        width: size,
        height: size,
        child: const Center(
          child: CircularProgressIndicator(
            strokeWidth: 2,
            color: Color(0xFFE63B6F),
          ),
        ),
      ),
      errorWidget: (_, _, _) => SizedBox(
        width: size,
        height: size,
        child: const Icon(
          Icons.image_outlined,
          color: Color(0xFFCBD5E1),
          size: 22,
        ),
      ),
    );
  }
}
