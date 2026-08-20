import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../services/api_client.dart';

class CategoryProvider extends ChangeNotifier {
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

  Future<void> loadAll() async {
    await Future.wait([fetchCategories(), fetchProducts()]);
  }

  /// Tải lại từ trang 1 (dùng cho pull-to-refresh và nút "Thử lại").
  Future<void> refresh() async {
    _currentPage = 1;
    await fetchProducts();
  }

  /// Gỡ một filter đang áp mà giữ nguyên các filter còn lại.
  void clearSort() => applyFilters(sort: 'newest', price: _priceRange, inStock: _filterInStock);
  void clearInStock() => applyFilters(sort: _sortBy, price: _priceRange, inStock: false);
  void clearPriceRange() => applyFilters(sort: _sortBy, price: const RangeValues(0, 50000000), inStock: _filterInStock);

  Future<void> fetchCategories() async {
    try {
      final res = await ApiClient().dio.get('/categories');
      final data = res.data['data'] as List? ?? [];
      final roots = data.where((c) {
        final pid = c['parent_id'];
        return pid == null || pid == 0;
      }).toList();
      _categories = roots;
      notifyListeners();
    } catch (_) {}
  }

  Future<void> fetchProducts({bool loadMore = false}) async {
    if (loadMore) {
      _isFetchingMore = true;
      notifyListeners();
    } else {
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
      if (data is List) {
        fetched = data;
        _hasMore = false;
      } else if (data['data'] is List) {
        fetched = data['data'];
        final page = int.tryParse(data['page']?.toString() ?? '1') ?? 1;
        final totalPages =
            int.tryParse(data['total_pages']?.toString() ?? '1') ?? 1;
        _hasMore = page < totalPages;
      }

      if (loadMore) {
        _products.addAll(fetched);
      } else {
        _products = fetched;
      }
      _isLoading = false;
      _isFetchingMore = false;
      notifyListeners();
    } on DioException catch (e) {
      _isLoading = false;
      _isFetchingMore = false;
      if (e.response != null) {
        _errorMessage = e.response?.data['message']?.toString() ?? 'Lỗi máy chủ';
      } else {
        _errorMessage = 'Lỗi kết nối';
      }
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      _isFetchingMore = false;
      _errorMessage = 'Lỗi không xác định: $e';
      notifyListeners();
    }
  }

  void loadMore() {
    if (!_isLoading && !_isFetchingMore && _hasMore) {
      _currentPage++;
      fetchProducts(loadMore: true);
    }
  }

  void selectCategory(int? id, String? name) {
    _selectedCategoryId = id;
    _selectedCategoryName = name;
    _currentPage = 1;
    fetchProducts();
  }

  void updateSearch(String query) {
    _searchQuery = query;
    _currentPage = 1;
    // Khi bắt đầu gõ tìm kiếm từ khóa, tự động chuyển về 'Tất cả' để tìm kiếm toàn sàn
    if (query.isNotEmpty && _selectedCategoryId != null) {
      _selectedCategoryId = null;
      _selectedCategoryName = null;
    }
    fetchProducts();
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
    fetchProducts();
  }
}
