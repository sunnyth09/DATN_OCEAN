import 'package:flutter/material.dart';
import '../services/api_client.dart';

class HomeProvider extends ChangeNotifier {
  List<dynamic> products = [];
  List<dynamic> categories = [];
  
  bool isProductsLoading = true;
  bool isCategoriesLoading = true;
  
  String? productsErrorMessage;
  String? categoriesErrorMessage;

  int currentPage = 1;
  int totalPages = 1;
  int totalProducts = 0;
  String searchQuery = '';

  Future<void> fetchProducts({bool refresh = false}) async {
    if (refresh) {
      currentPage = 1;
      products.clear();
    }
    
    isProductsLoading = true;
    productsErrorMessage = null;
    notifyListeners();

    try {
      final response = await ApiClient().get(
        '/products',
        queryParameters: {'page': currentPage, 'search': searchQuery},
      );

      final data = response.data;
      List<dynamic> fetched = [];

      if (data is List) {
        fetched = data;
        products.addAll(fetched);
        totalPages = 1;
        totalProducts = fetched.length;
      } else if (data['data'] is List) {
        fetched = data['data'];
        products.addAll(fetched);
        totalPages = data['total_pages'] ?? 1;
        totalProducts = data['total'] ?? products.length;
      }
    } catch (e) {
      productsErrorMessage = e.toString();
    } finally {
      isProductsLoading = false;
      notifyListeners();
    }
  }

  Future<void> fetchCategories() async {
    isCategoriesLoading = true;
    categoriesErrorMessage = null;
    notifyListeners();

    try {
      final response = await ApiClient().get('/categories');
      final data = response.data['data'] as List? ?? [];
      
      // Chỉ lấy danh mục cấp 1
      categories = data.where((c) {
        final pid = c['parent_id'];
        return pid == null || pid == 0;
      }).toList();
    } catch (e) {
      categoriesErrorMessage = e.toString();
    } finally {
      isCategoriesLoading = false;
      notifyListeners();
    }
  }

  void loadMoreProducts() {
    if (currentPage < totalPages && !isProductsLoading) {
      currentPage++;
      fetchProducts();
    }
  }

  void setSearchQuery(String query) {
    searchQuery = query;
    fetchProducts(refresh: true);
  }
}
