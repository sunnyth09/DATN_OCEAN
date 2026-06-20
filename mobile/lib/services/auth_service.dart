import 'dart:convert';

import 'package:dio/dio.dart';

import 'api_client.dart';
import 'storage_service.dart';

class AuthService {
  static const String keyToken = 'access_token';
  static const String keyUser = 'user_data';

  static Future<Map<String, dynamic>> login(
    String email,
    String password,
  ) async {
    try {
      final response = await ApiClient().dio.post(
        '/login',
        data: {'email': email, 'password': password},
      );

      final data = response.data;

      if (response.statusCode == 200 && data['status'] == 'success') {
        await StorageService.write(keyToken, data['access_token']);
        await StorageService.write(keyUser, jsonEncode(data['user']));
        return {'success': true, 'message': 'Đăng nhập thành công'};
      } else {
        return {
          'success': false,
          'message': data['message'] ?? 'Lỗi đăng nhập. Vui lòng thử lại.',
        };
      }
    } on DioException catch (e) {
      final data = e.response?.data;
      return {
        'success': false,
        'message':
            data?['message'] ?? 'Lỗi kết nối hoặc tài khoản không tồn tại.',
      };
    } catch (e) {
      return {'success': false, 'message': 'Không thể kết nối đến máy chủ: $e'};
    }
  }

  static Future<Map<String, dynamic>> register(
    String name,
    String email,
    String password,
    String passwordConfirm,
  ) async {
    try {
      final response = await ApiClient().dio.post(
        '/register',
        data: {
          'full_name': name,
          'email': email,
          'password': password,
          'password_confirmation': passwordConfirm,
        },
      );

      final data = response.data;

      if (response.statusCode == 200 || response.statusCode == 201) {
        if (data['status'] == 'success') {
          return {'success': true, 'message': 'Đăng ký thành công'};
        } else {
          return {
            'success': false,
            'message': data['message'] ?? 'Lỗi đăng ký.',
          };
        }
      }
      return {'success': false, 'message': 'Có lỗi xảy ra'};
    } on DioException catch (e) {
      final data = e.response?.data;
      if (data != null && data['errors'] != null) {
        final errors = data['errors'] as Map<String, dynamic>;
        final firstError = errors.values.first[0];
        return {'success': false, 'message': firstError};
      }
      return {
        'success': false,
        'message': data?['message'] ?? 'Lỗi đăng ký. Vui lòng thử lại.',
      };
    } catch (e) {
      return {'success': false, 'message': 'Không thể kết nối đến máy chủ: $e'};
    }
  }

  static Future<bool> logout() async {
    try {
      final token = await StorageService.read(keyToken);
      if (token != null) {
        await ApiClient().dio.post('/logout');
      }
      await _clearLocalSession();
      return true;
    } catch (_) {
      await _clearLocalSession();
      return true;
    }
  }

  static Future<bool> isLoggedIn() async {
    final token = await StorageService.read(keyToken);
    if (token == null || token.isEmpty) return false;
    if (_isTokenExpired(token)) {
      await _clearLocalSession();
      return false;
    }
    return true;
  }

  static Future<void> _clearLocalSession() async {
    await StorageService.delete(keyToken);
    await StorageService.delete(keyUser);
  }

  static bool _isTokenExpired(String token) {
    try {
      final parts = token.split('.');
      if (parts.length != 3) return false;

      final payload = utf8.decode(
        base64Url.decode(base64Url.normalize(parts[1])),
      );
      final decoded = jsonDecode(payload);
      if (decoded is! Map<String, dynamic>) return false;

      final exp = decoded['exp'];
      final expSeconds = exp is int ? exp : int.tryParse(exp.toString());
      if (expSeconds == null) return false;

      final expiresAt = DateTime.fromMillisecondsSinceEpoch(expSeconds * 1000);
      return DateTime.now().isAfter(expiresAt);
    } catch (_) {
      return false;
    }
  }
}
