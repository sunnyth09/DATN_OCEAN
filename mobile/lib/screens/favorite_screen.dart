import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import '../services/api_client.dart';
import 'product_detail_screen.dart';
import '../config/app_config.dart';
import '../widgets/shimmer_loading.dart';

class FavoriteScreen extends StatefulWidget {
  const FavoriteScreen({super.key});

  @override
  State<FavoriteScreen> createState() => _FavoriteScreenState();
}

class _FavoriteScreenState extends State<FavoriteScreen> {
  List<dynamic> favorites = [];
  bool isLoading = true;
  String? errorMessage;

  @override
  void initState() {
    super.initState();
    fetchFavorites();
  }

  Future<void> fetchFavorites() async {
    if (!mounted) return;
    setState(() {
      isLoading = true;
      errorMessage = null;
    });
    try {
      final res = await ApiClient().dio.get('/profile/favorites');
      if (mounted) {
        setState(() {
          favorites = res.data['data'] ?? [];
          isLoading = false;
        });
      }
    } on DioException catch (e) {
      if (mounted) {
        setState(() {
          errorMessage =
              e.response?.data?['message'] ?? 'Không thể tải danh sách';
          isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          errorMessage = 'Lỗi kết nối';
          isLoading = false;
        });
      }
    }
  }

  Future<void> toggleFavorite(int productId) async {
    // Optimistic remove
    final old = List<dynamic>.from(favorites);
    setState(
      () => favorites.removeWhere(
        (f) => (f['product']?['product_id'] ?? f['product_id']) == productId,
      ),
    );
    try {
      await ApiClient().dio.post(
        '/profile/favorites/toggle',
        data: {'product_id': productId},
      );
      // Refresh to get accurate state
      fetchFavorites();
    } catch (_) {
      // Revert on error
      if (mounted) setState(() => favorites = old);
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
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: Text(
          'Yêu thích (${favorites.length})',
          style: const TextStyle(
            color: Color(0xFF0F172A),
            fontWeight: FontWeight.bold,
            fontSize: 18,
          ),
        ),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFFE63B6F)),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (isLoading) {
      return const Padding(
        padding: EdgeInsets.all(16.0),
        child: ShimmerLoading(),
      );
    }

    if (errorMessage != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 60, color: Colors.grey),
            const SizedBox(height: 12),
            Text(
              errorMessage!,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.grey),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: fetchFavorites,
              child: const Text('Thử lại'),
            ),
          ],
        ),
      );
    }

    if (favorites.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.favorite_border, size: 80, color: Colors.grey.shade300),
            const SizedBox(height: 16),
            const Text(
              'Chưa có sản phẩm yêu thích',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: Color(0xFF64748B),
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Nhấn ♡ trên sản phẩm để thêm vào danh sách',
              style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: fetchFavorites,
      child: GridView.builder(
        padding: const EdgeInsets.all(16),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
          childAspectRatio: 0.68,
        ),
        itemCount: favorites.length,
        itemBuilder: (context, index) {
          final fav = favorites[index];
          final product = fav['product'] ?? fav;
          final name = product['name'] ?? 'Không tên';
          final productId = product['product_id'] ?? fav['product_id'];

          dynamic price = 0;
          if (product['lowest_price_variant'] is Map) {
            price = product['lowest_price_variant']['price'] ?? 0;
          } else if (product['min_price'] != null) {
            price = product['min_price'];
          }

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
                    color: Colors.black.withValues(alpha: 0.04),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Stack(
                    children: [
                      ClipRRect(
                        borderRadius: const BorderRadius.vertical(
                          top: Radius.circular(16),
                        ),
                        child: imageUrl.isNotEmpty
                            ? CachedNetworkImage(
                                imageUrl: imageUrl,
                                height: 155,
                                width: double.infinity,
                                fit: BoxFit.cover,
                                placeholder: (_, _) => Container(
                                  height: 155,
                                  color: const Color(0xFFF1F5F9),
                                ),
                                errorWidget: (_, _, _) => Container(
                                  height: 155,
                                  color: Colors.grey.shade100,
                                  child: const Icon(
                                    Icons.image_not_supported,
                                    color: Colors.grey,
                                  ),
                                ),
                              )
                            : Container(
                                height: 155,
                                color: Colors.grey.shade100,
                              ),
                      ),
                      Positioned(
                        top: 8,
                        right: 8,
                        child: GestureDetector(
                          onTap: () => toggleFavorite(productId),
                          child: Container(
                            padding: const EdgeInsets.all(6),
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.9),
                              shape: BoxShape.circle,
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.1),
                                  blurRadius: 4,
                                ),
                              ],
                            ),
                            child: const Icon(
                              Icons.favorite,
                              size: 18,
                              color: Colors.red,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(10, 10, 10, 6),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontWeight: FontWeight.w600,
                            fontSize: 13,
                            height: 1.3,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          _formatPrice(price),
                          style: const TextStyle(
                            fontWeight: FontWeight.w900,
                            color: Color(0xFFE63B6F),
                            fontSize: 14,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
