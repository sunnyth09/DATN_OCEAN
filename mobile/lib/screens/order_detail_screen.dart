import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
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
  bool _isTimelineExpanded = false;

  @override
  void initState() {
    super.initState();
    fetchOrderDetail();
  }

  Future<void> fetchOrderDetail() async {
    setState(() {
      isLoading = true;
      errorMessage = null;
    });
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
      if (mounted) {
        setState(() {
          errorMessage = e.response?.statusCode == 404
              ? 'Đơn hàng không tồn tại hoặc đã bị xóa.'
              : 'Không thể xem chi tiết đơn hàng. Vui lòng thử lại.';
          isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          errorMessage = 'Lỗi xử lý dữ liệu. Vui lòng thử lại.';
          isLoading = false;
        });
      }
    }
  }

  Future<void> _cancelOrder() async {
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
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(ctx).viewInsets.bottom + 20,
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Lý do huỷ đơn hàng',
                          style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                        ),
                        GestureDetector(
                          onTap: () => Navigator.pop(ctx, false),
                          child: const Icon(Icons.close_rounded, color: Colors.grey, size: 22),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'Vui lòng chọn lý do để giúp chúng tôi cải thiện dịch vụ.',
                      style: TextStyle(fontSize: 12.5, color: Color(0xFF64748B)),
                    ),
                    const SizedBox(height: 14),

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
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
                          decoration: BoxDecoration(
                            color: isSelected ? const Color(0xFFFFF5F5) : const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: isSelected ? Colors.red.shade300 : const Color(0xFFE2E8F0),
                              width: isSelected ? 1.5 : 1,
                            ),
                          ),
                          child: Row(
                            children: [
                              Icon(
                                isSelected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
                                color: isSelected ? Colors.red : const Color(0xFF64748B),
                                size: 19,
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(
                                  reason,
                                  style: TextStyle(
                                    fontSize: 13.5,
                                    fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                                    color: isSelected ? Colors.red.shade700 : const Color(0xFF334155),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    }),

                    if (showCustomInput) ...[
                      const SizedBox(height: 8),
                      TextField(
                        controller: customCtrl,
                        maxLines: 3,
                        maxLength: 500,
                        style: const TextStyle(fontSize: 13.5),
                        decoration: InputDecoration(
                          hintText: 'Nhập lý do chi tiết của bạn...',
                          hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12.5),
                          filled: true,
                          fillColor: const Color(0xFFF8FAFC),
                          contentPadding: const EdgeInsets.all(12),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
                          ),
                        ),
                      ),
                    ],

                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => Navigator.pop(ctx, false),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: const Color(0xFF64748B),
                              side: const BorderSide(color: Color(0xFFE2E8F0)),
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            child: const Text('Bỏ qua', style: TextStyle(fontWeight: FontWeight.w700)),
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
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              elevation: 0,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            child: const Text('Xác nhận huỷ', style: TextStyle(fontWeight: FontWeight.w800)),
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

  void _copyOrderCode(String code) {
    Clipboard.setData(ClipboardData(text: code));
    AppToast.showSuccess(context, message: 'Đã sao chép mã đơn hàng: $code');
  }

  Color _getStatusColor(String status) {
    final st = status.toUpperCase();
    if (st.contains('PENDING') || st.contains('WAITING') || st.contains('UNPAID')) return const Color(0xFFF59E0B);
    if (st.contains('PROCESSING') || st.contains('CONFIRMED')) return const Color(0xFF0284C7);
    if (st.contains('SHIP') || st.contains('DELIVERING') || st.contains('TRANSIT')) return const Color(0xFF0D9488);
    if (st.contains('COMPLETED') || st.contains('DELIVERED') || st.contains('SUCCESS')) return const Color(0xFF16A34A);
    if (st.contains('RETURN') || st.contains('REFUND')) return AppColors.primary;
    if (st.contains('CANCEL') || st.contains('FAIL')) return const Color(0xFFDC2626);
    return const Color(0xFF64748B);
  }

  IconData _getStatusIcon(String status) {
    final st = status.toUpperCase();
    if (st.contains('PENDING') || st.contains('WAITING')) return Icons.hourglass_top_rounded;
    if (st.contains('PROCESSING') || st.contains('CONFIRMED')) return Icons.inventory_2_outlined;
    if (st.contains('SHIP') || st.contains('DELIVERING')) return Icons.local_shipping_outlined;
    if (st.contains('COMPLETED') || st.contains('DELIVERED')) return Icons.verified_rounded;
    if (st.contains('RETURN') || st.contains('REFUND')) return Icons.assignment_return_outlined;
    if (st.contains('CANCEL') || st.contains('FAIL')) return Icons.cancel_outlined;
    return Icons.receipt_long_rounded;
  }

  Map<String, dynamic> _getHistoryItemStyle(String note, bool isFirst) {
    final lower = note.toLowerCase();
    if (lower.contains('đặt') || lower.contains('order')) {
      return {'icon': Icons.shopping_bag_outlined, 'color': const Color(0xFF2563EB)};
    }
    if (lower.contains('duyệt') || lower.contains('xác nhận') || lower.contains('confirmed')) {
      return {'icon': Icons.task_alt_rounded, 'color': const Color(0xFF16A34A)};
    }
    if (lower.contains('giao') || lower.contains('vận chuyển') || lower.contains('ship')) {
      return {'icon': Icons.local_shipping_outlined, 'color': const Color(0xFFD97706)};
    }
    if (lower.contains('hoàn') || lower.contains('trả') || lower.contains('refund') || lower.contains('return')) {
      return {'icon': Icons.assignment_return_outlined, 'color': AppColors.primary};
    }
    if (lower.contains('tiền') || lower.contains('thanh toán') || lower.contains('payment')) {
      return {'icon': Icons.payments_outlined, 'color': const Color(0xFF7C3AED)};
    }
    if (lower.contains('hủy') || lower.contains('cancel')) {
      return {'icon': Icons.cancel_outlined, 'color': const Color(0xFFDC2626)};
    }
    return {
      'icon': isFirst ? Icons.radio_button_checked_rounded : Icons.check_circle_outline_rounded,
      'color': isFirst ? AppColors.primary : const Color(0xFF64748B),
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text(
          'Chi Tiết Đơn Hàng',
          style: TextStyle(
            color: Color(0xFF0F172A),
            fontWeight: FontWeight.w800,
            fontSize: 17.5,
            letterSpacing: -0.2,
          ),
        ),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        scrolledUnderElevation: 1,
        shadowColor: Colors.black.withValues(alpha: 0.05),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Color(0xFF0F172A), size: 19),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/orders');
            }
          },
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.headset_mic_outlined, color: Color(0xFF334155), size: 22),
            onPressed: () => context.push('/chat'),
            tooltip: 'Hỗ trợ trực tuyến',
          ),
        ],
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (isLoading) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircularProgressIndicator(strokeWidth: 2.5, color: AppColors.primary),
            SizedBox(height: 14),
            Text(
              'Đang tải chi tiết đơn hàng...',
              style: TextStyle(color: Color(0xFF64748B), fontSize: 13.5, fontWeight: FontWeight.w500),
            ),
          ],
        ),
      );
    }

    if (errorMessage != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: const BoxDecoration(
                  color: Color(0xFFFEE2E2),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.error_outline_rounded, size: 36, color: Color(0xFFEF4444)),
              ),
              const SizedBox(height: 14),
              Text(
                errorMessage!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Color(0xFF334155), fontSize: 14.5, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 18),
              ElevatedButton.icon(
                onPressed: fetchOrderDetail,
                icon: const Icon(Icons.refresh_rounded, size: 18),
                label: const Text('Thử lại'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ],
          ),
        ),
      );
    }

    if (orderData == null) return const Center(child: Text('Không có dữ liệu.'));

    final orderCode = orderData!['order_code'] ?? '#${widget.orderId}';
    final grandTotal = orderData!['grand_total'] ?? 0;
    final shippingFee = orderData!['shipping_fee'] ?? 0;
    final discountAmount = orderData!['discount_amount'] ?? 0;
    final subtotal = orderData!['subtotal'] ?? 0;
    final items = orderData!['items'] as List? ?? [];
    final histories = orderData!['status_histories'] as List? ?? [];
    final address = orderData!['address'];
    final paymentMethod = orderData!['payment_method'] ?? 'COD';
    final createdAt = orderData!['created_at'];

    String status = (orderData!['fulfillment_status'] ?? orderData!['status'] ?? '').toString().toUpperCase();
    final canCancel = status.contains('PENDING') || status == 'CONFIRMED';
    final isCompleted = status.contains('COMPLETED') || status.contains('DELIVERED') || status.contains('SUCCESS');
    final isShipping = status.contains('SHIP') || status.contains('DELIVERING');

    return Column(
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // 1. STATUS HERO HEADER
                _buildStatusHeader(orderCode, status, createdAt),
                const SizedBox(height: 14),

                // 2. TIMELINE HISTORY (Collapsible & Smart-formatted)
                if (histories.isNotEmpty) ...[
                  _buildTimelineSection(histories),
                  const SizedBox(height: 14),
                ],

                // 3. SHIPPING ADDRESS
                if (address != null) ...[
                  _buildAddressSection(address),
                  const SizedBox(height: 14),
                ],

                // 4. ORDER PRODUCTS
                _buildProductsSection(items, isCompleted),
                const SizedBox(height: 14),

                // 5. PAYMENT & PRICING BREAKDOWN
                _buildPaymentSummarySection(paymentMethod, subtotal, shippingFee, discountAmount, grandTotal),
                const SizedBox(height: 20),
              ],
            ),
          ),
        ),

        // 6. STICKY BOTTOM ACTION BAR (Clean & Context-aware)
        _buildBottomActionBar(canCancel, isCompleted, isShipping),
      ],
    );
  }

  Widget _buildStatusHeader(String orderCode, String status, dynamic createdAt) {
    final statusColor = _getStatusColor(status);
    final statusIcon = _getStatusIcon(status);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: AppShadows.card,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(statusIcon, color: statusColor, size: 24),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          FormatUtils.translateStatus(status),
                          style: TextStyle(
                            color: statusColor,
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                            letterSpacing: -0.1,
                          ),
                        ),
                        const Spacer(),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            FormatUtils.formatDate(createdAt, withTime: true),
                            style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w600, color: Color(0xFF64748B)),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        const Text(
                          'Mã đơn: ',
                          style: TextStyle(fontSize: 12.5, color: Color(0xFF64748B)),
                        ),
                        Text(
                          orderCode,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF0F172A),
                            fontFamily: 'monospace',
                          ),
                        ),
                        const SizedBox(width: 4),
                        GestureDetector(
                          onTap: () => _copyOrderCode(orderCode),
                          child: const Padding(
                            padding: EdgeInsets.all(4),
                            child: Icon(Icons.copy_rounded, size: 14, color: AppColors.primary),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildTimelineSection(List<dynamic> histories) {
    // Reverse so the newest event is at the top
    final reversedHistories = histories.reversed.toList();
    final displayList = _isTimelineExpanded
        ? reversedHistories
        : reversedHistories.take(2).toList();

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: AppShadows.card,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF1F5),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.timeline_rounded, color: AppColors.primary, size: 16),
              ),
              const SizedBox(width: 10),
              const Text(
                'Lịch Sử Cập Nhật Đơn Hàng',
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 14,
                  color: Color(0xFF0F172A),
                ),
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  '${histories.length} mốc',
                  style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: Color(0xFF475569)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 12),

          ...displayList.asMap().entries.map((entry) {
            final int index = entry.key;
            final h = entry.value;
            final bool isLatest = index == 0;
            final bool isLastInView = index == displayList.length - 1;
            final String note = h['note'] ?? h['status'] ?? 'Cập nhật trạng thái';
            final String date = FormatUtils.formatDate(h['created_at'], withTime: true);
            final style = _getHistoryItemStyle(note, isLatest);
            final IconData itemIcon = style['icon'] as IconData;
            final Color itemColor = style['color'] as Color;

            return IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Column(
                    children: [
                      Container(
                        width: 24,
                        height: 24,
                        decoration: BoxDecoration(
                          color: isLatest ? itemColor : itemColor.withValues(alpha: 0.12),
                          shape: BoxShape.circle,
                          boxShadow: isLatest
                              ? [
                                  BoxShadow(
                                    color: itemColor.withValues(alpha: 0.35),
                                    blurRadius: 6,
                                    offset: const Offset(0, 2),
                                  ),
                                ]
                              : null,
                        ),
                        child: Icon(
                          itemIcon,
                          size: 13,
                          color: isLatest ? Colors.white : itemColor,
                        ),
                      ),
                      if (!isLastInView)
                        Expanded(
                          child: Container(
                            width: 1.5,
                            margin: const EdgeInsets.symmetric(vertical: 2),
                            color: const Color(0xFFE2E8F0),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Padding(
                      padding: EdgeInsets.only(bottom: isLastInView ? 2 : 14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: Text(
                                  note,
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: isLatest ? FontWeight.w800 : FontWeight.w600,
                                    color: isLatest ? const Color(0xFF0F172A) : const Color(0xFF475569),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Text(
                                date,
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: isLatest ? FontWeight.w600 : FontWeight.w500,
                                  color: isLatest ? AppColors.primary : const Color(0xFF94A3B8),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            );
          }),

          if (histories.length > 2) ...[
            const SizedBox(height: 8),
            GestureDetector(
              onTap: () => setState(() => _isTimelineExpanded = !_isTimelineExpanded),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(vertical: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      _isTimelineExpanded
                          ? 'Thu gọn lịch sử'
                          : 'Xem toàn bộ lịch sử (${histories.length} mốc)',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF475569),
                      ),
                    ),
                    const SizedBox(width: 4),
                    Icon(
                      _isTimelineExpanded ? Icons.keyboard_arrow_up_rounded : Icons.keyboard_arrow_down_rounded,
                      size: 16,
                      color: const Color(0xFF475569),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildAddressSection(dynamic address) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: AppShadows.card,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: const Color(0xFFEFF6FF),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.location_on_rounded, color: Color(0xFF3B82F6), size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      address['recipient_name'] ?? 'Người nhận',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: Color(0xFF0F172A)),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      address['phone'] ?? '',
                      style: const TextStyle(color: Color(0xFF64748B), fontSize: 12.5, fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  '${address['address_line'] ?? ''}, ${address['ward'] ?? ''}, ${address['province'] ?? ''}',
                  style: const TextStyle(fontSize: 12.5, color: Color(0xFF475569), height: 1.35),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildProductsSection(List<dynamic> items, bool isCompleted) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: AppShadows.card,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF1F5),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.shopping_bag_outlined, color: AppColors.primary, size: 16),
              ),
              const SizedBox(width: 10),
              const Text(
                'Sản Phẩm Trong Đơn',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: Color(0xFF0F172A)),
              ),
              const Spacer(),
              Text(
                '${items.length} món',
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF64748B)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 10),

          ...items.asMap().entries.map((entry) {
            final item = entry.value;
            final isLast = entry.key == items.length - 1;
            final name = item['product_name'] ?? item['variant_name'] ?? item['product']?['name'] ?? 'Sản phẩm';
            final qty = item['quantity'] ?? 1;
            final price = item['unit_price'] ?? 0;
            final product = item['product'];
            final variant = item['variant'];
            final variantDesc = [
              if (item['color'] != null && item['color'].toString().isNotEmpty) 'Màu: ${item['color']}',
              if (item['size'] != null && item['size'].toString().isNotEmpty) 'Size: ${item['size']}',
            ].join(' • ');

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
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: _buildProductImage(imageUrl, 56),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            name,
                            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF0F172A)),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                          if (variantDesc.isNotEmpty) ...[
                            const SizedBox(height: 2),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF1F5F9),
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                variantDesc,
                                style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w600, color: Color(0xFF475569)),
                              ),
                            ),
                          ],
                          const SizedBox(height: 4),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('x$qty', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF64748B))),
                              Text(
                                FormatUtils.formatPrice(num.parse(price.toString()) * qty),
                                style: const TextStyle(fontWeight: FontWeight.w800, color: AppColors.primary, fontSize: 13.5),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                if (isCompleted) ...[
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerRight,
                    child: OutlinedButton.icon(
                      onPressed: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ReviewScreen(
                            orderItem: item,
                            productId: item['product_id'] ?? 0,
                            productName: name,
                            productImage: item['thumbnail_url'] ?? item['image_url'],
                          ),
                        ),
                      ),
                      icon: const Icon(Icons.star_rounded, size: 14, color: Color(0xFFD97706)),
                      label: const Text('Đánh giá sản phẩm', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: Color(0xFFD97706))),
                      style: OutlinedButton.styleFrom(
                        side: const BorderSide(color: Color(0xFFFCD34D)),
                        backgroundColor: const Color(0xFFFFFBEB),
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        minimumSize: Size.zero,
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                    ),
                  ),
                ],
                if (!isLast) const Divider(height: 18, color: Color(0xFFF1F5F9)),
              ],
            );
          }),
        ],
      ),
    );
  }

  Widget _buildPaymentSummarySection(String paymentMethod, dynamic subtotal, dynamic shippingFee, dynamic discountAmount, dynamic grandTotal) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: AppShadows.card,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.receipt_long_rounded, color: Color(0xFF3B82F6), size: 16),
              ),
              const SizedBox(width: 10),
              const Text(
                'Chi Tiết Thanh Toán',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: Color(0xFF0F172A)),
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  paymentMethod.toUpperCase(),
                  style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800, color: Color(0xFF334155)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 10),

          _priceRow('Tiền hàng (Tạm tính)', FormatUtils.formatPrice(subtotal)),
          const SizedBox(height: 6),
          _priceRow('Phí vận chuyển', FormatUtils.formatPrice(shippingFee)),
          if (FormatUtils.parseNum(discountAmount) > 0) ...[
            const SizedBox(height: 6),
            _priceRow('Giảm giá voucher / khuyến mãi', '- ${FormatUtils.formatPrice(discountAmount)}', valueColor: const Color(0xFF16A34A)),
          ],
          const SizedBox(height: 10),
          const Divider(height: 1, color: Color(0xFFE2E8F0)),
          const SizedBox(height: 10),

          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Tổng thanh toán',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5, color: Color(0xFF0F172A)),
              ),
              Text(
                FormatUtils.formatPrice(grandTotal),
                style: const TextStyle(
                  fontWeight: FontWeight.w900,
                  color: AppColors.primary,
                  fontSize: 18,
                  letterSpacing: -0.2,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildBottomActionBar(bool canCancel, bool isCompleted, bool isShipping) {
    return Container(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        top: 10,
        bottom: MediaQuery.of(context).padding.bottom + 10,
      ),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 14,
            offset: const Offset(0, -3),
          ),
        ],
      ),
      child: Row(
        children: [
          // Nút huỷ đơn (chỉ hiện khi pending / unconfirmed)
          if (canCancel) ...[
            Expanded(
              child: OutlinedButton(
                onPressed: _isCancelling ? null : _cancelOrder,
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.red,
                  side: const BorderSide(color: Color(0xFFFCA5A5)),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: _isCancelling
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.red))
                    : const Text('Huỷ đơn hàng', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: ElevatedButton.icon(
                onPressed: () => context.push('/chat'),
                icon: const Icon(Icons.chat_bubble_outline_rounded, size: 16),
                label: const Text('Liên hệ shop', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
          ] else if (isCompleted) ...[
            // Đơn đã giao: Nút Hoàn hàng + Mua lại
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
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Yêu cầu hoàn hàng', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: ElevatedButton.icon(
                onPressed: _isReordering ? null : _reOrder,
                icon: _isReordering
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.replay_rounded, size: 16),
                label: const Text('Mua lại', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  elevation: 0,
                ),
              ),
            ),
          ] else ...[
            // Các trạng thái khác: Trả hàng / Đã hủy / Đang giao
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => context.push('/chat'),
                icon: const Icon(Icons.support_agent_rounded, size: 16),
                label: const Text('Hỗ trợ CSKH', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFF334155),
                  side: const BorderSide(color: Color(0xFFCBD5E1)),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: ElevatedButton.icon(
                onPressed: _isReordering ? null : _reOrder,
                icon: _isReordering
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.shopping_bag_outlined, size: 16),
                label: const Text('Mua lại đơn này', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  elevation: 0,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _priceRow(String label, String value, {Color? valueColor}) => Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: Color(0xFF64748B), fontSize: 12.5)),
          Text(
            value,
            style: TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 13,
              color: valueColor ?? const Color(0xFF0F172A),
            ),
          ),
        ],
      );

  String _resolveImageUrl(String raw) {
    return AppConfig.imageUrl(raw);
  }

  Widget _buildProductImage(String imgUrl, double size) {
    if (imgUrl.isEmpty) {
      return Container(
        width: size,
        height: size,
        color: const Color(0xFFF1F5F9),
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
        placeholderBuilder: (_) => Container(
          width: size,
          height: size,
          color: const Color(0xFFF1F5F9),
          child: const Center(
            child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
          ),
        ),
      );
    }

    return CachedNetworkImage(
      imageUrl: imgUrl,
      width: size,
      height: size,
      fit: BoxFit.cover,
      placeholder: (_, _) => Container(
        width: size,
        height: size,
        color: const Color(0xFFF1F5F9),
        child: const Center(
          child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
        ),
      ),
      errorWidget: (_, _, _) => Container(
        width: size,
        height: size,
        color: const Color(0xFFF1F5F9),
        child: const Icon(Icons.image_outlined, color: Color(0xFFCBD5E1), size: 22),
      ),
    );
  }
}
