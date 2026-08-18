import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../providers/coupon_provider.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../config/app_theme.dart';

class CouponScreen extends StatefulWidget {
  const CouponScreen({super.key});

  @override
  State<CouponScreen> createState() => _CouponScreenState();
}

class _CouponScreenState extends State<CouponScreen> {
  List<dynamic> coupons = [];
  bool isLoading = true;
  String? errorMessage;
  String searchQuery = '';

  @override
  void initState() {
    super.initState();
    fetchPublicCoupons();
  }

  Future<void> fetchPublicCoupons() async {
    try {
      setState(() { isLoading = true; errorMessage = null; });
      final res = await ApiClient().dio.get('/coupons/public');
      if (res.data['status'] == 'success') {
        if (mounted) setState(() { coupons = res.data['data'] ?? []; isLoading = false; });
      } else {
        if (mounted) setState(() => isLoading = false);
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          isLoading = false;
          errorMessage = 'Không tải được danh sách voucher. Vui lòng thử lại.';
        });
      }
    }
  }

  List<dynamic> get filteredCoupons {
    List<dynamic> list = List.from(coupons);
    if (searchQuery.isNotEmpty) {
      final q = searchQuery.toLowerCase();
      list = list.where((c) =>
        (c['code']?.toString().toLowerCase().contains(q) ?? false) ||
        (c['description']?.toString().toLowerCase().contains(q) ?? false)
      ).toList();
    }
    // Sort: active first
    list.sort((a, b) {
      final aActive = (a['is_active'] == true && !_isExpired(a['end_date'])) ? 1 : 0;
      final bActive = (b['is_active'] == true && !_isExpired(b['end_date'])) ? 1 : 0;
      return bActive - aActive;
    });
    return list;
  }

  bool _isExpired(dynamic endDate) {
    if (endDate == null) return false;
    final d = DateTime.tryParse(endDate.toString());
    return d != null && d.isBefore(DateTime.now());
  }

  String _formatValue(Map<String, dynamic> coupon) {
    final type = coupon['type']?.toString() ?? '';
    final value = coupon['value'];
    if (type == 'percent') return '$value%';
    if (type == 'free_ship') return 'Freeship ${_formatCurrency(value)}';
    return _formatCurrency(value);
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
    if (dateString == null) return 'Vô hạn';
    final d = DateTime.tryParse(dateString.toString());
    if (d == null) return 'Vô hạn';
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

  Future<void> _saveCoupon(int couponId) async {
    final loggedIn = await AuthService.isLoggedIn();
    if (!loggedIn) {
      if (mounted) {
        context.push('/login');
      }
      return;
    }

    if (!mounted) return;
    final ok = await context.read<CouponProvider>().claimCoupon(couponId);
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(ok ? 'Đã lưu mã giảm giá vào ví!' : 'Không thể lưu mã giảm giá!'),
          backgroundColor: ok ? AppColors.success : AppColors.error,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
    }
  }

  IconData _getTypeIcon(String type) {
    switch (type) {
      case 'percent': return Icons.percent;
      case 'free_ship': return Icons.local_shipping_outlined;
      default: return Icons.local_offer_outlined;
    }
  }

  String _getTypeLabel(String type) {
    switch (type) {
      case 'percent': return 'Giảm giá phần trăm';
      case 'free_ship': return 'Miễn phí vận chuyển';
      case 'fixed': return 'Giảm giá trực tiếp';
      default: return 'Khuyến mãi';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFFCF9FA),
      body: CustomScrollView(
        slivers: [
          // Hero
          SliverToBoxAdapter(child: _buildHero()),
          // Search
          SliverToBoxAdapter(child: _buildSearch()),
          // Content
          if (isLoading)
            const SliverFillRemaining(
              child: Center(child: CircularProgressIndicator(color: AppColors.primary)),
            )
          else if (errorMessage != null)
            SliverFillRemaining(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.cloud_off_outlined, size: 64, color: Color(0xFFCBD5E1)),
                    const SizedBox(height: 12),
                    Text(errorMessage!, textAlign: TextAlign.center, style: const TextStyle(fontSize: 15, color: Color(0xFF64748B))),
                    const SizedBox(height: 16),
                    ElevatedButton.icon(
                      onPressed: fetchPublicCoupons,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Thử lại'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  ],
                ),
              ),
            )
          else if (filteredCoupons.isEmpty)
            const SliverFillRemaining(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.confirmation_number_outlined, size: 64, color: Color(0xFFCBD5E1)),
                    SizedBox(height: 12),
                    Text('Không tìm thấy voucher nào', style: TextStyle(fontSize: 16, color: Color(0xFF64748B))),
                  ],
                ),
              ),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 32),
              sliver: SliverGrid(
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  mainAxisExtent: 236,
                ),
                delegate: SliverChildBuilderDelegate(
                  (context, index) => _buildCouponCard(filteredCoupons[index]),
                  childCount: filteredCoupons.length,
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildHero() {
    return Container(
      decoration: const BoxDecoration(
        gradient: AppGradients.hero,
      ),
      child: SafeArea(
        bottom: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              child: Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.arrow_back, color: Colors.white),
                    onPressed: () {
                      if (context.canPop()) {
                        context.pop();
                      } else {
                        context.go('/home');
                      }
                    },
                  ),
                  const Spacer(),
                  const Text('Săn Voucher', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
                  const Spacer(),
                  const SizedBox(width: 48),
                ],
              ),
            ),
            const Padding(
              padding: EdgeInsets.only(bottom: 24),
              child: Text('Ưu đãi hấp dẫn dành riêng cho bạn', style: TextStyle(color: Colors.white70, fontSize: 13)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSearch() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
      child: Container(
        height: 42,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFFFE3E8)),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.03), blurRadius: 6, offset: const Offset(0, 2))],
        ),
        child: TextField(
          onChanged: (v) => setState(() => searchQuery = v),
          style: const TextStyle(fontSize: 13.5, color: AppColors.textPrimary),
          decoration: const InputDecoration(
            hintText: 'Tìm mã giảm giá...',
            hintStyle: TextStyle(color: Color(0xFF64748B), fontSize: 13),
            prefixIcon: Icon(Icons.search, color: AppColors.primary, size: 18),
            isDense: true,
            filled: false,
            border: InputBorder.none,
            enabledBorder: InputBorder.none,
            focusedBorder: InputBorder.none,
            errorBorder: InputBorder.none,
            focusedErrorBorder: InputBorder.none,
            disabledBorder: InputBorder.none,
            contentPadding: EdgeInsets.symmetric(vertical: 10, horizontal: 12),
          ),
        ),
      ),
    );
  }

  Widget _buildCouponCard(Map<String, dynamic> coupon) {
    final type = coupon['type']?.toString() ?? 'fixed';
    final code = coupon['code']?.toString() ?? '';
    final expired = _isExpired(coupon['end_date']);
    final isActive = coupon['is_active'] == true && !expired;
    final id = int.tryParse((coupon['id'] ?? 0).toString()) ?? 0;
    final isSaved = context.watch<CouponProvider>().isSaved(id);

    return Opacity(
      opacity: isActive ? 1.0 : 0.6,
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFFFE3E8)),
          boxShadow: [BoxShadow(color: AppColors.primary.withValues(alpha: 0.03), blurRadius: 12, offset: const Offset(0, 4))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top section
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Type badge
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF0F3),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: AppColors.primary.withValues(alpha: 0.1)),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(_getTypeIcon(type), size: 12, color: AppColors.primary),
                          const SizedBox(width: 4),
                          Flexible(
                            child: Text(
                              _getTypeLabel(type),
                              style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: AppColors.primary),
                              maxLines: 1, overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 8),
                    // Code box
                    GestureDetector(
                      onTap: () => _copyCode(code),
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF5F7),
                          borderRadius: BorderRadius.circular(6),
                          border: Border.all(color: AppColors.primary.withValues(alpha: 0.3), width: 1, style: BorderStyle.solid),
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(code, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: AppColors.primary, letterSpacing: 0.5), maxLines: 1, overflow: TextOverflow.ellipsis),
                            ),
                            const Icon(Icons.copy, size: 14, color: AppColors.primary),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 6),
                    // Value
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerLeft,
                      child: Text(_formatValue(coupon), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), letterSpacing: -0.5)),
                    ),
                    const Spacer(),
                    // Min order
                    if (coupon['min_order_value'] != null)
                      Text('Đơn từ ${_formatCurrency(coupon['min_order_value'])}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                  ],
                ),
              ),
            ),
            // Divider with notches
            _buildTicketDivider(),
            // Bottom section
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                children: [
                  Row(
                    children: [
                      const Icon(Icons.access_time, size: 12, color: Color(0xFF64748B)),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text('HSD: ${_formatDate(coupon['end_date'])}', style: const TextStyle(fontSize: 10, color: Color(0xFF64748B)), maxLines: 1, overflow: TextOverflow.ellipsis),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  // Save button
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: (isActive && !isSaved) ? () => _saveCoupon(id) : null,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: isSaved ? const Color(0xFFE2E8F0) : (isActive ? AppColors.primary : const Color(0xFFE2E8F0)),
                        foregroundColor: isSaved ? const Color(0xFF64748B) : (isActive ? Colors.white : const Color(0xFF64748B)),
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(vertical: 6),
                        minimumSize: Size.zero,
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        textStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
                      ),
                      child: Text(isSaved ? 'Đã lưu' : 'Lưu mã'),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTicketDivider() {
    return SizedBox(
      height: 20,
      child: Row(
        children: [
          Container(
            width: 10, height: 20,
            decoration: const BoxDecoration(
              color: Color(0xFFFCF9FA),
              borderRadius: BorderRadius.only(topRight: Radius.circular(10), bottomRight: Radius.circular(10)),
            ),
          ),
          Expanded(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final dashCount = (constraints.maxWidth / 8).floor();
                return Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: List.generate(dashCount, (_) => Container(width: 4, height: 1, color: const Color(0xFFFFE3E8))),
                );
              },
            ),
          ),
          Container(
            width: 10, height: 20,
            decoration: const BoxDecoration(
              color: Color(0xFFFCF9FA),
              borderRadius: BorderRadius.only(topLeft: Radius.circular(10), bottomLeft: Radius.circular(10)),
            ),
          ),
        ],
      ),
    );
  }
}
