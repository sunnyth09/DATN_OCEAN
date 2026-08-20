import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import '../services/api_client.dart';
import 'review_screen.dart';
import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../utils/format_utils.dart';
import '../widgets/app_toast.dart';

class OrderDetailScreen extends StatefulWidget {
  final String orderId;
  const OrderDetailScreen({super.key, required this.orderId});

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  Map<String, dynamic>? orderData;
  bool isLoading = true;
  String? errorMessage;
  bool _isCancelling = false;
  bool _isReordering = false;

  @override
  void initState() {
    super.initState();
    fetchOrderDetail();
  }

  Future<void> fetchOrderDetail() async {
    setState(() { isLoading = true; errorMessage = null; });
    try {
      if (widget.orderId == 'null' || widget.orderId.isEmpty) {
        throw Exception('Invalid orderId');
      }
      final response = await ApiClient().dio.get('/profile/orders/${widget.orderId}');

      if (response.statusCode == 200) {
        final data = response.data;
        if (mounted) {
          setState(() {
            orderData = data['data'];
            isLoading = false;
          });
        }
      }
    } on DioException catch (e) {
      // P0-02: Không hiển thị raw error, chỉ dùng statusCode để debug
      if (mounted) {
        setState(() {
          errorMessage = e.response?.statusCode == 404
              ? 'Đơn hàng không tồn tại hoặc đã bị xóa.'
              : 'Không thể xem chi tiết đơn hàng. Vui lòng thử lại.';
          isLoading = false;
        });
      }
    } catch (_) {
      // P0-02 + P0-03: Không log exception, không expose đển user
      if (mounted) {
        setState(() {
          errorMessage = 'Lỗi xử lý dữ liệu. Vui lòng thử lại.';
          isLoading = false;
        });
      }
    }
  }

  Future<void> _cancelOrder() async {
    // Preset lý do huỷ
    final presetReasons = [
      'Tôi muốn thay đổi địa chỉ giao hàng',
      'Tôi muốn thay đổi sản phẩm / size / màu',
      'Tôi tìm được sản phẩm giá tốt hơn',
      'Tôi đặt nhầm sản phẩm',
      'Thời gian giao hàng quá lâu',
      'Lý do khác...',
    ];

    String? selectedReason;
    final customCtrl = TextEditingController();
    bool showCustomInput = false;

    final confirmed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 20, right: 20, top: 20,
                bottom: MediaQuery.of(ctx).viewInsets.bottom + 20,
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Header
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Lý do huỷ đơn hàng', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
                        GestureDetector(
                          onTap: () => Navigator.pop(ctx, false),
                          child: const Icon(Icons.close, color: Colors.grey),
                        )
                      ],
                    ),
                    const SizedBox(height: 6),
                    const Text('Vui lòng chọn lý do để giúp chúng tôi cải thiện dịch vụ.', style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
                    const SizedBox(height: 16),

                    // Preset reasons
                    ...presetReasons.map((reason) {
                      final isSelected = selectedReason == reason;
                      return GestureDetector(
                        onTap: () {
                          setModalState(() {
                            selectedReason = reason;
                            showCustomInput = reason == 'Lý do khác...';
                            if (!showCustomInput) customCtrl.clear();
                          });
                        },
                        child: Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                          decoration: BoxDecoration(
                            color: isSelected ? const Color(0xFFFFF5F5) : const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: isSelected ? Colors.red.shade300 : const Color(0xFFE2E8F0)),
                          ),
                          child: Row(
                            children: [
                              Icon(
                                isSelected ? Icons.radio_button_checked : Icons.radio_button_off,
                                color: isSelected ? Colors.red : const Color(0xFF64748B),
                                size: 20,
                              ),
                              const SizedBox(width: 12),
                              Expanded(child: Text(reason, style: TextStyle(fontSize: 14, fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal, color: isSelected ? Colors.red.shade700 : const Color(0xFF334155)))),
                            ],
                          ),
                        ),
                      );
                    }),

                    // Custom input nếu chọn "Lý do khác"
                    if (showCustomInput) ...[
                      const SizedBox(height: 8),
                      TextField(
                        controller: customCtrl,
                        maxLines: 3,
                        maxLength: 500,
                        decoration: InputDecoration(
                          hintText: 'Nhập lý do của bạn...',
                          hintStyle: const TextStyle(color: Color(0xFF64748B)),
                          filled: true,
                          fillColor: const Color(0xFFF8FAFC),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.primary)),
                        ),
                      ),
                    ],

                    const SizedBox(height: 16),
                    // Action buttons
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => Navigator.pop(ctx, false),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: const Color(0xFF64748B),
                              side: const BorderSide(color: Color(0xFFE2E8F0)),
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            child: const Text('Bỏ qua', style: TextStyle(fontWeight: FontWeight.bold)),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: ElevatedButton(
                            onPressed: () {
                              if (selectedReason == null) {
                                AppToast.showWarning(ctx, message: 'Vui lòng chọn lý do huỷ!');
                                return;
                              }
                              if (showCustomInput && customCtrl.text.trim().isEmpty) {
                                AppToast.showWarning(ctx, message: 'Vui lòng nhập lý do cụ thể!');
                                return;
                              }
                              Navigator.pop(ctx, true);
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.red,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              elevation: 0,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            child: const Text('Xác nhận huỷ', style: TextStyle(fontWeight: FontWeight.bold)),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );

    if (confirmed != true || selectedReason == null) return;

    final finalReason = (selectedReason == 'Lý do khác...' && customCtrl.text.trim().isNotEmpty)
        ? customCtrl.text.trim()
        : selectedReason!;

    setState(() => _isCancelling = true);
    try {
      await ApiClient().dio.put(
        '/profile/orders/${widget.orderId}/cancel',
        data: {'cancel_reason': finalReason},
      );

      if (mounted) {
        AppToast.showSuccess(
          context,
          message: 'Đơn hàng đã được huỷ thành công!',
        );
        fetchOrderDetail();
      }
    } on DioException catch (e) {
      final message = e.response?.data is Map ? e.response?.data['message'] : null;
      if (mounted) {
        AppToast.showError(
          context,
          message: message ?? 'Không thể huỷ đơn hàng!',
        );
      }
    } catch (_) {
      if (mounted) AppToast.showError(context, message: 'Lỗi kết nối!');
    } finally {
      if (mounted) setState(() => _isCancelling = false);
    }
  }

  Future<void> _reOrder() async {
    setState(() => _isReordering = true);
    try {
      await ApiClient().dio.post('/cart/buy-again/${widget.orderId}');

      if (mounted) {
        AppToast.showSuccess(
          context,
          message: 'Đã thêm sản phẩm vào giỏ hàng!',
        );
        context.go('/cart');
      }
    } catch (_) {
      if (mounted) AppToast.showError(context, message: 'Lỗi kết nối!');
    } finally {
      if (mounted) setState(() => _isReordering = false);
    }
  }

  String _formatPrice(dynamic price) {
    try {
      final num p = num.parse(price.toString());
      final formatted = p.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]}.');
      return '$formatted đ';
    } catch (_) {
      return price.toString();
    }
  }

  Color _getStatusColor(String status) {
    if (status.contains('PENDING') || status.contains('PROCESSING')) return Colors.orange;
    if (status.contains('SHIP') || status.contains('DELIVERING')) return Colors.blue;
    if (status.contains('COMPLETED') || status.contains('DELIVERED') || status.contains('SUCCESS')) return Colors.green;
    if (status.contains('CANCEL')) return Colors.red;
    return const Color(0xFF64748B);
  }

  IconData _getStatusIcon(String status) {
    if (status.contains('PENDING') || status.contains('PROCESSING')) return Icons.pending_outlined;
    if (status.contains('SHIP') || status.contains('DELIVERING')) return Icons.local_shipping_outlined;
    if (status.contains('COMPLETED') || status.contains('DELIVERED')) return Icons.check_circle_outline;
    if (status.contains('CANCEL')) return Icons.cancel_outlined;
    return Icons.receipt_long;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Chi tiết Đơn Hàng', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: AppColors.primary),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/orders');
            }
          },
        ),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (isLoading) return const Center(child: CircularProgressIndicator(color: AppColors.primary));
    if (errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 60, color: Colors.grey),
            const SizedBox(height: 12),
            Text(errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: fetchOrderDetail, child: const Text('Thử lại')),
          ],
        ),
      );
    }
    if (orderData == null) return const Center(child: Text('Không có dữ liệu.'));

    final orderCode = orderData!['order_code'] ?? '';
    final grandTotal = orderData!['grand_total'] ?? 0;
    final shippingFee = orderData!['shipping_fee'] ?? 0;
    final discountAmount = orderData!['discount_amount'] ?? 0;
    final subtotal = orderData!['subtotal'] ?? 0;
    final items = orderData!['items'] as List? ?? [];
    final histories = orderData!['status_histories'] as List? ?? [];
    final address = orderData!['address'];
    final paymentMethod = orderData!['payment_method'] ?? '';
    String status = (orderData!['fulfillment_status'] ?? '').toString().toUpperCase();
    final statusColor = _getStatusColor(status);
    final canCancel = status.contains('PENDING') || status.contains('PROCESSING');
    final isCompleted = status.contains('COMPLETED') || status.contains('DELIVERED');

    return Stack(
      children: [
        SingleChildScrollView(
          padding: const EdgeInsets.only(left: 16, right: 16, top: 16, bottom: 100),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ===== STATUS HEADER =====
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 10)],
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.1), shape: BoxShape.circle),
                      child: Icon(_getStatusIcon(status), color: statusColor, size: 28),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Đơn #$orderCode', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF0F172A))),
                          const SizedBox(height: 4),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20)),
                            child: Text(FormatUtils.translateStatus(status), style: TextStyle(color: statusColor, fontSize: 12, fontWeight: FontWeight.bold)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // ===== TIMELINE =====
              if (histories.isNotEmpty) ...[
                _sectionTitle('Lịch sử đơn hàng'),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
                  child: Column(
                    children: histories.asMap().entries.map((entry) {
                      final h = entry.value;
                      final isLast = entry.key == histories.length - 1;
                      final note = h['note'] ?? h['status'] ?? '';
                      final date = FormatUtils.formatDate(h['created_at']);
                      return Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Column(
                            children: [
                              Container(
                                width: 20, height: 20,
                                decoration: BoxDecoration(
                                  color: isLast ? AppColors.primary : Colors.green,
                                  shape: BoxShape.circle,
                                ),
                                child: Icon(isLast ? Icons.circle : Icons.check, size: 12, color: Colors.white),
                              ),
                              if (!isLast) Container(width: 2, height: 24, color: const Color(0xFFE2E8F0)),
                            ],
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Padding(
                              padding: const EdgeInsets.only(bottom: 16),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Expanded(child: Text(note, style: const TextStyle(fontSize: 13, color: Color(0xFF334155)))),
                                  Text(date, style: const TextStyle(fontSize: 11, color: Colors.grey)),
                                ],
                              ),
                            ),
                          ),
                        ],
                      );
                    }).toList(),
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // ===== ADDRESS =====
              if (address != null) ...[
                _sectionTitle('Địa chỉ nhận hàng'),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(color: const Color(0xFFF0F9FF), borderRadius: BorderRadius.circular(10)),
                        child: const Icon(Icons.location_on_outlined, color: AppColors.primary, size: 20),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(address['recipient_name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                            const SizedBox(height: 2),
                            Text(address['phone'] ?? '', style: const TextStyle(color: Color(0xFF64748B), fontSize: 13)),
                            const SizedBox(height: 2),
                            Text('${address['address_line'] ?? ''}, ${address['ward'] ?? ''}, ${address['province'] ?? ''}', style: const TextStyle(fontSize: 13, color: Color(0xFF475569), height: 1.4)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // ===== PRODUCTS =====
              _sectionTitle('Sản phẩm đã mua'),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
                child: Column(
                  children: items.asMap().entries.map((entry) {
                    final item = entry.value;
                    final isLast = entry.key == items.length - 1;
                    final name = item['product_name'] ?? item['variant_name'] ?? '';
                    final qty = item['quantity'] ?? 1;
                    final price = item['unit_price'] ?? 0;
                    final product = item['product'];
                    final variant = item['variant'];
                    String imageUrl = '';
                    if (variant != null && variant['image_url'] != null && variant['image_url'].toString().isNotEmpty) {
                      imageUrl = _resolveImageUrl(variant['image_url'].toString());
                    }
                    if (imageUrl.isEmpty && product != null) {
                      imageUrl = AppConfig.productImageUrl(product);
                    }
                    if (imageUrl.isEmpty) {
                      imageUrl = _resolveImageUrl(item['thumbnail_url']?.toString() ?? item['image_url']?.toString() ?? '');
                    }

                    return Column(
                      children: [
                        Row(
                          children: [
                            ClipRRect(
                              borderRadius: BorderRadius.circular(10),
                              child: _buildProductImage(imageUrl, 60),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: Color(0xFF0F172A)), maxLines: 2, overflow: TextOverflow.ellipsis),
                                  const SizedBox(height: 4),
                                  Row(
                                    children: [
                                      Text('x$qty', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                                      const Spacer(),
                                      Text(_formatPrice(num.parse(price.toString()) * qty), style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.primary)),
                                    ],
                                  ),
                                  if (isCompleted) ...[  
                                    const SizedBox(height: 6),
                                    GestureDetector(
                                      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ReviewScreen(
                                        orderItem: item,
                                        productId: item['product_id'] ?? 0,
                                        productName: name,
                                        productImage: item['thumbnail_url'] ?? item['image_url'],
                                      ))),
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFFFF9C4),
                                          borderRadius: BorderRadius.circular(20),
                                          border: Border.all(color: Colors.amber.shade300),
                                        ),
                                        child: Row(mainAxisSize: MainAxisSize.min, children: const [
                                          Icon(Icons.star_outline, size: 12, color: Colors.amber),
                                          SizedBox(width: 4),
                                          Text('Đánh giá', style: TextStyle(fontSize: 11, color: Colors.amber, fontWeight: FontWeight.bold)),
                                        ]),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ],
                        ),
                        if (!isLast) const Divider(height: 20, color: Color(0xFFF1F5F9)),
                      ],
                    );
                  }).toList(),
                ),
              ),
              const SizedBox(height: 16),

              // ===== PAYMENT METHOD =====
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(color: const Color(0xFFF0F9FF), borderRadius: BorderRadius.circular(10)),
                      child: const Icon(Icons.payment_outlined, color: AppColors.primary, size: 20),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Phương thức thanh toán', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                          Text(paymentMethod.toUpperCase(), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              // ===== PRICE SUMMARY =====
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
                child: Column(
                  children: [
                    _priceRow('Tạm tính', _formatPrice(subtotal)),
                    const SizedBox(height: 8),
                    _priceRow('Phí giao hàng', _formatPrice(shippingFee)),
                    if (num.tryParse(discountAmount.toString()) != null && num.parse(discountAmount.toString()) > 0) ...[
                      const SizedBox(height: 8),
                      _priceRow('Giảm giá', '- ${_formatPrice(discountAmount)}', valueColor: Colors.green),
                    ],
                    const Divider(height: 24, color: Color(0xFFE2E8F0)),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Tổng thanh toán', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                        Text(_formatPrice(grandTotal), style: const TextStyle(fontWeight: FontWeight.w900, color: AppColors.primary, fontSize: 20)),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),

        // ===== BOTTOM ACTION BUTTONS =====
        Positioned(
          bottom: 0, left: 0, right: 0,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 16, offset: const Offset(0, -4))],
            ),
            child: SafeArea(
              child: Row(
                children: [
                  // Nút huỷ đơn (chỉ hiện khi pending)
                  if (canCancel) ...[
                    Expanded(
                      child: OutlinedButton(
                        onPressed: _isCancelling ? null : _cancelOrder,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.red,
                          side: const BorderSide(color: Colors.red),
                          padding: const EdgeInsets.symmetric(vertical: 11),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        child: _isCancelling
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.red))
                          : const Text('Huỷ đơn hàng', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                      ),
                    ),
                    const SizedBox(width: 12),
                  ],
                  // Nút mua lại (chỉ hiện khi completed)
                  if (isCompleted) ...[
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () async {
                          final result = await context.push<bool>('/create-return/${widget.orderId}');
                          if (result == true) {
                            fetchOrderDetail();
                          }
                        },
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.primary,
                          side: const BorderSide(color: AppColors.primary),
                          padding: const EdgeInsets.symmetric(vertical: 11),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        child: const Text('Hoàn trả', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: _isReordering ? null : _reOrder,
                        icon: _isReordering
                          ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : const Icon(Icons.refresh, size: 16),
                        label: const Text('Mua lại', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 11),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          elevation: 0,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                  ],
                  // Nút liên hệ hỗ trợ (luôn hiện)
                  if (!canCancel && !isCompleted)
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: () => context.pop(),
                        icon: const Icon(Icons.arrow_back, size: 16),
                        label: const Text('Quay lại', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 11),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          elevation: 0,
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
  }

  Widget _sectionTitle(String title) => Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A)));

  Widget _priceRow(String label, String value, {Color? valueColor}) => Row(
    mainAxisAlignment: MainAxisAlignment.spaceBetween,
    children: [
      Text(label, style: const TextStyle(color: Color(0xFF64748B), fontSize: 13)),
      Text(value, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: valueColor ?? const Color(0xFF0F172A))),
    ],
  );

  String _resolveImageUrl(String raw) {
    return AppConfig.imageUrl(raw);
  }

  /// Widget hiển thị ảnh sản phẩm: hỗ trợ SVG lẫn raster (JPG/PNG/WebP)
  Widget _buildProductImage(String imgUrl, double size) {
    if (imgUrl.isEmpty) {
      return SizedBox(
        width: size,
        height: size,
        child: const Icon(Icons.image_outlined, color: Color(0xFFCBD5E1), size: 22),
      );
    }

    final isSvg = imgUrl.toLowerCase().endsWith('.svg');

    if (isSvg) {
      return SvgPicture.network(
        imgUrl,
        width: size,
        height: size,
        fit: BoxFit.cover,
        placeholderBuilder: (_) => SizedBox(
          width: size,
          height: size,
          child: const Center(
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: AppColors.primary,
            ),
          ),
        ),
      );
    }

    return CachedNetworkImage(
      imageUrl: imgUrl,
      width: size,
      height: size,
      fit: BoxFit.cover,
      placeholder: (_, _) => SizedBox(
        width: size,
        height: size,
        child: const Center(
          child: CircularProgressIndicator(
            strokeWidth: 2,
            color: AppColors.primary,
          ),
        ),
      ),
      errorWidget: (_, _, _) => SizedBox(
        width: size,
        height: size,
        child: const Icon(Icons.image_outlined, color: Color(0xFFCBD5E1), size: 22),
      ),
    );
  }
}
