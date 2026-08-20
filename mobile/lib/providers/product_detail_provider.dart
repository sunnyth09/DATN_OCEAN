import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../services/api_client.dart';

class ProductDetailProvider extends ChangeNotifier {
  Map<String, dynamic> _product = {};
  bool _isLoadingDetails = true;
  
  List<dynamic> _comments = [];
  bool _isLoadingComments = true;
  
  List<dynamic> _relatedProducts = [];
  List<dynamic> _coupons = [];
  
  Map<String, dynamic>? _flashSaleData;
  bool _isFlashSaleActive = false;
  DateTime? _flashSaleEndTime;
  
  String _selectedColor = '';
  String _selectedSize = '';

  Map<String, dynamic> get product => _product;
  bool get isLoadingDetails => _isLoadingDetails;
  
  List<dynamic> get comments => _comments;
  bool get isLoadingComments => _isLoadingComments;
  
  List<dynamic> get relatedProducts => _relatedProducts;
  List<dynamic> get coupons => _coupons;
  
  Map<String, dynamic>? get flashSaleData => _flashSaleData;
  bool get isFlashSaleActive => _isFlashSaleActive;
  DateTime? get flashSaleEndTime => _flashSaleEndTime;
  
  String get selectedColor => _selectedColor;
  String get selectedSize => _selectedSize;

  Future<void> fetchProductData(Map<String, dynamic> initialData) async {
    _product = initialData;
    _isLoadingDetails = true;
    _isLoadingComments = true;
    _comments = [];
    _relatedProducts = [];
    _coupons = [];
    _selectedColor = '';
    _selectedSize = '';

    // Check if initialData carries flash_sale info
    if (initialData['flash_sale'] != null || initialData['flash_sale_price'] != null || initialData['flash_price'] != null) {
      _flashSaleData = initialData['flash_sale'] is Map<String, dynamic>
          ? initialData['flash_sale']
          : (initialData['flash_sale_item'] is Map<String, dynamic> ? initialData['flash_sale_item'] : initialData);
      _isFlashSaleActive = true;
      final endStr = _flashSaleData?['ends_at'] ?? _flashSaleData?['end_time'] ?? _flashSaleData?['end_date'];
      if (endStr != null) {
        _flashSaleEndTime = DateTime.tryParse(endStr.toString());
      }
    } else {
      _flashSaleData = null;
      _isFlashSaleActive = false;
      _flashSaleEndTime = null;
    }

    notifyListeners();

    await Future.wait([
      fetchProductDetails(),
      fetchComments(),
      fetchRelatedProducts(),
      fetchCoupons(),
      checkActiveFlashSale(),
    ]);
  }

  Future<void> checkActiveFlashSale() async {
    try {
      final res = await ApiClient().dio.get('/flash-sale');
      List<dynamic> fsList = [];
      if (res.data is List) {
        fsList = res.data;
      } else if (res.data is Map && res.data['data'] is List) {
        fsList = res.data['data'];
      }

      final currentId = _product['id'] ?? _product['product_id'];
      final currentSlug = _product['slug'];

      for (var fs in fsList) {
        final fsProdId = fs['product_id'] ?? fs['product']?['id'] ?? fs['product']?['product_id'];
        final fsSlug = fs['slug'] ?? fs['product']?['slug'];
        if ((currentId != null && fsProdId != null && fsProdId.toString() == currentId.toString()) ||
            (currentSlug != null && fsSlug != null && fsSlug == currentSlug)) {
          _flashSaleData = fs;
          _isFlashSaleActive = true;
          final endStr = fs['ends_at'] ?? fs['end_time'] ?? fs['end_date'];
          if (endStr != null) {
            _flashSaleEndTime = DateTime.tryParse(endStr.toString());
          }
          notifyListeners();
          break;
        }
      }
    } catch (_) {}
  }

  Future<void> fetchCoupons() async {
    try {
      final res = await ApiClient().dio.get('/coupons/public');
      if (res.data['status'] == 'success') {
        _coupons = res.data['data'] ?? [];
        notifyListeners();
      }
    } catch (_) {}
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
