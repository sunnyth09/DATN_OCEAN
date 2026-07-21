import 'package:dio/dio.dart';
import 'package:network_info_plus/network_info_plus.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:geolocator/geolocator.dart';
import 'api_client.dart';

class AttendanceService {
  final _dio = ApiClient().dio;
  final _info = NetworkInfo();

  Future<Map<String, dynamic>> checkIn({String? note}) async {
    return _processAttendance('/attendance/check-in', note: note);
  }

  Future<Map<String, dynamic>> checkOut() async {
    return _processAttendance('/attendance/check-out');
  }

  Future<Map<String, dynamic>> _processAttendance(
    String endpoint, {
    String? note,
  }) async {
    // Kiểm tra Permission Vị trí
    final status = await Permission.locationWhenInUse.request();
    if (!status.isGranted) {
      return {
        'status': 'error',
        'message':
            'Ứng dụng cần quyền Vị trí để xác thực WiFi hoặc tính khoảng cách GPS.',
      };
    }

    // Lấy thông tin WiFi
    String? ssid;
    String? bssid;
    try {
      ssid = await _info.getWifiName();
      bssid = await _info.getWifiBSSID();
    } catch (_) {
      // Bỏ qua lỗi WiFi
    }

    // Lấy GPS Fallback (Nếu không kết nối WiFi công ty)
    double? lat;
    double? lng;
    try {
      final serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled && ssid == null) {
        return {
          'status': 'error',
          'message': 'Vui lòng bật Vị trí (Location/GPS) trên thiết bị.',
        };
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 10),
        ),
      );
      lat = position.latitude;
      lng = position.longitude;
    } catch (_) {
      // Ignored
    }

    try {
      final response = await _dio.post(
        endpoint,
        data: {
          'wifi_ssid': ssid,
          'wifi_bssid': bssid,
          'lat': lat,
          'lng': lng,
          'note': ?note,
        },
      );

      final data = response.data;
      if (data is Map) return Map<String, dynamic>.from(data);
      return {'status': 'error', 'message': 'Phản hồi không hợp lệ từ máy chủ.'};
    } on DioException catch (e) {
      final data = e.response?.data;
      if (data is Map && data['message'] != null) {
        return {'status': 'error', 'message': data['message'].toString()};
      }
      return {
        'status': 'error',
        'message':
            'Không thể kết nối với máy chủ chấm công. Vui lòng kiểm tra lại mạng.',
      };
    }
  }

  Future<Map<String, String?>> getCurrentNetworkInfo() async {
    final status = await Permission.locationWhenInUse.request();
    if (status.isGranted) {
      final ssid = await _info.getWifiName();
      return {'ssid': ssid?.replaceAll('"', '')};
    }
    return {'ssid': null};
  }

  Future<Map<String, dynamic>> getTodayStatus() async {
    try {
      final response = await _dio.get('/admin/attendance/today');
      final data = response.data;
      if (data is Map) return Map<String, dynamic>.from(data);
      return {'status': 'error', 'message': 'Invalid response'};
    } on DioException catch (e) {
      final data = e.response?.data;
      if (data is Map && data['message'] != null) {
        return {'status': 'error', 'message': data['message'].toString()};
      }
      return {'status': 'error', 'message': 'Failed to fetch today status.'};
    }
  }
}
