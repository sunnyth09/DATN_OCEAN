import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../services/api_client.dart';

class LoyaltyProvider extends ChangeNotifier {
  int _points = 0;
  String _tier = 'Đồng';
  List<dynamic> _rewards = [];
  bool _isLoading = true;
  bool _isRedeeming = false;

  int get points => _points;
  String get tier => _tier;
  List<dynamic> get rewards => _rewards;
  bool get isLoading => _isLoading;
  bool get isRedeeming => _isRedeeming;

  Future<void> fetchLoyaltyData() async {
    _isLoading = true;
    notifyListeners();

    try {
      final futures = await Future.wait([
        ApiClient().dio.get('/loyalty/profile'),
        ApiClient().dio.get('/loyalty/rewards'),
      ]);

      final profileRes = futures[0];
      final rewardsRes = futures[1];

      if (profileRes.data['status'] == 'success') {
        _points = profileRes.data['data']['points'] ?? 0;
        _tier = profileRes.data['data']['tier'] ?? 'Đồng';
      }

      if (rewardsRes.data['status'] == 'success') {
        _rewards = rewardsRes.data['data'] ?? [];
      }
    } catch (e) {
      debugPrint('Error fetching loyalty data: $e');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> redeemReward(int rewardId) async {
    _isRedeeming = true;
    notifyListeners();

    try {
      final res = await ApiClient().dio.post('/loyalty/redeem', data: {
        'reward_id': rewardId,
      });

      if (res.data['status'] == 'success') {
        // Cập nhật lại data sau khi đổi quà thành công
        await fetchLoyaltyData();
        return true;
      }
      return false;
    } on DioException catch (e) {
      debugPrint('Error redeeming reward: ${e.response?.data}');
      return false;
    } catch (e) {
      debugPrint('Error redeeming reward: $e');
      return false;
    } finally {
      _isRedeeming = false;
      notifyListeners();
    }
  }
}
