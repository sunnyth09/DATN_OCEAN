import 'dart:convert';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:http/http.dart' as http;

// ⚠️ QUAN TRỌNG: Hàm này PHẢI nằm ngoài class (Top-level function)
// Nhiệm vụ: Lắng nghe thông báo khi app đang chạy ngầm hoặc đã bị tắt.
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  print("Đã nhận thông báo Background/Terminated: ${message.messageId}");
  // OS sẽ tự hiển thị UI thông báo, ta không cần code UI ở đây.
}

class NotificationService {
  // Pattern Singleton để gọi Service ở bất cứ đâu
  static final NotificationService _instance = NotificationService._internal();
  factory NotificationService() => _instance;
  NotificationService._internal();

  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications = FlutterLocalNotificationsPlugin();

  Future<void> initialize() async {
    // 1. Xin quyền người dùng (rất cần thiết trên iOS và Android 13+)
    NotificationSettings settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      print('✅ Đã được cấp quyền hiển thị thông báo!');
      
      // 2. Lấy FCM Token và Gửi lên Laravel
      String? token = await _messaging.getToken();
      print("🔑 FCM Token: $token");
      if (token != null) {
        _sendTokenToLaravel(token);
      }

      // Lắng nghe sự thay đổi Token (ví dụ cài lại app)
      _messaging.onTokenRefresh.listen((newToken) {
        _sendTokenToLaravel(newToken);
      });

      // 3. Đăng ký Handler xử lý khi app chạy ngầm
      FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

      // 4. Khởi tạo Local Notification cho trạng thái Đang mở app (Foreground)
      await _initLocalNotifications();

      // 5. Lắng nghe thông báo khi app ĐANG MỞ (Foreground)
      FirebaseMessaging.onMessage.listen((RemoteMessage message) {
        print("📥 Nhận thông báo Foreground: ${message.notification?.title}");
        _showLocalNotification(message);
      });

      // 6. Xử lý khi người dùng BẤM vào thông báo lúc app chạy ngầm (Background)
      FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
        print("👆 User bấm thông báo (từ Background)!");
        _handleNotificationClick(message.data);
      });

      // 7. Xử lý khi người dùng BẤM vào thông báo lúc app bị tắt (Terminated)
      RemoteMessage? initialMessage = await _messaging.getInitialMessage();
      if (initialMessage != null) {
         print("👆 User bấm thông báo (từ Terminated, mở app lên)!");
        _handleNotificationClick(initialMessage.data);
      }
    }
  }

  // --- CÁC HÀM TIỆN ÍCH BÊN TRONG --- //

  // Khởi tạo thư viện Local Notifications
 // Khởi tạo thư viện Local Notifications
 // Khởi tạo thư viện Local Notifications
  Future<void> _initLocalNotifications() async {
    const AndroidInitializationSettings androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const DarwinInitializationSettings iosSettings = DarwinInitializationSettings();
    
    const InitializationSettings initSettings = InitializationSettings(
      android: androidSettings, 
      iOS: iosSettings,
    );

    // Truyền thẳng initSettings vào, đây là chuẩn mới của thư viện
    await _localNotifications.initialize(
  settings: initSettings,
  onDidReceiveNotificationResponse: (NotificationResponse response) {
    print("👆 User bấm thông báo (từ Foreground)!");
    if (response.payload != null) {
      Map<String, dynamic> data = jsonDecode(response.payload!);
      _handleNotificationClick(data);
    }
  },
);
  }

  // Tự vẽ thông báo khi app đang mở
  Future<void> _showLocalNotification(RemoteMessage message) async {
    RemoteNotification? notification = message.notification;
    AndroidNotification? android = message.notification?.android;

    if (notification != null && android != null) {
      await _localNotifications.show(
        id: notification.hashCode,
        title: notification.title,
        body: notification.body,
        notificationDetails: const NotificationDetails(
          android: AndroidNotificationDetails(
            'high_importance_channel', // id kênh
            'Thông báo quan trọng', // tên kênh
            importance: Importance.max,
            priority: Priority.high,
          ),
          iOS: DarwinNotificationDetails(),
        ),
        payload: jsonEncode(message.data), // Đính kèm data để xử lý khi click
      );
    }
  }

  // Xử lý logic điều hướng khi bấm vào thông báo
  void _handleNotificationClick(Map<String, dynamic> data) {
    print("Dữ liệu đính kèm (Payload): $data");
    // TODO: Viết logic chuyển trang ở đây (VD: Navigator.pushNamed(...))
    // if (data['screen'] == 'order') { ... }
  }

  // Hàm gọi API gửi token lên Laravel
  Future<void> _sendTokenToLaravel(String token) async {
    // TODO: Thay thế bằng API thực tế của bạn và nhớ truyền Bearer Token
    /*
    try {
      final response = await http.post(
        Uri.parse('https://ten-mien-laravel-cua-ban.com/api/device-tokens'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer {USER_TOKEN_CUA_BAN}',
        },
        body: jsonEncode({
          'fcm_token': token,
          'device_type': 'android', // hoặc 'ios'
        }),
      );
      print("Lưu token lên Laravel: ${response.statusCode}");
    } catch (e) {
      print("Lỗi lưu token: $e");
    }
    */
  }
}