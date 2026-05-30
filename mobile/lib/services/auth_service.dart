import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'api_client.dart';

class AuthService {
  static const String keyToken = 'access_token';
  static const String keyUser = 'user_data';

  // ========== LOGIN ==========
  static Future<Map<String, dynamic>> login(String email, String password, {required String turnstileToken}) async {
    try {
      final response = await ApiClient().dio.post(
        '/login',
        data: {
          'email': email,
          'password': password,
          'turnstile_token': turnstileToken,
        },
      );

      final data = response.data;

      if (response.statusCode == 200 && data['status'] == 'success') {
        const storage = FlutterSecureStorage(aOptions: AndroidOptions(encryptedSharedPreferences: true));
        // Save Token
        await storage.write(key: keyToken, value: data['access_token']);
        // Save User info
        await storage.write(key: keyUser, value: jsonEncode(data['user']));
        return {'success': true, 'message': 'Đăng nhập thành công'};
      } else {
        return {'success': false, 'message': data['message'] ?? 'Lỗi đăng nhập. Vui lòng thử lại.'};
      }
    } on DioException catch (e) {
      final data = e.response?.data;
      return {'success': false, 'message': data?['message'] ?? 'Lỗi kết nối hoặc tài khoản không tồn tại.'};
    } catch (e) {
      return {'success': false, 'message': 'Không thể kết nối đến máy chủ: $e'};
    }
  }

  // ========== REGISTER ==========
  static Future<Map<String, dynamic>> register(String name, String email, String password, String passwordConfirm, {required String turnstileToken}) async {
    try {
      final response = await ApiClient().dio.post(
        '/register',
        data: {
          'full_name': name,
          'email': email,
          'password': password,
          'password_confirmation': passwordConfirm,
          'turnstile_token': turnstileToken,
        },
      );

      final data = response.data;

      if (response.statusCode == 200 || response.statusCode == 201) {
        if (data['status'] == 'success') {
          return {'success': true, 'message': 'Đăng ký thành công'};
        } else {
          return {'success': false, 'message': data['message'] ?? 'Lỗi đăng ký.'};
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
      return {'success': false, 'message': data?['message'] ?? 'Lỗi đăng ký. Vui lòng thử lại.'};
    } catch (e) {
      return {'success': false, 'message': 'Không thể kết nối đến máy chủ: $e'};
    }
  }

  // ========== LOGOUT ==========
  static Future<bool> logout() async {
    try {
      const storage = FlutterSecureStorage(aOptions: AndroidOptions(encryptedSharedPreferences: true));
      final token = await storage.read(key: keyToken);
      if (token != null) {
        // ApiClient tự chèn token
        await ApiClient().dio.post('/logout');
      }
      await storage.delete(key: keyToken);
      await storage.delete(key: keyUser);
      return true;
    } catch (e) {
      // Even if API fails, we wipe local storage
      const storage = FlutterSecureStorage(aOptions: AndroidOptions(encryptedSharedPreferences: true));
      await storage.delete(key: keyToken);
      await storage.delete(key: keyUser);
      return true;
    }
  }

  // ========== CHECK STATUS ==========
  static Future<bool> isLoggedIn() async {
    const storage = FlutterSecureStorage(aOptions: AndroidOptions(encryptedSharedPreferences: true));
    final token = await storage.read(key: keyToken);
    return token != null && token.isNotEmpty;
  }
}
