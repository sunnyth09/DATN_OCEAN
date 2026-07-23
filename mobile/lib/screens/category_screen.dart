import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/category_provider.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../utils/format_utils.dart';
import '../widgets/shimmer_loading.dart';
import '../config/app_config.dart';
import '../widgets/network_image_widget.dart';
import 'product_detail_screen.dart';

class CategoryScreen extends StatefulWidget {
  const CategoryScreen({super.key});

  @override
  State<CategoryScreen> createState() => _CategoryScreenState();
}

class _CategoryScreenState extends State<CategoryScreen>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  // ─── Products ───────────────────────────────────────────
  // ─── Categories ─────────────────────────────────────────
  // ─── Filter / Sort ──────────────────────────────────────
  // ─── Search ─────────────────────────────────────────────
  final _searchCtrl = TextEditingController();
  Timer? _debounce;

  // ─── Scroll ─────────────────────────────────────────────
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
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: Colors.grey.shade300,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Bộ lọc & Sắp xếp',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
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
                        style: TextStyle(color: Color(0xFFE63B6F)),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),

                // Sort
                const Text(
                  'Sắp xếp theo',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                    color: Color(0xFF334155),
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 10,
                  children: [
                    _sortChip(
                      'newest',
                      'Mới nhất',
                      tmpSort,
                      (v) => setSheet(() => tmpSort = v),
                    ),
                    _sortChip(
                      'popular',
                      'Phổ biến',
                      tmpSort,
                      (v) => setSheet(() => tmpSort = v),
                    ),
                    _sortChip(
                      'price_asc',
                      'Giá tăng dần',
                      tmpSort,
                      (v) => setSheet(() => tmpSort = v),
                    ),
                    _sortChip(
                      'price_desc',
                      'Giá giảm dần',
                      tmpSort,
                      (v) => setSheet(() => tmpSort = v),
                    ),
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
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                        color: Color(0xFF334155),
                      ),
                    ),
                    Text(
                      '${_fmtPrice(tmpPrice.start)} – ${_fmtPrice(tmpPrice.end)}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: Color(0xFFE63B6F),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                SliderTheme(
                  data: SliderTheme.of(ctx).copyWith(
                    activeTrackColor: const Color(0xFFE63B6F),
                    thumbColor: const Color(0xFFE63B6F),
                    inactiveTrackColor: const Color(0xFFE2E8F0),
                    overlayColor: const Color(0xFFE63B6F).withValues(alpha: 0.1),
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

                // In stock
                GestureDetector(
                  onTap: () => setSheet(() => tmpInStock = !tmpInStock),
                  child: Row(
                    children: [
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        width: 22,
                        height: 22,
                        decoration: BoxDecoration(
                          color: tmpInStock
                              ? const Color(0xFFE63B6F)
                              : Colors.white,
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(
                            color: tmpInStock
                                ? const Color(0xFFE63B6F)
                                : const Color(0xFFCBD5E1),
                            width: 1.5,
                          ),
                        ),
                        child: tmpInStock
                            ? const Icon(
                                Icons.check,
                                size: 14,
                                color: Colors.white,
                              )
                            : null,
                      ),
                      const SizedBox(width: 10),
                      const Text(
                        'Chỉ hiển thị còn hàng',
                        style: TextStyle(
                          fontSize: 14,
                          color: Color(0xFF334155),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 28),

                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFE63B6F),
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                      elevation: 0,
                    ),
                    onPressed: () {
                      Navigator.pop(ctx);
                      context.read<CategoryProvider>().applyFilters(sort: tmpSort, price: tmpPrice, inStock: tmpInStock);
                      
                    },
                    child: const Text(
                      'Áp dụng',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
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
          color: sel ? const Color(0xFFE63B6F) : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: sel ? const Color(0xFFE63B6F) : const Color(0xFFE2E8F0),
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: sel ? Colors.white : const Color(0xFF475569),
          ),
        ),
      ),
    );
  }

  String _fmtPrice(double v) {
    if (v >= 1000000) return '${(v / 1000000).toStringAsFixed(0)}M';
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
            SliverToBoxAdapter(child: _buildTopBar()),
            SliverToBoxAdapter(child: _buildCategoryChips()),
          ],
          body: _buildBody(),
        ),
      ),
    );
  }

  Widget _buildTopBar() {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Khám phá',
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  Text(
                    context.watch<CategoryProvider>().selectedCategoryName != null
                        ? context.watch<CategoryProvider>().selectedCategoryName!
                        : 'Tất cả sản phẩm',
                    style: const TextStyle(
                      fontSize: 13,
                      color: Color(0xFF64748B),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
              // Filter button
              GestureDetector(
                onTap: _showFilterSheet,
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: _hasActiveFilter
                        ? const Color(0xFFE63B6F)
                        : const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        Icons.tune_rounded,
                        size: 16,
                        color: _hasActiveFilter
                            ? Colors.white
                            : const Color(0xFF475569),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        'Lọc',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: _hasActiveFilter
                              ? Colors.white
                              : const Color(0xFF475569),
                        ),
                      ),
                      if (_hasActiveFilter) ...[
                        const SizedBox(width: 4),
                        Container(
                          width: 6,
                          height: 6,
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          // Search bar
          Container(
            height: 46,
            decoration: BoxDecoration(
              color: const Color(0xFFF1F5F9),
              borderRadius: BorderRadius.circular(23),
            ),
            child: TextField(
              controller: _searchCtrl,
              onChanged: context.read<CategoryProvider>().updateSearch,
              onSubmitted: (v) {
                _debounce?.cancel();
                context.read<CategoryProvider>().updateSearch(v.trim());
              },
              decoration: InputDecoration(
                hintText: 'Tìm kiếm sản phẩm...',
                hintStyle: const TextStyle(
                  color: Color(0xFF64748B),
                  fontSize: 14,
                ),
                prefixIcon: const Icon(
                  Icons.search,
                  color: Color(0xFF64748B),
                  size: 20,
                ),
                suffixIcon: context.watch<CategoryProvider>().searchQuery.isNotEmpty
                    ? IconButton(
                        icon: const Icon(
                          Icons.close,
                          size: 16,
                          color: Color(0xFF64748B),
                        ),
                        onPressed: () {
                          _searchCtrl.clear();
                          context.read<CategoryProvider>().updateSearch('');
                        },
                      )
                    : null,
                border: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(vertical: 13),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCategoryChips() {
    if (context.watch<CategoryProvider>().categories.isEmpty) return const SizedBox(height: 8);
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.only(bottom: 12),
      child: SizedBox(
        height: 40,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: context.watch<CategoryProvider>().categories.length + 1, // +1 for "All"
          itemBuilder: (_, i) {
            if (i == 0) {
              final sel = context.watch<CategoryProvider>().selectedCategoryId == null;
              return _chip('Tất cả', sel, () => context.watch<CategoryProvider>().selectCategory(null, null));
            }
            final cat = context.watch<CategoryProvider>().categories[i - 1];
            final id = cat['category_id'] ?? cat['id'];
            final name = cat['name']?.toString() ?? '';
            final sel =
                context.watch<CategoryProvider>().selectedCategoryId != null &&
                context.watch<CategoryProvider>().selectedCategoryId.toString() == id.toString();
            return _chip(
              name,
              sel,
              () => context.watch<CategoryProvider>().selectCategory(int.tryParse(id.toString()), name),
            );
          },
        ),
      ),
    );
  }

  Widget _chip(String label, bool selected, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFE63B6F) : const Color(0xFFF1F5F9),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: selected ? const Color(0xFFE63B6F) : Colors.transparent,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: selected ? Colors.white : const Color(0xFF475569),
          ),
        ),
      ),
    );
  }

  Widget _buildBody() {
    return RefreshIndicator(
      color: const Color(0xFFE63B6F),
      onRefresh: () => context.read<CategoryProvider>().refresh(),
      child: CustomScrollView(
        controller: _scrollCtrl,
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          // Active filter pills
          if (_hasActiveFilter) SliverToBoxAdapter(child: _buildActiveBadges()),

          // Products count
          if (!context.watch<CategoryProvider>().isLoading && context.watch<CategoryProvider>().products.isNotEmpty)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
                child: Text(
                  '${context.watch<CategoryProvider>().products.length} sản phẩm${context.watch<CategoryProvider>().hasMore ? '+' : ''}',
                  style: const TextStyle(
                    fontSize: 13,
                    color: Color(0xFF64748B),
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ),

          // Loading
          if (context.watch<CategoryProvider>().isLoading && context.watch<CategoryProvider>().products.isEmpty)
            const SliverShimmerLoading(
              padding: EdgeInsets.fromLTRB(16, 12, 16, 16),
              crossAxisSpacing: 12,
              mainAxisSpacing: 12,
              childAspectRatio: 0.64,
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
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
              sliver: SliverGrid(
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 12,
                  childAspectRatio: 0.64,
                ),
                delegate: SliverChildBuilderDelegate(
                  (_, i) => _buildCard(context.watch<CategoryProvider>().products[i]),
                  childCount: context.watch<CategoryProvider>().products.length,
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
                    color: Color(0xFFE63B6F),
                    strokeWidth: 2,
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
                    '✨ Bạn đã xem hết ${context.watch<CategoryProvider>().products.length} sản phẩm',
                    style: const TextStyle(
                      color: Color(0xFF64748B),
                      fontSize: 13,
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
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
      child: Wrap(
        spacing: 8,
        runSpacing: 0,
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
        style: const TextStyle(fontSize: 12, color: Color(0xFFE63B6F)),
      ),
      backgroundColor: const Color(0xFFFFF0F3),
      deleteIcon: const Icon(Icons.close, size: 14, color: Color(0xFFE63B6F)),
      onDeleted: onRemove,
      padding: const EdgeInsets.symmetric(horizontal: 4),
      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
      side: BorderSide.none,
    );
  }

  String _sortLabel() {
    switch (context.watch<CategoryProvider>().sortBy) {
      case 'price_asc':
        return 'Giá tăng dần';
      case 'price_desc':
        return 'Giá giảm dần';
      case 'popular':
        return 'Phổ biến';
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
          const Icon(Icons.cloud_off_outlined, size: 60, color: Colors.grey),
          const SizedBox(height: 16),
          Text(
            context.watch<CategoryProvider>().errorMessage!,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.grey),
          ),
          const SizedBox(height: 16),
          ElevatedButton.icon(
            onPressed: () => context.read<CategoryProvider>().refresh(),
            icon: const Icon(Icons.refresh),
            label: const Text('Thử lại'),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFE63B6F),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmpty() {
    return Padding(
      padding: const EdgeInsets.only(top: 80),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.search_off_rounded, size: 80, color: Colors.grey.shade300),
          const SizedBox(height: 16),
          const Text(
            'Không tìm thấy sản phẩm',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: Color(0xFF64748B),
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Thử thay đổi bộ lọc hoặc từ khóa',
            style: TextStyle(color: Color(0xFF64748B)),
          ),
        ],
      ),
    );
  }

  Widget _buildCard(Map<String, dynamic> product) {
    final name = product['name']?.toString() ?? 'Sản phẩm';
    final rawPrice =
        product['min_price'] ??
        (product['lowest_price_variant'] is Map
            ? product['lowest_price_variant']['price']
            : 0);
    final imageUrl = AppConfig.productImageUrl(product);

    // Random badge for display
    final isFav =
        product['is_favorited'] == true || product['is_favorited'] == 1;

    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ProductDetailScreen(product: product),
        ),
      ),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image
            Expanded(
              child: Stack(
                children: [
                  Hero(tag: product['id'] ?? product['slug'] ?? UniqueKey().toString(), child: NetworkImageWidget(imageUrl: imageUrl, width: double.infinity, height: double.infinity, fit: BoxFit.cover, borderRadius: const BorderRadius.vertical(top: Radius.circular(18)), errorWidget: Container(color: const Color(0xFFF1F5F9), child: const Center(child: Icon(Icons.image, color: Color(0xFFCBD5E1), size: 32)))), ),
                  // Favorite button
                  Positioned(
                    top: 8,
                    right: 8,
                    child: GestureDetector(
                      onTap: () async {
                        final loggedIn = await AuthService.isLoggedIn();
                        if (!loggedIn) {
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text('Vui lòng đăng nhập!'),
                              ),
                            );
                          }
                          return;
                        }
                        try {
                          await ApiClient().dio.post(
                            '/profile/favorites/toggle',
                            data: {
                              'product_id':
                                  product['product_id'] ?? product['id'],
                            },
                          );
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text('Đã cập nhật yêu thích'),
                                duration: Duration(seconds: 1),
                              ),
                            );
                          }
                        } catch (_) {
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(
                                content: Text(
                                  'Không thể cập nhật yêu thích. Vui lòng thử lại.',
                                ),
                                backgroundColor: Colors.red,
                              ),
                            );
                          }
                        }
                      },
                      child: Container(
                        padding: const EdgeInsets.all(6),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.92),
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.08),
                              blurRadius: 4,
                            ),
                          ],
                        ),
                        child: Icon(
                          isFav ? Icons.favorite : Icons.favorite_border,
                          size: 16,
                          color: isFav ? Colors.red : const Color(0xFF64748B),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            // Info
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF0F172A),
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
                          gradient: const LinearGradient(
                            colors: [Color(0xFFE63B6F), Color(0xFFE63B6F)],
                          ),
                          borderRadius: BorderRadius.circular(9),
                        ),
                        child: const Icon(
                          Icons.add_shopping_cart_rounded,
                          size: 15,
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
}

