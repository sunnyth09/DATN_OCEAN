import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../services/api_client.dart';
import 'address_screen.dart';
import '../config/app_config.dart';
import '../utils/format_utils.dart';
import 'package:provider/provider.dart';
import '../providers/cart_provider.dart';
import 'order_success_screen.dart';
import 'checkout/widgets/checkout_address_box.dart';
import 'checkout/widgets/checkout_payment_box.dart';
import 'checkout/widgets/checkout_coupon_box.dart';
import 'checkout/widgets/checkout_order_summary.dart';
import 'checkout/widgets/checkout_bottom_bar.dart';
import 'payment_webview_screen.dart';

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
  int shippingFee = 0; // Phí ship mặc định (fallback)
  bool _isCalculatingShip = false;
  bool isLoading = true;
  String? errorMessage;

  // Coupon
  Map<String, dynamic>? appliedCoupon;
  int discountAmount = 0;
  final _couponCtrl = TextEditingController();
  bool _isApplyingCoupon = false;

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
      // Chạy song song để nhanh hơn
      final results = await Future.wait([
        ApiClient().dio.get('/profile/addresses'),
        ApiClient().dio.get('/cart'),
      ]);

      final addressRes = results[0];
      final cartRes = results[1];

      // Parse địa chỉ
      final addrList = addressRes.data['data'] as List? ?? [];
      if (addrList.isNotEmpty) {
        defaultAddress = addrList.firstWhere(
          (a) => a['is_default'] == 1 || a['is_default'] == true,
          orElse: () => addrList.first,
        );
      }

      // Parse giỏ hàng — lấy TẤT CẢ items (không filter selected vì backend có thể không trả field này)
      final cData = cartRes.data['data'];
      if (cData != null) {
        final allItems = (cData['items'] as List?) ?? [];
        // Ưu tiên filter selected, nếu không có thì lấy hết
        final selectedItems = allItems
            .where((item) => item['selected'] == 1 || item['selected'] == true)
            .toList();
        cartItems = selectedItems.isNotEmpty ? selectedItems : allItems;

        // Tính subtotal từ items nếu API không trả total_price đúng
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

      // Tính phí GHN sau khi có địa chỉ
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
    } catch (e) {
      if (mounted) {
        setState(() {
          errorMessage = 'Lỗi kết nối máy chủ. Vui lòng thử lại.';
          isLoading = false;
        });
      }
    }
  }

  /// Tính phí vận chuyển thực tế từ GHN API
  Future<void> _calculateShippingFee() async {
    if (defaultAddress == null) return;
    final districtCode = defaultAddress!['district_code']?.toString() ?? '';
    final wardCode = defaultAddress!['ward_code']?.toString() ?? '';
    if (districtCode.isEmpty || wardCode.isEmpty) return;

    if (!mounted) return;
    setState(() => _isCalculatingShip = true);

    try {
      final ghnToken = AppConfig.ghnToken;
      final ghnShopId = AppConfig.ghnShopId;
      if (ghnToken.isEmpty || ghnShopId.isEmpty) {
        if (mounted) setState(() => _isCalculatingShip = false);
        return;
      }

      final ghnDio = Dio(
        BaseOptions(
          baseUrl: 'https://online-gateway.ghn.vn/shiip/public-api',
          headers: {
            'Token': ghnToken,
            'ShopId': int.tryParse(ghnShopId) ?? ghnShopId,
            'Content-Type': 'application/json',
          },
          connectTimeout: const Duration(seconds: 8),
          receiveTimeout: const Duration(seconds: 8),
        ),
      );

      final response = await ghnDio.get(
        '/v2/shipping-order/fee',
        queryParameters: {
          'service_type_id': 2,
          'to_district_id': int.tryParse(districtCode) ?? districtCode,
          'to_ward_code': wardCode,
          'weight': 3000,
          'shop_id': int.tryParse(ghnShopId) ?? ghnShopId,
        },
      );

      final fee = response.data?['data']?['total'];
      if (fee != null && mounted) {
        setState(() {
          shippingFee = int.tryParse(fee.toString()) ?? 35000;
          _isCalculatingShip = false;
        });
      } else {
        if (mounted) {
          setState(() {
             shippingFee = 35000;
             _isCalculatingShip = false;
          });
        }
      }
    } on DioException catch (e) {
      debugPrint('GHN DioException: ${e.response?.data}');
      if (mounted) {
        setState(() {
           shippingFee = 35000;
           _isCalculatingShip = false;
        });
      }
    } catch (e) {
      debugPrint('GHN Exception: $e');
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
              backgroundColor: Colors.red,
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
                backgroundColor: Colors.orange,
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
                backgroundColor: Colors.green,
              ),
            );
          }
        }
      }
    } on DioException catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Lỗi kiểm tra mã!'),
            backgroundColor: Colors.red,
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
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }
    if (cartItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Giỏ hàng trống!'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(child: CircularProgressIndicator()),
    );

    try {
      final pm = ['cod', 'vnpay', 'momo'][selectedPayment];
      final response = await ApiClient().dio.post(
        '/profile/orders',
        data: {
          'address_id': defaultAddress!['address_id'] ?? defaultAddress!['id'],
          'payment_method': pm,
          'shipping_fee': shippingFee,
          if (appliedCoupon != null) 'coupon_code': appliedCoupon!['code'],
        },
      );

      if (mounted) context.pop(); // hide loading

      if (mounted) {
        context.read<CartProvider>().fetchCart(silent: true);
      }

      final resData = response.data;
      final vnpayUrl = resData['vnpay_url'];
      final momoUrl = resData['momo_url'];
      final paymentUrl = vnpayUrl ?? momoUrl;

      if (paymentUrl != null && mounted) {
        // Mở WebView thanh toán
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => PaymentWebviewScreen(
              url: paymentUrl,
              paymentMethod: pm,
            ),
          ),
        );
      } else if (mounted) {
        // Thanh toán COD hoặc ví -> đi tới trang thành công
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('🎉 Đặt hàng thành công!'),
            backgroundColor: Colors.green,
          ),
        );
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (_) => const OrderSuccessScreen()),
          (route) => false,
        );
      }
    } on DioException catch (e) {
      if (mounted) context.pop();
      final msg = e.response?.data?['message'] ?? 'Lỗi đặt hàng';
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(msg), backgroundColor: Colors.red),
        );
      }
    } catch (_) {
      if (mounted) {
        context.pop();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Lỗi kết nối máy chủ!'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  /// Mở màn chọn địa chỉ, cập nhật rồi tính lại phí ship theo địa chỉ mới.
  Future<void> _onChangeAddress() async {
    final selected = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => const AddressScreen(isSelecting: true),
      ),
    );
    if (selected != null && mounted) {
      setState(() {
        defaultAddress = selected;
        shippingFee = 35000; // reset về default rồi tính lại
      });
      _calculateShippingFee();
    }
  }

  PreferredSizeWidget _buildAppBar() {
    return AppBar(
      title: const Text(
        'Thanh toán',
        style: TextStyle(
          fontWeight: FontWeight.w800,
          color: Color(0xFF0F172A),
          fontSize: 18,
        ),
      ),
      backgroundColor: Colors.white,
      elevation: 0,
      centerTitle: true,
      leading: IconButton(
        icon: const Icon(Icons.arrow_back, color: Color(0xFFE63B6F)),
        onPressed: () => context.pop(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: _buildAppBar(),
        body: const Center(
          child: CircularProgressIndicator(color: Color(0xFFE63B6F)),
        ),
      );
    }

    if (errorMessage != null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: _buildAppBar(),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.error_outline, size: 64, color: Colors.grey),
                const SizedBox(height: 16),
                Text(
                  errorMessage!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Color(0xFF64748B)),
                ),
                const SizedBox(height: 24),
                ElevatedButton(
                  onPressed: fetchCheckoutData,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFE63B6F),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text(
                    'Thử lại',
                    style: TextStyle(color: Colors.white),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final grandTotal = (subtotal.toInt() + shippingFee - discountAmount).clamp(
      0,
      double.maxFinite.toInt(),
    );

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: _buildAppBar(),
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
            ),
          ),
        ],
      ),
    );
  }
}
