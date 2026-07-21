import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/navigation_provider.dart';
import '../services/api_client.dart';
import '../config/app_config.dart';
import '../utils/format_utils.dart';
import '../widgets/network_image_widget.dart';
import 'product_detail_screen.dart';

class OrderSuccessScreen extends StatefulWidget {
  const OrderSuccessScreen({super.key});

  @override
  State<OrderSuccessScreen> createState() => _OrderSuccessScreenState();
}

class _OrderSuccessScreenState extends State<OrderSuccessScreen> {
  List<dynamic> relatedProducts = [];
  bool isLoadingProducts = true;

  @override
  void initState() {
    super.initState();
    fetchRelatedProducts();
  }

  Future<void> fetchRelatedProducts() async {
    try {
      final response = await ApiClient().dio.get(
        '/products',
        queryParameters: {'page': 1},
      );
      if (response.statusCode == 200) {
        final data = response.data;
        List<dynamic> fetched = [];

        if (data is List) {
          fetched = data;
        } else if (data['data'] is List) {
          fetched = data['data'];
        }

        if (mounted) {
          setState(() {
            // Lấy ngẫu nhiên vài sản phẩm làm gợi ý (tối đa 6)
            fetched.shuffle();
            relatedProducts = fetched.take(6).toList();
            isLoadingProducts = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          isLoadingProducts = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        automaticallyImplyLeading: false, // Ẩn nút back
        backgroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
        title: const Text(
          'Hoàn tất',
          style: TextStyle(
            fontWeight: FontWeight.w800,
            color: Color(0xFF0F172A),
            fontSize: 18,
          ),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              const SizedBox(height: 40),
              // Success Icon
              Container(
                width: 100,
                height: 100,
                decoration: BoxDecoration(
                  color: const Color(0xFFE63B6F).withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_circle_rounded,
                  color: Color(0xFFE63B6F),
                  size: 64,
                ),
              ),
              const SizedBox(height: 24),
              // Title
              const Text(
                'Mua hàng thành công!',
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF2D3436),
                ),
              ),
              const SizedBox(height: 12),
              // Subtitle
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 32),
                child: Text(
                  'Cảm ơn bạn đã mua sắm tại Quyền Sport. Đơn hàng của bạn đang được xử lý và sẽ sớm được giao.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 14,
                    color: Color(0xFF636E72),
                    height: 1.5,
                  ),
                ),
              ),
              const SizedBox(height: 32),
              // Actions
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                child: Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () {
                          context.read<NavigationProvider>().setTab(4);
                          context.go('/home');
                        },
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          side: const BorderSide(color: Color(0xFFE63B6F)),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        child: const Text(
                          'Xem đơn hàng',
                          style: TextStyle(
                            color: Color(0xFFE63B6F),
                            fontWeight: FontWeight.bold,
                            fontSize: 15,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () {
                          context.read<NavigationProvider>().setTab(0);
                          context.go('/home');
                        },
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          backgroundColor: const Color(0xFFE63B6F),
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        child: const Text(
                          'Tiếp tục mua hàng',
                          style: TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                            fontSize: 15,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 48),
              
              // Related Products Section
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.only(
                    topLeft: Radius.circular(24),
                    topRight: Radius.circular(24),
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Có thể bạn sẽ thích',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF2D3436),
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (isLoadingProducts)
                      const Center(
                        child: Padding(
                          padding: EdgeInsets.all(32),
                          child: CircularProgressIndicator(
                              color: Color(0xFFE63B6F)),
                        ),
                      )
                    else if (relatedProducts.isEmpty)
                      const Center(
                        child: Padding(
                          padding: EdgeInsets.all(16),
                          child: Text(
                            'Chưa có sản phẩm liên quan',
                            style: TextStyle(color: Color(0xFF636E72)),
                          ),
                        ),
                      )
                    else
                      GridView.builder(
                        physics: const NeverScrollableScrollPhysics(),
                        shrinkWrap: true,
                        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          crossAxisSpacing: 16,
                          mainAxisSpacing: 16,
                          childAspectRatio: 0.65,
                        ),
                        itemCount: relatedProducts.length,
                        itemBuilder: (context, index) {
                          return _buildProductCard(relatedProducts[index]);
                        },
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildProductCard(Map<String, dynamic> product) {
    final name = product['name'] ?? 'Không tên';
    final dynamic rawPrice =
        product['min_price'] ??
        (product['lowest_price_variant'] != null
            ? product['lowest_price_variant']['price']
            : 0);

    final imageUrl = AppConfig.productImageUrl(product);

    return GestureDetector(
      onTap: () {
        context.push('/product-detail', extra: product);
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.03),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
          border: Border.all(color: const Color(0xFFF1F3F5)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Hero(
              tag: product['id'] ?? product['slug'] ?? 'product_image_${product.hashCode}',
              child: NetworkImageWidget(
                imageUrl: imageUrl,
                height: 160,
                width: double.infinity,
                fit: BoxFit.cover,
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(16),
                  topRight: Radius.circular(16),
                ),
                placeholder: Container(
                  height: 160,
                  color: const Color(0xFFF1F5F9),
                  child: const Center(
                    child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFE63B6F)),
                  ),
                ),
                errorWidget: Container(
                  height: 160,
                  color: const Color(0xFFF1F5F9),
                  child: const Icon(Icons.image_not_supported, color: Color(0xFFCBD5E1)),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF0F172A),
                      height: 1.3,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    FormatUtils.formatPrice(num.tryParse(rawPrice.toString()) ?? 0),
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFFE63B6F),
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
}
