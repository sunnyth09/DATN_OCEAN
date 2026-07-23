import '../config/app_config.dart';

/// Model cho một biến thể (variant) của sản phẩm trong giỏ.
class CartVariant {
  final int? variantId;
  final num price;
  final String? color;
  final String? size;
  final String? imageUrl;
  final int? stock;

  const CartVariant({
    this.variantId,
    this.price = 0,
    this.color,
    this.size,
    this.imageUrl,
    this.stock,
  });

  factory CartVariant.fromJson(Map<String, dynamic> json) {
    return CartVariant(
      variantId: _toInt(json['variant_id'] ?? json['id']),
      price: _toNum(json['price']),
      color: json['color']?.toString(),
      size: json['size']?.toString(),
      imageUrl: json['image_url']?.toString(),
      stock: _toInt(json['stock_quantity'] ?? json['stock']),
    );
  }

  /// Mô tả biến thể để hiển thị, ví dụ "Đỏ | Size M".
  String get label {
    final parts = <String>[];
    if (color != null && color!.isNotEmpty) parts.add(color!);
    if (size != null && size!.isNotEmpty) parts.add('Size $size');
    return parts.join(' | ');
  }
}

/// Model cho một dòng sản phẩm trong giỏ hàng.
class CartItem {
  final int cartItemId;
  final int quantity;
  final String productName;
  final Map<String, dynamic>? _productRaw;
  final CartVariant? variant;
  final int? productStock;

  const CartItem({
    required this.cartItemId,
    required this.quantity,
    required this.productName,
    Map<String, dynamic>? productRaw,
    this.variant,
    this.productStock,
  }) : _productRaw = productRaw;

  factory CartItem.fromJson(Map<String, dynamic> json) {
    final product = json['product'] is Map
        ? Map<String, dynamic>.from(json['product'])
        : null;
    final variant = json['variant'] is Map
        ? CartVariant.fromJson(Map<String, dynamic>.from(json['variant']))
        : null;

    return CartItem(
      cartItemId: _toInt(json['cart_item_id'] ?? json['id']) ?? 0,
      quantity: _toInt(json['quantity']) ?? 1,
      productName: product?['name']?.toString() ?? 'Sản phẩm',
      productRaw: product,
      variant: variant,
      productStock: _toInt(product?['stock_quantity'] ?? product?['stock']),
    );
  }

  num get price => variant?.price ?? 0;

  num get lineTotal => price * quantity;

  /// Số lượng tối đa có thể mua dựa trên tồn kho biến thể rồi tới sản phẩm.
  int get maxQuantity => variant?.stock ?? productStock ?? 99;

  /// URL ảnh hiển thị: ưu tiên ảnh biến thể, fallback ảnh sản phẩm.
  String get imageUrl {
    final variantImage = variant?.imageUrl;
    if (variantImage != null && variantImage.isNotEmpty) {
      return AppConfig.imageUrl(variantImage);
    }
    if (_productRaw != null) {
      return AppConfig.productImageUrl(_productRaw);
    }
    return '';
  }

  CartItem copyWith({int? quantity}) {
    return CartItem(
      cartItemId: cartItemId,
      quantity: quantity ?? this.quantity,
      productName: productName,
      productRaw: _productRaw,
      variant: variant,
      productStock: productStock,
    );
  }
}

/// Model cho toàn bộ giỏ hàng.
class Cart {
  final List<CartItem> items;
  final num? serverTotalPrice;

  const Cart({this.items = const [], this.serverTotalPrice});

  factory Cart.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    final items = rawItems is List
        ? rawItems
            .whereType<Map>()
            .map((e) => CartItem.fromJson(Map<String, dynamic>.from(e)))
            .toList()
        : <CartItem>[];

    return Cart(
      items: items,
      serverTotalPrice: json['total_price'] != null
          ? _toNum(json['total_price'])
          : null,
    );
  }

  static const empty = Cart();

  bool get isEmpty => items.isEmpty;

  /// Tổng số lượng sản phẩm (dùng cho badge giỏ hàng).
  int get totalQuantity =>
      items.fold(0, (sum, item) => sum + item.quantity);

  /// Tổng tiền: ưu tiên giá trị server trả về, nếu không có thì tự cộng dồn.
  num get totalPrice {
    if (serverTotalPrice != null) return serverTotalPrice!;
    return items.fold<num>(0, (sum, item) => sum + item.lineTotal);
  }
}

int? _toInt(dynamic value) {
  if (value == null) return null;
  if (value is int) return value;
  return int.tryParse(value.toString());
}

num _toNum(dynamic value) {
  if (value == null) return 0;
  if (value is num) return value;
  return num.tryParse(value.toString()) ?? 0;
}
