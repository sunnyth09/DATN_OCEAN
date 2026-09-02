import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../config/app_theme.dart';
import '../models/cart_model.dart';
import '../providers/cart_provider.dart';
import '../services/api_client.dart';
import '../utils/format_utils.dart';
import '../widgets/network_image_widget.dart';
import '../widgets/shimmer_loading.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/app_toast.dart';
import '../widgets/product_card.dart';
import '../widgets/voucher_selection_modal.dart';

/// Màn hình Giỏ Hàng Ocean Sport:
/// - Danh sách sản phẩm trực quan, tăng giảm số lượng mượt mà, nút xóa tiện lợi.
/// - Thanh chọn và áp dụng Ocean Voucher thông minh.
/// - Thanh thanh toán cố định ở đáy màn hình hiển thị chi tiết giảm giá.
class CartScreen extends StatefulWidget {
  final VoidCallback? onContinueShopping;

  const CartScreen({super.key, this.onContinueShopping});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  List<dynamic> _recommendedProducts = [];
  Map<String, dynamic>? _appliedCoupon;
  int _discountAmount = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CartProvider>().fetchCart(force: true);
      _fetchRecommendations();
    });
  }

  int _calculateDiscount(Map<String, dynamic> coupon, double subtotal) {
    final type = coupon['type']?.toString() ?? '';
    final val = num.tryParse(coupon['value']?.toString() ?? '0') ?? 0;
    final maxDisc = num.tryParse(coupon['max_discount_value']?.toString() ?? '0') ?? 0;

    int discount = 0;
    if (type == 'percent') {
      discount = (subtotal * val / 100).round();
      if (maxDisc > 0 && discount > maxDisc) discount = maxDisc.toInt();
    } else if (type == 'free_ship') {
      discount = val > 0 ? val.toInt() : 30000;
    } else {
      discount = val.toInt();
    }
    return discount > subtotal ? subtotal.toInt() : discount;
  }

  Future<void> _openVoucherModal(double subtotal) async {
    final result = await VoucherSelectionModal.show(
      context,
      subtotal: subtotal,
      currentCoupon: _appliedCoupon,
    );
    if (!mounted || result == null) return;
    if (result.isEmpty) {
      setState(() {
        _appliedCoupon = null;
        _discountAmount = 0;
      });
    } else {
      final disc = _calculateDiscount(result, subtotal);
      setState(() {
        _appliedCoupon = result;
        _discountAmount = disc;
      });
    }
  }

  Future<void> _fetchRecommendations() async {
    try {
      final response = await ApiClient().get(
        '/products',
        queryParameters: {'per_page': 10},
      );
      final data = response.data;
      List<dynamic> list = [];
      if (data is List) {
        list = data;
      } else if (data is Map && data['data'] is List) {
        list = data['data'];
      }
      if (mounted) {
        setState(() {
          _recommendedProducts = list;
        });
      }
    } catch (_) {}
  }

  Future<void> _changeQuantity(int cartItemId, int quantity) async {
    if (quantity <= 0) {
      _removeItem(cartItemId);
      return;
    }
    final ok = await context.read<CartProvider>().updateQuantity(
          cartItemId,
          quantity,
        );
    if (!mounted) return;
    if (!ok) {
      AppToast.showError(
        context,
        message: 'Lỗi cập nhật số lượng!',
      );
    } else if (_appliedCoupon != null) {
      // P0-06: Recalculate discount dựa trên subtotal mới sau khi thay đổi số lượng
      final newSubtotal = context.read<CartProvider>().cart.totalPrice.toDouble();
      final newDiscount = _calculateDiscount(_appliedCoupon!, newSubtotal);
      setState(() => _discountAmount = newDiscount);
    }
  }

  Future<void> _removeItem(int cartItemId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: const Text(
          'Xóa khỏi giỏ hàng?',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
        ),
        content: const Text(
          'Bạn có chắc chắn muốn bỏ sản phẩm này khỏi giỏ hàng không?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Hủy', style: TextStyle(color: AppColors.textMuted)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.error,
              foregroundColor: Colors.white,
              elevation: 0,
            ),
            child: const Text('Xóa', style: TextStyle(fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    final ok = await context.read<CartProvider>().removeItem(cartItemId);
    if (!ok && mounted) {
      AppToast.showError(
        context,
        message: 'Lỗi xóa sản phẩm!',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9), // Shopee neutral bg
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0.5,
        title: const Text(
          'Giỏ Hàng',
          style: TextStyle(fontWeight: FontWeight.w900, fontSize: 17, color: AppColors.textPrimary),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: AppColors.textPrimary),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/home');
            }
          },
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.chat_bubble_outline_rounded, color: AppColors.textPrimary),
            onPressed: () => context.push('/chat'),
          ),
        ],
      ),
      body: Consumer<CartProvider>(
        builder: (context, cart, _) {
          if (cart.isLoading && cart.items.isEmpty) {
            return const ListShimmerLoading();
          }

          if (cart.status == CartStatus.unauthenticated && cart.items.isEmpty) {
            return AppEmptyState(
              icon: Icons.account_circle_outlined,
              title: 'Bạn chưa đăng nhập',
              message: 'Đăng nhập ngay để xem giỏ hàng, đồng bộ sản phẩm và nhận nhiều ưu đãi độc quyền!',
              buttonText: 'Đăng nhập ngay',
              onAction: () async {
                await context.push('/login');
                if (context.mounted) {
                  context.read<CartProvider>().fetchCart(force: true);
                }
              },
            );
          }

          if (cart.status == CartStatus.error && cart.items.isEmpty) {
            return AppEmptyState(
              icon: Icons.wifi_off_rounded,
              title: 'Không thể tải giỏ hàng',
              message: cart.errorMessage ?? 'Đã xảy ra lỗi kết nối.',
              buttonText: 'Thử lại',
              onAction: () => cart.fetchCart(force: true),
            );
          }

          if (cart.items.isEmpty) {
            return _buildEmptyCartView(context, cart);
          }

          return Stack(
            children: [
              RefreshIndicator(
                color: AppColors.primary,
                onRefresh: () => cart.fetchCart(force: true),
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 130),
                  children: [
                    // ── Cart Items Unified Container (Ocean Sport) ──
                    Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.02),
                            blurRadius: 10,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              const Icon(Icons.shopping_bag_outlined, size: 18, color: AppColors.primary),
                              const SizedBox(width: 8),
                              Text(
                                'Sản phẩm trong giỏ (${cart.items.length})',
                                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: AppColors.textPrimary),
                              ),
                            ],
                          ),
                          const Divider(height: 18, color: Color(0xFFF1F5F9)),
                          ...cart.items.map((item) => _buildCartItemCard(item)),
                        ],
                      ),
                    ),

                    const SizedBox(height: 12),

                    // ── Voucher Bar ──
                    InkWell(
                      onTap: () => _openVoucherModal(cart.cart.totalPrice.toDouble()),
                      child: Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: _appliedCoupon != null ? const Color(0xFFFFD1DC) : const Color(0xFFF1F5F9),
                            width: _appliedCoupon != null ? 1.2 : 1.0,
                          ),
                        ),
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        child: Row(
                          children: [
                            const Icon(Icons.confirmation_number_outlined, size: 18, color: AppColors.primary),
                            const SizedBox(width: 10),
                            const Text(
                              'Ocean Voucher',
                              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
                            ),
                            const Spacer(),
                            if (_appliedCoupon != null) ...[
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFFF1F2),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  '${_appliedCoupon!['code']} (-${FormatUtils.formatPrice(_discountAmount)})',
                                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.primary),
                                ),
                              ),
                              const SizedBox(width: 6),
                              GestureDetector(
                                onTap: () => setState(() {
                                  _appliedCoupon = null;
                                  _discountAmount = 0;
                                }),
                                child: const Icon(Icons.cancel_rounded, size: 16, color: Color(0xFF94A3B8)),
                              ),
                            ] else ...[
                              const Text(
                                'Chọn hoặc nhập mã >',
                                style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 16),

                    // ── Recommended Section (Vertical 2-Column Grid) ──
                    if (_recommendedProducts.isNotEmpty) ...[
                      Padding(
                        padding: const EdgeInsets.fromLTRB(4, 12, 4, 10),
                        child: Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(4),
                              decoration: BoxDecoration(
                                gradient: AppGradients.primary,
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: const Icon(Icons.local_fire_department_rounded, size: 14, color: Colors.white),
                            ),
                            const SizedBox(width: 8),
                            const Text(
                              'CÓ THỂ BẠN THÍCH',
                              style: TextStyle(
                                fontSize: 13.5,
                                fontWeight: FontWeight.w900,
                                color: AppColors.textPrimary,
                                letterSpacing: 0.2,
                              ),
                            ),
                            const Spacer(),
                            GestureDetector(
                              onTap: () {
                                HapticFeedback.lightImpact();
                                context.go('/shop');
                              },
                              child: const Row(
                                children: [
                                  Text(
                                    'Xem tất cả',
                                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary),
                                  ),
                                  Icon(Icons.chevron_right_rounded, size: 16, color: AppColors.primary),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      GridView.builder(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        padding: EdgeInsets.zero,
                        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          childAspectRatio: 0.65,
                          crossAxisSpacing: 10,
                          mainAxisSpacing: 10,
                        ),
                        itemCount: _recommendedProducts.length,
                        itemBuilder: (context, index) {
                          final product = _recommendedProducts[index];
                          return ProductCard(
                            product: product,
                            onTap: () async {
                              await context.push('/product-detail', extra: product);
                              if (context.mounted) {
                                context.read<CartProvider>().fetchCart(silent: true);
                              }
                            },
                          );
                        },
                      ),
                      const SizedBox(height: 16),
                    ],
                  ],
                ),
              ),

              // ── Sticky Checkout Bar ──
              Positioned(
                bottom: 0,
                left: 0,
                right: 0,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.08),
                        blurRadius: 16,
                        offset: const Offset(0, -4),
                      ),
                    ],
                  ),
                  child: SafeArea(
                    top: false,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            if (_appliedCoupon != null) ...[
                              Text(
                                'Tiết kiệm: -${FormatUtils.formatPrice(_discountAmount)}',
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFF059669),
                                ),
                              ),
                              const SizedBox(height: 2),
                            ] else ...[
                              const Text(
                                'Tổng thanh toán:',
                                style: TextStyle(
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.textSecondary,
                                ),
                              ),
                              const SizedBox(height: 2),
                            ],
                            Text(
                              FormatUtils.formatPrice(
                                (cart.cart.totalPrice - _discountAmount).clamp(0, double.infinity),
                              ),
                              style: const TextStyle(
                                fontSize: 19,
                                fontWeight: FontWeight.w900,
                                color: AppColors.primary,
                                letterSpacing: -0.3,
                              ),
                            ),
                          ],
                        ),
                        SizedBox(
                          height: 44,
                          child: ElevatedButton(
                            onPressed: () {
                              HapticFeedback.lightImpact();
                              context.push('/checkout', extra: {
                                'appliedCoupon': _appliedCoupon,
                                'discountAmount': _discountAmount,
                              });
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              padding: const EdgeInsets.symmetric(horizontal: 20),
                              elevation: 0,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            child: Text(
                              'MUA HÀNG (${cart.itemCount})',
                              style: const TextStyle(
                                fontSize: 13.5,
                                fontWeight: FontWeight.w700,
                                color: Colors.white,
                                letterSpacing: 0.2,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildCartItemCard(CartItem item) {
    return Dismissible(
      key: Key(item.cartItemId.toString()),
      direction: DismissDirection.endToStart,
      onDismissed: (direction) {
        context.read<CartProvider>().removeItem(item.cartItemId);
        AppToast.showSuccess(context, message: 'Đã xóa sản phẩm');
      },
      background: Container(
        margin: const EdgeInsets.symmetric(vertical: 10),
        padding: const EdgeInsets.only(right: 20),
        decoration: BoxDecoration(
          color: AppColors.error,
          borderRadius: BorderRadius.circular(10),
        ),
        alignment: Alignment.centerRight,
        child: const Icon(Icons.delete_outline, color: Colors.white, size: 28),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Thumbnail
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: Container(
              width: 80,
              height: 80,
              color: const Color(0xFFF8FAFC),
              child: NetworkImageWidget(
                imageUrl: item.imageUrl,
                width: 80,
                height: 80,
                fit: BoxFit.contain,
              ),
            ),
          ),
          const SizedBox(width: 12),

          // Details
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
                          fontSize: 13.5,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                          height: 1.25,
                        ),
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.delete_outline_rounded, size: 18, color: AppColors.textMuted),
                      onPressed: () => _removeItem(item.cartItemId),
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(),
                    ),
                  ],
                ),
                if (item.variant != null && item.variant!.label.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      'Phân loại: ${item.variant!.label}',
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          FormatUtils.formatPrice(item.price),
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w900,
                            color: AppColors.primary,
                          ),
                        ),
                        if (item.variant?.originalPrice != null &&
                            item.variant!.originalPrice! > item.price) ...[
                          const SizedBox(width: 6),
                          Text(
                            FormatUtils.formatPrice(item.variant!.originalPrice!),
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppColors.textMuted,
                              decoration: TextDecoration.lineThrough,
                            ),
                          ),
                        ],
                      ],
                    ),
                    // Quantity Stepper
                    Container(
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          InkWell(
                            onTap: () => _changeQuantity(item.cartItemId, item.quantity - 1),
                            borderRadius: BorderRadius.circular(8),
                            child: const Padding(
                              padding: EdgeInsets.all(4),
                              child: Icon(Icons.remove, size: 14, color: AppColors.textPrimary),
                            ),
                          ),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 8),
                            child: Text(
                              '${item.quantity}',
                              style: const TextStyle(
                                fontSize: 12.5,
                                fontWeight: FontWeight.w800,
                                color: AppColors.textPrimary,
                              ),
                            ),
                          ),
                          InkWell(
                            onTap: () => _changeQuantity(item.cartItemId, item.quantity + 1),
                            borderRadius: BorderRadius.circular(8),
                            child: const Padding(
                              padding: EdgeInsets.all(4),
                              child: Icon(Icons.add, size: 14, color: AppColors.textPrimary),
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
    ),
  );
}

  Widget _buildEmptyCartView(BuildContext context, CartProvider cart) {
    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: () async {
        await Future.wait([
          cart.fetchCart(force: true),
          _fetchRecommendations(),
        ]);
      },
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
        slivers: [
          // 1. Khối Giỏ Hàng Trống Tinh Tế (Compact & Modern)
          SliverToBoxAdapter(
            child: Container(
              margin: const EdgeInsets.fromLTRB(14, 14, 14, 12),
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 22),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFFF1F5F9), width: 1),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.025),
                    blurRadius: 10,
                    offset: const Offset(0, 3),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 68,
                    height: 68,
                    decoration: const BoxDecoration(
                      color: AppColors.primarySoft,
                      shape: BoxShape.circle,
                    ),
                    child: const Center(
                      child: Icon(
                        Icons.shopping_bag_outlined,
                        size: 34,
                        color: AppColors.primary,
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Giỏ hàng của bạn đang trống',
                    style: TextStyle(
                      fontSize: 15.5,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 5),
                  const Text(
                    'Khám phá các sản phẩm thể thao nổi bật bên dưới và thêm ngay vào giỏ hàng nhé!',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 12.5,
                      color: AppColors.textSecondary,
                      height: 1.35,
                    ),
                  ),
                  const SizedBox(height: 14),
                  ElevatedButton.icon(
                    onPressed: () {
                      HapticFeedback.lightImpact();
                      if (widget.onContinueShopping != null) {
                        widget.onContinueShopping!();
                      } else {
                        context.go('/shop');
                      }
                    },
                    icon: const Icon(Icons.storefront_rounded, size: 16),
                    label: const Text('Khám phá cửa hàng'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 9),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
                      textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                    ),
                  ),
                ],
              ),
            ),
          ),

          // 2. Tiêu đề "GỢI Ý CHO BẠN"
          if (_recommendedProducts.isNotEmpty)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 6, 16, 10),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        gradient: AppGradients.primary,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: const Icon(Icons.local_fire_department_rounded, size: 14, color: Colors.white),
                    ),
                    const SizedBox(width: 8),
                    const Text(
                      'GỢI Ý CHO BẠN',
                      style: TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w900,
                        color: AppColors.textPrimary,
                        letterSpacing: 0.2,
                      ),
                    ),
                    const Spacer(),
                    GestureDetector(
                      onTap: () {
                        HapticFeedback.lightImpact();
                        context.go('/shop');
                      },
                      child: const Row(
                        children: [
                          Text(
                            'Xem tất cả',
                            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary),
                          ),
                          Icon(Icons.chevron_right_rounded, size: 16, color: AppColors.primary),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),

          // 3. Grid 2 Cột Sản Phẩm Đẹp Mắt
          if (_recommendedProducts.isNotEmpty)
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 32),
              sliver: SliverGrid(
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  childAspectRatio: 0.65,
                  crossAxisSpacing: 10,
                  mainAxisSpacing: 10,
                ),
                delegate: SliverChildBuilderDelegate(
                  (context, index) {
                    final product = _recommendedProducts[index];
                    return ProductCard(
                      product: product,
                      onTap: () async {
                        await context.push('/product-detail', extra: product);
                        if (context.mounted) {
                          context.read<CartProvider>().fetchCart(silent: true);
                        }
                      },
                    );
                  },
                  childCount: _recommendedProducts.length,
                ),
              ),
            )
          else
            const SliverToBoxAdapter(
              child: Padding(
                padding: EdgeInsets.symmetric(vertical: 24),
                child: Center(
                  child: CircularProgressIndicator(
                    strokeWidth: 2.5,
                    color: AppColors.primary,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

