import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/coupon_provider.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/app_toast.dart';
import '../widgets/shimmer_loading.dart';
import '../utils/format_utils.dart';

class MyCouponsScreen extends StatefulWidget {
  const MyCouponsScreen({super.key});

  @override
  State<MyCouponsScreen> createState() => _MyCouponsScreenState();
}

class _MyCouponsScreenState extends State<MyCouponsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final auth = context.read<AuthProvider>();
      if (auth.isAuthenticated) {
        context.read<CouponProvider>().fetchUserCoupons();
      }
    });
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
    if (type == 'free_ship') return 'Freeship ${FormatUtils.formatPrice(value)}';
    return 'Giảm ${FormatUtils.formatPrice(value)}';
  }

  String _formatDate(dynamic dateString) {
    if (dateString == null) return 'Vô thời hạn';
    final d = DateTime.tryParse(dateString.toString());
    if (d == null) return 'Vô thời hạn';
    return '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}';
  }

  void _copyCode(String code) {
    Clipboard.setData(ClipboardData(text: code));
    AppToast.showSuccess(context, message: 'Đã sao chép mã: $code');
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final couponProv = context.watch<CouponProvider>();

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Mã giảm giá của tôi', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF0F172A),
        iconTheme: const IconThemeData(color: Color(0xFF0F172A)),
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/me');
            }
          },
        ),
        actions: [
          TextButton.icon(
            onPressed: () async {
              await context.push('/coupon');
              if (context.mounted) {
                context.read<CouponProvider>().fetchUserCoupons();
              }
            },
            icon: const Icon(Icons.explore_outlined, size: 16, color: AppColors.primary),
            label: const Text(
              'Kho voucher',
              style: TextStyle(
                color: AppColors.primary,
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
      body: _buildBody(auth, couponProv),
    );
  }

  Widget _buildBody(AuthProvider auth, CouponProvider couponProv) {
    if (!auth.isAuthenticated) {
      return AppEmptyState(
        icon: Icons.person_outline_rounded,
        title: 'Bạn chưa đăng nhập',
        message: 'Đăng nhập để xem danh sách các mã giảm giá của riêng bạn.',
        buttonText: 'Đăng nhập ngay',
        onAction: () async {
          await context.push('/login');
          if (mounted && context.read<AuthProvider>().isAuthenticated) {
            context.read<CouponProvider>().fetchUserCoupons();
          }
        },
      );
    }

    if (couponProv.isLoadingUser && couponProv.userCoupons.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(16),
        child: ShimmerLoading(),
      );
    }

    final coupons = couponProv.userCoupons;

    if (coupons.isEmpty) {
      return AppEmptyState(
        icon: Icons.confirmation_number_outlined,
        title: 'Chưa có mã giảm giá nào',
        message: 'Bạn chưa lưu mã giảm giá nào. Hãy ghé Kho Voucher để săn thêm ưu đãi nhé!',
        buttonText: 'Khám phá Kho Voucher',
        onAction: () async {
          await context.push('/coupon');
          if (mounted) {
            context.read<CouponProvider>().fetchUserCoupons();
          }
        },
      );
    }

    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: () => couponProv.fetchUserCoupons(),
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
        itemCount: coupons.length,
        separatorBuilder: (context, index) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          final item = coupons[index];
          final coupon = (item['coupon'] is Map ? item['coupon'] : item) as Map<String, dynamic>;
          final code = coupon['code']?.toString() ?? '';
          final expired = _isExpired(coupon['end_date']);
          final isUsed = item['is_used'] == true || item['is_used'] == 1;

          return Opacity(
            opacity: (expired || isUsed) ? 0.6 : 1.0,
            child: Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFE2E8F0)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.02),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                children: [
                  // Left side banner
                  Container(
                    width: 90,
                    padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 8),
                    decoration: BoxDecoration(
                      gradient: isUsed || expired
                          ? const LinearGradient(colors: [Color(0xFF94A3B8), Color(0xFF64748B)])
                          : AppGradients.primary,
                      borderRadius: const BorderRadius.only(
                        topLeft: Radius.circular(15),
                        bottomLeft: Radius.circular(15),
                      ),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          isUsed ? Icons.check_circle_outline : (expired ? Icons.timer_off_outlined : Icons.local_activity_rounded),
                          color: Colors.white,
                          size: 24,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          isUsed ? 'ĐÃ DÙNG' : (expired ? 'HẾT HẠN' : 'KHẢ DỤNG'),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 9,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 0.5,
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ],
                    ),
                  ),

                  // Right side details
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  _formatValue(coupon),
                                  style: const TextStyle(
                                    fontSize: 14.5,
                                    fontWeight: FontWeight.w800,
                                    color: Color(0xFF0F172A),
                                  ),
                                ),
                              ),
                              GestureDetector(
                                onTap: () => _copyCode(code),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: AppColors.primary.withValues(alpha: 0.08),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Text(
                                        code,
                                        style: const TextStyle(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w800,
                                          color: AppColors.primary,
                                        ),
                                      ),
                                      const SizedBox(width: 4),
                                      const Icon(Icons.copy_rounded, size: 12, color: AppColors.primary),
                                    ],
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            coupon['description']?.toString() ?? coupon['name']?.toString() ?? 'Ưu đãi thể thao',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 11.5,
                              color: Color(0xFF64748B),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'HSD: ${_formatDate(coupon['end_date'])}',
                            style: const TextStyle(
                              fontSize: 10,
                              color: Color(0xFF94A3B8),
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
