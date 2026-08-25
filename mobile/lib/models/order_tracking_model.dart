import 'package:latlong2/latlong.dart';

class OrderTrackingResponse {
  final bool success;
  final String? message;
  final ShippingOrder? data;

  OrderTrackingResponse({
    required this.success,
    this.message,
    this.data,
  });

  factory OrderTrackingResponse.fromJson(Map<String, dynamic> json) {
    return OrderTrackingResponse(
      success: json['success'] == true || json['status'] == 'success',
      message: json['message']?.toString(),
      data: json['data'] != null ? ShippingOrder.fromJson(json['data'] as Map<String, dynamic>) : null,
    );
  }
}

class ShippingOrder {
  final String trackingNumber;
  final String status;
  final String statusName;
  final String statusLabel;
  final String statusDescription;
  final String receiverName;
  final String receiverPhone;
  final String receiverAddressDetail;
  final String senderAddressDetail;
  final num weight;
  final num codAmount;
  final DateTime? estimatedDeliveryTime;
  final DateTime? createdAt;
  final double? senderLatitude;
  final double? senderLongitude;
  final double? receiverLatitude;
  final double? receiverLongitude;
  final List<TrackingLog> trackingLogs;

  // Extra route metrics & current incident
  final double distanceKm;
  final int durationMinutes;
  final String? failedAttemptReason;
  final String? currentLocationName;

  // Driver/Shipper info (nullable or mockable)
  final String driverName;
  final String driverPhone;
  final String driverVehicle;
  final String driverAvatar;
  final double driverRating;

  ShippingOrder({
    required this.trackingNumber,
    required this.status,
    required this.statusName,
    required this.statusLabel,
    required this.statusDescription,
    required this.receiverName,
    required this.receiverPhone,
    required this.receiverAddressDetail,
    required this.senderAddressDetail,
    required this.weight,
    required this.codAmount,
    this.estimatedDeliveryTime,
    this.createdAt,
    this.senderLatitude,
    this.senderLongitude,
    this.receiverLatitude,
    this.receiverLongitude,
    required this.trackingLogs,
    this.distanceKm = 1562.8,
    this.durationMinutes = 1188,
    this.failedAttemptReason,
    this.currentLocationName,
    this.driverName = 'Nguyễn Tuấn Kiệt',
    this.driverPhone = '0987654321',
    this.driverVehicle = '29-G1 986.68 · Xe Máy',
    this.driverAvatar = '',
    this.driverRating = 4.9,
  });

  factory ShippingOrder.fromJson(Map<String, dynamic> json) {
    var logs = <TrackingLog>[];
    if (json['tracking_logs'] is List) {
      logs = (json['tracking_logs'] as List)
          .map((item) => TrackingLog.fromJson(item as Map<String, dynamic>))
          .toList();
    }

    // Sort logs descending by created_at
    logs.sort((a, b) {
      if (a.createdAt == null) return 1;
      if (b.createdAt == null) return -1;
      return b.createdAt!.compareTo(a.createdAt!);
    });

    final driverData = json['shipper'] ?? json['driver'];
    final driverName = driverData is Map ? (driverData['name'] ?? driverData['full_name'] ?? 'Nguyễn Tuấn Kiệt') : 'Nguyễn Tuấn Kiệt';
    final driverPhone = driverData is Map ? (driverData['phone'] ?? '0987654321') : (json['receiver_phone'] ?? '0987654321');
    final driverVehicle = driverData is Map ? (driverData['vehicle'] ?? '29-G1 986.68 · Xe Máy') : '29-G1 986.68 · Xe Máy';
    final driverAvatar = driverData is Map ? (driverData['avatar_url'] ?? '') : '';
    final driverRating = driverData is Map ? (double.tryParse(driverData['rating']?.toString() ?? '4.9') ?? 4.9) : 4.9;

    return ShippingOrder(
      trackingNumber: json['tracking_number']?.toString() ?? '',
      status: json['status']?.toString().toLowerCase() ?? 'delivering',
      statusName: json['status_name']?.toString() ?? 'Đang giao hàng',
      statusLabel: json['status_label']?.toString() ?? 'Shipper đang giao tới bạn',
      statusDescription: json['status_description']?.toString() ?? 'Kiện hàng đang trên đường giao đến bạn',
      receiverName: json['receiver_name']?.toString() ?? 'Khách hàng',
      receiverPhone: json['receiver_phone']?.toString() ?? '',
      receiverAddressDetail: json['receiver_address_detail']?.toString() ?? 'Địa chỉ nhận hàng',
      senderAddressDetail: json['sender_address_detail']?.toString() ?? 'Kho Tổng Ocean Express',
      weight: num.tryParse(json['weight']?.toString() ?? '0') ?? 0,
      codAmount: num.tryParse(json['cod_amount']?.toString() ?? '0') ?? 0,
      estimatedDeliveryTime: json['estimated_delivery_time'] != null
          ? DateTime.tryParse(json['estimated_delivery_time'].toString())
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
      senderLatitude: double.tryParse(json['sender_latitude']?.toString() ?? ''),
      senderLongitude: double.tryParse(json['sender_longitude']?.toString() ?? ''),
      receiverLatitude: double.tryParse(json['receiver_latitude']?.toString() ?? ''),
      receiverLongitude: double.tryParse(json['receiver_longitude']?.toString() ?? ''),
      distanceKm: double.tryParse(json['distance_km']?.toString() ?? '') ?? 1562.8,
      durationMinutes: int.tryParse(json['duration_minutes']?.toString() ?? '') ?? 1188,
      failedAttemptReason: json['failed_reason']?.toString() ?? json['reason']?.toString(),
      currentLocationName: json['current_location_name']?.toString(),
      trackingLogs: logs,
      driverName: driverName.toString(),
      driverPhone: driverPhone.toString(),
      driverVehicle: driverVehicle.toString(),
      driverAvatar: driverAvatar.toString(),
      driverRating: driverRating,
    );
  }

  /// Get Current Shipper coordinate (from latest log with coordinates or fallback)
  LatLng? get currentShipperLocation {
    for (var log in trackingLogs) {
      if (log.latitude != null && log.longitude != null) {
        return LatLng(log.latitude!, log.longitude!);
      }
    }
    if (senderLatitude != null && senderLongitude != null && receiverLatitude != null && receiverLongitude != null) {
      // Midpoint fallback
      return LatLng(
        (senderLatitude! + receiverLatitude!) / 2,
        (senderLongitude! + receiverLongitude!) / 2,
      );
    }
    if (senderLatitude != null && senderLongitude != null) {
      return LatLng(senderLatitude!, senderLongitude!);
    }
    return null;
  }

  /// Get sender LatLng
  LatLng? get senderLocation {
    if (senderLatitude != null && senderLongitude != null) {
      return LatLng(senderLatitude!, senderLongitude!);
    }
    return null;
  }

  /// Get receiver LatLng
  LatLng? get receiverLocation {
    if (receiverLatitude != null && receiverLongitude != null) {
      return LatLng(receiverLatitude!, receiverLongitude!);
    }
    return null;
  }

  /// Danh sách log theo trình tự thời gian từ cũ nhất đến mới nhất (Điểm 1 -> Điểm 6 -> Vị trí hiện tại)
  List<TrackingLog> get chronologicalLogs {
    final list = List<TrackingLog>.from(trackingLogs);
    list.sort((a, b) {
      if (a.createdAt == null) return -1;
      if (b.createdAt == null) return 1;
      return a.createdAt!.compareTo(b.createdAt!);
    });
    return list;
  }

  /// Danh sách log theo thứ tự mới nhất hiển thị trên cùng
  List<TrackingLog> get latestLogsFirst {
    final list = List<TrackingLog>.from(trackingLogs);
    list.sort((a, b) {
      if (a.createdAt == null) return 1;
      if (b.createdAt == null) return -1;
      return b.createdAt!.compareTo(a.createdAt!);
    });
    return list;
  }

  /// Masked phone number (e.g. 098****321)
  String get maskedReceiverPhone {
    final phone = receiverPhone.trim();
    if (phone.length <= 6) return phone;
    final start = phone.substring(0, 3);
    final end = phone.substring(phone.length - 3);
    return '$start****$end';
  }

  /// Stepper active index (0: Created, 1: Picked Up, 2: Hub, 3: Delivering, 4: Delivered)
  int get currentStepIndex {
    final st = status.toLowerCase();
    if (st.contains('delivered') || st.contains('completed') || st.contains('success')) {
      return 4;
    }
    if (st.contains('delivering') || st.contains('shipping') || st.contains('out_for_delivery')) {
      return 3;
    }
    if (st.contains('hub') || st.contains('warehouse') || st.contains('sorting') || st.contains('in_transit')) {
      return 2;
    }
    if (st.contains('picked') || st.contains('pickup') || st.contains('collected')) {
      return 1;
    }
    return 0;
  }
}

class TrackingLog {
  final String status;
  final String statusName;
  final String note;
  final double? latitude;
  final double? longitude;
  final DateTime? createdAt;

  TrackingLog({
    required this.status,
    required this.statusName,
    required this.note,
    this.latitude,
    this.longitude,
    this.createdAt,
  });

  factory TrackingLog.fromJson(Map<String, dynamic> json) {
    return TrackingLog(
      status: json['status']?.toString() ?? '',
      statusName: json['status_name']?.toString() ?? 'Cập nhật trạng thái',
      note: json['note']?.toString() ?? '',
      latitude: double.tryParse(json['latitude']?.toString() ?? ''),
      longitude: double.tryParse(json['longitude']?.toString() ?? ''),
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'].toString()) : null,
    );
  }

  LatLng? get location {
    if (latitude != null && longitude != null) {
      return LatLng(latitude!, longitude!);
    }
    return null;
  }
}
