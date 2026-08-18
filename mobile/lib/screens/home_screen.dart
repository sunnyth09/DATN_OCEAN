import 'dart:async';
import 'package:flutter/material.dart';
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
import '../widgets/product_card.dart';

/// Màn hình Trang Chủ chuẩn Sàn Thương Mại Điện Tử Quốc Tế (Shopee Mall / TikTok Shop / Taobao tier).
/// Thiết kế Single-Row Search Header tối ưu 100% diện tích màn hình, tỷ lệ chuyển đổi cao, tối ưu 60-120 FPS.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  final ScrollController _scrollController = ScrollController();
  static const int _kInitialBannerPage = 1200;
  late final PageController _bannerController;
  int _currentBannerIndex = 0;
  Timer? _bannerTimer;
  Timer? _flashSaleTimer;
  Timer? _tickerTimer;
  Duration _flashSaleRemaining = Duration.zero;
  String? _lastFlashSaleEndStr;
  String _selectedTab = 'all'; // 'all', 'hot', 'sale', 'new', 'top_rated'

  int _tickerIndex = 0;
  final List<String> _trendingKeywords = [
    'Vợt Yonex Astrox 100ZZ',
    'Giày Babolat Jet Mach 4',
    'Vợt Victor Thruster K',
    'Săn Voucher Freeship 0đ',
    'Giày Lining Sonic Pro',
    'Áo Thi Đấu Thể Thao VIP',
    'Balo Vợt Tennis Chuyên Dụng',
  ];

  bool _showBackToTop = false;

  final List<Map<String, dynamic>> _heroBanners = [
    {
      'tag': 'SIÊU SALE 2026',
      'title': 'Vợt Cầu Lông & Tennis',
      'highlight': 'GIẢM TỚI 35%',
      'cta': 'Khám phá ngay',
      'image': '/storage/products/72b710a6-2931-4dd7-a229-887e527bcf80.webp',
      'gradient': const [Color(0xFFE63B6F), Color(0xFFFF6584)],
      'action': '/flash-sale',
    },
    {
      'tag': 'BỘ SƯU TẬP MỚI',
      'title': 'Giày Thi Đấu Chính Hãng',
      'highlight': 'ĐỆM KHÍ CAO CẤP',
      'cta': 'Xem sản phẩm',
      'image': '/storage/products/f5801e74-1129-4d64-a1c3-00c68f34e191.webp',
      'gradient': const [Color(0xFF1E40AF), Color(0xFF0284C7)],
      'action': '/shop',
    },
    {
      'tag': 'ĐẶC QUYỀN THÀNH VIÊN',
      'title': 'Áo Đấu & Phụ Kiện',
      'highlight': 'VOUCHER 500K',
      'cta': 'Lấy mã ngay',
      'image': '/storage/products/026f9252-4579-4f19-a72a-740bfeb619ee.webp',
      'gradient': const [Color(0xFFD97706), Color(0xFFEA580C)],
      'action': '/coupon',
    },
  ];

  @override
  void initState() {
    super.initState();
    _bannerController = PageController(initialPage: _kInitialBannerPage);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CartProvider>().fetchCart(silent: true);
      final provider = context.read<HomeProvider>();
      if (provider.products.isEmpty) provider.fetchProducts();
      if (provider.categories.isEmpty) provider.fetchCategories();
      provider.fetchFlashSale();
      provider.fetchHomeCollections();
      provider.fetchVouchers();
    });

    _scrollController.addListener(_onScroll);
    _startBannerAutoSlide();
    _startTicker();
  }

  void _startTicker() {
    _tickerTimer?.cancel();
    _tickerTimer = Timer.periodic(const Duration(seconds: 3), (_) {
      if (mounted) {
        setState(() {
          _tickerIndex = (_tickerIndex + 1) % _trendingKeywords.length;
        });
      }
    });
  }

  void _startBannerAutoSlide() {
    _bannerTimer?.cancel();
    _bannerTimer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (!_bannerController.hasClients) return;
      _bannerController.nextPage(
        duration: const Duration(milliseconds: 550),
        curve: Curves.easeInOutCubic,
      );
    });
  }

  void _onScroll() {
    final offset = _scrollController.position.pixels;
    if (offset >= _scrollController.position.maxScrollExtent - 300) {
      context.read<HomeProvider>().loadMoreProducts();
    }

    if (offset > 500 && !_showBackToTop) {
      setState(() => _showBackToTop = true);
    } else if (offset <= 500 && _showBackToTop) {
      setState(() => _showBackToTop = false);
    }
  }

  void _scrollToTop() {
    _scrollController.animateTo(
      0,
      duration: const Duration(milliseconds: 500),
      curve: Curves.easeOutCubic,
    );
  }

  void _checkAndStartFlashSaleTimer(List<dynamic> flashSaleProducts) {
    if (flashSaleProducts.isEmpty) return;
    final first = flashSaleProducts.first;
    final endStr = (first['ends_at'] ?? first['end_time'] ?? first['end_date'])?.toString();
    if (endStr == null || endStr.isEmpty) return;
    if (_lastFlashSaleEndStr == endStr && _flashSaleTimer != null) return;

    _lastFlashSaleEndStr = endStr;
    final endTime = DateTime.tryParse(endStr);
    if (endTime == null) return;

    _flashSaleTimer?.cancel();
    void updateRemaining() {
      final now = DateTime.now();
      final diff = endTime.difference(now);
      if (diff.isNegative) {
        _flashSaleRemaining = Duration.zero;
        _flashSaleTimer?.cancel();
      } else {
        _flashSaleRemaining = diff;
      }
      if (mounted) setState(() {});
    }

    updateRemaining();
    _flashSaleTimer = Timer.periodic(const Duration(seconds: 1), (_) => updateRemaining());
  }

  @override
  void dispose() {
    _bannerTimer?.cancel();
    _flashSaleTimer?.cancel();
    _tickerTimer?.cancel();
    _bannerController.dispose();
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final provider = context.watch<HomeProvider>();

    if (provider.flashSaleProducts.isNotEmpty) {
      _checkAndStartFlashSaleTimer(provider.flashSaleProducts);
    }

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
              context.read<HomeProvider>().fetchVouchers(),
            ]);
          },
          child: CustomScrollView(
            controller: _scrollController,
            cacheExtent: 900,
            physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
            slivers: [
              // 1. Single-Row Integrated Search Header (Shopee / Lazada Tier)
              SliverToBoxAdapter(child: _buildTopHeader(context)),

              // 2. Banner Slider chuyển động mượt mà
              SliverToBoxAdapter(child: _buildBannerSlider(context)),

              // 3. Dải Voucher 1 chạm tương tác (Shopee Ticket Strip)
              SliverToBoxAdapter(child: _buildVoucherTicketStrip(context, provider)),

              // 4. Lưới 10 icon dịch vụ & tiện ích nhanh (2x5 Squircle Grid)
              SliverToBoxAdapter(child: _buildCategoryIconGrid(context, provider)),

              // 5. Flash Sale Box với đếm ngược thời gian bốc lửa
              if (provider.flashSaleProducts.isNotEmpty)
                SliverToBoxAdapter(child: _buildFlashSaleSection(context, provider)),

              // 6. Ocean Mall - Gian hàng uỷ quyền chính hãng
              SliverToBoxAdapter(child: _buildOceanMallSection(context)),

              // 7. Header "Gợi ý hôm nay" kèm Tab lọc
              SliverToBoxAdapter(child: _buildRecommendationHeader(context)),

              // 8. Grid Sản phẩm 2 cột chuẩn 60-120 FPS
              _buildProductSliverGrid(context, provider),

              // 9. Load more spinner
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

              const SliverToBoxAdapter(child: SizedBox(height: 48)),
            ],
          ),
        ),
      ),
      floatingActionButton: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Nút Back to Top xuất hiện khi cuộn sâu
          AnimatedScale(
            scale: _showBackToTop ? 1.0 : 0.0,
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeOutBack,
            child: Container(
              margin: const EdgeInsets.only(bottom: 12),
              child: FloatingActionButton.small(
                heroTag: 'home_back_to_top',
                onPressed: _scrollToTop,
                backgroundColor: Colors.white,
                foregroundColor: const Color(0xFF0F172A),
                elevation: 4,
                child: const Icon(Icons.arrow_upward_rounded, size: 20),
              ),
            ),
          ),

          // Nút Chat Tư Vấn 24/7 với hiệu ứng đổ bóng tỏa
          FloatingActionButton(
            heroTag: 'home_chat_btn',
            onPressed: () => context.push('/chat'),
            backgroundColor: AppColors.primary,
            elevation: 6,
            child: Stack(
              alignment: Alignment.center,
              children: [
                Container(
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.primary.withValues(alpha: 0.45),
                        blurRadius: 14,
                        spreadRadius: 2,
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.chat_bubble_outline_rounded, color: Colors.white),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ── 1. Single-Row Integrated Search Header (Shopee / Lazada Tier) ──
  Widget _buildTopHeader(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
      child: Row(
        children: [
          // 1. Search Bar Ticker (Chiếm trọn bên trái)
          Expanded(
            child: GestureDetector(
              onTap: () => context.push('/search'),
              child: Container(
                height: 40,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.search_rounded, color: AppColors.primary, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: AnimatedSwitcher(
                        duration: const Duration(milliseconds: 350),
                        transitionBuilder: (child, animation) => SlideTransition(
                          position: Tween<Offset>(
                            begin: const Offset(0, 0.4),
                            end: Offset.zero,
                          ).animate(animation),
                          child: FadeTransition(opacity: animation, child: child),
                        ),
                        child: Text(
                          _trendingKeywords[_tickerIndex],
                          key: ValueKey<int>(_tickerIndex),
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    const Icon(Icons.camera_alt_outlined, color: AppColors.primary, size: 18),
                  ],
                ),
              ),
            ),
          ),

          const SizedBox(width: 12),

          // 2. Nút Thông Báo
          IconButton(
            icon: const Icon(Icons.notifications_none_rounded, color: Color(0xFF0F172A), size: 24),
            onPressed: () => context.push('/notification'),
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(),
          ),

          const SizedBox(width: 12),

          // 3. Nút Giỏ Hàng với Badge Động
          Consumer<CartProvider>(
            builder: (context, cart, _) => GestureDetector(
              onTap: () => context.push('/cart'),
              behavior: HitTestBehavior.opaque,
              child: Badge(
                isLabelVisible: cart.itemCount > 0,
                label: Text(
                  cart.itemCount > 99 ? '99+' : cart.itemCount.toString(),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 9.5,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                backgroundColor: AppColors.primary,
                offset: const Offset(4, -4),
                child: const Icon(
                  Icons.shopping_cart_outlined,
                  color: Color(0xFF0F172A),
                  size: 24,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── 2. Hero Banner Slider (Infinite Looping & Breathing Layout) ──
  Widget _buildBannerSlider(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
      child: Column(
        children: [
          SizedBox(
            height: 145,
            child: Listener(
              onPointerDown: (_) => _bannerTimer?.cancel(),
              onPointerUp: (_) => _startBannerAutoSlide(),
              child: PageView.builder(
                controller: _bannerController,
                physics: const BouncingScrollPhysics(),
                onPageChanged: (i) => setState(() => _currentBannerIndex = i % _heroBanners.length),
                itemBuilder: (context, index) {
                  final b = _heroBanners[index % _heroBanners.length];
                  return GestureDetector(
                    onTap: () => context.push(b['action']),
                    child: Container(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: b['gradient'],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: [
                          BoxShadow(
                            color: (b['gradient'][0] as Color).withValues(alpha: 0.28),
                            blurRadius: 12,
                            offset: const Offset(0, 5),
                          ),
                        ],
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(16),
                        child: Stack(
                          children: [
                            // Right Side: High-res Sport Product Image in Ambient Glow Backdrop
                            Positioned(
                              right: 4,
                              top: 4,
                              bottom: 4,
                              width: 130,
                              child: Stack(
                                alignment: Alignment.center,
                                children: [
                                  Container(
                                    width: 110,
                                    height: 110,
                                    decoration: BoxDecoration(
                                      shape: BoxShape.circle,
                                      color: Colors.white.withValues(alpha: 0.20),
                                      boxShadow: [
                                        BoxShadow(
                                          color: Colors.black.withValues(alpha: 0.08),
                                          blurRadius: 16,
                                          spreadRadius: 2,
                                        ),
                                      ],
                                    ),
                                  ),
                                  Transform.rotate(
                                    angle: -0.10,
                                    child: Container(
                                      padding: const EdgeInsets.all(6),
                                      child: NetworkImageWidget(
                                        imageUrl: AppConfig.imageUrl(b['image']),
                                        fit: BoxFit.contain,
                                        customMemCacheWidth: 350,
                                        errorWidget: Center(
                                          child: Icon(
                                            Icons.sports_tennis_rounded,
                                            size: 54,
                                            color: Colors.white.withValues(alpha: 0.5),
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),

                            // Left Content: Tag + Headline + Highlight + Action Button
                            Positioned(
                              left: 0,
                              top: 0,
                              bottom: 0,
                              right: 130,
                              child: Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2.5),
                                      decoration: BoxDecoration(
                                        color: Colors.white.withValues(alpha: 0.24),
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                      child: Text(
                                        b['tag'],
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontSize: 9.5,
                                          fontWeight: FontWeight.w800,
                                          letterSpacing: 0.3,
                                        ),
                                      ),
                                    ),
                                    Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          b['title'],
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(
                                            color: Colors.white,
                                            fontSize: 14.5,
                                            fontWeight: FontWeight.w800,
                                            letterSpacing: -0.2,
                                          ),
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          b['highlight'],
                                          style: const TextStyle(
                                            color: Color(0xFFFFE082),
                                            fontSize: 13.5,
                                            fontWeight: FontWeight.w900,
                                            letterSpacing: 0.2,
                                          ),
                                        ),
                                      ],
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: Colors.white,
                                        borderRadius: BorderRadius.circular(16),
                                        boxShadow: [
                                          BoxShadow(
                                            color: Colors.black.withValues(alpha: 0.08),
                                            blurRadius: 4,
                                            offset: const Offset(0, 2),
                                          ),
                                        ],
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Text(
                                            b['cta'],
                                            style: TextStyle(
                                              color: b['gradient'][0],
                                              fontSize: 10.5,
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                          const SizedBox(width: 4),
                                          Icon(
                                            Icons.arrow_forward_rounded,
                                            size: 11,
                                            color: b['gradient'][0],
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
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
                width: _currentBannerIndex == i ? 18 : 6,
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

  // ── 3. Dải Voucher 1 chạm tương tác (Shopee Ticket Strip) ──
  Widget _buildVoucherTicketStrip(BuildContext context, HomeProvider provider) {
    final couponProv = context.watch<CouponProvider>();
    final vouchers = provider.homeVouchers.isNotEmpty
        ? provider.homeVouchers.take(4).toList()
        : [
            {'id': 1, 'code': 'FREESHIP', 'name': 'Miễn Phí Vận Chuyển', 'discount_amount': 30000, 'min_order_value': 0},
            {'id': 2, 'code': 'OCEAN50K', 'name': 'Giảm 50.000đ', 'discount_amount': 50000, 'min_order_value': 300000},
            {'id': 3, 'code': 'VIP100K', 'name': 'Giảm 100.000đ', 'discount_amount': 100000, 'min_order_value': 1000000},
          ];

    return Container(
      margin: const EdgeInsets.only(top: 8),
      padding: const EdgeInsets.symmetric(vertical: 12),
      color: Colors.white,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                const Icon(Icons.confirmation_number_outlined, color: AppColors.primary, size: 18),
                const SizedBox(width: 6),
                const Text(
                  'KHO VOUCHER ĐỘC QUYỀN',
                  style: TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w900,
                    color: AppColors.textPrimary,
                    letterSpacing: 0.2,
                  ),
                ),
                const Spacer(),
                GestureDetector(
                  onTap: () => context.push('/coupon'),
                  child: const Row(
                    children: [
                      Text('Xem thêm', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary)),
                      Icon(Icons.chevron_right_rounded, size: 16, color: AppColors.primary),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 68,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              itemCount: vouchers.length,
              itemBuilder: (context, index) {
                final v = vouchers[index];
                final id = int.tryParse((v['id'] ?? 0).toString()) ?? 0;
                final isSaved = couponProv.isSaved(id);
                final code = v['code']?.toString() ?? 'VOUCHER';
                final name = v['name']?.toString() ?? v['description']?.toString() ?? 'Ưu đãi mua sắm';

                return Container(
                  width: 220,
                  margin: const EdgeInsets.symmetric(horizontal: 4),
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    gradient: AppGradients.ticketVoucher,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFFFFCDD2), width: 0.8),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 38,
                        height: 38,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Center(
                          child: Icon(Icons.local_activity_rounded, color: AppColors.primary, size: 20),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              code,
                              style: const TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w900,
                                color: Color(0xFFE63B6F),
                              ),
                            ),
                            Text(
                              name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 10,
                                color: Color(0xFF64748B),
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 6),
                      InkWell(
                        onTap: isSaved
                            ? null
                            : () async {
                                final ok = await couponProv.claimCoupon(id);
                                if (ok && context.mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    SnackBar(
                                      content: Text('🎉 Đã lưu mã $code vào ví voucher của bạn!'),
                                      backgroundColor: AppColors.success,
                                      behavior: SnackBarBehavior.floating,
                                      duration: const Duration(seconds: 2),
                                    ),
                                  );
                                }
                              },
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: isSaved ? const Color(0xFFE2E8F0) : AppColors.primary,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            isSaved ? 'Đã lưu' : 'Lưu',
                            style: TextStyle(
                              color: isSaved ? const Color(0xFF64748B) : Colors.white,
                              fontSize: 10.5,
                              fontWeight: FontWeight.w800,
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
  }

  // ── 4. Category Quick Icon Grid (Shopee 2x5 Squircle Grid with Micro Badges) ──
  Widget _buildCategoryIconGrid(BuildContext context, HomeProvider provider) {
    final quickItems = [
      {
        'label': 'Flash Sale',
        'icon': Icons.flash_on_rounded,
        'color': const Color(0xFFEF4444),
        'badge': 'HOT',
        'action': () => context.push('/flash-sale'),
      },
      {
        'label': 'Kho Voucher',
        'icon': Icons.confirmation_number_outlined,
        'color': const Color(0xFF3B82F6),
        'badge': '500K',
        'action': () => context.push('/coupon'),
      },
      {
        'label': 'Đặt Sân Bãi',
        'icon': Icons.sports_tennis_rounded,
        'color': const Color(0xFF10B981),
        'badge': 'LIVE',
        'action': () => context.go('/court'),
      },
      {
        'label': 'Đổi Điểm VIP',
        'icon': Icons.stars_rounded,
        'color': const Color(0xFFF59E0B),
        'badge': 'X2',
        'action': () => context.push('/loyalty'),
      },
      {
        'label': 'Giày Thi Đấu',
        'icon': Icons.snowshoeing_rounded,
        'color': const Color(0xFF8B5CF6),
        'badge': 'NEW',
        'action': () => context.go('/shop'),
      },
      {
        'label': 'Vợt Thể Thao',
        'icon': Icons.sports_baseball_rounded,
        'color': const Color(0xFF06B6D4),
        'badge': null,
        'action': () => context.go('/shop'),
      },
      {
        'label': 'Áo & Phụ Kiện',
        'icon': Icons.checkroom_rounded,
        'color': const Color(0xFFEC4899),
        'badge': null,
        'action': () => context.go('/shop'),
      },
      {
        'label': 'Bán Chạy',
        'icon': Icons.local_fire_department_rounded,
        'color': const Color(0xFFFF5722),
        'badge': 'TOP 1',
        'action': () => context.go('/shop'),
      },
      {
        'label': 'Ocean Mall',
        'icon': Icons.verified_rounded,
        'color': const Color(0xFFE63B6F),
        'badge': '100%',
        'action': () => context.go('/shop'),
      },
      {
        'label': 'Quà Tặng VIP',
        'icon': Icons.card_giftcard_rounded,
        'color': const Color(0xFF6366F1),
        'badge': 'FREE',
        'action': () => context.push('/loyalty'),
      },
    ];

    return Container(
      margin: const EdgeInsets.only(top: 8),
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(10, 14, 10, 14),
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 5,
          mainAxisSpacing: 12,
          crossAxisSpacing: 4,
          childAspectRatio: 0.78,
        ),
        itemCount: quickItems.length,
        itemBuilder: (context, index) {
          final item = quickItems[index];
          final color = item['color'] as Color;
          final icon = item['icon'] as IconData;
          final label = item['label'] as String;
          final badge = item['badge'] as String?;
          final callback = item['action'] as VoidCallback;

          return GestureDetector(
            onTap: callback,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Stack(
                  clipBehavior: Clip.none,
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: color.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Center(
                        child: Icon(icon, color: color, size: 22),
                      ),
                    ),
                    if (badge != null)
                      Positioned(
                        top: -5,
                        right: -6,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1.5),
                          decoration: BoxDecoration(
                            gradient: AppGradients.primary,
                            borderRadius: BorderRadius.circular(6),
                            boxShadow: [
                              BoxShadow(
                                color: AppColors.primary.withValues(alpha: 0.3),
                                blurRadius: 4,
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
                    fontSize: 10.5,
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

  // ── 5. Flash Sale Live Box với ngọn lửa rực cháy ──
  Widget _buildFlashSaleSection(BuildContext context, HomeProvider provider) {
    if (provider.flashSaleProducts.isEmpty && !provider.isFlashSaleLoading) {
      return const SizedBox.shrink();
    }

    final hours = _flashSaleRemaining.inHours.toString().padLeft(2, '0');
    final minutes = (_flashSaleRemaining.inMinutes % 60).toString().padLeft(2, '0');
    final seconds = (_flashSaleRemaining.inSeconds % 60).toString().padLeft(2, '0');

    return Container(
      margin: const EdgeInsets.only(top: 8, bottom: 4),
      padding: const EdgeInsets.symmetric(vertical: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
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
          // Header: Flash sale + Flame + Countdown timer
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(4),
                  decoration: BoxDecoration(
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
                    color: Color(0xFFFF2A55),
                    letterSpacing: -0.2,
                  ),
                ),
                const SizedBox(width: 8),
                _buildCountdownBox(hours),
                const Text(' : ', style: TextStyle(fontWeight: FontWeight.w900, color: AppColors.textPrimary)),
                _buildCountdownBox(minutes),
                const Text(' : ', style: TextStyle(fontWeight: FontWeight.w900, color: AppColors.textPrimary)),
                _buildCountdownBox(seconds),
                const Spacer(),
                InkWell(
                  onTap: () => context.push('/flash-sale'),
                  child: const Row(
                    children: [
                      Text('Xem tất cả', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.textSecondary)),
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
            height: 220,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              itemCount: provider.flashSaleProducts.length,
              itemBuilder: (context, index) {
                final item = provider.flashSaleProducts[index];
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
                                height: 125,
                                width: double.infinity,
                                color: Colors.white,
                                padding: const EdgeInsets.all(8),
                                child: Center(
                                  child: NetworkImageWidget(
                                    imageUrl: imageUrl,
                                    fit: BoxFit.contain,
                                    customMemCacheWidth: 350,
                                    errorWidget: const Center(
                                      child: Icon(Icons.sports_tennis_rounded, color: Color(0xFFCBD5E1), size: 24),
                                    ),
                                  ),
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
                                    gradient: AppGradients.fireSale,
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
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFFFF2A55),
                                ),
                              ),
                              const SizedBox(height: 6),
                              // Sold progress bar
                              Container(
                                height: 14,
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFFE4EC),
                                  borderRadius: BorderRadius.circular(7),
                                ),
                                child: Stack(
                                  children: [
                                    if (progress > 0)
                                      FractionallySizedBox(
                                        widthFactor: progress,
                                        child: Container(
                                          decoration: BoxDecoration(
                                            gradient: AppGradients.fireSale,
                                            borderRadius: BorderRadius.circular(7),
                                          ),
                                        ),
                                      ),
                                    Center(
                                      child: Text(
                                        soldText,
                                        style: TextStyle(
                                          color: progress > 0.45 ? Colors.white : const Color(0xFFC72859),
                                          fontSize: 8.5,
                                          fontWeight: FontWeight.w900,
                                          letterSpacing: 0.1,
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
        color: const Color(0xFF0F172A),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        text,
        style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w900),
      ),
    );
  }

  // ── 6. Ocean Mall - Gian hàng uỷ quyền chính hãng ──
  Widget _buildOceanMallSection(BuildContext context) {
    final brands = [
      {'name': 'Yonex', 'tag': 'Nhật Bản', 'color': const Color(0xFF0038A8)},
      {'name': 'Babolat', 'tag': 'Pháp', 'color': const Color(0xFF0047AB)},
      {'name': 'Victor', 'tag': 'Đài Loan', 'color': const Color(0xFF1E3A8A)},
      {'name': 'Lining', 'tag': 'Chính hãng', 'color': const Color(0xFFDC2626)},
      {'name': 'Wilson', 'tag': 'Mỹ', 'color': const Color(0xFFB91C1C)},
      {'name': 'Mizuno', 'tag': 'Nhật Bản', 'color': const Color(0xFF0F172A)},
    ];

    return Container(
      margin: const EdgeInsets.only(top: 8),
      color: Colors.white,
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    gradient: AppGradients.oceanMall,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text(
                    'OCEAN MALL',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 0.5,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                const Text(
                  'Gian Hàng Chính Hãng',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0F172A),
                  ),
                ),
                const Spacer(),
                GestureDetector(
                  onTap: () => context.go('/shop'),
                  child: const Row(
                    children: [
                      Text('Xem thêm', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary)),
                      Icon(Icons.chevron_right_rounded, size: 16, color: AppColors.primary),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 64,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              itemCount: brands.length,
              itemBuilder: (context, index) {
                final b = brands[index];
                return GestureDetector(
                  onTap: () => context.go('/shop'),
                  child: Container(
                    width: 110,
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFE2E8F0)),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          b['name'] as String,
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w900,
                            color: b['color'] as Color,
                            letterSpacing: -0.2,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          b['tag'] as String,
                          style: const TextStyle(
                            fontSize: 10,
                            color: Color(0xFF64748B),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 10),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                _MallPill(icon: Icons.verified_user_outlined, label: '100% Chính hãng'),
                _MallPill(icon: Icons.local_shipping_outlined, label: 'Miễn phí vận chuyển'),
                _MallPill(icon: Icons.published_with_changes_rounded, label: 'Đổi trả 15 ngày'),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ── 7. Recommendation Header with Tabs ──
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
              Icon(Icons.explore_outlined, color: AppColors.primary, size: 20),
              SizedBox(width: 6),
              Text(
                'GỢI Ý HÔM NAY',
                style: TextStyle(
                  fontSize: 15.5,
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
                _buildFilterTab('all', '🌟 Tất cả'),
                _buildFilterTab('hot', '🔥 Bán chạy'),
                _buildFilterTab('sale', '🏷️ Giảm sâu'),
                _buildFilterTab('new', '✨ Hàng mới về'),
                _buildFilterTab('top_rated', '💎 Đánh giá cao'),
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

  // ── 8. Products 2-Column Grid ──
  Widget _buildProductSliverGrid(BuildContext context, HomeProvider provider) {
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
    if (_selectedTab == 'hot') {
      displayProducts = provider.bestSellingProducts.isNotEmpty ? provider.bestSellingProducts : provider.products;
    } else if (_selectedTab == 'sale') {
      displayProducts = provider.onSaleProducts.isNotEmpty ? provider.onSaleProducts : provider.products;
    } else if (_selectedTab == 'new') {
      displayProducts = provider.products.reversed.toList();
    } else if (_selectedTab == 'top_rated') {
      displayProducts = List.from(provider.products)..sort((a, b) {
        final rA = FormatUtils.parseNum(a['avg_rating'] ?? a['rating'] ?? 5);
        final rB = FormatUtils.parseNum(b['avg_rating'] ?? b['rating'] ?? 5);
        return rB.compareTo(rA);
      });
    }

    if (displayProducts.isEmpty) {
      return const SliverToBoxAdapter(
        child: Padding(
          padding: EdgeInsets.symmetric(vertical: 40),
          child: Center(
            child: Text(
              'Chưa có sản phẩm nào trong danh mục này.',
              style: TextStyle(color: AppColors.textSecondary),
            ),
          ),
        ),
      );
    }

    return SliverPadding(
      padding: const EdgeInsets.fromLTRB(10, 10, 10, 16),
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
              product: product is Map<String, dynamic> ? product : Map<String, dynamic>.from(product),
            );
          },
          childCount: displayProducts.length,
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
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: AppColors.primary),
        const SizedBox(width: 4),
        Text(
          label,
          style: const TextStyle(
            fontSize: 10.5,
            fontWeight: FontWeight.w600,
            color: Color(0xFF475569),
          ),
        ),
      ],
    );
  }
}
