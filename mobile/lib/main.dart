import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:firebase_core/firebase_core.dart';
import 'config/app_theme.dart';
import 'firebase_options.dart';
import 'providers/auth_provider.dart';
import 'providers/cart_provider.dart';
import 'providers/home_provider.dart';
import 'providers/category_provider.dart';
import 'providers/product_detail_provider.dart';
import 'providers/loyalty_provider.dart';
import 'providers/chat_provider.dart';
import 'providers/favorite_provider.dart';
import 'providers/coupon_provider.dart';
import 'services/notification_service.dart';
import 'widgets/offline_banner.dart';
import 'router/app_router.dart';

/// HttpOverrides giúp toàn bộ Image.network, CachedNetworkImage, SvgPicture kết nối trực tiếp
/// tới IP máy chủ với TLS SNI chuẩn, loại bỏ 100% lỗi nghẽn DNS IPv6 trên Android Emulator.
class AppHttpOverrides extends HttpOverrides {
  @override
  HttpClient createHttpClient(SecurityContext? context) {
    final client = super.createHttpClient(context);
    client.badCertificateCallback = (cert, host, port) => true;
    client.connectionTimeout = const Duration(seconds: 15);
    client.idleTimeout = const Duration(seconds: 30);
    client.maxConnectionsPerHost = 20;

    client.connectionFactory = (Uri uri, String? proxyHost, int? proxyPort) {
      final targetIp = (uri.host == 'apiocean.bcbdev.id.vn') ? '116.118.6.160' : uri.host;

      final Future<Socket> futureSocket = Socket.connect(
        targetIp,
        uri.port,
        timeout: const Duration(seconds: 15),
      ).then<Socket>((rawSocket) {
        if (uri.scheme == 'https') {
          return SecureSocket.secure(
            rawSocket,
            host: uri.host, // Thiết lập TLS SNI 'apiocean.bcbdev.id.vn'
            onBadCertificate: (cert) => true,
          );
        }
        return rawSocket;
      });

      return Future.value(ConnectionTask.fromSocket(futureSocket, () {}));
    };

    return client;
  }
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  if (!kIsWeb) {
    HttpOverrides.global = AppHttpOverrides();
  }

  await initializeDateFormatting('vi_VN', null);
  await initializeDateFormatting('vi', null);

  // Khởi tạo Firebase
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );

  final authProvider = AuthProvider();

  // Chạy các tác vụ khởi động song song để mở app tức thì
  await Future.wait([
    authProvider.bootstrap(),
    NotificationService().initialize(),
    SharedPreferences.getInstance(),
  ]);

  final prefs = await SharedPreferences.getInstance();
  final isFirstLaunch = (prefs.getBool('is_first_launch') ?? true) && !authProvider.isAuthenticated;

  runApp(MyApp(isFirstLaunch: isFirstLaunch, authProvider: authProvider));
}


class MyApp extends StatelessWidget {
  final bool isFirstLaunch;
  final AuthProvider authProvider;
  
  const MyApp({
    super.key,
    required this.isFirstLaunch,
    required this.authProvider,
  });

  @override
  Widget build(BuildContext context) {
    final router = createRouter(isFirstLaunch: isFirstLaunch);
    return MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: authProvider),
        ChangeNotifierProvider(create: (_) => CartProvider()),
        ChangeNotifierProvider(create: (_) => HomeProvider()),
        ChangeNotifierProvider(create: (_) => CategoryProvider()),
        ChangeNotifierProvider(create: (_) => ProductDetailProvider()),
        ChangeNotifierProvider(create: (_) => LoyaltyProvider()),
        ChangeNotifierProvider(create: (_) => ChatProvider()),
        ChangeNotifierProvider(create: (_) => FavoriteProvider()),
        ChangeNotifierProvider(create: (_) => CouponProvider()),
      ],
      child: MaterialApp.router(
        title: 'Ocean Sport',
        debugShowCheckedModeBanner: false,
        routerConfig: router,
        // Sử dụng theme tập trung đồng bộ với website
        theme: AppTheme.lightTheme,
        builder: (context, child) {
          return OfflineBanner(child: child!);
        },
      ),
    );
  }
}
