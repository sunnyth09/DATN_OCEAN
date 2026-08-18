import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/favorite_provider.dart';
import '../widgets/shimmer_loading.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/product_card.dart';

class FavoriteScreen extends StatefulWidget {
  const FavoriteScreen({super.key});

  @override
  State<FavoriteScreen> createState() => _FavoriteScreenState();
}

class _FavoriteScreenState extends State<FavoriteScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final auth = context.read<AuthProvider>();
      if (auth.isAuthenticated) {
        context.read<FavoriteProvider>().fetchFavorites();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final favProvider = context.watch<FavoriteProvider>();
    final favorites = favProvider.favorites;

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
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/me');
            }
          },
        ),
      ),
      body: _buildBody(auth, favProvider),
    );
  }

  Widget _buildBody(AuthProvider auth, FavoriteProvider favProvider) {
    if (!auth.isAuthenticated) {
      return AppEmptyState(
        icon: Icons.person_outline_rounded,
        title: 'Bạn chưa đăng nhập',
        message: 'Đăng nhập để xem và đồng bộ danh sách sản phẩm yêu thích của bạn.',
        buttonText: 'Đăng nhập ngay',
        onAction: () async {
          await context.push('/login');
          if (mounted && context.read<AuthProvider>().isAuthenticated) {
            context.read<FavoriteProvider>().fetchFavorites();
          }
        },
      );
    }

    if (favProvider.isLoading && favProvider.favorites.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(16.0),
        child: ShimmerLoading(),
      );
    }

    final favorites = favProvider.favorites;

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
      onRefresh: () => favProvider.fetchFavorites(),
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
          );
        },
      ),
    );
  }
}
