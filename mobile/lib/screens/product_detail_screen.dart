import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../config/app_config.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../utils/format_utils.dart';
import 'package:provider/provider.dart';
import '../providers/product_detail_provider.dart';
import '../providers/cart_provider.dart';
import '../providers/navigation_provider.dart';
import '../widgets/shimmer_loading.dart';
import 'login_screen.dart';

class ProductDetailScreen extends StatefulWidget {
  final Map<String, dynamic> product;
  const ProductDetailScreen({super.key, required this.product});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  int _currentImageIndex = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ProductDetailProvider>().fetchProductData(widget.product);
    });
  }

  

  

  

  void _handleActionSelected(String actionStr) async {
    final loggedIn = await AuthService.isLoggedIn();
    if (!loggedIn) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Vui lòng đăng nhập để tiếp tục')),
        );
        await Navigator.push(
          context,
          MaterialPageRoute(builder: (context) => const LoginScreen()),
        );
      }
      return;
    }

    if (!mounted) return;

    // Tìm Variant ID
    final detailProvider = context.read<ProductDetailProvider>();
    final product = detailProvider.product;
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
        variantId = v['variant_id'];
        break;
      }
    }

    if (variantId == null && variants.isNotEmpty) {
      variantId = variants.first['variant_id'];
    }

    if (variantId == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Không thể đặt hàng do sản phẩm thiếu dữ liệu (Variants)',
            ),
            backgroundColor: Colors.red,
          ),
        );
      }
      return;
    }

    if (!mounted) return;
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => const Center(child: CircularProgressIndicator()),
    );

    try {
      final response = await ApiClient().dio.post(
        '/cart/items',
        data: {'variant_id': variantId, 'quantity': 1},
      );

      if (mounted) context.pop();

      if (mounted) {
        context.read<CartProvider>().fetchCart(silent: true);
      }

      final msg = response.data['message'] ?? 'Thêm vào giỏ thành công!';
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg),
            backgroundColor: Colors.green,
            action: actionStr != 'Mua ngay' ? SnackBarAction(
              label: 'XEM GIỎ',
              textColor: Colors.white,
              onPressed: () {
                context.read<NavigationProvider>().setTab(3);
                context.go('/home');
              },
            ) : null,
          ),
        );
        if (actionStr == 'Mua ngay') {
          context.read<NavigationProvider>().setTab(3);
          context.go('/home');
        }
      }
    } on DioException catch (e) {
      if (mounted) context.pop();
      final errMsg = e.response?.data?['message'] ?? 'Lỗi thêm sản phẩm!';
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(errMsg), backgroundColor: Colors.red),
        );
      }
    } catch (e) {
      if (mounted) {
        context.pop();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Không kết nối được máy chủ!'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
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
    // ignore: no_leading_underscores_for_local_identifiers
    final _product = provider.product;
    final selectedColor = provider.selectedColor;
    final selectedSize = provider.selectedSize;
    if (provider.isLoadingDetails) {
      return const ProductDetailShimmer();
    }


    dynamic priceRaw =
        _product['min_price'] ??
        (_product['lowest_price_variant'] is Map
            ? _product['lowest_price_variant']['price']
            : 0);
    String rawImage = (_product['thumbnail_url'] ?? '').toString();

    final variants = _product['variants'] as List<dynamic>? ?? [];
    for (var v in variants) {
      final vColor = v['color']?.toString() ?? '';
      final vSize = v['size']?.toString() ?? '';
      bool match = true;
      if (vColor.isNotEmpty && vColor != selectedColor) match = false;
      if (vSize.isNotEmpty && vSize != selectedSize) match = false;
      if (match) {
        if (v['price'] != null) priceRaw = v['price'];
        if (v['image_url'] != null && v['image_url'].toString().isNotEmpty) {
          rawImage = v['image_url'];
        }
        break;
      }
    }

    List<String> allImages = [];
    if (rawImage.isNotEmpty) {
      allImages.add(AppConfig.imageUrl(rawImage));
    } else if (_product['thumbnail_url'] != null && _product['thumbnail_url'].toString().isNotEmpty) {
      allImages.add(AppConfig.imageUrl(_product['thumbnail_url'].toString()));
    }

    final gallery = _product['images'] as List<dynamic>? ?? [];
    for (var img in gallery) {
      final url = AppConfig.imageUrl(img['image_url']?.toString() ?? '');
      if (url.isNotEmpty && !allImages.contains(url)) {
        allImages.add(url);
      }
    }

    if (allImages.isEmpty) {
      allImages.add('');
    }

    String description = _product['description'] ?? 'Chưa có mô tả sản phẩm.';
    description = description
        .replaceAll(RegExp(r'<[^>]*>'), '')
        .replaceAll('&nbsp;', ' ')
        .trim();

    final categoryName = _product['category'] is Map
        ? (_product['category']['name'] ?? 'SẢN PHẨM')
        : 'SẢN PHẨM';
    final listColors = _getUniqueAttributes(_product, 'color');
    final listSizes = _getUniqueAttributes(_product, 'size');

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text(
          'Quyền Sport',
          style: TextStyle(
            fontWeight: FontWeight.w800,
            color: Color(0xFFB50C4D),
            fontSize: 18,
          ),
        ),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFFE63B6F),
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(icon: const Icon(Icons.share_outlined), onPressed: () {}),
          Consumer<CartProvider>(
            builder: (context, cart, child) {
              return IconButton(
                icon: Badge(
                  isLabelVisible: cart.itemCount > 0,
                  label: Text(cart.itemCount > 99 ? '99+' : cart.itemCount.toString()),
                  child: const Icon(Icons.shopping_cart_outlined),
                ),
                onPressed: () {
                  context.read<NavigationProvider>().setTab(3);
                  context.go('/home');
                },
              );
            },
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: Stack(
        children: [
          SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 100),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Image Section
                Container(
                  color: Colors.white,
                  child: Stack(
                    children: [
                      SizedBox(
                        height: 350,
                        width: double.infinity,
                        child: PageView.builder(
                          itemCount: allImages.length,
                          onPageChanged: (index) {
                            setState(() {
                              _currentImageIndex = index;
                            });
                          },
                          itemBuilder: (context, index) {
                            final imageWidget = _buildProductImage(allImages[index], double.infinity, 350);
                            if (index == 0) {
                              return Hero(
                                tag: _product['id'] ?? _product['slug'] ?? 'product_image',
                                child: imageWidget,
                              );
                            }
                            return imageWidget;
                          },
                        ),
                      ),
                      if (allImages.length > 1)
                        Positioned(
                          bottom: 16,
                          right: 16,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.black54,
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Text(
                              '${_currentImageIndex + 1}/${allImages.length}',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),

                // Content
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.only(
                      bottomLeft: Radius.circular(24),
                      bottomRight: Radius.circular(24),
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            categoryName.toUpperCase(),
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFFE63B6F),
                            ),
                          ),
                          const Icon(
                            Icons.favorite_border,
                            color: Color(0xFF64748B),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        _product['name'] ?? 'Sản phẩm',
                        style: const TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.w900,
                          color: Color(0xFF0F172A),
                          height: 1.2,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        FormatUtils.formatPrice(priceRaw),
                        style: const TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          color: Color(0xFFE63B6F),
                        ),
                      ),
                      const SizedBox(height: 24),
                      // GridView removed to avoid hardcoded watch features in a sports store
                    ],
                  ),
                ),

                const SizedBox(height: 8),

                // Variants
                if (listColors.isNotEmpty || listSizes.isNotEmpty)
                  Container(
                    padding: const EdgeInsets.all(20),
                    color: Colors.white,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (listColors.isNotEmpty) ...[
                          const Text(
                            'Màu sắc',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF0F172A),
                            ),
                          ),
                          const SizedBox(height: 12),
                          Wrap(
                            runSpacing: 8,
                            children: listColors
                                .map((c) => _buildColorChoice(c))
                                .toList(),
                          ),
                          if (listSizes.isNotEmpty) const SizedBox(height: 24),
                        ],
                        if (listSizes.isNotEmpty) ...[
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text(
                                'Kích thước',
                                style: TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.bold,
                                  color: Color(0xFF0F172A),
                                ),
                              ),
                              Text(
                                'Hướng dẫn đo size',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: Color(0xFFE63B6F),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Wrap(
                            spacing: 12,
                            runSpacing: 12,
                            children: listSizes
                                .map((s) => _buildSizeChoice(s))
                                .toList(),
                          ),
                        ],
                      ],
                    ),
                  ),

                const SizedBox(height: 8),

                // Description
                Container(
                  padding: const EdgeInsets.all(20),
                  color: Colors.white,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Mô tả sản phẩm',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        description,
                        style: const TextStyle(
                          fontSize: 14,
                          color: Color(0xFF475569),
                          height: 1.6,
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 8),

                // Comments
                Container(
                  padding: const EdgeInsets.all(20),
                  color: Colors.white,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Đánh giá sản phẩm (${context.watch<ProductDetailProvider>().comments.length})',
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 16),
                      if (context.watch<ProductDetailProvider>().isLoadingComments)
                        const Center(child: CircularProgressIndicator())
                      else if (context.watch<ProductDetailProvider>().comments.isEmpty)
                        const Text(
                          'Chưa có đánh giá nào.',
                          style: TextStyle(
                            color: Colors.grey,
                            fontStyle: FontStyle.italic,
                          ),
                        )
                      else
                        ...context.watch<ProductDetailProvider>().comments.map((cmt) {
                          final user = cmt['user'] != null
                              ? cmt['user']['full_name']
                              : 'Người dùng';
                          final rating = cmt['rating'] ?? 5;
                          final content = cmt['comment'] ?? '';
                          final date = FormatUtils.formatDate(cmt['created_at']);
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 16),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                CircleAvatar(
                                  radius: 16,
                                  backgroundColor: Colors.grey.shade300,
                                  child: const Icon(
                                    Icons.person,
                                    size: 16,
                                    color: Colors.white,
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        mainAxisAlignment:
                                            MainAxisAlignment.spaceBetween,
                                        children: [
                                          Text(
                                            user.toString(),
                                            style: const TextStyle(
                                              fontWeight: FontWeight.bold,
                                              fontSize: 13,
                                            ),
                                          ),
                                          Text(
                                            date.toString(),
                                            style: const TextStyle(
                                              fontSize: 11,
                                              color: Colors.grey,
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 4),
                                      Row(
                                        children: List.generate(
                                          5,
                                          (i) => Icon(
                                            Icons.star,
                                            size: 12,
                                            color: i < rating
                                                ? Colors.amber
                                                : Colors.grey.shade300,
                                          ),
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        content.toString(),
                                        style: const TextStyle(
                                          fontSize: 13,
                                          color: Color(0xFF334155),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          );
                        }),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // Related Products
                if (context.watch<ProductDetailProvider>().relatedProducts.isNotEmpty)
                  Container(
                    padding: const EdgeInsets.all(20),
                    color: Colors.white,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Sản phẩm tương tự',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                            GestureDetector(
                              onTap: () {},
                              child: const Text(
                                'Xem tất cả →',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: Color(0xFFE63B6F),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        SizedBox(
                          height: 220,
                          child: ListView.separated(
                            scrollDirection: Axis.horizontal,
                            itemCount: context.watch<ProductDetailProvider>().relatedProducts.length > 6
                                ? 6
                                : context.watch<ProductDetailProvider>().relatedProducts.length,
                            separatorBuilder: (_, _) =>
                                const SizedBox(width: 12),
                            itemBuilder: (context, index) {
                              final rp =
                                  context.watch<ProductDetailProvider>().relatedProducts[index]
                                      as Map<String, dynamic>;
                              return _buildRelatedProductCard(rp);
                            },
                          ),
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: 20),
              ],
            ),
          ),

          // Sticky Bottom Bar
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 10,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              child: SafeArea(
                child: Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => _handleActionSelected('Thêm vào giỏ'),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          side: const BorderSide(
                            color: Color(0xFFE63B6F),
                            width: 1.5,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(30),
                          ),
                        ),
                        child: const Text(
                          'Thêm vào giỏ',
                          style: TextStyle(
                            color: Color(0xFFE63B6F),
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () => _handleActionSelected('Mua ngay'),
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          backgroundColor: const Color(0xFFE63B6F),
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(30),
                          ),
                        ),
                        child: const Text(
                          'Mua ngay',
                          style: TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
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

  Widget _buildColorChoice(String colorVal) {
    final detailProvider = context.watch<ProductDetailProvider>();
    final isSelected = detailProvider.selectedColor == colorVal;
    Color? clr;
    if (colorVal.startsWith('#')) {
      try {
        clr = Color(int.parse(colorVal.replaceFirst('#', '0xFF')));
      } catch (_) {}
    } else {
      switch (colorVal.toLowerCase().trim()) {
        case 'đỏ':
        case 'red':
          clr = Colors.red;
          break;
        case 'xanh':
        case 'xanh dương':
        case 'blue':
          clr = Colors.blue;
          break;
        case 'xanh lá':
        case 'green':
          clr = Colors.green;
          break;
        case 'vàng':
        case 'yellow':
          clr = Colors.yellow;
          break;
        case 'đen':
        case 'black':
          clr = Colors.black;
          break;
        case 'trắng':
        case 'white':
          clr = Colors.white;
          break;
        case 'hồng':
        case 'pink':
          clr = Colors.pink;
          break;
        case 'tím':
        case 'purple':
          clr = Colors.purple;
          break;
        case 'cam':
        case 'orange':
          clr = Colors.orange;
          break;
        case 'xám':
        case 'gray':
        case 'grey':
          clr = Colors.grey;
          break;
        case 'be':
          clr = const Color(0xFFF5F0E8);
          break;
        case 'xanh navy':
          clr = const Color(0xFF001F5B);
          break;
        case 'kaki':
          clr = Colors.brown.shade300;
          break;
      }
    }

    if (clr != null) {
      return GestureDetector(
        onTap: () => context.read<ProductDetailProvider>().selectColor(colorVal),
        child: Container(
          margin: const EdgeInsets.only(right: 12),
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(
              color: isSelected ? const Color(0xFFE63B6F) : Colors.transparent,
              width: 2,
            ),
          ),
          child: Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: clr,
              shape: BoxShape.circle,
              border: Border.all(color: Colors.black12, width: 1),
            ),
          ),
        ),
      );
    } else {
      return GestureDetector(
        onTap: () => context.read<ProductDetailProvider>().selectColor(colorVal),
        child: Container(
          margin: const EdgeInsets.only(right: 12),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          decoration: BoxDecoration(
            color: isSelected
                ? const Color(0xFFE63B6F).withValues(alpha: 0.1)
                : Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: isSelected
                  ? const Color(0xFFE63B6F)
                  : const Color(0xFFE2E8F0),
            ),
          ),
          child: Text(
            colorVal,
            style: TextStyle(
              color: isSelected
                  ? const Color(0xFFE63B6F)
                  : const Color(0xFF475569),
              fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
              fontSize: 13,
            ),
          ),
        ),
      );
    }
  }

  Widget _buildSizeChoice(String sizeVal) {
    final isSelected = context.watch<ProductDetailProvider>().selectedSize == sizeVal;
    return GestureDetector(
      onTap: () => context.read<ProductDetailProvider>().selectSize(sizeVal),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFE63B6F) : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isSelected
                ? const Color(0xFFE63B6F)
                : const Color(0xFFE2E8F0),
          ),
        ),
        child: Text(
          sizeVal,
          style: TextStyle(
            color: isSelected ? Colors.white : const Color(0xFF475569),
            fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
            fontSize: 13,
          ),
        ),
      ),
    );
  }

  Widget _imagePlaceholder({double height = 350, double width = double.infinity}) {
    return Container(
      width: width,
      height: height,
      color: const Color(0xFFF1F5F9),
      child: const Center(
        child: Icon(Icons.image_not_supported, size: 60, color: Colors.grey),
      ),
    );
  }

  Widget _buildProductImage(String imgUrl, double width, double height) {
    if (imgUrl.isEmpty) {
      return _imagePlaceholder(height: height, width: width);
    }

    final isSvg = imgUrl.toLowerCase().endsWith('.svg');

    if (isSvg) {
      return SvgPicture.network(
        imgUrl,
        width: width,
        height: height,
        fit: BoxFit.cover,
        placeholderBuilder: (_) => Container(
          width: width,
          height: height,
          color: const Color(0xFFF1F5F9),
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
      width: width,
      height: height,
      fit: BoxFit.cover,
      placeholder: (_, _) => Container(
        width: width,
        height: height,
        color: const Color(0xFFF1F5F9),
        child: const Center(
          child: CircularProgressIndicator(
            strokeWidth: 2,
            color: Color(0xFFE63B6F),
          ),
        ),
      ),
      errorWidget: (_, _, _) => _imagePlaceholder(height: height, width: width),
    );
  }

  Widget _buildRelatedProductCard(Map<String, dynamic> rp) {
    final name = rp['name']?.toString() ?? 'Sản phẩm';
    final imageUrl = AppConfig.productImageUrl(rp);
    final minPrice = rp['min_price'] ?? 0;
    final categoryName = rp['category'] is Map
        ? (rp['category']['name'] ?? '')
        : '';

    return GestureDetector(
      onTap: () {
        context.push('/product-detail', extra: rp);
      },
      child: Container(
        width: 150,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE9ECEF)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.03),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(14),
                topRight: Radius.circular(14),
              ),
              child: _buildProductImage(imageUrl, 150, 130),
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (categoryName.isNotEmpty)
                    Text(
                      categoryName,
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF64748B),
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  const SizedBox(height: 4),
                  Text(
                    name,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF0F172A),
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    FormatUtils.formatPrice(minPrice),
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFFE63B6F),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
