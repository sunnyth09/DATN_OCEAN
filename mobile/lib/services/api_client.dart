import 'dart:async';
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
        },
      ),
    );

    if (!kIsWeb) {
      dio.httpClientAdapter = IOHttpClientAdapter(
        createHttpClient: () {
          final client = HttpClient();
          client.badCertificateCallback =
              (X509Certificate cert, String host, int port) => true;
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

          final token = StorageService.readSync('access_token') ??
              await StorageService.read('access_token');
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
            debugPrint('Type: ${e.type}');
            debugPrint('Message: ${e.message}');
            debugPrint('Error: ${e.error}');
            debugPrint('Status: ${e.response?.statusCode}');
            debugPrint('Data: ${e.response?.data}');
            debugPrint('=========================');
          }

          final is401 = e.response?.statusCode == 401;
          final isAuthPath = _isAuthEndpoint(e.requestOptions.path);
          // Cờ tránh loop: request đã retry 1 lần mà vẫn 401 → không refresh nữa.
          final alreadyRetried = e.requestOptions.extra['__retried'] == true;

          if (!is401 || isAuthPath || alreadyRetried) {
            return handler.next(e);
          }

          // Thử refresh token ngầm rồi retry request gốc.
          final newToken = await _refreshToken();
          if (newToken == null) {
            await _handleUnauthorized();
            return handler.next(e);
          }

          try {
            final opts = e.requestOptions
              ..headers['Authorization'] = 'Bearer $newToken'
              ..extra['__retried'] = true;
            final clone = await dio.fetch(opts);
            return handler.resolve(clone);
          } catch (_) {
            await _handleUnauthorized();
            return handler.next(e);
          }
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

  // Gộp mọi lời gọi refresh đồng thời vào 1 request duy nhất để nhiều
  // request 401 cùng lúc chỉ refresh token 1 lần.
  Completer<String?>? _refreshCompleter;

  Future<String?> _refreshToken() {
    if (_refreshCompleter != null) return _refreshCompleter!.future;

    final completer = Completer<String?>();
    _refreshCompleter = completer;

    () async {
      try {
        final token = await StorageService.read('access_token');
        if (token == null || token.trim().isEmpty || token == 'null') {
          completer.complete(null);
          return;
        }

        // Dùng Dio RIÊNG (không interceptor) để tránh đệ quy 401.
        // Backend /refresh đọc JWT từ Authorization header và cấp token mới
        // nếu còn trong cửa sổ refresh-TTL.
        final raw = Dio(
          BaseOptions(
            baseUrl: kBaseUrl,
            headers: {
              'Accept': 'application/json',
              'Authorization': 'Bearer $token',
              if (!kIsWeb) 'User-Agent': kMobileUserAgent,
            },
          ),
        );

        final res = await raw.post('/refresh');
        final data = res.data;
        final newToken = data is Map ? data['access_token'] as String? : null;

        if (newToken != null && newToken.trim().isNotEmpty) {
          await StorageService.write('access_token', newToken);
          completer.complete(newToken);
        } else {
          completer.complete(null);
        }
      } catch (_) {
        completer.complete(null);
      } finally {
        _refreshCompleter = null;
      }
    }();

    return completer.future;
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
