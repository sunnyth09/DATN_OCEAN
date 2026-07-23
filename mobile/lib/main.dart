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
import 'services/notification_service.dart';
import 'widgets/offline_banner.dart';
import 'router/app_router.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('vi_VN', null);
  await initializeDateFormatting('vi', null);

  // Khởi tạo Firebase TRƯỚC khi dùng FCM
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );

  // Khởi tạo NotificationService → xin quyền + đăng ký FCM handlers
  await NotificationService().initialize();

  final prefs = await SharedPreferences.getInstance();
  final isFirstLaunch = prefs.getBool('is_first_launch') ?? true;

  // Khôi phục phiên đăng nhập từ SecureStorage vào AuthProvider.
  final authProvider = AuthProvider();
  await authProvider.bootstrap();

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
      ],
      child: MaterialApp.router(
        title: 'Quyền Sport',
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
