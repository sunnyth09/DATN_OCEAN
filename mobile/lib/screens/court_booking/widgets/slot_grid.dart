import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../config/app_theme.dart';
import '../../../models/court_booking_models.dart';

/// Lưới khung giờ thông minh: Tự động nhận diện buổi theo thời gian thực (Real-time Session Tabs)
/// và hiển thị dạng 3 cột siêu gọn gàng.
class SlotGrid extends StatefulWidget {
  final List<CourtSlot> slots;
  final Set<int> selectedIndexes;
  final NumberFormat money;
  final ValueChanged<int> onToggle;
  final DateTime? selectedDate;

  const SlotGrid({
    super.key,
    required this.slots,
    required this.selectedIndexes,
    required this.money,
    required this.onToggle,
    this.selectedDate,
  });

  @override
  State<SlotGrid> createState() => _SlotGridState();
}

class _SlotGridState extends State<SlotGrid> {
  late String _activeSession;
  DateTime? _lastDate;

  bool get _isToday {
    final d = widget.selectedDate ?? DateTime.now();
    final now = DateTime.now();
    return d.year == now.year && d.month == now.month && d.day == now.day;
  }

  String _getRealtimeDefaultSession() {
    if (!_isToday) return 'all';
    final currentHour = DateTime.now().hour;
    if (currentHour >= 17) return 'evening';
    if (currentHour >= 12) return 'afternoon';
    return 'morning';
  }

  @override
  void initState() {
    super.initState();
    _activeSession = _getRealtimeDefaultSession();
    _lastDate = widget.selectedDate;
  }

  @override
  void didUpdateWidget(covariant SlotGrid oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.selectedDate != _lastDate) {
      _lastDate = widget.selectedDate;
      setState(() {
        _activeSession = _getRealtimeDefaultSession();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final morningSlots = <MapEntry<int, CourtSlot>>[];
    final afternoonSlots = <MapEntry<int, CourtSlot>>[];
    final eveningSlots = <MapEntry<int, CourtSlot>>[];

    for (int i = 0; i < widget.slots.length; i++) {
      final slot = widget.slots[i];
      final entry = MapEntry(i, slot);
      if (slot.session == 'morning') {
        morningSlots.add(entry);
      } else if (slot.session == 'afternoon') {
        afternoonSlots.add(entry);
      } else {
        eveningSlots.add(entry);
      }
    }

    final displayedEntries = <MapEntry<int, CourtSlot>>[];
    if (_activeSession == 'morning') {
      displayedEntries.addAll(morningSlots);
    } else if (_activeSession == 'afternoon') {
      displayedEntries.addAll(afternoonSlots);
    } else if (_activeSession == 'evening') {
      displayedEntries.addAll(eveningSlots);
    } else {
      displayedEntries.addAll(widget.slots.asMap().entries);
    }

    final activeAvailableCount = displayedEntries.where((e) => e.value.isAvailable || e.value.isMyLock).length;

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── 1. Header: Title + Inline Status Legend ──
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  const Icon(Icons.schedule_rounded, size: 16, color: AppColors.primary),
                  const SizedBox(width: 6),
                  const Text(
                    'Chọn giờ',
                    style: TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF1E293B),
                    ),
                  ),
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF0FDF4),
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(color: const Color(0xFFBBF7D0)),
                    ),
                    child: Text(
                      'Còn $activeAvailableCount chỗ',
                      style: const TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF16A34A),
                      ),
                    ),
                  ),
                ],
              ),

              // Inline Compact Legend Dots
              Row(
                children: [
                  _legendDot(const Color(0xFF10B981), 'Trống'),
                  const SizedBox(width: 8),
                  _legendDot(AppColors.primary, 'Chọn'),
                  const SizedBox(width: 8),
                  _legendDot(const Color(0xFFF59E0B), 'Giữ'),
                ],
              ),
            ],
          ),

          const SizedBox(height: 12),

          // ── 2. Realtime Session Tabs ──
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                if (_isToday) ...[
                  // If today, highlight evening/active session first
                  _sessionTab(
                    id: 'evening',
                    icon: Icons.nightlight_round,
                    label: 'Tối (17h-23h)',
                    isRealtimeCurrent: DateTime.now().hour >= 17,
                    isPeak: true,
                  ),
                  const SizedBox(width: 8),
                  _sessionTab(
                    id: 'afternoon',
                    icon: Icons.wb_sunny_rounded,
                    label: 'Chiều (12h-17h)',
                    isRealtimeCurrent: DateTime.now().hour >= 12 && DateTime.now().hour < 17,
                  ),
                  const SizedBox(width: 8),
                  _sessionTab(
                    id: 'morning',
                    icon: Icons.wb_sunny_outlined,
                    label: 'Sáng (06h-12h)',
                    isRealtimeCurrent: DateTime.now().hour < 12,
                  ),
                  const SizedBox(width: 8),
                  _sessionTab(
                    id: 'all',
                    icon: Icons.grid_view_rounded,
                    label: 'Tất cả',
                  ),
                ] else ...[
                  // Future dates: normal chronological order
                  _sessionTab(
                    id: 'all',
                    icon: Icons.grid_view_rounded,
                    label: 'Tất cả buổi',
                  ),
                  const SizedBox(width: 8),
                  _sessionTab(
                    id: 'morning',
                    icon: Icons.wb_sunny_outlined,
                    label: 'Sáng (06h-12h)',
                  ),
                  const SizedBox(width: 8),
                  _sessionTab(
                    id: 'afternoon',
                    icon: Icons.wb_sunny_rounded,
                    label: 'Chiều (12h-17h)',
                  ),
                  const SizedBox(width: 8),
                  _sessionTab(
                    id: 'evening',
                    icon: Icons.nightlight_round,
                    label: 'Tối (17h-23h)',
                    isPeak: true,
                  ),
                ],
              ],
            ),
          ),

          const SizedBox(height: 14),

          // ── 3. 3-Column Compact Slots Grid ──
          if (displayedEntries.isEmpty)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 24),
              alignment: Alignment.center,
              child: const Text(
                'Không có khung giờ nào trong buổi này.',
                style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12.5),
              ),
            )
          else
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: displayedEntries.length,
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 3,
                childAspectRatio: 1.65,
                crossAxisSpacing: 8,
                mainAxisSpacing: 8,
              ),
              itemBuilder: (context, idx) {
                final entry = displayedEntries[idx];
                return _slotTile(context, entry.key, entry.value);
              },
            ),
        ],
      ),
    );
  }

  Widget _sessionTab({
    required String id,
    required IconData icon,
    required String label,
    bool isRealtimeCurrent = false,
    bool isPeak = false,
  }) {
    final isSelected = _activeSession == id;

    return GestureDetector(
      onTap: () => setState(() => _activeSession = id),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
        decoration: BoxDecoration(
          gradient: isSelected
              ? const LinearGradient(
                  colors: [Color(0xFFE63B6F), Color(0xFFFF6584)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                )
              : null,
          color: isSelected ? null : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: isSelected ? Colors.transparent : (isRealtimeCurrent ? const Color(0xFFFFB6C1) : const Color(0xFFE2E8F0)),
            width: isRealtimeCurrent && !isSelected ? 1.3 : 1,
          ),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.3),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ]
              : null,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 14,
              color: isSelected ? Colors.white : (isPeak ? const Color(0xFFE11D48) : const Color(0xFF64748B)),
            ),
            const SizedBox(width: 5),
            Text(
              label,
              style: TextStyle(
                fontSize: 11.5,
                fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                color: isSelected ? Colors.white : const Color(0xFF334155),
              ),
            ),
            if (isRealtimeCurrent && !isSelected) ...[
              const SizedBox(width: 5),
              Container(
                width: 6,
                height: 6,
                decoration: const BoxDecoration(
                  color: AppColors.primary,
                  shape: BoxShape.circle,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _legendDot(Color color, String text) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 7,
          height: 7,
          decoration: BoxDecoration(
            color: color,
            shape: BoxShape.circle,
          ),
        ),
        const SizedBox(width: 3.5),
        Text(
          text,
          style: const TextStyle(
            fontSize: 10.5,
            fontWeight: FontWeight.w600,
            color: Color(0xFF64748B),
          ),
        ),
      ],
    );
  }

  Widget _slotTile(BuildContext context, int index, CourtSlot slot) {
    final selected = widget.selectedIndexes.contains(index) || slot.isMyLock;
    final isLockedByOther = slot.isLocked && !slot.isMyLock;
    final isBooked = slot.isBooked;
    final isPast = slot.isPast;
    final enabled = slot.isAvailable || slot.isMyLock;

    Color bgColor;
    Color borderColor;
    Color timeColor;
    Color priceBadgeBg;
    Color priceBadgeText;
    String statusText;

    if (selected) {
      bgColor = AppColors.primary;
      borderColor = AppColors.primary;
      timeColor = Colors.white;
      priceBadgeBg = Colors.white.withValues(alpha: 0.22);
      priceBadgeText = Colors.white;
      statusText = slot.shortPrice;
    } else if (isLockedByOther) {
      bgColor = const Color(0xFFFFFBEB);
      borderColor = const Color(0xFFF59E0B);
      timeColor = const Color(0xFF92400E);
      priceBadgeBg = const Color(0xFFFEF3C7);
      priceBadgeText = const Color(0xFFB45309);
      statusText = 'Giữ chỗ';
    } else if (isBooked) {
      bgColor = const Color(0xFFF8FAFC);
      borderColor = const Color(0xFFE2E8F0);
      timeColor = const Color(0xFF94A3B8);
      priceBadgeBg = const Color(0xFFF1F5F9);
      priceBadgeText = const Color(0xFF94A3B8);
      statusText = 'Đã đặt';
    } else if (isPast) {
      bgColor = const Color(0xFFF8FAFC);
      borderColor = const Color(0xFFE2E8F0);
      timeColor = const Color(0xFFCBD5E1);
      priceBadgeBg = const Color(0xFFF1F5F9);
      priceBadgeText = const Color(0xFFCBD5E1);
      statusText = 'Đã qua';
    } else if (slot.isMaintenance || slot.isClosed) {
      bgColor = const Color(0xFFF1F5F9);
      borderColor = const Color(0xFFCBD5E1);
      timeColor = const Color(0xFF64748B);
      priceBadgeBg = const Color(0xFFE2E8F0);
      priceBadgeText = const Color(0xFF64748B);
      statusText = 'Bảo trì';
    } else {
      // Available
      bgColor = Colors.white;
      borderColor = const Color(0xFFE2E8F0);
      timeColor = const Color(0xFF1E293B);
      priceBadgeBg = const Color(0xFFFFF0F5);
      priceBadgeText = AppColors.primary;
      statusText = slot.shortPrice;
    }

    return InkWell(
      onTap: enabled
          ? () => widget.onToggle(index)
          : () {
              if (isLockedByOther) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Khung giờ này đang có khách giữ chỗ. Vui lòng chọn khung giờ khác hoặc đợi hết hạn!'),
                    backgroundColor: Color(0xFFD97706),
                    duration: Duration(seconds: 2),
                    behavior: SnackBarBehavior.floating,
                  ),
                );
              }
            },
      borderRadius: BorderRadius.circular(12),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 6),
        decoration: BoxDecoration(
          color: bgColor,
          gradient: selected
              ? const LinearGradient(
                  colors: [Color(0xFFE63B6F), Color(0xFFFF6584)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                )
              : null,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: borderColor, width: selected ? 1.5 : 1),
          boxShadow: selected
              ? [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.3),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ]
              : [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.02),
                    blurRadius: 3,
                    offset: const Offset(0, 1),
                  ),
                ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              '${slot.startTime}-${slot.endTime}',
              style: TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 11,
                color: timeColor,
                letterSpacing: -0.2,
              ),
            ),
            const SizedBox(height: 3),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1.5),
              decoration: BoxDecoration(
                color: priceBadgeBg,
                borderRadius: BorderRadius.circular(5),
              ),
              child: Text(
                statusText,
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  color: priceBadgeText,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
