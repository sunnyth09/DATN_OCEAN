import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:go_router/go_router.dart';

import 'api_client.dart';
import '../router/app_router.dart';
import 'auth_service.dart';

// QUAN TRỌNG: Hàm này PHẢI nằm ngoài class (Top-level function)
// Lắng nghe thông báo khi app đang chạy ngầm hoặc đã bị tắt.
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  // OS sẽ tự hiển thị UI thông báo, ta không cần code UI ở đây.
}

class NotificationService {
  static final NotificationService _instance =
      NotificationService._internal();
  factory NotificationService() => _instance;
  NotificationService._internal();

  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  static const AndroidNotificationChannel _channel = AndroidNotificationChannel(
    'high_importance_channel',
    'Thông báo quan trọng',
    importance: Importance.max,
  );

  bool _initialized = false;

  Future<void> initialize() async {
    if (_initialized) return;
    _initialized = true;

    // Xin quyền (iOS + Android 13+). Dù bị từ chối vẫn đăng ký handler
    // để không cần khởi động lại app khi người dùng bật quyền sau này.
    await _messaging.requestPermission(alert: true, badge: true, sound: true);

    await _initLocalNotifications();

    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

    // App đang mở (foreground) → tự vẽ local notification.
    FirebaseMessaging.onMessage.listen(_showLocalNotification);

    // Bấm thông báo khi app chạy ngầm.
    FirebaseMessaging.onMessageOpenedApp.listen(
      (message) => _handleNotificationClick(message.data),
    );

    // Bấm thông báo lúc app bị tắt hẳn (mở app lên từ thông báo).
    final initialMessage = await _messaging.getInitialMessage();
    if (initialMessage != null) {
      _handleNotificationClick(initialMessage.data);
    }

    _messaging.onTokenRefresh.listen(syncTokenToServer);

    await syncTokenToServer();
  }

  /// Lấy FCM token và gửi lên backend. Gọi lại sau khi đăng nhập thành công.
  Future<void> syncTokenToServer([String? refreshedToken]) async {
    // Chỉ gửi khi đã đăng nhập, nếu không request sẽ bị 401.
    if (!await AuthService.isLoggedIn()) return;

    try {
      final token = refreshedToken ?? await _messaging.getToken();
      if (token == null || token.isEmpty) return;

      await ApiClient().dio.post(
        '/device-tokens',
        data: {
          'fcm_token': token,
          'device_type': _deviceType,
        },
      );
    } on DioException catch (e) {
      debugPrint('Lỗi gửi FCM token: ${e.response?.statusCode}');
    } catch (e) {
      debugPrint('Lỗi gửi FCM token: $e');
    }
  }

  String get _deviceType {
    if (kIsWeb) return 'web';
    if (defaultTargetPlatform == TargetPlatform.iOS) return 'ios';
    return 'android';
  }

  Future<void> _initLocalNotifications() async {
    const androidSettings =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosSettings = DarwinInitializationSettings();
    const initSettings = InitializationSettings(
      android: androidSettings,
      iOS: iosSettings,
    );

    await _localNotifications.initialize(
      settings: initSettings,
      onDidReceiveNotificationResponse: (response) {
        final payload = response.payload;
        if (payload == null || payload.isEmpty) return;
        try {
          final data = jsonDecode(payload);
          if (data is Map) {
            _handleNotificationClick(Map<String, dynamic>.from(data));
          }
        } catch (error, stackTrace) {
          debugPrint(
            '[NotificationService] click handler error: $error\n$stackTrace',
          );
        }
      },
    );

    await _localNotifications
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(_channel);
  }

  Future<void> _showLocalNotification(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;

    await _localNotifications.show(
      id: notification.hashCode,
      title: notification.title,
      body: notification.body,
      notificationDetails: NotificationDetails(
        android: AndroidNotificationDetails(
          _channel.id,
          _channel.name,
          importance: Importance.max,
          priority: Priority.high,
        ),
        iOS: const DarwinNotificationDetails(),
      ),
      payload: jsonEncode(message.data),
    );
  }

  void _handleNotificationClick(Map<String, dynamic> data) {
    final context = rootNavigatorKey.currentContext;
    if (context == null) return;

    // Hiện tại mọi thông báo đều mở màn hình danh sách thông báo.
    // Mở rộng theo data['type'] / data['screen'] khi có thêm màn hình đích.
    context.push('/notification');
  }
}

