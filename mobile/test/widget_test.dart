import 'package:flutter_test/flutter_test.dart';

import 'package:mobile/main.dart';
import 'package:mobile/providers/auth_provider.dart';

void main() {
  testWidgets('shows login screen when user is not logged in', (
    WidgetTester tester,
  ) async {
    // AuthProvider chưa bootstrap → mặc định chưa đăng nhập.
    await tester.pumpWidget(
      MyApp(isFirstLaunch: false, authProvider: AuthProvider()),
    );

    expect(find.text('Đăng nhập'), findsOneWidget);
    expect(find.text('Quyền Sport'), findsOneWidget);
  });
}
