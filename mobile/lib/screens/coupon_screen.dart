import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../config/app_theme.dart';
import 'login_screen.dart';

class CouponScreen extends StatefulWidget {
  const CouponScreen({super.key});

  @override
  State<CouponScreen> createState() => _CouponScreenState();
}

class _CouponScreenState extends State<CouponScreen> {
  List<dynamic> coupons = [];
  bool isLoading = true;
  String searchQuery = '';

  @override
  void initState() {
    super.initState();
    fetchPublicCoupons();
  }

  Future<void> fetchPublicCoupons() async {
    try {
      setState(() => isLoading = true);
      final res = await ApiClient().dio.get('/coupons/public');
      if (res.data['status'] == 'success') {
        if (mounted) setState(() { coupons = res.data['data'] ?? []; isLoading = false; });
      } else {
        if (mounted) setState(() => isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => isLoading = false);
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
    if (type == 'percent') return '${value}%';
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
      return '${formatted}₫';
    } catch (_) {
      return '${val}₫';
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
        Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      }
      return;
    }

    try {
      final res = await ApiClient().dio.post('/profile/coupons/save', data: {'coupon_id': couponId});
      final status = res.data['status'];
      final msg = res.data['message'] ?? 'Đã lưu mã giảm giá!';
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg),
            backgroundColor: status == 'success' ? AppColors.success : AppColors.info,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('Không thể lưu mã giảm giá!'),
            backgroundColor: AppColors.error,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
      }
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
                  mainAxisSpacing: 14,
                  crossAxisSpacing: 14,
                  childAspectRatio: 0.62,
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
        gradient: LinearGradient(
          colors: [Color(0xFFB50C4D), Color(0xFFE63B6F)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              child: Row(
                children: [
                  IconButton(icon: const Icon(Icons.arrow_back, color: Colors.white), onPressed: () => Navigator.pop(context)),
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
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 16),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(30),
          border: Border.all(color: const Color(0xFFFFE3E8)),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 8, offset: const Offset(0, 2))],
        ),
        child: TextField(
          onChanged: (v) => setState(() => searchQuery = v),
          decoration: InputDecoration(
            hintText: 'Tìm mã giảm giá...',
            hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 14),
            prefixIcon: const Icon(Icons.search, color: AppColors.primary, size: 20),
            border: InputBorder.none,
            contentPadding: const EdgeInsets.symmetric(vertical: 14),
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

    return Opacity(
      opacity: isActive ? 1.0 : 0.6,
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFFFE3E8)),
          boxShadow: [BoxShadow(color: AppColors.primary.withOpacity(0.03), blurRadius: 12, offset: const Offset(0, 4))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top section
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Type badge
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF0F3),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: AppColors.primary.withOpacity(0.1)),
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
                    const SizedBox(height: 10),
                    // Code box
                    GestureDetector(
                      onTap: () => _copyCode(code),
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF5F7),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: AppColors.primary, width: 1.5, style: BorderStyle.none),
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(code, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: AppColors.primary, letterSpacing: 0.5)),
                            ),
                            const Icon(Icons.copy, size: 14, color: AppColors.primary),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    // Value
                    Text(_formatValue(coupon), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), letterSpacing: -0.5)),
                    const Spacer(),
                    // Min order
                    if (coupon['min_order_value'] != null)
                      Text('Đơn từ ${_formatCurrency(coupon['min_order_value'])}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                  ],
                ),
              ),
            ),
            // Divider with notches
            _buildTicketDivider(),
            // Bottom section
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                children: [
                  Row(
                    children: [
                      const Icon(Icons.access_time, size: 12, color: Color(0xFF64748B)),
                      const SizedBox(width: 4),
                      Text('HSD: ${_formatDate(coupon['end_date'])}', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                    ],
                  ),
                  const SizedBox(height: 10),
                  // Save button
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: isActive ? () => _saveCoupon(coupon['id']) : null,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: isActive ? AppColors.primary : const Color(0xFFE2E8F0),
                        foregroundColor: isActive ? Colors.white : const Color(0xFF94A3B8),
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                      ),
                      child: const Text('Lưu mã'),
                    ),
                  ),
                  const SizedBox(height: 6),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton(
                      onPressed: () => _copyCode(code),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.primary,
                        side: const BorderSide(color: AppColors.primary, width: 1.5),
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                      ),
                      child: const Text('Sao chép mã'),
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
