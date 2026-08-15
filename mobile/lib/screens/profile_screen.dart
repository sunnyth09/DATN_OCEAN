import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/cart_provider.dart';
import '../widgets/app_empty_state.dart';

/// Màn hình Tài Khoản chuẩn Sàn Thương Mại Điện Tử (Shopee / Lazada tier).
/// - Thẻ hội viên VIP cao cấp.
/// - Thanh tiến trình theo dõi 5 trạng thái đơn hàng trực quan.
/// - Lưới tiện ích mua sắm & quản lý tài khoản mượt mà.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  Map<String, dynamic>? userData;
  bool isLoading = true;
  bool isGuest = false;
  String? errorMessage;

  @override
  void initState() {
    super.initState();
    fetchProfile();
  }

  Future<void> fetchProfile() async {
    if (!mounted) return;
    setState(() {
      isLoading = true;
      errorMessage = null;
      isGuest = false;
    });

    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) {
      if (mounted) setState(() { isGuest = true; isLoading = false; });
      return;
    }

    try {
      final ok = await auth.loadProfile();
      if (!mounted) return;
      if (!ok) {
        setState(() { isGuest = true; isLoading = false; });
        return;
      }
      setState(() {
        userData = auth.user?.raw;
        isLoading = false;
      });
    } catch (_) {
      if (mounted) setState(() { errorMessage = 'Lỗi kết nối máy chủ.'; isLoading = false; });
    }
  }

  void _handleLogout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Đăng xuất tài khoản?'),
        content: const Text('Bạn có chắc chắn muốn đăng xuất khỏi ứng dụng không?'),
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
            ),
            child: const Text('Đăng xuất'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => const Center(
        child: CircularProgressIndicator(color: AppColors.primary),
      ),
    );

    await context.read<AuthProvider>().logout();
    if (!mounted) return;
    context.read<CartProvider>().clearCart();
    context.pop(); // Close loading dialog
    context.go('/login');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9), // Shopee neutral bg
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (isLoading) {
      return const Center(child: CircularProgressIndicator(color: AppColors.primary));
    }

    if (isGuest) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Tài Khoản', style: TextStyle(fontWeight: FontWeight.w900)),
          backgroundColor: Colors.white,
        ),
        body: AppEmptyState(
          icon: Icons.person_outline_rounded,
          title: 'Bạn chưa đăng nhập',
          message: 'Đăng nhập để theo dõi đơn hàng, nhận mã giảm giá và các ưu đãi đặc quyền.',
          buttonText: 'Đăng nhập ngay',
          onAction: () async {
            await context.push('/login');
            fetchProfile();
          },
        ),
      );
    }

    if (errorMessage != null) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Tài Khoản', style: TextStyle(fontWeight: FontWeight.w900)),
          backgroundColor: Colors.white,
        ),
        body: AppEmptyState(
          icon: Icons.error_outline_rounded,
          title: 'Không thể tải hồ sơ',
          message: errorMessage!,
          buttonText: 'Thử lại',
          onAction: fetchProfile,
        ),
      );
    }

    final name = userData?['full_name'] ?? userData?['name'] ?? 'Khách hàng Ocean';
    final email = userData?['email'] ?? 'Chưa cập nhật email';
    final phone = userData?['phone'] ?? '';
    final avatar = userData?['avatar_url'];
    final role = userData?['role']?.toString();
    final isStaff = role == 'admin' || role == 'seller' || role == 'staff';
    final points = userData?['loyalty_points'] ?? 150;

    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: fetchProfile,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        child: Column(
          children: [
            // ── 1. Top Header Profile Card ──
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [Color(0xFFE63B6F), Color(0xFFFF6B8B)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              padding: EdgeInsets.fromLTRB(
                16,
                MediaQuery.of(context).padding.top + 16,
                16,
                24,
              ),
              child: Column(
                children: [
                  Row(
                    children: [
                      // Avatar with edit badge
                      Stack(
                        children: [
                          CircleAvatar(
                            radius: 34,
                            backgroundColor: Colors.white,
                            backgroundImage: avatar != null &&
                                    AppConfig.imageUrl(avatar.toString()).isNotEmpty
                                ? NetworkImage(AppConfig.imageUrl(avatar.toString()))
                                : null,
                            child: avatar == null
                                ? const Icon(Icons.person, size: 36, color: AppColors.primary)
                                : null,
                          ),
                          Positioned(
                            bottom: 0,
                            right: 0,
                            child: GestureDetector(
                              onTap: () async {
                                if (userData == null) return;
                                await context.push('/edit-profile', extra: userData);
                                fetchProfile();
                              },
                              child: Container(
                                padding: const EdgeInsets.all(4),
                                decoration: const BoxDecoration(
                                  color: Colors.white,
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(Icons.edit, size: 13, color: AppColors.primary),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              name.toString(),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              phone.isNotEmpty ? '$phone • $email' : email.toString(),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: Colors.white70, fontSize: 12),
                            ),
                            const SizedBox(height: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.25),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(Icons.star_rounded, size: 12, color: Colors.amberAccent),
                                  const SizedBox(width: 3),
                                  Text(
                                    isStaff ? 'NHÂN VIÊN HỆ THỐNG' : 'THÀNH VIÊN VIP',
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 10,
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: 0.5,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.settings_outlined, color: Colors.white, size: 24),
                        onPressed: () => context.push('/change-password'),
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // Balance / Points / Vouchers quick row
                  Container(
                    padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.06),
                          blurRadius: 10,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        _buildMetricCol('Voucher', '3 mã', Icons.confirmation_number_outlined, () => context.push('/my-coupons')),
                        Container(width: 1, height: 28, color: const Color(0xFFE2E8F0)),
                        _buildMetricCol('Điểm Ocean', '$points đ', Icons.stars_rounded, () => context.push('/loyalty')),
                        Container(width: 1, height: 28, color: const Color(0xFFE2E8F0)),
                        _buildMetricCol('Yêu thích', 'Xem', Icons.favorite_border_rounded, () => context.push('/favorite')),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            // ── 2. Order Tracking Status Row (Shopee 5 Status Icons) ──
            Container(
              margin: const EdgeInsets.fromLTRB(14, 10, 14, 0),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
              ),
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Đơn hàng của tôi',
                        style: TextStyle(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      InkWell(
                        onTap: () => context.push('/orders'),
                        child: const Row(
                          children: [
                            Text(
                              'Lịch sử mua hàng',
                              style: TextStyle(fontSize: 12, color: AppColors.textSecondary, fontWeight: FontWeight.w600),
                            ),
                            Icon(Icons.arrow_forward_ios_rounded, size: 11, color: AppColors.textSecondary),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const Divider(height: 20, color: Color(0xFFF1F5F9)),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _buildOrderStatusCol(Icons.account_balance_wallet_outlined, 'Chờ xác nhận', () => context.push('/orders')),
                      _buildOrderStatusCol(Icons.inventory_2_outlined, 'Chờ lấy hàng', () => context.push('/orders')),
                      _buildOrderStatusCol(Icons.local_shipping_outlined, 'Đang giao', () => context.push('/orders')),
                      _buildOrderStatusCol(Icons.star_outline_rounded, 'Đánh giá', () => context.push('/orders')),
                      _buildOrderStatusCol(Icons.published_with_changes_rounded, 'Trả hàng', () => context.push('/return-requests')),
                    ],
                  ),
                ],
              ),
            ),

            // ── 3. Staff Exclusive Section (If Staff) ──
            if (isStaff) ...[
              Container(
                margin: const EdgeInsets.fromLTRB(14, 10, 14, 0),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                ),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 8),
                      child: Text('DÀNH CHO NHÂN VIÊN', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.primary)),
                    ),
                    _buildListTile(Icons.qr_code_scanner_rounded, 'Máy quét POS / Vé sân', () => context.push('/pos-scanner')),
                  ],
                ),
              ),
            ],

            // ── 4. Utilities Grid & Support ──
            Container(
              margin: const EdgeInsets.fromLTRB(14, 10, 14, 0),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
              ),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 8),
                    child: Text('TIỆN ÍCH & DỊCH VỤ', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.textSecondary)),
                  ),
                  _buildListTile(Icons.sports_tennis_rounded, 'Lịch sử đặt sân bãi', () => context.push('/booking-history')),
                  _buildListTile(Icons.location_on_outlined, 'Sổ địa chỉ nhận hàng', () => context.push('/address')),
                  _buildListTile(Icons.chat_bubble_outline_rounded, 'Trung tâm hỗ trợ CSKH 24/7', () => context.push('/chat')),
                  _buildListTile(Icons.lock_outline_rounded, 'Đổi mật khẩu', () => context.push('/change-password')),
                ],
              ),
            ),

            // ── 5. Logout Button ──
            Container(
              margin: const EdgeInsets.fromLTRB(14, 16, 14, 36),
              width: double.infinity,
              height: 48,
              child: OutlinedButton.icon(
                onPressed: _handleLogout,
                icon: const Icon(Icons.logout_rounded, color: AppColors.error, size: 18),
                label: const Text(
                  'ĐĂNG XUẤT',
                  style: TextStyle(
                    color: AppColors.error,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.5,
                  ),
                ),
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: Color(0xFFFFCDD2)),
                  backgroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMetricCol(String title, String val, IconData icon, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        children: [
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 16, color: AppColors.primary),
              const SizedBox(width: 4),
              Text(
                val,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 2),
          Text(
            title,
            style: const TextStyle(fontSize: 11.5, color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }

  Widget _buildOrderStatusCol(IconData icon, String title, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: 62,
        child: Column(
          children: [
            Icon(icon, color: const Color(0xFF334155), size: 24),
            const SizedBox(height: 6),
            Text(
              title,
              textAlign: TextAlign.center,
              maxLines: 2,
              style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w600, color: AppColors.textPrimary, height: 1.1),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildListTile(IconData icon, String title, VoidCallback onTap) {
    return ListTile(
      onTap: onTap,
      contentPadding: EdgeInsets.zero,
      leading: Container(
        padding: const EdgeInsets.all(7),
        decoration: BoxDecoration(
          color: const Color(0xFFF1F5F9),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Icon(icon, size: 18, color: AppColors.textPrimary),
      ),
      title: Text(
        title,
        style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
      ),
      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 13, color: AppColors.textMuted),
    );
  }
}
