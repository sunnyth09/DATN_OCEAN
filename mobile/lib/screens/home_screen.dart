import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/cart_provider.dart';
import '../providers/coupon_provider.dart';
import '../providers/home_provider.dart';
import '../utils/format_utils.dart';
import '../widgets/network_image_widget.dart';
import '../widgets/shimmer_loading.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/app_toast.dart';
import '../widgets/product_card.dart';

/// Màn hình Trang Chủ tối ưu hiệu năng cao (60-120 FPS):
/// - Tách biệt các Timer (Flash Sale, Hero Banner, Search Ticker) thành các Widget con độc lập.
/// - Loại bỏ hoàn toàn rebuild toàn trang tại cấp Scaffold.
/// - ValueNotifier cho nút BackToTop và debounce khi tải thêm sản phẩm.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  final ScrollController _scrollController = ScrollController();
  final ValueNotifier<bool> _showBackToTopNotifier = ValueNotifier<bool>(false);
  final ValueNotifier<String> _selectedTabNotifier = ValueNotifier<String>('all');

  DateTime _lastLoadMoreTime = DateTime.now();

  @override
  void initState() {
    super.initState();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      context.read<CartProvider>().fetchCart(silent: true);
      context.read<CouponProvider>().fetchUserCoupons(silent: true);
      final provider = context.read<HomeProvider>();
      if (provider.products.isEmpty) provider.fetchProducts();
      if (provider.categories.isEmpty) provider.fetchCategories();
      provider.fetchFlashSale();
      provider.fetchHomeCollections();
      provider.fetchVouchers();
    });

    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    final offset = _scrollController.position.pixels;
    if (offset >= _scrollController.position.maxScrollExtent - 350) {
      final now = DateTime.now();
      if (now.difference(_lastLoadMoreTime).inMilliseconds > 400) {
        _lastLoadMoreTime = now;
        context.read<HomeProvider>().loadMoreProducts();
      }
    }

    final shouldShow = offset > 500;
    if (_showBackToTopNotifier.value != shouldShow) {
      _showBackToTopNotifier.value = shouldShow;
    }
  }

  void _scrollToTop() {
    _scrollController.animateTo(
      0,
      duration: const Duration(milliseconds: 450),
      curve: Curves.easeOutCubic,
    );
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    _showBackToTopNotifier.dispose();
    _selectedTabNotifier.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        top: false,
        child: RefreshIndicator(
          color: AppColors.primary,
          onRefresh: () async {
            await Future.wait([
              context.read<HomeProvider>().fetchProducts(refresh: true),
              context.read<HomeProvider>().fetchCategories(),
              context.read<HomeProvider>().fetchFlashSale(),
              context.read<HomeProvider>().fetchVouchers(),
              context.read<CouponProvider>().fetchPublicCoupons(silent: true),
              context.read<CouponProvider>().fetchUserCoupons(silent: true),
            ]);
          },
          child: CustomScrollView(
            controller: _scrollController,
            cacheExtent: 800,
            physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
            slivers: [
              // 1. Top Section: Brand Aurora Header & 3D Hero Carousel (Tách biệt độc lập)
              const SliverToBoxAdapter(
                child: RepaintBoundary(child: _HomeTopBrandSection()),
              ),

              // 2. Lưới Dịch Vụ & Ngành Hàng Thể Thao Soft Pastel (2x4 Grid)
              const SliverToBoxAdapter(
                child: RepaintBoundary(child: _HomeCategoryGrid()),
              ),

              // 3. Dải Voucher Răng Cưa 1 Chạm Lưu
              const SliverToBoxAdapter(
                child: RepaintBoundary(child: _HomeVoucherStrip()),
              ),

              // 4. Flash Sale Box Rực Cháy (Timer đếm ngược độc lập, không rebuild toàn trang)
              Consumer<HomeProvider>(
                builder: (context, provider, _) {
                  if (provider.flashSaleProducts.isEmpty && !provider.isFlashSaleLoading) {
                    return const SliverToBoxAdapter(child: SizedBox.shrink());
                  }
                  return SliverToBoxAdapter(
                    child: RepaintBoundary(
                      child: _HomeFlashSaleSection(
                        flashSaleProducts: provider.flashSaleProducts,
                        isLoading: provider.isFlashSaleLoading,
                      ),
                    ),
                  );
                },
              ),

              // 5. Ocean Mall - Gian Hàng Uỷ Quyền Chính Hãng
              const SliverToBoxAdapter(
                child: RepaintBoundary(child: _HomeOceanMallSection()),
              ),

              // 6. Header "Gợi Ý Hôm Nay" Kèm Tab Lọc
              SliverToBoxAdapter(
                child: _HomeRecommendationHeader(tabNotifier: _selectedTabNotifier),
              ),

              // 7. Grid Sản Phẩm 2 Cột Tối Ưu Render 60-120 FPS
              ValueListenableBuilder<String>(
                valueListenable: _selectedTabNotifier,
                builder: (context, selectedTab, _) {
                  return Consumer<HomeProvider>(
                    builder: (context, provider, _) {
                      return _buildProductSliverGrid(context, provider, selectedTab);
                    },
                  );
                },
              ),

              // 8. Load more spinner
              Consumer<HomeProvider>(
                builder: (context, provider, _) {
                  if (!provider.isFetchingMore) return const SliverToBoxAdapter(child: SizedBox.shrink());
                  return const SliverToBoxAdapter(
                    child: Padding(
                      padding: EdgeInsets.symmetric(vertical: 20),
                      child: Center(
                        child: CircularProgressIndicator(
                          strokeWidth: 2.5,
                          color: AppColors.primary,
                        ),
                      ),
                    ),
                  );
                },
              ),

              const SliverToBoxAdapter(child: SizedBox(height: 36)),
            ],
          ),
        ),
      ),
      floatingActionButton: ValueListenableBuilder<bool>(
        valueListenable: _showBackToTopNotifier,
        builder: (context, show, _) {
          return AnimatedScale(
            scale: show ? 1.0 : 0.0,
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeOutBack,
            child: FloatingActionButton.small(
              heroTag: 'home_back_to_top',
              onPressed: _scrollToTop,
              backgroundColor: Colors.white,
              foregroundColor: const Color(0xFF0F172A),
              elevation: 4,
              child: const Icon(Icons.arrow_upward_rounded, size: 20),
            ),
          );
        },
      ),
    );
  }

  // ── 7. Grid Sản Phẩm 2 Cột ──
  Widget _buildProductSliverGrid(BuildContext context, HomeProvider provider, String selectedTab) {
    if (provider.isInitialLoading && provider.products.isEmpty) {
      return const SliverShimmerLoading(
        padding: EdgeInsets.all(12),
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        childAspectRatio: 0.65,
      );
    }

    if (provider.productsErrorMessage != null && provider.products.isEmpty) {
      return SliverToBoxAdapter(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 40),
          child: AppEmptyState(
            icon: Icons.wifi_off_rounded,
            title: 'Không thể tải sản phẩm',
            message: provider.productsErrorMessage!,
            buttonText: 'Thử lại',
            onAction: () => provider.fetchProducts(refresh: true),
          ),
        ),
      );
    }

    // Lọc sản phẩm theo tab
    List<dynamic> displayProducts = provider.products;
    if (selectedTab == 'hot') {
      displayProducts = provider.bestSellingProducts.isNotEmpty ? provider.bestSellingProducts : provider.products;
    } else if (selectedTab == 'sale') {
      displayProducts = provider.onSaleProducts.isNotEmpty ? provider.onSaleProducts : provider.products;
    } else if (selectedTab == 'new') {
      displayProducts = provider.products;
    } else if (selectedTab == 'top_rated') {
      displayProducts = provider.bestSellingProducts.isNotEmpty ? provider.bestSellingProducts : provider.products;
    }

    if (displayProducts.isEmpty) {
      return const SliverToBoxAdapter(
        child: Padding(
          padding: EdgeInsets.symmetric(vertical: 40),
          child: Center(
            child: Text(
              'Chưa có sản phẩm nào trong danh mục này.',
              style: TextStyle(color: Color(0xFF64748B)),
            ),
          ),
        ),
      );
    }

    return SliverPadding(
      padding: const EdgeInsets.fromLTRB(12, 4, 12, 16),
      sliver: SliverGrid(
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 0.65,
        ),
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            final product = displayProducts[index];
            return ProductCard(
              key: ValueKey('prod_${product['id'] ?? product['product_id'] ?? index}'),
              product: product is Map<String, dynamic> ? product : Map<String, dynamic>.from(product),
            );
          },
          childCount: displayProducts.length,
        ),
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// 1. TOP BRAND SECTION (Header + Search Ticker + 3D Hero Carousel)
// ══════════════════════════════════════════════════════════════════════════
class _HomeTopBrandSection extends StatelessWidget {
  const _HomeTopBrandSection();

  @override
  Widget build(BuildContext context) {
    final topPadding = MediaQuery.of(context).padding.top;
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [
            Color(0xFFE63B6F),
            Color(0xFFF43F5E),
            Colors.white,
          ],
          stops: [0.0, 0.44, 1.0],
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
        ),
      ),
      child: Column(
        children: [
          SizedBox(height: topPadding + 2),
          // Header Row: Search + Chat + Cart
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 8),
            child: Row(
              children: [
                // Thanh Tìm Kiếm Đa Năng Ticker Độc Lập
                const Expanded(
                  child: _HomeSearchTicker(),
                ),
                const SizedBox(width: 8),

                // Nút Tin Nhắn / Chat
                GestureDetector(
                  onTap: () {
                    HapticFeedback.lightImpact();
                    context.push('/chat');
                  },
                  behavior: HitTestBehavior.opaque,
                  child: Container(
                    width: 38,
                    height: 40,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.08),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: const Icon(Icons.chat_bubble_outline_rounded, color: Color(0xFF0F172A), size: 19),
                  ),
                ),
                const SizedBox(width: 8),

                // Nút Giỏ Hàng với Badge động
                GestureDetector(
                  onTap: () {
                    HapticFeedback.lightImpact();
                    context.push('/cart');
                  },
                  behavior: HitTestBehavior.opaque,
                  child: Container(
                    width: 38,
                    height: 40,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.08),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Stack(
                      alignment: Alignment.center,
                      children: [
                        const Icon(Icons.shopping_bag_outlined, color: Color(0xFF0F172A), size: 20),
                        Consumer<CartProvider>(
                          builder: (context, cart, _) {
                            final count = cart.itemCount;
                            if (count <= 0) return const SizedBox.shrink();
                            return Positioned(
                              top: 4,
                              right: 4,
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1.5),
                                decoration: const BoxDecoration(
                                  gradient: AppGradients.primary,
                                  shape: BoxShape.circle,
                                ),
                                constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                                child: Center(
                                  child: Text(
                                    count > 99 ? '99+' : '$count',
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 8.5,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),

          // 3D Hero Carousel Tự Động Trượt Độc Lập
          const _HomeHeroBanner(),
        ],
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// SEARCH BAR TICKER (Độc lập, không rebuild trang khi đổi từ khóa)
// ══════════════════════════════════════════════════════════════════════════
class _HomeSearchTicker extends StatefulWidget {
  const _HomeSearchTicker();

  @override
  State<_HomeSearchTicker> createState() => _HomeSearchTickerState();
}

class _HomeSearchTickerState extends State<_HomeSearchTicker> {
  int _tickerIndex = 0;
  Timer? _timer;

  static const List<String> _trendingKeywords = [
    'Vợt Yonex Astrox 100ZZ',
    'Giày Babolat Jet Mach 4',
    'Vợt Victor Thruster K',
    'Săn Voucher Freeship 0đ',
    'Giày Lining Sonic Pro',
    'Áo Thi Đấu Thể Thao VIP',
    'Balo Vợt Tennis Chuyên Dụng',
  ];

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (mounted) {
        setState(() {
          _tickerIndex = (_tickerIndex + 1) % _trendingKeywords.length;
        });
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        HapticFeedback.lightImpact();
        context.push('/search');
      },
      child: Container(
        height: 40,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(22),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.08),
              blurRadius: 10,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        padding: const EdgeInsets.symmetric(horizontal: 12),
        child: Row(
          children: [
            const Icon(Icons.search_rounded, color: AppColors.primary, size: 20),
            const SizedBox(width: 8),
            Expanded(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 350),
                transitionBuilder: (child, anim) => FadeTransition(
                  opacity: anim,
                  child: SlideTransition(
                    position: Tween<Offset>(
                      begin: const Offset(0, 0.3),
                      end: Offset.zero,
                    ).animate(anim),
                    child: child,
                  ),
                ),
                child: SizedBox(
                  key: ValueKey<int>(_tickerIndex),
                  width: double.infinity,
                  child: Text(
                    _trendingKeywords[_tickerIndex],
                    maxLines: 1,
                    textAlign: TextAlign.left,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF64748B),
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 6),
            GestureDetector(
              onTap: () {
                HapticFeedback.lightImpact();
                context.push('/product-scanner');
              },
              child: Container(
                padding: const EdgeInsets.all(5),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.08),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.camera_alt_outlined, color: AppColors.primary, size: 16),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// HERO BANNER 3D CAROUSEL (Độc lập)
// ══════════════════════════════════════════════════════════════════════════
class _HomeHeroBanner extends StatefulWidget {
  const _HomeHeroBanner();

  @override
  State<_HomeHeroBanner> createState() => _HomeHeroBannerState();
}

class _HomeHeroBannerState extends State<_HomeHeroBanner> {
  static const int _kInitialBannerPage = 1200;
  late final PageController _bannerController;
  int _currentBannerIndex = 0;
  Timer? _bannerTimer;

  static const List<Map<String, dynamic>> _heroBanners = [
    {
      'tag': 'SIÊU SALE 2026',
      'title': 'Vợt Cầu Lông & Tennis',
      'highlight': 'GIẢM TỚI 35%',
      'cta': 'Khám phá ngay',
      'image': '/storage/products/72b710a6-2931-4dd7-a229-887e527bcf80.webp',
      'gradient': [Color(0xFFE11D48), Color(0xFFBE185D)],
      'action': '/flash-sale',
    },
    {
      'tag': 'BỘ SƯU TẬP MỚI',
      'title': 'Giày Thi Đấu Chính Hãng',
      'highlight': 'ĐỆM KHÍ CAO CẤP',
      'cta': 'Xem sản phẩm',
      'image': '/storage/products/f5801e74-1129-4d64-a1c3-00c68f34e191.webp',
      'gradient': [Color(0xFF1E3A8A), Color(0xFF0284C7)],
      'action': '/shop',
    },
    {
      'tag': 'ĐẶC QUYỀN VIP',
      'title': 'Áo Đấu & Phụ Kiện',
      'highlight': 'TẶNG VOUCHER 500K',
      'cta': 'Lấy mã ngay',
      'image': '/storage/products/026f9252-4579-4f19-a72a-740bfeb619ee.webp',
      'gradient': [Color(0xFFB45309), Color(0xFFEA580C)],
      'action': '/coupon',
    },
  ];

  @override
  void initState() {
    super.initState();
    _bannerController = PageController(initialPage: _kInitialBannerPage);
    _bannerTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!_bannerController.hasClients) return;
      _bannerController.nextPage(
        duration: const Duration(milliseconds: 550),
        curve: Curves.easeInOutCubic,
      );
    });
  }

  @override
  void dispose() {
    _bannerTimer?.cancel();
    _bannerController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 4, bottom: 8),
      child: Column(
        children: [
          SizedBox(
            height: 128,
            child: PageView.builder(
              controller: _bannerController,
              onPageChanged: (index) {
                setState(() {
                  _currentBannerIndex = index % _heroBanners.length;
                });
              },
              itemBuilder: (context, index) {
                final b = _heroBanners[index % _heroBanners.length];
                final gradient = b['gradient'] as List<Color>;

                return GestureDetector(
                  onTap: () {
                    HapticFeedback.lightImpact();
                    context.push(b['action'] as String);
                  },
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 14),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: gradient,
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(18),
                      boxShadow: [
                        BoxShadow(
                          color: gradient[0].withValues(alpha: 0.35),
                          blurRadius: 10,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: Colors.white.withValues(alpha: 0.22),
                                    borderRadius: BorderRadius.circular(5),
                                  ),
                                  child: Text(
                                    b['tag'] as String,
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 9.5,
                                      fontWeight: FontWeight.w900,
                                      letterSpacing: 0.4,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  b['title'] as String,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 14.5,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: -0.3,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  b['highlight'] as String,
                                  style: const TextStyle(
                                    color: Color(0xFFFFE082),
                                    fontSize: 13,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.08),
                                  blurRadius: 5,
                                  offset: const Offset(0, 2),
                                ),
                              ],
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  b['cta'] as String,
                                  style: TextStyle(
                                    color: gradient[0],
                                    fontSize: 11,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                const SizedBox(width: 4),
                                Icon(Icons.arrow_forward_rounded, size: 12, color: gradient[0]),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 6),
          // Indicator Dots
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(
              _heroBanners.length,
              (i) => AnimatedContainer(
                duration: const Duration(milliseconds: 250),
                margin: const EdgeInsets.symmetric(horizontal: 2.5),
                width: _currentBannerIndex == i ? 18 : 5,
                height: 4,
                decoration: BoxDecoration(
                  color: _currentBannerIndex == i ? AppColors.primary : const Color(0xFFCBD5E1),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// 2. CATEGORY ICON GRID
// ══════════════════════════════════════════════════════════════════════════
class _HomeCategoryGrid extends StatelessWidget {
  const _HomeCategoryGrid();

  @override
  Widget build(BuildContext context) {
    final quickItems = [
      {
        'label': 'Cầu Lông',
        'icon': Icons.sports_tennis_rounded,
        'bgColor': const Color(0xFFEFF6FF),
        'iconColor': const Color(0xFF2563EB),
        'borderColor': const Color(0xFFDBEAFE),
        'badge': null,
        'action': () => context.push('/product-list', extra: {'searchQuery': 'Cầu lông', 'categoryName': 'Cầu Lông'}),
      },
      {
        'label': 'Pickleball',
        'icon': Icons.sports_cricket_rounded,
        'bgColor': const Color(0xFFECFDF5),
        'iconColor': const Color(0xFF059669),
        'borderColor': const Color(0xFFD1FAE5),
        'badge': null,
        'action': () => context.push('/product-list', extra: {'searchQuery': 'Pickleball', 'categoryName': 'Pickleball'}),
      },
      {
        'label': 'Bóng Chuyền',
        'icon': Icons.sports_volleyball_rounded,
        'bgColor': const Color(0xFFFFF7ED),
        'iconColor': const Color(0xFFEA580C),
        'borderColor': const Color(0xFFFFEDD5),
        'badge': null,
        'action': () => context.push('/product-list', extra: {'searchQuery': 'Bóng chuyền', 'categoryName': 'Bóng Chuyền'}),
      },
      {
        'label': 'Giày Thể Thao',
        'icon': Icons.directions_run_rounded,
        'bgColor': const Color(0xFFF5F3FF),
        'iconColor': const Color(0xFF7C3AED),
        'borderColor': const Color(0xFFEDE9FE),
        'badge': null,
        'action': () => context.push('/product-list', extra: {'searchQuery': 'Giày', 'categoryName': 'Giày Thể Thao'}),
      },
      {
        'label': 'Đặt Sân Online',
        'icon': Icons.sports_tennis_rounded,
        'bgColor': const Color(0xFFF0FDF4),
        'iconColor': const Color(0xFF16A34A),
        'borderColor': const Color(0xFFDCFCE7),
        'badge': 'LIVE',
        'action': () => context.go('/court'),
      },
      {
        'label': 'Flash Sale',
        'icon': Icons.local_fire_department_rounded,
        'bgColor': const Color(0xFFFFF1F2),
        'iconColor': const Color(0xFFE11D48),
        'borderColor': const Color(0xFFFFE4E6),
        'badge': 'HOT',
        'action': () => context.push('/flash-sale'),
      },
      {
        'label': 'Săn Voucher',
        'icon': Icons.confirmation_number_rounded,
        'bgColor': const Color(0xFFFFFBEB),
        'iconColor': const Color(0xFFD97706),
        'borderColor': const Color(0xFFFEF3C7),
        'badge': null,
        'action': () => context.push('/coupon'),
      },
      {
        'label': 'Đổi Quà VIP',
        'icon': Icons.card_giftcard_rounded,
        'bgColor': const Color(0xFFEEF2FF),
        'iconColor': const Color(0xFF4F46E5),
        'borderColor': const Color(0xFFE0E7FF),
        'badge': null,
        'action': () => context.push('/loyalty'),
      },
    ];

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 2, 14, 8),
      padding: const EdgeInsets.fromLTRB(10, 14, 10, 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFF1F5F9), width: 1),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.035),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: GridView.builder(
        shrinkWrap: true,
        padding: EdgeInsets.zero,
        physics: const NeverScrollableScrollPhysics(),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 4,
          mainAxisSpacing: 10,
          crossAxisSpacing: 6,
          childAspectRatio: 0.76,
        ),
        itemCount: quickItems.length,
        itemBuilder: (context, index) {
          final item = quickItems[index];
          final bgColor = item['bgColor'] as Color;
          final iconColor = item['iconColor'] as Color;
          final borderColor = item['borderColor'] as Color;
          final icon = item['icon'] as IconData;
          final label = item['label'] as String;
          final badge = item['badge'] as String?;
          final callback = item['action'] as VoidCallback;

          return GestureDetector(
            onTap: () {
              HapticFeedback.selectionClick();
              callback();
            },
            behavior: HitTestBehavior.opaque,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Stack(
                  clipBehavior: Clip.none,
                  children: [
                    Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: bgColor,
                        borderRadius: BorderRadius.circular(15),
                        border: Border.all(color: borderColor, width: 1),
                        boxShadow: [
                          BoxShadow(
                            color: iconColor.withValues(alpha: 0.12),
                            blurRadius: 6,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Center(
                        child: Icon(icon, color: iconColor, size: 23),
                      ),
                    ),
                    if (badge != null)
                      Positioned(
                        top: -4,
                        right: -6,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                          decoration: BoxDecoration(
                            gradient: badge == 'HOT'
                                ? const LinearGradient(colors: [Color(0xFFFF2A55), Color(0xFFFF5E3A)])
                                : const LinearGradient(colors: [Color(0xFF10B981), Color(0xFF059669)]),
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: Colors.white, width: 1.2),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.15),
                                blurRadius: 3,
                                offset: const Offset(0, 1),
                              ),
                            ],
                          ),
                          child: Text(
                            badge,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 7.5,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  label,
                  textAlign: TextAlign.center,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF0F172A),
                    letterSpacing: -0.2,
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// 3. VOUCHER STRIP
// ══════════════════════════════════════════════════════════════════════════
class _HomeVoucherStrip extends StatelessWidget {
  const _HomeVoucherStrip();

  @override
  Widget build(BuildContext context) {
    return Consumer2<CouponProvider, HomeProvider>(
      builder: (context, couponProv, homeProv, _) {
        final vouchers = couponProv.publicCoupons.isNotEmpty
            ? couponProv.publicCoupons.take(6).toList()
            : (homeProv.homeVouchers.isNotEmpty ? homeProv.homeVouchers.take(6).toList() : []);

        if (vouchers.isEmpty) return const SizedBox.shrink();

        return Container(
          margin: const EdgeInsets.fromLTRB(14, 0, 14, 8),
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
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
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      gradient: AppGradients.primary,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Icon(Icons.confirmation_number_rounded, color: Colors.white, size: 14),
                  ),
                  const SizedBox(width: 8),
                  const Text(
                    'KHO VOUCHER ĐỘC QUYỀN',
                    style: TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w900,
                      color: Color(0xFF0F172A),
                      letterSpacing: 0.2,
                    ),
                  ),
                  const Spacer(),
                  GestureDetector(
                    onTap: () {
                      HapticFeedback.lightImpact();
                      context.push('/coupon');
                    },
                    child: const Row(
                      children: [
                        Text('Xem thêm', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.primary)),
                        Icon(Icons.chevron_right_rounded, size: 16, color: AppColors.primary),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              SizedBox(
                height: 72,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  physics: const BouncingScrollPhysics(),
                  itemCount: vouchers.length,
                  itemBuilder: (context, index) {
                    final v = vouchers[index];
                    final id = int.tryParse((v['id'] ?? v['coupon_id'] ?? 0).toString()) ?? 0;
                    final isSaved = couponProv.isSaved(id);
                    final code = v['code']?.toString() ?? 'VOUCHER';

                    final type = v['type']?.toString();
                    final value = v['value'];
                    final minOrder = v['min_order_value'];
                    final num numVal = FormatUtils.parseNum(value);
                    final num numMin = FormatUtils.parseNum(minOrder);

                    String discountStr = '';
                    if (type == 'percent') {
                      discountStr = 'Giảm ${numVal.toInt()}%';
                    } else if (type == 'free_ship') {
                      discountStr = 'Freeship ${FormatUtils.formatPrice(numVal)}';
                    } else if (numVal > 0) {
                      discountStr = 'Giảm ${FormatUtils.formatPrice(numVal)}';
                    } else {
                      discountStr = v['name']?.toString() ?? v['description']?.toString() ?? 'Ưu đãi thể thao';
                    }

                    final name = numMin > 0
                        ? '$discountStr đơn từ ${FormatUtils.formatPrice(numMin)}'
                        : discountStr;

                    return Container(
                      width: 236,
                      margin: const EdgeInsets.only(right: 10),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFFFFF7F9), Color(0xFFFFF0F4)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: const Color(0xFFFFE0E6), width: 1),
                      ),
                      child: Row(
                        children: [
                          Container(
                            width: 44,
                            padding: const EdgeInsets.symmetric(horizontal: 6),
                            child: Center(
                              child: Container(
                                width: 32,
                                height: 32,
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  shape: BoxShape.circle,
                                  border: Border.all(color: const Color(0xFFFFCCD5), width: 0.8),
                                ),
                                child: const Icon(Icons.local_activity_rounded, color: AppColors.primary, size: 16),
                              ),
                            ),
                          ),
                          Column(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: List.generate(
                              7,
                              (i) => Container(
                                width: 1.5,
                                height: 4,
                                color: const Color(0xFFFFCCD5),
                              ),
                            ),
                          ),
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.fromLTRB(10, 8, 6, 8),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Text(
                                    code,
                                    style: const TextStyle(
                                      fontSize: 12.5,
                                      fontWeight: FontWeight.w900,
                                      color: Color(0xFFE11D48),
                                      letterSpacing: 0.3,
                                    ),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    name,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontSize: 10.5,
                                      color: Color(0xFF334155),
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: InkWell(
                              onTap: isSaved
                                  ? null
                                  : () async {
                                      HapticFeedback.mediumImpact();
                                      final ok = await couponProv.claimCoupon(id);
                                      if (ok && context.mounted) {
                                        AppToast.showVoucherSaved(
                                          context,
                                          message: 'Đã lưu mã $code vào ví voucher của bạn!',
                                        );
                                      }
                                    },
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                                decoration: BoxDecoration(
                                  gradient: isSaved ? null : AppGradients.primary,
                                  color: isSaved ? const Color(0xFFE2E8F0) : null,
                                  borderRadius: BorderRadius.circular(14),
                                ),
                                child: Text(
                                  isSaved ? 'Đã lưu' : 'Lưu',
                                  style: TextStyle(
                                    color: isSaved ? const Color(0xFF64748B) : Colors.white,
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// 4. FLASH SALE SECTION (Timer độc lập, 60fps)
// ══════════════════════════════════════════════════════════════════════════
class _HomeFlashSaleSection extends StatefulWidget {
  final List<dynamic> flashSaleProducts;
  final bool isLoading;

  const _HomeFlashSaleSection({
    required this.flashSaleProducts,
    required this.isLoading,
  });

  @override
  State<_HomeFlashSaleSection> createState() => _HomeFlashSaleSectionState();
}

class _HomeFlashSaleSectionState extends State<_HomeFlashSaleSection> {
  Timer? _timer;
  Duration _remaining = Duration.zero;
  String? _lastEndStr;

  @override
  void initState() {
    super.initState();
    _initTimer();
  }

  @override
  void didUpdateWidget(covariant _HomeFlashSaleSection oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.flashSaleProducts != oldWidget.flashSaleProducts) {
      _initTimer();
    }
  }

  void _initTimer() {
    if (widget.flashSaleProducts.isEmpty) return;
    final first = widget.flashSaleProducts.first;
    final endStr = (first['ends_at'] ?? first['end_time'] ?? first['end_date'])?.toString();
    if (endStr == null || endStr.isEmpty) return;
    if (_lastEndStr == endStr && _timer != null) return;

    _lastEndStr = endStr;
    final endTime = DateTime.tryParse(endStr);
    if (endTime == null) return;

    _timer?.cancel();
    void tick() {
      final diff = endTime.difference(DateTime.now());
      if (diff.isNegative) {
        _remaining = Duration.zero;
        _timer?.cancel();
      } else {
        _remaining = diff;
      }
      if (mounted) setState(() {});
    }

    tick();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => tick());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.flashSaleProducts.isEmpty) return const SizedBox.shrink();

    final hours = _remaining.inHours.toString().padLeft(2, '0');
    final minutes = (_remaining.inMinutes % 60).toString().padLeft(2, '0');
    final seconds = (_remaining.inSeconds % 60).toString().padLeft(2, '0');

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 0, 14, 8),
      padding: const EdgeInsets.symmetric(vertical: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
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
        children: [
          // Header
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(4.5),
                  decoration: const BoxDecoration(
                    gradient: AppGradients.fireSale,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.local_fire_department_rounded, color: Colors.white, size: 16),
                ),
                const SizedBox(width: 6),
                const Text(
                  'FLASH SALE',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    color: Color(0xFFFF1744),
                    letterSpacing: -0.3,
                  ),
                ),
                const SizedBox(width: 8),
                _buildBox(hours),
                const Text(' : ', style: TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
                _buildBox(minutes),
                const Text(' : ', style: TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
                _buildBox(seconds),
                const Spacer(),
                InkWell(
                  onTap: () => context.push('/flash-sale'),
                  child: const Row(
                    children: [
                      Text('Xem tất cả', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF64748B))),
                      Icon(Icons.chevron_right_rounded, size: 16, color: Color(0xFF64748B)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),

          // Horizontal Products
          SizedBox(
            height: 174,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 10),
              itemCount: widget.flashSaleProducts.length,
              itemBuilder: (context, index) {
                final item = widget.flashSaleProducts[index];
                final product = item['product'] ?? item;
                final price = item['flash_sale_price'] ?? item['sale_price'] ?? item['flash_price'] ?? product['min_price'] ?? 0;
                final origPrice = item['original_price'] ?? product['original_price'] ?? product['min_price'] ?? 0;
                final imageUrl = AppConfig.productImageUrl(product);

                final num numPrice = FormatUtils.parseNum(price);
                final num numOrig = FormatUtils.parseNum(origPrice);
                int discount = FormatUtils.parseNum(item['discount_percent'] ?? 0).toInt();
                if (discount == 0 && numOrig > numPrice && numOrig > 0) {
                  discount = (((numOrig - numPrice) / numOrig) * 100).round();
                }

                final stock = FormatUtils.parseNum(item['total_stock'] ?? item['stock'] ?? item['total_quantity'] ?? 0).toInt();
                final sold = FormatUtils.parseNum(item['sold'] ?? item['sold_count'] ?? item['sold_quantity'] ?? 0).toInt();
                final total = stock > 0 ? (stock + sold) : (sold > 0 ? sold : 1);
                final double progress = total > 0 ? (sold / total).clamp(0.0, 1.0) : 0.0;

                String soldText;
                if (stock <= 0 && sold > 0) {
                  soldText = 'ĐÃ BÁN HẾT';
                } else if (sold == 0) {
                  soldText = 'VỪA MỞ BÁN';
                } else {
                  soldText = 'ĐÃ BÁN $sold';
                }

                return GestureDetector(
                  onTap: () {
                    HapticFeedback.lightImpact();
                    final Map<String, dynamic> extraData = product is Map<String, dynamic>
                        ? Map<String, dynamic>.from(product)
                        : (item is Map<String, dynamic> ? Map<String, dynamic>.from(item) : {});
                    extraData['flash_sale'] = item;
                    extraData['flash_sale_price'] = price;
                    extraData['flash_sale_item'] = item;
                    if (numOrig > 0) extraData['original_price'] = numOrig;
                    context.push('/product-detail', extra: extraData);
                  },
                  child: Container(
                    width: 140,
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFFF1F5F9)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.04),
                          blurRadius: 6,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Stack(
                          children: [
                            ClipRRect(
                              borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
                              child: Container(
                                height: 114,
                                width: double.infinity,
                                color: Colors.white,
                                padding: const EdgeInsets.all(4),
                                child: Center(
                                  child: NetworkImageWidget(
                                    imageUrl: imageUrl,
                                    fit: BoxFit.contain,
                                    customMemCacheWidth: 300,
                                    errorWidget: const Center(
                                      child: Icon(Icons.sports_tennis_rounded, color: Color(0xFFCBD5E1), size: 26),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                            if (discount > 0)
                              Positioned(
                                top: 5,
                                left: 5,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                                  decoration: BoxDecoration(
                                    gradient: AppGradients.fireSale,
                                    borderRadius: BorderRadius.circular(5),
                                  ),
                                  child: Text(
                                    '-$discount%',
                                    style: const TextStyle(color: Colors.white, fontSize: 9.5, fontWeight: FontWeight.w900),
                                  ),
                                ),
                              ),
                          ],
                        ),
                        Padding(
                          padding: const EdgeInsets.fromLTRB(8, 4, 8, 6),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                FormatUtils.formatPrice(numPrice),
                                style: const TextStyle(
                                  color: Color(0xFFFF1744),
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Stack(
                                children: [
                                  Container(
                                    height: 12,
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFFFE4E6),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                  ),
                                  FractionallySizedBox(
                                    widthFactor: progress > 0 ? progress : 0.05,
                                    child: Container(
                                      height: 12,
                                      decoration: BoxDecoration(
                                        gradient: AppGradients.fireSale,
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                    ),
                                  ),
                                  Positioned.fill(
                                    child: Center(
                                      child: Text(
                                        soldText,
                                        style: TextStyle(
                                          color: progress > 0.45 ? Colors.white : const Color(0xFFC72859),
                                          fontSize: 7.5,
                                          fontWeight: FontWeight.w900,
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
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBox(String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
      decoration: BoxDecoration(
        color: const Color(0xFF0F172A),
        borderRadius: BorderRadius.circular(5),
      ),
      child: Text(
        text,
        style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w900),
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// 5. OCEAN MALL SECTION
// ══════════════════════════════════════════════════════════════════════════
class _HomeOceanMallSection extends StatelessWidget {
  const _HomeOceanMallSection();

  @override
  Widget build(BuildContext context) {
    final brands = [
      {
        'name': 'YONEX',
        'sub': 'Nhật Bản',
        'promo': 'Ưu đãi -40%',
        'color': const Color(0xFF0038A8),
        'bg': const Color(0xFFEFF6FF),
        'icon': Icons.sports_tennis_rounded,
      },
      {
        'name': 'VICTOR',
        'sub': 'Đài Loan',
        'promo': 'Voucher 100K',
        'color': const Color(0xFF0A3981),
        'bg': const Color(0xFFF0FDF4),
        'icon': Icons.sports_baseball_rounded,
      },
      {
        'name': 'LI-NING',
        'sub': 'Chính hãng',
        'promo': 'Giảm sâu 50%',
        'color': const Color(0xFFDC2626),
        'bg': const Color(0xFFFEF2F2),
        'icon': Icons.flash_on_rounded,
      },
      {
        'name': 'MIZUNO',
        'sub': 'Nhật Bản',
        'promo': 'Mới về 2026',
        'color': const Color(0xFF0F172A),
        'bg': const Color(0xFFF8FAFC),
        'icon': Icons.shield_rounded,
      },
      {
        'name': 'BABOLAT',
        'sub': 'Pháp',
        'promo': 'Freeship Extra',
        'color': const Color(0xFF1D4ED8),
        'bg': const Color(0xFFEEF2FF),
        'icon': Icons.sports_tennis_rounded,
      },
      {
        'name': 'WILSON',
        'sub': 'Hoa Kỳ',
        'promo': 'Độc quyền Mall',
        'color': const Color(0xFFB91C1C),
        'bg': const Color(0xFFFFF1F2),
        'icon': Icons.verified_rounded,
      },
    ];

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 0, 14, 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
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
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFFD90429), Color(0xFFEF233C)],
                  ),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.verified_rounded, color: Colors.white, size: 11),
                    SizedBox(width: 3),
                    Text(
                      'MALL',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 7),
              const Expanded(
                child: Text(
                  'Gian Hàng Chính Hãng',
                  style: TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0F172A),
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              GestureDetector(
                onTap: () {
                  HapticFeedback.lightImpact();
                  context.push('/product-list', extra: {'categoryName': 'Ocean Mall Chính Hãng'});
                },
                behavior: HitTestBehavior.opaque,
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'Xem tất cả',
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                    Icon(Icons.chevron_right_rounded, size: 15, color: AppColors.primary),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 82,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              clipBehavior: Clip.none,
              itemCount: brands.length,
              itemBuilder: (context, index) {
                final b = brands[index];
                return GestureDetector(
                  onTap: () {
                    HapticFeedback.lightImpact();
                    context.push(
                      '/product-list',
                      extra: {
                        'searchQuery': b['name'],
                        'categoryName': '${b['name']} Official',
                      },
                    );
                  },
                  child: Container(
                    width: 138,
                    margin: const EdgeInsets.only(right: 8),
                    decoration: BoxDecoration(
                      color: b['bg'] as Color,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: (b['color'] as Color).withValues(alpha: 0.15),
                        width: 1,
                      ),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(10, 10, 10, 8),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              Icon(b['icon'] as IconData, size: 16, color: b['color'] as Color),
                              const SizedBox(width: 5),
                              Expanded(
                                child: Text(
                                  b['name'] as String,
                                  style: TextStyle(
                                    fontSize: 13.5,
                                    fontWeight: FontWeight.w900,
                                    color: b['color'] as Color,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ),
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  b['sub'] as String,
                                  style: const TextStyle(fontSize: 10, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: Text(
                                  b['promo'] as String,
                                  style: TextStyle(fontSize: 8.5, fontWeight: FontWeight.w800, color: b['color'] as Color),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// 6. RECOMMENDATION HEADER & TABS
// ══════════════════════════════════════════════════════════════════════════
class _HomeRecommendationHeader extends StatelessWidget {
  final ValueNotifier<String> tabNotifier;

  const _HomeRecommendationHeader({required this.tabNotifier});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(14, 0, 14, 8),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
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
        children: [
          const Row(
            children: [
              Icon(Icons.explore_rounded, color: AppColors.primary, size: 20),
              SizedBox(width: 6),
              Text(
                'GỢI Ý HÔM NAY',
                style: TextStyle(
                  fontSize: 15.5,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF0F172A),
                  letterSpacing: 0.2,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            physics: const BouncingScrollPhysics(),
            child: ValueListenableBuilder<String>(
              valueListenable: tabNotifier,
              builder: (context, activeTab, _) {
                return Row(
                  children: [
                    _tab('all', 'Tất cả', activeTab),
                    _tab('hot', 'Bán chạy', activeTab),
                    _tab('sale', 'Giảm sâu', activeTab),
                    _tab('new', 'Hàng mới về', activeTab),
                    _tab('top_rated', 'Đánh giá cao', activeTab),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _tab(String key, String label, String current) {
    final isSelected = current == key;
    return GestureDetector(
      onTap: () => tabNotifier.value = key,
      child: Container(
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7.5),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFFFF0F5) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: isSelected ? AppColors.primary : const Color(0xFFE2E8F0),
            width: isSelected ? 1.5 : 1.0,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12.5,
            fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
            color: isSelected ? AppColors.primary : const Color(0xFF475569),
          ),
        ),
      ),
    );
  }
}
