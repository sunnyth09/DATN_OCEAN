import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../../config/app_theme.dart';
import '../../../models/court_booking_models.dart';
import '../../../providers/auth_provider.dart';
import '../../../services/api_client.dart';
import '../../../widgets/app_toast.dart';

class CourtPlayerInviteSheet extends StatefulWidget {
  final CourtBooking booking;
  final VoidCallback? onUpdated;

  const CourtPlayerInviteSheet({
    super.key,
    required this.booking,
    this.onUpdated,
  });

  static Future<void> show(
    BuildContext context, {
    required CourtBooking booking,
    VoidCallback? onUpdated,
  }) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => CourtPlayerInviteSheet(
        booking: booking,
        onUpdated: onUpdated,
      ),
    );
  }

  @override
  State<CourtPlayerInviteSheet> createState() => _CourtPlayerInviteSheetState();
}

class _CourtPlayerInviteSheetState extends State<CourtPlayerInviteSheet>
    with SingleTickerProviderStateMixin {
  final TextEditingController _searchController = TextEditingController();
  final ApiClient _api = ApiClient();

  late TabController _tabController;
  bool _loading = false;
  bool _actionLoading = false;
  bool _searching = false;
  Timer? _searchDebounce;

  Map<String, dynamic>? _openPlayData;
  List<dynamic> _searchResults = [];
  final Set<int> _selectedUserIds = {};

  int _additionalSlots = 3;

  int get _maxCapacity => _additionalSlots + 1;

  int? get _currentUserId {
    final user = context.read<AuthProvider>().user;
    if (user == null) return null;
    return user.id;
  }

  bool get _isHost {
    final hostId = _openPlayData?['host_user_id'] ?? widget.booking.id;
    return hostId == _currentUserId;
  }

  List<dynamic> get _participants {
    if (_openPlayData == null || _openPlayData!['participants'] == null) return [];
    final list = _openPlayData!['participants'] as List;
    return list.where((p) {
      final status = p['status']?.toString();
      return ['confirmed', 'approved', 'host', 'checked_in', 'completed'].contains(status);
    }).toList();
  }

  bool get _isParticipant {
    if (_currentUserId == null) return false;
    return _participants.any((p) => p['user_id'] == _currentUserId);
  }

  int get _remainingSlots {
    final maxP = _openPlayData?['max_players'] ?? _maxCapacity;
    final currentP = _participants.length;
    return (maxP - (currentP > 0 ? currentP : 1)).clamp(0, 12);
  }

  bool get _isFull => _remainingSlots <= 0 || _openPlayData?['status'] == 'full';

  String get _shareLink {
    return 'https://oceansport.bcbdev.id.vn/profile/court-bookings?booking_id=${widget.booking.id}&open_play_id=${_openPlayData?['id'] ?? ''}';
  }

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _fetchCollaborationData();
  }

  @override
  void dispose() {
    _searchController.dispose();
    _searchDebounce?.cancel();
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _fetchCollaborationData() async {
    setState(() => _loading = true);
    try {
      final res = await _api.dio.get('/open-plays/by-booking/${widget.booking.id}');
      if (res.data?['data'] != null && mounted) {
        setState(() {
          _openPlayData = Map<String, dynamic>.from(res.data['data']);
          final maxP = _openPlayData!['max_players'] as int?;
          if (maxP != null && maxP > 1) {
            _additionalSlots = maxP - 1;
          }
        });
      }
    } catch (_) {
      // Not initialized yet
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _initOrUpdateCapacity() async {
    setState(() => _actionLoading = true);
    try {
      final res = await _api.dio.post(
        '/open-plays/init-for-booking/${widget.booking.id}',
        data: {
          'additional_slots': _additionalSlots,
          'max_players': _maxCapacity,
          'join_mode': 'auto',
        },
      );
      if (res.data?['data'] != null && mounted) {
        setState(() {
          _openPlayData = Map<String, dynamic>.from(res.data['data']);
        });
        AppToast.showSuccess(context, message: 'Đã lưu thiết lập người chơi!');
        widget.onUpdated?.call();
      }
    } catch (e) {
      if (mounted) {
        AppToast.showError(context, message: 'Không thể cập nhật cấu hình');
      }
    } finally {
      if (mounted) setState(() => _actionLoading = false);
    }
  }

  void _onSearchChanged(String query) {
    _searchDebounce?.cancel();
    if (query.trim().isEmpty) {
      setState(() => _searchResults = []);
      return;
    }
    _searchDebounce = Timer(const Duration(milliseconds: 300), () async {
      setState(() => _searching = true);
      try {
        final opId = _openPlayData?['id'];
        final queryParams = <String, dynamic>{'query': query.trim()};
        if (opId != null) queryParams['open_play_id'] = opId;
        final res = await _api.dio.get(
          '/open-plays/search-invitees',
          queryParameters: queryParams,
        );
        if (mounted) {
          setState(() {
            _searchResults = (res.data?['data'] as List?) ?? [];
          });
        }
      } catch (_) {
        if (mounted) setState(() => _searchResults = []);
      } finally {
        if (mounted) setState(() => _searching = false);
      }
    });
  }

  Future<void> _sendInvites() async {
    if (_selectedUserIds.isEmpty) return;
    setState(() => _actionLoading = true);
    try {
      if (_openPlayData?['id'] == null) {
        await _initOrUpdateCapacity();
      }
      final opId = _openPlayData?['id'];
      if (opId == null) throw Exception('Chưa khởi tạo trận');

      final res = await _api.dio.post(
        '/open-plays/$opId/invite-users',
        data: {'user_ids': _selectedUserIds.toList()},
      );

      if (mounted) {
        AppToast.showSuccess(
          context,
          message: res.data?['message'] ?? 'Đã gửi lời mời thành công!',
        );
        setState(() {
          _selectedUserIds.clear();
          _searchController.clear();
          _searchResults.clear();
        });
        await _fetchCollaborationData();
      }
    } catch (e) {
      if (mounted) {
        AppToast.showError(context, message: 'Không thể gửi lời mời');
      }
    } finally {
      if (mounted) setState(() => _actionLoading = false);
    }
  }

  Future<void> _joinMatch() async {
    final opId = _openPlayData?['id'];
    if (opId == null) return;
    setState(() => _actionLoading = true);
    try {
      final res = await _api.dio.post('/open-plays/$opId/join');
      if (mounted) {
        AppToast.showSuccess(
          context,
          message: res.data?['message'] ?? 'Tham gia trận chơi thành công! 🏸',
        );
        await _fetchCollaborationData();
        widget.onUpdated?.call();
      }
    } catch (e) {
      if (mounted) {
        AppToast.showError(context, message: 'Không thể tham gia trận');
      }
    } finally {
      if (mounted) setState(() => _actionLoading = false);
    }
  }

  Future<void> _leaveMatch() async {
    final opId = _openPlayData?['id'];
    if (opId == null) return;
    setState(() => _actionLoading = true);
    try {
      await _api.dio.post('/open-plays/$opId/leave');
      if (mounted) {
        AppToast.showSuccess(context, message: 'Đã rời khỏi trận chơi');
        await _fetchCollaborationData();
        widget.onUpdated?.call();
      }
    } catch (e) {
      if (mounted) {
        AppToast.showError(context, message: 'Không thể rời trận');
      }
    } finally {
      if (mounted) setState(() => _actionLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final courtName = widget.booking.courtName ?? 'Sân cầu lông';
    final dateStr = widget.booking.date;
    final timeStr = '${widget.booking.startTime} - ${widget.booking.endTime}';

    return Container(
      height: MediaQuery.of(context).size.height * 0.85,
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        children: [
          // Drag handle
          Center(
            child: Container(
              margin: const EdgeInsets.only(top: 12, bottom: 8),
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),

          // Header
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF0F5),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.people_alt_rounded, color: AppColors.primary, size: 22),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Rủ Bạn Bè Chơi Cùng',
                        style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
                      ),
                      Text(
                        '$courtName • $timeStr',
                        style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close, color: Colors.grey),
                ),
              ],
            ),
          ),

          // Capacity Summary Bar
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFFFFF1F2), Color(0xFFFDF2F8)],
              ),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFFCE7F3)),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '📅 $dateStr',
                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 12),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: _isFull ? AppColors.error : const Color(0xFF059669),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        _isFull ? Icons.lock : Icons.lock_open,
                        color: Colors.white,
                        size: 13,
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '${_participants.isNotEmpty ? _participants.length : 1} / ${_openPlayData?['max_players'] ?? _maxCapacity} người ($_remainingSlots trống)',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Tab Bar
          TabBar(
            controller: _tabController,
            labelColor: AppColors.primary,
            unselectedLabelColor: Colors.grey,
            indicatorColor: AppColors.primary,
            tabs: [
              Tab(text: _isHost ? 'Mời người chơi' : 'Thông tin trận'),
              Tab(text: 'Danh sách (${_participants.isNotEmpty ? _participants.length : 1})'),
            ],
          ),

          // Content
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
                : TabBarView(
                    controller: _tabController,
                    children: [
                      _buildInviteTab(),
                      _buildParticipantsTab(),
                    ],
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildInviteTab() {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (!_isHost && !_isParticipant) ...[
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: const Color(0xFFFFF1F2),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFFCE7F3)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(Icons.mail_outline_rounded, color: AppColors.primary),
                    SizedBox(width: 8),
                    Text(
                      'Lời mời tham gia giao lưu 🏸',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  'Bạn đã nhận được lời mời tham gia cùng trận này. Hãy tham gia ngay để giữ slot chơi nhé!',
                  style: TextStyle(color: Colors.grey.shade700, fontSize: 12.5),
                ),
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: _actionLoading || _isFull ? null : _joinMatch,
                    icon: const Icon(Icons.check_circle, size: 16),
                    label: Text(_isFull ? 'Đã đủ người chơi' : 'Tham gia ngay'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
        ],

        if (_isHost) ...[
          // Capacity configuration
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  '1. Số lượng người bạn muốn mời thêm:',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    IconButton(
                      onPressed: _additionalSlots > 1
                          ? () => setState(() => _additionalSlots--)
                          : null,
                      icon: const Icon(Icons.remove_circle_outline),
                      color: AppColors.primary,
                    ),
                    Text(
                      '$_additionalSlots người',
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 16,
                        color: AppColors.primary,
                      ),
                    ),
                    IconButton(
                      onPressed: _additionalSlots < 11
                          ? () => setState(() => _additionalSlots++)
                          : null,
                      icon: const Icon(Icons.add_circle_outline),
                      color: AppColors.primary,
                    ),
                    const Spacer(),
                    OutlinedButton(
                      onPressed: _actionLoading ? null : _initOrUpdateCapacity,
                      style: OutlinedButton.styleFrom(
                        side: const BorderSide(color: AppColors.primary),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                      ),
                      child: const Text('Lưu', style: TextStyle(color: AppColors.primary, fontSize: 12)),
                    ),
                  ],
                ),
                Text(
                  '(Host 1 người + $_additionalSlots người mời = Tổng $_maxCapacity người)',
                  style: TextStyle(color: Colors.grey.shade600, fontSize: 11),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Direct User Search
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'A. Tìm & Gửi Thông Báo Mời Bạn Bè',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _searchController,
                  onChanged: _onSearchChanged,
                  decoration: InputDecoration(
                    hintText: 'Nhập tên, SĐT hoặc Email...',
                    hintStyle: const TextStyle(fontSize: 12),
                    prefixIcon: const Icon(Icons.search, size: 18),
                    suffixIcon: _searching
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: Padding(
                              padding: EdgeInsets.all(12),
                              child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
                            ),
                          )
                        : null,
                    filled: true,
                    fillColor: const Color(0xFFF8FAFC),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
                    ),
                  ),
                ),
                if (_searchResults.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  ConstrainedBox(
                    constraints: const Box64Constraints(maxHeight: 180),
                    child: ListView.builder(
                      shrinkWrap: true,
                      itemCount: _searchResults.length,
                      itemBuilder: (ctx, i) {
                        final u = _searchResults[i];
                        final uid = u['user_id'] as int;
                        final isSelected = _selectedUserIds.contains(uid);
                        return CheckboxListTile(
                          dense: true,
                          value: isSelected,
                          onChanged: (val) {
                            setState(() {
                              if (val == true) {
                                _selectedUserIds.add(uid);
                              } else {
                                _selectedUserIds.remove(uid);
                              }
                            });
                          },
                          title: Text(u['full_name'] ?? 'User', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                          subtitle: Text(u['phone'] ?? u['email'] ?? '', style: const TextStyle(fontSize: 11)),
                          activeColor: AppColors.primary,
                        );
                      },
                    ),
                  ),
                ],
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: _selectedUserIds.isEmpty || _actionLoading ? null : _sendInvites,
                    icon: const Icon(Icons.send_rounded, size: 15),
                    label: Text('Gửi Lời Mời (${_selectedUserIds.length})'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Share Link Section
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'B. Sao Chép Liên Kết Mời Chơi',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFCBD5E1)),
                        ),
                        child: Text(
                          _shareLink,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 11, fontFamily: 'monospace'),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    ElevatedButton.icon(
                      onPressed: () async {
                        await Clipboard.setData(ClipboardData(text: _shareLink));
                        if (mounted) {
                          AppToast.showSuccess(context, message: 'Đã sao chép liên kết!');
                        }
                      },
                      icon: const Icon(Icons.copy, size: 14),
                      label: const Text('Chép link', style: TextStyle(fontSize: 12)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildParticipantsTab() {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Host card
        Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: const Color(0xFFFFF0F5),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFFFD1DC)),
          ),
          child: Row(
            children: [
              const CircleAvatar(
                backgroundColor: AppColors.primary,
                radius: 18,
                child: Icon(Icons.star, color: Colors.white, size: 16),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _openPlayData?['host']?['full_name'] ?? widget.booking.customerName ?? 'Host',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                    const Text('Chủ sân (Host)', style: TextStyle(color: AppColors.primary, fontSize: 11)),
                  ],
                ),
              ),
              const Icon(Icons.check_circle, color: Color(0xFF059669), size: 18),
            ],
          ),
        ),

        // Participants list
        ..._participants.where((p) => p['role'] != 'host').map((p) {
          final isMe = p['user_id'] == _currentUserId;
          return Container(
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  backgroundColor: Colors.grey.shade200,
                  radius: 16,
                  child: Text(
                    (p['user']?['full_name'] ?? p['guest_name'] ?? 'P').toString().substring(0, 1).toUpperCase(),
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.black87),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    '${p['user']?['full_name'] ?? p['guest_name'] ?? 'Người chơi'}${isMe ? ' (Bạn)' : ''}',
                    style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                  ),
                ),
                const Text('Đã tham gia', style: TextStyle(color: Color(0xFF059669), fontSize: 11, fontWeight: FontWeight.w600)),
              ],
            ),
          );
        }),

        // Empty slots placeholders
        for (int i = 0; i < _remainingSlots; i++)
          Container(
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFE2E8F0), style: BorderStyle.solid),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  backgroundColor: Colors.grey.shade100,
                  radius: 16,
                  child: Icon(Icons.add, color: Colors.grey.shade400, size: 16),
                ),
                const SizedBox(width: 12),
                Text(
                  'Slot trống #${i + 1}',
                  style: TextStyle(color: Colors.grey.shade500, fontSize: 12, fontStyle: FontStyle.italic),
                ),
              ],
            ),
          ),

        if (_isParticipant && !_isHost) ...[
          const SizedBox(height: 20),
          OutlinedButton.icon(
            onPressed: _actionLoading ? null : _leaveMatch,
            icon: const Icon(Icons.logout, color: AppColors.error, size: 16),
            label: const Text('Rời khỏi trận chơi', style: TextStyle(color: AppColors.error)),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: Color(0xFFFECACA)),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              padding: const EdgeInsets.symmetric(vertical: 12),
            ),
          ),
        ],
      ],
    );
  }
}

class Box64Constraints extends BoxConstraints {
  const Box64Constraints({super.maxHeight});
}
