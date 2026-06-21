import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:firebase_core/firebase_core.dart';
import 'screens/onboarding_screen.dart';
import 'screens/main_wrapper.dart';
import 'screens/login_screen.dart';
import 'services/auth_service.dart';
import 'config/app_theme.dart';
import 'firebase_options.dart';
import 'services/notification_service.dart';

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

  // Kiểm tra trạng thái đăng nhập từ SecureStorage (thay thế SharedPreferences cũ)
  final isLoggedIn = await AuthService.isLoggedIn();

  runApp(MyApp(isFirstLaunch: isFirstLaunch, isLoggedIn: isLoggedIn));
}

class MyApp extends StatelessWidget {
  final bool isFirstLaunch;
  final bool isLoggedIn;
  const MyApp({
    super.key,
    required this.isFirstLaunch,
    required this.isLoggedIn,
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
    } else if (isLoggedIn) {
      homeScreen = const MainWrapper();
    } else {
      homeScreen = const LoginScreen();
    }

    return MaterialApp(
      title: 'Quyền Sport',
      debugShowCheckedModeBanner: false,
      // Sử dụng theme tập trung đồng bộ với website
      theme: AppTheme.lightTheme,
      home: homeScreen,
    );
  }
}
