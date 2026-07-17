import 'dart:async';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
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
  List<dynamic> products = [];
  bool isLoading = true;
  bool isFetchingMore = false;
  bool hasMore = true;
  String? errorMessage;
  int currentPage = 1;

  // ─── Categories ─────────────────────────────────────────
  List<dynamic> categories = [];
  int? selectedCategoryId;
  String? selectedCategoryName;

  // ─── Filter / Sort ──────────────────────────────────────
  String _sortBy = 'newest'; // newest | price_asc | price_desc | popular
  RangeValues _priceRange = const RangeValues(0, 50000000);
  bool _filterInStock = false;

  // ─── Search ─────────────────────────────────────────────
  final _searchCtrl = TextEditingController();
  String _searchQuery = '';
  Timer? _debounce;

  // ─── Scroll ─────────────────────────────────────────────
  final _scrollCtrl = ScrollController();

  @override
  void initState() {
    super.initState();
    _loadAll();
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
    if (_scrollCtrl.position.pixels >=
        _scrollCtrl.position.maxScrollExtent - 250) {
      if (!isLoading && !isFetchingMore && hasMore) {
        currentPage++;
        fetchProducts(loadMore: true);
      }
    }
  }

  Future<void> _loadAll() async {
    await Future.wait([fetchCategories(), fetchProducts()]);
  }

  Future<void> fetchCategories() async {
    try {
      final res = await ApiClient().dio.get('/categories');
      final data = res.data['data'] as List? ?? [];
      final roots = data.where((c) {
        final pid = c['parent_id'];
        return pid == null || pid == 0;
      }).toList();
      if (mounted) setState(() => categories = roots);
    } catch (_) {}
  }

  Future<void> fetchProducts({bool loadMore = false}) async {
    if (!mounted) return;
    if (loadMore) {
      setState(() => isFetchingMore = true);
    } else {
      setState(() {
        isLoading = true;
        errorMessage = null;
        products.clear();
        hasMore = true;
      });
    }

    try {
      final params = <String, dynamic>{'page': currentPage};
      if (selectedCategoryId != null) {
        params['category_id'] = selectedCategoryId;
      }
      if (_searchQuery.isNotEmpty) params['search'] = _searchQuery;
      if (_sortBy == 'price_asc') params['sort'] = 'price_asc';
      if (_sortBy == 'price_desc') params['sort'] = 'price_desc';
      if (_sortBy == 'popular') params['sort'] = 'popular';
      if (_filterInStock) params['in_stock'] = 1;
      if (_priceRange.start > 0) {
        params['min_price'] = _priceRange.start.toInt();
      }
      if (_priceRange.end < 50000000) {
        params['max_price'] = _priceRange.end.toInt();
      }

      final res = await ApiClient().dio.get(
        '/products',
        queryParameters: params,
      );
      final data = res.data;
      List<dynamic> fetched = [];
      if (data is List) {
        fetched = data;
        hasMore = false;
      } else if (data['data'] is List) {
        fetched = data['data'];
        final page = int.tryParse(data['page']?.toString() ?? '1') ?? 1;
        final totalPages =
            int.tryParse(data['total_pages']?.toString() ?? '1') ?? 1;
        hasMore = page < totalPages;
      }

      if (mounted) {
        setState(() {
          if (loadMore) {
            products.addAll(fetched);
          } else {
            products = fetched;
          }
          isLoading = false;
          isFetchingMore = false;
        });
      }
    } on DioException catch (e) {
      if (mounted) {
        setState(() {
          errorMessage = e.response?.data?['message'] ?? 'Lỗi kết nối';
          isLoading = false;
          isFetchingMore = false;
          if (loadMore) currentPage--;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          errorMessage = 'Lỗi kết nối máy chủ';
          isLoading = false;
          isFetchingMore = false;
          if (loadMore) currentPage--;
        });
      }
    }
  }

  void _resetAndFetch() {
    currentPage = 1;
    fetchProducts();
  }

  void _onSearchChanged(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      setState(() => _searchQuery = v.trim());
      _resetAndFetch();
    });
  }

  void _selectCategory(int? id, String? name) {
    setState(() {
      selectedCategoryId = id;
      selectedCategoryName = name;
    });
    _resetAndFetch();
  }

  void _showFilterSheet() {
    String tmpSort = _sortBy;
    RangeValues tmpPrice = _priceRange;
    bool tmpInStock = _filterInStock;

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
                      setState(() {
                        _sortBy = tmpSort;
                        _priceRange = tmpPrice;
                        _filterInStock = tmpInStock;
                      });
                      _resetAndFetch();
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
      selectedCategoryId != null ||
      _sortBy != 'newest' ||
      _filterInStock ||
      _priceRange.start > 0 ||
      _priceRange.end < 50000000;

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
                    selectedCategoryName != null
                        ? selectedCategoryName!
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
              onChanged: _onSearchChanged,
              onSubmitted: (v) {
                _debounce?.cancel();
                setState(() => _searchQuery = v.trim());
                _resetAndFetch();
              },
              decoration: InputDecoration(
                hintText: 'Tìm kiếm sản phẩm...',
                hintStyle: const TextStyle(
                  color: Color(0xFF94A3B8),
                  fontSize: 14,
                ),
                prefixIcon: const Icon(
                  Icons.search,
                  color: Color(0xFF94A3B8),
                  size: 20,
                ),
                suffixIcon: _searchQuery.isNotEmpty
                    ? IconButton(
                        icon: const Icon(
                          Icons.close,
                          size: 16,
                          color: Color(0xFF94A3B8),
                        ),
                        onPressed: () {
                          _searchCtrl.clear();
                          setState(() => _searchQuery = '');
                          _resetAndFetch();
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
    if (categories.isEmpty) return const SizedBox(height: 8);
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.only(bottom: 12),
      child: SizedBox(
        height: 40,
        child: ListView.builder(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          itemCount: categories.length + 1, // +1 for "All"
          itemBuilder: (_, i) {
            if (i == 0) {
              final sel = selectedCategoryId == null;
              return _chip('Tất cả', sel, () => _selectCategory(null, null));
            }
            final cat = categories[i - 1];
            final id = cat['category_id'] ?? cat['id'];
            final name = cat['name']?.toString() ?? '';
            final sel =
                selectedCategoryId != null &&
                selectedCategoryId.toString() == id.toString();
            return _chip(
              name,
              sel,
              () => _selectCategory(int.tryParse(id.toString()), name),
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
      onRefresh: () async {
        currentPage = 1;
        await fetchProducts();
      },
      child: CustomScrollView(
        controller: _scrollCtrl,
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          // Active filter pills
          if (_hasActiveFilter) SliverToBoxAdapter(child: _buildActiveBadges()),

          // Products count
          if (!isLoading && products.isNotEmpty)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
                child: Text(
                  '${products.length} sản phẩm${hasMore ? '+' : ''}',
                  style: const TextStyle(
                    fontSize: 13,
                    color: Color(0xFF64748B),
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ),

          // Loading
          if (isLoading && products.isEmpty) const SliverShimmerLoading(),

          // Error
          if (errorMessage != null && products.isEmpty)
            SliverToBoxAdapter(child: _buildError()),

          // Empty
          if (!isLoading && products.isEmpty && errorMessage == null)
            SliverToBoxAdapter(child: _buildEmpty()),

          // Grid
          if (products.isNotEmpty)
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
                  (_, i) => _buildCard(products[i]),
                  childCount: products.length,
                ),
              ),
            ),

          // Load more indicator
          if (isFetchingMore)
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

          if (!hasMore && products.length > 4)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 24),
                child: Center(
                  child: Text(
                    '✨ Bạn đã xem hết ${products.length} sản phẩm',
                    style: const TextStyle(
                      color: Color(0xFF94A3B8),
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
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
      child: Wrap(
        spacing: 8,
        runSpacing: 0,
        children: [
          if (_sortBy != 'newest')
            _badge(
              _sortLabel(),
              onRemove: () {
                setState(() => _sortBy = 'newest');
                _resetAndFetch();
              },
            ),
          if (_filterInStock)
            _badge(
              'Còn hàng',
              onRemove: () {
                setState(() => _filterInStock = false);
                _resetAndFetch();
              },
            ),
          if (_priceRange.start > 0 || _priceRange.end < 50000000)
            _badge(
              '${_fmtPrice(_priceRange.start)}–${_fmtPrice(_priceRange.end)} đ',
              onRemove: () {
                setState(() => _priceRange = const RangeValues(0, 50000000));
                _resetAndFetch();
              },
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
    switch (_sortBy) {
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
            errorMessage!,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.grey),
          ),
          const SizedBox(height: 16),
          ElevatedButton.icon(
            onPressed: _resetAndFetch,
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
            style: TextStyle(color: Color(0xFF94A3B8)),
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
                  NetworkImageWidget(
                    imageUrl: imageUrl,
                    width: double.infinity,
                    height: double.infinity,
                    fit: BoxFit.cover,
                    borderRadius: const BorderRadius.vertical(
                      top: Radius.circular(18),
                    ),
                    errorWidget: Container(
                      color: const Color(0xFFF1F5F9),
                      child: const Center(
                        child: Icon(
                          Icons.image,
                          color: Color(0xFFCBD5E1),
                          size: 32,
                        ),
                      ),
                    ),
                  ),
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
                          color: isFav ? Colors.red : const Color(0xFF94A3B8),
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
