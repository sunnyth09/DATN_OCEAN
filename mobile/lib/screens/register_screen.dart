import 'package:go_router/go_router.dart';
import 'package:flutter/services.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/auth_service.dart';
import '../config/app_theme.dart';
import '../widgets/app_text_field.dart';
import '../widgets/app_toast.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  bool _isLoading = false;

  // P1-02: Email regex pattern — validate trước khi gọi API
  static final RegExp _emailPattern = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _passwordConfirmController = TextEditingController();

  double _passwordStrength = 0;

  @override
  void initState() {
    super.initState();
    _passwordController.addListener(_updatePasswordStrength);
  }

  void _updatePasswordStrength() {
    final pwd = _passwordController.text;
    double strength = 0;
    if (pwd.isNotEmpty) {
      if (pwd.length >= 6) strength += 0.25;
      if (pwd.length >= 8) strength += 0.25;
      if (RegExp(r'[A-Z]').hasMatch(pwd)) strength += 0.25;
      if (RegExp(r'[0-9!@#\$&*~]').hasMatch(pwd)) strength += 0.25;
    }
    setState(() {
      _passwordStrength = strength;
    });
  }

  void _handleGoogleLogin() async {
    HapticFeedback.lightImpact();
    setState(() => _isLoading = true);

    final result = await context.read<AuthProvider>().loginWithGoogle(context: context);

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (result['success'] == true) {
      AppToast.showSuccess(
        context,
        message: 'Đăng nhập bằng Google thành công!',
      );
      if (mounted) {
        context.go('/');
      }
    } else {
      final msg = result['message']?.toString() ?? 'Đăng nhập Google thất bại!';
      if (!msg.contains('hủy')) {
        AppToast.showError(
          context,
          message: msg,
        );
      }
    }
  }

  void _handleRegister() async {
    HapticFeedback.lightImpact();
    final name = _nameController.text.trim();
    final email = _emailController.text.trim();
    final password = _passwordController.text;
    final passwordConfirm = _passwordConfirmController.text;

    if (name.isEmpty || email.isEmpty || password.isEmpty || passwordConfirm.isEmpty) {
      AppToast.showWarning(
        context,
        message: 'Vui lòng điền đầy đủ thông tin',
      );
      return;
    }

    // P1-02: Validate email format
    if (!_emailPattern.hasMatch(email)) {
      AppToast.showWarning(
        context,
        message: 'Địa chỉ email không hợp lệ. Vui lòng kiểm tra lại.',
      );
      return;
    }

    // Validate password min length
    if (password.length < 8) {
      AppToast.showWarning(
        context,
        message: 'Mật khẩu phải có ít nhất 8 ký tự.',
      );
      return;
    }

    if (password != passwordConfirm) {
      AppToast.showWarning(
        context,
        message: 'Mật khẩu xác nhận không khớp',
      );
      return;
    }

    setState(() => _isLoading = true);

    final result = await AuthService.register(
      name,
      email,
      password,
      passwordConfirm,
    );

    setState(() => _isLoading = false);

    if (result['success']) {
      if (mounted) {
        AppToast.showSuccess(
          context,
          message: 'Đăng ký thành công! Vui lòng đăng nhập.',
        );
        context.pop(); // Go back to Login Screen
      }
    } else {
      if (mounted) {
        AppToast.showError(
          context,
          message: result['message'] ?? 'Đăng ký thất bại!',
        );
      }
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _passwordConfirmController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              AppColors.primary.withValues(alpha: 0.08),
              Colors.white,
            ],
            stops: const [0.0, 0.3],
          ),
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Align(
                  alignment: Alignment.centerLeft,
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: IconButton(
                      padding: EdgeInsets.zero,
                      alignment: Alignment.centerLeft,
                      icon: const Icon(Icons.arrow_back, color: Color(0xFF0F172A), size: 28),
                      onPressed: () {
                        if (context.canPop()) {
                          context.pop();
                        } else {
                          context.go('/login');
                        }
                      },
                    ),
                  ),
                ),
                // Logo
              Container(
                width: 72, height: 72,
                decoration: BoxDecoration(
                  color: AppColors.primaryContainer,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: const Icon(Icons.sports_tennis, color: AppColors.primary, size: 40),
              ),
              const SizedBox(height: 16),
              const Text('Đăng ký tài khoản', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
              const SizedBox(height: 8),
              const Text('Gia nhập Ocean Sport ngay hôm nay', style: TextStyle(fontSize: 14, color: Color(0xFF64748B), fontWeight: FontWeight.w600)),
              
              const SizedBox(height: 32),

              // Form
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                   const Text('Họ và tên', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                  const SizedBox(height: 8),
                  _buildTextField(
                    hint: 'Nguyễn Văn A',
                    icon: Icons.person_outline,
                    controller: _nameController,
                  ),
                  const SizedBox(height: 16),

                  const Text('Email', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                  const SizedBox(height: 8),
                  _buildTextField(
                    hint: 'name@example.com',
                    icon: Icons.email_outlined,
                    controller: _emailController,
                  ),
                  const SizedBox(height: 16),
                  
                  const Text('Mật khẩu', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                  const SizedBox(height: 8),
                  _buildTextField(
                    hint: '••••••••',
                    icon: Icons.lock_outline,
                    isPassword: true,
                    controller: _passwordController,
                    isConfirmPass: false,
                  ),
                  if (_passwordController.text.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Row(
                        children: [
                          Expanded(
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(4),
                              child: LinearProgressIndicator(
                                value: _passwordStrength,
                                backgroundColor: const Color(0xFFE2E8F0),
                                valueColor: AlwaysStoppedAnimation<Color>(
                                  _passwordStrength <= 0.25 ? AppColors.error :
                                  _passwordStrength <= 0.5 ? AppColors.warning :
                                  _passwordStrength <= 0.75 ? Colors.lightBlue : AppColors.success,
                                ),
                                minHeight: 6,
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            _passwordStrength <= 0.25 ? 'Yếu' :
                            _passwordStrength <= 0.5 ? 'Trung bình' :
                            _passwordStrength <= 0.75 ? 'Khá' : 'Mạnh',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: _passwordStrength <= 0.25 ? AppColors.error :
                                  _passwordStrength <= 0.5 ? AppColors.warning :
                                  _passwordStrength <= 0.75 ? Colors.lightBlue : AppColors.success,
                            ),
                          ),
                        ],
                      ),
                    ),
                  const SizedBox(height: 16),

                  const Text('Xác nhận mật khẩu', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF334155))),
                  const SizedBox(height: 8),
                  _buildTextField(
                    hint: '••••••••',
                    icon: Icons.lock_outline,
                    isPassword: true,
                    controller: _passwordConfirmController,
                    isConfirmPass: true,
                  ),
                  
                  const SizedBox(height: 24),
                  
                  // Nút Đăng ký
                  SizedBox(
                    width: double.infinity,
                    height: 46,
                    child: ElevatedButton(
                      onPressed: _isLoading ? null : _handleRegister,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      child: _isLoading 
                          ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : const Text('Đăng ký tài khoản', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: Colors.white)),
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
                          'Hoặc tiếp tục với',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
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
                      height: 44,
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
                            child: const Icon(
                              Icons.g_mobiledata_rounded,
                              color: Color(0xFFEA4335),
                              size: 22,
                            ),
                          ),
                          const SizedBox(width: 8),
                          const Text(
                            'Đăng ký với Google',
                            style: TextStyle(
                              fontSize: 13.5,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF1E293B),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  
                  const SizedBox(height: 24),
                ],
              ),
            ],
          ),
        ),
      ),
      ),
    );
  }

  Widget _buildTextField({
    required String hint, 
    required IconData icon, 
    bool isPassword = false, 
    required TextEditingController controller,
    bool isConfirmPass = false,
  }) {
    return AppTextField(
      controller: controller,
      hintText: hint,
      prefixIcon: icon,
      isPassword: isPassword,
    );
  }
}
