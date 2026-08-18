import 'package:flutter/foundation.dart';
import '../services/api_client.dart';

class CouponProvider extends ChangeNotifier {
  List<dynamic> _userCoupons = [];
  List<dynamic> _publicCoupons = [];
  final Set<int> _savedCouponIds = {};
  bool _isLoadingUser = false;
  bool _isLoadingPublic = false;

  List<dynamic> get userCoupons => _userCoupons;
  List<dynamic> get publicCoupons => _publicCoupons;
  Set<int> get savedCouponIds => _savedCouponIds;
  int get voucherCount => _userCoupons.length;
  bool get isLoadingUser => _isLoadingUser;
  bool get isLoadingPublic => _isLoadingPublic;

  bool isSaved(int couponId) => _savedCouponIds.contains(couponId);

  CouponProvider() {
    fetchPublicCoupons(silent: true);
    fetchUserCoupons(silent: true);
  }

  Future<void> fetchPublicCoupons({bool silent = false}) async {
    if (!silent) {
      _isLoadingPublic = true;
      notifyListeners();
    }
    try {
      final res = await ApiClient().dio.get('/coupons/public');
      final data = res.data;
      if (data is Map && data['status'] == 'success' && data['data'] is List) {
        _publicCoupons = data['data'];
      } else if (data is List) {
        _publicCoupons = data;
      }
    } catch (_) {}
    _isLoadingPublic = false;
    notifyListeners();
  }

  Future<void> fetchUserCoupons({bool silent = false}) async {
    if (!silent) {
      _isLoadingUser = true;
      notifyListeners();
    }
    try {
      final res = await ApiClient().dio.get('/profile/coupons');
      final data = res.data;
      final list = data is List
          ? data
          : (data is Map && data['data'] is List ? data['data'] as List : []);

      _userCoupons = list;
      _savedCouponIds.clear();
      for (final item in list) {
        final id = int.tryParse(
          (item['coupon_id'] ?? item['id'] ?? 0).toString(),
        );
        if (id != null && id > 0) {
          _savedCouponIds.add(id);
        }
      }
    } catch (_) {}
    _isLoadingUser = false;
    notifyListeners();
  }

  Future<bool> claimCoupon(int couponId) async {
    _savedCouponIds.add(couponId);
    notifyListeners();

    try {
      final res = await ApiClient().dio.post(
        '/profile/coupons/save',
        data: {'coupon_id': couponId},
      );
      if (res.statusCode == 200 || res.statusCode == 201) {
        // Đồng bộ danh sách voucher của người dùng ngầm
        await fetchUserCoupons(silent: true);
        return true;
      } else {
        _savedCouponIds.remove(couponId);
        notifyListeners();
        return false;
      }
    } catch (_) {
      _savedCouponIds.remove(couponId);
      notifyListeners();
      return false;
    }
  }
}
