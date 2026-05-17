/// ============================================================
/// CẤU HÌNH TẬP TRUNG CHO TOÀN BỘ ỨNG DỤNG MOBILE
/// ============================================================
/// Chỉ cần thay đổi URL ở ĐÂY, toàn bộ app sẽ tự cập nhật.
/// - Khi chạy trên máy local (emulator): dùng kLocalBaseUrl
/// - Khi chạy với server thật (production): dùng kProductionBaseUrl
/// ============================================================

import 'package:flutter_dotenv/flutter_dotenv.dart';

class AppConfig {
  AppConfig._(); // Ngăn tạo instance

  /// ── URL SERVER THẬT (Production) ──
  static const String kProductionBaseUrl = 'https://api.ocean.pro.vn/api';
  static const String kProductionStorageUrl = 'https://api.ocean.pro.vn/storage';

  /// ── URL LOCAL (Development/Emulator) ──
  static const String kLocalBaseUrl = 'http://127.0.0.1:8383/api';
  static const String kLocalStorageUrl = 'http://127.0.0.1:8383/storage';

  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  // 👇 CHUYỂN ĐỔI Ở ĐÂY: true = dùng server thật
  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  static const bool isProduction = true;

  /// Base URL cho API (tự động chọn theo isProduction)
  static const String kBaseUrl = isProduction ? kProductionBaseUrl : kLocalBaseUrl;

  /// Base URL cho Storage (ảnh sản phẩm, avatar, v.v.)
  static const String kStorageUrl = isProduction ? kProductionStorageUrl : kLocalStorageUrl;

  /// ── CLOUDFLARE TURNSTILE ──
  /// Đọc Site Key từ file .env (biến TURNSTILE_SITE_KEY)
  static String get kTurnstileSiteKey => dotenv.env['TURNSTILE_SITE_KEY'] ?? '';

  /// ── FRONTEND URL (cho WebView Login) ──
  static const String kFrontendLoginUrl = 'https://ocean.pro.vn/client/login';

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
