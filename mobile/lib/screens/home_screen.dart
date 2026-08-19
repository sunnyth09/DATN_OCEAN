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

/// Màn hình Trang Chủ đẳng cấp Sàn Thương Mại Điện Tử Quốc Tế (Shopee Mall / TikTok Shop / Taobao tier).
/// - Hệ màu Brand Aurora sống động, không khí lễ hội mua sắm đỉnh cao.
/// - Single-Row Search Header tích hợp Chat & Cart, loại bỏ 100% che khuất sản phẩm.
/// - Hero Banner 3D tràn viền với hiệu ứng ánh sáng hào quang (Ambient Glow).
/// - Kho Voucher dạng Vé răng cưa lấp lánh 1 chạm lưu.
/// - Lưới 10 Applet dịch vụ 3D Sport Neon rực rỡ.
/// - Flash Sale bốc lửa không bị che khuất, tối ưu 60-120 FPS.
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
      'gradient': const [Color(0xFFE11D48), Color(0xFFBE185D)],
      'action': '/flash-sale',
    },
    {
      'tag': 'BỘ SƯU TẬP MỚI',
      'title': 'Giày Thi Đấu Chính Hãng',
      'highlight': 'ĐỆM KHÍ CAO CẤP',
      'cta': 'Xem sản phẩm',
      'image': '/storage/products/f5801e74-1129-4d64-a1c3-00c68f34e191.webp',
      'gradient': const [Color(0xFF1E3A8A), Color(0xFF0284C7)],
      'action': '/shop',
    },
    {
      'tag': 'ĐẶC QUYỀN VIP',
      'title': 'Áo Đấu & Phụ Kiện',
      'highlight': 'TẶNG VOUCHER 500K',
      'cta': 'Lấy mã ngay',
      'image': '/storage/products/026f9252-4579-4f19-a72a-740bfeb619ee.webp',
      'gradient': const [Color(0xFFB45309), Color(0xFFEA580C)],
      'action': '/coupon',
    },
  ];

  @override
  void initState() {
    super.initState();
    _bannerController = PageController(initialPage: _kInitialBannerPage);
    WidgetsBinding.instance.addPostFrameCallback((_) {
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
      backgroundColor: Colors.white, // Nền trắng tinh tế, đồng bộ, sang trọng
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
            cacheExtent: 1000,
            physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
            slivers: [
              // 1. Top Section: Brand Aurora Backdrop + Integrated Header + 3D Hero Carousel
              SliverToBoxAdapter(child: _buildTopBrandAtmosphere(context)),

              // 2. Lưới 8 Dịch Vụ & Ngành Hàng Thể Thao Soft Pastel (2x4 Grid)
              SliverToBoxAdapter(child: _buildCategoryIconGrid(context, provider)),

              // 3. Dải Voucher Răng Cưa 1 Chạm Lưu (Luxury Perforated Ticket Strip)
              SliverToBoxAdapter(child: _buildVoucherTicketStrip(context, provider)),

              // 4. Flash Sale Box Rực Cháy (Không bị che khuất bởi bất kỳ nút nào)
              if (provider.flashSaleProducts.isNotEmpty)
                SliverToBoxAdapter(child: _buildFlashSaleSection(context, provider)),

              // 5. Ocean Mall - Gian Hàng Uỷ Quyền Chính Hãng
              SliverToBoxAdapter(child: _buildOceanMallSection(context)),

              // 6. Header "Gợi Ý Hôm Nay" Kèm Tab Lọc
              SliverToBoxAdapter(child: _buildRecommendationHeader(context)),

              // 7. Grid Sản Phẩm 2 Cột Chuẩn 60-120 FPS
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

              const SliverToBoxAdapter(child: SizedBox(height: 36)),
            ],
          ),
        ),
      ),
      floatingActionButton: AnimatedScale(
        scale: _showBackToTop ? 1.0 : 0.0,
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
      ),
    );
  }

  // ── 1. Top Section: Brand Aurora Backdrop + Single-Row Header + 3D Banner ──
  Widget _buildTopBrandAtmosphere(BuildContext context) {
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
          // ── Header Row: Search + Chat + Cart ──
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 8),
            child: Row(
              children: [
                // Thanh Tìm Kiếm Đa Năng
                Expanded(
                  child: GestureDetector(
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
                              child: Text(
                                _trendingKeywords[_tickerIndex],
                                key: ValueKey<int>(_tickerIndex),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w600,
                                  color: Color(0xFF64748B),
                                ),
                              ),
                            ),
                          ),
                          GestureDetector(
                            onTap: () {
                              HapticFeedback.lightImpact();
                              context.push('/pos-scanner');
                            },
                            child: Container(
                              padding: const EdgeInsets.all(4),
                              decoration: BoxDecoration(
                                color: AppColors.primary.withValues(alpha: 0.08),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(Icons.camera_alt_outlined, color: AppColors.primary, size: 15),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),

                const SizedBox(width: 8),

                // Nút Tin Nhắn / Chat Trực Tuyến 24/7 (Thiết kế Sàn TMĐT Chuẩn Quốc Tế)
                GestureDetector(
                  onTap: () {
                    HapticFeedback.lightImpact();
                    context.push('/chat');
                  },
                  behavior: HitTestBehavior.opaque,
                  child: Container(
                    width: 38,
                    height: 40,
                    alignment: Alignment.center,
                    child: Stack(
                      clipBehavior: Clip.none,
                      children: [
                        const Icon(
                          Icons.chat_bubble_outline_rounded,
                          color: Colors.white,
                          size: 24,
                        ),
                        Positioned(
                          top: -1,
                          right: -1,
                          child: Container(
                            width: 8,
                            height: 8,
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFD700),
                              shape: BoxShape.circle,
                              border: Border.all(color: const Color(0xFFE11D48), width: 1.5),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

                const SizedBox(width: 4),

                // Nút Giỏ Hàng với Badge Số Lượng Nổi Bật Chuẩn Sàn TMĐT
                Consumer<CartProvider>(
                  builder: (context, cart, _) => GestureDetector(
                    onTap: () {
                      HapticFeedback.lightImpact();
                      context.push('/cart');
                    },
                    behavior: HitTestBehavior.opaque,
                    child: Container(
                      width: 38,
                      height: 40,
                      alignment: Alignment.center,
                      child: Badge(
                        isLabelVisible: cart.itemCount > 0,
                        label: Text(
                          cart.itemCount > 99 ? '99+' : cart.itemCount.toString(),
                          style: const TextStyle(
                            color: AppColors.primary,
                            fontSize: 9.5,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        backgroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 4.5, vertical: 1),
                        offset: const Offset(6, -6),
                        child: const Icon(
                          Icons.shopping_cart_outlined,
                          color: Colors.white,
                          size: 24,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // ── 3D Hero Banner Carousel (Tràn viền, ánh sáng đa tầng) ──
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 6),
            child: Column(
              children: [
                SizedBox(
                  height: 154,
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
                          onTap: () {
                            HapticFeedback.lightImpact();
                            context.push(b['action']);
                          },
                          child: Container(
                            margin: const EdgeInsets.symmetric(horizontal: 2),
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                colors: b['gradient'],
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                              ),
                              borderRadius: BorderRadius.circular(18),
                              boxShadow: [
                                BoxShadow(
                                  color: (b['gradient'][0] as Color).withValues(alpha: 0.15),
                                  blurRadius: 10,
                                  offset: const Offset(0, 4),
                                ),
                              ],
                            ),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(18),
                              child: Stack(
                                children: [
                                  // Background Luxury Texture Lines
                                  Positioned(
                                    right: -30,
                                    top: -30,
                                    child: Container(
                                      width: 140,
                                      height: 140,
                                      decoration: BoxDecoration(
                                        shape: BoxShape.circle,
                                        color: Colors.white.withValues(alpha: 0.12),
                                      ),
                                    ),
                                  ),

                                  // Right Side: Ambient Glow Halo + 3D Angled Sport Product
                                  Positioned(
                                    right: 8,
                                    top: 6,
                                    bottom: 6,
                                    width: 140,
                                    child: Stack(
                                      alignment: Alignment.center,
                                      children: [
                                        // Ambient Glow Circle
                                        Container(
                                          width: 120,
                                          height: 120,
                                          decoration: BoxDecoration(
                                            shape: BoxShape.circle,
                                            color: Colors.white.withValues(alpha: 0.18),
                                            boxShadow: [
                                              BoxShadow(
                                                color: Colors.white.withValues(alpha: 0.2),
                                                blurRadius: 16,
                                                spreadRadius: 2,
                                              ),
                                            ],
                                          ),
                                        ),
                                        // 3D Angled Sports Image
                                        Transform.rotate(
                                          angle: -0.12,
                                          child: Container(
                                            padding: const EdgeInsets.all(4),
                                            child: NetworkImageWidget(
                                              imageUrl: AppConfig.imageUrl(b['image']),
                                              fit: BoxFit.contain,
                                              customMemCacheWidth: 380,
                                              errorWidget: Center(
                                                child: Icon(
                                                  Icons.sports_tennis_rounded,
                                                  size: 58,
                                                  color: Colors.white.withValues(alpha: 0.6),
                                                ),
                                              ),
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),

                                  // Left Content: Tag + Title + Highlight + Action Button
                                  Positioned(
                                    left: 0,
                                    top: 0,
                                    bottom: 0,
                                    right: 140,
                                    child: Padding(
                                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                            decoration: BoxDecoration(
                                              color: Colors.black.withValues(alpha: 0.25),
                                              borderRadius: BorderRadius.circular(10),
                                              border: Border.all(color: Colors.white.withValues(alpha: 0.3), width: 0.8),
                                            ),
                                            child: Text(
                                              b['tag'],
                                              style: const TextStyle(
                                                color: Colors.white,
                                                fontSize: 9.5,
                                                fontWeight: FontWeight.w800,
                                                letterSpacing: 0.4,
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
                                                  fontSize: 15,
                                                  fontWeight: FontWeight.w900,
                                                  letterSpacing: -0.3,
                                                ),
                                              ),
                                              const SizedBox(height: 2),
                                              Text(
                                                b['highlight'],
                                                style: const TextStyle(
                                                  color: Color(0xFFFFE082),
                                                  fontSize: 13.5,
                                                  fontWeight: FontWeight.w900,
                                                  letterSpacing: 0.3,
                                                ),
                                              ),
                                            ],
                                          ),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4.5),
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
                                                  b['cta'],
                                                  style: TextStyle(
                                                    color: b['gradient'][0],
                                                    fontSize: 11,
                                                    fontWeight: FontWeight.w900,
                                                  ),
                                                ),
                                                const SizedBox(width: 4),
                                                Icon(
                                                  Icons.arrow_forward_rounded,
                                                  size: 12,
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
                const SizedBox(height: 6),
                // Smooth Pill Indicator
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
          ),
        ],
      ),
    );
  }

  // ── 3. Kho Voucher Dạng Vé Răng Cưa 1 Chạm Lưu (Luxury Perforated Ticket Strip) ──
  Widget _buildVoucherTicketStrip(BuildContext context, HomeProvider provider) {
    final couponProv = context.watch<CouponProvider>();
    final vouchers = couponProv.publicCoupons.isNotEmpty
        ? couponProv.publicCoupons.take(6).toList()
        : (provider.homeVouchers.isNotEmpty ? provider.homeVouchers.take(6).toList() : []);

    if (vouchers.isEmpty) {
      return const SizedBox.shrink();
    }

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
                      // Left Ticket Punch Icon
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

                      // Perforated Dashed Divider
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

                      // Center Details
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

                      // Right Claim Button
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
                              gradient: isSaved
                                  ? null
                                  : AppGradients.primary,
                              color: isSaved ? const Color(0xFFE2E8F0) : null,
                              borderRadius: BorderRadius.circular(14),
                              boxShadow: isSaved
                                  ? null
                                  : [
                                      BoxShadow(
                                        color: AppColors.primary.withValues(alpha: 0.18),
                                        blurRadius: 4,
                                        offset: const Offset(0, 2),
                                      ),
                                    ],
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
  }

  // ── 2. Lưới 8 Dịch Vụ & Ngành Hàng Thể Thao Soft Pastel 2x4 ──
  Widget _buildCategoryIconGrid(BuildContext context, HomeProvider provider) {
    final quickItems = [
      {
        'label': 'Cầu Lông',
        'icon': Icons.sports_tennis_rounded,
        'bgColor': const Color(0xFFEFF6FF), // Sky Blue Pastel
        'iconColor': const Color(0xFF2563EB),
        'borderColor': const Color(0xFFDBEAFE),
        'badge': null,
        'action': () => context.go('/shop'),
      },
      {
        'label': 'Pickleball',
        'icon': Icons.sports_cricket_rounded,
        'bgColor': const Color(0xFFECFDF5), // Emerald Pastel
        'iconColor': const Color(0xFF059669),
        'borderColor': const Color(0xFFD1FAE5),
        'badge': null,
        'action': () => context.go('/shop'),
      },
      {
        'label': 'Bóng Chuyền',
        'icon': Icons.sports_volleyball_rounded,
        'bgColor': const Color(0xFFFFF7ED), // Orange Pastel
        'iconColor': const Color(0xFFEA580C),
        'borderColor': const Color(0xFFFFEDD5),
        'badge': null,
        'action': () => context.go('/shop'),
      },
      {
        'label': 'Giày Thể Thao',
        'icon': Icons.directions_run_rounded,
        'bgColor': const Color(0xFFF5F3FF), // Violet Pastel
        'iconColor': const Color(0xFF7C3AED),
        'borderColor': const Color(0xFFEDE9FE),
        'badge': null,
        'action': () => context.go('/shop'),
      },
      {
        'label': 'Đặt Sân Online',
        'icon': Icons.stadium_rounded,
        'bgColor': const Color(0xFFF0FDF4), // Mint Pastel
        'iconColor': const Color(0xFF16A34A),
        'borderColor': const Color(0xFFDCFCE7),
        'badge': 'LIVE',
        'action': () => context.go('/court'),
      },
      {
        'label': 'Flash Sale',
        'icon': Icons.local_fire_department_rounded,
        'bgColor': const Color(0xFFFFF1F2), // Rose Soft Pastel
        'iconColor': const Color(0xFFE11D48),
        'borderColor': const Color(0xFFFFE4E6),
        'badge': 'HOT',
        'action': () => context.push('/flash-sale'),
      },
      {
        'label': 'Săn Voucher',
        'icon': Icons.confirmation_number_rounded,
        'bgColor': const Color(0xFFFFFBEB), // Amber Pastel
        'iconColor': const Color(0xFFD97706),
        'borderColor': const Color(0xFFFEF3C7),
        'badge': null,
        'action': () => context.push('/coupon'),
      },
      {
        'label': 'Đổi Quà VIP',
        'icon': Icons.card_giftcard_rounded,
        'bgColor': const Color(0xFFEEF2FF), // Indigo Pastel
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

          Gradient? badgeGradient;
          if (badge == 'HOT') {
            badgeGradient = const LinearGradient(colors: [Color(0xFFFF2A55), Color(0xFFFF5E3A)]);
          } else if (badge == 'LIVE') {
            badgeGradient = const LinearGradient(colors: [Color(0xFF10B981), Color(0xFF059669)]);
          }

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
                            gradient: badgeGradient ?? AppGradients.fireSale,
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

  // ── 4. Flash Sale Box Rực Cháy (Không bị che khuất) ──
  Widget _buildFlashSaleSection(BuildContext context, HomeProvider provider) {
    if (provider.flashSaleProducts.isEmpty && !provider.isFlashSaleLoading) {
      return const SizedBox.shrink();
    }

    final hours = _flashSaleRemaining.inHours.toString().padLeft(2, '0');
    final minutes = (_flashSaleRemaining.inMinutes % 60).toString().padLeft(2, '0');
    final seconds = (_flashSaleRemaining.inSeconds % 60).toString().padLeft(2, '0');

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
          // Header: Flash sale + Flame + Countdown timer
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
                _buildCountdownBox(hours),
                const Text(' : ', style: TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
                _buildCountdownBox(minutes),
                const Text(' : ', style: TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
                _buildCountdownBox(seconds),
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

          // Horizontal Product Cards
          SizedBox(
            height: 174,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 10),
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
                        // Thumbnail with discount badge
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
                                    customMemCacheWidth: 350,
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
                                    boxShadow: [
                                      BoxShadow(
                                        color: const Color(0xFFFF2A55).withValues(alpha: 0.3),
                                        blurRadius: 4,
                                      ),
                                    ],
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
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.baseline,
                                textBaseline: TextBaseline.alphabetic,
                                children: [
                                  Expanded(
                                    child: Text(
                                      FormatUtils.formatPrice(numPrice),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w900,
                                        color: Color(0xFFFF1744),
                                      ),
                                    ),
                                  ),
                                  if (numOrig > numPrice) ...[
                                    const SizedBox(width: 3),
                                    Text(
                                      FormatUtils.formatPrice(numOrig),
                                      style: const TextStyle(
                                        fontSize: 9,
                                        color: Color(0xFF94A3B8),
                                        decoration: TextDecoration.lineThrough,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              const SizedBox(height: 5),
                              // Sold progress bar capsule
                              Container(
                                height: 13,
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
                                            gradient: const LinearGradient(
                                              colors: [Color(0xFFFF2A55), Color(0xFFFF7A00)],
                                            ),
                                            borderRadius: BorderRadius.circular(7),
                                          ),
                                        ),
                                      ),
                                    Center(
                                      child: Row(
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          const Icon(Icons.local_fire_department_rounded, color: Colors.white, size: 8.5),
                                          const SizedBox(width: 2),
                                          Text(
                                            soldText,
                                            style: TextStyle(
                                              color: progress > 0.45 ? Colors.white : const Color(0xFFC72859),
                                              fontSize: 7.5,
                                              fontWeight: FontWeight.w900,
                                              letterSpacing: 0.1,
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
        borderRadius: BorderRadius.circular(5),
      ),
      child: Text(
        text,
        style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w900),
      ),
    );
  }

  // ── 5. Ocean Mall - Gian Hàng Uỷ Quyền Chính Hãng ──
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
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14),
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
              padding: const EdgeInsets.symmetric(horizontal: 10),
              itemCount: brands.length,
              itemBuilder: (context, index) {
                final b = brands[index];
                return GestureDetector(
                  onTap: () => context.go('/shop'),
                  child: Container(
                    width: 112,
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
            padding: EdgeInsets.symmetric(horizontal: 14),
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

  // ── 6. Header "Gợi Ý Hôm Nay" Kèm Tab Lọc ──
  Widget _buildRecommendationHeader(BuildContext context) {
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
          // Filter Tabs
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            physics: const BouncingScrollPhysics(),
            child: Row(
              children: [
                _buildFilterTab('all', 'Tất cả'),
                _buildFilterTab('hot', 'Bán chạy'),
                _buildFilterTab('sale', 'Giảm sâu'),
                _buildFilterTab('new', 'Hàng mới về'),
                _buildFilterTab('top_rated', 'Đánh giá cao'),
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

  // ── 7. Grid Sản Phẩm 2 Cột ──
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
