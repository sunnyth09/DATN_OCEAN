import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../config/app_config.dart';

/// ============================================================
/// WEBVIEW LOGIN SCREEN
/// ============================================================
/// Mở trang Login web thật (ocean.pro.vn) có Turnstile CAPTCHA
/// hoạt động sẵn. Sau khi user đăng nhập thành công trên web,
/// bắt JWT token từ sessionStorage → lưu vào app → đóng WebView.
/// ============================================================

class WebViewLoginScreen extends StatefulWidget {
  /// Email & password đã nhập từ form native (auto-fill vào web form)
  final String? prefillEmail;
  final String? prefillPassword;

  const WebViewLoginScreen({
    super.key,
    this.prefillEmail,
    this.prefillPassword,
  });

  @override
  State<WebViewLoginScreen> createState() => _WebViewLoginScreenState();
}

class _WebViewLoginScreenState extends State<WebViewLoginScreen> {
  late final WebViewController _controller;
  bool _isLoading = true;
  Timer? _tokenCheckTimer;

  @override
  void initState() {
    super.initState();

    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white)
      ..addJavaScriptChannel(
        'FlutterAuth',
        onMessageReceived: _onAuthMessage,
      )
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (url) {
            if (mounted) setState(() => _isLoading = true);
          },
          onPageFinished: (url) {
            if (mounted) setState(() => _isLoading = false);
            _injectTokenWatcher();
            _autofillForm();
          },
          onNavigationRequest: (request) {
            // Cho phép tất cả navigation
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(AppConfig.kFrontendLoginUrl));
  }

  /// Inject JS để theo dõi sessionStorage — khi có auth_token → gửi về Flutter
  void _injectTokenWatcher() {
    _tokenCheckTimer?.cancel();

    // Kiểm tra mỗi 500ms xem đã login thành công chưa
    _tokenCheckTimer = Timer.periodic(const Duration(milliseconds: 500), (_) {
      _controller.runJavaScript('''
        (function() {
          var token = sessionStorage.getItem('auth_token');
          var user = sessionStorage.getItem('user');
          if (token && token.length > 10) {
            FlutterAuth.postMessage(JSON.stringify({
              'action': 'login_success',
              'access_token': token,
              'user': user
            }));
          }
        })();
      ''');
    });
  }

  /// Auto-fill email/password nếu đã nhập từ form native
  void _autofillForm() {
    if (widget.prefillEmail != null && widget.prefillEmail!.isNotEmpty) {
      _controller.runJavaScript('''
        (function() {
          var emailInput = document.getElementById('login-email');
          if (emailInput) {
            emailInput.value = '${widget.prefillEmail}';
            emailInput.dispatchEvent(new Event('input', { bubbles: true }));
          }
        })();
      ''');
    }
    if (widget.prefillPassword != null && widget.prefillPassword!.isNotEmpty) {
      _controller.runJavaScript('''
        (function() {
          var pwInput = document.getElementById('login-password');
          if (pwInput) {
            pwInput.value = '${widget.prefillPassword}';
            pwInput.dispatchEvent(new Event('input', { bubbles: true }));
          }
        })();
      ''');
    }
  }

  /// Nhận token từ JS channel
  void _onAuthMessage(JavaScriptMessage message) async {
    try {
      final data = jsonDecode(message.message);

      if (data['action'] == 'login_success') {
        final accessToken = data['access_token'] as String?;
        final userJson = data['user'] as String?;

        if (accessToken != null && accessToken.isNotEmpty) {
          // Dừng timer
          _tokenCheckTimer?.cancel();

          // Lưu vào Secure Storage
          const storage = FlutterSecureStorage(
            aOptions: AndroidOptions(encryptedSharedPreferences: true),
          );
          await storage.write(key: 'access_token', value: accessToken);
          if (userJson != null) {
            await storage.write(key: 'user_data', value: userJson);
          }

          debugPrint('[WebViewLogin] ✅ Login thành công! Token saved.');

          // Xóa sessionStorage trên web để tránh leak
          _controller.runJavaScript('''
            sessionStorage.removeItem('auth_token');
            sessionStorage.removeItem('user');
          ''');

          if (mounted) {
            // Trả kết quả về cho login_screen
            Navigator.pop(context, true);
          }
        }
      }
    } catch (e) {
      debugPrint('[WebViewLogin] Parse error: $e');
    }
  }

  @override
  void dispose() {
    _tokenCheckTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 1,
        leading: IconButton(
          icon: const Icon(Icons.close, color: Color(0xFF0F172A)),
          onPressed: () => Navigator.pop(context, false),
        ),
        title: const Text(
          'Đăng nhập bảo mật',
          style: TextStyle(
            color: Color(0xFF0F172A),
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
        centerTitle: true,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(3),
          child: _isLoading
              ? const LinearProgressIndicator(
                  color: Color(0xFF0EA5E9),
                  backgroundColor: Color(0xFFE0F2FE),
                )
              : const SizedBox.shrink(),
        ),
      ),
      body: WebViewWidget(controller: _controller),
    );
  }
}
