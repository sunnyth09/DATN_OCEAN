import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:latlong2/latlong.dart';
import '../models/order_tracking_model.dart';

class OrderTrackingService {
  static final OrderTrackingService _instance = OrderTrackingService._internal();
  factory OrderTrackingService() => _instance;
  OrderTrackingService._internal();

  final Dio _dio = Dio(
    BaseOptions(
      baseUrl: 'https://api.oceanexpress.bcbdev.id.vn/api/v1/public',
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 15),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ),
  );

  /// Fetch order tracking info from Ocean Express public API
  Future<ShippingOrder> fetchTracking(String trackingNumber) async {
    final cleanCode = trackingNumber.trim();
    if (cleanCode.isEmpty) {
      throw Exception('Vui lòng nhập mã vận đơn cần tra cứu.');
    }

    try {
      final response = await _dio.get('/tracking/$cleanCode');

      if (response.statusCode == 200 && response.data != null) {
        final dynamic rawData = response.data;
        if (rawData is Map<String, dynamic>) {
          final res = OrderTrackingResponse.fromJson(rawData);
          if (res.data != null) {
            return res.data!;
          }
          if (rawData['data'] != null && rawData['data'] is Map<String, dynamic>) {
            return ShippingOrder.fromJson(rawData['data'] as Map<String, dynamic>);
          }
        }
      }

      throw Exception('Không tìm thấy thông tin hành trình cho mã vận đơn: $cleanCode');
    } on DioException catch (e) {
      debugPrint('Ocean Express Tracking API error: ${e.message}');
      if (e.response != null && e.response?.data is Map) {
        final msg = e.response?.data['message']?.toString();
        if (msg != null && msg.isNotEmpty) {
          throw Exception(msg);
        }
      }
      if (e.type == DioExceptionType.connectionTimeout || e.type == DioExceptionType.receiveTimeout) {
        throw Exception('Hết thời gian kết nối đến máy chủ vận chuyển. Vui lòng thử lại.');
      }
      if (e.response?.statusCode == 404) {
        throw Exception('Mã vận đơn "$cleanCode" không tồn tại trên hệ thống Ocean Express.');
      }
      throw Exception('Lỗi kết nối tra cứu vận đơn (${e.response?.statusCode ?? 'No network'}).');
    } catch (e) {
      debugPrint('Unexpected tracking error: $e');
      rethrow;
    }
  }

  /// Lộ trình Quốc lộ 1A & Quốc lộ 6 uốn lượn chính xác dọc bờ biển và miền núi Việt Nam (TP.HCM -> Sơn La 1548.1 km)
  static List<LatLng> getVietnamHighwayRoutePoints() {
    return const [
      // 1. Xuất phát từ TP. Hồ Chí Minh
      LatLng(10.823099, 106.629664), // Điểm 1: TP.HCM
      LatLng(10.957500, 106.842700), // Biên Hòa
      LatLng(10.929700, 107.241500), // Long Khánh
      LatLng(10.933333, 108.100000), // Phan Thiết
      LatLng(11.350000, 108.520000), // Tuy Phong
      LatLng(11.566700, 108.983300), // Phan Rang
      LatLng(11.900000, 109.150000), // Cam Ranh
      LatLng(12.238800, 109.196700), // Nha Trang
      LatLng(12.700000, 109.280000), // Ninh Hòa
      LatLng(13.088200, 109.308000), // Tuy Hòa
      LatLng(13.782967, 109.219663), // Điểm 3: Quy Nhơn / Nam Trung Bộ
      LatLng(14.400000, 109.030000), // Phù Mỹ
      LatLng(15.120000, 108.800000), // Quảng Ngãi
      LatLng(15.568300, 108.483300), // Tam Kỳ
      LatLng(15.880000, 108.330000), // Hội An
      LatLng(16.054407, 108.202167), // Điểm 4: Đà Nẵng
      LatLng(16.463700, 107.590900), // Huế
      LatLng(16.816700, 107.100000), // Đông Hà
      LatLng(17.483300, 106.600000), // Đồng Hới
      LatLng(17.900000, 106.300000), // Ba Đồn
      LatLng(18.050000, 106.000000), // Kỳ Anh
      LatLng(18.341700, 105.900000), // Hà Tĩnh
      LatLng(18.679585, 105.681335), // Vinh
      LatLng(19.300000, 105.750000), // Hoàng Mai
      LatLng(19.800000, 105.780000), // Thanh Hóa
      LatLng(20.250000, 105.970000), // Ninh Bình
      LatLng(20.450000, 105.900000), // Nho Quan
      LatLng(20.817150, 105.337580), // Điểm 5: Hòa Bình / Cửa ngõ Tây Bắc
      LatLng(20.680000, 105.080000), // Cao Phong
      LatLng(20.700000, 104.900000), // Tân Lạc
      LatLng(20.750000, 104.750000), // Đèo Thung Khe
      LatLng(20.850000, 104.600000), // Vân Hồ
      LatLng(20.850000, 104.630000), // Mộc Châu
      LatLng(21.020000, 104.300000), // Yên Châu
      LatLng(21.200000, 104.050000), // Mai Sơn
      LatLng(21.328000, 103.914000), // Ghim Đỏ: TP. Sơn La / Xã Bình Thuận
  }
}
