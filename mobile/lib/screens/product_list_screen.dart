import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';

import '../config/app_theme.dart';
import '../providers/cart_provider.dart';
import '../services/api_client.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/product_card.dart';
import '../widgets/shimmer_loading.dart';

class ProductListScreen extends StatefulWidget {
  final int? categoryId;
  final String? categoryName;
  final String? searchQuery;

  const ProductListScreen({
    super.key,
    this.categoryId,
    this.categoryName,
    this.searchQuery,
  });

  @override
  State<ProductListScreen> createState() => _ProductListScreenState();
}

class _ProductListScreenState extends State<ProductListScreen> {
  List<dynamic> products = [];
  bool isLoading = true;
  bool isFetchingMore = false;
  bool hasMore = true;
  String? errorMessage;
  int currentPage = 1;
  int totalCount = 0;

  String currentSearch = '';
  late TextEditingController _searchCtrl;
  Timer? _debounce;
  final ScrollController _scrollController = ScrollController();

  // Filter & Sort State
  String _sortBy = 'popular'; // 'popular' | 'newest' | 'price_asc' | 'price_desc'
  RangeValues _priceRange = const RangeValues(0, 50000000);
  bool _filterInStock = false;

  @override
  void initState() {
    super.initState();
    currentSearch = widget.searchQuery ?? '';
    _searchCtrl = TextEditingController(text: currentSearch);

    _scrollController.addListener(_onScroll);
    fetchProducts();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _debounce?.cancel();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 250) {
      if (!isLoading && !isFetchingMore && hasMore) {
        currentPage++;
        fetchProducts(loadMore: true);
      }
    }
  }

  void _onSearchChanged(String text) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      if (mounted) {
        setState(() {
          currentSearch = text.trim();
          currentPage = 1;
          hasMore = true;
        });
        fetchProducts();
      }
    });
  }

  Future<void> fetchProducts({bool loadMore = false}) async {
    if (!mounted) return;

    setState(() {
      if (loadMore) {
        isFetchingMore = true;
      } else {
        isLoading = true;
        errorMessage = null;
        products.clear();
      }
    });

    try {
      final Map<String, dynamic> params = {'page': currentPage};
      if (widget.categoryId != null) {
        params['category_id'] = widget.categoryId;
      }
      if (currentSearch.isNotEmpty) {
        params['search'] = currentSearch;
      }

      // Sort
      if (_sortBy == 'price_asc') params['sort'] = 'price_asc';
      if (_sortBy == 'price_desc') params['sort'] = 'price_desc';
      if (_sortBy == 'popular') params['sort'] = 'popular';
      if (_sortBy == 'newest') params['sort'] = 'newest';

      // Filters
      if (_filterInStock) params['in_stock'] = 1;
      if (_priceRange.start > 0) {
        params['min_price'] = _priceRange.start.toInt();
      }
      if (_priceRange.end < 50000000) {
        params['max_price'] = _priceRange.end.toInt();
      }

      final response = await ApiClient().dio.get(
        '/products',
        queryParameters: params,
      );

      if (response.statusCode == 200) {
        final data = response.data;
        List<dynamic> fetched = [];

        if (data is List) {
          fetched = data;
          hasMore = false;
          totalCount = fetched.length;
        } else if (data['data'] is List) {
          fetched = data['data'];
          totalCount = int.tryParse(data['total']?.toString() ?? '0') ?? fetched.length;
          final page = int.tryParse(data['page']?.toString() ?? '1') ?? 1;
          final totalPages = int.tryParse(data['total_pages']?.toString() ?? '1') ?? 1;
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
      } else {
        if (mounted) {
          setState(() {
            errorMessage = 'Lỗi truy xuất (${response.statusCode})';
            isLoading = false;
            isFetchingMore = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          errorMessage = 'Không thể kết nối đến máy chủ';
          isLoading = false;
          isFetchingMore = false;
          if (loadMore) currentPage--;
        });
      }
    }
  }

  void _onSortChanged(String newSort) {
    if (_sortBy == newSort) return;
    HapticFeedback.selectionClick();
    setState(() {
      _sortBy = newSort;
      currentPage = 1;
      hasMore = true;
    });
    fetchProducts();
  }

  void _togglePriceSort() {
    HapticFeedback.selectionClick();
    setState(() {
      if (_sortBy == 'price_asc') {
        _sortBy = 'price_desc';
      } else {
        _sortBy = 'price_asc';
      }
      currentPage = 1;
      hasMore = true;
    });
    fetchProducts();
  }

  bool get _hasActiveFilters =>
      _filterInStock || _priceRange.start > 0 || _priceRange.end < 50000000;

  void _clearAllFilters() {
    setState(() {
      _priceRange = const RangeValues(0, 50000000);
      _filterInStock = false;
      _sortBy = 'popular';
      currentPage = 1;
    });
    fetchProducts();
  }

  void _showFilterSheet() {
    RangeValues tempRange = _priceRange;
    bool tempInStock = _filterInStock;
    String tempSort = _sortBy;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) {
          final currencyFormatter = NumberFormat('#,###', 'vi_VN');

          return Container(
            padding: EdgeInsets.only(
              bottom: MediaQuery.of(ctx).viewInsets.bottom,
            ),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 42,
                      height: 4.5,
                      decoration: BoxDecoration(
                        color: Colors.grey.shade300,
                        borderRadius: BorderRadius.circular(3),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
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
                        onPressed: () {
                          setSheetState(() {
                            tempRange = const RangeValues(0, 50000000);
                            tempInStock = false;
                            tempSort = 'popular';
                          });
                        },
                        child: const Text(
                          'Thiết lập lại',
                          style: TextStyle(
                            color: AppColors.primary,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const Divider(height: 24, color: Color(0xFFF1F5F9)),

                  // Sắp xếp
                  const Text(
                    'Sắp xếp theo',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _buildRadioChip('Phổ biến', 'popular', tempSort, (val) {
                        setSheetState(() => tempSort = val);
                      }),
                      _buildRadioChip('Mới nhất', 'newest', tempSort, (val) {
                        setSheetState(() => tempSort = val);
                      }),
                      _buildRadioChip('Giá thấp đến cao', 'price_asc', tempSort, (val) {
                        setSheetState(() => tempSort = val);
                      }),
                      _buildRadioChip('Giá cao đến thấp', 'price_desc', tempSort, (val) {
                        setSheetState(() => tempSort = val);
                      }),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Tình trạng hàng
                  const Text(
                    'Tình trạng sản phẩm',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 8),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text(
                      'Chỉ hiển thị hàng còn trong kho',
                      style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFF334155)),
                    ),
                    value: tempInStock,
                    activeThumbColor: AppColors.primary,
                    onChanged: (val) => setSheetState(() => tempInStock = val),
                  ),
                  const SizedBox(height: 16),

                  // Khoảng giá
                  const Text(
                    'Khoảng giá (VNĐ)',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        '${currencyFormatter.format(tempRange.start.toInt())} đ',
                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.primary),
                      ),
                      Text(
                        tempRange.end >= 50000000
                            ? '50.000.000+ đ'
                            : '${currencyFormatter.format(tempRange.end.toInt())} đ',
                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.primary),
                      ),
                    ],
                  ),
                  RangeSlider(
                    values: tempRange,
                    min: 0,
                    max: 50000000,
                    divisions: 50,
                    activeColor: AppColors.primary,
                    inactiveColor: const Color(0xFFE2E8F0),
                    onChanged: (values) => setSheetState(() => tempRange = values),
                  ),

                  // Preset chips
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _buildPresetPriceChip('< 500K', const RangeValues(0, 500000), tempRange, (val) {
                        setSheetState(() => tempRange = val);
                      }),
                      _buildPresetPriceChip('500K - 1Tr', const RangeValues(500000, 1000000), tempRange, (val) {
                        setSheetState(() => tempRange = val);
                      }),
                      _buildPresetPriceChip('1Tr - 3Tr', const RangeValues(1000000, 3000000), tempRange, (val) {
                        setSheetState(() => tempRange = val);
                      }),
                      _buildPresetPriceChip('> 3Tr', const RangeValues(3000000, 50000000), tempRange, (val) {
                        setSheetState(() => tempRange = val);
                      }),
                    ],
                  ),
                  const SizedBox(height: 28),

                  // Apply button
                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: ElevatedButton(
                      onPressed: () {
                        Navigator.pop(ctx);
                        setState(() {
                          _sortBy = tempSort;
                          _priceRange = tempRange;
                          _filterInStock = tempInStock;
                          currentPage = 1;
                        });
                        fetchProducts();
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
                        elevation: 0,
                      ),
                      child: const Text(
                        'Áp dụng bộ lọc',
                        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Colors.white),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildRadioChip(String label, String value, String current, Function(String) onSelect) {
    final isSelected = value == current;
    return GestureDetector(
      onTap: () => onSelect(value),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFFFF0F5) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(16),
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

  Widget _buildPresetPriceChip(String label, RangeValues range, RangeValues current, Function(RangeValues) onSelect) {
    final isSelected = range.start == current.start && range.end == current.end;
    return GestureDetector(
      onTap: () => onSelect(range),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFFFF0F5) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? AppColors.primary : const Color(0xFFE2E8F0),
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12,
            fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
            color: isSelected ? AppColors.primary : const Color(0xFF475569),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.categoryName ?? (widget.searchQuery != null ? 'Tìm: "${widget.searchQuery}"' : 'Sản phẩm');

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: PreferredSize(
        preferredSize: const Size.fromHeight(62),
        child: Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(bottom: BorderSide(color: Color(0xFFF1F5F9), width: 1)),
          ),
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              child: Row(
                children: [
                  // Back button
                  GestureDetector(
                    onTap: () {
                      HapticFeedback.lightImpact();
                      if (context.canPop()) {
                        context.pop();
                      } else {
                        context.go('/shop');
                      }
                    },
                    child: Container(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        shape: BoxShape.circle,
                        border: Border.all(color: const Color(0xFFE2E8F0), width: 0.8),
                      ),
                      child: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A), size: 20),
                    ),
                  ),

                  const SizedBox(width: 8),

                  // Search Field
                  Expanded(
                    child: Container(
                      height: 40,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: const Color(0xFFE2E8F0), width: 1),
                      ),
                      child: TextField(
                        controller: _searchCtrl,
                        onChanged: _onSearchChanged,
                        onSubmitted: (t) {
                          _debounce?.cancel();
                          setState(() {
                            currentSearch = t.trim();
                            currentPage = 1;
                            hasMore = true;
                          });
                          fetchProducts();
                        },
                        style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
                        decoration: InputDecoration(
                          hintText: widget.categoryName != null ? 'Tìm trong ${widget.categoryName}...' : 'Tìm kiếm sản phẩm...',
                          hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
                          prefixIcon: const Icon(Icons.search_rounded, color: AppColors.primary, size: 19),
                          suffixIcon: _searchCtrl.text.isNotEmpty
                              ? IconButton(
                                  icon: const Icon(Icons.close_rounded, color: Color(0xFF64748B), size: 16),
                                  onPressed: () {
                                    _searchCtrl.clear();
                                    _onSearchChanged('');
                                  },
                                )
                              : IconButton(
                                  icon: const Icon(Icons.camera_alt_outlined, color: AppColors.primary, size: 17),
                                  onPressed: () => context.push('/product-scanner'),
                                ),
                          isDense: true,
                          filled: false,
                          border: InputBorder.none,
                          contentPadding: const EdgeInsets.symmetric(vertical: 10),
                        ),
                      ),
                    ),
                  ),

                  const SizedBox(width: 8),

                  // Cart Button with Badge
                  Consumer<CartProvider>(
                    builder: (context, cart, _) {
                      return GestureDetector(
                        onTap: () {
                          HapticFeedback.lightImpact();
                          context.push('/cart');
                        },
                        child: Container(
                          width: 38,
                          height: 38,
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAFC),
                            shape: BoxShape.circle,
                            border: Border.all(color: const Color(0xFFE2E8F0), width: 0.8),
                          ),
                          child: Stack(
                            alignment: Alignment.center,
                            children: [
                              const Icon(Icons.shopping_cart_outlined, color: Color(0xFF0F172A), size: 19),
                              if (cart.itemCount > 0)
                                Positioned(
                                  top: 5,
                                  right: 5,
                                  child: Container(
                                    padding: const EdgeInsets.all(3),
                                    decoration: const BoxDecoration(
                                      color: Color(0xFFD90429),
                                      shape: BoxShape.circle,
                                    ),
                                    constraints: const BoxConstraints(minWidth: 14, minHeight: 14),
                                    child: Text(
                                      cart.itemCount > 99 ? '99+' : '${cart.itemCount}',
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 8,
                                        fontWeight: FontWeight.w900,
                                        height: 1,
                                      ),
                                      textAlign: TextAlign.center,
                                    ),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
      body: Column(
        children: [
          // Sorting & Filter bar
          _buildSortingBar(),

          // Active filter tags if applied
          if (_hasActiveFilters) _buildActiveFilterChips(),

          // Product Grid or Empty State
          Expanded(child: _buildBody(title)),
        ],
      ),
    );
  }

  // Sorting Bar Tabs
  Widget _buildSortingBar() {
    final isPriceActive = _sortBy == 'price_asc' || _sortBy == 'price_desc';

    return Container(
      height: 44,
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(bottom: BorderSide(color: Color(0xFFF1F5F9), width: 1)),
      ),
      child: Row(
        children: [
          _buildSortTab('Phổ biến', 'popular'),
          _buildSortTab('Mới nhất', 'newest'),
          GestureDetector(
            onTap: _togglePriceSort,
            behavior: HitTestBehavior.opaque,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14),
              alignment: Alignment.center,
              child: Row(
                children: [
                  Text(
                    'Giá',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: isPriceActive ? FontWeight.w800 : FontWeight.w600,
                      color: isPriceActive ? AppColors.primary : const Color(0xFF475569),
                    ),
                  ),
                  const SizedBox(width: 3),
                  Icon(
                    _sortBy == 'price_asc'
                        ? Icons.arrow_upward_rounded
                        : (_sortBy == 'price_desc' ? Icons.arrow_downward_rounded : Icons.swap_vert_rounded),
                    size: 14,
                    color: isPriceActive ? AppColors.primary : const Color(0xFF94A3B8),
                  ),
                ],
              ),
            ),
          ),
          const Spacer(),

          // Filter Button
          GestureDetector(
            onTap: _showFilterSheet,
            behavior: HitTestBehavior.opaque,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14),
              child: Row(
                children: [
                  Text(
                    'Lọc',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: _hasActiveFilters ? FontWeight.w800 : FontWeight.w600,
                      color: _hasActiveFilters ? AppColors.primary : const Color(0xFF475569),
                    ),
                  ),
                  const SizedBox(width: 4),
                  Stack(
                    children: [
                      Icon(
                        Icons.filter_list_rounded,
                        size: 18,
                        color: _hasActiveFilters ? AppColors.primary : const Color(0xFF64748B),
                      ),
                      if (_hasActiveFilters)
                        Positioned(
                          right: 0,
                          top: 0,
                          child: Container(
                            width: 6,
                            height: 6,
                            decoration: const BoxDecoration(
                              color: Color(0xFFD90429),
                              shape: BoxShape.circle,
                            ),
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSortTab(String label, String value) {
    final isSelected = _sortBy == value;
    return GestureDetector(
      onTap: () => _onSortChanged(value),
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          border: Border(
            bottom: BorderSide(
              color: isSelected ? AppColors.primary : Colors.transparent,
              width: 2.5,
            ),
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
            color: isSelected ? AppColors.primary : const Color(0xFF475569),
          ),
        ),
      ),
    );
  }

  // Active filter chips
  Widget _buildActiveFilterChips() {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.fromLTRB(14, 0, 14, 8),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            if (_filterInStock)
              _buildFilterChip('Còn hàng', () {
                setState(() => _filterInStock = false);
                fetchProducts();
              }),
            if (_priceRange.start > 0 || _priceRange.end < 50000000)
              _buildFilterChip('Giá: ${_formatPriceCompact(_priceRange.start)} - ${_formatPriceCompact(_priceRange.end)}', () {
                setState(() => _priceRange = const RangeValues(0, 50000000));
                fetchProducts();
              }),
            GestureDetector(
              onTap: _clearAllFilters,
              child: const Padding(
                padding: EdgeInsets.only(left: 6),
                child: Text(
                  'Xoá tất cả',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFilterChip(String label, VoidCallback onRemove) {
    return Container(
      margin: const EdgeInsets.only(right: 6),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3.5),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF0F5),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.2)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.primary)),
          const SizedBox(width: 4),
          GestureDetector(
            onTap: onRemove,
            child: const Icon(Icons.close_rounded, size: 13, color: AppColors.primary),
          ),
        ],
      ),
    );
  }

  String _formatPriceCompact(double val) {
    if (val >= 1000000) {
      return '${(val / 1000000).toStringAsFixed(val % 1000000 == 0 ? 0 : 1)}Tr';
    } else if (val >= 1000) {
      return '${(val / 1000).toStringAsFixed(0)}K';
    }
    return '${val.toInt()}đ';
  }

  Widget _buildBody(String title) {
    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: () async {
        currentPage = 1;
        hasMore = true;
        await fetchProducts();
      },
      child: CustomScrollView(
        controller: _scrollController,
        cacheExtent: 800,
        physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
        slivers: [
          // Banner heading with category name & count if active
          if (widget.categoryName != null)
            SliverToBoxAdapter(
              child: Container(
                margin: const EdgeInsets.fromLTRB(12, 10, 12, 4),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFF1F5F9)),
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: AppColors.primary.withValues(alpha: 0.08),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.category_rounded, size: 16, color: AppColors.primary),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        widget.categoryName!,
                        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (!isLoading && products.isNotEmpty)
                      Text(
                        '$totalCount sản phẩm',
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF64748B)),
                      ),
                  ],
                ),
              ),
            ),

          if (isLoading && products.isEmpty)
            const SliverShimmerLoading(
              padding: EdgeInsets.all(12),
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
              childAspectRatio: 0.65,
            )
          else if (errorMessage != null && products.isEmpty)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 40),
                child: AppEmptyState(
                  icon: Icons.wifi_off_rounded,
                  title: 'Không thể tải sản phẩm',
                  message: errorMessage!,
                  buttonText: 'Thử lại',
                  onAction: () => fetchProducts(),
                ),
              ),
            )
          else if (products.isEmpty)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 40),
                child: AppEmptyState(
                  icon: Icons.inventory_2_outlined,
                  title: 'Không tìm thấy sản phẩm',
                  message: _hasActiveFilters
                      ? 'Không có sản phẩm nào phù hợp với bộ lọc hiện tại. Vui lòng thử nới lỏng tiêu chí lọc.'
                      : 'Chưa có sản phẩm nào trong danh mục hoặc từ khoá này.',
                  buttonText: _hasActiveFilters ? 'Xoá bộ lọc' : 'Xem tất cả',
                  onAction: () {
                    if (_hasActiveFilters) {
                      _clearAllFilters();
                    } else {
                      _searchCtrl.clear();
                      _onSearchChanged('');
                    }
                  },
                ),
              ),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 16),
              sliver: SliverGrid(
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  crossAxisSpacing: 10,
                  mainAxisSpacing: 10,
                  childAspectRatio: 0.65,
                ),
                delegate: SliverChildBuilderDelegate(
                  (context, index) {
                    final product = products[index];
                    return ProductCard(
                      product: product is Map<String, dynamic> ? product : Map<String, dynamic>.from(product),
                    );
                  },
                  childCount: products.length,
                  addRepaintBoundaries: true,
                  addAutomaticKeepAlives: false,
                ),
              ),
            ),

          if (isFetchingMore)
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.only(bottom: 24),
                child: Center(
                  child: SizedBox(
                    width: 24,
                    height: 24,
                    child: CircularProgressIndicator(strokeWidth: 2.5, color: AppColors.primary),
                  ),
                ),
              ),
            ),

          if (!hasMore && products.length > 4)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.only(top: 8, bottom: 32),
                child: Center(
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.check_circle_rounded, size: 13, color: Color(0xFF94A3B8)),
                        const SizedBox(width: 5),
                        Text(
                          'Đã hiển thị ${products.length} sản phẩm',
                          style: const TextStyle(
                            color: Color(0xFF64748B),
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
