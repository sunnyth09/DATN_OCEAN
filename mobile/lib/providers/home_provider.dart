import 'package:flutter/material.dart';
import '../services/api_client.dart';

class HomeProvider extends ChangeNotifier {
  List<dynamic> products = [];
  List<dynamic> categories = [];
  List<dynamic> flashSaleProducts = [];

  bool isInitialLoading = true;
  bool isFetchingMore = false;
  bool isCategoriesLoading = true;
  bool isFlashSaleLoading = false;
  bool hasMore = true;

  String? productsErrorMessage;
  String? categoriesErrorMessage;

  int currentPage = 1;
  int totalPages = 1;
  int totalProducts = 0;
  String searchQuery = '';

  Future<void> fetchProducts({bool refresh = false}) async {
    if (refresh) {
      currentPage = 1;
      hasMore = true;
      isInitialLoading = true;
      productsErrorMessage = null;
      notifyListeners();
    } else if (products.isEmpty) {
      isInitialLoading = true;
      productsErrorMessage = null;
      notifyListeners();
    }

    try {
      final response = await ApiClient().get(
        '/products',
        queryParameters: {
          'page': currentPage,
          if (searchQuery.isNotEmpty) 'search': searchQuery,
          'per_page': 10,
        },
      );

      final data = response.data;
      List<dynamic> fetched = [];

      if (data is List) {
        fetched = data;
        totalPages = 1;
        totalProducts = fetched.length;
        hasMore = false;
      } else if (data is Map && data['data'] is List) {
        fetched = data['data'];
        totalPages = int.tryParse((data['total_pages'] ?? data['last_page'] ?? 1).toString()) ?? 1;
        totalProducts = int.tryParse((data['total'] ?? fetched.length).toString()) ?? fetched.length;
        hasMore = currentPage < totalPages;
      }

      if (refresh || currentPage == 1) {
        products = List<dynamic>.from(fetched);
      } else {
        // Tránh trùng lặp ID sản phẩm khi append
        final existingIds = products.map((p) => p['id'] ?? p['product_id']).toSet();
        for (final item in fetched) {
          final id = item['id'] ?? item['product_id'];
          if (!existingIds.contains(id)) {
            products.add(item);
            existingIds.add(id);
          }
        }
      }
    } catch (e) {
      if (products.isEmpty) {
        productsErrorMessage = 'Không thể tải danh sách sản phẩm.';
      }
    } finally {
      isInitialLoading = false;
      isFetchingMore = false;
      notifyListeners();
    }
  }

  Future<void> loadMoreProducts() async {
    if (isInitialLoading || isFetchingMore || !hasMore) return;

    isFetchingMore = true;
    currentPage++;
    notifyListeners();

    await fetchProducts();
  }

  Future<void> fetchCategories() async {
    isCategoriesLoading = true;
    categoriesErrorMessage = null;
    notifyListeners();

    try {
      final response = await ApiClient().get('/categories');
      final data = response.data['data'] as List? ?? [];

      // Lấy danh mục cấp 1 (root categories)
      categories = data.where((c) {
        final pid = c['parent_id'];
        return pid == null || pid == 0;
      }).toList();
    } catch (e) {
      categoriesErrorMessage = 'Không thể tải danh mục.';
    } finally {
      isCategoriesLoading = false;
      notifyListeners();
    }
  }

  Future<void> fetchFlashSale() async {
    isFlashSaleLoading = true;
    notifyListeners();

    try {
      final response = await ApiClient().get('/flash-sale');
      final data = response.data;
      if (data is List) {
        flashSaleProducts = data;
      } else if (data is Map && data['data'] is List) {
        flashSaleProducts = data['data'];
      }
    } catch (_) {
      flashSaleProducts = [];
    } finally {
      isFlashSaleLoading = false;
      notifyListeners();
    }
  }

  void setSearchQuery(String query) {
    searchQuery = query.trim();
    fetchProducts(refresh: true);
  }
}
