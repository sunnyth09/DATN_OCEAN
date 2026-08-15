import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../config/app_config.dart';
import '../screens/google_oauth_webview_screen.dart';
import 'api_client.dart';
import 'storage_service.dart';

class AuthService {
  static const String keyToken = 'access_token';
  static const String keyUser = 'user_data';

  static Future<Map<String, dynamic>> exchangeGoogleCode(String code) async {
    try {
      final response = await ApiClient().dio.post(
        '/auth/google/callback',
        data: {'code': code},
      );

      final data = response.data;
      if (response.statusCode == 200 && data['status'] == 'success') {
        await StorageService.write(keyToken, data['access_token']);
        await StorageService.write(keyUser, jsonEncode(data['user']));
        return {
          'success': true,
          'message': 'Đăng nhập Google thành công',
          'user': data['user'],
        };
      } else {
        return {
          'success': false,
          'message': data['message'] ?? 'Đăng nhập Google thất bại!',
        };
      }
    } on DioException catch (e) {
      final data = e.response?.data;
      return {
        'success': false,
        'message': data?['message'] ?? 'Lỗi kết nối khi xác thực Google.',
      };
    } catch (e) {
      return {'success': false, 'message': 'Lỗi xác thực Google: $e'};
    }
  }

  static Future<Map<String, dynamic>> loginWithGoogle({BuildContext? context}) async {
    try {
      final GoogleSignIn googleSignIn = GoogleSignIn(
        serverClientId: AppConfig.kGoogleClientId,
        scopes: ['email', 'profile'],
      );

      try {
        await googleSignIn.signOut();
      } catch (_) {}

      final GoogleSignInAccount? account = await googleSignIn.signIn();
      if (account == null) {
        return {'success': false, 'message': 'Đã hủy đăng nhập Google.'};
      }

      final GoogleSignInAuthentication auth = await account.authentication;

      final response = await ApiClient().dio.post(
        '/auth/google/mobile',
        data: {
          'id_token': auth.idToken,
          'google_id': account.id,
          'email': account.email,
          'name': account.displayName,
          'avatar_url': account.photoUrl,
        },
      );

      final data = response.data;
      if (response.statusCode == 200 && data['status'] == 'success') {
        await StorageService.write(keyToken, data['access_token']);
        await StorageService.write(keyUser, jsonEncode(data['user']));
        return {
          'success': true,
          'message': 'Đăng nhập Google thành công',
          'user': data['user'],
        };
      } else {
        return {
          'success': false,
          'message': data['message'] ?? 'Đăng nhập Google thất bại!',
        };
      }
    } on DioException catch (e) {
      final data = e.response?.data;
      return {
        'success': false,
        'message': data?['message'] ?? 'Lỗi kết nối máy chủ khi đăng nhập Google.',
      };
    } catch (e) {
      // If native Google Sign-In throws PlatformException (e.g. SHA-1 mismatch / ApiException: 10)
      // seamlessly fallback to the OAuth WebView!
      if (context != null && context.mounted) {
        final code = await Navigator.of(context).push<String>(
          MaterialPageRoute(
            builder: (context) => const GoogleOAuthWebViewScreen(),
          ),
        );

        if (code != null && code.isNotEmpty) {
          return await exchangeGoogleCode(code);
        } else {
          return {'success': false, 'message': 'Đã hủy đăng nhập Google.'};
        }
      }

      return {
        'success': false,
        'message': 'Lỗi đăng nhập Google: $e',
      };
    }
  }

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
