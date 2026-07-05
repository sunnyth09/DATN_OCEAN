import 'dart:async';
import 'package:flutter/material.dart';
import '../widgets/network_image_widget.dart';
import '../services/api_client.dart';
import '../config/app_config.dart';
import '../config/app_theme.dart';
import 'product_detail_screen.dart';

class FlashSaleScreen extends StatefulWidget {
  const FlashSaleScreen({super.key});

  @override
  State<FlashSaleScreen> createState() => _FlashSaleScreenState();
}

class _FlashSaleScreenState extends State<FlashSaleScreen> {
  List<dynamic> flashSales = [];
  bool isLoading = true;
  String? errorMessage;
  Timer? _countdownTimer;

  @override
  void initState() {
    super.initState();
    fetchFlashSales();
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    super.dispose();
  }

  Future<void> fetchFlashSales() async {
    try {
      setState(() {
        isLoading = true;
        errorMessage = null;
      });
      final res = await ApiClient().dio.get('/flash-sale');
      if (res.statusCode == 200) {
        final data = res.data;
        List<dynamic> list = [];
        if (data is List) {
          list = data;
        } else if (data['data'] is List) {
          list = data['data'];
        } else if (data['data'] is Map && data['data']['data'] is List) {
          list = data['data']['data'];
        }
        if (mounted)
          setState(() {
            flashSales = list;
            isLoading = false;
          });
      }
    } catch (e) {
      if (mounted)
        setState(() {
          errorMessage = 'Không thể tải Flash Sale';
          isLoading = false;
        });
    }
  }

  String _formatPrice(dynamic price) {
    try {
      final num p = num.parse(price.toString());
      final formatted = p
          .toStringAsFixed(0)
          .replaceAllMapped(
            RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
            (m) => '${m[1]}.',
          );
      return '${formatted}đ';
    } catch (_) {
      return price.toString();
    }
  }

  Map<String, int> _getCountdown(String? endDate) {
    if (endDate == null) return {'h': 0, 'm': 0, 's': 0};
    final end = DateTime.tryParse(endDate);
    if (end == null) return {'h': 0, 'm': 0, 's': 0};
    final diff = end.difference(DateTime.now());
    if (diff.isNegative) return {'h': 0, 'm': 0, 's': 0};
    return {
      'h': diff.inHours,
      'm': diff.inMinutes % 60,
      's': diff.inSeconds % 60,
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      body: CustomScrollView(
        slivers: [
          // Hero Header
          SliverToBoxAdapter(child: _buildHero()),
          // Content
          if (isLoading)
            const SliverFillRemaining(
              child: Center(
                child: CircularProgressIndicator(color: AppColors.primary),
              ),
            )
          else if (errorMessage != null)
            SliverFillRemaining(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(
                      Icons.error_outline,
                      size: 48,
                      color: Colors.grey,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      errorMessage!,
                      style: const TextStyle(color: Colors.grey),
                    ),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: fetchFlashSales,
                      child: const Text('Thử lại'),
                    ),
                  ],
                ),
              ),
            )
          else if (flashSales.isEmpty)
            const SliverFillRemaining(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.flash_off, size: 64, color: Color(0xFFCBD5E1)),
                    SizedBox(height: 12),
                    Text(
                      'Chưa có Flash Sale nào',
                      style: TextStyle(fontSize: 16, color: Color(0xFF64748B)),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Quay lại sau để săn deal nhé!',
                      style: TextStyle(fontSize: 13, color: Color(0xFF94A3B8)),
                    ),
                  ],
                ),
              ),
            )
          else
            SliverPadding(
              padding: const EdgeInsets.all(16),
              sliver: SliverList(
                delegate: SliverChildBuilderDelegate(
                  (context, index) => _buildFlashSaleCard(flashSales[index]),
                  childCount: flashSales.length,
                ),
              ),
            ),
          // Rules section
          SliverToBoxAdapter(child: _buildRules()),
          const SliverToBoxAdapter(child: SizedBox(height: 32)),
        ],
      ),
    );
  }

  Widget _buildHero() {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFFE63B6F), Color(0xFFFF6B9D)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: Column(
          children: [
            // App bar row
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              child: Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.arrow_back, color: Colors.white),
                    onPressed: () => Navigator.pop(context),
                  ),
                  const Spacer(),
                  const Text(
                    'Flash Sale',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const Spacer(),
                  const SizedBox(width: 48),
                ],
              ),
            ),
            // Hero content
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 8, 24, 32),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        width: 5,
                        height: 5,
                        decoration: BoxDecoration(
                          color: Colors.white54,
                          borderRadius: BorderRadius.circular(3),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Text(
                        'CHƯƠNG TRÌNH ĐẶC BIỆT',
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.75),
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 2,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Container(
                        width: 5,
                        height: 5,
                        decoration: BoxDecoration(
                          color: Colors.white54,
                          borderRadius: BorderRadius.circular(3),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.flash_on, color: Colors.white, size: 36),
                      const SizedBox(width: 8),
                      const Text(
                        'Flash Sale',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 32,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.2),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text(
                      'Giá Sốc',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Số lượng cực kỳ có hạn — Cơ hội săn deal không thể bỏ lỡ!',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.75),
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFlashSaleCard(Map<String, dynamic> sale) {
    final items = (sale['items'] as List<dynamic>?) ?? [];
    final endDate = sale['end_date']?.toString();
    final countdown = _getCountdown(endDate);
    final isActive =
        (countdown['h']! > 0 || countdown['m']! > 0 || countdown['s']! > 0);
    final saleName = sale['name'] ?? 'Flash Sale';

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFF1F3F5)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header
          Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: Color(0xFFF1F3F5))),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        saleName,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        isActive ? 'Đang diễn ra' : 'Đã kết thúc',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: isActive ? AppColors.success : Colors.grey,
                        ),
                      ),
                    ],
                  ),
                ),
                // Countdown
                if (isActive)
                  Row(
                    children: [
                      _countdownBox(
                        '${countdown['h']!.toString().padLeft(2, '0')}',
                      ),
                      const Text(
                        ' : ',
                        style: TextStyle(
                          fontWeight: FontWeight.w900,
                          color: AppColors.primary,
                        ),
                      ),
                      _countdownBox(
                        '${countdown['m']!.toString().padLeft(2, '0')}',
                      ),
                      const Text(
                        ' : ',
                        style: TextStyle(
                          fontWeight: FontWeight.w900,
                          color: AppColors.primary,
                        ),
                      ),
                      _countdownBox(
                        '${countdown['s']!.toString().padLeft(2, '0')}',
                      ),
                    ],
                  ),
              ],
            ),
          ),
          // Items
          if (items.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(
                child: Text(
                  'Không có sản phẩm',
                  style: TextStyle(color: Colors.grey),
                ),
              ),
            )
          else
            ...items.map((item) => _buildFlashSaleItem(item, isActive)),
        ],
      ),
    );
  }

  Widget _countdownBox(String value) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: AppColors.primary,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        value,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w800,
          fontSize: 14,
        ),
      ),
    );
  }

  Widget _buildFlashSaleItem(Map<String, dynamic> item, bool isActive) {
    final product = item['product'] as Map<String, dynamic>? ?? {};
    final variant = item['variant'] as Map<String, dynamic>? ?? {};
    final name = product['name'] ?? 'Sản phẩm';
    final salePrice = item['sale_price'] ?? variant['price'] ?? 0;
    final originalPrice =
        item['original_price'] ?? variant['price'] ?? salePrice;
    final stock = item['stock'] ?? 0;
    final sold = item['sold'] ?? 0;
    final total = stock + sold;
    final progress = total > 0 ? sold / total : 0.0;
    final imageUrl = AppConfig.productImageUrl(product);

    final discountPercent = originalPrice > 0
        ? (((originalPrice - salePrice) / originalPrice) * 100).round()
        : 0;

    return InkWell(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => ProductDetailScreen(product: product),
          ),
        );
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: [
            // Image
            NetworkImageWidget(
              imageUrl: imageUrl,
              width: 80,
              height: 80,
              fit: BoxFit.cover,
              borderRadius: BorderRadius.circular(12),
              errorWidget: Container(
                width: 80,
                height: 80,
                color: const Color(0xFFF1F5F9),
                child: const Icon(Icons.image, color: Colors.grey),
              ),
            ),
            const SizedBox(width: 14),
            // Info
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Text(
                        _formatPrice(salePrice),
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w900,
                          color: AppColors.primary,
                        ),
                      ),
                      if (discountPercent > 0) ...[
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.primary.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            '-$discountPercent%',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: AppColors.primary,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  if (discountPercent > 0)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(
                        _formatPrice(originalPrice),
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF94A3B8),
                          decoration: TextDecoration.lineThrough,
                        ),
                      ),
                    ),
                  const SizedBox(height: 8),
                  // Progress bar
                  Stack(
                    children: [
                      Container(
                        height: 6,
                        decoration: BoxDecoration(
                          color: const Color(0xFFE5E7EB),
                          borderRadius: BorderRadius.circular(3),
                        ),
                      ),
                      FractionallySizedBox(
                        widthFactor: progress.clamp(0.0, 1.0),
                        child: Container(
                          height: 6,
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFFE63B6F), Color(0xFFFF8FAB)],
                            ),
                            borderRadius: BorderRadius.circular(3),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    stock <= 0 ? 'Đã bán hết' : 'Đã bán $sold/$total',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: stock <= 0 ? Colors.red : const Color(0xFF64748B),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRules() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFF1F3F5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.assignment_outlined,
                size: 16,
                color: AppColors.primary,
              ),
              const SizedBox(width: 8),
              Text(
                'ĐIỀU KIỆN THAM GIA',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: AppColors.primary,
                  letterSpacing: 0.8,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _ruleItem(Icons.lock_outline, 'Phải ', 'đăng nhập', ' để mua hàng'),
          _ruleItem(
            Icons.shopping_cart_outlined,
            'Tối đa ',
            '1 sản phẩm / khách hàng',
            '',
          ),
          _ruleItem(
            Icons.local_shipping_outlined,
            'Flash Sale được ',
            'miễn phí vận chuyển',
            '',
          ),
          _ruleItem(
            Icons.flash_on,
            'Đơn hàng xử lý theo thứ tự — đặt sớm ưu tiên trước',
            '',
            '',
          ),
          _ruleItem(Icons.close, 'Không áp dụng thêm mã giảm giá khác', '', ''),
        ],
      ),
    );
  }

  Widget _ruleItem(IconData icon, String text, String bold, String suffix) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 16, color: AppColors.primary),
          const SizedBox(width: 10),
          Expanded(
            child: RichText(
              text: TextSpan(
                style: const TextStyle(
                  fontSize: 13,
                  color: Color(0xFF636E72),
                  height: 1.5,
                ),
                children: [
                  TextSpan(text: text),
                  if (bold.isNotEmpty)
                    TextSpan(
                      text: bold,
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                  if (suffix.isNotEmpty) TextSpan(text: suffix),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
