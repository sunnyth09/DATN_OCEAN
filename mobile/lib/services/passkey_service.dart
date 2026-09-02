import 'dart:convert';
import 'package:flutter/services.dart';
import 'package:local_auth/local_auth.dart';
import 'storage_service.dart';

/// Quản lý vòng đời Passkey & Sinh trắc học phần cứng thực tế (Android BiometricPrompt & iOS Face ID).
/// - Sử dụng LocalAuthentication để kết nối trực tiếp với cảm biến vân tay/Face ID và mã khóa bảo mật của thiết bị.
/// - Ngăn chặn kích hoạt nếu thiết bị chưa cài đặt bảo mật màn hình (PIN / Vân tay).
class PasskeyService {
  PasskeyService._();

  static final LocalAuthentication _localAuth = LocalAuthentication();
  static const String _keyPrefix = 'passkey_enrolled_';

  /// Kiểm tra xem thiết bị có phần cứng sinh trắc học và ĐÃ CÀI ĐẶT khóa màn hình / vân tay chưa
  static Future<Map<String, dynamic>> checkDeviceBiometricSupport() async {
    try {
      final isSupported = await _localAuth.isDeviceSupported();
      final canCheck = await _localAuth.canCheckBiometrics;
      final availableBiometrics = await _localAuth.getAvailableBiometrics();

      if (!isSupported) {
        return {
          'supported': false,
          'message': 'Thiết bị này không hỗ trợ bảo mật sinh trắc học.',
        };
      }

      if (!canCheck && availableBiometrics.isEmpty) {
        return {
          'supported': false,
          'message': 'Thiết bị chưa thiết lập Khóa màn hình hoặc Sinh trắc học (PIN / Vân tay / Face ID). Vui lòng vào Cài đặt của máy để cài đặt trước.',
        };
      }

      return {'supported': true, 'message': 'Thiết bị sẵn sàng.'};
    } catch (e) {
      return {
        'supported': false,
        'message': 'Không thể kiểm tra cảm biến sinh trắc học: $e',
      };
    }
  }

  /// Gọi Popup xác thực sinh trắc học GỐC của hệ điều hành (Native OS Biometric Prompt)
  static Future<Map<String, dynamic>> authenticateWithBiometrics({
    String reason = 'Xác thực sinh trắc học để bảo mật tài khoản Ocean Sport',
  }) async {
    try {
      final check = await checkDeviceBiometricSupport();
      if (check['supported'] != true) {
        return {
          'success': false,
          'message': check['message'],
        };
      }

      final authenticated = await _localAuth.authenticate(
        localizedReason: reason,
        options: const AuthenticationOptions(
          stickyAuth: true,
          biometricOnly: false, // Cho phép cả vân tay, Face ID hoặc mã PIN/mật khẩu khóa màn hình máy
          useErrorDialogs: true,
        ),
      );

      return {
        'success': authenticated,
        'message': authenticated ? 'Xác thực thành công' : 'Đã hủy xác thực sinh trắc học.',
      };
    } on PlatformException catch (e) {
      String msg = 'Lỗi xác thực sinh trắc học.';
      if (e.code == 'NotEnrolled' || e.code == 'PasscodeNotSet') {
        msg = 'Thiết bị chưa cài đặt mã PIN / Vân tay / Khóa màn hình. Vui lòng thiết lập trong Cài đặt thiết bị.';
      } else if (e.code == 'NotAvailable') {
        msg = 'Cảm biến sinh trắc học hiện không khả dụng.';
      } else if (e.code == 'LockedOut' || e.code == 'PermanentlyLockedOut') {
        msg = 'Sinh trắc học bị tạm khóa do thử sai quá nhiều lần. Vui lòng mở khóa thiết bị.';
      }
      return {'success': false, 'message': msg};
    } catch (e) {
      return {'success': false, 'message': 'Lỗi xác thực: $e'};
    }
  }

  /// Kiểm tra xem tài khoản (email) này đã được đăng ký Passkey trên thiết bị chưa.
  static Future<bool> isPasskeyEnrolled(String email) async {
    if (email.trim().isEmpty) return false;
    final val = await StorageService.read('$_keyPrefix${email.trim().toLowerCase()}');
    return val == 'true';
  }

  /// Đăng ký / Kích hoạt Passkey cho tài khoản sau khi đã đăng nhập thành công.
  /// ⚠️ KHÔNG lưu mật khẩu — chỉ lưu token phiên và thông tin người dùng.
  static Future<void> enrollPasskey(
    String email, {
    String? name,
    String? avatarUrl,
    String? token,
    Map<String, dynamic>? userData,
  }) async {
    final key = '$_keyPrefix${email.trim().toLowerCase()}';
    await StorageService.write(key, 'true');
    await StorageService.write('${key}_at', DateTime.now().toIso8601String());
    if (token != null && token.isNotEmpty) {
      await StorageService.write('${key}_token', token);
    }
    if (userData != null) {
      await StorageService.write('${key}_user', jsonEncode(userData));
    }

    // Cập nhật trạng thái has_passkey vào thông tin tài khoản gần nhất
    final raw = await StorageService.read('last_login_account');
    Map<String, dynamic> acc = {};
    if (raw != null) {
      try {
        final decoded = jsonDecode(raw);
        if (decoded is Map<String, dynamic>) {
          acc = decoded;
        }
      } catch (_) {}
    }
    acc['email'] = email;
    if (name != null) acc['name'] = name;
    if (avatarUrl != null) acc['avatar_url'] = avatarUrl;
    acc['has_passkey'] = true;
    await StorageService.write('last_login_account', jsonEncode(acc));
  }

  /// Cập nhật credentials mới nhất cho Passkey đã enrolled (KHÔNG thay đổi trạng thái enrolled).
  /// Gọi sau mỗi lần đăng nhập thành công để giữ token luôn fresh.
  static Future<void> updatePasskeyData(
    String email, {
    String? token,
    Map<String, dynamic>? userData,
  }) async {
    if (email.trim().isEmpty) return;
    final key = '$_keyPrefix${email.trim().toLowerCase()}';
    // Chỉ cập nhật nếu đã enrolled — không tạo mới entry
    final enrolled = await StorageService.read(key);
    if (enrolled != 'true') return;

    if (token != null && token.isNotEmpty) {
      await StorageService.write('${key}_token', token);
    }
    if (userData != null) {
      await StorageService.write('${key}_user', jsonEncode(userData));
    }
  }



  /// Lấy Token phiên đăng nhập của Passkey đã lưu
  static Future<String?> getPasskeyToken(String email) async {
    if (email.trim().isEmpty) return null;
    final key = '$_keyPrefix${email.trim().toLowerCase()}_token';
    return await StorageService.read(key);
  }

  /// Lấy User Profile Cache của Passkey đã lưu
  static Future<Map<String, dynamic>?> getPasskeyUserData(String email) async {
    if (email.trim().isEmpty) return null;
    final key = '$_keyPrefix${email.trim().toLowerCase()}_user';
    final raw = await StorageService.read(key);
    if (raw != null) {
      try {
        final decoded = jsonDecode(raw);
        if (decoded is Map<String, dynamic>) return decoded;
      } catch (_) {}
    }
    return null;
  }

  /// Hủy kích hoạt Passkey trên thiết bị.
  static Future<void> revokePasskey(String email) async {
    final key = '$_keyPrefix${email.trim().toLowerCase()}';
    await StorageService.delete(key);
    await StorageService.delete('${key}_at');
    await StorageService.delete('${key}_token');
    await StorageService.delete('${key}_user');

    final raw = await StorageService.read('last_login_account');
    if (raw != null) {
      try {
        final acc = jsonDecode(raw);
        if (acc is Map<String, dynamic>) {
          acc['has_passkey'] = false;
          await StorageService.write('last_login_account', jsonEncode(acc));
        }
      } catch (_) {}
    }
  }
}
