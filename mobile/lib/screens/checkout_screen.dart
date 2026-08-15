import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:provider/provider.dart';

import '../config/app_theme.dart';
import '../services/api_client.dart';
import '../providers/cart_provider.dart';
import '../utils/format_utils.dart';
import '../widgets/app_empty_state.dart';
import 'address_screen.dart';
import 'order_success_screen.dart';
import 'payment_webview_screen.dart';
import 'checkout/widgets/checkout_address_box.dart';
import 'checkout/widgets/checkout_payment_box.dart';
import 'checkout/widgets/checkout_coupon_box.dart';
import 'checkout/widgets/checkout_order_summary.dart';
import 'checkout/widgets/checkout_bottom_bar.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  int selectedPayment = 0; // 0: COD, 1: VNPay, 2: MoMo
  Map<String, dynamic>? defaultAddress;
  List<dynamic> cartItems = [];
  num subtotal = 0;
  int shippingFee = 0;
  bool _isCalculatingShip = false;
  bool isLoading = true;
  String? errorMessage;

  Map<String, dynamic>? appliedCoupon;
  int discountAmount = 0;
  final _couponCtrl = TextEditingController();
  bool _isApplyingCoupon = false;
  bool _isPlacingOrder = false;

  @override
  void initState() {
    super.initState();
    fetchCheckoutData();
  }

  @override
  void dispose() {
    _couponCtrl.dispose();
    super.dispose();
  }

  Future<void> fetchCheckoutData() async {
    if (!mounted) return;
    setState(() {
      isLoading = true;
      errorMessage = null;
    });

    try {
      final results = await Future.wait([
        ApiClient().dio.get('/profile/addresses'),
        ApiClient().dio.get('/cart'),
      ]);

      final addressRes = results[0];
      final cartRes = results[1];

      final addrList = addressRes.data['data'] as List? ?? [];
      if (addrList.isNotEmpty) {
        defaultAddress = addrList.firstWhere(
          (a) => a['is_default'] == 1 || a['is_default'] == true,
          orElse: () => addrList.first,
        );
      }

      final cData = cartRes.data['data'];
      if (cData != null) {
        final allItems = (cData['items'] as List?) ?? [];
        final selectedItems = allItems
            .where((item) => item['selected'] == 1 || item['selected'] == true)
            .toList();
        cartItems = selectedItems.isNotEmpty ? selectedItems : allItems;

        final apiTotal = cData['total_price'];
        if (apiTotal != null && num.tryParse(apiTotal.toString()) != null) {
          subtotal = num.parse(apiTotal.toString());
        } else {
          subtotal = cartItems.fold<num>(0, (sum, item) {
            final lineTotal =
                num.tryParse(item['line_total']?.toString() ?? '0') ?? 0;
            return sum + lineTotal;
          });
        }
      }

      await _calculateShippingFee();

      if (mounted) setState(() => isLoading = false);
    } on DioException catch (e) {
      if (mounted) {
        setState(() {
          errorMessage =
              e.response?.data?['message'] ??
              'Không thể tải dữ liệu thanh toán';
          isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          errorMessage = 'Lỗi kết nối máy chủ. Vui lòng thử lại.';
          isLoading = false;
        });
      }
    }
  }

  Future<void> _calculateShippingFee() async {
    if (defaultAddress == null) return;
    final wardCode = defaultAddress!['ward_code']?.toString() ?? '';
    if (wardCode.isEmpty) return;

    if (!mounted) return;
    setState(() => _isCalculatingShip = true);

    try {
      final response = await ApiClient().dio.post(
        '/ghn/calculate-fee',
        data: {
          'ward_code': wardCode,
          'weight': 500,
        },
      );

      if (response.statusCode == 200 && response.data['data'] != null) {
        final fee = response.data['data']['total'];
        if (fee != null && mounted) {
          setState(() {
            shippingFee = int.tryParse(fee.toString()) ?? 35000;
            _isCalculatingShip = false;
          });
        }
      } else {
        throw Exception('Lỗi tính phí từ backend');
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          shippingFee = 35000;
          _isCalculatingShip = false;
        });
      }
    }
  }

  Future<void> _applyCoupon() async {
    final code = _couponCtrl.text.trim();
    if (code.isEmpty) return;
    setState(() => _isApplyingCoupon = true);
    try {
      final res = await ApiClient().dio.get('/coupons/public');
      final List coupons = res.data['data'] ?? [];
      final coupon = coupons.firstWhere(
        (c) =>
            c['code'].toString().toLowerCase() == code.toLowerCase() &&
            c['is_active'] == true,
        orElse: () => null,
      );
      if (coupon == null) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Mã giảm giá không hợp lệ hoặc đã hết hạn!'),
              backgroundColor: AppColors.error,
            ),
          );
        }
      } else {
        final minOrder =
            num.tryParse(coupon['min_order_value']?.toString() ?? '0') ?? 0;
        if (subtotal < minOrder) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(
                  'Đơn hàng tối thiểu ${FormatUtils.formatPrice(minOrder)} để dùng mã này!',
                ),
                backgroundColor: AppColors.warning,
              ),
            );
          }
        } else {
          int discount = 0;
          if (coupon['type'] == 'percent') {
            discount = (subtotal * num.parse(coupon['value'].toString()) / 100)
                .round();
            final maxDisc =
                num.tryParse(coupon['max_discount_value']?.toString() ?? '0') ??
                0;
            if (maxDisc > 0 && discount > maxDisc) discount = maxDisc.toInt();
          } else if (coupon['type'] == 'fixed') {
            discount = num.parse(coupon['value'].toString()).toInt();
          } else if (coupon['type'] == 'free_ship') {
            discount = shippingFee;
          }
          setState(() {
            appliedCoupon = coupon;
            discountAmount = discount;
          });
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(
                  'Áp dụng mã thành công! Giảm ${FormatUtils.formatPrice(discount)}',
                ),
                backgroundColor: AppColors.success,
              ),
            );
          }
        }
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Lỗi kiểm tra mã!'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isApplyingCoupon = false);
    }
  }

  void _removeCoupon() {
    setState(() {
      appliedCoupon = null;
      discountAmount = 0;
      _couponCtrl.clear();
    });
  }

  Future<void> placeOrder() async {
    if (defaultAddress == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Vui lòng thêm địa chỉ nhận hàng!'),
          backgroundColor: AppColors.warning,
        ),
      );
      return;
    }
    if (cartItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Giỏ hàng trống!'),
          backgroundColor: AppColors.warning,
        ),
      );
      return;
    }
    if (_isPlacingOrder) return;
    setState(() => _isPlacingOrder = true);

    try {
      final pm = ['cod', 'vnpay', 'momo'][selectedPayment];
      final response = await ApiClient().dio.post(
        '/profile/orders',
        data: {
          'address_id': defaultAddress!['address_id'] ?? defaultAddress!['id'],
          'payment_method': pm,
          if (appliedCoupon != null) 'coupon_applied': appliedCoupon!['code'],
        },
      );

      if (mounted) {
        context.read<CartProvider>().fetchCart(silent: true, force: true);
      }

      final resData = response.data;
      final vnpayUrl = resData['vnpay_url'];
      final momoUrl = resData['momo_url'];
      final paymentUrl = vnpayUrl ?? momoUrl;

      final orderData = resData['data'];
      final orderCode = orderData is Map ? orderData['order_code']?.toString() : null;
      final grandTotal = orderData is Map
          ? num.tryParse(orderData['grand_total']?.toString() ?? '')
          : null;

      if (!mounted) return;

      if (paymentUrl != null) {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => PaymentWebviewScreen(
              url: paymentUrl,
              paymentMethod: pm,
              orderCode: orderCode,
              grandTotal: grandTotal,
            ),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('🎉 Đặt hàng thành công!'),
            backgroundColor: AppColors.success,
          ),
        );
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(
            builder: (_) => OrderSuccessScreen(
              orderCode: orderCode,
              grandTotal: grandTotal,
            ),
          ),
          (route) => false,
        );
      }
    } on DioException catch (e) {
      final msg = e.response?.data?['message'] ?? 'Lỗi đặt hàng';
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(msg), backgroundColor: AppColors.error),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Lỗi kết nối máy chủ!'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isPlacingOrder = false);
    }
  }

  Future<void> _onChangeAddress() async {
    final selected = await Navigator.push<dynamic>(
      context,
      MaterialPageRoute(
        builder: (_) => const AddressScreen(isSelecting: true),
      ),
    );
    if (selected != null && mounted) {
      setState(() {
        defaultAddress = selected;
        shippingFee = 35000;
      });
      _calculateShippingFee();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return Scaffold(
        backgroundColor: AppColors.background,
        appBar: AppBar(
          title: const Text('Thanh toán', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18)),
          leading: IconButton(icon: const Icon(Icons.arrow_back_rounded), onPressed: () => context.pop()),
        ),
        body: const Center(
          child: CircularProgressIndicator(color: AppColors.primary),
        ),
      );
    }

    if (errorMessage != null) {
      return Scaffold(
        backgroundColor: AppColors.background,
        appBar: AppBar(
          title: const Text('Thanh toán', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18)),
          leading: IconButton(icon: const Icon(Icons.arrow_back_rounded), onPressed: () => context.pop()),
        ),
        body: AppEmptyState(
          icon: Icons.error_outline_rounded,
          title: 'Lỗi tải trang thanh toán',
          message: errorMessage!,
          buttonText: 'Thử lại',
          onAction: fetchCheckoutData,
        ),
      );
    }

    final grandTotal = (subtotal.toInt() + shippingFee - discountAmount).clamp(
      0,
      double.maxFinite.toInt(),
    );

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text(
          'Thanh Toán Đơn Hàng',
          style: TextStyle(
            fontWeight: FontWeight.w900,
            fontSize: 18,
          ),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
      ),
      body: Stack(
        children: [
          SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 120),
            child: Column(
              children: [
                CheckoutAddressBox(
                  address: defaultAddress,
                  onChangeAddress: _onChangeAddress,
                ),
                const SizedBox(height: 8),
                CheckoutPaymentBox(
                  selectedPayment: selectedPayment,
                  onChanged: (index) =>
                      setState(() => selectedPayment = index),
                ),
                const SizedBox(height: 8),
                CheckoutCouponBox(
                  appliedCoupon: appliedCoupon,
                  discountAmount: discountAmount,
                  isApplying: _isApplyingCoupon,
                  controller: _couponCtrl,
                  onApply: _applyCoupon,
                  onRemove: _removeCoupon,
                ),
                const SizedBox(height: 8),
                CheckoutOrderSummary(
                  cartItems: cartItems,
                  subtotal: subtotal,
                  shippingFee: shippingFee,
                  isCalculatingShip: _isCalculatingShip,
                  discountAmount: discountAmount,
                  appliedCoupon: appliedCoupon,
                ),
              ],
            ),
          ),
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: CheckoutBottomBar(
              grandTotal: grandTotal,
              onPlaceOrder: placeOrder,
              isPlacing: _isPlacingOrder,
            ),
          ),
        ],
      ),
    );
  }
}
