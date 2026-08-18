import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/cart_provider.dart';
import '../services/auth_service.dart';
import '../services/notification_service.dart';
import '../services/passkey_service.dart';
import '../services/storage_service.dart';
import '../widgets/app_text_field.dart';
import 'forgot_password_screen.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  bool _isLoading = false;
  bool _useSavedAccount = true;
  bool _isPasskeyEnrolled = false;
  Map<String, dynamic>? _lastAccount;

  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadLastAccount();
  }

  Future<void> _loadLastAccount() async {
    try {
      final raw = await StorageService.read('last_login_account');
      if (raw != null && mounted) {
        final decoded = jsonDecode(raw);
        if (decoded is Map<String, dynamic>) {
          final email = decoded['email']?.toString() ?? '';
          final hasPasskey = await PasskeyService.isPasskeyEnrolled(email);
          if (mounted) {
            setState(() {
              _lastAccount = decoded;
              _isPasskeyEnrolled = hasPasskey;
              _emailController.text = email;
            });
          }
        }
      }
    } catch (_) {}
  }

  Future<void> _completeLoginSuccess(String email, {String? password}) async {
    final user = context.read<AuthProvider>().user;
    final token = await StorageService.read(AuthService.keyToken);
    final isPasskey = await PasskeyService.isPasskeyEnrolled(email);
    if (isPasskey) {
      await PasskeyService.enrollPasskey(
        email,
        name: user?.fullName,
        avatarUrl: user?.avatarUrl,
        password: password,
        token: token,
        userData: user?.toJson(),
      );
    }
    final accountData = {
      'email': email,
      'name': (user?.fullName.isNotEmpty == true) ? user!.fullName : 'Khách hàng Ocean',
      'role': user?.role ?? 'customer',
      'avatar_url': user?.avatarUrl,
      'has_passkey': isPasskey,
    };
    await StorageService.write('last_login_account', jsonEncode(accountData));
    if (!mounted) return;

    NotificationService().syncTokenToServer();
    context.read<CartProvider>().fetchCart(silent: true, force: true);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('🎉 Xin chào ${user?.fullName.isNotEmpty == true ? user!.fullName : email}, đăng nhập thành công!'),
        backgroundColor: AppColors.success,
        behavior: SnackBarBehavior.floating,
      ),
    );

    if (context.canPop()) {
      context.pop(true);
    } else {
      context.go('/me');
    }
  }

  Future<void> _handlePasskeyLogin() async {
    if (_lastAccount == null) return;
    final email = _lastAccount!['email']?.toString() ?? '';
    if (email.isEmpty) return;

    final authProvider = context.read<AuthProvider>();

    // 1. Kích hoạt Popup xác thực Sinh trắc học / Khóa màn hình GỐC của hệ điều hành
    final authRes = await PasskeyService.authenticateWithBiometrics(
      reason: 'Đăng nhập tài khoản $email với Passkey / Face ID / PIN',
    );

    if (authRes['success'] != true) {
      if (!mounted) return;
      final msg = authRes['message']?.toString() ?? '';
      if (!msg.contains('Hủy') && !msg.contains('hủy') && !msg.contains('cancel')) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg),
            backgroundColor: AppColors.error,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
      return;
    }

    setState(() => _isLoading = true);

    // 2. Thử đăng nhập bằng Passkey Token đã lưu (nếu token còn hạn)
    final savedToken = await PasskeyService.getPasskeyToken(email);
    final savedUser = await PasskeyService.getPasskeyUserData(email);
    if (savedToken != null && savedToken.isNotEmpty) {
      final tokenResult = await authProvider.loginWithSavedToken(savedToken, cachedUser: savedUser);
      if (tokenResult['success'] == true) {
        if (!mounted) return;
        setState(() => _isLoading = false);
        await _completeLoginSuccess(email);
        return;
      }
    }

    // 3. Nếu Token hết hạn hoặc chưa có, thử đăng nhập bằng Mật khẩu đã lưu trong vault
    final savedPwd = await PasskeyService.getPasskeyPassword(email);
    if (savedPwd != null && savedPwd.isNotEmpty) {
      final loginResult = await authProvider.login(email, savedPwd);
      if (loginResult['success'] == true) {
        if (!mounted) return;
        setState(() => _isLoading = false);
        await _completeLoginSuccess(email, password: savedPwd);
        return;
      }
    }

    // 4. Nếu chưa có mật khẩu hoặc token lưu trữ (e.g. lần đầu sau khi bật passkey mà token hết hạn)
    if (!mounted) return;
    setState(() => _isLoading = false);
    _showPasskeyPasswordPrompt(email);
  }

  void _showPasskeyPasswordPrompt(String email) {
    final pwdController = TextEditingController();
    bool isVerifying = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) => Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(ctx).viewInsets.bottom + 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF3E8FF),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(Icons.fingerprint_rounded, color: Color(0xFF8B5CF6), size: 28),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Đồng Bộ Mật Khẩu Passkey',
                          style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF0F172A)),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          email,
                          style: const TextStyle(fontSize: 13, color: Color(0xFF64748B), fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              const Text(
                'Nhập mật khẩu tài khoản của bạn một lần duy nhất để lưu an toàn vào Passkey của thiết bị. Những lần sau bạn chỉ cần chạm vân tay để vào app.',
                style: TextStyle(fontSize: 13, color: Color(0xFF64748B), height: 1.4),
              ),
              const SizedBox(height: 16),
              AppTextField(
                controller: pwdController,
                labelText: 'Mật khẩu tài khoản',
                hintText: 'Nhập mật khẩu của bạn',
                prefixIcon: Icons.lock_outline_rounded,
                isPassword: true,
              ),
              const SizedBox(height: 20),
              ElevatedButton(
                onPressed: isVerifying
                    ? null
                    : () async {
                        final pwd = pwdController.text.trim();
                        if (pwd.isEmpty) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Vui lòng nhập mật khẩu!'), backgroundColor: AppColors.error),
                          );
                          return;
                        }
                        setModalState(() => isVerifying = true);
                        final authProvider = context.read<AuthProvider>();
                        final result = await authProvider.login(email, pwd);
                        setModalState(() => isVerifying = false);

                        if (result['success'] == true) {
                          if (ctx.mounted) Navigator.pop(ctx);
                          if (!mounted) return;
                          await _completeLoginSuccess(email, password: pwd);
                        } else {
                          if (!mounted) return;
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(result['message'] ?? 'Mật khẩu không chính xác!'),
                              backgroundColor: AppColors.error,
                            ),
                          );
                        }
                      },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF8B5CF6),
                  foregroundColor: Colors.white,
                  minimumSize: const Size(double.infinity, 48),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: isVerifying
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text('Xác nhận & Lưu Passkey', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _handleLogin() async {
    final email = _emailController.text.trim();
    final password = _passwordController.text;

    if (email.isEmpty || password.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Vui lòng nhập đầy đủ thông tin đăng nhập'),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    setState(() => _isLoading = true);

    final result = await context.read<AuthProvider>().login(email, password);

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (result['success'] == true) {
      await _completeLoginSuccess(email, password: password);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result['message'] ?? 'Đăng nhập thất bại'),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  void _handleGoogleLogin() async {
    setState(() => _isLoading = true);

    final result = await context.read<AuthProvider>().loginWithGoogle(context: context);

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (result['success'] == true) {
      final user = context.read<AuthProvider>().user;
      if (user != null) {
        final isPasskey = await PasskeyService.isPasskeyEnrolled(user.email);
        final accountData = {
          'email': user.email,
          'name': user.fullName,
          'role': user.role ?? 'customer',
          'avatar_url': user.avatarUrl,
          'has_passkey': isPasskey,
        };
        await StorageService.write('last_login_account', jsonEncode(accountData));
      }

      if (!mounted) return;
      NotificationService().syncTokenToServer();
      context.read<CartProvider>().fetchCart(silent: true, force: true);

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Đăng nhập bằng Google thành công!'),
          backgroundColor: AppColors.success,
          behavior: SnackBarBehavior.floating,
        ),
      );
      if (context.canPop()) {
        context.pop(true);
      } else {
        context.go('/me');
      }
    } else {
      final msg = result['message']?.toString() ?? 'Đăng nhập Google thất bại!';
      if (!msg.contains('hủy')) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(msg),
            backgroundColor: AppColors.error,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    }
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final hasSavedAccount = _lastAccount != null;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              if (Navigator.canPop(context))
                Align(
                  alignment: Alignment.centerLeft,
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: IconButton(
                      padding: EdgeInsets.zero,
                      alignment: Alignment.centerLeft,
                      icon: const Icon(Icons.arrow_back, color: Color(0xFF0F172A), size: 28),
                      onPressed: () => context.pop(),
                    ),
                  ),
                )
              else
                const SizedBox(height: 8),

              // Logo & App Name
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  gradient: AppGradients.hero,
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.primary.withValues(alpha: 0.3),
                      blurRadius: 16,
                      offset: const Offset(0, 6),
                    ),
                  ],
                ),
                child: const Icon(
                  Icons.sports_tennis_rounded,
                  color: Colors.white,
                  size: 36,
                ),
              ),
              const SizedBox(height: 14),
              const Text(
                'Ocean Sport',
                style: TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF0F172A),
                  letterSpacing: -0.5,
                ),
              ),

              const SizedBox(height: 20),

              // ── PHÂN NHÁNH GIAO DIỆN ──
              if (hasSavedAccount && _useSavedAccount)
                _buildSavedAccountView()
              else
                _buildStandardForm(hasSavedAccount),
            ],
          ),
        ),
      ),
    );
  }

  /// 🌟 Giao diện Đăng nhập nhanh với Tài khoản đã lưu sẵn (Chỉ cần Passkey hoặc Nhập mật khẩu)
  Widget _buildSavedAccountView() {
    final name = _lastAccount!['name']?.toString() ?? 'Khách hàng Ocean';
    final email = _lastAccount!['email']?.toString() ?? '';
    final initial = name.isNotEmpty ? name.substring(0, 1).toUpperCase() : 'U';

    return Column(
      children: [
        // Card Chào mừng & Thông tin tài khoản có sẵn
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: AppColors.primary.withValues(alpha: 0.2), width: 1.2),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.08),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            children: [
              Stack(
                alignment: Alignment.bottomRight,
                children: [
                  CircleAvatar(
                    radius: 36,
                    backgroundColor: AppColors.primaryContainer,
                    child: Text(
                      initial,
                      style: const TextStyle(
                        fontSize: 30,
                        fontWeight: FontWeight.w900,
                        color: AppColors.primary,
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.all(3),
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.verified_rounded,
                      color: Color(0xFF10B981),
                      size: 20,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              const Text(
                'Chào mừng trở lại,',
                style: TextStyle(fontSize: 13, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
              ),
              const SizedBox(height: 2),
              Text(
                name,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF0F172A),
                ),
              ),
              Text(
                email,
                style: const TextStyle(
                  fontSize: 12.5,
                  color: Color(0xFF94A3B8),
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 20),

        // 🔑 Nút 1: Đăng nhập bằng Passkey / Sinh trắc học (CHỈ HIỂN THỊ KHI ĐÃ ĐĂNG KÝ KÍCH HOẠT TRƯỚC ĐÓ)
        if (_isPasskeyEnrolled) ...[
          InkWell(
            onTap: _isLoading ? null : _handlePasskeyLogin,
            borderRadius: BorderRadius.circular(16),
            child: Container(
              height: 52,
              width: double.infinity,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF8B5CF6), Color(0xFF6366F1)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF8B5CF6).withValues(alpha: 0.35),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.fingerprint_rounded, color: Colors.white, size: 24),
                  SizedBox(width: 10),
                  Text(
                    'Đăng nhập với Passkey / Face ID',
                    style: TextStyle(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                      letterSpacing: 0.2,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              const Expanded(child: Divider(color: Color(0xFFE2E8F0))),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Text(
                  'HOẶC NHẬP MẬT KHẨU',
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                    color: Colors.grey.shade500,
                  ),
                ),
              ),
              const Expanded(child: Divider(color: Color(0xFFE2E8F0))),
            ],
          ),
        ] else ...[
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: const Row(
              children: [
                Icon(Icons.info_outline_rounded, size: 18, color: Color(0xFF64748B)),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Bạn có thể kích hoạt Passkey trong phần Cài đặt tài khoản sau khi đăng nhập.',
                    style: TextStyle(fontSize: 11.5, color: Color(0xFF64748B), height: 1.3),
                  ),
                ),
              ],
            ),
          ),
        ],

        const SizedBox(height: 16),

        // 🔒 Nhập mật khẩu cho tài khoản có sẵn
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Mật khẩu',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF334155),
                  ),
                ),
                GestureDetector(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const ForgotPasswordScreen(),
                      ),
                    );
                  },
                  child: const Text(
                    'Quên mật khẩu?',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: AppColors.primary,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            _buildTextField(
              hint: '••••••••',
              icon: Icons.lock_outline_rounded,
              isPassword: true,
              controller: _passwordController,
            ),
            const SizedBox(height: 18),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: ElevatedButton(
                onPressed: _isLoading ? null : _handleLogin,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: _isLoading
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
                      )
                    : const Text(
                        'Đăng nhập',
                        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
                      ),
              ),
            ),
          ],
        ),

        const SizedBox(height: 16),

        // Nút chuyển đổi: Sử dụng tài khoản khác
        TextButton.icon(
          onPressed: () {
            setState(() {
              _useSavedAccount = false;
              _passwordController.clear();
            });
          },
          icon: const Icon(Icons.swap_horiz_rounded, size: 18, color: Color(0xFF64748B)),
          label: const Text(
            'Sử dụng tài khoản khác',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: Color(0xFF64748B),
            ),
          ),
        ),

        const SizedBox(height: 12),

        // Khám phá ngay
        OutlinedButton.icon(
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/home');
            }
          },
          icon: const Icon(Icons.explore_outlined, size: 18, color: Color(0xFF64748B)),
          label: const Text(
            'Khám phá ngay (Chế độ Khách)',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF64748B)),
          ),
          style: OutlinedButton.styleFrom(
            minimumSize: const Size(double.infinity, 44),
            side: const BorderSide(color: Color(0xFFE2E8F0)),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            backgroundColor: Colors.white,
          ),
        ),
      ],
    );
  }

  /// 📝 Giao diện Form Tiêu Chuẩn (khi nhập tài khoản mới hoặc khi chưa có tài khoản lưu)
  Widget _buildStandardForm(bool hasSavedAccount) {
    return Column(
      children: [
        // Nếu có tài khoản lưu trước đó, hiện nút quay lại
        if (hasSavedAccount) ...[
          InkWell(
            onTap: () => setState(() => _useSavedAccount = true),
            borderRadius: BorderRadius.circular(14),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.account_circle_rounded, color: AppColors.primary, size: 22),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Quay lại tài khoản ${_lastAccount!['name'] ?? ''}',
                      style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: Color(0xFF334155)),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const Icon(Icons.arrow_forward_ios_rounded, size: 13, color: Color(0xFF94A3B8)),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
        ],

        // Form Email + Mật khẩu
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Email hoặc Tên đăng nhập',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: Color(0xFF334155),
              ),
            ),
            const SizedBox(height: 8),
            _buildTextField(
              hint: 'name@example.com',
              icon: Icons.alternate_email_rounded,
              controller: _emailController,
            ),

            const SizedBox(height: 16),

            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Mật khẩu',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF334155),
                  ),
                ),
                GestureDetector(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const ForgotPasswordScreen(),
                      ),
                    );
                  },
                  child: const Text(
                    'Quên mật khẩu?',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: AppColors.primary,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            _buildTextField(
              hint: '••••••••',
              icon: Icons.lock_outline_rounded,
              isPassword: true,
              controller: _passwordController,
            ),

            const SizedBox(height: 24),

            // Nút Đăng nhập
            SizedBox(
              width: double.infinity,
              height: 48,
              child: ElevatedButton(
                onPressed: _isLoading ? null : _handleLogin,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: _isLoading
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
                      )
                    : const Text(
                        'Đăng nhập',
                        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, letterSpacing: 0.2),
                      ),
              ),
            ),

            const SizedBox(height: 20),

            // Divider
            Row(
              children: [
                const Expanded(child: Divider(color: Color(0xFFE2E8F0))),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  child: Text(
                    'HOẶC ĐĂNG NHẬP VỚI',
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                      color: Colors.grey.shade500,
                    ),
                  ),
                ),
                const Expanded(child: Divider(color: Color(0xFFE2E8F0))),
              ],
            ),

            const SizedBox(height: 16),

            // Google Sign-In Button
            InkWell(
              onTap: _isLoading ? null : _handleGoogleLogin,
              borderRadius: BorderRadius.circular(14),
              child: Container(
                height: 46,
                width: double.infinity,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.03),
                      blurRadius: 4,
                      offset: const Offset(0, 1.5),
                    ),
                  ],
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(4.5),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEA4335).withValues(alpha: 0.1),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.g_mobiledata_rounded, color: Color(0xFFEA4335), size: 24),
                    ),
                    const SizedBox(width: 8),
                    const Text(
                      'Tiếp tục với Google',
                      style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: Color(0xFF1E293B)),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 14),

            // Khám Phá Ngay (Chế độ Khách)
            OutlinedButton(
              onPressed: () {
                if (context.canPop()) {
                  context.pop();
                } else {
                  context.go('/home');
                }
              },
              style: OutlinedButton.styleFrom(
                minimumSize: const Size(double.infinity, 44),
                side: const BorderSide(color: Color(0xFFE2E8F0)),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                backgroundColor: Colors.white,
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.explore_outlined, size: 18, color: Color(0xFF64748B)),
                  SizedBox(width: 6),
                  Text(
                    'Khám phá ngay (Chế độ Khách)',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF64748B)),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // Đăng ký
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Text('Bạn chưa có tài khoản? ', style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
                GestureDetector(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (context) => const RegisterScreen()),
                    );
                  },
                  child: const Text(
                    'Đăng ký ngay',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.primary),
                  ),
                ),
              ],
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildTextField({
    required String hint,
    required IconData icon,
    bool isPassword = false,
    required TextEditingController controller,
  }) {
    return AppTextField(
      controller: controller,
      hintText: hint,
      prefixIcon: icon,
      isPassword: isPassword,
    );
  }
}
