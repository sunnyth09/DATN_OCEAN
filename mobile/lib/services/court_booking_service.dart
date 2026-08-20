import 'package:dio/dio.dart';
import 'api_client.dart';
import '../models/court_booking_models.dart';

class CourtBookingService {
  final _dio = ApiClient().dio;

  Future<List<Court>> getCourts() async {
    final response = await _dio.get('/courts');
    return _asList(response.data['data'])
        .map((item) => Court.fromJson(item))
        .toList();
  }

  Future<List<CourtSlot>> getAvailability(int courtId, String date) async {
    final response = await _dio.get('/courts/$courtId/availability', queryParameters: {'date': date});
    return _asList(response.data['data'])
        .map((item) => CourtSlot.fromJson(item))
        .toList();
  }

  Future<List<CourtExtraService>> getServices() async {
    final response = await _dio.get('/court-services');
    return _asList(response.data['data'])
        .map((item) => CourtExtraService.fromJson(item))
        .toList();
  }

  Future<Map<String, dynamic>> lockSlot({
    required int courtId,
    required String date,
    required String startTime,
    required String endTime,
  }) async {
    final response = await _dio.post('/court-bookings/lock', data: {
      'court_id': courtId,
      'booking_date': date,
      'start_time': startTime,
      'end_time': endTime,
    });
    return Map<String, dynamic>.from(response.data['data'] ?? {});
  }

  Future<void> releaseLock(String lockToken) async {
    await _dio.post('/court-bookings/release-lock', data: {'lock_token': lockToken});
  }

  Future<CourtBooking> createBooking({
    required int courtId,
    required String date,
    required String startTime,
    required String endTime,
    required String paymentMethod,
    String? lockToken,
    Map<int, int> services = const {},
  }) async {
    final response = await _dio.post('/court-bookings', data: {
      'court_id': courtId,
      'booking_date': date,
      'start_time': startTime,
      'end_time': endTime,
      'payment_method': paymentMethod,
      if (lockToken != null && lockToken.isNotEmpty) 'lock_token': lockToken,
      'services': services.entries
          .where((entry) => entry.value > 0)
          .map((entry) => {'service_id': entry.key, 'quantity': entry.value})
          .toList(),
    });
    return CourtBooking.fromJson(Map<String, dynamic>.from(response.data['data']));
  }

  Future<List<CourtBooking>> getMyBookings() async {
    final response = await _dio.get('/court-bookings');
    return _asList(response.data['data'])
        .map((item) => CourtBooking.fromJson(item))
        .toList();
  }

  Future<CourtBooking> getBooking(int bookingId) async {
    final response = await _dio.get('/court-bookings/$bookingId');
    return CourtBooking.fromJson(Map<String, dynamic>.from(response.data['data']));
  }

  Future<void> cancelBooking(int bookingId, {String? reason}) async {
    await _dio.post('/court-bookings/$bookingId/cancel', data: {
      if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
    });
  }

  Future<Map<String, dynamic>> payBooking({
    required int bookingId,
    required String paymentMethod,
    required String paymentType,
    int? amount,
    String? note,
  }) async {
    final response = await _dio.post('/court-bookings/$bookingId/payments', data: {
      'payment_method': paymentMethod,
      'payment_type': paymentType,
      'amount': ?amount,
      if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
    });
    return Map<String, dynamic>.from(response.data['data'] ?? {});
  }

  Future<Map<String, dynamic>> getQrToken(int bookingId) async {
    final response = await _dio.get('/court-bookings/$bookingId/qr');
    return Map<String, dynamic>.from(response.data['data'] ?? {});
  }

  Future<List<CourtBooking>> getStaffBookings({
    required String date,
    String? status,
    String? search,
  }) async {
    final response = await _dio.get('/admin/court-bookings', queryParameters: {
      'date': date,
      'per_page': 80,
      if (status != null && status != 'all') 'status': status,
      if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
    });
    final data = response.data['data'];
    final items = data is Map && data['data'] is List ? data['data'] : data;
    return _asList(items).map((item) => CourtBooking.fromJson(item)).toList();
  }

  Future<void> confirmBooking(int bookingId) async {
    await _dio.post('/admin/court-bookings/$bookingId/confirm');
  }

  Future<void> staffCancelBooking(int bookingId, {String? reason}) async {
    await _dio.post('/admin/court-bookings/$bookingId/cancel', data: {
      if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
    });
  }

  Future<void> checkIn(int bookingId) async {
    await _dio.post('/admin/court-bookings/$bookingId/check-in');
  }

  Future<void> qrCheckIn(int bookingId, String qrToken) async {
    await _dio.post('/admin/court-bookings/$bookingId/qr-check-in', data: {'qr_token': qrToken});
  }

  Future<void> checkOut({
    required int bookingId,
    String? paymentMethod,
    String? note,
  }) async {
    await _dio.post('/admin/court-bookings/$bookingId/check-out', data: {
      'payment_method': ?paymentMethod,
      if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
    });
  }

  Future<void> recordStaffPayment({
    required int bookingId,
    required String paymentMethod,
    required String paymentType,
    int? amount,
    String? note,
  }) async {
    await _dio.post('/admin/court-bookings/$bookingId/payments', data: {
      'payment_method': paymentMethod,
      'payment_type': paymentType,
      'amount': ?amount,
      if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
    });
  }

  Future<void> addService({
    required int bookingId,
    required int serviceId,
    required int quantity,
    String? note,
  }) async {
    await _dio.post('/admin/court-bookings/$bookingId/services', data: {
      'service_id': serviceId,
      'quantity': quantity,
      if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
    });
  }

  Future<void> extendBooking(int bookingId, int minutes) async {
    await _dio.post('/admin/court-bookings/$bookingId/extend', data: {'extension_minutes': minutes});
  }

  String errorMessage(Object error) {
    if (error is DioException) {
      final data = error.response?.data;
      if (data is Map && data['message'] != null) return data['message'].toString();
      if (data is Map && data['errors'] is Map) {
        final errors = Map<String, dynamic>.from(data['errors']);
        if (errors.isNotEmpty) return (errors.values.first as List).first.toString();
      }
      return 'Khong the ket noi may chu (${error.response?.statusCode ?? 'network'}).';
    }
    return error.toString();
  }

  List<Map<String, dynamic>> _asList(dynamic value) {
    if (value is List) {
      return value.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList();
    }
    return [];
  }
}
