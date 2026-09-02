import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../config/app_theme.dart';
import '../services/api_client.dart';
import '../widgets/app_toast.dart';

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final currentCtrl = TextEditingController();
  final newCtrl = TextEditingController();
  final confirmCtrl = TextEditingController();
  bool _isSaving = false;
  bool _showCurrent = false;
  bool _showNew = false;
  bool _showConfirm = false;

  @override
  void dispose() {
    // P1-03: Gi\u1ea3i ph\u00f3ng controller \u2014 tr\u00e1nh memory leak
    currentCtrl.dispose();
    newCtrl.dispose();
    confirmCtrl.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (currentCtrl.text.isEmpty || newCtrl.text.isEmpty || confirmCtrl.text.isEmpty) {
      AppToast.showWarning(context, message: 'Vui lòng điền đầy đủ thông tin!');
      return;
    }
    if (newCtrl.text != confirmCtrl.text) {
      AppToast.showWarning(context, message: 'Xác nhận mật khẩu không khớp!');
      return;
    }
    if (newCtrl.text.length < 8) {
      AppToast.showWarning(context, message: 'Mật khẩu mới phải có ít nhất 8 ký tự!');
      return;
    }

    setState(() => _isSaving = true);
    try {
      await ApiClient().dio.put('/profile/password', data: {
        'current_password': currentCtrl.text,
        'new_password': newCtrl.text,
        'new_password_confirmation': confirmCtrl.text,
      });

      if (mounted) {
        AppToast.showSuccess(context, message: 'Đổi mật khẩu thành công!');
        context.pop();
      }
    } on DioException catch (e) {
      if (mounted) AppToast.showError(context, message: e.response?.data?['message'] ?? 'Lỗi thay đổi mật khẩu!');
    } catch (_) {
      if (mounted) AppToast.showError(context, message: 'Lỗi kết nối máy chủ!');
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }


  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Đổi mật khẩu', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: AppColors.primary),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A)),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/me');
            }
          },
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 10)]),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _passField('Mật khẩu hiện tại', currentCtrl, _showCurrent, () => setState(() => _showCurrent = !_showCurrent)),
              const SizedBox(height: 20),
              _passField('Mật khẩu mới', newCtrl, _showNew, () => setState(() => _showNew = !_showNew)),
              const SizedBox(height: 20),
              _passField('Xác nhận mật khẩu mới', confirmCtrl, _showConfirm, () => setState(() => _showConfirm = !_showConfirm)),
              const SizedBox(height: 32),
              SizedBox(
                width: double.infinity,
                height: 46,
                child: ElevatedButton(
                  onPressed: _isSaving ? null : submit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    elevation: 0,
                  ),
                  child: _isSaving
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text('Cập Nhật Mật Khẩu', style: TextStyle(color: Colors.white, fontSize: 14.5, fontWeight: FontWeight.w700)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _passField(String label, TextEditingController ctrl, bool show, VoidCallback toggle) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: Color(0xFF334155))),
        const SizedBox(height: 5),
        TextField(
          controller: ctrl,
          obscureText: !show,
          style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600, color: Color(0xFF0F172A)),
          decoration: InputDecoration(
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.primary, width: 1.5)),
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            suffixIcon: IconButton(
              icon: Icon(show ? Icons.visibility_off_outlined : Icons.visibility_outlined, color: const Color(0xFF64748B), size: 18),
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
              onPressed: toggle,
            ),
          ),
        ),
      ],
    );
  }
}
