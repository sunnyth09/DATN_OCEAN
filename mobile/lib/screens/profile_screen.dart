import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../services/auth_service.dart';
import '../services/api_client.dart';
import '../config/app_theme.dart';
import 'login_screen.dart';
import 'address_screen.dart';
import 'change_password_screen.dart';
import 'favorite_screen.dart';
import 'edit_profile_screen.dart';
import 'pos_scanner_screen.dart';
import 'attendance_screen.dart';
import 'my_coupons_screen.dart';
import 'return_requests_screen.dart';
import 'flash_sale_screen.dart';
import 'coupon_screen.dart';
import 'booking_history_screen.dart';
import '../config/app_config.dart';

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

    final loggedIn = await AuthService.isLoggedIn();
    if (!loggedIn) {
      if (mounted) setState(() { isGuest = true; isLoading = false; });
      return;
    }

    try {
      final response = await ApiClient().dio.get('/me');
      if (mounted) {
        setState(() {
          userData = response.data['user'];
          isLoading = false;
        });
      }
    } on DioException catch (e) {
      if (e.response?.statusCode == 401) {
        await AuthService.logout();
        if (mounted) setState(() { isGuest = true; isLoading = false; });
      } else {
        if (mounted) {
          setState(() {
            errorMessage = 'Không thể lấy thông tin (${e.response?.statusCode})';
            isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) setState(() { errorMessage = 'Lỗi kết nối máy chủ.'; isLoading = false; });
    }
  }

  void _handleLogout() async {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => const Center(child: CircularProgressIndicator()),
    );
    await AuthService.logout();
    if (mounted) {
      Navigator.pop(context);
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (context) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: const Color(0xFFE63B6F),
        elevation: 0,
        title: const Text(
          'Hồ Sơ Cá Nhân',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18),
        ),
        centerTitle: true,
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (isLoading) {
      return const Center(child: CircularProgressIndicator(color: Color(0xFFE63B6F)));
    }

    // Chưa đăng nhập → hiện giao diện Guest
    if (isGuest) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(24),
                decoration: const BoxDecoration(
                  color: Color(0xFFFFF0F3),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.person_outline, size: 64, color: Color(0xFFE63B6F)),
              ),
              const SizedBox(height: 24),
              const Text(
                'Bạn chưa đăng nhập',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
              ),
              const SizedBox(height: 8),
              const Text(
                'Đăng nhập để xem hồ sơ, đơn hàng và ưu đãi dành riêng cho bạn.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Color(0xFF64748B), height: 1.5),
              ),
              const SizedBox(height: 32),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () async {
                    await Navigator.push(
                      context,
                      MaterialPageRoute(builder: (context) => const LoginScreen()),
                    );
                    fetchProfile();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFE63B6F),
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
                    elevation: 0,
                  ),
                  child: const Text(
                    'Đăng nhập ngay',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }

    if (errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.person_off_outlined, size: 60, color: Colors.grey),
            const SizedBox(height: 16),
            Text(errorMessage!, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: fetchProfile,
              child: const Text('Thử lại'),
            ),
          ],
        ),
      );
    }

    final name = userData?['full_name'] ?? userData?['name'] ?? 'Khách hàng';
    final email = userData?['email'] ?? 'Không có email';
    final avatar = userData?['avatar_url'];

    return SingleChildScrollView(
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.only(bottom: 30, top: 20, left: 24, right: 24),
            decoration: const BoxDecoration(
              color: Color(0xFFE63B6F),
              borderRadius: BorderRadius.only(
                bottomLeft: Radius.circular(30),
                bottomRight: Radius.circular(30),
              ),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 36,
                  backgroundColor: Colors.white,
                  backgroundImage: avatar != null && AppConfig.imageUrl(avatar.toString()).isNotEmpty
                      ? NetworkImage(AppConfig.imageUrl(avatar.toString()))
                      : null,
                  child: avatar == null
                      ? const Icon(Icons.person, size: 40, color: Color(0xFFE63B6F))
                      : null,
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name.toString(),
                        style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        email.toString(),
                        style: const TextStyle(color: Colors.white70, fontSize: 13),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 24),

          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Column(
              children: [
                if (userData?['role'] == 'admin' ||
                    userData?['role'] == 'seller' ||
                    userData?['role'] == 'staff') ...[
                  _buildMenuItem(Icons.qr_code_scanner, 'Máy quét POS', () {
                    Navigator.push(context, MaterialPageRoute(builder: (context) => const PosScannerScreen()));
                  }),
                  _buildMenuItem(Icons.fingerprint, 'Điểm danh (Chấm công)', () {
                    Navigator.push(context, MaterialPageRoute(builder: (context) => const AttendanceScreen()));
                  }),
                ],
                _buildMenuItem(Icons.edit_outlined, 'Chỉnh sửa hồ sơ', () async {
                  if (userData == null) return;
                  final updated = await Navigator.push(
                    context,
                    MaterialPageRoute(builder: (context) => EditProfileScreen(userData: userData!)),
                  );
                  if (updated != null && mounted) {
                    setState(() => userData = updated);
                  }
                }),
                _buildMenuItem(Icons.location_on_outlined, 'Sổ địa chỉ', () {
                  Navigator.push(context, MaterialPageRoute(builder: (context) => const AddressScreen()));
                }),
                _buildMenuItem(Icons.lock_outline, 'Đổi mật khẩu', () {
                  Navigator.push(context, MaterialPageRoute(builder: (context) => const ChangePasswordScreen()));
                }),
                _buildMenuItem(Icons.favorite_border, 'Sản phẩm yêu thích', () {
                  Navigator.push(context, MaterialPageRoute(builder: (context) => const FavoriteScreen()));
                }),
                _buildMenuItem(Icons.confirmation_number_outlined, 'Mã giảm giá của tôi', () {
                  Navigator.push(context, MaterialPageRoute(builder: (context) => const MyCouponsScreen()));
                }),
                _buildMenuItem(Icons.assignment_return_outlined, 'Yêu cầu hoàn hàng', () {
                  Navigator.push(context, MaterialPageRoute(builder: (context) => const ReturnRequestsScreen()));
                }),
                _buildMenuItem(Icons.sports_tennis, 'Lịch sử đặt sân', () {
                  Navigator.push(context, MaterialPageRoute(builder: (context) => const BookingHistoryScreen()));
                }),
                const SizedBox(height: 8),
                Padding(
                  padding: const EdgeInsets.only(left: 4, bottom: 10),
                  child: Text('Khám phá', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF94A3B8))),
                ),
                _buildMenuItem(Icons.flash_on, 'Flash Sale', () {
                  Navigator.push(context, MaterialPageRoute(builder: (context) => const FlashSaleScreen()));
                }),
                _buildMenuItem(Icons.local_offer_outlined, 'Săn Voucher', () {
                  Navigator.push(context, MaterialPageRoute(builder: (context) => const CouponScreen()));
                }),
                _buildMenuItem(Icons.article_outlined, 'Chính sách & Quy định', () {}),
                const SizedBox(height: 16),
                _buildMenuItem(Icons.logout, 'Đăng xuất', _handleLogout, isLogout: true),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMenuItem(IconData icon, String title, VoidCallback onTap, {bool isLogout = false}) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.02), blurRadius: 10, offset: const Offset(0, 4)),
        ],
      ),
      child: ListTile(
        onTap: onTap,
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: isLogout ? Colors.red.withOpacity(0.1) : const Color(0xFFF1F5F9),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: isLogout ? Colors.red : const Color(0xFF64748B), size: 20),
        ),
        title: Text(
          title,
          style: TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 15,
            color: isLogout ? Colors.red : const Color(0xFF0F172A),
          ),
        ),
        trailing: isLogout ? null : const Icon(Icons.arrow_forward_ios, size: 16, color: Color(0xFFCBD5E1)),
      ),
    );
  }
}
