import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../services/api_client.dart';
import '../config/app_theme.dart';

class MyCouponsScreen extends StatefulWidget {
  const MyCouponsScreen({super.key});

  @override
  State<MyCouponsScreen> createState() => _MyCouponsScreenState();
}

class _MyCouponsScreenState extends State<MyCouponsScreen> {
  List<dynamic> coupons = [];
  bool isLoading = true;

  @override
  void initState() {
    super.initState();
    fetchUserCoupons();
  }

  Future<void> fetchUserCoupons() async {
    try {
      setState(() => isLoading = true);
      final res = await ApiClient().dio.get('/profile/coupons');
      if (res.data['status'] == 'success') {
        if (mounted) setState(() { coupons = res.data['data'] ?? []; isLoading = false; });
      } else {
        if (mounted) setState(() => isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => isLoading = false);
    }
  }

  bool _isExpired(dynamic endDate) {
    if (endDate == null) return false;
    final d = DateTime.tryParse(endDate.toString());
    return d != null && d.isBefore(DateTime.now());
  }

  String _formatValue(Map<String, dynamic> coupon) {
    final type = coupon['type']?.toString() ?? '';
    final value = coupon['value'];
    if (type == 'percent') return 'Giảm $value%';
    if (type == 'free_ship') return 'Freeship ${_formatCurrency(value)}';
    return 'Giảm ${_formatCurrency(value)}';
  }

  String _formatCurrency(dynamic val) {
    if (val == null) return '0₫';
    try {
      final num p = num.parse(val.toString());
      final formatted = p.toStringAsFixed(0).replaceAllMapped(
        RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
        (m) => '${m[1]}.',
      );
      return '$formatted₫';
    } catch (_) {
      return '$val₫';
    }
  }

  String _formatDate(dynamic dateString) {
    if (dateString == null) return 'Vô thời hạn';
    final d = DateTime.tryParse(dateString.toString());
    if (d == null) return 'Vô thời hạn';
    return '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}';
  }

  void _copyCode(String code) {
    Clipboard.setData(ClipboardData(text: code));
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Đã sao chép mã: $code'),
        backgroundColor: AppColors.success,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Mã giảm giá của tôi'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
      ),
      body: RefreshIndicator(
        onRefresh: fetchUserCoupons,
        child: isLoading
            ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
            : coupons.isEmpty
                ? _buildEmpty()
                : ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: coupons.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 12),
                    itemBuilder: (context, index) => _buildCouponCard(coupons[index]),
                  ),
      ),
    );
  }

  Widget _buildEmpty() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: const Color(0xFFF8F9FA),
              shape: BoxShape.circle,
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: const Icon(Icons.confirmation_number_outlined, size: 48, color: Color(0xFFCBD5E1)),
          ),
          const SizedBox(height: 20),
          const Text('Bạn chưa lưu mã nào', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Color(0xFF374151))),
          const SizedBox(height: 8),
          const Text('Hãy khám phá kho voucher để nhận ưu đãi hấp dẫn', style: TextStyle(fontSize: 13, color: Color(0xFF9CA3AF))),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: () => context.pop(),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
              elevation: 0,
              padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 12),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            child: const Text('Khám phá ngay', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
        ],
      ),
    );
  }

  Widget _buildCouponCard(Map<String, dynamic> coupon) {
    final type = coupon['type']?.toString() ?? 'fixed';
    final code = coupon['code']?.toString() ?? '';
    final expired = _isExpired(coupon['end_date']);

    IconData typeIcon;
    Color typeBg;
    Color typeColor;
    switch (type) {
      case 'free_ship':
        typeIcon = Icons.local_shipping_outlined;
        typeBg = const Color(0xFFE0F2FE);
        typeColor = const Color(0xFF0369A1);
        break;
      case 'percent':
        typeIcon = Icons.percent;
        typeBg = const Color(0xFFFEE2E2);
        typeColor = const Color(0xFFDC2626);
        break;
      default:
        typeIcon = Icons.local_offer_outlined;
        typeBg = const Color(0xFFDCFCE7);
        typeColor = const Color(0xFF166534);
    }

    return Opacity(
      opacity: expired ? 0.6 : 1.0,
      child: Stack(
        children: [
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFE5E7EB)),
              boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.02), blurRadius: 8, offset: const Offset(0, 2))],
            ),
            child: IntrinsicHeight(
              child: Row(
                children: [
                  // Left side
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        children: [
                          Container(
                            width: 48, height: 48,
                            decoration: BoxDecoration(color: typeBg, borderRadius: BorderRadius.circular(10)),
                            child: Icon(typeIcon, color: typeColor, size: 24),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Text(_formatValue(coupon), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Color(0xFF111827))),
                                const SizedBox(height: 4),
                                Text(
                                  coupon['min_order_value'] != null ? 'Đơn từ ${_formatCurrency(coupon['min_order_value'])}' : 'Mọi đơn hàng',
                                  style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280)),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  // Dashed divider
                  CustomPaint(
                    size: const Size(1, double.infinity),
                    painter: _DashedLinePainter(),
                  ),
                  // Right side
                  Container(
                    width: 110,
                    padding: const EdgeInsets.all(16),
                    decoration: const BoxDecoration(
                      color: Color(0xFFFAFAFA),
                      borderRadius: BorderRadius.only(topRight: Radius.circular(12), bottomRight: Radius.circular(12)),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Text('Mã:', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: Color(0xFF9CA3AF))),
                        Text(code, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.primary)),
                        const SizedBox(height: 6),
                        Text('HSD: ${_formatDate(coupon['end_date'])}', style: const TextStyle(fontSize: 10, color: Color(0xFF6B7280))),
                        const SizedBox(height: 10),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: () => _copyCode(code),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              foregroundColor: Colors.white,
                              elevation: 0,
                              padding: const EdgeInsets.symmetric(vertical: 8),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                              textStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
                            ),
                            child: const Text('Sao chép'),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          // Expired overlay
          if (expired)
            Positioned.fill(
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.4),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Center(
                  child: Transform.rotate(
                    angle: -0.26,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFF6B7280),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: const Text('Hết hạn', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _DashedLinePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFFE5E7EB)
      ..strokeWidth = 1;

    double startY = 0;
    const dashHeight = 4.0;
    const gapHeight = 4.0;

    while (startY < size.height) {
      canvas.drawLine(Offset(0, startY), Offset(0, startY + dashHeight), paint);
      startY += dashHeight + gapHeight;
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
