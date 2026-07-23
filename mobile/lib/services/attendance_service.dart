import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:geolocator/geolocator.dart';
import 'package:network_info_plus/network_info_plus.dart';
import 'package:permission_handler/permission_handler.dart';
import 'api_client.dart';

/// Kết quả xin quyền vị trí, để UI biết cách phản hồi.
enum LocationPermissionResult { granted, denied, permanentlyDenied, serviceDisabled }

class AttendanceService {
  final _dio = ApiClient().dio;
  final _info = NetworkInfo();

  Future<Map<String, dynamic>> checkIn({String? note}) async {
    return _processAttendance('/admin/attendance/check-in', note: note);
  }

  Future<Map<String, dynamic>> checkOut() async {
    return _processAttendance('/admin/attendance/check-out');
  }

  Future<Map<String, dynamic>> _processAttendance(
    String endpoint, {
    String? note,
  }) async {
    // 1. Kiểm tra kết nối mạng SỚM để không phải chờ timeout 15s khi mất mạng.
    final connectivity = await Connectivity().checkConnectivity();
    if (connectivity.contains(ConnectivityResult.none)) {
      return {
        'status': 'error',
        'message': 'Mất kết nối mạng. Vui lòng kiểm tra WiFi/4G rồi thử lại.',
      };
    }

    // 2. Xin quyền Vị trí — phân biệt rõ trạng thái để UI xử lý đúng.
    final permission = await ensureLocationPermission();
    switch (permission) {
      case LocationPermissionResult.granted:
        break;
      case LocationPermissionResult.serviceDisabled:
        return {
          'status': 'error',
          'message': 'Vui lòng bật Vị trí (Location/GPS) trên thiết bị để chấm công.',
        };
      case LocationPermissionResult.permanentlyDenied:
        return {
          'status': 'error',
          'needs_settings': true,
          'message':
              'Bạn đã tắt quyền Vị trí. Vui lòng mở Cài đặt và cấp lại quyền để chấm công.',
        };
      case LocationPermissionResult.denied:
        return {
          'status': 'error',
          'message': 'Ứng dụng cần quyền Vị trí để xác thực toạ độ chấm công.',
        };
    }

    // 3. Lấy toạ độ GPS. Backend BẮT BUỘC latitude/longitude nên phải có mới gửi.
    Position position;
    try {
      position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 15),
        ),
      );
    } catch (_) {
      return {
        'status': 'error',
        'message':
            'Không lấy được vị trí GPS. Hãy ra nơi thoáng, bật độ chính xác cao rồi thử lại.',
      };
    }

    // 4. Chống Fake GPS: chặn ngay ở client nếu toạ độ đến từ app mock location.
    //    Lưu ý: đây chỉ là lớp phòng thủ đầu; quyết định hợp lệ vẫn do backend
    //    kiểm tra bán kính. isMocked có thể bị bypass trên máy root.
    if (position.isMocked) {
      return {
        'status': 'error',
        'message':
            'Phát hiện vị trí giả mạo (Fake GPS). Vui lòng tắt ứng dụng giả lập vị trí rồi thử lại.',
      };
    }

    // 5. Lấy thông tin WiFi (chỉ để hiển thị/log; backend hiện xác thực bằng GPS).
    String? ssid;
    String? bssid;
    try {
      ssid = await _info.getWifiName();
      bssid = await _info.getWifiBSSID();
    } catch (_) {
      // Bỏ qua lỗi WiFi
    }

    try {
      final response = await _dio.post(
        endpoint,
        data: {
          'latitude': position.latitude,
          'longitude': position.longitude,
          'accuracy': position.accuracy,
          'wifi_ssid': ssid,
          'wifi_bssid': bssid,
          if (note != null && note.isNotEmpty) 'note': note,
        },
      );

      final data = response.data;
      if (data is Map) return Map<String, dynamic>.from(data);
      return {'status': 'error', 'message': 'Phản hồi không hợp lệ từ máy chủ.'};
    } on DioException catch (e) {
      return {'status': 'error', 'message': _messageFromError(e)};
    }
  }

  /// Xin quyền vị trí, trả về trạng thái chi tiết cho UI.
  Future<LocationPermissionResult> ensureLocationPermission() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) return LocationPermissionResult.serviceDisabled;

    var status = await Permission.locationWhenInUse.status;

    if (status.isGranted) return LocationPermissionResult.granted;

    // Đã từ chối vĩnh viễn → .request() sẽ KHÔNG hiện popup nữa, phải ra Settings.
    if (status.isPermanentlyDenied) {
      return LocationPermissionResult.permanentlyDenied;
    }

    status = await Permission.locationWhenInUse.request();
    if (status.isGranted) return LocationPermissionResult.granted;
    if (status.isPermanentlyDenied) {
      return LocationPermissionResult.permanentlyDenied;
    }
    return LocationPermissionResult.denied;
  }

  Future<Map<String, String?>> getCurrentNetworkInfo() async {
    final status = await Permission.locationWhenInUse.status;
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
      return {'status': 'error', 'message': 'Phản hồi không hợp lệ.'};
    } on DioException catch (e) {
      return {'status': 'error', 'message': _messageFromError(e)};
    }
  }

  String _messageFromError(DioException e) {
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.sendTimeout) {
      return 'Máy chủ phản hồi chậm. Vui lòng thử lại.';
    }
    if (e.type == DioExceptionType.connectionError) {
      return 'Không thể kết nối máy chủ. Vui lòng kiểm tra mạng.';
    }
    final data = e.response?.data;
    if (data is Map && data['message'] != null) {
      return data['message'].toString();
    }
    return 'Không thể kết nối máy chủ chấm công. Vui lòng thử lại.';
  }
}
