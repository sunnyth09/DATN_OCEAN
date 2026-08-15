import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../config/app_theme.dart';
import '../services/api_client.dart';
import '../widgets/shimmer_loading.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/product_card.dart';

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
      fetchFavorites();
    } catch (_) {
      if (mounted) setState(() => favorites = old);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          favorites.isNotEmpty
              ? 'Yêu Thích (${favorites.length})'
              : 'Sản Phẩm Yêu Thích',
          style: const TextStyle(
            fontWeight: FontWeight.w900,
            fontSize: 18,
          ),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
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
      return AppEmptyState(
        icon: Icons.error_outline_rounded,
        title: 'Lỗi tải danh sách yêu thích',
        message: errorMessage!,
        buttonText: 'Thử lại',
        onAction: fetchFavorites,
      );
    }

    if (favorites.isEmpty) {
      return AppEmptyState(
        icon: Icons.favorite_border_rounded,
        title: 'Chưa có sản phẩm yêu thích',
        message: 'Nhấn vào biểu tượng trái tim trên các sản phẩm để lưu lại tại đây.',
        buttonText: 'Khám phá ngay',
        onAction: () => context.go('/shop'),
      );
    }

    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: fetchFavorites,
      child: GridView.builder(
        padding: const EdgeInsets.all(16),
        cacheExtent: 800,
        physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
        addRepaintBoundaries: true,
        addAutomaticKeepAlives: false,
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
          childAspectRatio: 0.65,
        ),
        itemCount: favorites.length,
        itemBuilder: (context, index) {
          final fav = favorites[index];
          final product = fav['product'] ?? fav;
          final map = Map<String, dynamic>.from(product);
          map['is_favorited'] = true;

          return ProductCard(
            product: map,
            onFavoriteChanged: fetchFavorites,
          );
        },
      ),
    );
  }
}
