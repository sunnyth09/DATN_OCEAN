/// Bộ công cụ validation tập trung cho form input.
/// Mỗi hàm trả về `null` nếu hợp lệ, hoặc message lỗi nếu không hợp lệ.
class Validators {
  Validators._();

  /// Kiểm tra trường bắt buộc
  static String? required(String? value, [String fieldName = 'Trường này']) {
    if (value == null || value.trim().isEmpty) {
      return '$fieldName không được để trống';
    }
    return null;
  }

  /// Kiểm tra email hợp lệ
  static String? email(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Vui lòng nhập email';
    }
    final trimmed = value.trim();
    if (!RegExp(r'^[\w\-\.+]+@([\w\-]+\.)+[\w\-]{2,}$').hasMatch(trimmed)) {
      return 'Email không hợp lệ';
    }
    return null;
  }

  /// Kiểm tra mật khẩu — ít nhất 6 ký tự
  static String? password(String? value, {int minLength = 6}) {
    if (value == null || value.isEmpty) {
      return 'Vui lòng nhập mật khẩu';
    }
    if (value.length < minLength) {
      return 'Mật khẩu phải có ít nhất $minLength ký tự';
    }
    return null;
  }

  /// Kiểm tra xác nhận mật khẩu
  static String? confirmPassword(String? value, String? original) {
    if (value == null || value.isEmpty) {
      return 'Vui lòng nhập lại mật khẩu';
    }
    if (value != original) {
      return 'Mật khẩu xác nhận không khớp';
    }
    return null;
  }

  /// Kiểm tra số điện thoại Việt Nam (0xxx hoặc +84xxx, 9-11 chữ số)
  static String? phone(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Vui lòng nhập số điện thoại';
    }
    final cleaned = value.trim().replaceAll(RegExp(r'[\s\-\.]'), '');
    if (!RegExp(r'^(\+84|0)\d{8,10}$').hasMatch(cleaned)) {
      return 'Số điện thoại không hợp lệ';
    }
    return null;
  }

  /// Kiểm tra độ dài tối thiểu
  static String? minLength(String? value, int min, [String fieldName = 'Nội dung']) {
    if (value == null || value.trim().length < min) {
      return '$fieldName phải có ít nhất $min ký tự';
    }
    return null;
  }

  /// Kiểm tra độ dài tối đa
  static String? maxLength(String? value, int max, [String fieldName = 'Nội dung']) {
    if (value != null && value.trim().length > max) {
      return '$fieldName không được vượt quá $max ký tự';
    }
    return null;
  }

  /// Kiểm tra giá trị số dương
  static String? positiveNumber(String? value, [String fieldName = 'Giá trị']) {
    if (value == null || value.trim().isEmpty) {
      return '$fieldName không được để trống';
    }
    final number = num.tryParse(value.trim());
    if (number == null || number <= 0) {
      return '$fieldName phải là số lớn hơn 0';
    }
    return null;
  }
}
