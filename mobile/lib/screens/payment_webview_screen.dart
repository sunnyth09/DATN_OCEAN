import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../widgets/app_toast.dart';

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
  bool _isRedirecting = false;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (url) {
            if (mounted) setState(() => _isLoading = true);
            _checkUrl(url);
          },
          onPageFinished: (url) {
            if (mounted) setState(() => _isLoading = false);
            _checkUrl(url);
          },
          onNavigationRequest: (request) {
            _checkUrl(request.url);
            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.url));
  }

  void _checkUrl(String url) {
    if (_isRedirecting) return;
    final uri = Uri.tryParse(url);
    if (uri == null) return;

    // Bắt return URL từ VNPAY hoặc MOMO hoặc deep link custom
    if (url.contains('/vnpay/return') ||
        url.contains('/momo/return') ||
        url.contains('payment-return') ||
        uri.scheme == 'qs-project') {
      _isRedirecting = true;
      _handlePaymentResult(uri);
    }
  }

  void _handlePaymentResult(Uri uri) {
    final params = uri.queryParameters;
    final vnpCode = params['vnp_ResponseCode'];
    final momoCode = params['resultCode'];

    bool success = false;
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
      AppToast.showSuccess(
        context,
        message: 'Thanh toán thành công!',
      );
      // P1-09: Dùng context.go() (GoRouter) thay Navigator.pushAndRemoveUntil()
      context.go(
        '/order-success',
        extra: {
          'orderCode': widget.orderCode,
          'grandTotal': widget.grandTotal,
          'orderId': null,
        },
      );
    } else {
      // Huỷ hoặc thất bại: KHÔNG đưa sang màn thành công. Đơn đã tạo,
      // user có thể thanh toán lại trong "Đơn hàng của tôi".
      final isCancelled = vnpCode == '24' || momoCode == '1003';
      if (isCancelled) {
        AppToast.showWarning(
          context,
          message: 'Bạn đã huỷ thanh toán. Đơn hàng vẫn được lưu trong "Đơn hàng của tôi".',
        );
      } else {
        AppToast.showError(
          context,
          message: 'Thanh toán thất bại. Đơn hàng vẫn được lưu trong "Đơn hàng của tôi".',
        );
      }
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
