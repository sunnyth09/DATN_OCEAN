import 'package:flutter/foundation.dart';

/// Logger tập trung cho toàn ứng dụng.
/// - Chỉ xuất log trên môi trường debug (kDebugMode).
/// - Production: không xuất log nào ra console.
/// - Sẵn sàng tích hợp Crashlytics / Sentry cho production error tracking.
class AppLogger {
  AppLogger._();

  static const String _tag = 'OceanSport';

  /// Log thông tin debug — chỉ hiển thị khi kDebugMode = true
  static void debug(String message, [String? tag]) {
    if (kDebugMode) {
      debugPrint('[$_tag${tag != null ? ':$tag' : ''}] $message');
    }
  }

  /// Log thông tin thông thường
  static void info(String message, [String? tag]) {
    if (kDebugMode) {
      debugPrint('[$_tag${tag != null ? ':$tag' : ''}] ℹ️ $message');
    }
  }

  /// Log cảnh báo — vấn đề không critical nhưng cần chú ý
  static void warning(String message, [String? tag]) {
    if (kDebugMode) {
      debugPrint('[$_tag${tag != null ? ':$tag' : ''}] ⚠️ $message');
    }
  }

  /// Log lỗi — vấn đề cần xử lý
  static void error(String message, [Object? error, String? tag]) {
    if (kDebugMode) {
      debugPrint('[$_tag${tag != null ? ':$tag' : ''}] ❌ $message${error != null ? ': $error' : ''}');
    }
    // TODO: Tích hợp Firebase Crashlytics cho production
    // if (!kDebugMode && error != null) {
    //   FirebaseCrashlytics.instance.recordError(error, StackTrace.current);
    // }
  }
}
