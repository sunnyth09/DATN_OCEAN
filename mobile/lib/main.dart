import 'dart:io';
import 'config/app_config.dart';
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

import 'providers/loyalty_provider.dart';
import 'providers/chat_provider.dart';
import 'providers/favorite_provider.dart';
import 'providers/coupon_provider.dart';
import 'providers/open_play_provider.dart';
import 'services/notification_service.dart';
import 'widgets/offline_banner.dart';
import 'router/app_router.dart';

/// HttpOverrides cho phép bỏ qua lỗi chứng chỉ self-signed trên môi trường dev/emulator.
/// ⚠️ CHỈ hoạt động khi KHÔNG phải Production để ngăn chặn tấn công MITM.
class AppHttpOverrides extends HttpOverrides {
  @override
  HttpClient createHttpClient(SecurityContext? context) {
    final client = super.createHttpClient(context);
    if (!AppConfig.isProduction) {
      client.badCertificateCallback = (cert, host, port) => true;
    }
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
        ChangeNotifierProvider(create: (_) => LoyaltyProvider()),
        ChangeNotifierProvider(create: (_) => ChatProvider()),
        ChangeNotifierProvider(create: (_) => FavoriteProvider()),
        ChangeNotifierProvider(create: (_) => CouponProvider()),
        ChangeNotifierProvider(create: (_) => OpenPlayProvider()),
      ],
      child: MaterialApp.router(
        title: 'Ocean Sport',
        debugShowCheckedModeBanner: false,
        routerConfig: router,
        // Sử dụng theme tập trung đồng bộ với website
        theme: AppTheme.lightTheme,
        darkTheme: AppTheme.darkTheme,
        themeMode: ThemeMode.system,
        builder: (context, child) {
          return OfflineBanner(child: child!);
        },
      ),
    );
  }
}
