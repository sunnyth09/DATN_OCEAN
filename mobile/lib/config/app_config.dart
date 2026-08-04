import 'package:flutter/foundation.dart';

class AppConfig {
  AppConfig._();

  static const String kProductionBaseUrl = 'https://apiocean.bcbdev.id.vn/api';
  static const String kProductionStorageUrl =
      'https://apiocean.bcbdev.id.vn/storage';

  static const String _apiBaseUrlOverride = String.fromEnvironment(
    'API_BASE_URL',
  );
  static const String _storageBaseUrlOverride = String.fromEnvironment(
    'STORAGE_BASE_URL',
  );
  static const String _apiIpOverride = String.fromEnvironment('API_IP');

  static const bool isProduction = bool.fromEnvironment('IS_PRODUCTION');
  static const String ghnToken = String.fromEnvironment('TOKEN_GHN', defaultValue: 'aca8eb35-5c9d-11f0-9ca3-d63c9cb9975d');
  static const String ghnShopId = String.fromEnvironment('GHN_SHOP_ID', defaultValue: '5881673');

  static String get _localIp {
    if (kIsWeb) {
      return '127.0.0.1';
    }
    return _apiIpOverride.isNotEmpty ? _apiIpOverride : '10.0.2.2';
  }

  static String get kLocalBaseUrl => 'https://apiocean.bcbdev.id.vn/api';
  static String get kLocalStorageUrl => 'https://apiocean.bcbdev.id.vn/storage';

  static String get kBaseUrl {
    if (_apiBaseUrlOverride.isNotEmpty) return _apiBaseUrlOverride;
    return isProduction ? kProductionBaseUrl : kLocalBaseUrl;
  }

  static String get kStorageUrl {
    if (_storageBaseUrlOverride.isNotEmpty) return _storageBaseUrlOverride;
    return isProduction ? kProductionStorageUrl : kLocalStorageUrl;
  }

  static String? _normalizeImagePath(String? rawImage) {
    final value = rawImage?.trim();
    if (value == null || value.isEmpty || value == '0') {
      return null;
    }

    if (value.startsWith('http')) {
      return value;
    }

    if (value.startsWith('/storage/')) {
      return value.substring('/storage/'.length);
    }

    if (value.startsWith('storage/')) {
      return value.substring('storage/'.length);
    }

    if (value.startsWith('/')) {
      return value.substring(1);
    }

    return value;
  }

  static String imageUrl(String? rawImage) {
    final normalized = _normalizeImagePath(rawImage);
    if (normalized == null) return '';
    
    if (normalized.startsWith('http')) {
      if (!isProduction && !kIsWeb) {
        String url = normalized;
        if (url.contains('127.0.0.1')) {
          url = url.replaceAll('127.0.0.1', _localIp);
        } else if (url.contains('localhost')) {
          url = url.replaceAll('localhost', _localIp);
        }
        return url;
      }
      return normalized;
    }

    if (isProduction) {
      return '$kStorageUrl/$normalized';
    }

    return '$kLocalStorageUrl/$normalized';
  }

  static String productImageUrl(Map<dynamic, dynamic>? product) {
    if (product == null) return '';

    final mainImage = product['main_image'];
    final images = product['images'];
    final variants = product['variants'];

    final candidates = <dynamic>[
      if (mainImage is Map) mainImage['image_url'],
      if (mainImage is String) mainImage,
      product['thumbnail_url'],
      product['image_url'],
      if (images is List)
        ...images.map((image) => image is Map ? image['image_url'] : null),
      if (variants is List)
        ...variants.map((variant) => variant is Map ? variant['image_url'] : null),
    ];

    for (final candidate in candidates) {
      final resolvedUrl = imageUrl(candidate?.toString());
      if (resolvedUrl.isNotEmpty) {
        return resolvedUrl;
      }
    }

    return '';
  }
}
