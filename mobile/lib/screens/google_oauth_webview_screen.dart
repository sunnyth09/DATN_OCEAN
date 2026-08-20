import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../config/app_config.dart';
import '../config/app_theme.dart';

/// Màn hình Google OAuth WebView an toàn và chuẩn quốc tế.
/// Tự động bắt mã xác thực code từ Google Redirect Callback và đăng nhập tức thì.
class GoogleOAuthWebViewScreen extends StatefulWidget {
  const GoogleOAuthWebViewScreen({super.key});

  @override
  State<GoogleOAuthWebViewScreen> createState() => _GoogleOAuthWebViewScreenState();
}

class _GoogleOAuthWebViewScreenState extends State<GoogleOAuthWebViewScreen> {
  late final WebViewController _controller;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();

    final clientId = AppConfig.kGoogleClientId;
    final redirectUri = AppConfig.kGoogleRedirectUri;
    const scope = 'email profile openid';

    final authUrl =
        'https://accounts.google.com/o/oauth2/v2/auth?client_id=$clientId&redirect_uri=${Uri.encodeComponent(redirectUri)}&response_type=code&scope=${Uri.encodeComponent(scope)}&access_type=offline&prompt=consent';

    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setUserAgent('Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36')
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (url) {
            _checkCallback(url);
          },
          onPageFinished: (_) {
            if (mounted) setState(() => _isLoading = false);
          },
          onNavigationRequest: (request) {
            if (_checkCallback(request.url)) {
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(authUrl));
  }

  bool _checkCallback(String url) {
    if (url.startsWith(AppConfig.kGoogleRedirectUri) || url.contains('/client/auth/google/callback')) {
      final uri = Uri.parse(url);
      final code = uri.queryParameters['code'];
      if (code != null && code.isNotEmpty) {
        Navigator.of(context).pop(code);
        return true;
      }
      final error = uri.queryParameters['error'];
      if (error != null) {
        Navigator.of(context).pop(null);
        return true;
      }
    }
    return false;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: const Text(
          'Đăng nhập Google',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
        ),
        leading: IconButton(
          icon: const Icon(Icons.close_rounded),
          onPressed: () => Navigator.of(context).pop(null),
        ),
        bottom: _isLoading
            ? const PreferredSize(
                preferredSize: Size.fromHeight(3),
                child: LinearProgressIndicator(
                  backgroundColor: Colors.transparent,
                  color: AppColors.primary,
                  minHeight: 3,
                ),
              )
            : null,
      ),
      body: WebViewWidget(controller: _controller),
    );
  }
}
