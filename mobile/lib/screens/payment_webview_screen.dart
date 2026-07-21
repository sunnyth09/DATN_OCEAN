import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'order_success_screen.dart';

class PaymentWebviewScreen extends StatefulWidget {
  final String url;
  final String paymentMethod; 

  const PaymentWebviewScreen({
    super.key,
    required this.url,
    required this.paymentMethod,
  });

  @override
  State<PaymentWebviewScreen> createState() => _PaymentWebviewScreenState();
}

class _PaymentWebviewScreenState extends State<PaymentWebviewScreen> {
  late final WebViewController _controller;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (String url) {
            if (mounted) setState(() => _isLoading = true);
          },
          onPageFinished: (String url) {
            if (mounted) setState(() => _isLoading = false);
          },
          onNavigationRequest: (NavigationRequest request) {
            final url = request.url;
            // QS-project scheme hoặc URL chứa tham số trả về
            if (url.startsWith('qs-project://payment-return') || 
                url.contains('vnp_ResponseCode') || 
                url.contains('resultCode')) {
              _handlePaymentReturn(url);
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.url));
  }

  void _handlePaymentReturn(String url) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Xử lý thanh toán hoàn tất!'),
          backgroundColor: Colors.green,
        ),
      );
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const OrderSuccessScreen()),
        (route) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) async {
        if (didPop) return;
        final canGoBack = await _controller.canGoBack();
        if (canGoBack) {
          _controller.goBack();
        } else {
          if (context.mounted) {
            showDialog(
              context: context,
              builder: (ctx) => AlertDialog(
                title: const Text('Huỷ thanh toán?'),
                content: const Text('Bạn có chắc chắn muốn thoát? Đơn hàng đã được tạo và bạn có thể thanh toán sau.'),
                actions: [
                  TextButton(
                    onPressed: () => ctx.pop(),
                    child: const Text('Không'),
                  ),
                  TextButton(
                    onPressed: () {
                      ctx.pop(); // Đóng dialog
                      context.pop(); // Đóng webview
                    },
                    child: const Text('Đồng ý'),
                  ),
                ],
              ),
            );
          }
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text('Thanh toán ${widget.paymentMethod.toUpperCase()}'),
          leading: IconButton(
            icon: const Icon(Icons.arrow_back),
            onPressed: () async {
              final canGoBack = await _controller.canGoBack();
              if (canGoBack) {
                _controller.goBack();
              } else {
                if (context.mounted) Navigator.maybePop(context);
              }
            },
          ),
        ),
        body: Stack(
          children: [
            WebViewWidget(controller: _controller),
            if (_isLoading)
              const Center(child: CircularProgressIndicator()),
          ],
        ),
      ),
    );
  }
}
