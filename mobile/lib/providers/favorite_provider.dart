import 'package:flutter/foundation.dart';
import '../services/api_client.dart';

class FavoriteProvider extends ChangeNotifier {
  final Set<int> _favoriteIds = {};
  List<dynamic> _favorites = [];
  bool _isLoading = false;

  Set<int> get favoriteIds => _favoriteIds;
  List<dynamic> get favorites => _favorites;
  int get itemCount => _favoriteIds.length;
  bool get isLoading => _isLoading;

  bool isFavorite(int productId) => _favoriteIds.contains(productId);

  FavoriteProvider() {
    fetchFavorites(silent: true);
  }

  Future<void> fetchFavorites({bool silent = false}) async {
    if (!silent) {
      _isLoading = true;
      notifyListeners();
    }

    try {
      final res = await ApiClient().dio.get('/profile/favorites');
      final data = res.data;
      final list = data is List
          ? data
          : (data is Map && data['data'] is List ? data['data'] as List : []);

      _favorites = list;
      _favoriteIds.clear();
      for (final item in list) {
        final id = int.tryParse(
          (item['product']?['product_id'] ?? item['product_id'] ?? item['id'] ?? 0).toString(),
        );
        if (id != null && id > 0) {
          _favoriteIds.add(id);
        }
      }
    } catch (_) {}

    _isLoading = false;
    notifyListeners();
  }

  Future<bool> toggleFavorite(int productId) async {
    final currentlyFav = _favoriteIds.contains(productId);
    if (currentlyFav) {
      _favoriteIds.remove(productId);
      _favorites.removeWhere((item) {
        final id = int.tryParse(
          (item['product']?['product_id'] ?? item['product_id'] ?? item['id'] ?? 0).toString(),
        );
        return id == productId;
      });
    } else {
      _favoriteIds.add(productId);
    }
    notifyListeners();

    try {
      final res = await ApiClient().dio.post(
        '/profile/favorites/toggle',
        data: {'product_id': productId},
      );
      if (res.statusCode == 200 || res.statusCode == 201) {
        // Đồng bộ lại danh sách chi tiết ngầm
        fetchFavorites(silent: true);
        return true;
      } else {
        // Revert on non-success
        if (currentlyFav) {
          _favoriteIds.add(productId);
        } else {
          _favoriteIds.remove(productId);
        }
        notifyListeners();
        return false;
      }
    } catch (_) {
      // Revert on error
      if (currentlyFav) {
        _favoriteIds.add(productId);
      } else {
        _favoriteIds.remove(productId);
      }
      notifyListeners();
      return false;
    }
  }
}
