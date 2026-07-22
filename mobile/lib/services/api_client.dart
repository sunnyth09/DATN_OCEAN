import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../config/app_config.dart';
import '../router/app_router.dart';
import 'auth_service.dart';
import 'storage_service.dart';

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
          if (!kIsWeb) 'User-Agent': kMobileUserAgent,
          'ngrok-skip-browser-warning': '69420',
        },
      ),
    );

    if (!kIsWeb) {
      dio.httpClientAdapter = IOHttpClientAdapter(
        createHttpClient: () {
          final client = HttpClient();
          client.userAgent = kMobileUserAgent;
          return client;
        },
      );
    }

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
          if (!kIsWeb) {
            options.headers['User-Agent'] = kMobileUserAgent;
          }

          final token = await StorageService.read('access_token');
          if (token != null && token.trim().isNotEmpty && token != 'null') {
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
    if (_handlingUnauthorized) return;
    _handlingUnauthorized = true;

    final token = await StorageService.read('access_token');
    final hasToken = token != null && token.trim().isNotEmpty && token != 'null';

    await AuthService.logout();

    if (hasToken) {
      final context = rootNavigatorKey.currentContext;
      if (context != null && context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Phiên đăng nhập hết hạn, vui lòng đăng nhập lại')),
        );
        context.go('/login');
      }
    }

    _handlingUnauthorized = false;
  }

  // Wrapper cho các method HTTP phổ biến
  Future<Response> get(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await dio.get(
        path,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw _handleDioError(e);
    } catch (e) {
      throw Exception('Lỗi không xác định: $e');
    }
  }

  Future<Response> post(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await dio.post(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw _handleDioError(e);
    } catch (e) {
      throw Exception('Lỗi không xác định: $e');
    }
  }

  Future<Response> put(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await dio.put(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw _handleDioError(e);
    } catch (e) {
      throw Exception('Lỗi không xác định: $e');
    }
  }

  Future<Response> delete(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await dio.delete(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw _handleDioError(e);
    } catch (e) {
      throw Exception('Lỗi không xác định: $e');
    }
  }

  Exception _handleDioError(DioException e) {
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.sendTimeout) {
      return Exception('Kết nối máy chủ hết hạn. Vui lòng thử lại.');
    } else if (e.type == DioExceptionType.connectionError ||
        e.error is SocketException) {
      return Exception('Không có kết nối mạng. Vui lòng kiểm tra Wifi/4G.');
    } else if (e.response != null) {
      final data = e.response?.data;
      if (data is Map && data['message'] != null) {
        return Exception(data['message']);
      }
      return Exception('Lỗi máy chủ (${e.response?.statusCode})');
    }
    return Exception('Lỗi kết nối mạng.');
  }
}
