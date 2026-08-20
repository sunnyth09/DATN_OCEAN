import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/cart_provider.dart';
import '../providers/coupon_provider.dart';
import '../providers/favorite_provider.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../services/passkey_service.dart';
import '../services/storage_service.dart';
import '../utils/api_response_parser.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/app_toast.dart';

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
  bool isLoading = false;
  bool _isPasskeyEnrolled = false;
  String? errorMessage;
  int _loyaltyPoints = 0;
  String _membershipTier = 'THÀNH VIÊN MỚI';
  Map<String, int> _orderCounts = {
    'pending': 0,
    'pickup': 0,
    'shipping': 0,
    'review': 0,
    'returns': 0,
  };

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final auth = context.read<AuthProvider>();
      if (auth.isAuthenticated) {
        auth.loadProfile();
        final email = auth.user?.email ?? '';
        _checkPasskeyStatus(email);
        _fetchOrderCounts();
        _fetchUserMetrics();
      }
    });
  }

  Future<void> _fetchUserMetrics() async {
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) return;

    // 1. Lấy số lượng Voucher đã lưu thực tế
    try {
      if (mounted) {
        context.read<CouponProvider>().fetchUserCoupons(silent: true);
      }
    } catch (_) {}

    // 2. Lấy số lượng Sản phẩm yêu thích thực tế
    try {
      if (mounted) {
        context.read<FavoriteProvider>().fetchFavorites(silent: true);
      }
    } catch (_) {}

    // 3. Lấy Điểm thưởng và Hạng thành viên thực tế
    try {
      final res = await ApiClient().dio.get('/loyalty/profile');
      if (res.data != null && res.data['status'] == 'success') {
        final data = res.data['data'];
        final pts = (data['points'] as num?)?.toInt() ?? 0;
        final tier = data['tier']?.toString() ?? '';
        if (mounted) {
          setState(() {
            _loyaltyPoints = pts;
            if (tier.isNotEmpty) {
              _membershipTier = tier.toUpperCase();
            } else if (pts >= 1000) {
              _membershipTier = 'THÀNH VIÊN KIM CƯƠNG';
            } else if (pts >= 500) {
              _membershipTier = 'THÀNH VIÊN VÀNG';
            } else if (pts >= 100) {
              _membershipTier = 'THÀNH VIÊN BẠC';
            } else {
              _membershipTier = 'THÀNH VIÊN ĐỒNG';
            }
          });
        }
      }
    } catch (_) {}
  }

  Future<void> _fetchOrderCounts() async {
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) return;

    try {
      final response = await ApiClient().dio.get('/profile/orders');
      final decoded = response.data;
      final fetchedOrders = ApiResponseParser.parseList(decoded);

      int pendingCount = 0;
      int pickupCount = 0;
      int shippingCount = 0;
      int reviewCount = 0;
      int returnCount = 0;

      for (var order in fetchedOrders) {
        final st = (order['fulfillment_status'] ?? order['status'] ?? '').toString().toLowerCase();
        final orderStatus = (order['order_status'] ?? order['status'] ?? '').toString().toLowerCase();

        if (st.contains('pending') || orderStatus.contains('pending') || orderStatus.contains('unpaid') || orderStatus.contains('waiting')) {
          pendingCount++;
        } else if (st.contains('processing') || st.contains('confirmed') || st.contains('ready') || st.contains('pickup')) {
          pickupCount++;
        } else if (st.contains('shipping') || st.contains('delivering') || st.contains('transit')) {
          shippingCount++;
        } else if (st.contains('completed') || st.contains('delivered') || st.contains('success') || orderStatus.contains('completed')) {
          reviewCount++;
        } else if (st.contains('return') || st.contains('refund') || orderStatus.contains('return')) {
          returnCount++;
        }
      }

      try {
        final retRes = await ApiClient().dio.get('/return-requests');
        if (retRes.data != null) {
          final retList = retRes.data['data'] ?? retRes.data;
          if (retList is List && retList.isNotEmpty) {
            returnCount = retList.length;
          }
        }
      } catch (_) {}

      if (mounted) {
        setState(() {
          _orderCounts = {
            'pending': pendingCount,
            'pickup': pickupCount,
            'shipping': shippingCount,
            'review': reviewCount,
            'returns': returnCount,
          };
        });
      }
    } catch (_) {}
  }

  Future<void> _checkPasskeyStatus(String email) async {
    if (email.isEmpty) return;
    final status = await PasskeyService.isPasskeyEnrolled(email);
    if (mounted) setState(() => _isPasskeyEnrolled = status);
  }

  Future<void> fetchProfile() async {
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) return;

    if (!mounted) return;
    setState(() {
      isLoading = true;
      errorMessage = null;
    });

    try {
      await auth.loadProfile();
      await _fetchOrderCounts();
      await _fetchUserMetrics();
    } catch (_) {
      if (mounted) setState(() => errorMessage = 'Lỗi kết nối máy chủ.');
    } finally {
      if (mounted) setState(() => isLoading = false);
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
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9), // Shopee neutral bg
      body: _buildBody(auth),
    );
  }

  Widget _buildBody(AuthProvider auth) {
    if (!auth.isAuthenticated) {
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
            final res = await context.push('/login');
            if (mounted && (res == true || context.read<AuthProvider>().isAuthenticated)) {
              fetchProfile();
            }
          },
        ),
      );
    }

    if (isLoading && auth.user == null) {
      return const Center(child: CircularProgressIndicator(color: AppColors.primary));
    }

    if (errorMessage != null && auth.user == null) {
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

    final user = auth.user;
    final userData = user?.raw ?? {};

    final name = (user?.fullName.isNotEmpty == true)
        ? user!.fullName
        : (userData['full_name'] ?? userData['name'] ?? 'Khách hàng Ocean');
    final email = (user?.email.isNotEmpty == true)
        ? user!.email
        : (userData['email'] ?? 'Chưa cập nhật email');
    final phone = user?.phone ?? userData['phone'] ?? '';
    final avatar = user?.avatarUrl ?? userData['avatar_url'];
    final role = user?.role ?? userData['role']?.toString();
    final isStaff = role == 'admin' || role == 'seller' || role == 'staff';
    final points = _loyaltyPoints;
    final tierLabel = isStaff ? 'NHÂN VIÊN HỆ THỐNG' : _membershipTier;

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
                gradient: AppGradients.hero,
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
                                    tierLabel,
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
                        Consumer<CouponProvider>(
                          builder: (context, couponProv, _) => _buildMetricCol(
                            'Voucher',
                            '${couponProv.voucherCount} mã',
                            Icons.confirmation_number_outlined,
                            () => context.push('/my-coupons'),
                          ),
                        ),
                        Container(width: 1, height: 28, color: const Color(0xFFE2E8F0)),
                        _buildMetricCol(
                          'Điểm Ocean',
                          '$points đ',
                          Icons.stars_rounded,
                          () => context.push('/loyalty'),
                        ),
                        Container(width: 1, height: 28, color: const Color(0xFFE2E8F0)),
                        Consumer<FavoriteProvider>(
                          builder: (context, fav, _) => _buildMetricCol(
                            'Yêu thích',
                            '${fav.itemCount} món',
                            Icons.favorite_border_rounded,
                            () => context.push('/favorite'),
                          ),
                        ),
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
                      _buildOrderStatusCol(
                        Icons.account_balance_wallet_outlined,
                        'Chờ xác nhận',
                        () => context.push('/orders?tab=1'),
                        badgeCount: _orderCounts['pending'] ?? 0,
                      ),
                      _buildOrderStatusCol(
                        Icons.inventory_2_outlined,
                        'Chờ lấy hàng',
                        () => context.push('/orders?tab=2'),
                        badgeCount: _orderCounts['pickup'] ?? 0,
                      ),
                      _buildOrderStatusCol(
                        Icons.local_shipping_outlined,
                        'Đang giao',
                        () => context.push('/orders?tab=3'),
                        badgeCount: _orderCounts['shipping'] ?? 0,
                      ),
                      _buildOrderStatusCol(
                        Icons.star_outline_rounded,
                        'Đánh giá',
                        () => context.push('/orders?tab=4'),
                        badgeCount: _orderCounts['review'] ?? 0,
                      ),
                      _buildOrderStatusCol(
                        Icons.published_with_changes_rounded,
                        'Trả hàng',
                        () => context.push('/orders?tab=5'),
                        badgeCount: _orderCounts['returns'] ?? 0,
                      ),
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
                      child: Text('TIỆN ÍCH & BẢO MẬT', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.textSecondary)),
                    ),
                    _buildListTile(
                      Icons.fingerprint_rounded,
                      'Passkey & Sinh trắc học',
                      () => _showPasskeyManagementDialog(email, name, avatar),
                      trailingWidget: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: _isPasskeyEnrolled ? const Color(0xFFF0FDF4) : const Color(0xFFF1F5F9),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: _isPasskeyEnrolled ? const Color(0xFFBBF7D0) : const Color(0xFFE2E8F0),
                          ),
                        ),
                        child: Text(
                          _isPasskeyEnrolled ? 'Đã bật' : 'Chưa bật',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            color: _isPasskeyEnrolled ? const Color(0xFF16A34A) : const Color(0xFF64748B),
                          ),
                        ),
                      ),
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

  Widget _buildOrderStatusCol(
    IconData icon,
    String title,
    VoidCallback onTap, {
    int badgeCount = 0,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 2),
        child: SizedBox(
          width: 60,
          child: Column(
            children: [
              SizedBox(
                height: 28,
                child: Stack(
                  clipBehavior: Clip.none,
                  alignment: Alignment.center,
                  children: [
                    Icon(icon, color: const Color(0xFF64748B), size: 22),
                    if (badgeCount > 0)
                      Positioned(
                        top: -3,
                        right: -10,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 4.5, vertical: 1),
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFFFF4B55), Color(0xFFEE2A35)],
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            ),
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: Colors.white, width: 1.5),
                            boxShadow: [
                              BoxShadow(
                                color: const Color(0xFFEF4444).withValues(alpha: 0.35),
                                blurRadius: 4,
                                offset: const Offset(0, 1.5),
                              ),
                            ],
                          ),
                          constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                          child: Center(
                            child: Text(
                              badgeCount > 99 ? '99+' : badgeCount.toString(),
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 9.5,
                                fontWeight: FontWeight.w900,
                                height: 1.1,
                              ),
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 4),
              Text(
                title,
                textAlign: TextAlign.center,
                maxLines: 2,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w500,
                  color: Color(0xFF475569),
                  height: 1.15,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildListTile(IconData icon, String title, VoidCallback onTap, {Widget? trailingWidget}) {
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
      trailing: trailingWidget ?? const Icon(Icons.arrow_forward_ios_rounded, size: 13, color: AppColors.textMuted),
    );
  }

  void _showPasskeyManagementDialog(String email, String name, String? avatar) async {
    final isEnrolled = await PasskeyService.isPasskeyEnrolled(email);
    final bioSupport = await PasskeyService.checkDeviceBiometricSupport();
    final isDeviceReady = bioSupport['supported'] == true;

    if (!mounted) return;
    await showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (modalCtx, setModalState) => Container(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 28),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 44,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFE2E8F0),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 20),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: (isEnrolled
                          ? const Color(0xFF10B981)
                          : (isDeviceReady ? const Color(0xFF8B5CF6) : const Color(0xFFF59E0B)))
                      .withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  isDeviceReady ? Icons.fingerprint_rounded : Icons.lock_clock_rounded,
                  size: 48,
                  color: isEnrolled
                      ? const Color(0xFF10B981)
                      : (isDeviceReady ? const Color(0xFF8B5CF6) : const Color(0xFFD97706)),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                isEnrolled
                    ? 'Passkey Đang Hoạt Động'
                    : (isDeviceReady ? 'Kích Hoạt Passkey Thiết Bị' : 'Yêu Cầu Khóa Màn Hình'),
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF0F172A),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                isEnrolled
                    ? 'Tài khoản $email đã được liên kết với mã khóa bảo mật trên thiết bị này. Bạn có thể đăng nhập 1 chạm bằng sinh trắc học ở màn hình đăng nhập.'
                    : (isDeviceReady
                        ? 'Kích hoạt Passkey để đăng nhập 1 chạm an toàn bằng Face ID / Vân tay / PIN máy mà không cần nhập mật khẩu cho những lần sau.'
                        : 'Thiết bị này chưa thiết lập Khóa màn hình hoặc Sinh trắc học (PIN / Mẫu hình / Vân tay). Vui lòng vào Cài đặt thiết bị để thiết lập trước khi kích hoạt Passkey.'),
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 13, color: Color(0xFF64748B), height: 1.4),
              ),
              const SizedBox(height: 24),
              if (!isEnrolled)
                ElevatedButton(
                  onPressed: () async {
                    final authProvider = context.read<AuthProvider>();
                    // Kiểm tra và yêu cầu xác thực bằng cảm biến/mã khóa thực tế của hệ điều hành
                    final authRes = await PasskeyService.authenticateWithBiometrics(
                      reason: 'Xác thực vân tay / Face ID / PIN để kích hoạt Passkey',
                    );

                    if (authRes['success'] != true) {
                      if (ctx.mounted) Navigator.pop(ctx);
                      if (!mounted) return;
                      AppToast.showError(
                        context,
                        message: authRes['message'] ?? 'Xác thực sinh trắc học không thành công.',
                      );
                      return;
                    }

                    final token = await StorageService.read(AuthService.keyToken);
                    final user = authProvider.user;
                    await PasskeyService.enrollPasskey(
                      email,
                      name: name,
                      avatarUrl: avatar,
                      token: token,
                      userData: user?.toJson(),
                    );
                    if (ctx.mounted) Navigator.pop(ctx);
                    if (!mounted) return;
                    _checkPasskeyStatus(email);
                    AppToast.showSuccess(
                      context,
                      message: 'Đã kích hoạt Passkey bảo mật thành công trên thiết bị này!',
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF8B5CF6),
                    foregroundColor: Colors.white,
                    minimumSize: const Size(double.infinity, 48),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.fingerprint_rounded, size: 20),
                      SizedBox(width: 8),
                      Text('Xác thực & Kích hoạt ngay', style: TextStyle(fontWeight: FontWeight.w800)),
                    ],
                  ),
                )
              else
                OutlinedButton(
                  onPressed: () async {
                    await PasskeyService.revokePasskey(email);
                    if (ctx.mounted) Navigator.pop(ctx);
                    if (!mounted) return;
                    _checkPasskeyStatus(email);
                    AppToast.showInfo(
                      context,
                      message: 'Đã hủy kích hoạt Passkey trên thiết bị.',
                    );
                  },
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(double.infinity, 48),
                    side: const BorderSide(color: AppColors.error),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  child: const Text('Hủy kích hoạt Passkey trên máy này', style: TextStyle(color: AppColors.error, fontWeight: FontWeight.bold)),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
