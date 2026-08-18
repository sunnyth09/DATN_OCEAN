import 'dart:async';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../config/app_theme.dart';
import '../providers/cart_provider.dart';
import '../providers/category_provider.dart';
import '../widgets/product_card.dart';
import '../widgets/shimmer_loading.dart';

/// Màn hình Cửa Hàng chuẩn TikTok Shop & Shopee Mall Tier:
/// - Header siêu tinh gọn (Ultra-Compact Header): Gom toàn bộ Tìm kiếm, Lọc và Giỏ hàng vào 1 hàng duy nhất.
/// - Giải phóng 60% diện tích phía trên để người dùng nhìn thấy sản phẩm ngay lập tức khi mở app.
class CategoryScreen extends StatefulWidget {
  const CategoryScreen({super.key});

  @override
  State<CategoryScreen> createState() => _CategoryScreenState();
}

class _CategoryScreenState extends State<CategoryScreen>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  final _searchCtrl = TextEditingController();
  Timer? _debounce;
  final _scrollCtrl = ScrollController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CategoryProvider>().loadAll();
    });
    _scrollCtrl.addListener(_onScroll);
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _scrollCtrl.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 250) {
      context.read<CategoryProvider>().loadMore();
    }
  }

  void _showFilterSheet() {
    String tmpSort = context.read<CategoryProvider>().sortBy;
    RangeValues tmpPrice = context.read<CategoryProvider>().priceRange;
    bool tmpInStock = context.read<CategoryProvider>().filterInStock;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => StatefulBuilder(
        builder: (ctx, setSheet) => Container(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(ctx).viewInsets.bottom,
          ),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 16, 24, 32),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 44,
                    height: 4.5,
                    decoration: BoxDecoration(
                      color: Colors.grey.shade300,
                      borderRadius: BorderRadius.circular(3),
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Bộ Lọc & Sắp Xếp',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    TextButton(
                      onPressed: () => setSheet(() {
                        tmpSort = 'newest';
                        tmpPrice = const RangeValues(0, 50000000);
                        tmpInStock = false;
                      }),
                      child: const Text(
                        'Đặt lại',
                        style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),

                // Sort
                const Text(
                  'Sắp xếp theo',
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 14,
                    color: Color(0xFF334155),
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _sortChip('newest', 'Mới nhất', tmpSort, (v) => setSheet(() => tmpSort = v)),
                    _sortChip('popular', 'Bán chạy nhất', tmpSort, (v) => setSheet(() => tmpSort = v)),
                    _sortChip('price_asc', 'Giá: Thấp → Cao', tmpSort, (v) => setSheet(() => tmpSort = v)),
                    _sortChip('price_desc', 'Giá: Cao → Thấp', tmpSort, (v) => setSheet(() => tmpSort = v)),
                  ],
                ),
                const SizedBox(height: 24),

                // Price range
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Khoảng giá',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                        color: Color(0xFF334155),
                      ),
                    ),
                    Text(
                      '${_fmtPrice(tmpPrice.start)} – ${_fmtPrice(tmpPrice.end)}',
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.primary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
                SliderTheme(
                  data: SliderTheme.of(ctx).copyWith(
                    activeTrackColor: AppColors.primary,
                    thumbColor: AppColors.primary,
                    inactiveTrackColor: const Color(0xFFE2E8F0),
                    overlayColor: AppColors.primary.withValues(alpha: 0.1),
                  ),
                  child: RangeSlider(
                    values: tmpPrice,
                    min: 0,
                    max: 50000000,
                    divisions: 100,
                    onChanged: (v) => setSheet(() => tmpPrice = v),
                  ),
                ),
                const SizedBox(height: 12),

                // In stock toggle
                GestureDetector(
                  onTap: () => setSheet(() => tmpInStock = !tmpInStock),
                  child: Row(
                    children: [
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        width: 22,
                        height: 22,
                        decoration: BoxDecoration(
                          color: tmpInStock ? AppColors.primary : Colors.white,
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(
                            color: tmpInStock ? AppColors.primary : const Color(0xFFCBD5E1),
                            width: 1.5,
                          ),
                        ),
                        child: tmpInStock
                            ? const Icon(Icons.check, size: 15, color: Colors.white)
                            : null,
                      ),
                      const SizedBox(width: 10),
                      const Text(
                        'Chỉ hiển thị sản phẩm còn hàng',
                        style: TextStyle(
                          fontSize: 14,
                          color: Color(0xFF334155),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 28),

                SizedBox(
                  width: double.infinity,
                  height: 44,
                  child: ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      elevation: 0,
                    ),
                    onPressed: () {
                      Navigator.pop(ctx);
                      context.read<CategoryProvider>().applyFilters(
                            sort: tmpSort,
                            price: tmpPrice,
                            inStock: tmpInStock,
                          );
                    },
                    child: const Text(
                      'Áp dụng bộ lọc',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 14,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _sortChip(
    String value,
    String label,
    String current,
    void Function(String) onTap,
  ) {
    final sel = current == value;
    return GestureDetector(
      onTap: () => onTap(value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: sel ? AppColors.primary : const Color(0xFFF1F5F9),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: sel ? AppColors.primary : Colors.transparent,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12.5,
            fontWeight: FontWeight.w700,
            color: sel ? Colors.white : const Color(0xFF475569),
          ),
        ),
      ),
    );
  }

  String _fmtPrice(double v) {
    if (v >= 1000000) return '${(v / 1000000).toStringAsFixed(1)}M';
    if (v >= 1000) return '${(v / 1000).toStringAsFixed(0)}K';
    return v.toStringAsFixed(0);
  }

  bool get _hasActiveFilter =>
      context.read<CategoryProvider>().selectedCategoryId != null ||
      context.read<CategoryProvider>().sortBy != 'newest' ||
      context.read<CategoryProvider>().filterInStock ||
      context.read<CategoryProvider>().priceRange.start > 0 ||
      context.read<CategoryProvider>().priceRange.end < 50000000;

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: SafeArea(
        child: NestedScrollView(
          headerSliverBuilder: (_, _) => [
            SliverToBoxAdapter(child: _buildCompactTopBar()),
            SliverToBoxAdapter(child: _buildCompactCategoryChips()),
          ],
          body: _buildBody(),
        ),
      ),
    );
  }

  /// ── 1. COMPACT INTEGRATED TOP BAR (Shopee / TikTok Shop Style) ──
  Widget _buildCompactTopBar() {
    final cart = context.watch<CartProvider>();

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 6),
      child: Row(
        children: [
          // Expanded Integrated Search Bar
          Expanded(
            child: Container(
              height: 38,
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: TextField(
                controller: _searchCtrl,
                onChanged: context.read<CategoryProvider>().updateSearch,
                onSubmitted: (v) {
                  _debounce?.cancel();
                  context.read<CategoryProvider>().updateSearch(v.trim());
                },
                decoration: InputDecoration(
                  hintText: 'Tìm kiếm vợt, giày, trang phục...',
                  hintStyle: const TextStyle(
                    color: Color(0xFF94A3B8),
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                  ),
                  prefixIcon: const Icon(
                    Icons.search_rounded,
                    color: AppColors.primary,
                    size: 18,
                  ),
                  prefixIconConstraints: const BoxConstraints(minWidth: 34),
                  suffixIcon: context.watch<CategoryProvider>().searchQuery.isNotEmpty
                      ? IconButton(
                          icon: const Icon(Icons.close_rounded, size: 16, color: Color(0xFF64748B)),
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(minWidth: 32),
                          onPressed: () {
                            _searchCtrl.clear();
                            context.read<CategoryProvider>().updateSearch('');
                          },
                        )
                      : null,
                  isDense: true,
                  filled: false,
                  border: InputBorder.none,
                  enabledBorder: InputBorder.none,
                  focusedBorder: InputBorder.none,
                  contentPadding: const EdgeInsets.symmetric(vertical: 9),
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),

          // Filter button (Compact Square)
          GestureDetector(
            onTap: _showFilterSheet,
            child: Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: _hasActiveFilter ? AppColors.primary : const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: _hasActiveFilter ? AppColors.primary : const Color(0xFFE2E8F0),
                ),
              ),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  Icon(
                    Icons.tune_rounded,
                    size: 18,
                    color: _hasActiveFilter ? Colors.white : const Color(0xFF475569),
                  ),
                  if (_hasActiveFilter)
                    Positioned(
                      top: 6,
                      right: 6,
                      child: Container(
                        width: 6,
                        height: 6,
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(width: 8),

          // Cart Icon Button (Compact Square with live badge)
          GestureDetector(
            onTap: () => context.push('/cart'),
            child: Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Center(
                child: Badge(
                  isLabelVisible: cart.itemCount > 0,
                  label: Text(
                    cart.itemCount > 99 ? '99+' : '${cart.itemCount}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 8.5,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  backgroundColor: AppColors.primary,
                  offset: const Offset(4, -4),
                  child: const Icon(Icons.shopping_cart_outlined, size: 20, color: Color(0xFF1E293B)),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// ── 2. COMPACT CATEGORY CHIPS (Shopee / TikTok Minimalist Ribbon) ──
  Widget _buildCompactCategoryChips() {
    if (context.watch<CategoryProvider>().categories.isEmpty) return const SizedBox(height: 4);
    final cats = context.watch<CategoryProvider>().categories;

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.only(bottom: 8, top: 4),
      child: SizedBox(
        height: 32,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: cats.length + 1,
          itemBuilder: (_, i) {
            if (i == 0) {
              final sel = context.watch<CategoryProvider>().selectedCategoryId == null;
              return _compactChip(
                label: 'Tất cả',
                selected: sel,
                onTap: () => context.read<CategoryProvider>().selectCategory(null, null),
              );
            }
            final cat = cats[i - 1];
            final id = cat['category_id'] ?? cat['id'];
            final rawName = cat['name']?.toString() ?? '';
            final cleanName = rawName.replaceAll(RegExp(r'\s+'), ' ').trim();
            final sel =
                context.watch<CategoryProvider>().selectedCategoryId != null &&
                context.watch<CategoryProvider>().selectedCategoryId.toString() == id.toString();
            return _compactChip(
              label: cleanName,
              selected: sel,
              onTap: () => context.read<CategoryProvider>().selectCategory(int.tryParse(id.toString()), cleanName),
            );
          },
        ),
      ),
    );
  }

  Widget _compactChip({
    required String label,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        margin: const EdgeInsets.only(right: 6),
        padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 5),
        decoration: BoxDecoration(
          gradient: selected
              ? const LinearGradient(
                  colors: [Color(0xFFE63B6F), Color(0xFFFF6584)],
                )
              : null,
          color: selected ? null : const Color(0xFFF1F5F9),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected ? Colors.transparent : const Color(0xFFE2E8F0),
            width: 0.8,
          ),
          boxShadow: selected
              ? [
                  BoxShadow(
                    color: const Color(0xFFE63B6F).withValues(alpha: 0.22),
                    blurRadius: 4,
                    offset: const Offset(0, 1.5),
                  ),
                ]
              : null,
        ),
        child: Center(
          child: Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
              color: selected ? Colors.white : const Color(0xFF334155),
              letterSpacing: -0.1,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildBody() {
    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: () => context.read<CategoryProvider>().refresh(),
      child: CustomScrollView(
        controller: _scrollCtrl,
        cacheExtent: 800,
        physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
        slivers: [
          // Active filter pills
          if (_hasActiveFilter) SliverToBoxAdapter(child: _buildActiveBadges()),

          // Products count
          if (!context.watch<CategoryProvider>().isLoading && context.watch<CategoryProvider>().products.isNotEmpty)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 2),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      '${context.watch<CategoryProvider>().products.length} sản phẩm${context.watch<CategoryProvider>().hasMore ? '+' : ''}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: Color(0xFF64748B),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const Text(
                      'Chính hãng 100%',
                      style: TextStyle(
                        fontSize: 11,
                        color: Color(0xFF10B981),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ),

          // Loading
          if (context.watch<CategoryProvider>().isLoading && context.watch<CategoryProvider>().products.isEmpty)
            const SliverShimmerLoading(
              padding: EdgeInsets.fromLTRB(14, 8, 14, 16),
              crossAxisSpacing: 10,
              mainAxisSpacing: 12,
              childAspectRatio: 0.65,
            ),

          // Error
          if (context.watch<CategoryProvider>().errorMessage != null && context.watch<CategoryProvider>().products.isEmpty)
            SliverToBoxAdapter(child: _buildError()),

          // Empty
          if (!context.watch<CategoryProvider>().isLoading && context.watch<CategoryProvider>().products.isEmpty && context.watch<CategoryProvider>().errorMessage == null)
            SliverToBoxAdapter(child: _buildEmpty()),

          // Grid
          if (context.watch<CategoryProvider>().products.isNotEmpty)
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(14, 6, 14, 24),
              sliver: SliverGrid(
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  crossAxisSpacing: 10,
                  mainAxisSpacing: 12,
                  childAspectRatio: 0.65,
                ),
                delegate: SliverChildBuilderDelegate(
                  (_, i) => ProductCard(
                    product: context.watch<CategoryProvider>().products[i],
                  ),
                  childCount: context.watch<CategoryProvider>().products.length,
                  addRepaintBoundaries: true,
                  addAutomaticKeepAlives: false,
                ),
              ),
            ),

          // Load more indicator
          if (context.watch<CategoryProvider>().isFetchingMore)
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.symmetric(vertical: 20),
                child: Center(
                  child: CircularProgressIndicator(
                    color: AppColors.primary,
                    strokeWidth: 2.5,
                  ),
                ),
              ),
            ),

          if (!context.watch<CategoryProvider>().hasMore && context.watch<CategoryProvider>().products.length > 4)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 24),
                child: Center(
                  child: Text(
                    'Đã hiển thị tất cả ${context.watch<CategoryProvider>().products.length} sản phẩm',
                    style: const TextStyle(
                      color: Color(0xFF94A3B8),
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildActiveBadges() {
    final provider = context.watch<CategoryProvider>();
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 6, 16, 0),
      child: Wrap(
        spacing: 6,
        runSpacing: 4,
        children: [
          if (provider.sortBy != 'newest')
            _badge(
              _sortLabel(),
              onRemove: () => provider.applyFilters(
                sort: 'newest',
                price: provider.priceRange,
                inStock: provider.filterInStock,
              ),
            ),
          if (provider.filterInStock)
            _badge(
              'Còn hàng',
              onRemove: () => provider.applyFilters(
                sort: provider.sortBy,
                price: provider.priceRange,
                inStock: false,
              ),
            ),
          if (provider.priceRange.start > 0 || provider.priceRange.end < 50000000)
            _badge(
              '${_fmtPrice(provider.priceRange.start)}–${_fmtPrice(provider.priceRange.end)} đ',
              onRemove: () => provider.applyFilters(
                sort: provider.sortBy,
                price: const RangeValues(0, 50000000),
                inStock: provider.filterInStock,
              ),
            ),
        ],
      ),
    );
  }

  Widget _badge(String label, {required VoidCallback onRemove}) {
    return Chip(
      label: Text(
        label,
        style: const TextStyle(fontSize: 11, color: AppColors.primary, fontWeight: FontWeight.w700),
      ),
      backgroundColor: AppColors.primaryContainer,
      deleteIcon: const Icon(Icons.close_rounded, size: 13, color: AppColors.primary),
      onDeleted: onRemove,
      padding: const EdgeInsets.symmetric(horizontal: 3),
      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
      side: const BorderSide(color: Color(0xFFFFD1DC)),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
    );
  }

  String _sortLabel() {
    switch (context.watch<CategoryProvider>().sortBy) {
      case 'price_asc':
        return 'Giá: Thấp → Cao';
      case 'price_desc':
        return 'Giá: Cao → Thấp';
      case 'popular':
        return 'Bán chạy nhất';
      default:
        return '';
    }
  }

  Widget _buildError() {
    return Padding(
      padding: const EdgeInsets.all(40),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 54, color: Color(0xFF94A3B8)),
          const SizedBox(height: 16),
          Text(
            context.watch<CategoryProvider>().errorMessage!,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Color(0xFF64748B), fontSize: 14, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 16),
          ElevatedButton.icon(
            onPressed: () => context.read<CategoryProvider>().refresh(),
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Thử lại'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmpty() {
    return Padding(
      padding: const EdgeInsets.only(top: 60),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: const BoxDecoration(
              color: Color(0xFFF1F5F9),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.search_off_rounded, size: 50, color: Color(0xFF94A3B8)),
          ),
          const SizedBox(height: 16),
          const Text(
            'Không tìm thấy sản phẩm',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: Color(0xFF0F172A),
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm khác',
            style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
          ),
        ],
      ),
    );
  }
}
