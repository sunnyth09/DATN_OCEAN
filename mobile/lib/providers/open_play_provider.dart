import 'package:flutter/widgets.dart';
import '../models/open_play_models.dart';
import '../services/open_play_service.dart';
import '../services/realtime_service.dart';

class OpenPlayProvider extends ChangeNotifier {
  final _service = OpenPlayService();
  final _realtime = RealtimeService();

  List<OpenPlayModel> _matches = [];
  OpenPlayModel? _currentMatch;
  List<dynamic> _eligibleBookings = [];
  Map<String, dynamic> _myOpenPlays = {'hosted': [], 'joined': []};

  bool _isLoading = false;
  bool _isActionLoading = false;
  String? _errorMessage;

  List<OpenPlayModel> get matches => _matches;
  OpenPlayModel? get currentMatch => _currentMatch;
  List<dynamic> get eligibleBookings => _eligibleBookings;
  Map<String, dynamic> get myOpenPlays => _myOpenPlays;
  bool get isLoading => _isLoading;
  bool get isActionLoading => _isActionLoading;
  String? get errorMessage => _errorMessage;

  void subscribeGlobalChannel() {
    _realtime.subscribe('open-plays', 'OpenPlayCreated', (event, data) => fetchMatches());
    _realtime.subscribe('open-plays', 'ParticipantJoined', (event, data) => fetchMatches());
    _realtime.subscribe('open-plays', 'ParticipantLeft', (event, data) => fetchMatches());
    _realtime.subscribe('open-plays', 'OpenPlayCancelled', (event, data) => fetchMatches());
  }

  void unsubscribeGlobalChannel() {
    _realtime.unsubscribe('open-plays');
  }

  void subscribeMatchChannel(int matchId) {
    final channel = 'open-play.$matchId';
    _realtime.subscribe(channel, 'ParticipantJoined', (event, data) => fetchMatchDetail(matchId));
    _realtime.subscribe(channel, 'ParticipantLeft', (event, data) => fetchMatchDetail(matchId));
    _realtime.subscribe(channel, 'ParticipantApproved', (event, data) => fetchMatchDetail(matchId));
    _realtime.subscribe(channel, 'ParticipantRejected', (event, data) => fetchMatchDetail(matchId));
    _realtime.subscribe(channel, 'ParticipantCheckedIn', (event, data) => fetchMatchDetail(matchId));
    _realtime.subscribe(channel, 'PaymentUpdated', (event, data) => fetchMatchDetail(matchId));
    _realtime.subscribe(channel, 'OpenPlayUpdated', (event, data) => fetchMatchDetail(matchId));
    _realtime.subscribe(channel, 'OpenPlayCancelled', (event, data) => fetchMatchDetail(matchId));
  }

  void unsubscribeMatchChannel(int matchId) {
    _realtime.unsubscribe('open-play.$matchId');
  }

  Future<void> fetchMatches({
    String? date,
    String? sportType,
    String? skillLevel,
    String? genderRule,
    String? matchType,
    bool? availableOnly,
    String? search,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      _matches = await _service.getOpenPlays(
        date: date,
        sportType: sportType,
        skillLevel: skillLevel,
        genderRule: genderRule,
        matchType: matchType,
        availableOnly: availableOnly,
        search: search,
      );
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> fetchMatchDetail(int id) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      _currentMatch = await _service.getOpenPlayDetail(id);
    } catch (e) {
      _errorMessage = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> fetchEligibleBookings() async {
    _isLoading = true;
    notifyListeners();
    try {
      _eligibleBookings = await _service.getEligibleBookings();
    } catch (_) {
      _eligibleBookings = [];
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<OpenPlayModel> createMatch(Map<String, dynamic> data) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      final res = await _service.createOpenPlay(data);
      return res;
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  Future<OpenPlayParticipantModel> joinMatch(int matchId, {String? guestName, String? guestPhone}) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      final res = await _service.joinOpenPlay(matchId, guestName: guestName, guestPhone: guestPhone);
      await fetchMatchDetail(matchId);
      return res;
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>> leaveMatch(int matchId, {String? reason}) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      final res = await _service.leaveOpenPlay(matchId, reason: reason);
      await fetchMatchDetail(matchId);
      return res;
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  Future<OpenPlayWaitlistModel> joinWaitlist(int matchId) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      final res = await _service.joinWaitlist(matchId);
      await fetchMatchDetail(matchId);
      return res;
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  Future<void> leaveWaitlist(int matchId) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      await _service.leaveWaitlist(matchId);
      await fetchMatchDetail(matchId);
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  Future<void> approveParticipant(int matchId, int participantId) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      await _service.approveParticipant(matchId, participantId);
      await fetchMatchDetail(matchId);
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  Future<void> rejectParticipant(int matchId, int participantId, {String? reason}) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      await _service.rejectParticipant(matchId, participantId, reason: reason);
      await fetchMatchDetail(matchId);
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  Future<void> removeParticipant(int matchId, int participantId, {String? reason}) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      await _service.removeParticipant(matchId, participantId, reason: reason);
      await fetchMatchDetail(matchId);
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  Future<void> cancelMatch(int matchId, {String? reason}) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      await _service.cancelOpenPlay(matchId, reason: reason);
      await fetchMatchDetail(matchId);
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  Future<void> closeRegistration(int matchId) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      await _service.closeRegistration(matchId);
      await fetchMatchDetail(matchId);
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  Future<void> paySlot(int matchId, String method) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      await _service.paySlot(matchId, method);
      await fetchMatchDetail(matchId);
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  Future<void> fetchMyOpenPlays() async {
    _isLoading = true;
    notifyListeners();
    try {
      _myOpenPlays = await _service.getMyOpenPlays();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
