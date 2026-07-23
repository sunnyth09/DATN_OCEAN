import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'order_success_screen.dart';

class PaymentWebviewScreen extends StatefulWidget {
  final String url;
  final String paymentMethod;
  final String? orderCode;
  final num? grandTotal;

  const PaymentWebviewScreen({
    super.key,
    required this.url,
    required this.paymentMethod,
    this.orderCode,
    this.grandTotal,
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
            // QS-project scheme hoặc URL chứa tham số trả về từ cổng thanh toán
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
    if (!mounted) return;

    final params = Uri.parse(url).queryParameters;
    // VNPay: vnp_ResponseCode == '00' là thành công (24 = huỷ, khác = lỗi).
    // MoMo: resultCode == '0' là thành công.
    final vnpCode = params['vnp_ResponseCode'];
    final momoCode = params['resultCode'];

    bool success;
    if (vnpCode != null) {
      success = vnpCode == '00';
    } else if (momoCode != null) {
      success = momoCode == '0';
    } else {
      // Scheme qs-project://payment-return có thể kèm status
      final status = params['status'];
      success = status == 'success' || status == '00' || status == '0';
    }

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Thanh toán thành công!'),
          backgroundColor: Colors.green,
        ),
      );
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(
          builder: (_) => OrderSuccessScreen(
            orderCode: widget.orderCode,
            grandTotal: widget.grandTotal,
          ),
        ),
        (route) => false,
      );
    } else {
      // Huỷ hoặc thất bại: KHÔNG đưa sang màn thành công. Đơn đã tạo,
      // user có thể thanh toán lại trong "Đơn hàng của tôi".
      final isCancelled = vnpCode == '24' || momoCode == '1003';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isCancelled
                ? 'Bạn đã huỷ thanh toán. Đơn hàng vẫn được lưu, có thể thanh toán lại trong "Đơn hàng của tôi".'
                : 'Thanh toán thất bại. Đơn hàng vẫn được lưu, vui lòng thử lại trong "Đơn hàng của tôi".',
          ),
          backgroundColor: isCancelled ? Colors.orange : Colors.red,
          duration: const Duration(seconds: 4),
        ),
      );
      Navigator.of(context).pop();
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
