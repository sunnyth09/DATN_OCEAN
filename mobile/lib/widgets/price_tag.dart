import 'package:flutter/material.dart';
import '../config/app_theme.dart';
import '../utils/format_utils.dart';

class PriceTag extends StatelessWidget {
  final dynamic price;
  final dynamic originalPrice;
  final double fontSize;
  final double? originalFontSize;
  final Color? color;
  final bool showDiscountBadge;
  final FontWeight fontWeight;

  const PriceTag({
    super.key,
    required this.price,
    this.originalPrice,
    this.fontSize = 16,
    this.originalFontSize,
    this.color,
    this.showDiscountBadge = true,
    this.fontWeight = FontWeight.w900,
  });

  @override
  Widget build(BuildContext context) {
    final formattedPrice = FormatUtils.formatPrice(price);
    final numPrice = num.tryParse(price?.toString() ?? '0') ?? 0;
    final numOriginal = num.tryParse(originalPrice?.toString() ?? '0') ?? 0;
    final hasDiscount = numOriginal > numPrice && numPrice > 0;
    final discountPercent = hasDiscount
        ? (((numOriginal - numPrice) / numOriginal) * 100).round()
        : 0;

    return Wrap(
      crossAxisAlignment: WrapCrossAlignment.center,
      spacing: 6,
      children: [
        Text(
          formattedPrice,
          style: TextStyle(
            fontSize: fontSize,
            fontWeight: fontWeight,
            color: color ?? AppColors.primary,
            letterSpacing: -0.2,
          ),
        ),
        if (hasDiscount) ...[
          Text(
            FormatUtils.formatPrice(originalPrice),
            style: TextStyle(
              fontSize: originalFontSize ?? (fontSize * 0.75),
              fontWeight: FontWeight.w500,
              color: AppColors.textMuted,
              decoration: TextDecoration.lineThrough,
            ),
          ),
          if (showDiscountBadge && discountPercent > 0)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
              decoration: BoxDecoration(
                color: AppColors.errorLight,
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                '-$discountPercent%',
                style: const TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  color: AppColors.error,
                ),
              ),
            ),
        ],
      ],
    );
  }
}
