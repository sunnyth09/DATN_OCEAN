import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../config/app_theme.dart';
import '../models/open_play_models.dart';
import '../providers/auth_provider.dart';
import '../providers/open_play_provider.dart';

class OpenPlayDetailScreen extends StatefulWidget {
  final int matchId;

  const OpenPlayDetailScreen({super.key, required this.matchId});

  @override
  State<OpenPlayDetailScreen> createState() => _OpenPlayDetailScreenState();
}

class _OpenPlayDetailScreenState extends State<OpenPlayDetailScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<OpenPlayProvider>();
      provider.fetchMatchDetail(widget.matchId);
      provider.subscribeMatchChannel(widget.matchId);
    });
  }

  @override
  void dispose() {
    context.read<OpenPlayProvider>().unsubscribeMatchChannel(widget.matchId);
    super.dispose();
  }

  String _formatCurrency(int amount) {
    return NumberFormat.currency(locale: 'vi_VN', symbol: 'đ', decimalDigits: 0).format(amount);
  }

  String _formatDate(String? dateStr) {
    if (dateStr == null) return '';
    try {
      final d = DateTime.parse(dateStr);
      return DateFormat('EEEE, dd/MM/yyyy', 'vi_VN').format(d);
    } catch (_) {
      return dateStr;
    }
  }

  String _formatTime(String? timeStr) {
    if (timeStr == null || timeStr.length < 5) return '';
    return timeStr.substring(0, 5);
  }

  void _showQrDialog(OpenPlayParticipantModel p) {
    final token = p.checkInToken ?? '';
    final qrData = 'OSOP:${widget.matchId}:${p.userId}:$token';

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        contentPadding: const EdgeInsets.all(24),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Mã QR Check-in',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            const Text(
              'Đưa mã này cho Host hoặc Lễ tân để quét vào sân.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: 200,
              height: 200,
              child: QrImageView(
                data: qrData,
                version: QrVersions.auto,
                size: 200.0,
              ),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                onPressed: () => Navigator.pop(ctx),
                child: const Text('Đóng', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showPaymentDialog(OpenPlayModel match) {
    String selectedMethod = 'wallet';
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) => Container(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Thanh Toán Phần Tiền Sân', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Số tiền slot:'),
                    Text(
                      _formatCurrency(match.slotPrice),
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.primary),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              RadioListTile<String>(
                title: const Text('Ví cá nhân Ocean Sport'),
                subtitle: const Text('Thanh toán trừ số dư ví', style: TextStyle(fontSize: 11)),
                value: 'wallet',
                groupValue: selectedMethod,
                activeColor: AppColors.primary,
                onChanged: (v) => setModalState(() => selectedMethod = v!),
              ),
              RadioListTile<String>(
                title: const Text('Tiền mặt tại sân'),
                subtitle: const Text('Gửi tiền mặt cho Host khi đến', style: TextStyle(fontSize: 11)),
                value: 'cash',
                groupValue: selectedMethod,
                activeColor: AppColors.primary,
                onChanged: (v) => setModalState(() => selectedMethod = v!),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: () async {
                    Navigator.pop(ctx);
                    final provider = context.read<OpenPlayProvider>();
                    try {
                      await provider.paySlot(widget.matchId, selectedMethod);
                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Thanh toán slot thành công!')),
                        );
                      }
                    } catch (e) {
                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Lỗi: $e')),
                        );
                      }
                    }
                  },
                  child: const Text('Xác Nhận Thanh Toán', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _handleJoin(OpenPlayModel match) async {
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng đăng nhập để tham gia trận đấu.')),
      );
      context.push('/login');
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Xác nhận tham gia'),
        content: Text(
          match.paymentMode == 'split_payment'
            ? 'Bạn đang đăng ký tham gia trận này. Chi phí chia sân: ${_formatCurrency(match.slotPrice)}/slot.'
            : 'Trận đấu này do Host bao sân (Miễn phí).'
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Hủy')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Đồng ý', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      final provider = context.read<OpenPlayProvider>();
      try {
        await provider.joinMatch(widget.matchId);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Tham gia trận thành công!')),
          );
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Không thể tham gia: $e')),
          );
        }
      }
    }
  }

  Future<void> _handleLeave() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Rời trận đấu?'),
        content: const Text('Bạn có chắc chắn muốn rời trận không?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Ở lại')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Xác nhận rời', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      final provider = context.read<OpenPlayProvider>();
      try {
        await provider.leaveMatch(widget.matchId);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Bạn đã rời trận.')),
          );
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Lỗi: $e')),
          );
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<OpenPlayProvider>();
    final auth = context.watch<AuthProvider>();
    final match = provider.currentMatch;
    final currentUserId = auth.user?.id;

    if (provider.isLoading && match == null) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (match == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Chi tiết trận đấu')),
        body: const Center(child: Text('Không tìm thấy thông tin trận đấu.')),
      );
    }

    final isHost = currentUserId != null && currentUserId == match.hostUserId;
    final myParticipation = match.participants.firstWhere(
      (p) => p.userId == currentUserId && !['cancelled', 'rejected'].contains(p.status),
      orElse: () => OpenPlayParticipantModel(
        id: 0,
        openPlayId: 0,
        role: '',
        status: 'none',
        paymentStatus: '',
        paymentAmount: 0,
      ),
    );
    final hasJoined = myParticipation.status != 'none';

    final bookingDate = match.booking?['booking_date']?.toString();
    final startTime = match.booking?['start_time']?.toString();
    final endTime = match.booking?['end_time']?.toString();
    final courtName = match.booking?['court']?['court_name']?.toString() ?? 'Sân Cầu Lông';

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: Text(match.openPlayCode, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        backgroundColor: Colors.white,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top Card: Info & Badges
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 10, offset: const Offset(0, 4)),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: AppColors.primary.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          match.status.toUpperCase(),
                          style: const TextStyle(color: AppColors.primary, fontSize: 11, fontWeight: FontWeight.bold),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        match.paymentMode == 'split_payment' ? 'Chia tiền sân' : 'Host bao sân',
                        style: const TextStyle(fontSize: 12, color: Colors.grey, fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(
                    match.title,
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1E293B)),
                  ),
                  const SizedBox(height: 12),
                  const Divider(height: 1),
                  const SizedBox(height: 12),

                  // Detail rows
                  _buildDetailRow(Icons.calendar_today, 'Ngày đấu', _formatDate(bookingDate)),
                  const SizedBox(height: 8),
                  _buildDetailRow(Icons.access_time, 'Thời gian', '${_formatTime(startTime)} - ${_formatTime(endTime)}'),
                  const SizedBox(height: 8),
                  _buildDetailRow(Icons.location_on, 'Địa điểm', courtName),
                  const SizedBox(height: 8),
                  _buildDetailRow(
                    Icons.monetization_on,
                    'Chi phí',
                    match.paymentMode == 'split_payment' ? '${_formatCurrency(match.slotPrice)}/người' : 'Miễn phí',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Description & Rules
            if (match.description != null && match.description!.isNotEmpty) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [
                    BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 10, offset: const Offset(0, 4)),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Mô tả trận đấu', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 6),
                    Text(match.description!, style: const TextStyle(fontSize: 13, color: Color(0xFF475569))),
                  ],
                ),
              ),
              const SizedBox(height: 16),
            ],

            // Participants List
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 10, offset: const Offset(0, 4)),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Người chơi (${match.currentPlayers}/${match.maxPlayers})',
                        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                      ),
                      if (match.availableSlots > 0)
                        Text('Còn ${match.availableSlots} slot', style: const TextStyle(color: Colors.green, fontWeight: FontWeight.bold, fontSize: 12))
                      else
                        const Text('Đã đầy', style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold, fontSize: 12)),
                    ],
                  ),
                  const SizedBox(height: 12),
                  ...match.participants.map((p) {
                    final name = p.guestName ?? p.user?['full_name']?.toString() ?? 'Người chơi';
                    return Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.grey.shade200),
                      ),
                      child: Row(
                        children: [
                          CircleAvatar(
                            radius: 14,
                            backgroundColor: AppColors.primary.withValues(alpha: 0.15),
                            child: Text(
                              name.isNotEmpty ? name[0].toUpperCase() : 'P',
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppColors.primary),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              name,
                              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                            ),
                          ),
                          if (p.role == 'host')
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                              decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(6)),
                              child: const Text('Host', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                            ),
                          if (p.status == 'checked_in') ...[
                            const SizedBox(width: 4),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                              decoration: BoxDecoration(color: Colors.green, borderRadius: BorderRadius.circular(6)),
                              child: const Text('Đã Check-in', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                            ),
                          ],
                        ],
                      ),
                    );
                  }),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Actions for User
            if (hasJoined) ...[
              if (myParticipation.status == 'confirmed')
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.black87,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    icon: const Icon(Icons.qr_code, color: Colors.white),
                    label: const Text('Mã QR Check-in', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                    onPressed: () => _showQrDialog(myParticipation),
                  ),
                ),
              if (match.paymentMode == 'split_payment' && myParticipation.paymentStatus == 'unpaid') ...[
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.orange,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    icon: const Icon(Icons.credit_card, color: Colors.white),
                    label: Text('Thanh Toán Slot (${_formatCurrency(match.slotPrice)})', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                    onPressed: () => _showPaymentDialog(match),
                  ),
                ),
              ],
              if (!isHost) ...[
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  height: 44,
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.red,
                      side: const BorderSide(color: Colors.red),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    icon: const Icon(Icons.exit_to_app, size: 18),
                    label: const Text('Rời Trận Đấu'),
                    onPressed: _handleLeave,
                  ),
                ),
              ],
            ] else ...[
              if (match.availableSlots > 0 && match.status == 'open')
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    onPressed: () => _handleJoin(match),
                    child: const Text('Tham Gia Trận Này', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                  ),
                )
              else if (match.status == 'full')
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.orange,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    onPressed: () async {
                      final provider = context.read<OpenPlayProvider>();
                      await provider.joinWaitlist(widget.matchId);
                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Bạn đã vào danh sách chờ!')),
                        );
                      }
                    },
                    child: const Text('Vào Danh Sách Chờ', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                  ),
                ),
            ],
            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailRow(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, size: 16, color: AppColors.primary),
        const SizedBox(width: 8),
        Text(
          '$label: ',
          style: const TextStyle(fontSize: 13, color: Colors.grey),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF1E293B)),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }
}
