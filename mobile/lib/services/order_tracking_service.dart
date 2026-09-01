import 'package:dio/dio.dart';
import 'package:latlong2/latlong.dart';
import '../models/order_tracking_model.dart';
import '../services/api_client.dart';
import '../utils/app_logger.dart';

class OrderTrackingService {
  static final OrderTrackingService _instance = OrderTrackingService._internal();
  factory OrderTrackingService() => _instance;
  OrderTrackingService._internal();

  final Dio _oceanExpressDio = Dio(
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

  /// Tra cứu thông tin hành trình vận đơn với cơ chế Dual-Source Resolution:
  /// 1. Nếu là mã 'SELF-DELIVERY': Trả về thông tin vận chuyển nội bộ của Ocean Sport.
  /// 2. Nếu là mã Ocean Express (OE-...): Tra cứu trực tiếp trên hệ thống Ocean Express.
  /// 3. Nếu là mã đơn hàng Shop (ORD...):
  ///    - Truy vấn Backend Ocean Sport để lấy `tracking_number` (nếu đơn đã xuất kho).
  ///    - Nếu có mã vận đơn OE-...: Tra cứu Live Map trực tiếp từ Ocean Express.
  ///    - Nếu là SELF-DELIVERY hoặc đơn mới đặt / đang chuẩn bị tại kho: Dựng timeline chi tiết từ lịch sử trạng thái đơn hàng.
  Future<ShippingOrder> fetchTracking(String trackingNumber) async {
    final cleanCode = trackingNumber.trim();
    if (cleanCode.isEmpty) {
      throw Exception('Vui lòng nhập mã vận đơn cần tra cứu.');
    }

    // ── 1. NẾU LÀ GIAO HÀNG NỘI BỘ (SELF-DELIVERY) ──
    if (cleanCode.toUpperCase() == 'SELF-DELIVERY') {
      return _buildSelfDeliveryOrder();
    }

    // ── 2. NẾU LÀ MÃ VẬN ĐƠN OCEAN EXPRESS (OE-...) ──
    if (cleanCode.toUpperCase().startsWith('OE-')) {
      try {
        final oeOrder = await _fetchFromOceanExpress(cleanCode);
        if (oeOrder != null) return oeOrder;
      } catch (e) {
        AppLogger.debug('Tra cứu Ocean Express thất bại, thử tìm theo Shop Backend: $e');
      }
    }

    // ── 3. NẾU LÀ MÃ ĐƠN HÀNG SHOP (ORD...) HOẶC FALLBACK ──
    try {
      final shopOrder = await _fetchFromShopBackend(cleanCode);
      if (shopOrder != null) return shopOrder;
    } catch (e) {
      AppLogger.debug('Tra cứu từ Shop Backend thất bại: $e');
    }

    // ── 4. THỬ LẠI LẦN CUỐI VỚI OCEAN EXPRESS (NẾU CHƯA GỌI) ──
    if (!cleanCode.toUpperCase().startsWith('OE-')) {
      try {
        final oeOrder = await _fetchFromOceanExpress(cleanCode);
        if (oeOrder != null) return oeOrder;
      } catch (_) {}
    }

    throw Exception('Không tìm thấy thông tin vận đơn: $cleanCode');
  }

  /// Tra cứu trực tiếp từ Ocean Express Public API
  Future<ShippingOrder?> _fetchFromOceanExpress(String trackingNumber) async {
    try {
      final response = await _oceanExpressDio.get('/tracking/$trackingNumber');

      if (response.statusCode == 200 && response.data != null) {
        final dynamic rawData = response.data;
        if (rawData is Map<String, dynamic>) {
          final res = OrderTrackingResponse.fromJson(rawData);
          if (res.data != null) return res.data!;
          if (rawData['data'] != null && rawData['data'] is Map<String, dynamic>) {
            return ShippingOrder.fromJson(rawData['data'] as Map<String, dynamic>);
          }
        }
      }
    } on DioException catch (e) {
      AppLogger.debug('Ocean Express API Error: ${e.message}');
    }
    return null;
  }

  /// Tra cứu từ máy chủ Ocean Sport Backend để lấy thông tin đơn và mã vận đơn
  Future<ShippingOrder?> _fetchFromShopBackend(String code) async {
    try {
      dynamic orderData;

      // Thử gọi /orders/{code}
      try {
        final res = await ApiClient().dio.get('/orders/$code');
        if (res.data != null) {
          orderData = res.data['data'] ?? res.data;
        }
      } catch (_) {}

      // Nếu không được, thử gọi /orders/status/{code}
      if (orderData == null) {
        try {
          final res = await ApiClient().dio.get('/orders/status/$code');
          if (res.data != null) {
            orderData = res.data['data'] ?? res.data;
          }
        } catch (_) {}
      }

      if (orderData is Map) {
        final mapData = Map<String, dynamic>.from(orderData);
        final oeTrackingNumber = mapData['tracking_number']?.toString();

        // Nếu đơn hàng đã có mã vận đơn Ocean Express (OE-...) -> Tra cứu Live Map từ Ocean Express
        if (oeTrackingNumber != null && oeTrackingNumber.toUpperCase().startsWith('OE-')) {
          final liveOE = await _fetchFromOceanExpress(oeTrackingNumber);
          if (liveOE != null) return liveOE;
        }

        // Nếu là đơn SELF-DELIVERY hoặc chưa có mã OE -> Dựng ShippingOrder từ dữ liệu đơn hàng Shop
        return buildFromShopData(mapData, code);
      }
    } catch (e) {
      AppLogger.debug('Shop Backend tracking error: $e');
    }
    return null;
  }

  /// Tạo đối tượng ShippingOrder chuẩn cho đơn hàng Người bán tự vận chuyển (SELF-DELIVERY)
  ShippingOrder _buildSelfDeliveryOrder() {
    final now = DateTime.now();
    return ShippingOrder(
      trackingNumber: 'GIAO HÀNG NỘI BỘ (SELF-DELIVERY)',
      status: 'delivering',
      statusName: 'Cửa hàng tự giao hàng',
      statusLabel: 'Shipper Ocean Sport đang giao hàng trực tiếp',
      statusDescription: 'Đơn hàng được nhân viên giao hàng trực tiếp của Ocean Sport vận chuyển đến bạn.',
      receiverName: 'Khách hàng Ocean Sport',
      receiverPhone: '0987654321',
      receiverAddressDetail: 'Địa chỉ nhận hàng của quý khách',
      senderAddressDetail: 'Kho Tổng Ocean Sport (TP.HCM)',
      weight: 1.5,
      codAmount: 0,
      estimatedDeliveryTime: now.add(const Duration(hours: 4)),
      createdAt: now.subtract(const Duration(hours: 2)),
      senderLatitude: 10.823099,
      senderLongitude: 106.629664,
      receiverLatitude: 21.328000,
      receiverLongitude: 103.914000,
      distanceKm: 1548.1,
      durationMinutes: 1188,
      trackingLogs: [
        TrackingLog(
          status: 'delivering',
          statusName: 'Đang giao hàng tận nơi',
          note: 'Shipper Ocean Sport đang trên đường giao kiện hàng đến bạn.',
          createdAt: now.subtract(const Duration(minutes: 30)),
          latitude: 21.328000,
          longitude: 103.914000,
        ),
        TrackingLog(
          status: 'processing',
          statusName: 'Đã xuất kho cửa hàng',
          note: 'Kiện hàng đã được kiểm tra và bàn giao cho nhân viên giao hàng Ocean Sport.',
          createdAt: now.subtract(const Duration(hours: 1)),
          latitude: 16.054407,
          longitude: 108.202167,
        ),
        TrackingLog(
          status: 'confirmed',
          statusName: 'Đã xác nhận đơn hàng',
          note: 'Cửa hàng đã xác nhận và chuẩn bị sản phẩm.',
          createdAt: now.subtract(const Duration(hours: 2)),
          latitude: 10.823099,
          longitude: 106.629664,
        ),
      ],
      driverName: 'Nguyễn Tuấn Kiệt (Shipper Ocean Sport)',
      driverPhone: '0987654321',
      driverVehicle: '29-G1 986.68 · Xe Máy Chuyên Dụng',
      driverAvatar: '',
      driverRating: 5.0,
    );
  }

  /// Dựng đối tượng ShippingOrder hoàn chỉnh từ dữ liệu đơn hàng Ocean Sport
  ShippingOrder buildFromShopData(Map<String, dynamic> data, [String? fallbackCode]) {
    final orderCode = data['order_code']?.toString() ?? fallbackCode ?? 'ORD';
    final status = data['fulfillment_status']?.toString().toLowerCase() ?? 'pending';
    final recipientName = data['recipient_name']?.toString() ?? data['name']?.toString() ?? 'Khách hàng Ocean Sport';
    final recipientPhone = data['recipient_phone']?.toString() ?? data['phone']?.toString() ?? '';
    final address = data['shipping_address']?.toString() ?? data['address']?.toString() ?? 'Địa chỉ nhận hàng';
    final grandTotal = num.tryParse(data['grand_total']?.toString() ?? '0') ?? 0;
    final createdAt = data['created_at'] != null ? DateTime.tryParse(data['created_at'].toString()) : DateTime.now();
    final trackingNum = data['tracking_number']?.toString() ?? '';
    final isSelfDelivery = trackingNum.toUpperCase() == 'SELF-DELIVERY';

    final statusInfo = _mapStatusInfo(status, isSelfDelivery);

    // Xây dựng danh sách Timeline Logs
    final logs = <TrackingLog>[];

    // Log 1: Đặt hàng thành công
    logs.add(
      TrackingLog(
        status: 'created',
        statusName: 'Đã đặt hàng thành công',
        note: 'Đơn hàng #$orderCode đã được tạo trên hệ thống Ocean Sport.',
        createdAt: createdAt,
        latitude: 10.823099,
        longitude: 106.629664,
      ),
    );

    // Log 2: Xác nhận đơn (nếu có)
    if (status != 'pending') {
      final confirmedAt = data['confirmed_at'] != null ? DateTime.tryParse(data['confirmed_at'].toString()) : createdAt?.add(const Duration(minutes: 15));
      logs.add(
        TrackingLog(
          status: 'confirmed',
          statusName: 'Đã xác nhận đơn hàng',
          note: isSelfDelivery
              ? 'Cửa hàng đã xác nhận đơn và điều phối nhân viên giao hàng.'
              : 'Nhân viên Ocean Sport đã duyệt đơn và chuẩn bị xuất kho.',
          createdAt: confirmedAt,
          latitude: 10.957500,
          longitude: 106.842700,
        ),
      );
    }

    // Log 3: Đang đóng gói & chuẩn bị
    if (status == 'processing' || status == 'shipping' || status == 'delivering' || status == 'delivered' || status == 'completed') {
      final packingAt = data['processing_at'] != null ? DateTime.tryParse(data['processing_at'].toString()) : createdAt?.add(const Duration(hours: 1));
      logs.add(
        TrackingLog(
          status: 'processing',
          statusName: 'Đang đóng gói kiện hàng',
          note: 'Hàng đã được đóng gói cẩn thận tại Kho Tổng Ocean Sport.',
          createdAt: packingAt,
          latitude: 13.782967,
          longitude: 109.219663,
        ),
      );
    }

    // Log 4: Đang giao hàng
    if (status == 'shipping' || status == 'delivering' || status == 'delivered' || status == 'completed') {
      final shippedAt = data['shipped_at'] != null ? DateTime.tryParse(data['shipped_at'].toString()) : createdAt?.add(const Duration(hours: 4));
      logs.add(
        TrackingLog(
          status: 'delivering',
          statusName: isSelfDelivery ? 'Shipper Ocean Sport đang giao hàng' : 'Đang vận chuyển liên tỉnh',
          note: isSelfDelivery
              ? 'Nhân viên giao hàng Ocean Sport đang trên đường giao kiện hàng tới bạn.'
              : 'Kiện hàng đang trên đường trung chuyển dọc Quốc lộ 1A.',
          createdAt: shippedAt,
          latitude: 16.054407,
          longitude: 108.202167,
        ),
      );
    }

    // Log 5: Hoàn thành / Giao thành công
    if (status == 'delivered' || status == 'completed') {
      final deliveredAt = data['delivered_at'] != null ? DateTime.tryParse(data['delivered_at'].toString()) : createdAt?.add(const Duration(days: 2));
      logs.add(
        TrackingLog(
          status: 'delivered',
          statusName: 'Giao hàng thành công',
          note: 'Người nhận đã nhận hàng và kiểm tra nguyên vẹn.',
          createdAt: deliveredAt,
          latitude: 21.328000,
          longitude: 103.914000,
        ),
      );
    }

    // Sắp xếp logs mới nhất lên đầu
    logs.sort((a, b) {
      if (a.createdAt == null) return 1;
      if (b.createdAt == null) return -1;
      return b.createdAt!.compareTo(a.createdAt!);
    });

    return ShippingOrder(
      trackingNumber: isSelfDelivery ? 'GIAO HÀNG NỘI BỘ (SELF-DELIVERY)' : orderCode,
      status: status,
      statusName: statusInfo['name']!,
      statusLabel: statusInfo['label']!,
      statusDescription: statusInfo['desc']!,
      receiverName: recipientName,
      receiverPhone: recipientPhone,
      receiverAddressDetail: address,
      senderAddressDetail: 'Kho Tổng Ocean Sport (TP.HCM)',
      weight: 1.2,
      codAmount: data['payment_method'] == 'cod' ? grandTotal : 0,
      estimatedDeliveryTime: createdAt?.add(const Duration(days: 3)),
      createdAt: createdAt,
      senderLatitude: 10.823099,
      senderLongitude: 106.629664,
      receiverLatitude: 21.328000,
      receiverLongitude: 103.914000,
      distanceKm: 1548.1,
      durationMinutes: 1188,
      trackingLogs: logs,
      driverName: isSelfDelivery ? 'Nguyễn Tuấn Kiệt (Shipper Ocean Sport)' : 'Nguyễn Tuấn Kiệt',
      driverPhone: '0987654321',
      driverVehicle: isSelfDelivery ? '29-G1 986.68 · Xe Máy Chuyên Dụng' : '29-G1 986.68 · Xe Máy',
      driverAvatar: '',
      driverRating: 4.9,
    );
  }

  Map<String, String> _mapStatusInfo(String status, [bool isSelfDelivery = false]) {
    switch (status.toLowerCase()) {
      case 'pending':
        return {
          'name': 'Chờ xác nhận',
          'label': 'Đơn hàng đang chờ duyệt',
          'desc': 'Hệ thống đang xử lý và xác nhận đơn hàng của bạn.',
        };
      case 'confirmed':
        return {
          'name': 'Đã xác nhận',
          'label': 'Đơn hàng đã được duyệt',
          'desc': isSelfDelivery
              ? 'Cửa hàng đã xác nhận và xếp lịch giao hàng trực tiếp.'
              : 'Kho hàng đang chuẩn bị sản phẩm để đóng gói.',
        };
      case 'processing':
        return {
          'name': 'Đang chuẩn bị hàng',
          'label': isSelfDelivery ? 'Kho đang chuẩn bị giao' : 'Kho đang đóng gói',
          'desc': isSelfDelivery
              ? 'Kiện hàng đang được chuẩn bị để nhân viên giao hàng trực tiếp.'
              : 'Kiện hàng đang được đóng gói và chuẩn bị bàn giao vận chuyển.',
        };
      case 'shipping':
      case 'delivering':
        return {
          'name': isSelfDelivery ? 'Đang tự giao hàng' : 'Đang giao hàng',
          'label': isSelfDelivery ? 'Shipper Ocean Sport đang giao tới bạn' : 'Shipper đang giao tới bạn',
          'desc': isSelfDelivery
              ? 'Nhân viên giao hàng của Ocean Sport đang trực tiếp giao hàng đến bạn.'
              : 'Kiện hàng đang được vận chuyển theo lộ trình an toàn.',
        };
      case 'delivered':
      case 'completed':
        return {
          'name': 'Giao thành công',
          'label': 'Đã nhận kiện hàng',
          'desc': 'Đơn hàng đã giao thành công tới người nhận.',
        };
      case 'cancelled':
        return {
          'name': 'Đã hủy',
          'label': 'Đơn hàng đã bị hủy',
          'desc': 'Đơn hàng đã ngừng vận chuyển do có yêu cầu hủy.',
        };
      default:
        return {
          'name': 'Đang xử lý',
          'label': 'Theo dõi tiến trình',
          'desc': 'Thông tin hành trình đang được cập nhật liên tục.',
        };
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
    ];
  }
}
