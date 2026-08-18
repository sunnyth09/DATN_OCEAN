import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/favorite_provider.dart';
import '../utils/format_utils.dart';
import 'network_image_widget.dart';

/// Thẻ sản phẩm chuẩn TikTok Shop & Shopee Mall Tier:
/// - Bố cục tối ưu không gian, hình ảnh sạch đẹp, hiển thị trọn vẹn 2 dòng tiêu đề không bị cắt chữ.
/// - Đầy đủ nhãn chính hãng, huy hiệu giảm giá/HOT, đánh giá sao & nút thêm nhanh.
class ProductCard extends StatelessWidget {
  final Map<String, dynamic> product;
  final VoidCallback? onTap;
  final VoidCallback? onFavoriteChanged;
  final double imageAspectRatio;

  const ProductCard({
    super.key,
    required this.product,
    this.onTap,
    this.onFavoriteChanged,
    this.imageAspectRatio = 1.1,
  });

  @override
  Widget build(BuildContext context) {
    final money = NumberFormat.currency(locale: 'vi_VN', symbol: 'đ');
    final name = product['name']?.toString() ?? 'Sản phẩm thể thao';

    final dynamic rawPrice = product['min_price'] ??
        (product['lowest_price_variant'] is Map
            ? product['lowest_price_variant']['price']
            : (product['lowest_price_variant'] != null
                ? product['lowest_price_variant']['price']
                : 0));
    final num currentPrice = num.tryParse(rawPrice.toString()) ?? 0;

    final dynamic rawOrigPrice = product['original_price'] ?? product['max_price'];
    final num? originalPrice = rawOrigPrice != null ? num.tryParse(rawOrigPrice.toString()) : null;

    final hasDiscount = originalPrice != null && originalPrice > currentPrice;
    final int discountPercent = hasDiscount
        ? (((originalPrice - currentPrice) / originalPrice) * 100).round()
        : 0;

    final int soldCount = FormatUtils.parseNum(product['sold_count'] ?? product['sold'] ?? product['sold_quantity'] ?? 0).toInt();

    final imageUrl = AppConfig.productImageUrl(product);
    final isHot = product['is_featured'] == 1 || product['is_hot'] == 1;

    return RepaintBoundary(
      child: GestureDetector(
        onTap: onTap ?? () => context.push('/product-detail', extra: product),
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFFF1F5F9), width: 1),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.035),
                blurRadius: 10,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              // ── 1. IMAGE CONTAINER ──────────────────────────────
              AspectRatio(
                aspectRatio: imageAspectRatio,
                child: Stack(
                  children: [
                    // Product Image with soft backdrop
                    Positioned.fill(
                      child: ClipRRect(
                        borderRadius: const BorderRadius.vertical(top: Radius.circular(15)),
                        child: Container(
                          color: Colors.white,
                          padding: const EdgeInsets.all(8),
                          child: Center(
                            child: NetworkImageWidget(
                              imageUrl: imageUrl,
                              fit: BoxFit.contain,
                              errorWidget: Container(
                                color: Colors.white,
                                child: const Center(
                                  child: Icon(
                                    Icons.sports_tennis_rounded,
                                    color: Color(0xFFCBD5E1),
                                    size: 28,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),

                    // Discount Ribbon or HOT Badge
                    if (discountPercent > 0)
                      Positioned(
                        top: 6,
                        left: 6,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 5.5, vertical: 2.5),
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFFEF4444), Color(0xFFF87171)],
                            ),
                            borderRadius: BorderRadius.circular(5),
                          ),
                          child: Text(
                            '-$discountPercent%',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 9.5,
                              fontWeight: FontWeight.w900,
                              letterSpacing: 0.2,
                            ),
                          ),
                        ),
                      )
                    else if (isHot)
                      Positioned(
                        top: 6,
                        left: 6,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2.5),
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFFEF4444), Color(0xFFF97316)],
                            ),
                            borderRadius: BorderRadius.circular(5),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.local_fire_department_rounded, size: 10, color: Colors.white),
                              SizedBox(width: 2),
                              Text(
                                'HOT',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 9,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),

                    // Favorite Button Top Right
                    Positioned(
                      top: 6,
                      right: 6,
                      child: _FavoriteButton(
                        product: product,
                        onChanged: onFavoriteChanged,
                      ),
                    ),
                  ],
                ),
              ),

              // ── 2. PRODUCT DETAILS ──────────────────────────────
              Padding(
                padding: const EdgeInsets.fromLTRB(9, 8, 9, 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Product Title (Fixed 2 lines height)
                    SizedBox(
                      height: 32,
                      child: Text(
                        name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF0F172A),
                          height: 1.25,
                          letterSpacing: -0.1,
                        ),
                      ),
                    ),
                    const SizedBox(height: 4),

                    // Rating & Sold Row
                    Row(
                      children: [
                        const Icon(Icons.star_rounded, size: 13, color: Color(0xFFF59E0B)),
                        const SizedBox(width: 2.5),
                        const Text(
                          '4.9',
                          style: TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                        if (soldCount > 0) ...[
                          const SizedBox(width: 4),
                          Container(
                            width: 2.5,
                            height: 2.5,
                            decoration: const BoxDecoration(
                              color: Color(0xFFCBD5E1),
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 4),
                          Text(
                            'Đã bán $soldCount',
                            style: const TextStyle(
                              fontSize: 10,
                              color: Color(0xFF94A3B8),
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 6),

                    // Price & Quick Cart Button Row
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        // Price Column
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                money.format(currentPrice),
                                style: const TextStyle(
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w900,
                                  color: AppColors.primary,
                                  height: 1.1,
                                ),
                              ),
                              if (hasDiscount) ...[
                                const SizedBox(height: 1),
                                Text(
                                  money.format(originalPrice),
                                  style: const TextStyle(
                                    fontSize: 9.5,
                                    color: Color(0xFF94A3B8),
                                    decoration: TextDecoration.lineThrough,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),

                        // Quick Add to Cart Button
                        GestureDetector(
                          onTap: () {
                            context.push('/product-detail', extra: product);
                          },
                          child: Container(
                            width: 28,
                            height: 28,
                            decoration: BoxDecoration(
                              color: AppColors.primaryContainer,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: const Color(0xFFFFD1DC)),
                            ),
                            child: const Center(
                              child: Icon(
                                Icons.add_shopping_cart_rounded,
                                size: 14,
                                color: AppColors.primary,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FavoriteButton extends StatelessWidget {
  final Map<String, dynamic> product;
  final VoidCallback? onChanged;

  const _FavoriteButton({
    required this.product,
    this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final id = int.tryParse((product['product_id'] ?? product['id'] ?? 0).toString()) ?? 0;
    final isFav = context.watch<FavoriteProvider>().isFavorite(id);

    return GestureDetector(
      onTap: () async {
        final messenger = ScaffoldMessenger.of(context);
        final loggedIn = context.read<AuthProvider>().isAuthenticated;
        if (!loggedIn) {
          messenger.showSnackBar(
            const SnackBar(
              content: Text('Vui lòng đăng nhập để lưu yêu thích!'),
              behavior: SnackBarBehavior.floating,
            ),
          );
          context.push('/login');
          return;
        }

        final ok = await context.read<FavoriteProvider>().toggleFavorite(id);
        onChanged?.call();
        if (ok) {
          messenger.showSnackBar(
            SnackBar(
              content: Text(!isFav ? 'Đã thêm vào yêu thích' : 'Đã xóa khỏi yêu thích'),
              duration: const Duration(milliseconds: 900),
              behavior: SnackBarBehavior.floating,
            ),
          );
        }
      },
      child: Container(
        width: 28,
        height: 28,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.92),
          shape: BoxShape.circle,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.08),
              blurRadius: 4,
              offset: const Offset(0, 1.5),
            ),
          ],
        ),
        child: Center(
          child: Icon(
            isFav ? Icons.favorite_rounded : Icons.favorite_border_rounded,
            size: 15,
            color: isFav ? AppColors.error : const Color(0xFF64748B),
          ),
        ),
      ),
    );
  }
}
