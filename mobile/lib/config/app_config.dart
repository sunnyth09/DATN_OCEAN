/// ============================================================
/// CẤU HÌNH TẬP TRUNG CHO TOÀN BỘ ỨNG DỤNG MOBILE
/// ============================================================
/// Chỉ cần thay đổi URL ở ĐÂY, toàn bộ app sẽ tự cập nhật.
/// - Khi chạy trên máy local (emulator): dùng kLocalBaseUrl
/// - Khi chạy với server thật (production): dùng kProductionBaseUrl
/// ============================================================


import 'package:flutter/foundation.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';

class AppConfig {
  AppConfig._(); // Ngăn tạo instance

  /// ── URL CƠ BẢN CỦA API (Production) ──
  static const String kProductionBaseUrl = 'https://api.ocean.pro.vn/api';
  static const String kProductionStorageUrl = 'https://api.ocean.pro.vn/storage';

  /// ── URL LOCAL (Development/Emulator) ──
  /// LƯU Ý: Lấy IP từ file .env (API_IP)
  static String get _localIp {
    if (kIsWeb) {
      // Khi chạy trên Web (Chrome), luôn gọi thẳng vào localhost (127.0.0.1)
      return '127.0.0.1';
    }
    // Khi chạy Mobile: Emulator dùng 10.0.2.2, máy thật dùng LAN IP
    return dotenv.env['API_IP'] ?? '10.0.2.2';
  }
  static String get kLocalBaseUrl => 'http://$_localIp:8383/api';
  static String get kLocalStorageUrl => 'http://$_localIp:8383/storage';

  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  // 👇 CHUYỂN ĐỔI Ở ĐÂY: true = dùng server thật
  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  static const bool isProduction = false;

  /// Base URL cho API (tự động chọn theo isProduction)
  static String get kBaseUrl => isProduction ? kProductionBaseUrl : kLocalBaseUrl;

  /// Base URL cho ảnh/video (tự động chọn theo isProduction)
  static String get kStorageUrl => isProduction ? kProductionStorageUrl : kLocalStorageUrl;

  /// Tạo URL đầy đủ cho ảnh từ đường dẫn relative
  /// VD: 'products/abc.jpg' → 'https://api.ocean.pro.vn/storage/products/abc.jpg'
  static String imageUrl(String? rawImage) {
    if (rawImage == null || rawImage.isEmpty) return '';
    if (rawImage.startsWith('http')) return rawImage;
    // Production: dùng /storage/ trực tiếp (nhanh hơn image-proxy)
    // Local: dùng /api/image-proxy?path= (vì storage link chưa config)
    if (isProduction) {
      return '$kStorageUrl/$rawImage';
    }
    return '$kBaseUrl/image-proxy?path=$rawImage';
  }
}
