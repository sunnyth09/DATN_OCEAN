import 'dart:async';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../config/app_config.dart';

/// ============================================================
/// CLOUDFLARE TURNSTILE SERVICE (v2 — Fixed)
/// ============================================================
/// Sử dụng WebView ẩn với baseUrl = domain thật để Turnstile
/// nhận diện đúng origin. Gửi token về Flutter qua JS Channel.
/// ============================================================

class TurnstileService extends StatefulWidget {
  final ValueChanged<String> onTokenReceived;
  final ValueChanged<String>? onError;

  const TurnstileService({
    super.key,
    required this.onTokenReceived,
    this.onError,
  });

  @override
  State<TurnstileService> createState() => TurnstileServiceState();
}

class TurnstileServiceState extends State<TurnstileService> {
  late final WebViewController _controller;

  /// HTML page — Turnstile cần chạy trên domain thật để hoạt động
  String _buildHtml() {
    final siteKey = AppConfig.kTurnstileSiteKey;
    debugPrint('[Turnstile] Building HTML with siteKey: ${siteKey.isNotEmpty ? "${siteKey.substring(0, 8)}..." : "EMPTY!"}');

    return '''
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    * { margin: 0; padding: 0; }
    body { background: transparent; }
  </style>
</head>
<body>
  <div id="turnstile-box"></div>

  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onLoadTurnstile" async defer></script>
  <script>
    var widgetId = null;

    function onLoadTurnstile() {
      try {
        widgetId = turnstile.render('#turnstile-box', {
          sitekey: '$siteKey',
          callback: function(token) {
            TurnstileChannel.postMessage('TOKEN:' + token);
          },
          'expired-callback': function() {
            TurnstileChannel.postMessage('EXPIRED');
          },
          'error-callback': function(code) {
            TurnstileChannel.postMessage('ERROR:' + (code || 'unknown'));
          },
          size: 'invisible',
          retry: 'auto',
          'retry-interval': 2000
        });
        TurnstileChannel.postMessage('RENDERED');
      } catch(e) {
        TurnstileChannel.postMessage('ERROR:render_failed:' + e.toString());
      }
    }

    function doReset() {
      if (widgetId !== null && typeof turnstile !== 'undefined') {
        turnstile.reset(widgetId);
      } else {
        location.reload();
      }
    }
  </script>
</body>
</html>
''';
  }

  @override
  void initState() {
    super.initState();

    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.transparent)
      ..setUserAgent('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36')
      ..addJavaScriptChannel(
        'TurnstileChannel',
        onMessageReceived: _onMessage,
      )
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageFinished: (url) {
            debugPrint('[Turnstile] Page loaded: $url');
          },
          onWebResourceError: (error) {
            debugPrint('[Turnstile] Resource error: ${error.description} (${error.errorCode})');
          },
        ),
      )
      // ★ KEY FIX: baseUrl phải là domain thật để Turnstile hoạt động
      ..loadHtmlString(
        _buildHtml(),
        baseUrl: 'https://api.ocean.pro.vn',
      );
  }

  void _onMessage(JavaScriptMessage message) {
    final msg = message.message;
    debugPrint('[Turnstile] Message: $msg');

    if (msg.startsWith('TOKEN:')) {
      final token = msg.substring(6);
      if (token.isNotEmpty) {
        widget.onTokenReceived(token);
      }
    } else if (msg == 'EXPIRED') {
      debugPrint('[Turnstile] Token expired → resetting');
      resetToken();
    } else if (msg.startsWith('ERROR:')) {
      widget.onError?.call(msg.substring(6));
    } else if (msg == 'RENDERED') {
      debugPrint('[Turnstile] Widget rendered, waiting for token...');
    }
  }

  /// Reset để lấy token mới
  void resetToken() {
    _controller.runJavaScript('doReset();').catchError((e) {
      debugPrint('[Turnstile] Reset error, reloading page');
      _controller.loadHtmlString(
        _buildHtml(),
        baseUrl: 'https://api.ocean.pro.vn',
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    // WebView ẩn — đặt ngoài viewport
    return SizedBox(
      width: 0,
      height: 0,
      child: WebViewWidget(controller: _controller),
    );
  }
}
