import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../config/app_config.dart';
import 'app_navigator.dart';
import 'auth_service.dart';
import 'storage_service.dart';
import '../screens/login_screen.dart';

String get kBaseUrl => AppConfig.kBaseUrl;

const String kMobileUserAgent = 'Dart/3.0 (dart:io) Flutter OceanShop/1.0';

class ApiClient {
  static final ApiClient _instance = ApiClient._internal();
  factory ApiClient() => _instance;

  late Dio dio;

  ApiClient._internal() {
    dio = Dio(
      BaseOptions(
        baseUrl: kBaseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 15),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'User-Agent': kMobileUserAgent,
        },
      ),
    );

    dio.httpClientAdapter = IOHttpClientAdapter(
      createHttpClient: () {
        final client = HttpClient();
        client.userAgent = kMobileUserAgent;
        return client;
      },
    );

    dio.options.responseDecoder =
        (
          List<int> responseBytes,
          RequestOptions options,
          ResponseBody responseBody,
        ) {
          return utf8.decode(responseBytes, allowMalformed: true);
        };

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          options.headers['User-Agent'] = kMobileUserAgent;

          final token = await StorageService.read('access_token');
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }

          if (kDebugMode) {
            final safeHeaders = Map<String, dynamic>.from(options.headers);
            if (safeHeaders.containsKey('Authorization')) {
              safeHeaders['Authorization'] = 'Bearer ***';
            }
            debugPrint('====== API REQUEST ======');
            debugPrint('URL: ${options.baseUrl}${options.path}');
            debugPrint('Headers: $safeHeaders');
            debugPrint('Body: ${options.data}');
            debugPrint('=========================');
          }

          return handler.next(options);
        },
        onResponse: (response, handler) {
          if (kDebugMode) {
            debugPrint('====== API RESPONSE ======');
            debugPrint('Status: ${response.statusCode}');
            debugPrint('Data: ${response.data}');
            debugPrint('==========================');
          }
          return handler.next(response);
        },
        onError: (DioException e, handler) async {
          if (kDebugMode) {
            debugPrint('======= API ERROR =======');
            debugPrint('Status: ${e.response?.statusCode}');
            debugPrint('Data: ${e.response?.data}');
            debugPrint('=========================');
          }

          if (e.response?.statusCode == 401 && !_isAuthEndpoint(e.requestOptions.path)) {
            await _handleUnauthorized();
          }

          return handler.next(e);
        },
      ),
    );
  }

  static const Set<String> _authPaths = {'/login', '/register'};

  bool _isAuthEndpoint(String path) {
    return _authPaths.any((auth) => path.endsWith(auth));
  }

  bool _handlingUnauthorized = false;

  Future<void> _handleUnauthorized() async {
    // Chống điều hướng lặp khi nhiều request cùng trả 401 một lúc.
    if (_handlingUnauthorized) return;
    _handlingUnauthorized = true;

    await AuthService.logout();

    final navigator = appNavigatorKey.currentState;
    if (navigator != null) {
      navigator.pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => false,
      );
    }

    _handlingUnauthorized = false;
  }
}
