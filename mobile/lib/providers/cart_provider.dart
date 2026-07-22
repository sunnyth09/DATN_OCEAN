import 'package:flutter/foundation.dart';

import '../models/cart_model.dart';
import '../services/api_client.dart';

/// Trạng thái tải giỏ hàng.
enum CartStatus { initial, loading, loaded, error }

/// Quản lý state giỏ hàng tập trung, thay cho việc mỗi màn hình tự fetch.
class CartProvider extends ChangeNotifier {
  final _dio = ApiClient().dio;

  Cart _cart = Cart.empty;
  CartStatus _status = CartStatus.initial;
  String? _errorMessage;
  DateTime? _lastFetchTime;

  Cart get cart => _cart;
  CartStatus get status => _status;
  String? get errorMessage => _errorMessage;
  List<CartItem> get items => _cart.items;
  int get itemCount => _cart.totalQuantity;
  bool get isLoading => _status == CartStatus.loading;

  /// Tải giỏ hàng. `silent` = true để refresh nền không hiện loading toàn màn.
  /// `force` = true để bỏ qua bộ đệm thời gian (ví dụ sau khi đặt hàng hoặc logout).
  Future<void> fetchCart({bool silent = false, bool force = false}) async {
    // Tối ưu Caching: Nếu không bắt buộc và chưa qua 2 phút thì không gọi lại API
    if (!force && _lastFetchTime != null) {
      if (DateTime.now().difference(_lastFetchTime!).inSeconds < 120) {
        return;
      }
    }

    if (!silent) {
      _status = CartStatus.loading;
      _errorMessage = null;
      notifyListeners();
    }

    try {
      final response = await _dio.get('/cart');
      final data = response.data['data'];
      _cart = data is Map
          ? Cart.fromJson(Map<String, dynamic>.from(data))
          : Cart.empty;
      _status = CartStatus.loaded;
      _lastFetchTime = DateTime.now();
    } catch (_) {
      if (!silent) {
        _status = CartStatus.error;
        _errorMessage = 'Lỗi kết nối máy chủ.';
      }
    }
    notifyListeners();
  }

  /// Cập nhật số lượng với optimistic UI: đổi ngay local, rollback nếu lỗi.
  Future<bool> updateQuantity(int cartItemId, int quantity) async {
    final index = _cart.items.indexWhere((i) => i.cartItemId == cartItemId);
    if (index == -1) return false;

    final previous = _cart.items[index];
    _replaceItem(index, previous.copyWith(quantity: quantity));

    try {
      await _dio.post('/cart/items', data: {
        'product_id': cartItemId,
        'quantity': quantity,
      });
      await fetchCart(silent: true, force: true);
      return true;
    } catch (_) {
      _replaceItem(index, previous);
      return false;
    }
  }

  /// Xoá một dòng với optimistic UI.
  Future<bool> removeItem(int cartItemId) async {
    final index = _cart.items.indexWhere((i) => i.cartItemId == cartItemId);
    if (index == -1) return false;

    final removed = _cart.items[index];
    final updated = List<CartItem>.from(_cart.items)..removeAt(index);
    _cart = Cart(items: updated, serverTotalPrice: null);
    notifyListeners();

    try {
      await _dio.delete('/cart/items/$cartItemId');
      await fetchCart(silent: true, force: true);
      return true;
    } catch (_) {
      final rollback = List<CartItem>.from(_cart.items)..insert(index, removed);
      _cart = Cart(items: rollback, serverTotalPrice: null);
      notifyListeners();
      return false;
    }
  }

  /// Xoá sạch state cục bộ (dùng khi đăng xuất).
  void clearCart() {
    _cart = Cart.empty;
    _status = CartStatus.initial;
    _errorMessage = null;
    _lastFetchTime = null;
    notifyListeners();
  }

  void _replaceItem(int index, CartItem item) {
    final updated = List<CartItem>.from(_cart.items)..[index] = item;
    // Tự cộng dồn total sau khi đổi local (bỏ giá server cũ đã lệch).
    _cart = Cart(items: updated, serverTotalPrice: null);
    notifyListeners();
  }

  bool _disposed = false;

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }

  @override
  void notifyListeners() {
    if (!_disposed) {
      super.notifyListeners();
    }
  }
}
