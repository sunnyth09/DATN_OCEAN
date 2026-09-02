import 'package:flutter/material.dart';
import '../config/app_theme.dart';
import '../services/api_client.dart';
import '../utils/format_utils.dart';

class VoucherSelectionModal extends StatefulWidget {
  final double subtotal;
  final Map<String, dynamic>? currentCoupon;

  const VoucherSelectionModal({
    super.key,
    required this.subtotal,
    this.currentCoupon,
  });

  static Future<Map<String, dynamic>?> show(
    BuildContext context, {
    required double subtotal,
    Map<String, dynamic>? currentCoupon,
  }) {
    return showModalBottomSheet<Map<String, dynamic>?>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => VoucherSelectionModal(
        subtotal: subtotal,
        currentCoupon: currentCoupon,
      ),
    );
  }

  @override
  State<VoucherSelectionModal> createState() => _VoucherSelectionModalState();
}

class _VoucherSelectionModalState extends State<VoucherSelectionModal> {
  final TextEditingController _codeController = TextEditingController();
  List<dynamic> _coupons = [];
  bool _isLoading = true;
  bool _isCheckingManualCode = false;
  Map<String, dynamic>? _selectedCoupon;
  String? _manualErrorMessage;

  @override
  void initState() {
    super.initState();
    _selectedCoupon = widget.currentCoupon;
    if (_selectedCoupon != null) {
      _codeController.text = _selectedCoupon!['code']?.toString() ?? '';
    }
    _fetchAllCoupons();
  }

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _fetchAllCoupons() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiClient().dio.get('/coupons/public');
      if (res.data['status'] == 'success') {
        final rawList = res.data['data'] as List<dynamic>? ?? [];
        if (mounted) {
          setState(() {
            _coupons = rawList;
            _isLoading = false;
          });
        }
      } else {
        if (mounted) setState(() => _isLoading = false);
      }
    } catch (_) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  int _calculateDiscount(Map<String, dynamic> coupon) {
    final subtotal = widget.subtotal;
    final type = coupon['type']?.toString() ?? '';
    final val = num.tryParse(coupon['value']?.toString() ?? '0') ?? 0;
    final maxDisc = num.tryParse(coupon['max_discount_value']?.toString() ?? '0') ?? 0;

    int discount = 0;
    if (type == 'percent') {
      discount = (subtotal * val / 100).round();
      if (maxDisc > 0 && discount > maxDisc) {
        discount = maxDisc.toInt();
      }
    } else if (type == 'free_ship') {
      discount = val > 0 ? val.toInt() : 30000;
    } else {
      discount = val.toInt();
    }
    if (discount > subtotal) {
      discount = subtotal.toInt();
    }
    return discount;
  }

  bool _isEligible(Map<String, dynamic> coupon) {
    final minOrder = num.tryParse(coupon['min_order_value']?.toString() ?? '0') ?? 0;
    return widget.subtotal >= minOrder;
  }

  Future<void> _applyManualCode() async {
    final code = _codeController.text.trim();
    if (code.isEmpty) return;

    setState(() {
      _isCheckingManualCode = true;
      _manualErrorMessage = null;
    });

    // 1. Kiểm tra trong danh sách đã tải
    final found = _coupons.firstWhere(
      (c) => (c['code']?.toString().toLowerCase() == code.toLowerCase()),
      orElse: () => null,
    );

    if (found != null) {
      if (_isEligible(found)) {
        setState(() {
          _selectedCoupon = found;
          _isCheckingManualCode = false;
        });
      } else {
        final minOrder = num.tryParse(found['min_order_value']?.toString() ?? '0') ?? 0;
        final needMore = minOrder - widget.subtotal;
        setState(() {
          _manualErrorMessage = 'Mua thêm ${FormatUtils.formatPrice(needMore)} để dùng mã này!';
          _isCheckingManualCode = false;
        });
      }
      return;
    }

    // 2. Nếu không có trong public list, gọi API kiểm tra
    try {
      final res = await ApiClient().dio.post('/coupons/check', data: {
        'code': code,
        'subtotal': widget.subtotal,
      });

      if (res.data['status'] == 'success' && res.data['data'] != null) {
        final couponData = res.data['data'] as Map<String, dynamic>;
        setState(() {
          _selectedCoupon = couponData;
          _isCheckingManualCode = false;
        });
      } else {
        setState(() {
          _manualErrorMessage = res.data['message'] ?? 'Mã không hợp lệ hoặc đã hết hạn!';
          _isCheckingManualCode = false;
        });
      }
    } catch (e) {
      setState(() {
        _manualErrorMessage = 'Mã giảm giá không hợp lệ hoặc chưa đủ điều kiện!';
        _isCheckingManualCode = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final eligibleCoupons = _coupons.where((c) => _isEligible(c)).toList();
    final ineligibleCoupons = _coupons.where((c) => !_isEligible(c)).toList();

    return Container(
      height: MediaQuery.of(context).size.height * 0.85,
      decoration: const BoxDecoration(
        color: Color(0xFFF8FAFC),
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        children: [
          // ── Header ──
          Container(
            padding: const EdgeInsets.fromLTRB(20, 16, 12, 16),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              border: Border(bottom: BorderSide(color: Color(0xFFF1F5F9))),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Chọn Ocean Voucher',
                  style: TextStyle(
                    fontSize: 16.5,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close_rounded, color: AppColors.textSecondary),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
          ),

          // ── Manual Input Code Bar ──
          Container(
            color: Colors.white,
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    Expanded(
                      child: SizedBox(
                        height: 44,
                        child: TextField(
                          controller: _codeController,
                          textCapitalization: TextCapitalization.characters,
                          style: const TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w700,
                            color: AppColors.textPrimary,
                          ),
                          decoration: InputDecoration(
                            hintText: 'Nhập mã giảm giá...',
                            hintStyle: const TextStyle(
                              fontSize: 13,
                              color: Color(0xFF94A3B8),
                              fontWeight: FontWeight.w400,
                            ),
                            filled: true,
                            fillColor: const Color(0xFFF8FAFC),
                            prefixIcon: const Icon(
                              Icons.confirmation_number_outlined,
                              size: 18,
                              color: Color(0xFF94A3B8),
                            ),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
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
                      ),
                    ),
                    const SizedBox(width: 10),
                    SizedBox(
                      height: 44,
                      child: ElevatedButton(
                        onPressed: _isCheckingManualCode ? null : _applyManualCode,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          padding: const EdgeInsets.symmetric(horizontal: 18),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        child: _isCheckingManualCode
                            ? const SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                              )
                            : const Text('Áp dụng', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800)),
                      ),
                    ),
                  ],
                ),
                if (_manualErrorMessage != null) ...[
                  const SizedBox(height: 6),
                  Text(
                    _manualErrorMessage!,
                    style: const TextStyle(fontSize: 12, color: Color(0xFFEF4444), fontWeight: FontWeight.w600),
                  ),
                ],
              ],
            ),
          ),

          // ── Coupons List ──
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
                : _coupons.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.confirmation_number_outlined, size: 54, color: Colors.grey.shade300),
                            const SizedBox(height: 12),
                            Text('Hiện chưa có voucher nào khả dụng', style: TextStyle(color: Colors.grey.shade600, fontSize: 13.5)),
                          ],
                        ),
                      )
                    : ListView(
                        padding: const EdgeInsets.all(16),
                        children: [
                          if (eligibleCoupons.isNotEmpty) ...[
                            const Text(
                              'MÃ GIẢM GIÁ KHẢ DỤNG',
                              style: TextStyle(
                                fontSize: 12.5,
                                fontWeight: FontWeight.w800,
                                color: AppColors.textSecondary,
                                letterSpacing: 0.3,
                              ),
                            ),
                            const SizedBox(height: 10),
                            ...eligibleCoupons.map((c) => _buildCouponCard(c, isEligible: true)),
                            const SizedBox(height: 16),
                          ],
                          if (ineligibleCoupons.isNotEmpty) ...[
                            const Text(
                              'CHƯA ĐỦ ĐIỀU KIỆN ÁP DỤNG',
                              style: TextStyle(
                                fontSize: 12.5,
                                fontWeight: FontWeight.w800,
                                color: AppColors.textSecondary,
                                letterSpacing: 0.3,
                              ),
                            ),
                            const SizedBox(height: 10),
                            ...ineligibleCoupons.map((c) => _buildCouponCard(c, isEligible: false)),
                          ],
                        ],
                      ),
          ),

          // ── Bottom Confirmation Bar ──
          Container(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.06),
                  blurRadius: 10,
                  offset: const Offset(0, -3),
                ),
              ],
            ),
            child: SafeArea(
              top: false,
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          _selectedCoupon != null
                              ? 'Đã chọn: ${_selectedCoupon!['code']}'
                              : 'Chưa chọn voucher',
                          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
                        ),
                        if (_selectedCoupon != null) ...[
                          const SizedBox(height: 2),
                          Text(
                            'Tiết kiệm: ${FormatUtils.formatPrice(_calculateDiscount(_selectedCoupon!))}',
                            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.primary),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  SizedBox(
                    height: 44,
                    width: 140,
                    child: ElevatedButton(
                      onPressed: () {
                        Navigator.pop(context, _selectedCoupon ?? const <String, dynamic>{});
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text(
                        'ĐỒNG Ý',
                        style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCouponCard(Map<String, dynamic> coupon, {required bool isEligible}) {
    final code = coupon['code']?.toString() ?? '';
    final type = coupon['type']?.toString() ?? '';
    final value = coupon['value'];
    final minOrder = num.tryParse(coupon['min_order_value']?.toString() ?? '0') ?? 0;
    final endDate = coupon['end_date']?.toString() ?? '';
    final isSelected = _selectedCoupon != null && _selectedCoupon!['code'] == code;

    String valueTitle = '';
    if (type == 'percent') {
      valueTitle = 'Giảm $value%';
    } else if (type == 'free_ship') {
      valueTitle = 'Miễn phí vận chuyển';
    } else {
      valueTitle = 'Giảm ${FormatUtils.formatPrice(num.tryParse(value?.toString() ?? '0') ?? 0)}';
    }

    final calculatedDiscount = _calculateDiscount(coupon);

    return Opacity(
      opacity: isEligible ? 1.0 : 0.55,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isSelected ? AppColors.primary : const Color(0xFFE2E8F0),
            width: isSelected ? 1.6 : 1.0,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: InkWell(
          onTap: isEligible
              ? () {
                  setState(() {
                    if (isSelected) {
                      _selectedCoupon = null;
                      _codeController.clear();
                    } else {
                      _selectedCoupon = coupon;
                      _codeController.text = code;
                    }
                  });
                }
              : null,
          borderRadius: BorderRadius.circular(14),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Icon tag
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: isEligible ? const Color(0xFFFFF1F2) : const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Center(
                    child: Icon(
                      type == 'free_ship' ? Icons.local_shipping_outlined : Icons.confirmation_number_outlined,
                      color: isEligible ? AppColors.primary : const Color(0xFF94A3B8),
                      size: 22,
                    ),
                  ),
                ),
                const SizedBox(width: 12),

                // Coupon Info
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFF1F2),
                              borderRadius: BorderRadius.circular(4),
                              border: Border.all(color: const Color(0xFFFFD1DC)),
                            ),
                            child: Text(
                              code,
                              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: AppColors.primary),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            valueTitle,
                            style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w800, color: AppColors.textPrimary),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        minOrder > 0 ? 'Đơn tối thiểu ${FormatUtils.formatPrice(minOrder)}' : 'Không giới hạn đơn tối thiểu',
                        style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                      ),
                      if (endDate.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          'HSD: ${endDate.split('T')[0]}',
                          style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
                        ),
                      ],
                      if (!isEligible) ...[
                        const SizedBox(height: 4),
                        Text(
                          'Mua thêm ${FormatUtils.formatPrice(minOrder - widget.subtotal)} để dùng mã',
                          style: const TextStyle(fontSize: 11.5, color: Color(0xFFEA580C), fontWeight: FontWeight.w700),
                        ),
                      ] else if (calculatedDiscount > 0) ...[
                        const SizedBox(height: 4),
                        Text(
                          'Tiết kiệm: ${FormatUtils.formatPrice(calculatedDiscount)}',
                          style: const TextStyle(fontSize: 11.5, color: Color(0xFF059669), fontWeight: FontWeight.w700),
                        ),
                      ],
                    ],
                  ),
                ),

                // Radio Button
                if (isEligible)
                  Padding(
                    padding: const EdgeInsets.only(left: 8, top: 4),
                    child: Icon(
                      isSelected ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
                      color: isSelected ? AppColors.primary : const Color(0xFFCBD5E1),
                      size: 22,
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
