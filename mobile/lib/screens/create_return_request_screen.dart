import 'dart:io';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:dio/dio.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../services/api_client.dart';
import '../router/app_router.dart';
import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../utils/format_utils.dart';
import '../widgets/app_toast.dart';

class CreateReturnRequestScreen extends StatefulWidget {
  final String orderId;
  const CreateReturnRequestScreen({super.key, required this.orderId});

  @override
  State<CreateReturnRequestScreen> createState() => _CreateReturnRequestScreenState();
}

class _CreateReturnRequestScreenState extends State<CreateReturnRequestScreen> {
  // Order Data & State
  Map<String, dynamic>? orderData;
  List<dynamic> orderItems = [];
  bool isLoadingOrder = true;
  String? loadErrorMessage;

  // Selected items: order_item_id -> return quantity
  final Map<int, int> _selectedQuantities = {};

  // Preset Reasons with dedicated icons
  final List<Map<String, dynamic>> presetReasons = [
    {
      'title': 'Sản phẩm bị lỗi / hư hỏng',
      'desc': 'Rách, bung chỉ, hỏng khóa, bể vỡ khi nhận hàng',
      'icon': Icons.handyman_outlined,
    },
    {
      'title': 'Giao sai sản phẩm',
      'desc': 'Sai mẫu mã, sai màu sắc hoặc sai kích cỡ (size)',
      'icon': Icons.sync_problem_outlined,
    },
    {
      'title': 'Sản phẩm không giống mô tả',
      'desc': 'Chất liệu, kiểu dáng khác biệt so với hình ảnh trên app',
      'icon': Icons.visibility_off_outlined,
    },
    {
      'title': 'Thiếu phụ kiện / quà tặng',
      'desc': 'Đơn hàng thiếu món kèm theo hoặc thiếu quà tặng khuyến mãi',
      'icon': Icons.card_giftcard_outlined,
    },
    {
      'title': 'Lý do khác...',
      'desc': 'Các vấn đề phát sinh khác cần cửa hàng hỗ trợ',
      'icon': Icons.edit_note_outlined,
    },
  ];
  String? selectedReason;

  // Refund Method: 'wallet', 'bank_transfer', 'original_payment'
  String selectedRefundMethod = 'wallet';

  // Shipping Method: 'pickup_original_address', 'dropoff_post_office'
  String selectedShippingMethod = 'pickup_original_address';

  final TextEditingController _descriptionCtrl = TextEditingController();
  final List<File> _selectedImages = [];
  final ImagePicker _picker = ImagePicker();
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _fetchOrderDetail();
  }

  @override
  void dispose() {
    _descriptionCtrl.dispose();
    super.dispose();
  }

  Future<void> _fetchOrderDetail() async {
    setState(() {
      isLoadingOrder = true;
      loadErrorMessage = null;
    });

    try {
      final response = await ApiClient().dio.get('/profile/orders/${widget.orderId}');
      if (response.statusCode == 200 && response.data != null) {
        final data = response.data['data'] as Map<String, dynamic>?;
        if (data != null) {
          final items = (data['items'] as List<dynamic>?) ?? [];
          setState(() {
            orderData = data;
            orderItems = items;
            // Default: select all items with their returnable quantity
            for (var rawItem in items) {
              if (rawItem is Map) {
                final int orderItemId = rawItem['order_item_id'] ?? rawItem['id'] ?? 0;
                final int totalQty = rawItem['quantity'] ?? 1;
                final int returnedQty = rawItem['returned_quantity'] ?? 0;
                final int returnableQty = (totalQty - returnedQty).clamp(0, totalQty);
                if (orderItemId > 0 && returnableQty > 0) {
                  _selectedQuantities[orderItemId] = returnableQty;
                }
              }
            }
            isLoadingOrder = false;
          });
          return;
        }
      }
      setState(() {
        isLoadingOrder = false;
        loadErrorMessage = 'Không tìm thấy thông tin đơn hàng.';
      });
    } on DioException catch (e) {
      if (mounted) {
        setState(() {
          isLoadingOrder = false;
          loadErrorMessage = e.response?.data is Map && e.response?.data['message'] != null
              ? e.response?.data['message']
              : 'Không thể tải chi tiết đơn hàng. Vui lòng thử lại.';
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          isLoadingOrder = false;
          loadErrorMessage = 'Lỗi kết nối máy chủ.';
        });
      }
    }
  }

  Future<void> _pickImages() async {
    if (_selectedImages.length >= 5) {
      AppToast.showWarning(context, message: 'Chỉ được chọn tối đa 5 ảnh minh chứng');
      return;
    }

    try {
      final List<XFile> images = await _picker.pickMultiImage(imageQuality: 75);
      if (images.isNotEmpty) {
        setState(() {
          for (var img in images) {
            if (_selectedImages.length < 5) {
              _selectedImages.add(File(img.path));
            }
          }
        });
      }
    } catch (_) {
      if (mounted) {
        AppToast.showError(context, message: 'Không thể chọn ảnh');
      }
    }
  }

  void _removeImage(int index) {
    setState(() {
      _selectedImages.removeAt(index);
    });
  }

  String _resolveImageUrl(dynamic item) {
    if (item is! Map) return '';
    final variant = item['variant'];
    if (variant is Map && variant['image_url'] != null && variant['image_url'].toString().isNotEmpty) {
      return AppConfig.imageUrl(variant['image_url'].toString());
    }
    final product = item['product'];
    if (product is Map) {
      return AppConfig.productImageUrl(product);
    }
    final directUrl = item['thumbnail_url']?.toString() ?? item['image_url']?.toString() ?? '';
    return AppConfig.imageUrl(directUrl);
  }

  bool get _isAllSelected {
    if (orderItems.isEmpty) return false;
    for (var rawItem in orderItems) {
      if (rawItem is Map) {
        final int orderItemId = rawItem['order_item_id'] ?? rawItem['id'] ?? 0;
        final int totalQty = rawItem['quantity'] ?? 1;
        final int returnedQty = rawItem['returned_quantity'] ?? 0;
        final int returnableQty = (totalQty - returnedQty).clamp(0, totalQty);
        if (returnableQty > 0 && (_selectedQuantities[orderItemId] ?? 0) <= 0) {
          return false;
        }
      }
    }
    return true;
  }

  void _toggleSelectAll() {
    setState(() {
      if (_isAllSelected) {
        _selectedQuantities.clear();
      } else {
        for (var rawItem in orderItems) {
          if (rawItem is Map) {
            final int orderItemId = rawItem['order_item_id'] ?? rawItem['id'] ?? 0;
            final int totalQty = rawItem['quantity'] ?? 1;
            final int returnedQty = rawItem['returned_quantity'] ?? 0;
            final int returnableQty = (totalQty - returnedQty).clamp(0, totalQty);
            if (orderItemId > 0 && returnableQty > 0) {
              _selectedQuantities[orderItemId] = returnableQty;
            }
          }
        }
      }
    });
  }

  num get _estimatedRefundTotal {
    num total = 0;
    for (var rawItem in orderItems) {
      if (rawItem is Map) {
        final int orderItemId = rawItem['order_item_id'] ?? rawItem['id'] ?? 0;
        final int selectedQty = _selectedQuantities[orderItemId] ?? 0;
        if (selectedQty > 0) {
          final unitPrice = FormatUtils.parseNum(rawItem['unit_price'] ?? rawItem['price'] ?? 0);
          total += unitPrice * selectedQty;
        }
      }
    }
    return total;
  }

  int get _selectedItemCount {
    int count = 0;
    _selectedQuantities.forEach((_, q) {
      if (q > 0) count += q;
    });
    return count;
  }

  Future<void> _submit() async {
    // 1. Validate items
    final List<Map<String, dynamic>> returnItems = [];
    _selectedQuantities.forEach((orderItemId, qty) {
      if (qty > 0) {
        returnItems.add({
          'order_item_id': orderItemId,
          'quantity': qty,
        });
      }
    });

    if (returnItems.isEmpty) {
      AppToast.showWarning(context, message: 'Vui lòng chọn ít nhất một sản phẩm cần hoàn!');
      return;
    }

    // 2. Validate reason
    if (selectedReason == null || selectedReason!.isEmpty) {
      AppToast.showWarning(context, message: 'Vui lòng chọn lý do hoàn hàng!');
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final Map<String, dynamic> dataMap = {
        'reason': selectedReason,
        'refund_method': selectedRefundMethod,
        'return_shipping_method': selectedShippingMethod,
        'idempotency_key': 'return-${widget.orderId}-${DateTime.now().millisecondsSinceEpoch}',
      };

      if (_descriptionCtrl.text.trim().isNotEmpty) {
        dataMap['description'] = _descriptionCtrl.text.trim();
      }

      for (int i = 0; i < returnItems.length; i++) {
        dataMap['items[$i][order_item_id]'] = returnItems[i]['order_item_id'];
        dataMap['items[$i][quantity]'] = returnItems[i]['quantity'];
      }

      final formData = FormData.fromMap(dataMap);

      for (var i = 0; i < _selectedImages.length; i++) {
        formData.files.add(
          MapEntry(
            'images[]',
            await MultipartFile.fromFile(
              _selectedImages[i].path,
              filename: 'return_evidence_${DateTime.now().millisecondsSinceEpoch}_$i.jpg',
            ),
          ),
        );
      }

      final response = await ApiClient().dio.post(
        '/orders/${widget.orderId}/return-request',
        data: formData,
      );

      if (!mounted) return;
      if (response.statusCode == 200 || response.statusCode == 201) {
        if (context.canPop()) {
          context.pop(true);
        } else {
          context.go('/orders');
        }
        final rootCtx = rootNavigatorKey.currentContext;
        if (rootCtx != null && rootCtx.mounted) {
          AppToast.showSuccess(
            rootCtx,
            message: 'Đã gửi yêu cầu hoàn hàng thành công!',
          );
        }
      }
    } on DioException catch (e) {
      if (mounted) {
        String errorMsg = 'Không thể gửi yêu cầu hoàn hàng!';
        if (e.response?.data is Map) {
          final data = e.response!.data as Map;
          if (data['errors'] is Map) {
            final errors = data['errors'] as Map;
            final firstErrorList = errors.values.firstWhere((v) => v is List && v.isNotEmpty, orElse: () => null);
            if (firstErrorList != null && firstErrorList is List) {
              errorMsg = firstErrorList.first.toString();
            } else if (data['message'] != null) {
              errorMsg = data['message'].toString();
            }
          } else if (data['message'] != null) {
            errorMsg = data['message'].toString();
          }
        }
        AppToast.showError(context, message: errorMsg);
      }
    } catch (_) {
      if (mounted) {
        AppToast.showError(context, message: 'Lỗi kết nối tới máy chủ!');
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final orderCode = orderData?['order_code'] ?? '#${widget.orderId}';

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text(
          'Yêu Cầu Hoàn Hàng',
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
      ),
      body: isLoadingOrder
          ? const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CircularProgressIndicator(strokeWidth: 2.5, color: AppColors.primary),
                  SizedBox(height: 14),
                  Text(
                    'Đang nạp thông tin đơn hàng...',
                    style: TextStyle(color: Color(0xFF64748B), fontSize: 13.5, fontWeight: FontWeight.w500),
                  ),
                ],
              ),
            )
          : loadErrorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFEE2E2),
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.error_outline_rounded, color: Color(0xFFEF4444), size: 36),
                        ),
                        const SizedBox(height: 14),
                        Text(
                          loadErrorMessage!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600, color: Color(0xFF334155)),
                        ),
                        const SizedBox(height: 18),
                        ElevatedButton.icon(
                          onPressed: _fetchOrderDetail,
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
                )
              : Column(
                  children: [
                    Expanded(
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // 0. ORDER SUMMARY HERO BANNER
                            _buildOrderHeaderBanner(orderCode),
                            const SizedBox(height: 16),

                            // 1. SELECT PRODUCTS TO RETURN
                            _buildProductsSection(),
                            const SizedBox(height: 16),

                            // 2. SELECT REASON
                            _buildReasonSection(),
                            const SizedBox(height: 16),

                            // 3. REFUND METHOD
                            _buildRefundMethodSection(),
                            const SizedBox(height: 16),

                            // 4. SHIPPING METHOD
                            _buildShippingMethodSection(),
                            const SizedBox(height: 16),

                            // 5. DESCRIPTION
                            _buildDescriptionSection(),
                            const SizedBox(height: 16),

                            // 6. IMAGES EVIDENCE
                            _buildEvidenceSection(),
                            const SizedBox(height: 24),
                          ],
                        ),
                      ),
                    ),

                    // STICKY BOTTOM BAR
                    _buildStickyBottomBar(),
                  ],
                ),
    );
  }

  Widget _buildOrderHeaderBanner(String orderCode) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFFFF1F5), Color(0xFFFFF0F3)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFFECDD3)),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFFE63B6F).withValues(alpha: 0.12),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: const Icon(Icons.verified_user_rounded, color: AppColors.primary, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      orderCode,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 13.5,
                        color: Color(0xFF0F172A),
                        fontFamily: 'monospace',
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: const Color(0xFFDCFCE7),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: const Text(
                        'Đã giao',
                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Color(0xFF15803D)),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 2),
                const Text(
                  'Bảo đảm đổi trả trong 7 ngày • Miễn phí vận chuyển',
                  style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w500, color: Color(0xFF64748B)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader({
    required String title,
    required String subtitle,
    required IconData icon,
    Widget? trailing,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Container(
          width: 30,
          height: 30,
          decoration: BoxDecoration(
            color: const Color(0xFFFFF1F5),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, color: AppColors.primary, size: 17),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 14.5,
                  color: Color(0xFF0F172A),
                  letterSpacing: -0.1,
                ),
              ),
              Text(
                subtitle,
                style: const TextStyle(fontSize: 11.5, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
              ),
            ],
          ),
        ),
        ?trailing,
      ],
    );
  }

  Widget _buildProductsSection() {
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
          _buildSectionHeader(
            title: 'Sản Phẩm Hoàn Trả *',
            subtitle: 'Đã chọn $_selectedItemCount sản phẩm',
            icon: Icons.inventory_2_outlined,
            trailing: TextButton(
              onPressed: _toggleSelectAll,
              style: TextButton.styleFrom(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: Text(
                _isAllSelected ? 'Bỏ chọn' : 'Chọn tất cả',
                style: const TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: AppColors.primary,
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 10),

          ...orderItems.map((rawItem) {
            if (rawItem is! Map) return const SizedBox.shrink();
            final int orderItemId = rawItem['order_item_id'] ?? rawItem['id'] ?? 0;
            final String name = rawItem['product_name'] ?? rawItem['variant_name'] ?? rawItem['product']?['name'] ?? 'Sản phẩm';
            final String variantDesc = [
              if (rawItem['color'] != null && rawItem['color'].toString().isNotEmpty) 'Màu: ${rawItem['color']}',
              if (rawItem['size'] != null && rawItem['size'].toString().isNotEmpty) 'Size: ${rawItem['size']}',
            ].join(' • ');

            final int totalQty = rawItem['quantity'] ?? 1;
            final int returnedQty = rawItem['returned_quantity'] ?? 0;
            final int returnableQty = (totalQty - returnedQty).clamp(0, totalQty);
            final int currentQty = _selectedQuantities[orderItemId] ?? 0;
            final bool isSelected = currentQty > 0;
            final String imgUrl = _resolveImageUrl(rawItem);
            final unitPrice = rawItem['unit_price'] ?? 0;

            if (returnableQty <= 0) {
              return Opacity(
                opacity: 0.5,
                child: Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF8FAFC),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.check_box_outline_blank, color: Color(0xFFCBD5E1), size: 20),
                      const SizedBox(width: 10),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: imgUrl.isNotEmpty
                            ? CachedNetworkImage(imageUrl: imgUrl, width: 44, height: 44, fit: BoxFit.cover)
                            : Container(width: 44, height: 44, color: const Color(0xFFE2E8F0)),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                            const SizedBox(height: 2),
                            const Text('Đã hoàn trả toàn bộ', style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8))),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              );
            }

            return AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: isSelected ? const Color(0xFFFFF9FA) : Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: isSelected ? const Color(0xFFFECDD3) : const Color(0xFFE2E8F0),
                  width: isSelected ? 1.5 : 1,
                ),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Checkbox(
                    value: isSelected,
                    activeColor: AppColors.primary,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(5)),
                    side: const BorderSide(color: Color(0xFF94A3B8), width: 1.5),
                    onChanged: (val) {
                      setState(() {
                        if (val == true) {
                          _selectedQuantities[orderItemId] = returnableQty;
                        } else {
                          _selectedQuantities.remove(orderItemId);
                        }
                      });
                    },
                  ),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: imgUrl.isNotEmpty
                        ? CachedNetworkImage(
                            imageUrl: imgUrl,
                            width: 52,
                            height: 52,
                            fit: BoxFit.cover,
                            errorWidget: (context, url, error) => Container(
                              width: 52,
                              height: 52,
                              color: const Color(0xFFF1F5F9),
                              child: const Icon(Icons.image_outlined, color: Colors.grey),
                            ),
                          )
                        : Container(
                            width: 52,
                            height: 52,
                            color: const Color(0xFFF1F5F9),
                            child: const Icon(Icons.image_outlined, color: Colors.grey),
                          ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF0F172A),
                          ),
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
                        const SizedBox(height: 3),
                        Text(
                          FormatUtils.formatPrice(unitPrice),
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: AppColors.primary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (isSelected && returnableQty > 1) ...[
                    Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        border: Border.all(color: const Color(0xFFCBD5E1)),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          GestureDetector(
                            onTap: () {
                              if (currentQty > 1) {
                                setState(() => _selectedQuantities[orderItemId] = currentQty - 1);
                              }
                            },
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                              child: const Icon(Icons.remove_rounded, size: 16, color: Color(0xFF64748B)),
                            ),
                          ),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 6),
                            child: Text(
                              '$currentQty',
                              style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                            ),
                          ),
                          GestureDetector(
                            onTap: () {
                              if (currentQty < returnableQty) {
                                setState(() => _selectedQuantities[orderItemId] = currentQty + 1);
                              }
                            },
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                              child: const Icon(Icons.add_rounded, size: 16, color: Color(0xFF64748B)),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ] else if (isSelected) ...[
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        'x$currentQty',
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF334155)),
                      ),
                    ),
                  ],
                ],
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildReasonSection() {
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
          _buildSectionHeader(
            title: 'Lý Do Hoàn Hàng *',
            subtitle: 'Chọn lý do chính xác giúp yêu cầu được duyệt nhanh hơn',
            icon: Icons.help_outline_rounded,
          ),
          const SizedBox(height: 12),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 10),

          ...presetReasons.map((reasonMap) {
            final String reasonTitle = reasonMap['title'] as String;
            final String reasonDesc = reasonMap['desc'] as String;
            final IconData reasonIcon = reasonMap['icon'] as IconData;
            final isSelected = selectedReason == reasonTitle;

            return GestureDetector(
              onTap: () => setState(() => selectedReason = reasonTitle),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                decoration: BoxDecoration(
                  color: isSelected ? const Color(0xFFFFF1F5) : Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: isSelected ? AppColors.primary : const Color(0xFFE2E8F0),
                    width: isSelected ? 1.5 : 1,
                  ),
                ),
                child: Row(
                  children: [
                    Icon(
                      isSelected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
                      color: isSelected ? AppColors.primary : const Color(0xFF94A3B8),
                      size: 19,
                    ),
                    const SizedBox(width: 10),
                    Icon(
                      reasonIcon,
                      size: 18,
                      color: isSelected ? AppColors.primary : const Color(0xFF64748B),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            reasonTitle,
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                              color: isSelected ? AppColors.primary : const Color(0xFF1E293B),
                            ),
                          ),
                          const SizedBox(height: 1),
                          Text(
                            reasonDesc,
                            style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildRefundMethodSection() {
    final refundOptions = [
      {
        'value': 'wallet',
        'title': 'Ví Ocean Wallet',
        'badge': 'Khuyên dùng • Tức thì',
        'desc': 'Tiền hoàn cộng ngay vào ví sau khi duyệt, có thể rút về ngân hàng',
        'icon': Icons.account_balance_wallet_rounded,
      },
      {
        'value': 'bank_transfer',
        'title': 'Tài khoản ngân hàng',
        'badge': '1 - 3 ngày',
        'desc': 'Cửa hàng chuyển khoản trực tiếp qua STK bạn cung cấp',
        'icon': Icons.account_balance_rounded,
      },
      {
        'value': 'original_payment',
        'title': 'Kênh thanh toán ban đầu',
        'badge': 'Tự động',
        'desc': 'Hoàn trả theo cổng VNPay, MoMo hoặc Thẻ đã thanh toán đơn',
        'icon': Icons.credit_card_rounded,
      },
    ];

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
          _buildSectionHeader(
            title: 'Phương Thức Nhận Hoàn Tiền *',
            subtitle: 'Lựa chọn kênh bạn muốn nhận tiền hoàn lại',
            icon: Icons.payments_outlined,
          ),
          const SizedBox(height: 12),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 10),

          ...refundOptions.map((opt) {
            final isSelected = selectedRefundMethod == opt['value'];
            return GestureDetector(
              onTap: () => setState(() => selectedRefundMethod = opt['value'] as String),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                decoration: BoxDecoration(
                  color: isSelected ? const Color(0xFFFFF1F5) : Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: isSelected ? AppColors.primary : const Color(0xFFE2E8F0),
                    width: isSelected ? 1.5 : 1,
                  ),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Icon(
                        isSelected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
                        color: isSelected ? AppColors.primary : const Color(0xFF94A3B8),
                        size: 19,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: isSelected ? Colors.white : const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Icon(
                        opt['icon'] as IconData,
                        size: 18,
                        color: isSelected ? AppColors.primary : const Color(0xFF475569),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Text(
                                opt['title'] as String,
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: isSelected ? FontWeight.w800 : FontWeight.w700,
                                  color: isSelected ? AppColors.primary : const Color(0xFF1E293B),
                                ),
                              ),
                              const SizedBox(width: 6),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                                decoration: BoxDecoration(
                                  color: opt['value'] == 'wallet'
                                      ? const Color(0xFFDCFCE7)
                                      : const Color(0xFFF1F5F9),
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: Text(
                                  opt['badge'] as String,
                                  style: TextStyle(
                                    fontSize: 9.5,
                                    fontWeight: FontWeight.w700,
                                    color: opt['value'] == 'wallet'
                                        ? const Color(0xFF16A34A)
                                        : const Color(0xFF64748B),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 2),
                          Text(
                            opt['desc'] as String,
                            style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildShippingMethodSection() {
    final shippingOptions = [
      {
        'value': 'pickup_original_address',
        'title': 'Shipper đến lấy hàng tận nơi',
        'badge': 'Tiện lợi nhất',
        'desc': 'Đơn vị vận chuyển OceanExpress đến lấy hàng tại địa chỉ giao ban đầu',
        'icon': Icons.local_shipping_rounded,
      },
      {
        'value': 'dropoff_post_office',
        'title': 'Tự mang hàng ra bưu cục',
        'badge': 'Chủ động',
        'desc': 'Bạn tự mang hàng ra bưu cục gần nhất để gửi về shop',
        'icon': Icons.storefront_rounded,
      },
    ];

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
          _buildSectionHeader(
            title: 'Cách Thức Gửi Hàng Hoàn *',
            subtitle: 'Phương án chuyển hàng trả lại cho cửa hàng',
            icon: Icons.local_shipping_outlined,
          ),
          const SizedBox(height: 12),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 10),

          ...shippingOptions.map((opt) {
            final isSelected = selectedShippingMethod == opt['value'];
            return GestureDetector(
              onTap: () => setState(() => selectedShippingMethod = opt['value'] as String),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                decoration: BoxDecoration(
                  color: isSelected ? const Color(0xFFFFF1F5) : Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: isSelected ? AppColors.primary : const Color(0xFFE2E8F0),
                    width: isSelected ? 1.5 : 1,
                  ),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Icon(
                        isSelected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
                        color: isSelected ? AppColors.primary : const Color(0xFF94A3B8),
                        size: 19,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: isSelected ? Colors.white : const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Icon(
                        opt['icon'] as IconData,
                        size: 18,
                        color: isSelected ? AppColors.primary : const Color(0xFF475569),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Text(
                                opt['title'] as String,
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: isSelected ? FontWeight.w800 : FontWeight.w700,
                                  color: isSelected ? AppColors.primary : const Color(0xFF1E293B),
                                ),
                              ),
                              const SizedBox(width: 6),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF1F5F9),
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: Text(
                                  opt['badge'] as String,
                                  style: const TextStyle(
                                    fontSize: 9.5,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF64748B),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 2),
                          Text(
                            opt['desc'] as String,
                            style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildDescriptionSection() {
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
          _buildSectionHeader(
            title: 'Mô Tả Thêm (Tùy chọn)',
            subtitle: 'Ghi chú chi tiết về tình trạng hàng để hỗ trợ nhanh hơn',
            icon: Icons.notes_rounded,
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _descriptionCtrl,
            maxLines: 3,
            maxLength: 2000,
            style: const TextStyle(fontSize: 13.5, color: Color(0xFF1E293B)),
            decoration: InputDecoration(
              hintText: 'Ví dụ: Sản phẩm bị rách ở phần tay áo, kích cỡ không vừa...',
              hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12.5),
              filled: true,
              fillColor: const Color(0xFFF8FAFC),
              contentPadding: const EdgeInsets.all(12),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
              ),
              enabledBorder: OutlineInputBorder(
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
      ),
    );
  }

  Widget _buildEvidenceSection() {
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
          _buildSectionHeader(
            title: 'Hình Ảnh Minh Chứng',
            subtitle: 'Tải lên tối đa 5 ảnh chụp rõ vị trí lỗi hoặc tem mác',
            icon: Icons.photo_library_outlined,
            trailing: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                '${_selectedImages.length}/5 ảnh',
                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF475569)),
              ),
            ),
          ),
          const SizedBox(height: 12),
          const Divider(height: 1, color: Color(0xFFF1F5F9)),
          const SizedBox(height: 12),

          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              ...List.generate(_selectedImages.length, (index) {
                return Stack(
                  clipBehavior: Clip.none,
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: Image.file(
                        _selectedImages[index],
                        width: 72,
                        height: 72,
                        fit: BoxFit.cover,
                      ),
                    ),
                    Positioned(
                      top: -6,
                      right: -6,
                      child: GestureDetector(
                        onTap: () => _removeImage(index),
                        child: Container(
                          padding: const EdgeInsets.all(3),
                          decoration: const BoxDecoration(
                            color: Color(0xFFEF4444),
                            shape: BoxShape.circle,
                            boxShadow: [
                              BoxShadow(color: Colors.black26, blurRadius: 4),
                            ],
                          ),
                          child: const Icon(Icons.close_rounded, color: Colors.white, size: 13),
                        ),
                      ),
                    ),
                  ],
                );
              }),
              if (_selectedImages.length < 5)
                GestureDetector(
                  onTap: _pickImages,
                  child: Container(
                    width: 72,
                    height: 72,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF1F5),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: const Color(0xFFFECDD3),
                        style: BorderStyle.solid,
                      ),
                    ),
                    child: const Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.add_a_photo_outlined, color: AppColors.primary, size: 22),
                        SizedBox(height: 4),
                        Text(
                          'Thêm ảnh',
                          style: TextStyle(
                            color: AppColors.primary,
                            fontSize: 10.5,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Row(
              children: [
                Icon(Icons.lightbulb_outline_rounded, color: Color(0xFFF59E0B), size: 16),
                SizedBox(width: 6),
                Expanded(
                  child: Text(
                    'Mẹo: Chụp rõ tem mác và chi tiết lỗi để được duyệt trong 24h.',
                    style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStickyBottomBar() {
    return Container(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        top: 12,
        bottom: MediaQuery.of(context).padding.bottom + 12,
      ),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Tiền hoàn dự kiến:', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                  Text('(Đã tính theo số lượng chọn)', style: TextStyle(fontSize: 10, color: Color(0xFF94A3B8))),
                ],
              ),
              Text(
                FormatUtils.formatPrice(_estimatedRefundTotal),
                style: const TextStyle(
                  fontSize: 16.5,
                  fontWeight: FontWeight.w900,
                  color: AppColors.primary,
                  letterSpacing: -0.2,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton(
              onPressed: _isSubmitting ? null : _submit,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: _isSubmitting
                  ? const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
                        ),
                        SizedBox(width: 10),
                        Text(
                          'Đang gửi yêu cầu...',
                          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5),
                        ),
                      ],
                    )
                  : Text(
                      'Gửi Yêu Cầu Hoàn Hàng ($_selectedItemCount SP)',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}
