import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../models/cart_model.dart';
import '../providers/cart_provider.dart';
import '../utils/format_utils.dart';
import 'checkout_screen.dart';

class CartScreen extends StatefulWidget {
  final VoidCallback? onContinueShopping;

  const CartScreen({super.key, this.onContinueShopping});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  @override
  void initState() {
    super.initState();
    // Nạp giỏ hàng sau frame đầu để có thể dùng context.read an toàn.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CartProvider>().fetchCart();
    });
  }

  Future<void> _changeQuantity(int cartItemId, int quantity) async {
    final ok = await context.read<CartProvider>().updateQuantity(
      cartItemId,
      quantity,
    );
    if (!ok && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Lỗi cập nhật số lượng!')),
      );
    }
  }

  Future<void> _removeItem(int cartItemId) async {
    final ok = await context.read<CartProvider>().removeItem(cartItemId);
    if (!ok && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Lỗi xoá sản phẩm!')),
      );
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
      body: Consumer<CartProvider>(
        builder: (context, cart, _) => _buildBody(cart),
      ),
    );
  }

  Widget _buildBody(CartProvider cart) {
    if (cart.isLoading) {
      return const Center(
        child: CircularProgressIndicator(color: Color(0xFFE63B6F)),
      );
    }

    if (cart.status == CartStatus.error) {
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
            Text(
              cart.errorMessage ?? 'Đã xảy ra lỗi',
              style: const TextStyle(color: Colors.red),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => cart.fetchCart(),
              child: const Text('Thử lại'),
            ),
          ],
        ),
      );
    }

    if (cart.items.isEmpty) {
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
              onPressed: () => widget.onContinueShopping?.call(),
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

    return Column(
      children: [
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => cart.fetchCart(silent: true),
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: cart.items.length,
              itemBuilder: (context, index) => _buildCartItem(cart.items[index]),
            ),
          ),
        ),
        _buildCheckoutSection(cart),
      ],
    );
  }

  Widget _buildCartItem(CartItem item) {
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: _buildProductImage(item.imageUrl, 80),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        item.productName,
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
                      onTap: () => _removeItem(item.cartItemId),
                      child: const Padding(
                        padding: EdgeInsets.only(left: 8),
                        child: Icon(Icons.close, color: Colors.grey, size: 18),
                      ),
                    ),
                  ],
                ),
                if (item.variant?.label.isNotEmpty ?? false) ...[
                  const SizedBox(height: 4),
                  Text(
                    item.variant!.label,
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xFF64748B),
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      FormatUtils.formatPrice(item.price),
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        color: Color(0xFFE63B6F),
                        fontSize: 16,
                      ),
                    ),
                    _buildQuantitySelector(item),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildQuantitySelector(CartItem item) {
    final qty = item.quantity;
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        children: [
          GestureDetector(
            onTap: () {
              if (qty > 1) _changeQuantity(item.cartItemId, qty - 1);
            },
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              child: Icon(
                Icons.remove,
                size: 16,
                color: qty > 1 ? const Color(0xFF475569) : Colors.grey.shade400,
              ),
            ),
          ),
          Text(
            '$qty',
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
          ),
          GestureDetector(
            onTap: () {
              final maxQty = item.maxQuantity;
              if (qty < maxQty) {
                _changeQuantity(item.cartItemId, qty + 1);
              } else {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Chỉ còn $maxQty sản phẩm trong kho!')),
                );
              }
            },
            child: const Padding(
              padding: EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              child: Icon(Icons.add, size: 16, color: Color(0xFF475569)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCheckoutSection(CartProvider cart) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: const BorderRadius.only(
          topLeft: Radius.circular(30),
          topRight: Radius.circular(30),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
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
                  FormatUtils.formatPrice(cart.cart.totalPrice),
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
                onPressed: () async {
                  await Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => const CheckoutScreen(),
                    ),
                  );
                  // Quay lại từ checkout → đồng bộ lại giỏ.
                  if (mounted) {
                    context.read<CartProvider>().fetchCart(silent: true);
                  }
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
    );
  }

  /// Widget hiển thị ảnh sản phẩm: hỗ trợ cả SVG lẫn raster (JPG/PNG/WebP).
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

    if (imageUrl.toLowerCase().endsWith('.svg')) {
      return SvgPicture.network(
        imageUrl,
        width: size,
        height: size,
        fit: BoxFit.cover,
        placeholderBuilder: (_) => _imagePlaceholder(size),
      );
    }

    return CachedNetworkImage(
      imageUrl: imageUrl,
      width: size,
      height: size,
      fit: BoxFit.cover,
      placeholder: (_, _) => _imagePlaceholder(size),
      errorWidget: (_, _, _) => Container(
        width: size,
        height: size,
        color: const Color(0xFFF1F5F9),
        child: const Icon(Icons.image_outlined, color: Color(0xFFCBD5E1)),
      ),
    );
  }

  Widget _imagePlaceholder(double size) => Container(
    width: size,
    height: size,
    color: const Color(0xFFF1F5F9),
    child: const Center(
      child: CircularProgressIndicator(
        strokeWidth: 2,
        color: Color(0xFFE63B6F),
      ),
    ),
  );
}
