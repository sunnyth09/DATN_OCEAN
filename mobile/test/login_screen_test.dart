import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:mobile/screens/login_screen.dart';
import 'package:mobile/providers/auth_provider.dart';
import 'package:mobile/providers/cart_provider.dart';

void main() {
  testWidgets('LoginScreen shows validation error on empty fields', (WidgetTester tester) async {
    // Bọc LoginScreen trong MultiProvider và MaterialApp
    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => AuthProvider()),
          ChangeNotifierProvider(create: (_) => CartProvider()),
        ],
        child: const MaterialApp(
          home: LoginScreen(),
        ),
      ),
    );

    // Verify that the login button is present.
    final loginButtonFinder = find.byType(ElevatedButton);
    expect(loginButtonFinder, findsOneWidget);

    // Bấm nút đăng nhập mà không nhập email/mật khẩu
    await tester.tap(loginButtonFinder);
    
    // Pump để render SnackBar
    await tester.pump();

    // Verify rằng SnackBar báo lỗi hiển thị
    expect(find.text('Vui lòng nhập Email và Mật khẩu'), findsOneWidget);
  });
}
