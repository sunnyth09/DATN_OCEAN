import 'api_client.dart';
import '../models/open_play_models.dart';

class OpenPlayService {
  final _dio = ApiClient().dio;

  List<dynamic> _asList(dynamic raw) {
    if (raw is List) return raw;
    if (raw is Map && raw['data'] is List) return raw['data'];
    return [];
  }

  Future<List<OpenPlayModel>> getOpenPlays({
    String? date,
    String? sportType,
    String? skillLevel,
    String? genderRule,
    String? matchType,
    bool? availableOnly,
    String? search,
    int page = 1,
  }) async {
    final query = <String, dynamic>{
      'page': page,
    };
    if (date != null && date.isNotEmpty) query['date'] = date;
    if (sportType != null && sportType != 'all') query['sport_type'] = sportType;
    if (skillLevel != null && skillLevel != 'all') query['skill_level'] = skillLevel;
    if (genderRule != null && genderRule != 'all') query['gender_rule'] = genderRule;
    if (matchType != null && matchType != 'all') query['match_type'] = matchType;
    if (availableOnly == true) query['available_only'] = true;
    if (search != null && search.isNotEmpty) query['search'] = search;

    final response = await _dio.get('/open-plays', queryParameters: query);
    final rawList = _asList(response.data['data']);
    return rawList.map((item) => OpenPlayModel.fromJson(item as Map<String, dynamic>)).toList();
  }

  Future<OpenPlayModel> getOpenPlayDetail(int id) async {
    final response = await _dio.get('/open-plays/$id');
    return OpenPlayModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }

  Future<List<dynamic>> getEligibleBookings() async {
    final response = await _dio.get('/open-plays/eligible-bookings');
    return _asList(response.data['data']);
  }

  Future<OpenPlayModel> createOpenPlay(Map<String, dynamic> data) async {
    final response = await _dio.post('/open-plays', data: data);
    return OpenPlayModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }

  Future<OpenPlayParticipantModel> joinOpenPlay(int id, {String? guestName, String? guestPhone}) async {
    final data = <String, dynamic>{};
    if (guestName != null) data['guest_name'] = guestName;
    if (guestPhone != null) data['guest_phone'] = guestPhone;

    final response = await _dio.post('/open-plays/$id/join', data: data);
    return OpenPlayParticipantModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }

  Future<Map<String, dynamic>> leaveOpenPlay(int id, {String? reason}) async {
    final data = <String, dynamic>{};
    if (reason != null) data['reason'] = reason;

    final response = await _dio.post('/open-plays/$id/leave', data: data);
    return response.data as Map<String, dynamic>;
  }

  Future<OpenPlayWaitlistModel> joinWaitlist(int id) async {
    final response = await _dio.post('/open-plays/$id/waitlist');
    return OpenPlayWaitlistModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }

  Future<void> leaveWaitlist(int id) async {
    await _dio.post('/open-plays/$id/waitlist/leave');
  }

  Future<OpenPlayParticipantModel> approveParticipant(int id, int participantId) async {
    final response = await _dio.post('/open-plays/$id/approve', data: {
      'participant_id': participantId,
    });
    return OpenPlayParticipantModel.fromJson(response.data['data'] as Map<String, dynamic>);
  }

  Future<void> rejectParticipant(int id, int participantId, {String? reason}) async {
    final data = <String, dynamic>{'participant_id': participantId};
    if (reason != null) data['reason'] = reason;

    await _dio.post('/open-plays/$id/reject', data: data);
  }

  Future<void> removeParticipant(int id, int participantId, {String? reason}) async {
    final data = <String, dynamic>{'participant_id': participantId};
    if (reason != null) data['reason'] = reason;

    await _dio.post('/open-plays/$id/remove-participant', data: data);
  }

  Future<void> closeRegistration(int id) async {
    await _dio.post('/open-plays/$id/close');
  }

  Future<void> cancelOpenPlay(int id, {String? reason}) async {
    final data = <String, dynamic>{};
    if (reason != null) data['reason'] = reason;

    await _dio.post('/open-plays/$id/cancel', data: data);
  }

  Future<Map<String, dynamic>> paySlot(int id, String paymentMethod) async {
    final response = await _dio.post('/open-plays/$id/pay', data: {
      'payment_method': paymentMethod,
    });
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getMyOpenPlays() async {
    final response = await _dio.get('/my-open-plays');
    return response.data['data'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> sendGuestOtp(String phone) async {
    final response = await _dio.post('/open-plays/guest/send-otp', data: {'phone': phone});
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> verifyGuestOtp(String phone, String otp, {String? fullName}) async {
    final data = <String, dynamic>{'phone': phone, 'otp': otp};
    if (fullName != null) data['full_name'] = fullName;

    final response = await _dio.post('/open-plays/guest/verify-otp', data: data);
    return response.data as Map<String, dynamic>;
  }
}
