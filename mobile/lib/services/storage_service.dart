import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Dịch vụ lưu trữ bảo mật kết hợp In-Memory Cache.
/// Giải quyết triệt để vấn đề lag/chậm trên Android khi FlutterSecureStorage
/// phải gọi qua Android Keystore JNI Bridge (100-300ms) ở mỗi request API.
class StorageService {
  StorageService._();

  static final Map<String, String> _memoryCache = {};

  static const FlutterSecureStorage _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(
      encryptedSharedPreferences: true,
      resetOnError: true,
    ),
    iOptions: IOSOptions(
      accessibility: KeychainAccessibility.first_unlock_this_device,
    ),
  );

  static Future<String?> read(String key) async {
    if (_memoryCache.containsKey(key)) {
      return _memoryCache[key];
    }
    final value = await _storage.read(key: key);
    if (value != null) {
      _memoryCache[key] = value;
    }
    return value;
  }

  static String? readSync(String key) => _memoryCache[key];

  static Future<void> write(String key, String value) async {
    _memoryCache[key] = value;
    await _storage.write(key: key, value: value);
  }

  static Future<void> delete(String key) async {
    _memoryCache.remove(key);
    await _storage.delete(key: key);
  }

  static Future<void> clearAll() async {
    _memoryCache.clear();
    await _storage.deleteAll();
  }
}
