import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/cart_provider.dart';
import '../providers/favorite_provider.dart';
import '../providers/product_detail_provider.dart';
import '../services/api_client.dart';
import '../utils/format_utils.dart';
import '../widgets/app_toast.dart';
import '../widgets/fly_to_cart_animator.dart';
import '../widgets/network_image_widget.dart';
import '../widgets/product_card.dart';
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

class _ProductDetailScreenState extends State<ProductDetailScreen>
    with SingleTickerProviderStateMixin {
  int _currentImageIndex = 0;
  final PageController _imagePageController = PageController();
  int _quantity = 1;

  final GlobalKey _cartKey = GlobalKey();
  final GlobalKey _imageKey = GlobalKey();
  late AnimationController _cartBounceCtrl;
  late Animation<double> _cartBounceScale;

  @override
  void initState() {
    super.initState();

    _cartBounceCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );
    _cartBounceScale = TweenSequence<double>([
      TweenSequenceItem(tween: Tween(begin: 1.0, end: 1.38).chain(CurveTween(curve: Curves.easeOutBack)), weight: 45),
      TweenSequenceItem(tween: Tween(begin: 1.38, end: 0.88).chain(CurveTween(curve: Curves.easeInOut)), weight: 30),
      TweenSequenceItem(tween: Tween(begin: 0.88, end: 1.0).chain(CurveTween(curve: Curves.easeIn)), weight: 25),
    ]).animate(_cartBounceCtrl);

    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ProductDetailProvider>().fetchProductData(widget.product);
    });
  }

  @override
  void didUpdateWidget(ProductDetailScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.product['product_id'] != widget.product['product_id'] && 
        oldWidget.product['id'] != widget.product['id']) {
      setState(() {
        _quantity = 1;
        _currentImageIndex = 0;
      });
      WidgetsBinding.instance.addPostFrameCallback((_) {
        context.read<ProductDetailProvider>().fetchProductData(widget.product);
      });
    }
  }

  @override
  void dispose() {
    _cartBounceCtrl.dispose();
    _imagePageController.dispose();
    super.dispose();
  }

  Future<void> _toggleFavorite(int productId) async {
    final loggedIn = context.read<AuthProvider>().isAuthenticated;
    if (!loggedIn) {
      AppToast.showInfo(context, message: 'Vui lòng đăng nhập để lưu yêu thích!');
      context.push('/login');
      return;
    }

    final currentlyFav = context.read<FavoriteProvider>().isFavorite(productId);
    final ok = await context.read<FavoriteProvider>().toggleFavorite(productId);
    if (ok && mounted) {
      AppToast.showFavorite(
        context,
        message: !currentlyFav ? 'Đã lưu vào mục Yêu thích' : 'Đã bỏ yêu thích',
        isFavorited: !currentlyFav,
      );
    }
  }

  Future<void> _handleAddToCart(String action) async {
    final loggedIn = context.read<AuthProvider>().isAuthenticated;
    if (!loggedIn) {
      AppToast.showInfo(context, message: 'Vui lòng đăng nhập để tiếp tục');
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
      AppToast.showError(context, message: 'Sản phẩm hiện chưa có phân loại khả dụng.');
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
        // Kích hoạt hiệu ứng Fly-to-Cart bay hình ảnh theo đường cong Parabol vào icon giỏ hàng trên AppBar
        final product = detailProvider.product.isNotEmpty ? detailProvider.product : widget.product;
        String? currentImg;
        if (product['thumbnail_url'] != null && product['thumbnail_url'].toString().isNotEmpty) {
          currentImg = AppConfig.imageUrl(product['thumbnail_url'].toString());
        }

        FlyToCartAnimator.fly(
          context: context,
          targetKey: _cartKey,
          startKey: _imageKey,
          imageUrl: currentImg,
          onComplete: () {
            _cartBounceCtrl.forward(from: 0);
            AppToast.showAddToCartSuccess(context, message: msg);
          },
        );
      }
    } on DioException catch (e) {
      if (mounted) context.pop();
      if (e.response?.statusCode == 401) {
        if (mounted) {
          AppToast.showError(context, message: 'Vui lòng đăng nhập để thêm vào giỏ hàng!');
          context.push('/login');
        }
        return;
      }
      final errMsg = e.response?.data?['message'] ?? 'Lỗi thêm sản phẩm!';
      if (mounted) {
        AppToast.showError(context, message: errMsg);
      }
    } catch (_) {
      if (mounted) {
        context.pop();
        AppToast.showError(context, message: 'Không kết nối được máy chủ!');
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
            final isFlashSale = activeProvider.isFlashSaleActive && activeProvider.flashSaleData != null;
            final fsPrice = isFlashSale
                ? FormatUtils.parseNum(activeProvider.flashSaleData?['sale_price'] ?? activeProvider.flashSaleData?['flash_price'] ?? activeProvider.flashSaleData?['flash_sale_price'] ?? activeProvider.flashSaleData?['min_price'])
                : 0;

            dynamic curPrice = (isFlashSale && fsPrice > 0) ? fsPrice : (product['min_price'] ?? 0);
            String curImage = (product['thumbnail_url'] ?? '').toString();

            final variants = product['variants'] as List<dynamic>? ?? [];
            for (var v in variants) {
              final vColor = v['color']?.toString() ?? '';
              final vSize = v['size']?.toString() ?? '';
              bool match = true;
              if (vColor.isNotEmpty && vColor != activeProvider.selectedColor) match = false;
              if (vSize.isNotEmpty && vSize != activeProvider.selectedSize) match = false;
              if (match) {
                if (!isFlashSale && v['price'] != null) curPrice = v['price'];
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
                              color: Colors.white,
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
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6.5),
                                decoration: BoxDecoration(
                                  color: isSel ? AppColors.primaryContainer : const Color(0xFFF4F4F5),
                                  borderRadius: BorderRadius.circular(6),
                                  border: Border.all(
                                    color: isSel ? AppColors.primary : const Color(0xFFE4E4E7),
                                    width: 1.0,
                                  ),
                                ),
                                child: Text(
                                  color,
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: isSel ? FontWeight.w700 : FontWeight.w500,
                                    color: isSel ? AppColors.primary : const Color(0xFF27272A),
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
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6.5),
                                decoration: BoxDecoration(
                                  color: isSel ? AppColors.primaryContainer : const Color(0xFFF4F4F5),
                                  borderRadius: BorderRadius.circular(6),
                                  border: Border.all(
                                    color: isSel ? AppColors.primary : const Color(0xFFE4E4E7),
                                    width: 1.0,
                                  ),
                                ),
                                child: Text(
                                  size,
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: isSel ? FontWeight.w700 : FontWeight.w500,
                                    color: isSel ? AppColors.primary : const Color(0xFF27272A),
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
                            height: 30,
                            decoration: BoxDecoration(
                              color: const Color(0xFFF8FAFC),
                              borderRadius: BorderRadius.circular(6),
                              border: Border.all(color: const Color(0xFFE2E8F0), width: 0.8),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                InkWell(
                                  onTap: _quantity > 1
                                      ? () {
                                          setState(() => _quantity--);
                                          setModalState(() {});
                                        }
                                      : null,
                                  borderRadius: const BorderRadius.horizontal(left: Radius.circular(6)),
                                  child: Container(
                                    width: 30,
                                    height: 30,
                                    alignment: Alignment.center,
                                    child: Icon(
                                      Icons.remove_rounded,
                                      size: 15,
                                      color: _quantity > 1 ? const Color(0xFF334155) : const Color(0xFFCBD5E1),
                                    ),
                                  ),
                                ),
                                Container(
                                  width: 34,
                                  height: 30,
                                  alignment: Alignment.center,
                                  decoration: const BoxDecoration(
                                    border: Border.symmetric(
                                      vertical: BorderSide(color: Color(0xFFE2E8F0), width: 0.8),
                                    ),
                                    color: Colors.white,
                                  ),
                                  child: Text(
                                    '$_quantity',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w700,
                                      fontSize: 13,
                                      color: Color(0xFF0F172A),
                                    ),
                                  ),
                                ),
                                InkWell(
                                  onTap: () {
                                    setState(() => _quantity++);
                                    setModalState(() {});
                                  },
                                  borderRadius: const BorderRadius.horizontal(right: Radius.circular(6)),
                                  child: Container(
                                    width: 30,
                                    height: 30,
                                    alignment: Alignment.center,
                                    child: const Icon(
                                      Icons.add_rounded,
                                      size: 15,
                                      color: Color(0xFF334155),
                                    ),
                                  ),
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
                        height: 46,
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
                              fontSize: 14.5,
                              fontWeight: FontWeight.w700,
                              color: Colors.white,
                              letterSpacing: 0.3,
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

    final bool isFlashSale = provider.isFlashSaleActive && provider.flashSaleData != null;
    final num fsPrice = isFlashSale
        ? FormatUtils.parseNum(provider.flashSaleData?['sale_price'] ?? provider.flashSaleData?['flash_price'] ?? provider.flashSaleData?['flash_sale_price'] ?? provider.flashSaleData?['min_price'])
        : 0;
    final num fsOrigPrice = isFlashSale
        ? FormatUtils.parseNum(provider.flashSaleData?['original_price'] ?? product['original_price'] ?? product['min_price'] ?? product['lowest_price_variant']?['price'] ?? 0)
        : 0;

    dynamic price = (isFlashSale && fsPrice > 0)
        ? fsPrice
        : (product['min_price'] ??
            (product['lowest_price_variant'] is Map
                ? product['lowest_price_variant']['price']
                : 0));
    dynamic originalPrice = (isFlashSale && fsOrigPrice > 0)
        ? fsOrigPrice
        : (product['original_price'] ?? product['max_price']);
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
        if (!isFlashSale && v['price'] != null) price = v['price'];
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

    String rawDescription = (product['description'] ?? product['short_description'] ?? '').toString();
    rawDescription = rawDescription
        .replaceAll(RegExp(r'<[^>]*>'), '')
        .replaceAll('&nbsp;', ' ')
        .trim();
    final description = rawDescription.isNotEmpty ? rawDescription : 'Chưa có mô tả chi tiết cho sản phẩm này.';

    final categoryName = product['category'] is Map
        ? (product['category']['name'] ?? 'Chưa phân loại')
        : (product['category_name'] ?? 'Chưa phân loại');
    final brandName = product['brand'] is Map
        ? (product['brand']['name'] ?? '')
        : (product['brand_name'] ?? product['brand']?.toString() ?? '');
    final sku = (product['sku'] ?? product['code'] ?? 'SP-${product['product_id'] ?? product['id']}').toString();
    final name = product['name']?.toString() ?? 'Sản phẩm thể thao';
    final pId = int.tryParse((product['product_id'] ?? product['id'] ?? 0).toString()) ?? 0;
    final isFav = context.watch<FavoriteProvider>().isFavorite(pId);

    // Real ratings and sold count from API
    final rawRatingAvg = FormatUtils.parseNum(product['rating_avg'] ?? product['rating']).toDouble();
    final rawRatingCount = int.tryParse((product['rating_count'] ?? provider.totalComments).toString()) ?? provider.totalComments;
    double ratingAvg = rawRatingAvg;
    int ratingCount = rawRatingCount;
    if (ratingAvg <= 0 && provider.comments.isNotEmpty) {
      double sum = 0;
      for (var c in provider.comments) {
        final r = FormatUtils.parseNum(c['rating']).toDouble();
        sum += (r <= 0 ? 5.0 : r);
      }
      ratingAvg = sum / provider.comments.length;
      ratingCount = provider.totalComments > 0 ? provider.totalComments : provider.comments.length;
    }
    final soldCount = int.tryParse((product['sold_count'] ?? 0).toString()) ?? 0;

    // Real Stock calculation from active variants
    int totalStock = 0;
    for (var v in variants) {
      totalStock += int.tryParse(v['stock']?.toString() ?? '0') ?? 0;
    }
    if (totalStock == 0 && product['stock'] != null) {
      totalStock = int.tryParse(product['stock'].toString()) ?? 0;
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9), // Shopee/TikTok neutral background
      body: SafeArea(
        top: true,
        bottom: false,
        child: Stack(
          children: [
            // ── Scrollable Body ──
            CustomScrollView(
              physics: const BouncingScrollPhysics(),
              slivers: [
                // ── 0. Clean Dedicated Top App Bar (Không bao giờ tràn vào Status Bar) ──
                SliverToBoxAdapter(
                  child: Container(
                    color: Colors.white,
                    padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        _buildCircleButton(
                          icon: Icons.arrow_back_rounded,
                          onTap: () {
                            if (context.canPop()) {
                              context.pop();
                            } else {
                              context.go('/home');
                            }
                          },
                        ),
                        Row(
                          children: [
                            _buildCircleButton(
                              icon: Icons.share_outlined,
                              onTap: () {},
                            ),
                            const SizedBox(width: 8),
                            _buildCircleButton(
                              icon: isFav
                                  ? Icons.favorite_rounded
                                  : Icons.favorite_border_rounded,
                              iconColor: isFav ? AppColors.error : Colors.black87,
                              onTap: () => _toggleFavorite(pId),
                            ),
                            const SizedBox(width: 8),
                            Consumer<CartProvider>(
                              builder: (context, cart, _) => ScaleTransition(
                                scale: _cartBounceScale,
                                child: Stack(
                                  key: _cartKey,
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
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),

                // ── 1. Hero Image 1:1 Square Slider + Thumbnail Strip (Shopee Mall style) ──
                SliverToBoxAdapter(
                  child: Container(
                    color: Colors.white,
                    child: Column(
                      children: [
                        Stack(
                          children: [
                            Container(
                              key: _imageKey,
                              height: MediaQuery.of(context).size.width * 0.85,
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
                                      child: InteractiveViewer(
                                        minScale: 1.0,
                                        maxScale: 4.0,
                                        child: NetworkImageWidget(
                                          imageUrl: allImages[index],
                                          width: double.infinity,
                                          height: double.infinity,
                                          fit: BoxFit.contain,
                                          customMemCacheWidth: 900,
                                        ),
                                      ),
                                    ),
                                  );
                                },
                              ),
                            ),

                            // Page Indicator (Shopee 1/5 style)
                            if (allImages.length > 1)
                              Positioned(
                                bottom: 10,
                                right: 14,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: Colors.black.withValues(alpha: 0.55),
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

                        // Mini Thumbnail Strip (Giúp người dùng chọn góc ảnh và định hướng trực quan)
                        if (allImages.length > 1)
                          Container(
                            height: 52,
                            padding: const EdgeInsets.only(bottom: 8),
                            child: ListView.separated(
                              scrollDirection: Axis.horizontal,
                              padding: const EdgeInsets.symmetric(horizontal: 16),
                              itemCount: allImages.length,
                              separatorBuilder: (_, _) => const SizedBox(width: 8),
                              itemBuilder: (context, idx) {
                                final isSelected = idx == _currentImageIndex;
                                return GestureDetector(
                                  onTap: () {
                                    _imagePageController.animateToPage(
                                      idx,
                                      duration: const Duration(milliseconds: 250),
                                      curve: Curves.easeInOut,
                                    );
                                  },
                                  child: AnimatedContainer(
                                    duration: const Duration(milliseconds: 200),
                                    width: 44,
                                    height: 44,
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFFAFAFA),
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(
                                        color: isSelected ? AppColors.primary : const Color(0xFFE2E8F0),
                                        width: isSelected ? 2 : 1,
                                      ),
                                    ),
                                    padding: const EdgeInsets.all(2),
                                    child: ClipRRect(
                                      borderRadius: BorderRadius.circular(6),
                                      child: NetworkImageWidget(
                                        imageUrl: allImages[idx],
                                        fit: BoxFit.contain,
                                      ),
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
                      ],
                    ),
                  ),
                ),

              // ── 1.5. Flash Sale Countdown Ribbon (Shopee / TikTok Shop style) ──
              if (isFlashSale)
                SliverToBoxAdapter(
                  child: _FlashSaleCountdownRibbon(
                    endTime: provider.flashSaleEndTime,
                    discountPercent: discountPercent,
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

                      // Product Title
                      Text(
                        name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 16.5,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textPrimary,
                          height: 1.3,
                        ),
                      ),

                      const SizedBox(height: 10),

                      // Ratings, Sold & Reviews (100% Real from API)
                      Row(
                        children: [
                          if (ratingAvg > 0) ...[
                            const Icon(Icons.star_rounded, size: 16, color: Color(0xFFF59E0B)),
                            const SizedBox(width: 3),
                            Text(
                              ratingAvg.toStringAsFixed(1),
                              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                            ),
                            if (ratingCount > 0) ...[
                              const SizedBox(width: 4),
                              Text(
                                '($ratingCount)',
                                style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                              ),
                            ],
                            const SizedBox(width: 8),
                            const Text('|', style: TextStyle(color: Color(0xFFE2E8F0))),
                            const SizedBox(width: 8),
                          ] else ...[
                            const Text(
                              'Chưa có đánh giá',
                              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                            ),
                            const SizedBox(width: 8),
                            const Text('|', style: TextStyle(color: Color(0xFFE2E8F0))),
                            const SizedBox(width: 8),
                          ],
                          Text(
                            soldCount > 0 ? 'Đã bán $soldCount' : 'Mới ra mắt',
                            style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
                          ),
                          if (totalStock > 0) ...[
                            const SizedBox(width: 8),
                            const Text('|', style: TextStyle(color: Color(0xFFE2E8F0))),
                            const SizedBox(width: 8),
                            Text(
                              'Kho: $totalStock',
                              style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
                            ),
                          ],
                        ],
                      ),
                    ],
                  ),
                ),
              ),

              const SliverToBoxAdapter(child: SizedBox(height: 8)),

              // ── 3. Shop Vouchers (Real Available Coupons from API) ──
              if (provider.coupons.isNotEmpty)
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
                              runSpacing: 4,
                              children: provider.coupons.take(3).map((c) {
                                final code = c['code']?.toString() ?? '';
                                final type = c['type']?.toString() ?? '';
                                final val = c['value'];
                                String label = code;
                                if (type == 'percent') {
                                  label = 'Giảm $val%';
                                } else if (type == 'free_ship') {
                                  label = 'Freeship';
                                } else if (val != null) {
                                  label = 'Giảm ${FormatUtils.formatPrice(val)}';
                                }
                                return _buildVoucherTag(label);
                              }).toList(),
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

              // ── 6. Ocean Sport Brand Assurances & Guarantees ──
              SliverToBoxAdapter(
                child: Container(
                  color: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Row(
                        children: [
                          Icon(Icons.verified_rounded, color: AppColors.primary, size: 18),
                          SizedBox(width: 8),
                          Text(
                            'Cam kết từ Ocean Sport',
                            style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w800, color: AppColors.textPrimary),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: _buildAssuranceItem(
                              icon: Icons.shield_outlined,
                              title: '100% Chính hãng',
                              subtitle: 'Hoàn tiền 200% nếu hàng giả',
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _buildAssuranceItem(
                              icon: Icons.published_with_changes_rounded,
                              title: 'Đổi trả 15 ngày',
                              subtitle: 'Miễn phí đổi hàng tận nơi',
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(
                            child: _buildAssuranceItem(
                              icon: Icons.local_shipping_outlined,
                              title: 'Giao hàng nhanh',
                              subtitle: 'Toàn quốc 24h - 48h',
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _buildAssuranceItem(
                              icon: Icons.support_agent_rounded,
                              title: 'Tư vấn chuyên sâu',
                              subtitle: 'Đan vợt & test miễn phí',
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),

              const SliverToBoxAdapter(child: SizedBox(height: 8)),

              // ── 7. Product Specifications & Description (100% Real Fields) ──
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
                      if (brandName.isNotEmpty)
                        _buildSpecRow('Thương hiệu', brandName),
                      _buildSpecRow('Mã sản phẩm', sku),
                      _buildSpecRow('Tình trạng', totalStock > 0 ? 'Còn hàng ($totalStock)' : 'Tạm hết hàng'),
                      if (product['weight'] != null && product['weight'].toString() != '0' && product['weight'].toString().isNotEmpty)
                        _buildSpecRow('Trọng lượng', '${product['weight']}g'),
                      _buildSpecRow('Chính sách', 'Đổi trả miễn phí 15 ngày'),
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

              // ── 8. Real Customer Reviews (Đánh giá thực tế từ API) ──
              SliverToBoxAdapter(
                child: Container(
                  color: Colors.white,
                  margin: const EdgeInsets.only(top: 8),
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              Text(
                                'ĐÁNH GIÁ SẢN PHẨM (${provider.totalComments})',
                                style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.textPrimary,
                                  letterSpacing: 0.3,
                                ),
                              ),
                            ],
                          ),
                          if (provider.comments.isNotEmpty)
                            InkWell(
                              onTap: () => _openAllReviewsSheet(
                                context,
                                provider.comments,
                                ratingAvg,
                                provider.totalComments,
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.star_rounded, size: 16, color: Color(0xFFF59E0B)),
                                  const SizedBox(width: 2),
                                  Text(
                                    ratingAvg > 0 ? ratingAvg.toStringAsFixed(1) : '5.0',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 13,
                                      color: Color(0xFFF59E0B),
                                    ),
                                  ),
                                  const SizedBox(width: 4),
                                  const Icon(Icons.chevron_right_rounded, size: 18, color: Color(0xFF94A3B8)),
                                ],
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      if (provider.isLoadingComments)
                        const Center(
                          child: Padding(
                            padding: EdgeInsets.all(16),
                            child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
                          ),
                        )
                      else if (provider.comments.isEmpty)
                        Container(
                          padding: const EdgeInsets.symmetric(vertical: 20),
                          alignment: Alignment.center,
                          child: Column(
                            children: [
                              Container(
                                width: 52,
                                height: 52,
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF8FAFC),
                                  shape: BoxShape.circle,
                                  border: Border.all(color: const Color(0xFFE2E8F0)),
                                ),
                                child: const Icon(Icons.rate_review_outlined, size: 26, color: Color(0xFF94A3B8)),
                              ),
                              const SizedBox(height: 10),
                              const Text(
                                'Chưa có đánh giá nào cho sản phẩm này',
                                style: TextStyle(
                                  color: Color(0xFF475569),
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 4),
                              const Text(
                                'Hãy mua hàng và là người đầu tiên chia sẻ cảm nhận nhé!',
                                style: TextStyle(color: Color(0xFF94A3B8), fontSize: 11.5),
                              ),
                            ],
                          ),
                        )
                      else ...[
                        // Reviews Preview list (Top 3)
                        Column(
                          children: provider.comments.take(3).map((cmt) {
                            return _buildReviewItem(
                              context,
                              cmt,
                              showBorder: cmt != provider.comments.take(3).last,
                            );
                          }).toList(),
                        ),

                        if (provider.comments.length > 3 || provider.totalComments > 3) ...[
                          const SizedBox(height: 12),
                          InkWell(
                            onTap: () => _openAllReviewsSheet(
                              context,
                              provider.comments,
                              ratingAvg,
                              provider.totalComments,
                            ),
                            borderRadius: BorderRadius.circular(8),
                            child: Container(
                              width: double.infinity,
                              padding: const EdgeInsets.symmetric(vertical: 10),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF8FAFC),
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: const Color(0xFFE2E8F0)),
                              ),
                              alignment: Alignment.center,
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Text(
                                    'Xem tất cả (${provider.totalComments}) đánh giá',
                                    style: const TextStyle(
                                      fontSize: 12.5,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.primary,
                                    ),
                                  ),
                                  const SizedBox(width: 4),
                                  const Icon(
                                    Icons.arrow_forward_ios_rounded,
                                    size: 11,
                                    color: AppColors.primary,
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ],
                    ],
                  ),
                ),
              ),

              // ── 9. Real Related Products (Sản phẩm liên quan dạng lưới 2 cột cuộn dọc) ──
              if (provider.relatedProducts.isNotEmpty) ...[
                SliverToBoxAdapter(
                  child: Container(
                    margin: const EdgeInsets.only(top: 8),
                    padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
                    color: Colors.white,
                    child: Row(
                      children: [
                        Container(
                          width: 3.5,
                          height: 16,
                          decoration: BoxDecoration(
                            color: AppColors.primary,
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                        const SizedBox(width: 8),
                        const Text(
                          'SẢN PHẨM TƯƠNG TỰ',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                            color: AppColors.textPrimary,
                            letterSpacing: 0.3,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(12, 10, 12, 16),
                  sliver: SliverGrid(
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      mainAxisSpacing: 10,
                      crossAxisSpacing: 10,
                      childAspectRatio: 0.62,
                    ),
                    delegate: SliverChildBuilderDelegate(
                      (context, idx) {
                        final rel = provider.relatedProducts[idx];
                        return ProductCard(
                          product: rel is Map<String, dynamic> ? rel : Map<String, dynamic>.from(rel),
                        );
                      },
                      childCount: provider.relatedProducts.length,
                    ),
                  ),
                ),
              ],

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

  Widget _buildAssuranceItem({
    required IconData icon,
    required String title,
    required String subtitle,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFF1F5F9)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 20, color: AppColors.primary),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.textPrimary),
                ),
                const SizedBox(height: 1),
                Text(
                  subtitle,
                  style: const TextStyle(fontSize: 10.5, color: AppColors.textSecondary),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  List<String> _parseReviewImages(dynamic rawImages) {
    if (rawImages == null) return [];
    if (rawImages is List) {
      return rawImages.map((e) {
        if (e is Map) return (e['image_url'] ?? e['url'] ?? '').toString();
        return e.toString();
      }).where((s) => s.isNotEmpty).toList();
    }
    if (rawImages is String) {
      final trimmed = rawImages.trim();
      if (trimmed.startsWith('[') && trimmed.endsWith(']')) {
        try {
          final decoded = jsonDecode(trimmed);
          if (decoded is List) {
            return _parseReviewImages(decoded);
          }
        } catch (_) {}
      }
      return trimmed
          .split(',')
          .map((s) => s.trim())
          .where((s) => s.isNotEmpty)
          .toList();
    }
    return [];
  }

  void _showReviewImageDialog(BuildContext context, List<String> allImages, int initialIndex) {
    showDialog(
      context: context,
      builder: (ctx) => Dialog(
        backgroundColor: Colors.black,
        insetPadding: EdgeInsets.zero,
        child: Stack(
          fit: StackFit.expand,
          children: [
            PageView.builder(
              controller: PageController(initialPage: initialIndex),
              itemCount: allImages.length,
              itemBuilder: (context, idx) {
                return InteractiveViewer(
                  minScale: 0.8,
                  maxScale: 4.0,
                  child: Center(
                    child: NetworkImageWidget(
                      imageUrl: AppConfig.imageUrl(allImages[idx]),
                      fit: BoxFit.contain,
                    ),
                  ),
                );
              },
            ),
            Positioned(
              top: MediaQuery.of(ctx).padding.top + 10,
              right: 16,
              child: InkWell(
                onTap: () => Navigator.of(ctx).pop(),
                child: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: const BoxDecoration(
                    color: Colors.black54,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.close_rounded, color: Colors.white, size: 24),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildReviewItem(BuildContext context, dynamic cmt, {bool showBorder = true}) {
    final reviewerName = cmt['commenter_info']?['full_name'] ??
        cmt['user']?['full_name'] ??
        cmt['user_name'] ??
        'Khách hàng';
    final avatarUrl = (cmt['commenter_info']?['avatar_url'] ??
            cmt['user']?['avatar_url'] ??
            '')
        .toString();
    final cmtRating = int.tryParse(cmt['rating']?.toString() ?? '5') ?? 5;
    final cmtContent = (cmt['content'] ?? '').toString();
    final cmtImages = _parseReviewImages(cmt['images']);
    final createdAt = (cmt['created_at'] ?? '').toString();

    final variantInfo = cmt['variant_name'] ??
        cmt['order_item']?['variant_name'] ??
        (cmt['order_item'] != null &&
                (cmt['order_item']['color'] != null || cmt['order_item']['size'] != null)
            ? '${cmt['order_item']['color'] ?? ''} ${cmt['order_item']['size'] ?? ''}'.trim()
            : null);

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(
        border: showBorder
            ? const Border(bottom: BorderSide(color: Color(0xFFF1F5F9), width: 1))
            : null,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              CircleAvatar(
                radius: 16,
                backgroundColor: const Color(0xFFFFF1F2),
                backgroundImage: avatarUrl.isNotEmpty
                    ? NetworkImage(AppConfig.imageUrl(avatarUrl))
                    : null,
                child: avatarUrl.isEmpty
                    ? Text(
                        reviewerName.isNotEmpty ? reviewerName[0].toUpperCase() : 'U',
                        style: const TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.bold,
                          color: AppColors.primary,
                        ),
                      )
                    : null,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            reviewerName,
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF0F172A),
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                          decoration: BoxDecoration(
                            color: const Color(0xFFECFDF5),
                            borderRadius: BorderRadius.circular(4),
                            border: Border.all(color: const Color(0xFFA7F3D0)),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.verified_rounded, size: 10, color: Color(0xFF059669)),
                              SizedBox(width: 2),
                              Text(
                                'Đã mua',
                                style: TextStyle(
                                  fontSize: 9.5,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF059669),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        Row(
                          children: List.generate(5, (sIdx) {
                            return Icon(
                              sIdx < cmtRating ? Icons.star_rounded : Icons.star_border_rounded,
                              size: 13,
                              color: const Color(0xFFF59E0B),
                            );
                          }),
                        ),
                        if (createdAt.isNotEmpty) ...[
                          const SizedBox(width: 8),
                          Text(
                            FormatUtils.formatDate(createdAt, withTime: true),
                            style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (variantInfo != null && variantInfo.toString().isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              'Phân loại: $variantInfo',
              style: const TextStyle(fontSize: 11.5, color: Color(0xFF64748B)),
            ),
          ],
          if (cmtContent.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              cmtContent,
              style: const TextStyle(
                fontSize: 13,
                color: Color(0xFF334155),
                height: 1.45,
              ),
            ),
          ],
          if (cmtImages.isNotEmpty) ...[
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: List.generate(cmtImages.length, (imgIdx) {
                final imgUrl = cmtImages[imgIdx];
                return GestureDetector(
                  onTap: () => _showReviewImageDialog(context, cmtImages, imgIdx),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Container(
                      decoration: BoxDecoration(
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: NetworkImageWidget(
                        imageUrl: AppConfig.imageUrl(imgUrl),
                        width: 68,
                        height: 68,
                        fit: BoxFit.cover,
                      ),
                    ),
                  ),
                );
              }),
            ),
          ],
          if (cmt['reply'] != null || cmt['seller_reply'] != null) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.storefront_rounded, size: 14, color: AppColors.primary),
                      SizedBox(width: 5),
                      Text(
                        'Phản hồi từ Người bán',
                        style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    cmt['reply'] is Map
                        ? (cmt['reply']['content'] ?? '')
                        : (cmt['seller_reply'] is Map
                            ? (cmt['seller_reply']['content'] ?? '')
                            : (cmt['reply'] ?? cmt['seller_reply']).toString()),
                    style: const TextStyle(fontSize: 12, color: Color(0xFF475569), height: 1.4),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  void _openAllReviewsSheet(
    BuildContext context,
    List<dynamic> allComments,
    double avgRating,
    int totalCount,
  ) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            int selectedStarFilter = 0;

            List<dynamic> filtered = allComments.where((c) {
              final r = int.tryParse(c['rating']?.toString() ?? '5') ?? 5;
              final imgs = _parseReviewImages(c['images']);
              if (selectedStarFilter == 0) return true;
              if (selectedStarFilter >= 1 && selectedStarFilter <= 5) {
                return r == selectedStarFilter;
              }
              if (selectedStarFilter == 6) {
                return imgs.isNotEmpty;
              }
              return true;
            }).toList();

            return Container(
              height: MediaQuery.of(context).size.height * 0.85,
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
              ),
              child: Column(
                children: [
                  Container(
                    margin: const EdgeInsets.only(top: 8),
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: const Color(0xFFCBD5E1),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            const Text(
                              'Đánh giá sản phẩm',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                            const SizedBox(width: 6),
                            Text(
                              '($totalCount)',
                              style: const TextStyle(
                                fontSize: 14,
                                color: AppColors.textSecondary,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                        IconButton(
                          icon: const Icon(Icons.close_rounded, size: 22, color: Color(0xFF64748B)),
                          onPressed: () => Navigator.of(ctx).pop(),
                        ),
                      ],
                    ),
                  ),
                  const Divider(height: 1, color: Color(0xFFF1F5F9)),
                  Container(
                    color: const Color(0xFFF8FAFC),
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Text(
                              avgRating.toStringAsFixed(1),
                              style: const TextStyle(
                                fontSize: 26,
                                fontWeight: FontWeight.w900,
                                color: Color(0xFFF59E0B),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: List.generate(5, (idx) {
                                    return Icon(
                                      idx < avgRating.round()
                                          ? Icons.star_rounded
                                          : Icons.star_border_rounded,
                                      size: 15,
                                      color: const Color(0xFFF59E0B),
                                    );
                                  }),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  '$totalCount lượt đánh giá từ khách hàng',
                                  style: const TextStyle(fontSize: 11.5, color: Color(0xFF64748B)),
                                ),
                              ],
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          child: Row(
                            children: [
                              _buildFilterChip('Tất cả ($totalCount)', 0, selectedStarFilter, (val) {
                                setSheetState(() => selectedStarFilter = val);
                              }),
                              _buildFilterChip('5 Sao', 5, selectedStarFilter, (val) {
                                setSheetState(() => selectedStarFilter = val);
                              }),
                              _buildFilterChip('4 Sao', 4, selectedStarFilter, (val) {
                                setSheetState(() => selectedStarFilter = val);
                              }),
                              _buildFilterChip('3 Sao', 3, selectedStarFilter, (val) {
                                setSheetState(() => selectedStarFilter = val);
                              }),
                              _buildFilterChip('2 Sao', 2, selectedStarFilter, (val) {
                                setSheetState(() => selectedStarFilter = val);
                              }),
                              _buildFilterChip('1 Sao', 1, selectedStarFilter, (val) {
                                setSheetState(() => selectedStarFilter = val);
                              }),
                              _buildFilterChip('Có hình ảnh', 6, selectedStarFilter, (val) {
                                setSheetState(() => selectedStarFilter = val);
                              }),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  Expanded(
                    child: filtered.isEmpty
                        ? Center(
                            child: Padding(
                              padding: const EdgeInsets.all(32),
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.rate_review_outlined, size: 40, color: Colors.grey.shade300),
                                  const SizedBox(height: 8),
                                  const Text(
                                    'Không có đánh giá nào phù hợp với bộ lọc',
                                    style: TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
                                  ),
                                ],
                              ),
                            ),
                          )
                        : ListView.builder(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            itemCount: filtered.length,
                            itemBuilder: (context, idx) {
                              return _buildReviewItem(
                                context,
                                filtered[idx],
                                showBorder: idx != filtered.length - 1,
                              );
                            },
                          ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildFilterChip(
    String label,
    int value,
    int selectedValue,
    ValueChanged<int> onSelected,
  ) {
    final isSelected = value == selectedValue;
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: InkWell(
        onTap: () => onSelected(value),
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
          decoration: BoxDecoration(
            color: isSelected ? const Color(0xFFFFF1F2) : Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: isSelected ? AppColors.primary : const Color(0xFFE2E8F0),
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              fontSize: 11.5,
              fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
              color: isSelected ? AppColors.primary : const Color(0xFF475569),
            ),
          ),
        ),
      ),
    );
  }
}

class _FlashSaleCountdownRibbon extends StatefulWidget {
  final DateTime? endTime;
  final int discountPercent;

  const _FlashSaleCountdownRibbon({
    this.endTime,
    required this.discountPercent,
  });

  @override
  State<_FlashSaleCountdownRibbon> createState() => _FlashSaleCountdownRibbonState();
}

class _FlashSaleCountdownRibbonState extends State<_FlashSaleCountdownRibbon> {
  late Duration _remaining;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _calculateRemaining();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) _calculateRemaining();
    });
  }

  void _calculateRemaining() {
    final now = DateTime.now();
    final end = widget.endTime ?? now.add(const Duration(hours: 2, minutes: 45));
    final diff = end.difference(now);
    setState(() {
      _remaining = diff.isNegative ? Duration.zero : diff;
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final hours = _remaining.inHours.toString().padLeft(2, '0');
    final minutes = (_remaining.inMinutes % 60).toString().padLeft(2, '0');
    final seconds = (_remaining.inSeconds % 60).toString().padLeft(2, '0');

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8.5),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFFE11D48), Color(0xFFF43F5E)],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              const Icon(Icons.bolt_rounded, color: Color(0xFFFFD166), size: 22),
              const SizedBox(width: 4),
              const Text(
                'FLASH SALE',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                  fontSize: 15.5,
                  letterSpacing: 0.4,
                  fontStyle: FontStyle.italic,
                ),
              ),
              if (widget.discountPercent > 0) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(
                    '-${widget.discountPercent}%',
                    style: const TextStyle(
                      color: Color(0xFFE11D48),
                      fontWeight: FontWeight.w900,
                      fontSize: 10,
                    ),
                  ),
                ),
              ],
            ],
          ),
          Row(
            children: [
              const Text(
                'KẾT THÚC TRONG ',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 9.5,
                  letterSpacing: 0.2,
                ),
              ),
              _buildTimeBox(hours),
              const Text(' : ', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 10.5)),
              _buildTimeBox(minutes),
              const Text(' : ', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 10.5)),
              _buildTimeBox(seconds),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildTimeBox(String val) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 4.5, vertical: 2),
      decoration: BoxDecoration(
        color: const Color(0x3D000000),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        val,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w900,
          fontSize: 10.5,
        ),
      ),
    );
  }
}
