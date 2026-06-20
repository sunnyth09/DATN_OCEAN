import 'package:flutter_test/flutter_test.dart';

import 'package:mobile/main.dart';

void main() {
  testWidgets('shows login screen when user is not logged in', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      const MyApp(isFirstLaunch: false, isLoggedIn: false),
    );

    expect(find.text('Đăng nhập'), findsOneWidget);
    expect(find.text('Quyền Sport'), findsOneWidget);
  });
}
