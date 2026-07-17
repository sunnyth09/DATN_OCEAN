import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:firebase_core/firebase_core.dart';
import 'screens/onboarding_screen.dart';
import 'screens/main_wrapper.dart';
import 'screens/login_screen.dart';
import 'config/app_theme.dart';
import 'firebase_options.dart';
import 'providers/auth_provider.dart';
import 'providers/cart_provider.dart';
import 'services/notification_service.dart';
import 'services/app_navigator.dart';

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
    // Xác định màn hình khởi động:
    // 1. Lần đầu mở app → Onboarding
    // 2. Đã đăng nhập → MainWrapper (thẳng vào trang chủ)
    // 3. Chưa đăng nhập → LoginScreen
    Widget homeScreen;
    if (isFirstLaunch) {
      homeScreen = const OnboardingScreen();
    } else if (authProvider.isAuthenticated) {
      homeScreen = const MainWrapper();
    } else {
      homeScreen = const LoginScreen();
    }

    return MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: authProvider),
        ChangeNotifierProvider(create: (_) => CartProvider()),
      ],
      child: MaterialApp(
        title: 'Quyền Sport',
        debugShowCheckedModeBanner: false,
        navigatorKey: appNavigatorKey,
        // Sử dụng theme tập trung đồng bộ với website
        theme: AppTheme.lightTheme,
        home: homeScreen,
      ),
    );
  }
}
