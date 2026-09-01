/// Design tokens cho khoảng cách (spacing) tập trung.
/// Sử dụng thống nhất trong toàn bộ ứng dụng thay vì hardcode giá trị.
///
/// Ví dụ:
/// ```dart
/// Padding(padding: EdgeInsets.all(AppSpacing.md))
/// SizedBox(height: AppSpacing.lg)
/// ```
class AppSpacing {
  AppSpacing._();

  /// 2.0 — Khoảng cách siêu nhỏ (giữa icon & text inline)
  static const double xxs = 2;

  /// 4.0 — Khoảng cách cực nhỏ
  static const double xs = 4;

  /// 8.0 — Khoảng cách nhỏ (padding nội bộ)
  static const double sm = 8;

  /// 12.0 — Khoảng cách vừa nhỏ
  static const double md = 12;

  /// 16.0 — Khoảng cách chuẩn (padding thường dùng)
  static const double lg = 16;

  /// 20.0 — Khoảng cách vừa lớn
  static const double xl = 20;

  /// 24.0 — Khoảng cách lớn (section padding)
  static const double xxl = 24;

  /// 32.0 — Khoảng cách rất lớn (giữa các section)
  static const double xxxl = 32;

  /// 48.0 — Khoảng cách đặc biệt lớn (header/footer)
  static const double huge = 48;

  /// Padding ngang mặc định cho screen content
  static const double screenHorizontal = 16;

  /// Padding dọc mặc định cho screen content
  static const double screenVertical = 20;

  /// Border radius nhỏ (button, input)
  static const double radiusSm = 8;

  /// Border radius trung bình (card)
  static const double radiusMd = 12;

  /// Border radius lớn (bottom sheet, dialog)
  static const double radiusLg = 16;

  /// Border radius tròn (avatar, badge)
  static const double radiusXl = 24;

  /// Border radius tròn hoàn toàn
  static const double radiusFull = 999;
}
