class Court {
  final int id;
  final String name;
  final String code;
  final String type;
  final String status;
  final String? description;
  final String? surface;
  final String? imageUrl;
  final int maxPlayers;

  Court({
    required this.id,
    required this.name,
    required this.code,
    required this.type,
    required this.status,
    this.description,
    this.surface,
    this.imageUrl,
    required this.maxPlayers,
  });

  factory Court.fromJson(Map<String, dynamic> json) {
    return Court(
      id: _toInt(json['court_id']),
      name: (json['court_name'] ?? 'San').toString(),
      code: (json['court_code'] ?? '').toString(),
      type: (json['type'] ?? 'standard').toString(),
      status: (json['status'] ?? 'active').toString(),
      description: json['description']?.toString(),
      surface: json['surface']?.toString(),
      imageUrl: json['image_url']?.toString(),
      maxPlayers: _toInt(json['max_players'], fallback: 4),
    );
  }
}

class CourtSlot {
  final String startTime;
  final String endTime;
  final int price;
  final String status;

  CourtSlot({
    required this.startTime,
    required this.endTime,
    required this.price,
    required this.status,
  });

  bool get isAvailable => status == 'available';

  factory CourtSlot.fromJson(Map<String, dynamic> json) {
    return CourtSlot(
      startTime: _shortTime(json['start_time']),
      endTime: _shortTime(json['end_time']),
      price: _toInt(json['price']),
      status: (json['status'] ?? 'available').toString(),
    );
  }
}

class CourtExtraService {
  final int id;
  final String name;
  final String code;
  final String unit;
  final int unitPrice;

  CourtExtraService({
    required this.id,
    required this.name,
    required this.code,
    required this.unit,
    required this.unitPrice,
  });

  factory CourtExtraService.fromJson(Map<String, dynamic> json) {
    return CourtExtraService(
      id: _toInt(json['service_id']),
      name: (json['service_name'] ?? 'Dich vu').toString(),
      code: (json['service_code'] ?? '').toString(),
      unit: (json['unit'] ?? 'piece').toString(),
      unitPrice: _toInt(json['unit_price']),
    );
  }
}

class CourtBooking {
  final int id;
  final String code;
  final int courtId;
  final String? courtName;
  final String date;
  final String startTime;
  final String endTime;
  final int durationMinutes;
  final String status;
  final String paymentStatus;
  final String? paymentMethod;
  final int totalAmount;
  final int paidAmount;
  final String? customerName;
  final String? customerPhone;
  final String? note;
  final List<CourtBookingPayment> payments;

  CourtBooking({
    required this.id,
    required this.code,
    required this.courtId,
    this.courtName,
    required this.date,
    required this.startTime,
    required this.endTime,
    required this.durationMinutes,
    required this.status,
    required this.paymentStatus,
    this.paymentMethod,
    required this.totalAmount,
    required this.paidAmount,
    this.customerName,
    this.customerPhone,
    this.note,
    this.payments = const [],
  });

  int get amountDue => totalAmount - paidAmount;

  factory CourtBooking.fromJson(Map<String, dynamic> json) {
    final court = json['court'] is Map ? Map<String, dynamic>.from(json['court']) : null;
    final payments = json['payments'] is List
        ? (json['payments'] as List)
            .whereType<Map>()
            .map((item) => CourtBookingPayment.fromJson(Map<String, dynamic>.from(item)))
            .toList()
        : <CourtBookingPayment>[];

    return CourtBooking(
      id: _toInt(json['booking_id']),
      code: (json['booking_code'] ?? '').toString(),
      courtId: _toInt(json['court_id']),
      courtName: court?['court_name']?.toString(),
      date: _dateOnly(json['booking_date']),
      startTime: _shortTime(json['start_time']),
      endTime: _shortTime(json['end_time']),
      durationMinutes: _toInt(json['duration_minutes']),
      status: (json['status'] ?? 'pending').toString(),
      paymentStatus: (json['payment_status'] ?? 'unpaid').toString(),
      paymentMethod: json['payment_method']?.toString(),
      totalAmount: _toInt(json['total_amount']),
      paidAmount: _toInt(json['paid_amount']),
      customerName: json['customer_name']?.toString(),
      customerPhone: json['customer_phone']?.toString(),
      note: json['note']?.toString(),
      payments: payments,
    );
  }
}

class CourtBookingPayment {
  final int id;
  final String paymentType;
  final String paymentMethod;
  final String status;
  final int amount;
  final String? transactionCode;

  CourtBookingPayment({
    required this.id,
    required this.paymentType,
    required this.paymentMethod,
    required this.status,
    required this.amount,
    this.transactionCode,
  });

  factory CourtBookingPayment.fromJson(Map<String, dynamic> json) {
    return CourtBookingPayment(
      id: _toInt(json['court_payment_id']),
      paymentType: (json['payment_type'] ?? 'full').toString(),
      paymentMethod: (json['payment_method'] ?? 'cash').toString(),
      status: (json['status'] ?? 'pending').toString(),
      amount: _toInt(json['amount']),
      transactionCode: json['transaction_code']?.toString(),
    );
  }
}

int _toInt(dynamic value, {int fallback = 0}) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

String _shortTime(dynamic value) {
  final raw = value?.toString() ?? '';
  if (raw.length >= 5) return raw.substring(0, 5);
  return raw;
}

String _dateOnly(dynamic value) {
  final raw = value?.toString() ?? '';
  if (raw.length >= 10) return raw.substring(0, 10);
  return raw;
}
