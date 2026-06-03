import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import '../services/api_client.dart';
import '../config/app_theme.dart';
import 'main_wrapper.dart';
import 'address_screen.dart';
import '../config/app_config.dart';

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
  int shippingFee = 35000; // Phí ship mặc định (fallback)
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
    setState(() { isLoading = true; errorMessage = null; });

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
        final selectedItems = allItems.where((item) => item['selected'] == 1 || item['selected'] == true).toList();
        cartItems = selectedItems.isNotEmpty ? selectedItems : allItems;
        
        // Tính subtotal từ items nếu API không trả total_price đúng
        final apiTotal = cData['total_price'];
        if (apiTotal != null && num.tryParse(apiTotal.toString()) != null) {
          subtotal = num.parse(apiTotal.toString());
        } else {
          subtotal = cartItems.fold<num>(0, (sum, item) {
            final lineTotal = num.tryParse(item['line_total']?.toString() ?? '0') ?? 0;
            return sum + lineTotal;
          });
        }
      }

      // Tính phí GHN sau khi có địa chỉ
      await _calculateShippingFee();

      if (mounted) setState(() => isLoading = false);
    } on DioException catch (e) {
      if (mounted) setState(() {
        errorMessage = e.response?.data?['message'] ?? 'Không thể tải dữ liệu thanh toán';
        isLoading = false;
      });
    } catch (e) {
      if (mounted) setState(() {
        errorMessage = 'Lỗi kết nối máy chủ. Vui lòng thử lại.';
        isLoading = false;
      });
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
      final ghnToken = dotenv.env['TOKEN_GHN'] ?? '';
      if (ghnToken.isEmpty) return;

      final ghnDio = Dio(BaseOptions(
        baseUrl: 'https://online-gateway.ghn.vn/shiip/public-api',
        headers: {
          'Token': ghnToken,
          'ShopId': '5881673',
          'Content-Type': 'application/json',
        },
        connectTimeout: const Duration(seconds: 8),
        receiveTimeout: const Duration(seconds: 8),
      ));

      final response = await ghnDio.get(
        '/v2/shipping-order/fee',
        queryParameters: {
          'service_type_id': 2,
          'to_district_id': int.tryParse(districtCode) ?? districtCode,
          'to_ward_code': wardCode,
          'height': 15,
          'length': 15,
          'weight': 500,
          'width': 15,
          'insurance_value': 0,
        },
      );

      final fee = response.data?['data']?['total'];
      if (fee != null && mounted) {
        setState(() {
          shippingFee = int.tryParse(fee.toString()) ?? 35000;
          _isCalculatingShip = false;
        });
      }
    } catch (_) {
      // Giữ giá mặc định 35.000đ nếu lỗi
      if (mounted) setState(() => _isCalculatingShip = false);
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
        (c) => c['code'].toString().toLowerCase() == code.toLowerCase() && c['is_active'] == true,
        orElse: () => null,
      );
      if (coupon == null) {
        if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Mã giảm giá không hợp lệ hoặc đã hết hạn!'), backgroundColor: Colors.red));
      } else {
        final minOrder = num.tryParse(coupon['min_order_value']?.toString() ?? '0') ?? 0;
        if (subtotal < minOrder) {
          if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Đơn hàng tối thiểu ${_formatPrice(minOrder)} để dùng mã này!'), backgroundColor: Colors.orange));
        } else {
          int discount = 0;
          if (coupon['type'] == 'percent') {
            discount = (subtotal * num.parse(coupon['value'].toString()) / 100).round();
            final maxDisc = num.tryParse(coupon['max_discount_value']?.toString() ?? '0') ?? 0;
            if (maxDisc > 0 && discount > maxDisc) discount = maxDisc.toInt();
          } else if (coupon['type'] == 'fixed') {
            discount = num.parse(coupon['value'].toString()).toInt();
          } else if (coupon['type'] == 'free_ship') {
            discount = shippingFee;
          }
          setState(() { appliedCoupon = coupon; discountAmount = discount; });
          if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Áp dụng mã thành công! Giảm ${_formatPrice(discount)}'), backgroundColor: Colors.green));
        }
      }
    } on DioException catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Lỗi kiểm tra mã!'), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _isApplyingCoupon = false);
    }
  }

  void _removeCoupon() {
    setState(() { appliedCoupon = null; discountAmount = 0; _couponCtrl.clear(); });
  }

  Future<void> placeOrder() async {
    if (defaultAddress == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Vui lòng thêm địa chỉ nhận hàng!'), backgroundColor: Colors.orange));
      return;
    }
    if (cartItems.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Giỏ hàng trống!'), backgroundColor: Colors.orange));
      return;
    }

    showDialog(context: context, barrierDismissible: false, builder: (_) => const Center(child: CircularProgressIndicator()));

    try {
      final pm = ['cod', 'vnpay', 'momo'][selectedPayment];
      final response = await ApiClient().dio.post('/profile/orders', data: {
        'address_id': defaultAddress!['address_id'] ?? defaultAddress!['id'],
        'payment_method': pm,
        'shipping_fee': shippingFee,
        if (appliedCoupon != null) 'coupon_code': appliedCoupon!['code'],
      });

      if (mounted) Navigator.pop(context); // hide loading

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('🎉 Đặt hàng thành công!'), backgroundColor: Colors.green));
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (_) => const MainWrapper(initialIndex: 3)),
          (route) => false,
        );
      }
    } on DioException catch (e) {
      if (mounted) Navigator.pop(context);
      final msg = e.response?.data?['message'] ?? 'Lỗi đặt hàng';
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: Colors.red));
    } catch (_) {
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Lỗi kết nối máy chủ!'), backgroundColor: Colors.red));
      }
    }
  }

  String _formatPrice(dynamic price) {
    try {
      final num p = num.parse(price.toString());
      return '${p.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]}.')} đ';
    } catch (_) {
      return price.toString();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: AppBar(
          title: const Text('Thanh toán', style: TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF0F172A), fontSize: 18)),
          backgroundColor: Colors.white, elevation: 0, centerTitle: true,
          leading: IconButton(icon: const Icon(Icons.arrow_back, color: Color(0xFFE63B6F)), onPressed: () => Navigator.pop(context)),
        ),
        body: const Center(child: CircularProgressIndicator(color: Color(0xFFE63B6F))),
      );
    }

    if (errorMessage != null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        appBar: AppBar(title: const Text('Thanh toán'), backgroundColor: Colors.white, elevation: 0, centerTitle: true),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.error_outline, size: 64, color: Colors.grey),
                const SizedBox(height: 16),
                Text(errorMessage!, textAlign: TextAlign.center, style: const TextStyle(color: Color(0xFF64748B))),
                const SizedBox(height: 24),
                ElevatedButton(
                  onPressed: fetchCheckoutData,
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFE63B6F), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                  child: const Text('Thử lại', style: TextStyle(color: Colors.white)),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final grandTotal = (subtotal.toInt() + shippingFee - discountAmount).clamp(0, double.maxFinite.toInt());

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Thanh toán', style: TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF0F172A), fontSize: 18)),
        backgroundColor: Colors.white, elevation: 0, centerTitle: true,
        leading: IconButton(icon: const Icon(Icons.arrow_back, color: Color(0xFFE63B6F)), onPressed: () => Navigator.pop(context)),
      ),
      body: Stack(
        children: [
          SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 120),
            child: Column(
              children: [
                _buildAddressBox(),
                const SizedBox(height: 8),
                _buildPaymentBox(),
                const SizedBox(height: 8),
                _buildCouponBox(),
                const SizedBox(height: 8),
                _buildOrderSummary(),
              ],
            ),
          ),
          // Sticky Bottom Bar
          Positioned(
            bottom: 0, left: 0, right: 0,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 12, offset: const Offset(0, -4))],
              ),
              child: SafeArea(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Tổng cộng', style: TextStyle(fontSize: 12, color: Color(0xFF475569))),
                        Text(_formatPrice(grandTotal), style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFFE63B6F))),
                      ],
                    ),
                    ElevatedButton(
                      onPressed: placeOrder,
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(horizontal: 36, vertical: 14),
                        backgroundColor: const Color(0xFFE63B6F),
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
                      ),
                      child: const Text('Đặt hàng', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAddressBox() {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white, borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 8)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Row(children: [
                Icon(Icons.location_on_outlined, color: Color(0xFFE63B6F), size: 20),
                SizedBox(width: 8),
                Text('Địa chỉ nhận hàng', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A), fontSize: 15)),
              ]),
              GestureDetector(
                onTap: () async {
                  final selected = await Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const AddressScreen(isSelecting: true)),
                  );
                  if (selected != null && mounted) {
                    setState(() {
                      defaultAddress = selected;
                      shippingFee = 35000; // reset về default rồi tính lại
                    });
                    _calculateShippingFee(); // tính phí GHN theo địa chỉ mới
                  }
                },
                child: Text(
                  defaultAddress != null ? 'Thay đổi' : 'Thêm mới',
                  style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFFE63B6F), fontSize: 13),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (defaultAddress != null) ...[
            Row(children: [
              const Icon(Icons.person_outline, size: 14, color: Color(0xFF94A3B8)),
              const SizedBox(width: 6),
              Text(defaultAddress!['recipient_name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(width: 12),
              const Icon(Icons.phone_outlined, size: 14, color: Color(0xFF94A3B8)),
              const SizedBox(width: 6),
              Text(defaultAddress!['phone'] ?? '', style: const TextStyle(fontSize: 13, color: Color(0xFF475569))),
            ]),
            const SizedBox(height: 6),
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Icon(Icons.home_outlined, size: 14, color: Color(0xFF94A3B8)),
              const SizedBox(width: 6),
              Expanded(child: Text(
                '${defaultAddress!['address_line']}, ${defaultAddress!['ward']}, ${defaultAddress!['district']}, ${defaultAddress!['province']}',
                style: const TextStyle(fontSize: 13, color: Color(0xFF475569), height: 1.5),
              )),
            ]),
          ] else ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(8)),
              child: const Row(children: [
                Icon(Icons.warning_amber_rounded, color: Colors.red, size: 16),
                SizedBox(width: 8),
                Text('Bạn chưa có địa chỉ giao hàng. Vui lòng thêm.', style: TextStyle(color: Colors.red, fontSize: 13)),
              ]),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildPaymentBox() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white, borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 8)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(children: [
            Icon(Icons.payment_outlined, color: Color(0xFFE63B6F), size: 20),
            SizedBox(width: 8),
            Text('Phương thức thanh toán', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A), fontSize: 15)),
          ]),
          const SizedBox(height: 12),
          _buildPaymentOption(0, Icons.delivery_dining_outlined, 'Thanh toán khi nhận hàng (COD)', 'Trả tiền mặt cho người giao hàng'),
          _buildPaymentOption(1, Icons.credit_card, 'VNPay', 'Chuyển khoản / Thẻ ngân hàng ATM'),
          _buildPaymentOption(2, Icons.account_balance_wallet_outlined, 'MoMo', 'Ví điện tử MoMo'),
        ],
      ),
    );
  }

  Widget _buildPaymentOption(int index, IconData icon, String title, String subtitle) {
    final isSelected = selectedPayment == index;
    return GestureDetector(
      onTap: () => setState(() => selectedPayment = index),
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFF0F9FF) : Colors.grey.shade50,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: isSelected ? const Color(0xFFE63B6F) : const Color(0xFFE2E8F0), width: isSelected ? 1.5 : 1),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(color: isSelected ? const Color(0xFFE63B6F).withOpacity(0.1) : const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(8)),
              child: Icon(icon, color: isSelected ? const Color(0xFFE63B6F) : const Color(0xFF475569), size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(title, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: isSelected ? const Color(0xFFE63B6F) : const Color(0xFF0F172A))),
                const SizedBox(height: 2),
                Text(subtitle, style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
              ]),
            ),
            Icon(isSelected ? Icons.radio_button_checked : Icons.radio_button_off, color: isSelected ? const Color(0xFFE63B6F) : const Color(0xFFCBD5E1), size: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildCouponBox() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white, borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 8)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(children: [
            Icon(Icons.local_offer_outlined, color: Color(0xFFE63B6F), size: 20),
            SizedBox(width: 8),
            Text('Mã giảm giá', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A), fontSize: 15)),
          ]),
          const SizedBox(height: 12),
          if (appliedCoupon == null)
            Row(children: [
              Expanded(
                child: TextField(
                  controller: _couponCtrl,
                  textCapitalization: TextCapitalization.characters,
                  decoration: InputDecoration(
                    hintText: 'Nhập mã giảm giá...',
                    hintStyle: const TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
                    filled: true, fillColor: const Color(0xFFF8FAFC),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFE2E8F0))),
                    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFE63B6F))),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    isDense: true,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              ElevatedButton(
                onPressed: _isApplyingCoupon ? null : _applyCoupon,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFE63B6F), foregroundColor: Colors.white,
                  elevation: 0, padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: _isApplyingCoupon
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Áp dụng', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
              ),
            ])
          else
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(color: Colors.green.withOpacity(0.08), borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.green.withOpacity(0.3))),
              child: Row(children: [
                const Icon(Icons.check_circle, color: Colors.green, size: 20),
                const SizedBox(width: 8),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(appliedCoupon!['code'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.green, fontSize: 14)),
                  Text('Giảm ${_formatPrice(discountAmount)}', style: const TextStyle(fontSize: 12, color: Colors.green)),
                ])),
                GestureDetector(onTap: _removeCoupon, child: const Icon(Icons.close, color: Colors.grey, size: 20)),
              ]),
            ),
        ],
      ),
    );
  }

  Widget _buildOrderSummary() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white, borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 8)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            const Icon(Icons.receipt_outlined, color: Color(0xFFE63B6F), size: 20),
            const SizedBox(width: 8),
            Text('Sản phẩm (${cartItems.length})', style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F172A), fontSize: 15)),
          ]),
          const SizedBox(height: 12),
          ...cartItems.map((item) {
            final variantData = item['variant'];
            final productData = item['product'];
            final name = variantData?['variant_name'] ?? productData?['name'] ?? 'Sản phẩm';
            final qty = item['quantity']?.toString() ?? '1';
            final lineTotal = _formatPrice(item['line_total'] ?? 0);

            String imageUrl = '';
            String rawImage = '';
            if (productData?['main_image'] is Map) {
              rawImage = productData?['main_image']['image_url']?.toString() ?? '';
            } else {
              rawImage = productData?['thumbnail_url']?.toString() ?? '';
            }
            imageUrl = AppConfig.imageUrl(rawImage);

            return Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 48, height: 48,
                    decoration: BoxDecoration(color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(10)),
                    child: imageUrl.isNotEmpty
                      ? ClipRRect(borderRadius: BorderRadius.circular(10), child: Image.network(imageUrl, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const Icon(Icons.image, color: Colors.grey, size: 22)))
                      : const Icon(Icons.image, color: Colors.grey, size: 22),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13), maxLines: 2, overflow: TextOverflow.ellipsis),
                      const SizedBox(height: 4),
                      Text('x$qty', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                    ]),
                  ),
                  Text(lineTotal, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFFE63B6F))),
                ],
              ),
            );
          }),
          const Divider(color: Color(0xFFE2E8F0)),
          const SizedBox(height: 8),
          _buildPriceRow('Tạm tính', _formatPrice(subtotal)),
          const SizedBox(height: 6),
          // Phí ship: hiện trạng thái loading nếu đang tính từ GHN
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Phí vận chuyển', style: TextStyle(fontSize: 13, color: Color(0xFF475569))),
              _isCalculatingShip
                ? const Row(mainAxisSize: MainAxisSize.min, children: [
                    SizedBox(width: 12, height: 12, child: CircularProgressIndicator(strokeWidth: 1.5, color: Color(0xFFE63B6F))),
                    SizedBox(width: 6),
                    Text('Đang tính...', style: TextStyle(fontSize: 13, color: Color(0xFF94A3B8), fontStyle: FontStyle.italic)),
                  ])
                : Text(_formatPrice(shippingFee), style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
            ],
          ),
          if (discountAmount > 0) ...[
            const SizedBox(height: 6),
            _buildPriceRow('Giảm giá (${appliedCoupon?['code'] ?? ''})', '- ${_formatPrice(discountAmount)}', valueColor: Colors.green),
          ],
          const Divider(color: Color(0xFFE2E8F0)),
          const SizedBox(height: 6),
          _buildPriceRow(
            'Tổng cộng',
            _formatPrice((subtotal.toInt() + shippingFee - discountAmount).clamp(0, double.maxFinite.toInt())),
            labelBold: true,
            valueColor: const Color(0xFFE63B6F),
            valueFontSize: 16,
          ),
        ],
      ),
    );
  }

  Widget _buildPriceRow(String label, String value, {Color? valueColor, bool labelBold = false, double valueFontSize = 13}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: TextStyle(fontSize: 13, color: const Color(0xFF475569), fontWeight: labelBold ? FontWeight.bold : FontWeight.normal)),
        Text(value, style: TextStyle(fontSize: valueFontSize, fontWeight: FontWeight.bold, color: valueColor ?? const Color(0xFF0F172A))),
      ],
    );
  }
}
