import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../utils/app_logger.dart';

/// Lưu trữ thông tin cache của từng danh mục / bộ lọc sản phẩm.
class _CategoryCacheEntry {
  final List<dynamic> products;
  final int currentPage;
  final bool hasMore;
  final DateTime timestamp;

  _CategoryCacheEntry({
    required this.products,
    required this.currentPage,
    required this.hasMore,
    required this.timestamp,
  });
}

class CategoryProvider extends ChangeNotifier {
  // ─── Cache Đa Tầng (Multi-Bucket Cache) ───────────────────
  // Key format: cat_{id}_sort_{sort}_p_{start}_{end}_st_{stock}_q_{query}
  final Map<String, _CategoryCacheEntry> _cache = {};
  int _activeRequestId = 0;

  // ─── Products ───────────────────────────────────────────
  List<dynamic> _products = [];
  bool _isLoading = true;
  bool _isFetchingMore = false;
  bool _hasMore = true;
  String? _errorMessage;
  int _currentPage = 1;

  // ─── Categories ─────────────────────────────────────────
  List<dynamic> _categories = [];
  int? _selectedCategoryId;
  String? _selectedCategoryName;

  // ─── Filter / Sort ──────────────────────────────────────
  String _sortBy = 'newest'; // newest | price_asc | price_desc | popular
  RangeValues _priceRange = const RangeValues(0, 50000000);
  bool _filterInStock = false;
  String _searchQuery = '';

  List<dynamic> get products => _products;
  bool get isLoading => _isLoading;
  bool get isFetchingMore => _isFetchingMore;
  bool get hasMore => _hasMore;
  String? get errorMessage => _errorMessage;

  List<dynamic> get categories => _categories;
  int? get selectedCategoryId => _selectedCategoryId;
  String? get selectedCategoryName => _selectedCategoryName;

  String get sortBy => _sortBy;
  RangeValues get priceRange => _priceRange;
  bool get filterInStock => _filterInStock;
  String get searchQuery => _searchQuery;

  /// Tạo khóa định danh duy nhất cho bộ filter hiện tại để tra cứu cache
  String _buildCacheKey({
    int? categoryId,
    String? search,
    String? sort,
    RangeValues? price,
    bool? inStock,
  }) {
    final cat = categoryId ?? _selectedCategoryId ?? 'all';
    final q = (search ?? _searchQuery).trim().toLowerCase();
    final s = sort ?? _sortBy;
    final p = price ?? _priceRange;
    final stock = inStock ?? _filterInStock;
    return 'cat_${cat}_sort_${s}_p_${p.start.toInt()}_${p.end.toInt()}_st_${stock}_q_$q';
  }

  Future<void> loadAll() async {
    // Nếu danh mục đã có sẵn thì không fetch lại
    if (_categories.isEmpty) {
      await fetchCategories();
    }
    // Nếu đã có sản phẩm hiển thị thì không fetch lại từ đầu
    if (_products.isEmpty) {
      await fetchProducts();
    }
  }

  /// Tải lại từ trang 1 (dùng cho pull-to-refresh và nút "Thử lại").
  /// Xóa cache của danh mục/bộ lọc hiện tại để buộc tải mới nhất từ server.
  Future<void> refresh() async {
    final currentKey = _buildCacheKey();
    _cache.remove(currentKey);
    _currentPage = 1;
    await fetchProducts();
  }

  /// Gỡ một filter đang áp mà giữ nguyên các filter còn lại.
  void clearSort() => applyFilters(sort: 'newest', price: _priceRange, inStock: _filterInStock);
  void clearInStock() => applyFilters(sort: _sortBy, price: _priceRange, inStock: false);
  void clearPriceRange() => applyFilters(sort: _sortBy, price: const RangeValues(0, 50000000), inStock: _filterInStock);

  Future<void> fetchCategories() async {
    if (_categories.isNotEmpty) return;

    try {
      final res = await ApiClient().dio.get('/categories');
      final data = res.data['data'] as List? ?? [];
      final roots = data.where((c) {
        final pid = c['parent_id'];
        return pid == null || pid == 0;
      }).toList();
      _categories = roots;
      notifyListeners();

      // Smart Pre-warming: Tải trước ngầm 2-3 danh mục đầu để chuyển tab mở tức thì 0ms
      _prewarmCategoryCaches(roots.take(3).toList());
    } catch (e) {
      AppLogger.error('Lỗi tải danh mục', e, 'CategoryProvider');
    }
  }

  /// Tải trước dữ liệu các danh mục phổ biến trong background
  void _prewarmCategoryCaches(List<dynamic> targetCategories) {
    for (final cat in targetCategories) {
      final id = int.tryParse((cat['category_id'] ?? cat['id'] ?? '').toString());
      if (id != null) {
        final key = _buildCacheKey(categoryId: id);
        if (!_cache.containsKey(key)) {
          _fetchProductsSilently(key, id);
        }
      }
    }
  }

  /// Tải sản phẩm từ API
  Future<void> fetchProducts({bool loadMore = false, bool isSilent = false}) async {
    final currentKey = _buildCacheKey();
    final requestId = ++_activeRequestId;

    if (loadMore) {
      _isFetchingMore = true;
      notifyListeners();
    } else if (!isSilent) {
      _isLoading = true;
      _errorMessage = null;
      _products.clear();
      _hasMore = true;
      notifyListeners();
    }

    try {
      final params = <String, dynamic>{'page': _currentPage};
      if (_selectedCategoryId != null) {
        params['category_id'] = _selectedCategoryId;
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
      bool hasMoreData = false;

      if (data is List) {
        fetched = data;
        hasMoreData = false;
      } else if (data is Map && data['data'] is List) {
        fetched = data['data'];
        final page = int.tryParse(data['page']?.toString() ?? '1') ?? 1;
        final totalPages =
            int.tryParse(data['total_pages']?.toString() ?? '1') ?? 1;
        hasMoreData = page < totalPages;
      }

      // Cập nhật vào Memory Cache
      if (loadMore) {
        final existing = _cache[currentKey]?.products ?? _products;
        final updatedList = List<dynamic>.from(existing)..addAll(fetched);
        _cache[currentKey] = _CategoryCacheEntry(
          products: updatedList,
          currentPage: _currentPage,
          hasMore: hasMoreData,
          timestamp: DateTime.now(),
        );
      } else {
        _cache[currentKey] = _CategoryCacheEntry(
          products: List<dynamic>.from(fetched),
          currentPage: 1,
          hasMore: hasMoreData,
          timestamp: DateTime.now(),
        );
      }

      // Race condition check: Chỉ hiển thị lên UI nếu user vẫn đang ở tab/filter này
      if (requestId == _activeRequestId && currentKey == _buildCacheKey()) {
        if (loadMore) {
          _products.addAll(fetched);
        } else {
          _products = List<dynamic>.from(fetched);
        }
        _hasMore = hasMoreData;
        _isLoading = false;
        _isFetchingMore = false;
        _errorMessage = null;
        notifyListeners();
      }
    } on DioException catch (e) {
      if (requestId == _activeRequestId && currentKey == _buildCacheKey()) {
        _isLoading = false;
        _isFetchingMore = false;
        if (e.response != null) {
          _errorMessage = e.response?.data['message']?.toString() ?? 'Lỗi máy chủ';
        } else {
          _errorMessage = 'Lỗi kết nối mạng';
        }
        notifyListeners();
      }
    } catch (e) {
      if (requestId == _activeRequestId && currentKey == _buildCacheKey()) {
        _isLoading = false;
        _isFetchingMore = false;
        _errorMessage = 'Lỗi không xác định: $e';
        notifyListeners();
      }
    }
  }

  /// Tải dữ liệu ngầm (Stale-While-Revalidate) mà KHÔNG xóa UI hay bật spinner loading
  Future<void> _fetchProductsSilently(String key, int? categoryId) async {
    final requestId = ++_activeRequestId;
    try {
      final params = <String, dynamic>{'page': 1};
      if (categoryId != null) params['category_id'] = categoryId;
      if (_searchQuery.isNotEmpty) params['search'] = _searchQuery;
      if (_sortBy == 'price_asc') params['sort'] = 'price_asc';
      if (_sortBy == 'price_desc') params['sort'] = 'price_desc';
      if (_sortBy == 'popular') params['sort'] = 'popular';
      if (_filterInStock) params['in_stock'] = 1;
      if (_priceRange.start > 0) params['min_price'] = _priceRange.start.toInt();
      if (_priceRange.end < 50000000) params['max_price'] = _priceRange.end.toInt();

      final res = await ApiClient().dio.get('/products', queryParameters: params);
      final data = res.data;
      List<dynamic> fetched = [];
      bool hasMoreData = false;

      if (data is List) {
        fetched = data;
        hasMoreData = false;
      } else if (data is Map && data['data'] is List) {
        fetched = data['data'];
        final page = int.tryParse(data['page']?.toString() ?? '1') ?? 1;
        final totalPages = int.tryParse(data['total_pages']?.toString() ?? '1') ?? 1;
        hasMoreData = page < totalPages;
      }

      // Cập nhật Cache
      _cache[key] = _CategoryCacheEntry(
        products: List<dynamic>.from(fetched),
        currentPage: 1,
        hasMore: hasMoreData,
        timestamp: DateTime.now(),
      );

      // Nếu user vẫn đang xem tab này thì cập nhật dữ liệu mới mượt mà
      if (requestId == _activeRequestId && key == _buildCacheKey()) {
        _products = List<dynamic>.from(fetched);
        _hasMore = hasMoreData;
        notifyListeners();
      }
    } catch (_) {
      // Revalidate ngầm gặp lỗi thì giữ nguyên cache hiện tại, không gây gián đoạn trải nghiệm
    }
  }

  void loadMore() {
    if (!_isLoading && !_isFetchingMore && _hasMore) {
      _currentPage++;
      fetchProducts(loadMore: true);
    }
  }

  /// Chuyển đổi danh mục tức thì (Instant Tab Switch):
  /// - Nếu đã có cache: Hiển thị ngay 0ms không độ trễ, không shimmer skeleton!
  /// - Nếu chưa có cache: Tải mới bình thường.
  void selectCategory(int? id, String? name) {
    if (_selectedCategoryId == id && _products.isNotEmpty) return;

    _selectedCategoryId = id;
    _selectedCategoryName = name;
    _currentPage = 1;

    final key = _buildCacheKey(categoryId: id);
    final cached = _cache[key];

    if (cached != null && cached.products.isNotEmpty) {
      // ✅ CÓ CACHE: Hiển thị tức thì 0ms!
      _products = List<dynamic>.from(cached.products);
      _currentPage = cached.currentPage;
      _hasMore = cached.hasMore;
      _isLoading = false;
      _errorMessage = null;
      notifyListeners();

      // Nếu cache quá 3 phút: Revalidate ngầm trong background
      if (DateTime.now().difference(cached.timestamp) > const Duration(minutes: 3)) {
        _fetchProductsSilently(key, id);
      }
    } else {
      // ⏳ CHƯA CÓ CACHE: Tải bình thường
      fetchProducts();
    }
  }

  void updateSearch(String query) {
    _searchQuery = query;
    _currentPage = 1;
    // Khi bắt đầu gõ tìm kiếm từ khóa, tự động chuyển về 'Tất cả' để tìm kiếm toàn sàn
    if (query.isNotEmpty && _selectedCategoryId != null) {
      _selectedCategoryId = null;
      _selectedCategoryName = null;
    }

    final key = _buildCacheKey(search: query);
    final cached = _cache[key];
    if (cached != null && cached.products.isNotEmpty) {
      _products = List<dynamic>.from(cached.products);
      _currentPage = cached.currentPage;
      _hasMore = cached.hasMore;
      _isLoading = false;
      _errorMessage = null;
      notifyListeners();
    } else {
      fetchProducts();
    }
  }

  void applyFilters({
    required String sort,
    required RangeValues price,
    required bool inStock,
  }) {
    _sortBy = sort;
    _priceRange = price;
    _filterInStock = inStock;
    _currentPage = 1;

    final key = _buildCacheKey(sort: sort, price: price, inStock: inStock);
    final cached = _cache[key];
    if (cached != null && cached.products.isNotEmpty) {
      _products = List<dynamic>.from(cached.products);
      _currentPage = cached.currentPage;
      _hasMore = cached.hasMore;
      _isLoading = false;
      _errorMessage = null;
      notifyListeners();
    } else {
      fetchProducts();
    }
  }
}
