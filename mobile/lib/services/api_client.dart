import 'dart:convert';
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter/foundation.dart';
import '../config/app_config.dart';

const String kBaseUrl = AppConfig.kBaseUrl;

/// User-Agent chứa 'dart' để backend nhận diện mobile app và bypass CAPTCHA
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
          // Tầng 1: BaseOptions headers
          'User-Agent': kMobileUserAgent,
        },
      ),
    );

    // Tầng 2: HttpClient adapter level (dart:io)
    dio.httpClientAdapter = IOHttpClientAdapter(
      createHttpClient: () {
        final client = HttpClient();
        client.userAgent = kMobileUserAgent;
        return client;
      },
    );

    // Bắt buộc decode UTF-8 cho mọi response
    dio.options.responseDecoder = (List<int> responseBytes, RequestOptions options, ResponseBody responseBody) {
      return utf8.decode(responseBytes, allowMalformed: true);
    };

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          // Tầng 3: Force User-Agent trong mỗi request qua interceptor
          options.headers['User-Agent'] = kMobileUserAgent;

          // Auto-inject Bearer token
          const storage = FlutterSecureStorage(aOptions: AndroidOptions(encryptedSharedPreferences: true));
          final token = await storage.read(key: 'access_token');
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }

          // DEBUG LOG
          debugPrint('══════ API REQUEST ══════');
          debugPrint('URL: ${options.baseUrl}${options.path}');
          debugPrint('User-Agent: ${options.headers['User-Agent']}');
          debugPrint('All Headers: ${options.headers}');
          debugPrint('Body: ${options.data}');
          debugPrint('═════════════════════════');

          return handler.next(options);
        },
        onResponse: (response, handler) {
          debugPrint('══════ API RESPONSE ══════');
          debugPrint('Status: ${response.statusCode}');
          debugPrint('Data: ${response.data}');
          debugPrint('══════════════════════════');
          return handler.next(response);
        },
        onError: (DioException e, handler) async {
          debugPrint('══════ API ERROR ══════');
          debugPrint('Status: ${e.response?.statusCode}');
          debugPrint('Data: ${e.response?.data}');
          debugPrint('═══════════════════════');
          if (e.response?.statusCode == 401) {
            debugPrint('API Error 401: Unauthorized');
          }
          return handler.next(e);
        },
      ),
    );
  }
}
