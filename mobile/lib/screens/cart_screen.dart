import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../services/api_client.dart';
import '../utils/format_utils.dart';
import 'checkout_screen.dart';
import '../config/app_config.dart';

class CartScreen extends StatefulWidget {
  final VoidCallback? onContinueShopping;

  const CartScreen({super.key, this.onContinueShopping});

  @override
  State<CartScreen> createState() => CartScreenState();
}

class CartScreenState extends State<CartScreen> {
  List<dynamic> cartItems = [];
  dynamic cartData;
  bool isLoading = true;
  String? errorMessage;

  @override
  void initState() {
    super.initState();
    fetchCart();
  }

  Future<void> fetchCart() async {
    try {
      final response = await ApiClient().dio.get('/cart');

      if (response.statusCode == 200) {
        final data = response.data;
        if (mounted) {
          setState(() {
            cartData = data['data'];
            cartItems = data['data']['items'] ?? [];
            isLoading = false;
          });
        }
      } else {
        if (mounted) {
          setState(() {
            errorMessage = 'Lỗi truy xuất giỏ hàng (${response.statusCode})';
            isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          errorMessage = 'Lỗi kết nối máy chủ.';
          isLoading = false;
        });
      }
    }
  }

  Future<void> fetchCartSilently() async {
    try {
      final response = await ApiClient().dio.get('/cart');
      if (response.statusCode == 200) {
        final data = response.data;
        if (mounted) {
          setState(() {
            cartData = data['data'];
            cartItems = data['data']['items'] ?? [];
          });
        }
      }
    } catch (_) {}
  }

  Future<void> _updateCartItem(int cartItemId, int quantity) async {
    final itemIndex = cartItems.indexWhere(
      (item) => item['cart_item_id'] == cartItemId,
    );
    if (itemIndex == -1) return;
    final int oldQty = int.parse(cartItems[itemIndex]['quantity'].toString());

    setState(() {
      cartItems[itemIndex]['quantity'] = quantity;
    }); // Optimistic UI

    try {
      final response = await ApiClient().dio.put(
        '/cart/items/$cartItemId',
        data: {'quantity': quantity},
      );

      if (response.statusCode == 200) {
        await fetchCartSilently();
      } else {
        setState(() {
          cartItems[itemIndex]['quantity'] = oldQty;
        });
        if (mounted)
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Lỗi cập nhật số lượng!')),
          );
      }
    } catch (_) {
      setState(() {
        cartItems[itemIndex]['quantity'] = oldQty;
      });
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Lỗi kết nối!')));
    }
  }

  Future<void> _removeCartItem(int cartItemId) async {
    final itemIndex = cartItems.indexWhere(
      (item) => item['cart_item_id'] == cartItemId,
    );
    if (itemIndex == -1) return;
    final oldItem = cartItems[itemIndex];

    setState(() {
      cartItems.removeAt(itemIndex);
    }); // Optimistic UI

    try {
      final response = await ApiClient().dio.delete('/cart/items/$cartItemId');

      if (response.statusCode == 200) {
        await fetchCartSilently();
      } else {
        setState(() {
          cartItems.insert(itemIndex, oldItem);
        });
        if (mounted)
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(const SnackBar(content: Text('Lỗi xoá sản phẩm!')));
      }
    } catch (_) {
      setState(() {
        cartItems.insert(itemIndex, oldItem);
      });
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Lỗi kết nối!')));
    }
  }

  String _formatPrice(dynamic price) {
    try {
      final num p = num.parse(price.toString());
      final formatted = p
          .toStringAsFixed(0)
          .replaceAllMapped(
            RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
            (m) => '${m[1]}.',
          );
      return '$formatted đ';
    } catch (_) {
      return price.toString();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'Giỏ hàng',
          style: TextStyle(
            color: Color(0xFF0F172A),
            fontWeight: FontWeight.w800,
            fontSize: 18,
          ),
        ),
        centerTitle: true,
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (isLoading) {
      return const Center(
        child: CircularProgressIndicator(color: Color(0xFFE63B6F)),
      );
    }

    if (errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.shopping_cart_outlined,
              size: 60,
              color: Colors.grey,
            ),
            const SizedBox(height: 16),
            Text(errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () {
                setState(() {
                  isLoading = true;
                  errorMessage = null;
                });
                fetchCart();
              },
              child: const Text('Thử lại'),
            ),
          ],
        ),
      );
    }

    if (cartItems.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.shopping_bag_outlined,
              size: 80,
              color: Color(0xFFE2E8F0),
            ),
            const SizedBox(height: 16),
            const Text(
              'Giỏ hàng của bạn đang trống',
              style: TextStyle(
                color: Color(0xFF64748B),
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFE63B6F),
                padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(24),
                ),
                elevation: 0,
              ),
              onPressed: () {
                widget.onContinueShopping?.call();
              },
              child: const Text(
                'Mua sắm ngay',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
            ),
          ],
        ),
      );
    }

    // Tính tổng tiền dựa trên total fields hoặc cộng dồn các item
    num totalAmount = 0;
    if (cartData != null && cartData['total_price'] != null) {
      totalAmount = num.parse(cartData['total_price'].toString());
    } else {
      for (var item in cartItems) {
        final price = item['variant']?['price'] ?? 0;
        totalAmount +=
            num.parse(price.toString()) *
            num.parse(item['quantity'].toString());
      }
    }

    return Column(
      children: [
        Expanded(
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: cartItems.length,
            itemBuilder: (context, index) {
              final item = cartItems[index];
              final product = item['product'];
              final variant = item['variant']; // Variant info if exist

              String name = product != null ? product['name'] : 'Sản phẩm';
              String image = '';
              if (variant != null && variant['image_url'] != null && variant['image_url'].toString().isNotEmpty) {
                image = AppConfig.imageUrl(variant['image_url'].toString());
              }
              if (image.isEmpty && product != null) {
                image = AppConfig.productImageUrl(product);
              }
              // Debug: log ra URL ảnh để kiểm tra
              if (kDebugMode) {
                debugPrint('=== CART IMAGE DEBUG ===');
                debugPrint('Product: ${product?['name']}');
                debugPrint('variant[image_url]: ${variant?['image_url']}');
                debugPrint('product[thumbnail_url]: ${product?['thumbnail_url']}');
                debugPrint('product[main_image]: ${product?['main_image']}');
                debugPrint('Resolved image URL: $image');
                debugPrint('isProduction: ${AppConfig.isProduction}');
                debugPrint('kStorageUrl: ${AppConfig.kStorageUrl}');
                debugPrint('========================');
              }
              int qty = int.tryParse(item['quantity'].toString()) ?? 1;
              String variantStr = '';
              if (variant != null) {
                if (variant['color'] != null)
                  variantStr += '${variant['color']} ';
                if (variant['size'] != null)
                  variantStr += '| Size ${variant['size']}';
              }

              return Container(
                margin: const EdgeInsets.only(bottom: 16),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.03),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    // Hình
                    ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: _buildProductImage(image, 80),
                    ),
                    const SizedBox(width: 16),
                    // Thông tin
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: Text(
                                  name,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                    fontSize: 14,
                                    color: Color(0xFF0F172A),
                                  ),
                                ),
                              ),
                              GestureDetector(
                                onTap: () =>
                                    _removeCartItem(item['cart_item_id']),
                                child: const Padding(
                                  padding: EdgeInsets.only(left: 8),
                                  child: Icon(
                                    Icons.close,
                                    color: Colors.grey,
                                    size: 18,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          if (variantStr.isNotEmpty) const SizedBox(height: 4),
                          if (variantStr.isNotEmpty)
                            Text(
                              variantStr,
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xFF64748B),
                              ),
                            ),
                          const SizedBox(height: 8),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                FormatUtils.formatPrice(
                                  item['variant']?['price'] ?? 0,
                                ),
                                style: const TextStyle(
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFFE63B6F),
                                  fontSize: 16,
                                ),
                              ),
                              // Bộ số đếm
                              Container(
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF1F5F9),
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                child: Row(
                                  children: [
                                    GestureDetector(
                                      onTap: () {
                                        if (qty > 1) {
                                          _updateCartItem(
                                            item['cart_item_id'],
                                            qty - 1,
                                          );
                                        }
                                      },
                                      child: Padding(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 10,
                                          vertical: 4,
                                        ),
                                        child: Icon(
                                          Icons.remove,
                                          size: 16,
                                          color: qty > 1
                                              ? const Color(0xFF475569)
                                              : Colors.grey.shade400,
                                        ),
                                      ),
                                    ),
                                    Text(
                                      '$qty',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.bold,
                                        fontSize: 13,
                                      ),
                                    ),
                                    GestureDetector(
                                      onTap: () {
                                        int maxQty =
                                            int.tryParse(
                                              (variant?['stock_quantity'] ??
                                                      variant?['stock'] ??
                                                      product?['stock_quantity'] ??
                                                      product?['stock'] ??
                                                      99)
                                                  .toString(),
                                            ) ??
                                            99;
                                        if (qty < maxQty) {
                                          _updateCartItem(
                                            item['cart_item_id'],
                                            qty + 1,
                                          );
                                        } else {
                                          ScaffoldMessenger.of(
                                            context,
                                          ).showSnackBar(
                                            SnackBar(
                                              content: Text(
                                                'Chỉ còn $maxQty sản phẩm trong kho!',
                                              ),
                                            ),
                                          );
                                        }
                                      },
                                      child: const Padding(
                                        padding: EdgeInsets.symmetric(
                                          horizontal: 10,
                                          vertical: 4,
                                        ),
                                        child: Icon(
                                          Icons.add,
                                          size: 16,
                                          color: Color(0xFF475569),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
        ),

        // Checkout Section
        Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(30),
              topRight: Radius.circular(30),
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.05),
                blurRadius: 20,
                offset: const Offset(0, -4),
              ),
            ],
          ),
          child: SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Tổng thanh toán',
                      style: TextStyle(color: Color(0xFF64748B), fontSize: 14),
                    ),
                    Text(
                      FormatUtils.formatPrice(totalAmount),
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 24,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => const CheckoutScreen(),
                        ),
                      );
                    },
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      backgroundColor: const Color(0xFFE63B6F),
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(30),
                      ),
                    ),
                    child: const Text(
                      'Thanh toán ngay',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  /// Widget hiển thị ảnh sản phẩm: hỗ trợ cả SVG lẫn raster (JPG/PNG/WebP)
  Widget _buildProductImage(String imageUrl, double size) {
    if (imageUrl.isEmpty) {
      return Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          color: const Color(0xFFF1F5F9),
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Icon(Icons.image_outlined, color: Color(0xFFCBD5E1)),
      );
    }

    final isSvg = imageUrl.toLowerCase().endsWith('.svg');

    if (isSvg) {
      return SvgPicture.network(
        imageUrl,
        width: size,
        height: size,
        fit: BoxFit.cover,
        placeholderBuilder: (_) => Container(
          width: size,
          height: size,
          color: const Color(0xFFF1F5F9),
          child: const Center(
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: Color(0xFFE63B6F),
            ),
          ),
        ),
      );
    }

    return CachedNetworkImage(
      imageUrl: imageUrl,
      width: size,
      height: size,
      fit: BoxFit.cover,
      placeholder: (_, __) => Container(
        width: size,
        height: size,
        color: const Color(0xFFF1F5F9),
        child: const Center(
          child: CircularProgressIndicator(
            strokeWidth: 2,
            color: Color(0xFFE63B6F),
          ),
        ),
      ),
      errorWidget: (_, __, ___) => Container(
        width: size,
        height: size,
        color: const Color(0xFFF1F5F9),
        child: const Icon(Icons.image_outlined, color: Color(0xFFCBD5E1)),
      ),
    );
  }
}
