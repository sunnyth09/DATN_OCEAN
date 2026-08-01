import 'package:go_router/go_router.dart';
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../providers/home_provider.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../utils/format_utils.dart';
import '../widgets/network_image_widget.dart';
import '../widgets/shimmer_loading.dart';
import 'coupon_screen.dart';
import 'flash_sale_screen.dart';
import 'notification_screen.dart';
import 'product_list_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  final ScrollController _scrollController = ScrollController();
  Timer? _searchDebounce;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<HomeProvider>();
      if (provider.products.isEmpty) provider.fetchProducts();
      if (provider.categories.isEmpty) provider.fetchCategories();
    });
    
    _scrollController.addListener(() {
      if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
        context.read<HomeProvider>().loadMoreProducts();
      }
    });
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _scrollController.dispose();
    super.dispose();
  }

  void _openSearchResults(String rawText) {
    // Legacy function, no longer used. See _buildSearchBar
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final provider = context.watch<HomeProvider>();

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async {
            await context.read<HomeProvider>().fetchProducts(refresh: true);
            await context.read<HomeProvider>().fetchCategories();
          },
          child: CustomScrollView(
            controller: _scrollController,
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(child: _buildHeader()),
              SliverToBoxAdapter(child: _buildSearchBar()),
              SliverToBoxAdapter(child: _buildHeroBanner()),
              SliverToBoxAdapter(child: _buildQuickActions()),
              SliverToBoxAdapter(child: _buildCategories(provider)),
              _buildProductsSection(provider),
            ],
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () => context.push('/chat'),
        backgroundColor: const Color(0xFFE63B6F),
        child: const Icon(Icons.chat_bubble_outline, color: Colors.white),
      ),
    );
  }

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const Text(
            'Ocean Sport',
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              color: Color(0xFF0F172A),
            ),
          ),
          Row(
            children: [
              IconButton(
                icon: const Icon(
                  Icons.notifications_none,
                  color: Color(0xFF64748B),
                ),
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => const NotificationScreen(),
                    ),
                  );
                },
              ),
              IconButton(
                icon: const Icon(Icons.grid_view, color: Color(0xFF64748B)),
                onPressed: () {},
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSearchBar() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: GestureDetector(
        onTap: () {
          context.push('/search');
        },
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(30),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.05),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 15),
          child: Row(
            children: [
              const Icon(Icons.search, color: Color(0xFF64748B)),
              const SizedBox(width: 10),
              const Expanded(
                child: Text(
                  'Bạn muốn tìm gì?',
                  style: TextStyle(color: Color(0xFF64748B), fontSize: 15),
                ),
              ),
              const Icon(Icons.filter_list, color: Color(0xFFE63B6F)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeroBanner() {
    return Container(
      margin: const EdgeInsets.all(20),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFE63B6F), Color(0xFFFF8FAB)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Text(
              'HOT DEAL',
              style: TextStyle(
                color: Colors.white,
                fontSize: 10,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          const SizedBox(height: 12),
          const Text(
            'Mùa Hè Sôi Động\nCÙNG QUYỀN SPORT',
            style: TextStyle(
              color: Colors.white,
              fontSize: 22,
              fontWeight: FontWeight.w900,
              height: 1.2,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Giảm ngay 25% cho tất cả đồ thể thao chính hãng.',
            style: TextStyle(color: Colors.white70, fontSize: 13),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              ElevatedButton(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const FlashSaleScreen()),
                  );
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: const Color(0xFFE63B6F),
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(20),
                  ),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 20,
                    vertical: 12,
                  ),
                ),
                child: const Text(
                  'Khám phá ngay',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
              ),
              const SizedBox(width: 10),
              OutlinedButton.icon(
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const FlashSaleScreen()),
                  );
                },
                icon: const Icon(Icons.flash_on, size: 16, color: Colors.white),
                label: const Text(
                  'Flash Sale',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
                style: OutlinedButton.styleFrom(
                  side: BorderSide(color: Colors.white.withValues(alpha: 0.5)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(20),
                  ),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 12,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ===== QUICK ACTION BUTTONS =====
  Widget _buildQuickActions() {
    final actions = [
      {
        'icon': Icons.flash_on,
        'label': 'Flash Sale',
        'color': const Color(0xFFE63B6F),
        'screen': const FlashSaleScreen(),
      },
      {
        'icon': Icons.confirmation_number_outlined,
        'label': 'Voucher',
        'color': const Color(0xFF3B82F6),
        'screen': const CouponScreen(),
      },
      {
        'icon': Icons.sports_tennis,
        'label': 'Đặt sân',
        'color': const Color(0xFF10B981),
        'screen': null,
      },
      {
        'icon': Icons.grid_view_rounded,
        'label': 'Sản phẩm',
        'color': const Color(0xFFF59E0B),
        'screen': const ProductListScreen(),
      },
    ];

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: actions.map((action) {
          final icon = action['icon'] as IconData;
          final label = action['label'] as String;
          final color = action['color'] as Color;
          final screen = action['screen'] as Widget?;

          return GestureDetector(
            onTap: () {
              if (screen != null) {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => screen),
                );
              }
            },
            child: Column(
              children: [
                Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Icon(icon, color: color, size: 26),
                ),
                const SizedBox(height: 8),
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFF475569),
                  ),
                ),
              ],
            ),
          );
        }).toList(),
      ),
    );
  }

  /// Lấy icon thích hợp dựa trên tên danh mục
  IconData _iconForCategory(String name) {
    final n = name.toLowerCase();
    if (n.contains('lặn') || n.contains('bơi') || n.contains('dưới nước')) {
      return Icons.scuba_diving;
    }
    if (n.contains('lướt')) return Icons.surfing;
    if (n.contains('dã ngoại') ||
        n.contains('leo núi') ||
        n.contains('cắm trại')) {
      return Icons.hiking;
    }
    if (n.contains('phụ kiện') || n.contains('đồng hồ') || n.contains('kính')) {
      return Icons.watch;
    }
    if (n.contains('quần áo') || n.contains('thời trang') || n.contains('áo')) {
      return Icons.checkroom;
    }
    if (n.contains('giày') || n.contains('dép') || n.contains('sản phẩm')) {
      return Icons.format_list_bulleted;
    }
    if (n.contains('kayak') || n.contains('chèo') || n.contains('thỹền')) {
      return Icons.rowing;
    }
    if (n.contains('câu cá') || n.contains('bắt cá')) return Icons.phishing;
    if (n.contains('thể thao') || n.contains('sport')) return Icons.sports;
    if (n.contains('bảo hộ') || n.contains('an toàn')) return Icons.security;
    if (n.contains('đèn') || n.contains('chiếu sáng')) {
      return Icons.flashlight_on;
    }
    if (n.contains('tús') || n.contains('balo')) return Icons.backpack;
    if (n.contains('máy ảnh') || n.contains('camera') || n.contains('quay')) {
      return Icons.camera_alt;
    }
    if (n.contains('kife') || n.contains('dao') || n.contains('công cụ')) {
      return Icons.handyman;
    }
    if (n.contains('giày lặn') || n.contains('chân nhái')) {
      return Icons.do_not_step;
    }
    if (n.contains('xe') || n.contains('đạp')) return Icons.directions_bike;
    if (n.contains('sóng') || n.contains('biển')) return Icons.waves;
    return Icons.category_outlined;
  }

  /// Màu gradient theo index cho đẹp
  List<Color> _colorsForIndex(int index) {
    const palettes = [
      [Color(0xFFFFF0F3), Color(0xFFBAE6FD)],
      [Color(0xFFF0FDF4), Color(0xFFBBF7D0)],
      [Color(0xFFFFF7ED), Color(0xFFFED7AA)],
      [Color(0xFFFDF4FF), Color(0xFFF5D0FE)],
      [Color(0xFFFFF1F2), Color(0xFFFFCDD2)],
      [Color(0xFFF0F9FF), Color(0xFFB3E5FC)],
      [Color(0xFFF0FFF4), Color(0xFFB3DFBD)],
      [Color(0xFFFFFBEB), Color(0xFFFDE68A)],
    ];
    return palettes[index % palettes.length];
  }

  static const List<Color> _iconColors = [
    Color(0xFFE63B6F),
    Color(0xFF16A34A),
    Color(0xFFD97706),
    Color(0xFF9333EA),
    Color(0xFFE11D48),
    Color(0xFFE63B6F),
    Color(0xFF059669),
    Color(0xFFCA8A04),
  ];

  Widget _buildCategories(HomeProvider provider) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Danh mục phổ biến',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF0F172A),
                ),
              ),
              TextButton(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const ProductListScreen()),
                ),
                child: const Text(
                  'Xem tất cả',
                  style: TextStyle(
                    color: Color(0xFFE63B6F),
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        SizedBox(
          height: 110,
          child: provider.isCategoriesLoading
              ? ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  itemCount: 5,
                  itemBuilder: (_, _) => Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    child: Column(
                      children: [
                        Container(
                          width: 60,
                          height: 60,
                          decoration: BoxDecoration(
                            color: Colors.grey.shade200,
                            borderRadius: BorderRadius.circular(30),
                          ),
                        ),
                        const SizedBox(height: 8),
                        Container(
                          width: 50,
                          height: 10,
                          decoration: BoxDecoration(
                            color: Colors.grey.shade200,
                            borderRadius: BorderRadius.circular(4),
                          ),
                        ),
                      ],
                    ),
                  ),
                )
              : provider.categories.isEmpty
              ? const Center(
                  child: Text(
                    'Chưa có danh mục',
                    style: TextStyle(color: Colors.grey),
                  ),
                )
              : ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  itemCount: provider.categories.length,
                  itemBuilder: (context, index) {
                    final cat = provider.categories[index];
                    final catName = cat['name']?.toString() ?? '';
                    final catId = cat['category_id'] ?? cat['id'];
                    final colors = _colorsForIndex(index);
                    final iconColor = _iconColors[index % _iconColors.length];
                    final icon = _iconForCategory(catName);

                    return GestureDetector(
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ProductListScreen(
                            categoryId: catId is int
                                ? catId
                                : int.tryParse(catId.toString()),
                            categoryName: catName,
                          ),
                        ),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 64,
                              height: 64,
                              decoration: BoxDecoration(
                                gradient: LinearGradient(
                                  colors: colors,
                                  begin: Alignment.topLeft,
                                  end: Alignment.bottomRight,
                                ),
                                borderRadius: BorderRadius.circular(20),
                                boxShadow: [
                                  BoxShadow(
                                    color: colors[1].withValues(alpha: 0.5),
                                    blurRadius: 8,
                                    offset: const Offset(0, 3),
                                  ),
                                ],
                              ),
                              child: Icon(icon, color: iconColor, size: 30),
                            ),
                            const SizedBox(height: 8),
                            SizedBox(
                              width: 70,
                              child: Text(
                                catName,
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                  color: Color(0xFF475569),
                                ),
                                textAlign: TextAlign.center,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }

  Widget _buildProductsSection(HomeProvider provider) {
    return SliverPadding(
      padding: const EdgeInsets.fromLTRB(20, 10, 20, 20),
      sliver: SliverMainAxisGroup(
        slivers: [
          SliverToBoxAdapter(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Dành cho bạn',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    const Icon(Icons.more_horiz, color: Color(0xFF64748B)),
                  ],
                ),
                const SizedBox(height: 16),
                if (provider.productsErrorMessage != null && !provider.isProductsLoading)
                  Center(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Text(
                        provider.productsErrorMessage!,
                        style: const TextStyle(color: Colors.red),
                      ),
                    ),
                  )
                else if (provider.products.isEmpty && !provider.isProductsLoading)
                  const Center(
                    child: Padding(
                      padding: EdgeInsets.all(20),
                      child: Text('Không có sản phẩm nào phù hợp'),
                    ),
                  ),
              ],
            ),
          ),
          if (provider.isProductsLoading && provider.products.isEmpty)
            const SliverShimmerLoading()
          else if (!provider.isProductsLoading && provider.productsErrorMessage == null && provider.products.isNotEmpty)
            SliverGrid(
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: 16,
                mainAxisSpacing: 16,
                childAspectRatio: 0.65,
              ),
              delegate: SliverChildBuilderDelegate((context, index) {
                return _buildProductCard(provider.products[index]);
              }, childCount: provider.products.length),
            ),
        ],
      ),
    );
  }

  Widget _buildProductCard(Map<String, dynamic> product) {
    final name = product['name'] ?? 'Không tên';
    final dynamic rawPrice =
        product['min_price'] ??
        (product['lowest_price_variant'] != null
            ? product['lowest_price_variant']['price']
            : 0);

    final imageUrl = AppConfig.productImageUrl(product);

    return GestureDetector(
      onTap: () {
        context.push('/product-detail', extra: product);
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.03),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                Hero(
                  tag: product['id'] ?? product['slug'] ?? 'product_image_${product.hashCode}',
                  child: NetworkImageWidget(
                    imageUrl: imageUrl,
                    height: 160,
                    width: double.infinity,
                    fit: BoxFit.cover,
                    borderRadius: const BorderRadius.only(
                      topLeft: Radius.circular(16),
                      topRight: Radius.circular(16),
                    ),
                    placeholder: Container(
                      height: 160,
                      color: const Color(0xFFF1F5F9),
                      child: const Center(
                        child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFE63B6F)),
                      ),
                    ),
                    errorWidget: _imagePlaceholder(),
                  ),
                ),
                Positioned(
                  top: 8,
                  right: 8,
                  child: GestureDetector(
                    onTap: () async {
                      final messenger = ScaffoldMessenger.of(context);
                      try {
                        final loggedIn = await AuthService.isLoggedIn();
                        if (!loggedIn) {
                          messenger.showSnackBar(
                            const SnackBar(
                              content: Text('Vui lòng đăng nhập để lưu!'),
                            ),
                          );
                          return;
                        }
                        await ApiClient().dio.post(
                          '/profile/favorites/toggle',
                          data: {
                            'product_id':
                                product['product_id'] ?? product['id'],
                          },
                        );
                        messenger.showSnackBar(
                          const SnackBar(
                            content: Text('Đã cập nhật danh sách yêu thích!'),
                            duration: Duration(seconds: 1),
                          ),
                        );
                      } catch (_) {
                        messenger.showSnackBar(
                          const SnackBar(
                            content: Text(
                              'Không thể cập nhật yêu thích. Vui lòng thử lại.',
                            ),
                            backgroundColor: Colors.red,
                          ),
                        );
                      }
                    },
                    child: Container(
                      padding: const EdgeInsets.all(6),
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.favorite_border,
                        size: 16,
                        color: Color(0xFF64748B),
                      ),
                    ),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name.toString(),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF1E293B),
                      height: 1.3,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        FormatUtils.formatPrice(rawPrice),
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w900,
                          color: Color(0xFFE63B6F),
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.all(6),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE63B6F),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Icon(
                          Icons.shopping_cart_outlined,
                          size: 14,
                          color: Colors.white,
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
    );
  }

  Widget _imagePlaceholder() {
    return Container(
      height: 160,
      color: const Color(0xFFF1F5F9),
      child: const Center(
        child: Icon(Icons.image_not_supported, size: 30, color: Colors.grey),
      ),
    );
  }
}
