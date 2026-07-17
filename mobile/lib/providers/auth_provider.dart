import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../models/user_model.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../services/storage_service.dart';

/// Quản lý state phiên đăng nhập + thông tin người dùng tập trung.
class AuthProvider extends ChangeNotifier {
  final _dio = ApiClient().dio;

  User? _user;
  bool _isAuthenticated = false;
  bool _isLoading = false;

  User? get user => _user;
  bool get isAuthenticated => _isAuthenticated;
  bool get isLoading => _isLoading;

  /// Khôi phục phiên lúc khởi động app (đọc token đã lưu + cache user).
  Future<void> bootstrap() async {
    _isAuthenticated = await AuthService.isLoggedIn();
    if (_isAuthenticated) {
      _user = await _readCachedUser();
    }
    notifyListeners();
  }

  Future<Map<String, dynamic>> login(String email, String password) async {
    _isLoading = true;
    notifyListeners();

    final result = await AuthService.login(email, password);

    if (result['success'] == true) {
      _isAuthenticated = true;
      _user = await _readCachedUser();
    }

    _isLoading = false;
    notifyListeners();
    return result;
  }

  /// Tải lại thông tin người dùng từ server. Trả về false nếu phiên hết hạn.
  Future<bool> loadProfile() async {
    if (!await AuthService.isLoggedIn()) {
      _setLoggedOut();
      return false;
    }

    try {
      final response = await _dio.get('/me');
      final data = response.data['user'];
      if (data is Map) {
        _user = User.fromJson(Map<String, dynamic>.from(data));
        _isAuthenticated = true;
        notifyListeners();
      }
      return true;
    } on DioException catch (e) {
      if (e.response?.statusCode == 401) {
        await logout();
        return false;
      }
      rethrow;
    }
  }

  Future<void> logout() async {
    await AuthService.logout();
    _setLoggedOut();
  }

  void _setLoggedOut() {
    _user = null;
    _isAuthenticated = false;
    notifyListeners();
  }

  Future<User?> _readCachedUser() async {
    final cached = await StorageService.read(AuthService.keyUser);
    if (cached == null || cached.isEmpty) return null;
    try {
      final decoded = jsonDecode(cached);
      if (decoded is Map) {
        return User.fromJson(Map<String, dynamic>.from(decoded));
      }
    } catch (_) {}
    return null;
  }
}
