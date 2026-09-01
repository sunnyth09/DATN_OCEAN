class OpenPlayModel {
  final int id;
  final String openPlayCode;
  final int bookingId;
  final int hostUserId;
  final String title;
  final String? description;
  final String sportType;
  final String skillLevel;
  final String genderRule;
  final String matchType;
  final int maxPlayers;
  final int currentPlayers;
  final String joinMode;
  final String paymentMode;
  final int slotPrice;
  final String status;
  final String? rules;
  final int availableSlots;
  final Map<String, dynamic>? booking;
  final Map<String, dynamic>? host;
  final List<OpenPlayParticipantModel> participants;
  final List<OpenPlayWaitlistModel> waitlists;
  final OpenPlayParticipantModel? myParticipation;
  final OpenPlayWaitlistModel? myWaitlist;

  OpenPlayModel({
    required this.id,
    required this.openPlayCode,
    required this.bookingId,
    required this.hostUserId,
    required this.title,
    this.description,
    required this.sportType,
    required this.skillLevel,
    required this.genderRule,
    required this.matchType,
    required this.maxPlayers,
    required this.currentPlayers,
    required this.joinMode,
    required this.paymentMode,
    required this.slotPrice,
    required this.status,
    this.rules,
    required this.availableSlots,
    this.booking,
    this.host,
    this.participants = const [],
    this.waitlists = const [],
    this.myParticipation,
    this.myWaitlist,
  });

  factory OpenPlayModel.fromJson(Map<String, dynamic> json) {
    var rawParticipants = json['participants'] ?? json['confirmed_participants'];
    List<OpenPlayParticipantModel> pList = [];
    if (rawParticipants is List) {
      pList = rawParticipants
          .map((i) => OpenPlayParticipantModel.fromJson(i as Map<String, dynamic>))
          .toList();
    }

    var rawWaitlists = json['waitlists'] ?? json['active_waitlists'];
    List<OpenPlayWaitlistModel> wList = [];
    if (rawWaitlists is List) {
      wList = rawWaitlists
          .map((i) => OpenPlayWaitlistModel.fromJson(i as Map<String, dynamic>))
          .toList();
    }

    OpenPlayParticipantModel? myP;
    if (json['my_participation'] != null && json['my_participation'] is Map) {
      myP = OpenPlayParticipantModel.fromJson(json['my_participation'] as Map<String, dynamic>);
    }

    OpenPlayWaitlistModel? myW;
    if (json['my_waitlist'] != null && json['my_waitlist'] is Map) {
      myW = OpenPlayWaitlistModel.fromJson(json['my_waitlist'] as Map<String, dynamic>);
    }

    int maxP = (json['max_players'] ?? 4) is int ? json['max_players'] : int.tryParse('${json['max_players']}') ?? 4;
    int currP = (json['current_players'] ?? 1) is int ? json['current_players'] : int.tryParse('${json['current_players']}') ?? 1;
    int avail = json['available_slots'] != null
        ? (json['available_slots'] is int ? json['available_slots'] : int.tryParse('${json['available_slots']}') ?? 0)
        : (maxP - currP).clamp(0, 99);

    return OpenPlayModel(
      id: json['id'] is int ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      openPlayCode: json['open_play_code']?.toString() ?? '',
      bookingId: json['booking_id'] is int ? json['booking_id'] : int.tryParse('${json['booking_id']}') ?? 0,
      hostUserId: json['host_user_id'] is int ? json['host_user_id'] : int.tryParse('${json['host_user_id']}') ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString(),
      sportType: json['sport_type']?.toString() ?? 'badminton',
      skillLevel: json['skill_level']?.toString() ?? 'all_levels',
      genderRule: json['gender_rule']?.toString() ?? 'any',
      matchType: json['match_type']?.toString() ?? 'doubles',
      maxPlayers: maxP,
      currentPlayers: currP,
      joinMode: json['join_mode']?.toString() ?? 'auto',
      paymentMode: json['payment_mode']?.toString() ?? 'host_pays',
      slotPrice: json['slot_price'] is int ? json['slot_price'] : int.tryParse('${json['slot_price']}') ?? 0,
      status: json['status']?.toString() ?? 'open',
      rules: json['rules']?.toString(),
      availableSlots: avail,
      booking: json['booking'] is Map ? (json['booking'] as Map<String, dynamic>) : null,
      host: json['host'] is Map ? (json['host'] as Map<String, dynamic>) : null,
      participants: pList,
      waitlists: wList,
      myParticipation: myP,
      myWaitlist: myW,
    );
  }
}

class OpenPlayParticipantModel {
  final int id;
  final int openPlayId;
  final int? userId;
  final String? guestName;
  final String? guestPhone;
  final String role;
  final String status;
  final String paymentStatus;
  final int paymentAmount;
  final String? paymentMethod;
  final String? checkInToken;
  final DateTime? joinedAt;
  final Map<String, dynamic>? user;

  OpenPlayParticipantModel({
    required this.id,
    required this.openPlayId,
    this.userId,
    this.guestName,
    this.guestPhone,
    required this.role,
    required this.status,
    required this.paymentStatus,
    required this.paymentAmount,
    this.paymentMethod,
    this.checkInToken,
    this.joinedAt,
    this.user,
  });

  factory OpenPlayParticipantModel.fromJson(Map<String, dynamic> json) {
    return OpenPlayParticipantModel(
      id: json['id'] is int ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      openPlayId: json['open_play_id'] is int ? json['open_play_id'] : int.tryParse('${json['open_play_id']}') ?? 0,
      userId: json['user_id'] != null ? (json['user_id'] is int ? json['user_id'] : int.tryParse('${json['user_id']}')) : null,
      guestName: json['guest_name']?.toString(),
      guestPhone: json['guest_phone']?.toString(),
      role: json['role']?.toString() ?? 'participant',
      status: json['status']?.toString() ?? 'confirmed',
      paymentStatus: json['payment_status']?.toString() ?? 'free',
      paymentAmount: json['payment_amount'] is int ? json['payment_amount'] : int.tryParse('${json['payment_amount']}') ?? 0,
      paymentMethod: json['payment_method']?.toString(),
      checkInToken: json['check_in_token']?.toString(),
      joinedAt: json['joined_at'] != null ? DateTime.tryParse(json['joined_at'].toString()) : null,
      user: json['user'] is Map ? (json['user'] as Map<String, dynamic>) : null,
    );
  }
}

class OpenPlayWaitlistModel {
  final int id;
  final int openPlayId;
  final int userId;
  final int position;
  final String status;
  final Map<String, dynamic>? user;

  OpenPlayWaitlistModel({
    required this.id,
    required this.openPlayId,
    required this.userId,
    required this.position,
    required this.status,
    this.user,
  });

  factory OpenPlayWaitlistModel.fromJson(Map<String, dynamic> json) {
    return OpenPlayWaitlistModel(
      id: json['id'] is int ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      openPlayId: json['open_play_id'] is int ? json['open_play_id'] : int.tryParse('${json['open_play_id']}') ?? 0,
      userId: json['user_id'] is int ? json['user_id'] : int.tryParse('${json['user_id']}') ?? 0,
      position: json['position'] is int ? json['position'] : int.tryParse('${json['position']}') ?? 1,
      status: json['status']?.toString() ?? 'waiting',
      user: json['user'] is Map ? (json['user'] as Map<String, dynamic>) : null,
    );
  }
}
