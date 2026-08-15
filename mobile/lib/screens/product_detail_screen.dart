import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/cart_provider.dart';
import '../providers/product_detail_provider.dart';
import '../services/api_client.dart';
import '../utils/format_utils.dart';
import '../widgets/network_image_widget.dart';
import '../widgets/shimmer_loading.dart';

/// Màn hình Chi tiết Sản phẩm phong cách Shopee & TikTok Shop.
/// - Trực quan, dễ sử dụng, tỷ lệ chuyển đổi cao.
/// - Modal BottomSheet chọn phân loại, live price & image switch.
/// - Vouchers, chính sách đổi trả, gian hàng chính hãng, thanh chốt đơn cố định.
class ProductDetailScreen extends StatefulWidget {
  final Map<String, dynamic> product;
  const ProductDetailScreen({super.key, required this.product});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  int _currentImageIndex = 0;
  final PageController _imagePageController = PageController();
  int _quantity = 1;
  bool _isFavorite = false;

  @override
  void initState() {
    super.initState();
    _isFavorite = widget.product['is_favorited'] == true || widget.product['is_favorited'] == 1;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ProductDetailProvider>().fetchProductData(widget.product);
    });
  }

  @override
  void dispose() {
    _imagePageController.dispose();
    super.dispose();
  }

  Future<void> _toggleFavorite(int productId) async {
    final messenger = ScaffoldMessenger.of(context);
    final loggedIn = context.read<AuthProvider>().isAuthenticated;
    if (!loggedIn) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Vui lòng đăng nhập để lưu yêu thích!')),
      );
      context.push('/login');
      return;
    }

    setState(() => _isFavorite = !_isFavorite);

    try {
      await ApiClient().dio.post(
        '/profile/favorites/toggle',
        data: {'product_id': productId},
      );
      messenger.showSnackBar(
        SnackBar(
          content: Text(_isFavorite ? 'Đã lưu vào mục Yêu thích' : 'Đã bỏ yêu thích'),
          duration: const Duration(milliseconds: 900),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      setState(() => _isFavorite = !_isFavorite);
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Không thể cập nhật yêu thích.'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  Future<void> _handleAddToCart(String action) async {
    final loggedIn = context.read<AuthProvider>().isAuthenticated;
    if (!loggedIn) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng đăng nhập để tiếp tục')),
      );
      context.push('/login');
      return;
    }

    final detailProvider = context.read<ProductDetailProvider>();
    final product = detailProvider.product.isNotEmpty ? detailProvider.product : widget.product;
    final selectedColor = detailProvider.selectedColor;
    final selectedSize = detailProvider.selectedSize;

    int? variantId;
    final variants = product['variants'] as List<dynamic>? ?? [];
    for (var v in variants) {
      final vColor = v['color']?.toString() ?? '';
      final vSize = v['size']?.toString() ?? '';
      bool match = true;
      if (vColor.isNotEmpty && vColor != selectedColor) match = false;
      if (vSize.isNotEmpty && vSize != selectedSize) match = false;
      if (match) {
        variantId = v['variant_id'] ?? v['id'];
        break;
      }
    }

    if (variantId == null && variants.isNotEmpty) {
      variantId = variants.first['variant_id'] ?? variants.first['id'];
    }

    if (variantId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Sản phẩm hiện chưa có phân loại khả dụng.'),
          backgroundColor: AppColors.error,
        ),
      );
      return;
    }

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => const Center(
        child: CircularProgressIndicator(color: AppColors.primary),
      ),
    );

    try {
      final response = await ApiClient().dio.post(
        '/cart/items',
        data: {'variant_id': variantId, 'quantity': _quantity},
      );

      if (mounted) context.pop(); // Close loading

      if (mounted) {
        context.read<CartProvider>().fetchCart(silent: true, force: true);
      }

      final msg = response.data['message'] ?? 'Đã thêm sản phẩm vào giỏ!';
      if (!mounted) return;

      if (action == 'buy_now') {
        context.push('/cart');
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg),
            backgroundColor: AppColors.success,
            behavior: SnackBarBehavior.floating,
            action: SnackBarAction(
              label: 'XEM GIỎ',
              textColor: Colors.white,
              onPressed: () => context.push('/cart'),
            ),
          ),
        );
      }
    } on DioException catch (e) {
      if (mounted) context.pop();
      final errMsg = e.response?.data?['message'] ?? 'Lỗi thêm sản phẩm!';
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(errMsg), backgroundColor: AppColors.error),
        );
      }
    } catch (_) {
      if (mounted) {
        context.pop();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Không kết nối được máy chủ!'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  void _showVariantBottomSheet(BuildContext context, String initialAction) {
    final detailProvider = context.read<ProductDetailProvider>();
    final product = detailProvider.product.isNotEmpty ? detailProvider.product : widget.product;
    final listColors = _getUniqueAttributes(product, 'color');
    final listSizes = _getUniqueAttributes(product, 'size');

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (modalContext) {
        return StatefulBuilder(
          builder: (ctx, setModalState) {
            final activeProvider = context.watch<ProductDetailProvider>();
            dynamic curPrice = product['min_price'] ?? 0;
            String curImage = (product['thumbnail_url'] ?? '').toString();

            final variants = product['variants'] as List<dynamic>? ?? [];
            for (var v in variants) {
              final vColor = v['color']?.toString() ?? '';
              final vSize = v['size']?.toString() ?? '';
              bool match = true;
              if (vColor.isNotEmpty && vColor != activeProvider.selectedColor) match = false;
              if (vSize.isNotEmpty && vSize != activeProvider.selectedSize) match = false;
              if (match) {
                if (v['price'] != null) curPrice = v['price'];
                if (v['image_url'] != null && v['image_url'].toString().isNotEmpty) {
                  curImage = v['image_url'];
                }
                break;
              }
            }

            return Container(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(ctx).viewInsets.bottom + 16,
              ),
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
              ),
              child: SafeArea(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Handle Bar
                    Center(
                      child: Container(
                        margin: const EdgeInsets.only(top: 8, bottom: 12),
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(
                          color: const Color(0xFFE2E8F0),
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),

                    // Top Preview: Thumbnail + Price + Selected Name
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 88,
                            height: 88,
                            decoration: BoxDecoration(
                              color: const Color(0xFFF8FAFC),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFFE2E8F0)),
                            ),
                            padding: const EdgeInsets.all(4),
                            child: NetworkImageWidget(
                              imageUrl: AppConfig.imageUrl(curImage),
                              width: double.infinity,
                              height: double.infinity,
                              fit: BoxFit.contain,
                            ),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  FormatUtils.formatPrice(curPrice),
                                  style: const TextStyle(
                                    fontSize: 22,
                                    fontWeight: FontWeight.w900,
                                    color: AppColors.primary,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                const Text(
                                  'Kho: Còn hàng (Hàng chính hãng)',
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'Đã chọn: ${activeProvider.selectedColor.isNotEmpty ? activeProvider.selectedColor : ''} ${activeProvider.selectedSize.isNotEmpty ? activeProvider.selectedSize : ''}'.trim(),
                                  style: const TextStyle(
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.textPrimary,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.close_rounded, color: AppColors.textMuted),
                            onPressed: () => Navigator.pop(ctx),
                          ),
                        ],
                      ),
                    ),

                    const Divider(height: 24, color: Color(0xFFF1F5F9)),

                    // Colors Selector
                    if (listColors.isNotEmpty) ...[
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Text(
                          'Màu sắc (${listColors.length})',
                          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: listColors.map((color) {
                            final isSel = activeProvider.selectedColor == color;
                            return GestureDetector(
                              onTap: () {
                                detailProvider.selectColor(color);
                                setModalState(() {});
                              },
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                                decoration: BoxDecoration(
                                  color: isSel ? const Color(0xFFFFF1F2) : const Color(0xFFF8FAFC),
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(
                                    color: isSel ? AppColors.primary : const Color(0xFFE2E8F0),
                                    width: isSel ? 1.5 : 1,
                                  ),
                                ),
                                child: Text(
                                  color,
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: isSel ? FontWeight.w800 : FontWeight.w600,
                                    color: isSel ? AppColors.primary : AppColors.textPrimary,
                                  ),
                                ),
                              ),
                            );
                          }).toList(),
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],

                    // Size Selector
                    if (listSizes.isNotEmpty) ...[
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Text(
                          'Kích thước (${listSizes.length})',
                          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: listSizes.map((size) {
                            final isSel = activeProvider.selectedSize == size;
                            return GestureDetector(
                              onTap: () {
                                detailProvider.selectSize(size);
                                setModalState(() {});
                              },
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                                decoration: BoxDecoration(
                                  color: isSel ? const Color(0xFFFFF1F2) : const Color(0xFFF8FAFC),
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(
                                    color: isSel ? AppColors.primary : const Color(0xFFE2E8F0),
                                    width: isSel ? 1.5 : 1,
                                  ),
                                ),
                                child: Text(
                                  size,
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: isSel ? FontWeight.w800 : FontWeight.w600,
                                    color: isSel ? AppColors.primary : AppColors.textPrimary,
                                  ),
                                ),
                              ),
                            );
                          }).toList(),
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],

                    // Quantity Stepper
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Số lượng',
                            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
                          ),
                          Container(
                            decoration: BoxDecoration(
                              color: const Color(0xFFF8FAFC),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: const Color(0xFFE2E8F0)),
                            ),
                            child: Row(
                              children: [
                                IconButton(
                                  icon: const Icon(Icons.remove_rounded, size: 16),
                                  onPressed: _quantity > 1
                                      ? () {
                                          setState(() => _quantity--);
                                          setModalState(() {});
                                        }
                                      : null,
                                ),
                                Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 8),
                                  child: Text(
                                    '$_quantity',
                                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
                                  ),
                                ),
                                IconButton(
                                  icon: const Icon(Icons.add_rounded, size: 16),
                                  onPressed: () {
                                    setState(() => _quantity++);
                                    setModalState(() {});
                                  },
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 20),

                    // Big Action Button in Modal
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: SizedBox(
                        width: double.infinity,
                        height: 48,
                        child: ElevatedButton(
                          onPressed: () {
                            Navigator.pop(ctx);
                            _handleAddToCart(initialAction);
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          child: Text(
                            initialAction == 'buy_now' ? 'MUA NGAY' : 'THÊM VÀO GIỎ HÀNG',
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w900,
                              color: Colors.white,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  List<String> _getUniqueAttributes(Map<String, dynamic> product, String key) {
    final variants = product['variants'] as List<dynamic>? ?? [];
    List<String> list = [];
    for (var v in variants) {
      final val = v[key]?.toString() ?? '';
      if (val.isNotEmpty && !list.contains(val)) list.add(val);
    }
    return list;
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<ProductDetailProvider>();
    final product = provider.product.isNotEmpty ? provider.product : widget.product;
    final selectedColor = provider.selectedColor;
    final selectedSize = provider.selectedSize;

    if (provider.isLoadingDetails && provider.product.isEmpty) {
      return const ProductDetailShimmer();
    }

    dynamic price = product['min_price'] ??
        (product['lowest_price_variant'] is Map
            ? product['lowest_price_variant']['price']
            : 0);
    dynamic originalPrice = product['original_price'] ?? product['max_price'];
    String rawImage = (product['thumbnail_url'] ?? '').toString();

    // Match variant price and image
    final variants = product['variants'] as List<dynamic>? ?? [];
    for (var v in variants) {
      final vColor = v['color']?.toString() ?? '';
      final vSize = v['size']?.toString() ?? '';
      bool match = true;
      if (vColor.isNotEmpty && vColor != selectedColor) match = false;
      if (vSize.isNotEmpty && vSize != selectedSize) match = false;
      if (match) {
        if (v['price'] != null) price = v['price'];
        if (v['image_url'] != null && v['image_url'].toString().isNotEmpty) {
          rawImage = v['image_url'];
        }
        break;
      }
    }

    // Safe Numeric Parsing for Price & Discount
    final num numPrice = FormatUtils.parseNum(price);
    final num numOriginalPrice = FormatUtils.parseNum(originalPrice);

    int discountPercent = 0;
    if (numOriginalPrice > numPrice && numOriginalPrice > 0) {
      discountPercent = (((numOriginalPrice - numPrice) / numOriginalPrice) * 100).round();
    }

    List<String> allImages = [];
    if (rawImage.isNotEmpty) {
      allImages.add(AppConfig.imageUrl(rawImage));
    } else if (product['thumbnail_url'] != null &&
        product['thumbnail_url'].toString().isNotEmpty) {
      allImages.add(AppConfig.imageUrl(product['thumbnail_url'].toString()));
    }

    final gallery = product['images'] as List<dynamic>? ?? [];
    for (var img in gallery) {
      final url = AppConfig.imageUrl(img['image_url']?.toString() ?? '');
      if (url.isNotEmpty && !allImages.contains(url)) {
        allImages.add(url);
      }
    }
    if (allImages.isEmpty) allImages.add('');

    String description = product['description'] ?? 'Sản phẩm thể thao cao cấp chính hãng từ Ocean Sport, được thiết kế tối ưu hiệu năng thi đấu và bảo vệ vận động viên.';
    description = description
        .replaceAll(RegExp(r'<[^>]*>'), '')
        .replaceAll('&nbsp;', ' ')
        .trim();

    final categoryName = product['category'] is Map
        ? (product['category']['name'] ?? 'THỂ THAO')
        : 'THỂ THAO';
    final name = product['name']?.toString() ?? 'Sản phẩm thể thao';
    final productId = product['product_id'] ?? product['id'] ?? 0;

    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9), // Shopee/TikTok neutral background
      body: Stack(
        children: [
          // ── Scrollable Body ──
          CustomScrollView(
            physics: const BouncingScrollPhysics(),
            slivers: [
              // ── 1. Hero Image 1:1 Square Slider (TikTok Shop style) ──
              SliverToBoxAdapter(
                child: Stack(
                  children: [
                    Container(
                      height: MediaQuery.of(context).size.width,
                      width: double.infinity,
                      color: Colors.white,
                      child: PageView.builder(
                        controller: _imagePageController,
                        itemCount: allImages.length,
                        onPageChanged: (index) {
                          setState(() => _currentImageIndex = index);
                        },
                        itemBuilder: (context, index) {
                          return Center(
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: NetworkImageWidget(
                                imageUrl: allImages[index],
                                width: double.infinity,
                                height: double.infinity,
                                fit: BoxFit.contain,
                                customMemCacheWidth: 900,
                              ),
                            ),
                          );
                        },
                      ),
                    ),

                    // Floating App Bar
                    Positioned(
                      top: MediaQuery.of(context).padding.top + 6,
                      left: 12,
                      right: 12,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          _buildCircleButton(
                            icon: Icons.arrow_back_rounded,
                            onTap: () => context.pop(),
                          ),
                          Row(
                            children: [
                              _buildCircleButton(
                                icon: Icons.share_outlined,
                                onTap: () {},
                              ),
                              const SizedBox(width: 8),
                              _buildCircleButton(
                                icon: _isFavorite
                                    ? Icons.favorite_rounded
                                    : Icons.favorite_border_rounded,
                                iconColor: _isFavorite ? AppColors.error : Colors.black87,
                                onTap: () => _toggleFavorite(productId),
                              ),
                              const SizedBox(width: 8),
                              Consumer<CartProvider>(
                                builder: (context, cart, _) => Stack(
                                  clipBehavior: Clip.none,
                                  children: [
                                    _buildCircleButton(
                                      icon: Icons.shopping_cart_outlined,
                                      onTap: () => context.push('/cart'),
                                    ),
                                    if (cart.itemCount > 0)
                                      Positioned(
                                        top: -2,
                                        right: -2,
                                        child: Container(
                                          padding: const EdgeInsets.all(4),
                                          decoration: const BoxDecoration(
                                            color: AppColors.primary,
                                            shape: BoxShape.circle,
                                          ),
                                          constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
                                          child: Text(
                                            cart.itemCount > 99 ? '99+' : cart.itemCount.toString(),
                                            style: const TextStyle(color: Colors.white, fontSize: 9.5, fontWeight: FontWeight.w900),
                                            textAlign: TextAlign.center,
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),

                    // Page Indicator (Shopee 1/5 style)
                    if (allImages.length > 1)
                      Positioned(
                        bottom: 12,
                        right: 14,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                          decoration: BoxDecoration(
                            color: Colors.black.withValues(alpha: 0.5),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            '${_currentImageIndex + 1}/${allImages.length}',
                            style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w700),
                          ),
                        ),
                      ),
                  ],
                ),
              ),

              // ── 2. Price & Title Section (Shopee Mall style) ──
              SliverToBoxAdapter(
                child: Container(
                  color: Colors.white,
                  padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Price Row
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            FormatUtils.formatPrice(numPrice),
                            style: const TextStyle(
                              fontSize: 24,
                              fontWeight: FontWeight.w900,
                              color: AppColors.primary,
                              letterSpacing: -0.3,
                            ),
                          ),
                          if (numOriginalPrice > numPrice) ...[
                            const SizedBox(width: 8),
                            Text(
                              FormatUtils.formatPrice(numOriginalPrice),
                              style: const TextStyle(
                                fontSize: 14,
                                color: AppColors.textMuted,
                                decoration: TextDecoration.lineThrough,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFFF1F2),
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                '-$discountPercent%',
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.primary,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),

                      const SizedBox(height: 10),

                      // Product Title with [Mall] / [Chính Hãng] Badge
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            margin: const EdgeInsets.only(top: 2, right: 6),
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: AppColors.primary,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: const Text(
                              'Mall',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 10.5,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          Expanded(
                            child: Text(
                              name,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                                color: AppColors.textPrimary,
                                height: 1.3,
                              ),
                            ),
                          ),
                        ],
                      ),

                      const SizedBox(height: 10),

                      // Ratings, Sold & Favorite Count
                      const Row(
                        children: [
                          Icon(Icons.star_rounded, size: 16, color: Color(0xFFF59E0B)),
                          SizedBox(width: 3),
                          Text(
                            '4.9',
                            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                          ),
                          SizedBox(width: 8),
                          Text('|', style: TextStyle(color: Color(0xFFE2E8F0))),
                          SizedBox(width: 8),
                          Text(
                            'Đã bán 450+',
                            style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
                          ),
                          SizedBox(width: 8),
                          Text('|', style: TextStyle(color: Color(0xFFE2E8F0))),
                          SizedBox(width: 8),
                          Text(
                            '128 Đánh giá',
                            style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),

              const SliverToBoxAdapter(child: SizedBox(height: 8)),

              // ── 3. Shop Vouchers (Shopee Voucher Row) ──
              SliverToBoxAdapter(
                child: InkWell(
                  onTap: () => context.push('/coupon'),
                  child: Container(
                    color: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                    child: Row(
                      children: [
                        const Text(
                          'Mã giảm giá',
                          style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: AppColors.textSecondary),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Wrap(
                            spacing: 6,
                            children: [
                              _buildVoucherTag('Giảm 10%'),
                              _buildVoucherTag('Freeship Xtra'),
                              _buildVoucherTag('Giảm 30K'),
                            ],
                          ),
                        ),
                        const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: AppColors.textMuted),
                      ],
                    ),
                  ),
                ),
              ),

              const SliverToBoxAdapter(child: SizedBox(height: 8)),

              // ── 4. Variant Selector Trigger (Chọn phân loại Shopee style) ──
              SliverToBoxAdapter(
                child: InkWell(
                  onTap: () => _showVariantBottomSheet(context, 'add_to_cart'),
                  child: Container(
                    color: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                    child: Row(
                      children: [
                        const Text(
                          'Chọn phân loại',
                          style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: AppColors.textSecondary),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Text(
                            selectedColor.isNotEmpty || selectedSize.isNotEmpty
                                ? 'Màu: ${selectedColor.isNotEmpty ? selectedColor : 'Mặc định'}, Size: ${selectedSize.isNotEmpty ? selectedSize : 'Chuẩn'}'
                                : 'Chọn Màu sắc, Kích thước (Size)',
                            style: const TextStyle(
                              fontSize: 13.5,
                              fontWeight: FontWeight.w600,
                              color: AppColors.textPrimary,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: AppColors.textMuted),
                      ],
                    ),
                  ),
                ),
              ),

              const SliverToBoxAdapter(child: SizedBox(height: 8)),

              // ── 5. Shipping & Return Assurance (Vận chuyển & Đổi trả) ──
              SliverToBoxAdapter(
                child: Container(
                  color: Colors.white,
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(Icons.local_shipping_outlined, size: 18, color: Color(0xFF059669)),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Miễn phí vận chuyển toàn quốc',
                                  style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  'Nhận hàng dự kiến trong 24h - 48h tới',
                                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const Divider(height: 20, color: Color(0xFFF1F5F9)),
                      Row(
                        children: [
                          const Icon(Icons.verified_user_outlined, size: 18, color: AppColors.primary),
                          const SizedBox(width: 10),
                          const Expanded(
                            child: Text(
                              'Đổi trả miễn phí 15 ngày  •  Chính hãng 100%',
                              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
                            ),
                          ),
                          const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: AppColors.textMuted),
                        ],
                      ),
                    ],
                  ),
                ),
              ),

              const SliverToBoxAdapter(child: SizedBox(height: 8)),

              // ── 6. Shop Info Banner (Ocean Sport Official Store) ──
              SliverToBoxAdapter(
                child: Container(
                  color: Colors.white,
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    children: [
                      Container(
                        width: 48,
                        height: 48,
                        decoration: BoxDecoration(
                          gradient: AppGradients.primary,
                          borderRadius: BorderRadius.circular(24),
                        ),
                        child: const Center(
                          child: Icon(Icons.sports_tennis_rounded, color: Colors.white, size: 26),
                        ),
                      ),
                      const SizedBox(width: 12),
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Text(
                                  'Ocean Sport Official',
                                  style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800, color: AppColors.textPrimary),
                                ),
                                SizedBox(width: 4),
                                Icon(Icons.check_circle, size: 14, color: AppColors.primary),
                              ],
                            ),
                            SizedBox(height: 3),
                            Text(
                              'Online 5 phút trước  •  TP. Hồ Chí Minh',
                              style: TextStyle(fontSize: 11.5, color: AppColors.textSecondary),
                            ),
                          ],
                        ),
                      ),
                      OutlinedButton(
                        onPressed: () => context.go('/shop'),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: AppColors.primary),
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                        child: const Text(
                          'Xem Shop',
                          style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800, color: AppColors.primary),
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SliverToBoxAdapter(child: SizedBox(height: 8)),

              // ── 7. Product Specifications & Description ──
              SliverToBoxAdapter(
                child: Container(
                  color: Colors.white,
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'CHI TIẾT SẢN PHẨM',
                        style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: AppColors.textPrimary, letterSpacing: 0.3),
                      ),
                      const SizedBox(height: 12),
                      _buildSpecRow('Danh mục', categoryName),
                      _buildSpecRow('Thương hiệu', 'Chính hãng'),
                      _buildSpecRow('Gửi từ', 'Hồ Chí Minh, Việt Nam'),
                      _buildSpecRow('Bảo hành', '12 tháng chính hãng'),
                      const Divider(height: 24, color: Color(0xFFF1F5F9)),
                      const Text(
                        'MÔ TẢ SẢN PHẨM',
                        style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: AppColors.textPrimary, letterSpacing: 0.3),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        description,
                        style: const TextStyle(fontSize: 13.5, color: Color(0xFF334155), height: 1.6),
                      ),
                    ],
                  ),
                ),
              ),

              // Bottom Padding for Sticky Bar
              const SliverToBoxAdapter(child: SizedBox(height: 110)),
            ],
          ),

          // ── 8. Shopee / TikTok Shop Sticky Bottom Bar ──
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Container(
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.08),
                    blurRadius: 10,
                    offset: const Offset(0, -3),
                  ),
                ],
              ),
              child: SafeArea(
                top: false,
                child: Row(
                  children: [
                    // Nút Chat
                    InkWell(
                      onTap: () => context.push('/chat', extra: product),
                      child: Container(
                        width: 60,
                        height: 52,
                        color: Colors.white,
                        child: const Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.chat_bubble_outline_rounded, size: 20, color: AppColors.textPrimary),
                            SizedBox(height: 2),
                            Text('Chat ngay', style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.w600, color: AppColors.textPrimary)),
                          ],
                        ),
                      ),
                    ),

                    Container(width: 0.8, height: 32, color: const Color(0xFFE2E8F0)),

                    // Nút Thêm vào giỏ hàng (Màu cam/hồng nhạt Shopee)
                    Expanded(
                      child: InkWell(
                        onTap: () => _showVariantBottomSheet(context, 'add_to_cart'),
                        child: Container(
                          height: 52,
                          color: const Color(0xFFFFF1F2),
                          child: const Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.add_shopping_cart_rounded, size: 20, color: AppColors.primary),
                              SizedBox(height: 2),
                              Text(
                                'Thêm vào giỏ',
                                style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: AppColors.primary),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),

                    // Nút MUA NGAY (Màu đỏ/hồng đậm TikTok/Shopee)
                    Expanded(
                      child: InkWell(
                        onTap: () => _showVariantBottomSheet(context, 'buy_now'),
                        child: Container(
                          height: 52,
                          color: AppColors.primary,
                          child: const Center(
                            child: Text(
                              'MUA NGAY',
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w900,
                                color: Colors.white,
                                letterSpacing: 0.5,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCircleButton({
    required IconData icon,
    required VoidCallback onTap,
    Color iconColor = Colors.black87,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 38,
        width: 38,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.9),
          shape: BoxShape.circle,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.1),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Center(
          child: Icon(icon, color: iconColor, size: 20),
        ),
      ),
    );
  }

  Widget _buildVoucherTag(String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF1F2),
        borderRadius: BorderRadius.circular(4),
        border: Border.all(color: const Color(0xFFFFCDD2)),
      ),
      child: Text(
        text,
        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.primary),
      ),
    );
  }

  Widget _buildSpecRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
            ),
          ),
        ],
      ),
    );
  }
}
