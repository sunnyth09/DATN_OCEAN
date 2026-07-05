import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class StorageService {
  StorageService._();

  static const FlutterSecureStorage _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static Future<String?> read(String key) => _storage.read(key: key);

  static Future<void> write(String key, String value) =>
      _storage.write(key: key, value: value);

  static Future<void> delete(String key) => _storage.delete(key: key);
}
