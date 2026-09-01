import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../utils/app_logger.dart';
import '../config/app_config.dart';
import '../services/api_client.dart';
import '../utils/format_utils.dart';

/// Provider quản lý toàn bộ trạng thái, dữ liệu chi tiết, biến thể, đánh giá, mã giảm giá và flash sale của Sản Phẩm
class ProductDetailProvider extends ChangeNotifier {
  Map<String, dynamic> _product = {};
  bool _isLoadingDetails = true;

  List<dynamic> _comments = [];
  int _totalComments = 0;
  bool _isLoadingComments = true;

  List<dynamic> _relatedProducts = [];
  List<dynamic> _coupons = [];

  Map<String, dynamic>? _flashSaleData;
  bool _isFlashSaleActive = false;
  DateTime? _flashSaleEndTime;

  String _selectedColor = '';
  String _selectedSize = '';
  int _quantity = 1;
  int _selectedImageIndex = 0;

  // ── GETTERS CƠ BẢN ──
  Map<String, dynamic> get product => _product;
  bool get isLoadingDetails => _isLoadingDetails;

  List<dynamic> get comments => _comments;
  int get totalComments => _totalComments > 0 ? _totalComments : _comments.length;
  bool get isLoadingComments => _isLoadingComments;

  List<dynamic> get relatedProducts => _relatedProducts;
  List<dynamic> get coupons => _coupons;

  Map<String, dynamic>? get flashSaleData => _flashSaleData;
  bool get isFlashSaleActive => _isFlashSaleActive;
  DateTime? get flashSaleEndTime => _flashSaleEndTime;

  String get selectedColor => _selectedColor;
  String get selectedSize => _selectedSize;
  int get quantity => _quantity;
  int get selectedImageIndex => _selectedImageIndex;

  // ── GETTERS TIỆN ÍCH & TÍNH TOÁN ──

  int? get productId {
    final id = _product['id'] ?? _product['product_id'];
    return id != null ? int.tryParse(id.toString()) : null;
  }

  String get productName => _product['name']?.toString() ?? '';
  String get productSlug => _product['slug']?.toString() ?? '';

  /// Danh sách tất cả các biến thể
  List<dynamic> get variants => _product['variants'] is List ? _product['variants'] as List : [];

  /// Danh sách tất cả các màu sắc hiện có
  List<String> get availableColors {
    final colors = <String>[];
    for (var v in variants) {
      if (v is Map && v['color'] != null) {
        final c = v['color'].toString().trim();
        if (c.isNotEmpty && !colors.contains(c)) {
          colors.add(c);
        }
      }
    }
    return colors;
  }

  /// Danh sách tất cả các kích cỡ hiện có
  List<String> get availableSizes {
    final sizes = <String>[];
    for (var v in variants) {
      if (v is Map && v['size'] != null) {
        final s = v['size'].toString().trim();
        if (s.isNotEmpty && !sizes.contains(s)) {
          sizes.add(s);
        }
      }
    }
    return sizes;
  }

  /// Danh sách kích cỡ phù hợp theo màu sắc đang chọn
  List<String> get availableSizesForSelectedColor {
    if (_selectedColor.isEmpty) return availableSizes;
    final sizes = <String>[];
    for (var v in variants) {
      if (v is Map && v['color']?.toString() == _selectedColor && v['size'] != null) {
        final s = v['size'].toString().trim();
        if (s.isNotEmpty && !sizes.contains(s)) {
          sizes.add(s);
        }
      }
    }
    return sizes.isNotEmpty ? sizes : availableSizes;
  }

  /// Biến thể đang được chọn chính xác theo (Color, Size)
  Map<String, dynamic>? get selectedVariant {
    if (variants.isEmpty) return null;
    for (var v in variants) {
      if (v is! Map<String, dynamic>) continue;
      final matchColor = _selectedColor.isEmpty || v['color']?.toString() == _selectedColor;
      final matchSize = _selectedSize.isEmpty || v['size']?.toString() == _selectedSize;
      if (matchColor && matchSize) {
        return v;
      }
    }
    return variants.first is Map<String, dynamic> ? (variants.first as Map<String, dynamic>) : null;
  }

  /// Giá bán hiện tại (Ưu tiên Flash Sale -> Giá biến thể -> Giá sản phẩm gốc)
  num get currentPrice {
    if (_isFlashSaleActive && _flashSaleData != null) {
      final fsPrice = _flashSaleData!['flash_sale_price'] ?? _flashSaleData!['flash_price'] ?? _flashSaleData!['price'];
      if (fsPrice != null) return FormatUtils.parseNum(fsPrice);
    }
    final variant = selectedVariant;
    if (variant != null && variant['price'] != null) {
      return FormatUtils.parseNum(variant['price']);
    }
    return FormatUtils.parseNum(_product['price'] ?? _product['sale_price'] ?? 0);
  }

  /// Giá gốc trước khi giảm
  num get originalPrice {
    final variant = selectedVariant;
    if (variant != null && variant['original_price'] != null) {
      return FormatUtils.parseNum(variant['original_price']);
    }
    return FormatUtils.parseNum(_product['original_price'] ?? _product['regular_price'] ?? _product['price'] ?? 0);
  }

  /// Tồn kho hiện tại của sản phẩm / biến thể đang chọn
  int get currentStock {
    final variant = selectedVariant;
    if (variant != null && variant['stock_quantity'] != null) {
      return int.tryParse(variant['stock_quantity'].toString()) ?? 0;
    }
    if (_product['stock_quantity'] != null) {
      return int.tryParse(_product['stock_quantity'].toString()) ?? 0;
    }
    return 999;
  }

  /// Kiểm tra sản phẩm đã hết hàng chưa
  bool get isOutOfStock => currentStock <= 0;

  /// Điểm đánh giá trung bình (1.0 -> 5.0)
  double get ratingAvg {
    if (_product['rating'] != null) {
      final r = double.tryParse(_product['rating'].toString());
      if (r != null && r > 0) return r;
    }
    if (_product['rating_avg'] != null) {
      final r = double.tryParse(_product['rating_avg'].toString());
      if (r != null && r > 0) return r;
    }
    if (_comments.isNotEmpty) {
      double total = 0;
      int count = 0;
      for (var c in _comments) {
        if (c is Map && c['rating'] != null) {
          total += FormatUtils.parseNum(c['rating']);
          count++;
        }
      }
      if (count > 0) return total / count;
    }
    return 5.0;
  }

  /// Danh sách hình ảnh của sản phẩm (Bao gồm ảnh đại diện, ảnh biến thể, gallery)
  List<String> get productImages {
    final images = <String>[];

    // 1. Ảnh chính sản phẩm
    final mainImg = AppConfig.productImageUrl(_product);
    if (mainImg.isNotEmpty && !images.contains(mainImg)) {
      images.add(mainImg);
    }

    // 2. Ảnh từ danh sách galleries
    if (_product['galleries'] is List) {
      for (var g in _product['galleries']) {
        final gUrl = g is Map ? (g['image_url'] ?? g['image'] ?? g['url'])?.toString() : g?.toString();
        if (gUrl != null && gUrl.isNotEmpty) {
          final fullUrl = AppConfig.imageUrl(gUrl);
          if (!images.contains(fullUrl)) images.add(fullUrl);
        }
      }
    }

    // 3. Ảnh từ các biến thể (variants)
    for (var v in variants) {
      if (v is Map && v['image_url'] != null && v['image_url'].toString().isNotEmpty) {
        final vUrl = AppConfig.imageUrl(v['image_url'].toString());
        if (!images.contains(vUrl)) images.add(vUrl);
      }
    }

    return images.isNotEmpty ? images : [mainImg];
  }

  // ── CÁC HÀM NẠP DỮ LIỆU TỪ SERVER ──

  /// Khởi tạo và nạp toàn bộ dữ liệu chi tiết sản phẩm
  Future<void> fetchProductData(Map<String, dynamic> initialData) async {
    _product = Map<String, dynamic>.from(initialData);
    _isLoadingDetails = true;
    _isLoadingComments = true;
    _comments = [];
    _totalComments = 0;
    _relatedProducts = [];
    _coupons = [];
    _selectedColor = '';
    _selectedSize = '';
    _quantity = 1;
    _selectedImageIndex = 0;

    // Kiểm tra flash sale sơ bộ từ initialData
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

    // 1. Tải chi tiết sản phẩm trước để đảm bảo có đầy đủ ID và Slug
    await fetchProductDetails();

    // 2. Tải song song các dữ liệu liên quan (Đánh giá, SP liên quan, Voucher, Flash sale)
    await Future.wait([
      fetchComments(),
      fetchRelatedProducts(),
      fetchCoupons(),
      checkActiveFlashSale(),
    ]);
  }

  /// Nạp thông tin chi tiết đầy đủ của sản phẩm
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
        final rawData = res.data['data'] ?? res.data;
        if (rawData is Map<String, dynamic>) {
          // Merge dữ liệu an toàn để không mất initialData
          _product = {..._product, ...rawData};
        }
      }

      // Tự động chọn biến thể đầu tiên
      final colors = availableColors;
      final sizes = availableSizes;
      if (colors.isNotEmpty && _selectedColor.isEmpty) {
        _selectedColor = colors.first;
      }
      if (sizes.isNotEmpty && _selectedSize.isEmpty) {
        _selectedSize = sizes.first;
      }
    } catch (e) {
      AppLogger.debug('Lỗi tải thông tin chi tiết sản phẩm: $e');
    } finally {
      _isLoadingDetails = false;
      notifyListeners();
    }
  }

  /// Nạp danh sách đánh giá / nhận xét của sản phẩm
  Future<void> fetchComments({int? productId}) async {
    try {
      _isLoadingComments = true;
      notifyListeners();

      final id = productId ?? _product['product_id'] ?? _product['id'];
      if (id == null) {
        _comments = [];
        _totalComments = 0;
        return;
      }

      final res = await ApiClient().dio.get('/products/$id/comments');
      if (res.data != null && res.data['data'] != null) {
        final rawData = res.data['data'];
        if (rawData is List) {
          _comments = List<dynamic>.from(rawData);
          _totalComments = _comments.length;
        } else if (rawData is Map) {
          if (rawData['data'] is List) {
            _comments = List<dynamic>.from(rawData['data']);
          } else {
            _comments = [];
          }
          _totalComments = int.tryParse(rawData['total']?.toString() ?? '') ?? _comments.length;
        } else {
          _comments = [];
          _totalComments = 0;
        }
      } else {
        _comments = [];
        _totalComments = 0;
      }
    } catch (e) {
      AppLogger.debug('Lỗi tải đánh giá sản phẩm: $e');
      _comments = [];
      _totalComments = 0;
    } finally {
      _isLoadingComments = false;
      notifyListeners();
    }
  }

  /// Nạp danh sách sản phẩm liên quan
  Future<void> fetchRelatedProducts() async {
    try {
      final slug = _product['slug'];
      final id = _product['id'] ?? _product['product_id'];
      if (slug == null && id == null) return;

      final endpoint = (slug != null && slug.toString().isNotEmpty)
          ? '/products/$slug/related'
          : '/products/$id/related';
      final res = await ApiClient().dio.get(endpoint);
      if (res.data != null) {
        final list = res.data['data'] ?? res.data;
        if (list is List) {
          _relatedProducts = list;
          notifyListeners();
        }
      }
    } catch (_) {}
  }

  /// Nạp danh sách mã giảm giá công khai
  Future<void> fetchCoupons() async {
    try {
      final res = await ApiClient().dio.get('/coupons/public');
      if (res.data != null) {
        final list = res.data['data'] ?? res.data;
        if (list is List) {
          _coupons = list;
          notifyListeners();
        }
      }
    } catch (_) {}
  }

  /// Kiểm tra chiến dịch Flash Sale đang diễn ra
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
        if (fs is! Map) continue;
        final fsProdId = fs['product_id'] ?? fs['product']?['id'] ?? fs['product']?['product_id'];
        final fsSlug = fs['slug'] ?? fs['product']?['slug'];
        if ((currentId != null && fsProdId != null && fsProdId.toString() == currentId.toString()) ||
            (currentSlug != null && fsSlug != null && fsSlug == currentSlug)) {
          _flashSaleData = Map<String, dynamic>.from(fs);
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

  // ── CÁC HÀM TƯƠNG TÁC NGƯỜI DÙNG ──

  void selectColor(String color) {
    _selectedColor = color;
    // Kiểm tra xem size hiện tại có phù hợp với màu mới không
    final validSizes = availableSizesForSelectedColor;
    if (validSizes.isNotEmpty && !validSizes.contains(_selectedSize)) {
      _selectedSize = validSizes.first;
    }
    notifyListeners();
  }

  void selectSize(String size) {
    _selectedSize = size;
    notifyListeners();
  }

  void setSelectedImageIndex(int index) {
    _selectedImageIndex = index;
    notifyListeners();
  }

  void setQuantity(int q) {
    final max = currentStock > 0 ? currentStock : 1;
    _quantity = q.clamp(1, max);
    notifyListeners();
  }

  void incrementQuantity() {
    setQuantity(_quantity + 1);
  }

  void decrementQuantity() {
    if (_quantity > 1) {
      setQuantity(_quantity - 1);
    }
  }

  void reset() {
    _product = {};
    _comments = [];
    _totalComments = 0;
    _relatedProducts = [];
    _coupons = [];
    _flashSaleData = null;
    _isFlashSaleActive = false;
    _flashSaleEndTime = null;
    _selectedColor = '';
    _selectedSize = '';
    _quantity = 1;
    _selectedImageIndex = 0;
    _isLoadingDetails = true;
    _isLoadingComments = true;
    notifyListeners();
  }
}
