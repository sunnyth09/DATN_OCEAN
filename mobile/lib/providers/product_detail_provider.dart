import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../services/api_client.dart';

class ProductDetailProvider extends ChangeNotifier {
  Map<String, dynamic> _product = {};
  bool _isLoadingDetails = true;
  
  List<dynamic> _comments = [];
  bool _isLoadingComments = true;
  
  List<dynamic> _relatedProducts = [];
  
  String _selectedColor = '';
  String _selectedSize = '';

  Map<String, dynamic> get product => _product;
  bool get isLoadingDetails => _isLoadingDetails;
  
  List<dynamic> get comments => _comments;
  bool get isLoadingComments => _isLoadingComments;
  
  List<dynamic> get relatedProducts => _relatedProducts;
  
  String get selectedColor => _selectedColor;
  String get selectedSize => _selectedSize;

  Future<void> fetchProductData(Map<String, dynamic> initialData) async {
    _product = initialData;
    _isLoadingDetails = true;
    _isLoadingComments = true;
    _comments = [];
    _relatedProducts = [];
    _selectedColor = '';
    _selectedSize = '';
    notifyListeners();

    await Future.wait([
      fetchProductDetails(),
      fetchComments(),
      fetchRelatedProducts(),
    ]);
  }

  Future<void> fetchProductDetails() async {
    try {
      final slug = _product['slug'];
      final id = _product['id'] ?? _product['product_id'];

      Response res;
      if (slug != null && slug.toString().isNotEmpty) {
        res = await ApiClient().dio.get('/products/slug/$slug');
      } else if (id != null) {
        res = await ApiClient().dio.get('/products/$id');
      } else {
        throw Exception('Không có ID hoặc Slug sản phẩm');
      }

      if (res.data is Map<String, dynamic>) {
        _product = res.data['data'] ?? res.data;
      }
      
      final variants = _product['variants'] as List<dynamic>? ?? [];
      if (variants.isNotEmpty) {
        List<String> colors = [];
        List<String> sizes = [];
        for (var v in variants) {
          if (v['color'] != null && !colors.contains(v['color'].toString())) {
            colors.add(v['color'].toString());
          }
          if (v['size'] != null && !sizes.contains(v['size'].toString())) {
            sizes.add(v['size'].toString());
          }
        }
        if (colors.isNotEmpty) _selectedColor = colors.first;
        if (sizes.isNotEmpty) _selectedSize = sizes.first;
      }
    } catch (e) {
      debugPrint('Lỗi tải thông tin sản phẩm: $e');
    } finally {
      _isLoadingDetails = false;
      notifyListeners();
    }
  }

  Future<void> fetchComments() async {
    try {
      final id = _product['product_id'] ?? _product['id'];
      if (id == null) return;
      final res = await ApiClient().dio.get('/products/$id/comments');
      _comments = res.data['data'] ?? [];
    } catch (_) {
    } finally {
      _isLoadingComments = false;
      notifyListeners();
    }
  }

  Future<void> fetchRelatedProducts() async {
    try {
      final slug = _product['slug'];
      final id = _product['id'] ?? _product['product_id'];
      if (slug == null && id == null) return;
      
      final endpoint = slug != null && slug.toString().isNotEmpty
          ? '/products/$slug/related'
          : '/products/$id/related';
      final res = await ApiClient().dio.get(endpoint);
      if (res.data['status'] == 'success') {
        _relatedProducts = res.data['data'] ?? [];
        notifyListeners();
      }
    } catch (_) {}
  }
  
  void selectColor(String color) {
    _selectedColor = color;
    notifyListeners();
  }
  
  void selectSize(String size) {
    _selectedSize = size;
    notifyListeners();
  }
}
