import 'dart:async';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/cart_provider.dart';
import '../providers/home_provider.dart';
import '../utils/format_utils.dart';
import '../widgets/network_image_widget.dart';
import '../widgets/shimmer_loading.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/product_card.dart';

/// Màn hình Trang Chủ chuẩn Sàn Thương Mại Điện Tử Quốc Tế (Shopee / Lazada / TikTok Shop tier).
/// Thiết kế hiện đại, bố cục khoa học, tỷ lệ chuyển đổi cao, tối ưu 60-120 FPS.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  final ScrollController _scrollController = ScrollController();
  final PageController _bannerController = PageController();
  int _currentBannerIndex = 0;
  Timer? _bannerTimer;
  String _selectedTab = 'all'; // 'all', 'hot', 'sale', 'new'

  final List<Map<String, dynamic>> _heroBanners = [
    {
      'tag': '🔥 SIÊU SALE MÙA GIẢI',
      'title': 'Babolat & Wilson\nGIẢM TỚI 35%',
      'subtitle': 'Tặng kèm cuốn cán & bao vợt cao cấp',
      'gradient': const [Color(0xFFE63B6F), Color(0xFF9333EA)],
      'action': '/flash-sale',
    },
    {
      'tag': '👟 BỘ SƯU TẬP 2026',
      'title': 'Giày Tennis & Cầu Lông\nCHÍNH HÃNG 100%',
      'subtitle': 'Đệm khí êm ái, bám sân cực đỉnh',
      'gradient': const [Color(0xFF2563EB), Color(0xFF06B6D4)],
      'action': '/shop',
    },
    {
      'tag': '🎟️ ĐẶC QUYỀN THÀNH VIÊN',
      'title': 'Săn Voucher 500K\nFREESHIP TOÀN QUỐC',
      'subtitle': 'Áp dụng cho mọi đơn hàng thể thao',
      'gradient': const [Color(0xFFF59E0B), Color(0xFFEA580C)],
      'action': '/coupon',
    },
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<HomeProvider>();
      if (provider.products.isEmpty) provider.fetchProducts();
      if (provider.categories.isEmpty) provider.fetchCategories();
      provider.fetchFlashSale();
    });

    _scrollController.addListener(_onScroll);
    _startBannerAutoSlide();
  }

  void _startBannerAutoSlide() {
    _bannerTimer?.cancel();
    _bannerTimer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (!_bannerController.hasClients) return;
      int next = _currentBannerIndex + 1;
      if (next >= _heroBanners.length) next = 0;
      _bannerController.animateToPage(
        next,
        duration: const Duration(milliseconds: 400),
        curve: Curves.easeInOut,
      );
    });
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 300) {
      context.read<HomeProvider>().loadMoreProducts();
    }
  }

  @override
  void dispose() {
    _bannerTimer?.cancel();
    _bannerController.dispose();
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final provider = context.watch<HomeProvider>();

    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9), // Nền trung tính Shopee
      body: SafeArea(
        child: RefreshIndicator(
          color: AppColors.primary,
          onRefresh: () async {
            await Future.wait([
              context.read<HomeProvider>().fetchProducts(refresh: true),
              context.read<HomeProvider>().fetchCategories(),
              context.read<HomeProvider>().fetchFlashSale(),
            ]);
          },
          child: CustomScrollView(
            controller: _scrollController,
            cacheExtent: 800,
            physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
            slivers: [
              // 1. Header & Search Bar tích hợp
              SliverToBoxAdapter(child: _buildTopHeader(context)),

              // 2. Banner Slider chuyển động mượt mà
              SliverToBoxAdapter(child: _buildBannerSlider(context)),

              // 3. Danh mục 8 icon tiện ích (Shopee Grid Style)
              SliverToBoxAdapter(child: _buildCategoryIconGrid(context, provider)),

              // 4. Flash Sale Box với đếm ngược thời gian
              if (provider.flashSaleProducts.isNotEmpty)
                SliverToBoxAdapter(child: _buildFlashSaleLiveBox(context, provider)),

              // 5. Cam kết Ocean Mall (Chính hãng & Đổi trả)
              SliverToBoxAdapter(child: _buildMallAssuranceBanner(context)),

              // 6. Header "Gợi ý hôm nay" kèm Tab lọc
              SliverToBoxAdapter(child: _buildRecommendationHeader(context)),

              // 7. Grid Sản phẩm 2 cột chuẩn 60 FPS
              _buildProductSliverGrid(context, provider),

              // 8. Load more spinner
              if (provider.isFetchingMore)
                const SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.symmetric(vertical: 24),
                    child: Center(
                      child: CircularProgressIndicator(
                        strokeWidth: 2.5,
                        color: AppColors.primary,
                      ),
                    ),
                  ),
                ),

              const SliverToBoxAdapter(child: SizedBox(height: 32)),
            ],
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () => context.push('/chat'),
        backgroundColor: AppColors.primary,
        elevation: 4,
        child: const Icon(Icons.chat_bubble_outline_rounded, color: Colors.white),
      ),
    );
  }

  // ── 1. Top Header & Search Bar ──
  Widget _buildTopHeader(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      child: Column(
        children: [
          Row(
            children: [
              // Logo Brand
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  gradient: AppGradients.primary,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.sports_tennis_rounded, color: Colors.white, size: 20),
              ),
              const SizedBox(width: 10),
              const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        'OCEAN SPORT',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w900,
                          color: AppColors.textPrimary,
                          letterSpacing: -0.2,
                        ),
                      ),
                      SizedBox(width: 4),
                      Icon(Icons.verified_rounded, color: AppColors.primary, size: 14),
                    ],
                  ),
                  Text(
                    'Siêu thị thể thao chính hãng',
                    style: TextStyle(fontSize: 11, color: AppColors.textSecondary, fontWeight: FontWeight.w500),
                  ),
                ],
              ),
              const Spacer(),
              // Nút Thông báo
              IconButton(
                icon: const Icon(Icons.notifications_none_rounded, color: AppColors.textPrimary, size: 24),
                onPressed: () => context.push('/notification'),
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
              ),
              const SizedBox(width: 14),
              // Nút Giỏ hàng có Badge
              Consumer<CartProvider>(
                builder: (context, cart, _) => Stack(
                  clipBehavior: Clip.none,
                  children: [
                    IconButton(
                      icon: const Icon(Icons.shopping_bag_outlined, color: AppColors.textPrimary, size: 24),
                      onPressed: () => context.push('/cart'),
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(),
                    ),
                    if (cart.itemCount > 0)
                      Positioned(
                        top: -4,
                        right: -4,
                        child: Container(
                          padding: const EdgeInsets.all(4),
                          decoration: const BoxDecoration(
                            color: AppColors.primary,
                            shape: BoxShape.circle,
                          ),
                          constraints: const BoxConstraints(minWidth: 17, minHeight: 17),
                          child: Text(
                            cart.itemCount > 99 ? '99+' : cart.itemCount.toString(),
                            style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w900),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          // Search Bar
          GestureDetector(
            onTap: () => context.push('/search'),
            child: Container(
              height: 42,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(21),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.search_rounded, color: AppColors.textMuted, size: 20),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Tìm kiếm vợt tennis, cầu lông, giày, áo đấu...',
                      style: TextStyle(color: AppColors.textMuted, fontSize: 13),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Icon(Icons.camera_alt_outlined, color: AppColors.primary, size: 18),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── 2. Hero Banner Slider ──
  Widget _buildBannerSlider(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: Column(
        children: [
          SizedBox(
            height: 145,
            child: PageView.builder(
              controller: _bannerController,
              itemCount: _heroBanners.length,
              onPageChanged: (i) => setState(() => _currentBannerIndex = i),
              itemBuilder: (context, index) {
                final b = _heroBanners[index];
                return GestureDetector(
                  onTap: () => context.push(b['action']),
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: b['gradient'],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(
                          color: (b['gradient'][0] as Color).withValues(alpha: 0.3),
                          blurRadius: 10,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.25),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            b['tag'],
                            style: const TextStyle(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w900),
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          b['title'],
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 17,
                            fontWeight: FontWeight.w900,
                            height: 1.15,
                            letterSpacing: -0.2,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          b['subtitle'],
                          style: const TextStyle(color: Colors.white70, fontSize: 11.5, fontWeight: FontWeight.w500),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 8),
          // Dots Indicator
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(
              _heroBanners.length,
              (i) => AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                width: _currentBannerIndex == i ? 16 : 6,
                height: 5,
                decoration: BoxDecoration(
                  color: _currentBannerIndex == i ? AppColors.primary : const Color(0xFFCBD5E1),
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── 3. Category Quick Icon Grid (Shopee 4x2 style) ──
  Widget _buildCategoryIconGrid(BuildContext context, HomeProvider provider) {
    final quickItems = [
      {
        'label': 'Flash Sale',
        'icon': Icons.flash_on_rounded,
        'color': const Color(0xFFEF4444),
        'action': () => context.push('/flash-sale'),
      },
      {
        'label': 'Voucher 50K',
        'icon': Icons.confirmation_number_outlined,
        'color': const Color(0xFF3B82F6),
        'action': () => context.push('/coupon'),
      },
      {
        'label': 'Đặt Sân Bãi',
        'icon': Icons.sports_tennis_rounded,
        'color': const Color(0xFF10B981),
        'action': () => context.go('/court'),
      },
      {
        'label': 'Đổi Điểm',
        'icon': Icons.stars_rounded,
        'color': const Color(0xFFF59E0B),
        'action': () => context.push('/loyalty'),
      },
      {
        'label': 'Hàng Mới Về',
        'icon': Icons.fiber_new_rounded,
        'color': const Color(0xFF8B5CF6),
        'action': () => context.go('/shop'),
      },
      {
        'label': 'Yêu Thích',
        'icon': Icons.favorite_rounded,
        'color': const Color(0xFFEC4899),
        'action': () => context.push('/favorite'),
      },
      {
        'label': 'Đơn Mua',
        'icon': Icons.receipt_long_rounded,
        'color': const Color(0xFF06B6D4),
        'action': () => context.push('/orders'),
      },
      {
        'label': 'Tất Cả Shop',
        'icon': Icons.grid_view_rounded,
        'color': const Color(0xFF64748B),
        'action': () => context.go('/shop'),
      },
    ];

    return Container(
      margin: const EdgeInsets.only(top: 8),
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(14, 16, 14, 16),
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 4,
          mainAxisSpacing: 14,
          crossAxisSpacing: 10,
          childAspectRatio: 0.85,
        ),
        itemCount: quickItems.length,
        itemBuilder: (context, index) {
          final item = quickItems[index];
          final color = item['color'] as Color;
          final icon = item['icon'] as IconData;
          final label = item['label'] as String;
          final callback = item['action'] as VoidCallback;

          return GestureDetector(
            onTap: callback,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Center(
                    child: Icon(icon, color: color, size: 24),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  label,
                  textAlign: TextAlign.center,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textPrimary,
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  // ── 4. Flash Sale Live Box ──
  Widget _buildFlashSaleLiveBox(BuildContext context, HomeProvider provider) {
    return Container(
      margin: const EdgeInsets.only(top: 8),
      color: Colors.white,
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header: Flash sale + Countdown timer
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                const Icon(Icons.flash_on_rounded, color: AppColors.primary, size: 22),
                const SizedBox(width: 4),
                const Text(
                  'FLASH SALE',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    color: AppColors.primary,
                    letterSpacing: -0.2,
                  ),
                ),
                const SizedBox(width: 8),
                _buildCountdownBox('02'),
                const Text(' : ', style: TextStyle(fontWeight: FontWeight.w900, color: AppColors.textPrimary)),
                _buildCountdownBox('18'),
                const Text(' : ', style: TextStyle(fontWeight: FontWeight.w900, color: AppColors.textPrimary)),
                _buildCountdownBox('45'),
                const Spacer(),
                InkWell(
                  onTap: () => context.push('/flash-sale'),
                  child: const Row(
                    children: [
                      Text('Xem tất cả', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.textSecondary)),
                      Icon(Icons.arrow_forward_ios_rounded, size: 12, color: AppColors.textSecondary),
                    ],
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 12),

          // Horizontal Product Cards
          SizedBox(
            height: 210,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              itemCount: provider.flashSaleProducts.length,
              itemBuilder: (context, index) {
                final item = provider.flashSaleProducts[index];
                final product = item['product'] ?? item;
                final price = item['flash_sale_price'] ?? product['min_price'] ?? 0;
                final origPrice = product['min_price'] ?? 0;
                final imageUrl = AppConfig.productImageUrl(product);

                final num numPrice = FormatUtils.parseNum(price);
                final num numOrig = FormatUtils.parseNum(origPrice);
                int discount = 0;
                if (numOrig > numPrice && numOrig > 0) {
                  discount = (((numOrig - numPrice) / numOrig) * 100).round();
                }

                return GestureDetector(
                  onTap: () => context.push('/product-detail', extra: product),
                  child: Container(
                    width: 140,
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFF1F5F9)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.03),
                          blurRadius: 6,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Thumbnail with discount badge
                        Stack(
                          children: [
                            ClipRRect(
                              borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
                              child: Container(
                                height: 120,
                                width: double.infinity,
                                color: const Color(0xFFF8FAFC),
                                child: NetworkImageWidget(
                                  imageUrl: imageUrl,
                                  fit: BoxFit.cover,
                                  customMemCacheWidth: 300,
                                ),
                              ),
                            ),
                            if (discount > 0)
                              Positioned(
                                top: 6,
                                left: 6,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: AppColors.primary,
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    '-$discount%',
                                    style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900),
                                  ),
                                ),
                              ),
                          ],
                        ),

                        Padding(
                          padding: const EdgeInsets.all(8),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                FormatUtils.formatPrice(numPrice),
                                style: const TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w900,
                                  color: AppColors.primary,
                                ),
                              ),
                              const SizedBox(height: 6),
                              // Sold progress bar
                              Container(
                                height: 14,
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFFCDD2),
                                  borderRadius: BorderRadius.circular(7),
                                ),
                                child: Stack(
                                  children: [
                                    FractionallySizedBox(
                                      widthFactor: 0.75,
                                      child: Container(
                                        decoration: BoxDecoration(
                                          gradient: AppGradients.primary,
                                          borderRadius: BorderRadius.circular(7),
                                        ),
                                      ),
                                    ),
                                    const Center(
                                      child: Text(
                                        'ĐÃ BÁN 75%',
                                        style: TextStyle(
                                          color: Colors.white,
                                          fontSize: 8.5,
                                          fontWeight: FontWeight.w900,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
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

  Widget _buildCountdownBox(String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        text,
        style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w900),
      ),
    );
  }

  // ── 5. Mall Assurance Banner (Shopee Mall style) ──
  Widget _buildMallAssuranceBanner(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 8),
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: const Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          _MallPill(icon: Icons.verified_user_outlined, label: '100% Chính hãng'),
          _MallPill(icon: Icons.local_shipping_outlined, label: 'Miễn phí vận chuyển'),
          _MallPill(icon: Icons.published_with_changes_rounded, label: 'Đổi trả 15 ngày'),
        ],
      ),
    );
  }

  // ── 6. Recommendation Header with Tabs ──
  Widget _buildRecommendationHeader(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 8),
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.recommend_rounded, color: AppColors.primary, size: 20),
              SizedBox(width: 6),
              Text(
                'GỢI Ý HÔM NAY',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w900,
                  color: AppColors.textPrimary,
                  letterSpacing: 0.2,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          // Filter Tabs
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                _buildFilterTab('all', 'Tất cả'),
                _buildFilterTab('hot', '🔥 Bán chạy'),
                _buildFilterTab('sale', '⚡ Giảm giá sâu'),
                _buildFilterTab('new', '✨ Hàng mới về'),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterTab(String key, String label) {
    final isSelected = _selectedTab == key;
    return GestureDetector(
      onTap: () => setState(() => _selectedTab = key),
      child: Container(
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFFFF1F2) : const Color(0xFFF1F5F9),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? AppColors.primary : const Color(0xFFE2E8F0),
            width: isSelected ? 1.4 : 1.0,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12.5,
            fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
            color: isSelected ? AppColors.primary : AppColors.textSecondary,
          ),
        ),
      ),
    );
  }

  // ── 7. Products 2-Column Grid ──
  Widget _buildProductSliverGrid(BuildContext context, HomeProvider provider) {
    if (provider.isInitialLoading && provider.products.isEmpty) {
      return const SliverShimmerLoading(
        padding: EdgeInsets.all(12),
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        childAspectRatio: 0.65,
      );
    }

    if (provider.products.isEmpty) {
      return SliverToBoxAdapter(
        child: AppEmptyState(
          icon: Icons.inventory_2_outlined,
          title: 'Chưa có sản phẩm',
          message: 'Không tìm thấy sản phẩm phù hợp. Vui lòng quay lại sau.',
          buttonText: 'Tải lại',
          onAction: () => context.read<HomeProvider>().fetchProducts(refresh: true),
        ),
      );
    }

    // Filter products based on selected tab
    var displayProducts = provider.products;
    if (_selectedTab == 'hot') {
      displayProducts = provider.products.where((p) => p['is_featured'] == 1 || p['is_hot'] == 1).toList();
      if (displayProducts.isEmpty) displayProducts = provider.products;
    }

    return SliverPadding(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 16),
      sliver: SliverGrid(
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          crossAxisSpacing: 10,
          mainAxisSpacing: 10,
          childAspectRatio: 0.65,
        ),
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            return ProductCard(
              product: displayProducts[index],
            );
          },
          childCount: displayProducts.length,
          addRepaintBoundaries: true,
          addAutomaticKeepAlives: false,
        ),
      ),
    );
  }
}

class _MallPill extends StatelessWidget {
  final IconData icon;
  final String label;
  const _MallPill({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 14, color: AppColors.primary),
        const SizedBox(width: 4),
        Text(
          label,
          style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
        ),
      ],
    );
  }
}
