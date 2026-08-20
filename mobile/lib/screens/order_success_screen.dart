import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/navigation_provider.dart';
import '../services/api_client.dart';
import '../utils/format_utils.dart';
import '../widgets/app_toast.dart';
import '../widgets/network_image_widget.dart';
import '../widgets/price_tag.dart';

class OrderSuccessScreen extends StatefulWidget {
  final String? orderCode;
  final num? grandTotal;
  final String? orderId;

  const OrderSuccessScreen({
    super.key,
    this.orderCode,
    this.grandTotal,
    this.orderId,
  });

  @override
  State<OrderSuccessScreen> createState() => _OrderSuccessScreenState();
}

class _OrderSuccessScreenState extends State<OrderSuccessScreen> {
  List<dynamic> relatedProducts = [];
  bool isLoadingProducts = true;

  @override
  void initState() {
    super.initState();
    fetchRelatedProducts();
  }

  Future<void> fetchRelatedProducts() async {
    try {
      final response = await ApiClient().dio.get(
        '/products',
        queryParameters: {'page': 1, 'per_page': 8},
      );
      if (response.statusCode == 200) {
        final data = response.data;
        List<dynamic> fetched = [];

        if (data is List) {
          fetched = data;
        } else if (data['data'] is List) {
          fetched = data['data'];
        }

        if (mounted) {
          setState(() {
            fetched.shuffle();
            relatedProducts = fetched.take(6).toList();
            isLoadingProducts = false;
          });
        }
      }
    } catch (_) {
      if (mounted) {
        setState(() => isLoadingProducts = false);
      }
    }
  }

  void _onViewOrder() {
    if (widget.orderId != null && widget.orderId!.isNotEmpty) {
      context.push('/order-detail', extra: widget.orderId!);
    } else {
      context.push('/orders');
    }
  }

  void _onContinueShopping() {
    context.read<NavigationProvider>().setTab(0);
    context.go('/home');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        automaticallyImplyLeading: false,
        backgroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
        title: const Text(
          'Hoàn tất đặt hàng',
          style: TextStyle(
            fontWeight: FontWeight.w800,
            color: AppColors.textPrimary,
            fontSize: 17,
          ),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              const SizedBox(height: 20),

              // ── Professional Layered Success Badge ──
              Center(
                child: Container(
                  width: 78,
                  height: 78,
                  decoration: BoxDecoration(
                    color: const Color(0xFFECFDF5),
                    shape: BoxShape.circle,
                    border: Border.all(color: const Color(0xFFA7F3D0), width: 1.5),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF10B981).withValues(alpha: 0.15),
                        blurRadius: 16,
                        offset: const Offset(0, 6),
                      ),
                    ],
                  ),
                  child: Center(
                    child: Container(
                      width: 52,
                      height: 52,
                      decoration: const BoxDecoration(
                        gradient: LinearGradient(
                          colors: [Color(0xFF10B981), Color(0xFF059669)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: Color(0x4410B981),
                            blurRadius: 10,
                            offset: Offset(0, 3),
                          ),
                        ],
                      ),
                      child: const Center(
                        child: Icon(
                          Icons.check_rounded,
                          color: Colors.white,
                          size: 30,
                        ),
                      ),
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 14),

              // ── Title & Subtitle ──
              const Text(
                'Mua hàng thành công!',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                  color: AppColors.textPrimary,
                  letterSpacing: -0.2,
                ),
              ),
              const SizedBox(height: 6),
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 28),
                child: Text(
                  'Cảm ơn bạn đã mua sắm tại Ocean Sport.\nĐơn hàng của bạn đang được đóng gói và giao sớm nhất.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                    height: 1.4,
                  ),
                ),
              ),

              // ── Order Summary Card ──
              if (widget.orderCode != null || widget.grandTotal != null) ...[
                const SizedBox(height: 16),
                Container(
                  margin: const EdgeInsets.symmetric(horizontal: 24),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.02),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Column(
                    children: [
                      if (widget.orderCode != null)
                        Row(
                          children: [
                            const Icon(Icons.receipt_outlined, size: 15, color: Color(0xFF64748B)),
                            const SizedBox(width: 6),
                            const Text(
                              'Mã đơn hàng',
                              style: TextStyle(
                                color: Color(0xFF64748B),
                                fontSize: 12.5,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            const Spacer(),
                            Text(
                              widget.orderCode!,
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 12.5,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                            const SizedBox(width: 6),
                            GestureDetector(
                              onTap: () {
                                Clipboard.setData(ClipboardData(text: widget.orderCode!));
                                AppToast.showSuccess(
                                  context,
                                  message: 'Đã sao chép mã đơn hàng!',
                                );
                              },
                              child: const Icon(
                                Icons.copy_rounded,
                                size: 14,
                                color: AppColors.primary,
                              ),
                            ),
                          ],
                        ),
                      if (widget.orderCode != null && widget.grandTotal != null)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 8),
                          child: Divider(height: 1, color: Color(0xFFF1F5F9)),
                        ),
                      if (widget.grandTotal != null)
                        Row(
                          children: [
                            const Icon(Icons.payments_outlined, size: 15, color: Color(0xFF64748B)),
                            const SizedBox(width: 6),
                            const Text(
                              'Tổng thanh toán',
                              style: TextStyle(
                                color: Color(0xFF64748B),
                                fontSize: 12.5,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            const Spacer(),
                            Text(
                              FormatUtils.formatPrice(widget.grandTotal!),
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                                fontSize: 15,
                                color: AppColors.primary,
                              ),
                            ),
                          ],
                        ),
                    ],
                  ),
                ),
              ],

              const SizedBox(height: 18),

              // ── Action Buttons ──
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                child: Row(
                  children: [
                    Expanded(
                      child: SizedBox(
                        height: 38,
                        child: OutlinedButton.icon(
                          onPressed: _onViewOrder,
                          icon: const Icon(Icons.receipt_long_rounded, size: 15, color: AppColors.primary),
                          label: const Text(
                            'Xem đơn hàng',
                            style: TextStyle(
                              color: AppColors.primary,
                              fontWeight: FontWeight.w700,
                              fontSize: 12.5,
                            ),
                          ),
                          style: OutlinedButton.styleFrom(
                            side: const BorderSide(color: AppColors.primary, width: 1.0),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            backgroundColor: Colors.white,
                            elevation: 0,
                            padding: const EdgeInsets.symmetric(horizontal: 8),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: SizedBox(
                        height: 38,
                        child: ElevatedButton.icon(
                          onPressed: _onContinueShopping,
                          icon: const Icon(Icons.shopping_bag_outlined, size: 15, color: Colors.white),
                          label: const Text(
                            'Tiếp tục mua hàng',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                              fontSize: 12.5,
                            ),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            elevation: 0,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            padding: const EdgeInsets.symmetric(horizontal: 8),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 28),

              // ── Related Products Section ──
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                  boxShadow: [
                    BoxShadow(
                      color: Color(0x0A000000),
                      blurRadius: 12,
                      offset: Offset(0, -2),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.auto_awesome_rounded, size: 18, color: AppColors.primary),
                        SizedBox(width: 8),
                        Text(
                          'Có thể bạn sẽ thích',
                          style: TextStyle(
                            fontSize: 16.5,
                            fontWeight: FontWeight.w800,
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    if (isLoadingProducts)
                      const Center(
                        child: Padding(
                          padding: EdgeInsets.all(32),
                          child: CircularProgressIndicator(color: AppColors.primary),
                        ),
                      )
                    else if (relatedProducts.isEmpty)
                      const Center(
                        child: Padding(
                          padding: EdgeInsets.all(16),
                          child: Text(
                            'Chưa có sản phẩm gợi ý',
                            style: TextStyle(color: AppColors.textSecondary),
                          ),
                        ),
                      )
                    else
                      GridView.builder(
                        physics: const NeverScrollableScrollPhysics(),
                        shrinkWrap: true,
                        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          crossAxisSpacing: 12,
                          mainAxisSpacing: 12,
                          childAspectRatio: 0.65,
                        ),
                        itemCount: relatedProducts.length,
                        itemBuilder: (context, index) {
                          return _buildProductCard(relatedProducts[index]);
                        },
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildProductCard(Map<String, dynamic> product) {
    final name = product['name'] ?? 'Không tên';
    final dynamic rawPrice = product['min_price'] ??
        (product['lowest_price_variant'] != null
            ? product['lowest_price_variant']['price']
            : 0);

    final imageUrl = AppConfig.productImageUrl(product);

    return GestureDetector(
      onTap: () {
        context.push('/product-detail', extra: product);
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
          border: Border.all(color: const Color(0xFFF1F5F9)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 1.1,
              child: Container(
                decoration: const BoxDecoration(
                  color: Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.vertical(top: Radius.circular(14)),
                ),
                padding: const EdgeInsets.all(8),
                child: Center(
                  child: NetworkImageWidget(
                    imageUrl: imageUrl,
                    fit: BoxFit.contain,
                    errorWidget: const Center(
                      child: Icon(Icons.sports_tennis_rounded, color: Color(0xFFCBD5E1), size: 24),
                    ),
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textPrimary,
                      height: 1.25,
                    ),
                  ),
                  const SizedBox(height: 6),
                  PriceTag(
                    price: num.tryParse(rawPrice.toString()) ?? 0,
                    fontSize: 14,
                    showDiscountBadge: false,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
