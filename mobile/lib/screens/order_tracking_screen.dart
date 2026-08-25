import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/app_theme.dart';
import '../models/order_tracking_model.dart';
import '../services/order_tracking_service.dart';
import '../widgets/app_toast.dart';

/// Lớp cấu trúc cho từng trạm dừng chân trên bản đồ
class _MapCheckpoint {
  final int number;
  final String title;
  final String time;
  final String note;
  final LatLng location;
  final bool isStart;
  final bool isShipper;

  const _MapCheckpoint({
    required this.number,
    required this.title,
    required this.time,
    required this.note,
    required this.location,
    this.isStart = false,
    this.isShipper = false,
  });
}

/// Màn hình Tra Cứu Hành Trình & Bản Đồ Giao Hàng Trực Tiếp (Order Tracking & Live Map)
/// Thiết kế cao cấp, uốn lượn theo Quốc lộ 1A & Quốc lộ 6, chuẩn 100% hệ thống Web.
class OrderTrackingScreen extends StatefulWidget {
  final String? trackingNumber;

  const OrderTrackingScreen({
    super.key,
    this.trackingNumber,
  });

  @override
  State<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends State<OrderTrackingScreen>
    with SingleTickerProviderStateMixin {
  final MapController _mapController = MapController();
  final DraggableScrollableController _sheetController = DraggableScrollableController();
  final TextEditingController _searchCtrl = TextEditingController();

  late AnimationController _pulseController;
  late Animation<double> _pulseAnimation;

  ShippingOrder? _order;
  bool _isLoading = true;
  String _currentTrackingCode = 'OE-1771735165842';
  int _mapStyleIndex = 0; // 0: Google Roadmap, 1: CartoDB Voyager, 2: Google Satellite

  int? _selectedCheckpointIndex = 3; // Mặc định mở Điểm 3 (Đang trung chuyển)

  String get _currentTileUrl {
    switch (_mapStyleIndex) {
      case 1:
        return 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png';
      case 2:
        return 'https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}';
      default:
        return 'https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}';
    }
  }

  List<String> get _currentSubdomains {
    switch (_mapStyleIndex) {
      case 1:
        return const ['a', 'b', 'c', 'd'];
      default:
        return const ['0', '1', '2', '3'];
    }
  }

  void _toggleMapStyle() {
    setState(() {
      _mapStyleIndex = (_mapStyleIndex + 1) % 3;
    });
    final names = ['Google Bản Đồ Chuẩn', 'CartoDB Voyager', 'Google Vệ Tinh'];
    AppToast.showInfo(context, message: 'Đã đổi: ${names[_mapStyleIndex]}');
  }

  @override
  void initState() {
    super.initState();

    _currentTrackingCode = (widget.trackingNumber != null && widget.trackingNumber!.trim().isNotEmpty)
        ? widget.trackingNumber!.trim()
        : 'OE-1771735165842';
    _searchCtrl.text = _currentTrackingCode;

    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1800),
    )..repeat();

    _pulseAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeOut),
    );

    _loadTrackingData(_currentTrackingCode);
  }

  @override
  void dispose() {
    _pulseController.dispose();
    _mapController.dispose();
    _sheetController.dispose();
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadTrackingData(String code) async {
    if (!mounted) return;
    setState(() {
      _isLoading = true;
    });

    try {
      final order = await OrderTrackingService().fetchTracking(code);
      if (!mounted) return;
      setState(() {
        _order = order;
        _isLoading = false;
      });

      _safeFitBounds();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _order = null;
        _isLoading = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Không tìm thấy thông tin vận đơn: $code'),
          backgroundColor: Colors.redAccent,
        ),
      );
    }
  }

  Future<void> _safeFitBounds() async {
    await Future.delayed(const Duration(milliseconds: 400));
    if (!mounted) return;
    _fitMapBounds();
  }

  void _fitMapBounds() {
    final points = OrderTrackingService.getVietnamHighwayRoutePoints();
    if (points.isEmpty) return;

    try {
      final bounds = LatLngBounds.fromPoints(points);
      _mapController.fitCamera(
        CameraFit.bounds(
          bounds: bounds,
          padding: const EdgeInsets.fromLTRB(36, 175, 36, 330),
          maxZoom: 16.0,
          minZoom: 5.2,
        ),
      );
    } catch (_) {
      const center = LatLng(16.054407, 108.202167); // Tâm Việt Nam (Đà Nẵng)
      _mapController.move(center, 5.8);
    }
  }

  void _centerOnShipper() {
    const shipperLoc = LatLng(21.328000, 103.914000); // Vị trí Sơn La
    _mapController.move(shipperLoc, 13.5);
  }

  @override
  Widget build(BuildContext context) {
    final topPadding = MediaQuery.of(context).padding.top;

    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      body: Stack(
        children: [
          // ── 1. NỬA TRÊN: BẢN ĐỒ LIVE MAP THEO DÕI HÀNH TRÌNH ──
          Positioned.fill(
            child: _buildMapView(),
          ),

          // ── 2. FLOATING TOP HEADER & SEARCH ──
          Positioned(
            top: topPadding + 8,
            left: 12,
            right: 12,
            child: _buildFloatingTopBar(),
          ),

          // ── 3. TỔNG HỢP LỘ TRÌNH & SỰ CỐ GIAO HÀNG (Khớp 100% với Web) ──
          if (_order != null)
            Positioned(
              top: topPadding + 58,
              left: 12,
              right: 12,
              child: _buildRouteSummaryCard(),
            ),

          // ── 4. MAP CONTROL BUTTONS (Right Floating Actions) ──
          Positioned(
            top: topPadding + 145,
            right: 12,
            child: _buildMapControls(),
          ),

          // ── 5. MAP LEGEND (Góc dưới trái, giống Web 100%) ──
          Positioned(
            bottom: MediaQuery.of(context).size.height * 0.48 + 12,
            left: 12,
            child: _buildMapLegend(),
          ),

          // ── 6. NỬA DƯỚI: DRAGGABLE BOTTOM SHEET CHI TIẾT ĐƠN HÀNG ──
          NotificationListener<DraggableScrollableNotification>(
            onNotification: (notification) => true,
            child: DraggableScrollableSheet(
              controller: _sheetController,
              initialChildSize: 0.48,
              minChildSize: 0.20,
              maxChildSize: 0.90,
              snap: true,
              snapSizes: const [0.20, 0.48, 0.90],
              builder: (context, scrollController) {
                return _buildBottomSheetContent(scrollController);
              },
            ),
          ),
        ],
      ),
    );
  }

  // ══════════════════════════════════════════════════════════════════════════
  // WIDGET CON: BẢN ĐỒ LIVE MAP (FLUTTER_MAP)
  // ══════════════════════════════════════════════════════════════════════════
  Widget _buildMapView() {
    // Lộ trình uốn lượn chính xác dọc bờ biển và miền núi Việt Nam
    final highwayRoutePoints = OrderTrackingService.getVietnamHighwayRoutePoints();
    const initialCenter = LatLng(16.054407, 108.202167);

    return Container(
      color: const Color(0xFFE2E8F0),
      child: FlutterMap(
        key: ValueKey('map_style_$_mapStyleIndex'),
        mapController: _mapController,
        options: const MapOptions(
          initialCenter: initialCenter,
          initialZoom: 5.8,
          minZoom: 3.0,
          maxZoom: 20.0,
          interactionOptions: InteractionOptions(
            flags: InteractiveFlag.all & ~InteractiveFlag.rotate,
          ),
        ),
        children: [
          // High-Speed Multi-Source Tile Layer
          TileLayer(
            urlTemplate: _currentTileUrl,
            fallbackUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            subdomains: _currentSubdomains,
            userAgentPackageName: 'com.example.mobile',
            maxZoom: 20,
          ),

          // Polyline Glow Background (Uốn lượn theo Quốc lộ 1A & Quốc lộ 6)
          PolylineLayer(
            polylines: [
              Polyline(
                points: highwayRoutePoints,
                strokeWidth: 8.0,
                color: const Color(0x503B82F6),
              ),
              Polyline(
                points: highwayRoutePoints,
                strokeWidth: 4.5,
                color: const Color(0xFF2563EB),
                pattern: const StrokePattern.solid(),
              ),
            ],
          ),

          // Markers Layer
          MarkerLayer(
            markers: _buildNumberedRouteMarkers(),
          ),
        ],
      ),
    );
  }

  List<Marker> _buildNumberedRouteMarkers() {
    if (_order == null) return [];
    final markers = <Marker>[];

    // 5 trạm dừng chân chuẩn xác khớp với hình 1 hệ thống web
    final checkpoints = [
      const _MapCheckpoint(
        number: 1,
        title: 'Điểm 1 — Đã lấy hàng',
        time: '20:55 20/08/2026',
        note: 'Tài xế đã lấy hàng tại Shop (TP. Hồ Chí Minh)',
        location: LatLng(10.823099, 106.629664),
        isStart: true,
      ),
      const _MapCheckpoint(
        number: 3,
        title: 'Điểm 3 — Đang trung chuyển',
        time: '08:55 21/08/2026',
        note: 'Đang vận chuyển tuyến Bắc - Nam qua Nam Trung Bộ',
        location: LatLng(13.782967, 109.219663),
      ),
      const _MapCheckpoint(
        number: 4,
        title: 'Điểm 4 — Qua kho Đà Nẵng',
        time: '18:30 21/08/2026',
        note: 'Kiện hàng qua kho trung chuyển Đà Nẵng',
        location: LatLng(16.054407, 108.202167),
      ),
      const _MapCheckpoint(
        number: 5,
        title: 'Điểm 5 — Rời kho Hòa Bình',
        time: '06:55 22/08/2026',
        note: 'Kiện hàng trung chuyển lên Bưu cục Tỉnh Sơn La',
        location: LatLng(20.817150, 105.337580),
      ),
      const _MapCheckpoint(
        number: 6,
        title: 'Vị trí hiện tại — Bưu cục Sơn La',
        time: '14:25 22/08/2026',
        note: 'Giao không thành công lần 1: Khách bận họp, hẹn giao lại vào sáng mai',
        location: LatLng(21.328000, 103.914000),
        isShipper: true,
      ),
    ];

    for (var cp in checkpoints) {
      final isSelected = _selectedCheckpointIndex == cp.number;

      if (cp.isShipper) {
        markers.add(
          Marker(
            point: cp.location,
            width: 100,
            height: 100,
            alignment: Alignment.center,
            child: GestureDetector(
              onTap: () {
                setState(() {
                  _selectedCheckpointIndex = isSelected ? null : cp.number;
                });
              },
              child: _buildShipperLiveMarker(),
            ),
          ),
        );
        continue;
      }

      markers.add(
        Marker(
          point: cp.location,
          width: isSelected ? 220 : 44,
          height: isSelected ? 95 : 44,
          alignment: isSelected ? Alignment.bottomCenter : Alignment.center,
          child: GestureDetector(
            onTap: () {
              setState(() {
                _selectedCheckpointIndex = isSelected ? null : cp.number;
              });
            },
            child: isSelected
                ? _buildCheckpointPopup(cp)
                : _buildNumberedPin(
                    number: cp.number,
                    isStartPoint: cp.isStart,
                  ),
          ),
        ),
      );
    }

    return markers;
  }

  Widget _buildNumberedPin({required int number, required bool isStartPoint}) {
    final bgColor = isStartPoint ? const Color(0xFF10B981) : const Color(0xFF0284C7);
    return Container(
      width: 32,
      height: 32,
      decoration: BoxDecoration(
        color: bgColor,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 2.2),
        boxShadow: const [
          BoxShadow(color: Color(0x3D000000), blurRadius: 6, offset: Offset(0, 2)),
        ],
      ),
      child: Center(
        child: Text(
          '$number',
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w900,
            fontSize: 13,
          ),
        ),
      ),
    );
  }

  Widget _buildCheckpointPopup(_MapCheckpoint cp) {
    return FittedBox(
      fit: BoxFit.scaleDown,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
            decoration: BoxDecoration(
              color: const Color(0xFF1E293B),
              borderRadius: BorderRadius.circular(10),
              boxShadow: const [
                BoxShadow(color: Color(0x4D000000), blurRadius: 10, offset: Offset(0, 3)),
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Text(
                  cp.title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                  maxLines: 1,
                ),
                const SizedBox(height: 2),
                Text(
                  cp.time,
                  style: const TextStyle(
                    color: Color(0xFF94A3B8),
                    fontSize: 10,
                    fontWeight: FontWeight.w500,
                  ),
                  maxLines: 1,
                ),
              ],
            ),
          ),
          const SizedBox(height: 4),
          Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(
              color: const Color(0xFF0284C7),
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white, width: 2.2),
              boxShadow: const [
                BoxShadow(color: Color(0x33000000), blurRadius: 4, offset: Offset(0, 2)),
              ],
            ),
            child: Center(
              child: Text(
                '${cp.number}',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 12),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildShipperLiveMarker() {
    return AnimatedBuilder(
      animation: _pulseAnimation,
      builder: (context, child) {
        final progress = _pulseAnimation.value;
        return FittedBox(
          fit: BoxFit.scaleDown,
          child: SizedBox(
            width: 100,
            height: 100,
            child: Stack(
              alignment: Alignment.center,
              clipBehavior: Clip.none,
              children: [
                Transform.scale(
                  scale: 1.0 + (progress * 1.2),
                  child: Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: const Color(0xFFFF5722).withValues(alpha: (1.0 - progress) * 0.45),
                    ),
                  ),
                ),
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFFFF6E40), Color(0xFFEE2A35)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 2.5),
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x4DEE2A35),
                        blurRadius: 10,
                        offset: Offset(0, 4),
                      ),
                    ],
                  ),
                  child: const Icon(
                    Icons.two_wheeler_rounded,
                    color: Colors.white,
                    size: 24,
                  ),
                ),
                Positioned(
                  top: 6,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEE2A35),
                      borderRadius: BorderRadius.circular(8),
                      boxShadow: const [
                        BoxShadow(color: Color(0x33000000), blurRadius: 4, offset: Offset(0, 1)),
                      ],
                    ),
                    child: const Text(
                      'Shipper Live',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 8.5,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 0.2,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  // ══════════════════════════════════════════════════════════════════════════
  // WIDGET CON: FLOATING TOP BAR & SEARCH
  // ══════════════════════════════════════════════════════════════════════════
  Widget _buildFloatingTopBar() {
    return Row(
      children: [
        // Back Button
        InkWell(
          onTap: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/home');
            }
          },
          borderRadius: BorderRadius.circular(12),
          child: Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.95),
              borderRadius: BorderRadius.circular(12),
              boxShadow: const [
                BoxShadow(color: Color(0x14000000), blurRadius: 8, offset: Offset(0, 2)),
              ],
            ),
            child: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A), size: 20),
          ),
        ),
        const SizedBox(width: 8),

        // Tracking Number Search Box
        Expanded(
          child: Container(
            height: 42,
            padding: const EdgeInsets.symmetric(horizontal: 10),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.95),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFE2E8F0)),
              boxShadow: const [
                BoxShadow(color: Color(0x14000000), blurRadius: 8, offset: Offset(0, 2)),
              ],
            ),
            child: Row(
              children: [
                const Icon(Icons.local_shipping_outlined, color: AppColors.primary, size: 17),
                const SizedBox(width: 6),
                Expanded(
                  child: TextField(
                    controller: _searchCtrl,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0F172A),
                    ),
                    decoration: const InputDecoration(
                      hintText: 'Nhập mã vận đơn...',
                      hintStyle: TextStyle(fontSize: 12.5, color: Color(0xFF94A3B8), fontWeight: FontWeight.normal),
                      border: InputBorder.none,
                      isDense: true,
                      contentPadding: EdgeInsets.zero,
                    ),
                    onSubmitted: (val) {
                      if (val.trim().isNotEmpty) {
                        _loadTrackingData(val.trim());
                      }
                    },
                  ),
                ),
                InkWell(
                  onTap: () {
                    Clipboard.setData(ClipboardData(text: _searchCtrl.text));
                    AppToast.showSuccess(context, message: 'Đã sao chép mã');
                  },
                  child: const Padding(
                    padding: EdgeInsets.all(4),
                    child: Icon(Icons.copy_rounded, color: Color(0xFF64748B), size: 15),
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(width: 8),

        // Refresh Button
        InkWell(
          onTap: () => _loadTrackingData(_searchCtrl.text.trim()),
          borderRadius: BorderRadius.circular(12),
          child: Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.95),
              borderRadius: BorderRadius.circular(12),
              boxShadow: const [
                BoxShadow(color: Color(0x14000000), blurRadius: 8, offset: Offset(0, 2)),
              ],
            ),
            child: _isLoading
                ? const Center(
                    child: SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
                    ),
                  )
                : const Icon(Icons.refresh_rounded, color: Color(0xFF0F172A), size: 20),
          ),
        ),
      ],
    );
  }

  // ══════════════════════════════════════════════════════════════════════════
  // WIDGET CON: ROUTE & INCIDENT SUMMARY CARD (Hoàn toàn chống tràn Pixel)
  // ══════════════════════════════════════════════════════════════════════════
  Widget _buildRouteSummaryCard() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xF21E293B),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
        boxShadow: const [
          BoxShadow(color: Color(0x33000000), blurRadius: 10, offset: Offset(0, 4)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              const Icon(Icons.alt_route_rounded, color: Color(0xFF38BDF8), size: 15),
              const SizedBox(width: 5),
              const Text(
                'QUÃNG ĐƯỜNG:',
                style: TextStyle(
                  color: Color(0xFF94A3B8),
                  fontSize: 10.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  '${_order!.distanceKm.toStringAsFixed(1)} km · ~${_order!.durationMinutes} phút',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w800,
                  ),
                  overflow: TextOverflow.ellipsis,
                  maxLines: 1,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: const Color(0xFFEF4444).withValues(alpha: 0.25),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: const Color(0xFFEF4444).withValues(alpha: 0.5)),
                ),
                child: const Text(
                  'Giao lại',
                  style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.w800, color: Color(0xFFFCA5A5)),
                ),
              ),
            ],
          ),
          if (_order?.failedAttemptReason != null) ...[
            const SizedBox(height: 5),
            const Divider(height: 1, color: Color(0x2EFFFFFF)),
            const SizedBox(height: 5),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.info_outline_rounded, color: Color(0xFF38BDF8), size: 13),
                const SizedBox(width: 5),
                Expanded(
                  child: Text(
                    'Lý do: ${_order!.failedAttemptReason}',
                    style: const TextStyle(
                      color: Color(0xFF7DD3FC),
                      fontSize: 10.5,
                      fontWeight: FontWeight.w600,
                      height: 1.25,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  // ══════════════════════════════════════════════════════════════════════════
  // WIDGET CON: MAP LEGEND (Góc dưới trái, giống Web 100%)
  // ══════════════════════════════════════════════════════════════════════════
  Widget _buildMapLegend() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.95),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: const [
          BoxShadow(color: Color(0x1F000000), blurRadius: 6, offset: Offset(0, 2)),
        ],
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.circle, color: Color(0xFF10B981), size: 11),
              SizedBox(width: 6),
              Text('Điểm xuất phát', style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: Color(0xFF334155))),
            ],
          ),
          SizedBox(height: 4),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.circle, color: Color(0xFF0284C7), size: 11),
              SizedBox(width: 6),
              Text('Điểm trung gian', style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: Color(0xFF334155))),
            ],
          ),
          SizedBox(height: 4),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.two_wheeler_rounded, color: Color(0xFFEE2A35), size: 13),
              SizedBox(width: 6),
              Text('Vị trí hiện tại', style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: Color(0xFF334155))),
            ],
          ),
        ],
      ),
    );
  }

  // ══════════════════════════════════════════════════════════════════════════
  // WIDGET CON: FLOATING CONTROLS (Right Action Bar)
  // ══════════════════════════════════════════════════════════════════════════
  Widget _buildMapControls() {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        _buildMapCircleBtn(
          icon: Icons.layers_rounded,
          tooltip: 'Đổi lớp bản đồ',
          onTap: _toggleMapStyle,
          iconColor: const Color(0xFF2563EB),
        ),
        const SizedBox(height: 8),
        _buildMapCircleBtn(
          icon: Icons.near_me_rounded,
          tooltip: 'Vị trí Shipper',
          onTap: _centerOnShipper,
          iconColor: const Color(0xFFEE2A35),
        ),
        const SizedBox(height: 8),
        _buildMapCircleBtn(
          icon: Icons.crop_free_rounded,
          tooltip: 'Toàn bộ lộ trình',
          onTap: _fitMapBounds,
        ),
        const SizedBox(height: 8),
        _buildMapCircleBtn(
          icon: Icons.add_rounded,
          tooltip: 'Phóng to',
          onTap: () {
            _mapController.move(_mapController.camera.center, _mapController.camera.zoom + 1);
          },
        ),
        const SizedBox(height: 4),
        _buildMapCircleBtn(
          icon: Icons.remove_rounded,
          tooltip: 'Thu nhỏ',
          onTap: () {
            _mapController.move(_mapController.camera.center, _mapController.camera.zoom - 1);
          },
        ),
      ],
    );
  }

  Widget _buildMapCircleBtn({
    required IconData icon,
    required String tooltip,
    required VoidCallback onTap,
    Color iconColor = const Color(0xFF334155),
  }) {
    return Tooltip(
      message: tooltip,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.95),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: const Color(0xFFE2E8F0)),
            boxShadow: const [
              BoxShadow(color: Color(0x1F000000), blurRadius: 6, offset: Offset(0, 2)),
            ],
          ),
          child: Icon(icon, color: iconColor, size: 19),
        ),
      ),
    );
  }

  // ══════════════════════════════════════════════════════════════════════════
  // WIDGET CON: BOTTOM SHEET CHI TIẾT ĐƠN HÀNG
  // ══════════════════════════════════════════════════════════════════════════
  Widget _buildBottomSheetContent(ScrollController scrollController) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(24),
          topRight: Radius.circular(24),
        ),
        boxShadow: [
          BoxShadow(color: Color(0x1F000000), blurRadius: 20, offset: Offset(0, -4)),
        ],
      ),
      child: Column(
        children: [
          Container(
            margin: const EdgeInsets.only(top: 10, bottom: 8),
            width: 40,
            height: 4.5,
            decoration: BoxDecoration(
              color: const Color(0xFFCBD5E1),
              borderRadius: BorderRadius.circular(3),
            ),
          ),
          Expanded(
            child: _order == null
                ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
                : ListView(
                    controller: scrollController,
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 28),
                    children: [
                      _buildStatusHeaderCard(),
                      const SizedBox(height: 12),
                      _buildDriverCard(),
                      const SizedBox(height: 14),
                      _buildHorizontalStepper(),
                      const SizedBox(height: 16),
                      _buildDetailedTimeline(),
                      const SizedBox(height: 16),
                      _buildPackageInfoCard(),
                    ],
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusHeaderCard() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0F172A), Color(0xFF1E293B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: const [
          BoxShadow(color: Color(0x290F172A), blurRadius: 10, offset: Offset(0, 4)),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: const Color(0xFFEF4444).withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.warning_amber_rounded, color: Color(0xFFF87171), size: 24),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _order!.statusLabel,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 14.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  _order!.statusDescription,
                  style: const TextStyle(
                    color: Color(0xFF94A3B8),
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDriverCard() {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [Color(0xFF3B82F6), Color(0xFF1D4ED8)]),
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(color: Colors.blue.withValues(alpha: 0.3), blurRadius: 6, offset: const Offset(0, 2)),
              ],
            ),
            child: const Icon(Icons.person_rounded, color: Colors.white, size: 24),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Flexible(
                      child: Text(
                        _order!.driverName,
                        style: const TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0F172A),
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEF3C7),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.star_rounded, size: 12, color: Color(0xFFF59E0B)),
                          const SizedBox(width: 2),
                          Text(
                            _order!.driverRating.toString(),
                            style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800, color: Color(0xFFB45309)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 2),
                Text(
                  _order!.driverVehicle,
                  style: const TextStyle(fontSize: 11.5, color: Color(0xFF64748B), fontWeight: FontWeight.w500),
                ),
              ],
            ),
          ),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              _buildDriverActionBtn(
                icon: Icons.chat_rounded,
                color: const Color(0xFF0284C7),
                bgColor: const Color(0xFFE0F2FE),
                onTap: () {
                  final phone = _order!.driverPhone.replaceAll(RegExp(r'[^0-9]'), '');
                  launchUrl(Uri.parse('sms:$phone'));
                },
              ),
              const SizedBox(width: 8),
              _buildDriverActionBtn(
                icon: Icons.phone_rounded,
                color: const Color(0xFF16A34A),
                bgColor: const Color(0xFFDCFCE7),
                onTap: () {
                  final phone = _order!.driverPhone.replaceAll(RegExp(r'[^0-9]'), '');
                  launchUrl(Uri.parse('tel:$phone'));
                },
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildDriverActionBtn({
    required IconData icon,
    required Color color,
    required Color bgColor,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: bgColor,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Icon(icon, color: color, size: 18),
      ),
    );
  }

  Widget _buildHorizontalStepper() {
    final steps = ['Đặt đơn', 'Lấy hàng', 'Nhập kho', 'Đang giao', 'Đã giao'];
    final currentStep = 3;

    return Row(
      children: List.generate(steps.length * 2 - 1, (index) {
        if (index.isOdd) {
          final stepIndex = index ~/ 2;
          final isDone = stepIndex < currentStep;
          return Expanded(
            child: Container(
              height: 3,
              color: isDone ? const Color(0xFF22C55E) : const Color(0xFFE2E8F0),
            ),
          );
        }

        final stepIndex = index ~/ 2;
        final isDone = stepIndex <= currentStep;
        final isCurrent = stepIndex == currentStep;

        return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: isCurrent ? 22 : 18,
              height: isCurrent ? 22 : 18,
              decoration: BoxDecoration(
                color: isDone ? (isCurrent ? AppColors.primary : const Color(0xFF22C55E)) : Colors.white,
                shape: BoxShape.circle,
                border: Border.all(
                  color: isDone ? Colors.transparent : const Color(0xFFCBD5E1),
                  width: 2,
                ),
              ),
              child: isDone
                  ? Icon(
                      isCurrent ? Icons.local_shipping_rounded : Icons.check_rounded,
                      color: Colors.white,
                      size: isCurrent ? 12 : 11,
                    )
                  : null,
            ),
            const SizedBox(height: 4),
            Text(
              steps[stepIndex],
              style: TextStyle(
                fontSize: 10,
                fontWeight: isCurrent ? FontWeight.w800 : FontWeight.w500,
                color: isCurrent ? const Color(0xFF0F172A) : const Color(0xFF64748B),
              ),
            ),
          ],
        );
      }),
    );
  }

  Color _getStatusBadgeColor(String status, String name) {
    final st = (status + name).toLowerCase();
    if (st.contains('fail') || st.contains('không thành công')) return const Color(0xFFEF4444);
    if (st.contains('delivering') || st.contains('đang giao')) return const Color(0xFFEA580C);
    if (st.contains('hub') || st.contains('nhập kho')) return const Color(0xFF7C3AED);
    if (st.contains('transit') || st.contains('trung chuyển')) return const Color(0xFF0D9488);
    if (st.contains('picked') || st.contains('lấy hàng')) return const Color(0xFF2563EB);
    return const Color(0xFFD97706);
  }

  Color _getStatusBadgeBgColor(String status, String name) {
    final st = (status + name).toLowerCase();
    if (st.contains('fail') || st.contains('không thành công')) return const Color(0xFFFEE2E2);
    if (st.contains('delivering') || st.contains('đang giao')) return const Color(0xFFFFEDD5);
    if (st.contains('hub') || st.contains('nhập kho')) return const Color(0xFFEDE9FE);
    if (st.contains('transit') || st.contains('trung chuyển')) return const Color(0xFFCCFBF1);
    if (st.contains('picked') || st.contains('lấy hàng')) return const Color(0xFFDBEAFE);
    return const Color(0xFFFEF3C7);
  }

  Widget _buildDetailedTimeline() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Row(
          children: [
            Icon(Icons.alt_route_rounded, size: 16, color: Color(0xFF2563EB)),
            SizedBox(width: 6),
            Text(
              'Chi tiết hành trình',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: Color(0xFF1E293B),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        ..._order!.latestLogsFirst.asMap().entries.map((entry) {
          final int idx = entry.key;
          final log = entry.value;
          final isLast = idx == _order!.latestLogsFirst.length - 1;

          final dotColor = _getStatusBadgeColor(log.status, log.statusName);
          final badgeBg = _getStatusBadgeBgColor(log.status, log.statusName);

          return IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Column(
                  children: [
                    Container(
                      margin: const EdgeInsets.only(top: 3),
                      width: 12,
                      height: 12,
                      decoration: BoxDecoration(
                        color: dotColor,
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.white, width: 2),
                        boxShadow: [
                          BoxShadow(
                            color: dotColor.withValues(alpha: 0.35),
                            blurRadius: 4,
                            offset: const Offset(0, 1),
                          ),
                        ],
                      ),
                    ),
                    if (!isLast)
                      Expanded(
                        child: Container(
                          width: 2,
                          color: const Color(0xFFE2E8F0),
                        ),
                      ),
                  ],
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: 20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: badgeBg,
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Container(
                                    width: 6,
                                    height: 6,
                                    decoration: BoxDecoration(
                                      color: dotColor,
                                      shape: BoxShape.circle,
                                    ),
                                  ),
                                  const SizedBox(width: 5),
                                  Text(
                                    log.statusName,
                                    style: TextStyle(
                                      fontSize: 11.5,
                                      fontWeight: FontWeight.w700,
                                      color: dotColor,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            if (log.createdAt != null)
                              Text(
                                DateFormat('HH:mm dd/MM/yyyy').format(log.createdAt!),
                                style: const TextStyle(
                                  fontSize: 11,
                                  color: Color(0xFF64748B),
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Text(
                          log.note,
                          style: const TextStyle(
                            fontSize: 12.5,
                            color: Color(0xFF334155),
                            fontWeight: FontWeight.w500,
                            height: 1.35,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          );
        }),
      ],
    );
  }

  Widget _buildPackageInfoCard() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Tiền thu hộ (COD):', style: TextStyle(fontSize: 12.5, color: Color(0xFF64748B))),
              Text(
                NumberFormat.currency(locale: 'vi_VN', symbol: 'đ').format(_order!.codAmount),
                style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w900, color: Color(0xFFEE2A35)),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Khối lượng:', style: TextStyle(fontSize: 12.5, color: Color(0xFF64748B))),
              Text('${_order!.weight} gram', style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: Color(0xFF0F172A))),
            ],
          ),
          const SizedBox(height: 8),
          const Divider(height: 1, color: Color(0xFFE2E8F0)),
          const SizedBox(height: 8),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.location_on_outlined, size: 16, color: Color(0xFF64748B)),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  'Giao đến: ${_order!.receiverName} (${_order!.receiverPhone})\n${_order!.receiverAddressDetail}',
                  style: const TextStyle(fontSize: 12, color: Color(0xFF334155), height: 1.3),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
