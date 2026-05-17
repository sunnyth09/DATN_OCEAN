import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:network_info_plus/network_info_plus.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:geolocator/geolocator.dart';
import '../config/app_config.dart';

class AttendanceService {
  static const String baseUrl = '${AppConfig.kBaseUrl}/attendance';
  final _info = NetworkInfo();

  Future<String?> _getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('access_token');
  }

  Future<Map<String, dynamic>> checkIn({String? note}) async {
    return _processAttendance('/check-in', note: note);
  }

  Future<Map<String, dynamic>> checkOut() async {
    return _processAttendance('/check-out');
  }

  Future<Map<String, dynamic>> _processAttendance(String endpoint, {String? note}) async {
    final token = await _getToken();
    if (token == null) {
      return {'status': 'error', 'message': 'Bạn chưa đăng nhập'};
    }

    // Kiểm tra Permission Vị trí
    var status = await Permission.locationWhenInUse.request();
    if (!status.isGranted) {
      return {'status': 'error', 'message': 'Ứng dụng cần quyền Vị trí để xác thực WiFi hoặc tính khoảng cách GPS.'};
    }

    // Lấy thông tin WiFi
    String? ssid;
    String? bssid;
    try {
      ssid = await _info.getWifiName();
      bssid = await _info.getWifiBSSID();
    } catch (e) {
      // Bỏ qua lỗi WiFi
    }

    // Lấy GPS Fallback (Nếu ko kết nối WiFi công ty)
    double? lat;
    double? lng;
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled && ssid == null) {
          return {'status': 'error', 'message': 'Vui lòng bật Vị trí (Location/GPS) trên thiết bị.'};
      }

      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
        timeLimit: const Duration(seconds: 10),
      );
      lat = position.latitude;
      lng = position.longitude;
    } catch (e) {
      // Ignored
    }

    try {
      final response = await http.post(
        Uri.parse('$baseUrl$endpoint'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: jsonEncode({
          'wifi_ssid': ssid,
          'wifi_bssid': bssid,
          'lat': lat,
          'lng': lng,
          if (note != null) 'note': note,
        }),
      ).timeout(const Duration(seconds: 10));

      return jsonDecode(utf8.decode(response.bodyBytes));
    } catch (e) {
      return {'status': 'error', 'message': 'Không thể kết nối với máy chủ chấm công. Vui lòng kiểm tra lại mạng.'};
    }
  }

  Future<Map<String, String?>> getCurrentNetworkInfo() async {
    var status = await Permission.locationWhenInUse.request();
    if (status.isGranted) {
      final ssid = await _info.getWifiName();
      return {'ssid': ssid?.replaceAll('"', '')};
    }
    return {'ssid': null};
  }
}
