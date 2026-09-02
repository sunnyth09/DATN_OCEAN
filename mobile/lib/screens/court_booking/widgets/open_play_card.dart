import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../config/app_theme.dart';
import '../../../models/open_play_models.dart';

class OpenPlayCard extends StatelessWidget {
  final OpenPlayModel match;
  final VoidCallback onTap;

  const OpenPlayCard({
    super.key,
    required this.match,
    required this.onTap,
  });

  String _formatCurrency(int amount) {
    return NumberFormat.currency(locale: 'vi_VN', symbol: 'đ', decimalDigits: 0).format(amount);
  }

  String _formatDate(String? dateStr) {
    if (dateStr == null) return '';
    try {
      final d = DateTime.parse(dateStr);
      return DateFormat('EEE, dd/MM/yyyy', 'vi_VN').format(d);
    } catch (_) {
      return dateStr;
    }
  }

  String _formatTime(String? timeStr) {
    if (timeStr == null || timeStr.length < 5) return '';
    return timeStr.substring(0, 5);
  }

  Map<String, dynamic> _getSkillMeta(String level) {
    switch (level) {
      case 'beginner':
        return {'text': 'Mới chơi', 'color': Colors.blue};
      case 'intermediate':
        return {'text': 'Trung bình', 'color': Colors.indigo};
      case 'advanced':
        return {'text': 'Nâng cao / Pro', 'color': Colors.orange};
      default:
        return {'text': 'Mọi trình độ', 'color': Colors.teal};
    }
  }

  @override
  Widget build(BuildContext context) {
    final skillMeta = _getSkillMeta(match.skillLevel);
    final bookingDate = match.booking?['booking_date']?.toString();
    final startTime = match.booking?['start_time']?.toString();
    final endTime = match.booking?['end_time']?.toString();
    final courtName = match.booking?['court']?['court_name']?.toString() ?? 'Sân Cầu Lông';
    final hostName = match.host?['full_name']?.toString() ?? 'Host';

    final isFull = match.currentPlayers >= match.maxPlayers;
    final progress = (match.currentPlayers / match.maxPlayers).clamp(0.0, 1.0);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isFull ? Colors.grey.shade200 : AppColors.primary.withValues(alpha: 0.3),
            width: 1.5,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Row 1: Badges
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: (skillMeta['color'] as Color).withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    skillMeta['text'] as String,
                    style: TextStyle(
                      color: skillMeta['color'] as Color,
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade100,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: Colors.grey.shade300),
                  ),
                  child: Text(
                    match.matchType == 'doubles' ? 'Đánh Đôi' : 'Đánh Đơn',
                    style: const TextStyle(fontSize: 11, color: Colors.black87),
                  ),
                ),
                const Spacer(),
                if (isFull)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      'FULL',
                      style: TextStyle(color: Colors.red.shade700, fontSize: 11, fontWeight: FontWeight.bold),
                    ),
                  )
                else
                  Text(
                    'Còn ${match.availableSlots} slot',
                    style: TextStyle(color: Colors.green.shade700, fontSize: 12, fontWeight: FontWeight.bold),
                  ),
              ],
            ),
            const SizedBox(height: 10),

            // Title
            Text(
              match.title,
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.bold,
                color: Color(0xFF1E293B),
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 8),

            // Court & Time details
            Row(
              children: [
                const Icon(Icons.location_on, size: 14, color: AppColors.primary),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    courtName,
                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF334155)),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Row(
              children: [
                const Icon(Icons.calendar_today, size: 13, color: Colors.grey),
                const SizedBox(width: 5),
                Text(
                  _formatDate(bookingDate),
                  style: const TextStyle(fontSize: 12, color: Colors.grey),
                ),
                const SizedBox(width: 10),
                const Icon(Icons.access_time, size: 13, color: Colors.grey),
                const SizedBox(width: 5),
                Text(
                  '${_formatTime(startTime)} - ${_formatTime(endTime)}',
                  style: const TextStyle(fontSize: 12, color: Colors.grey, fontWeight: FontWeight.w600),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Progress bar
            ClipRRect(
              borderRadius: BorderRadius.circular(6),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 6,
                backgroundColor: Colors.grey.shade100,
                valueColor: const AlwaysStoppedAnimation<Color>(AppColors.primary),
              ),
            ),
            const SizedBox(height: 12),

            // Footer: Host Avatar + Price
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    CircleAvatar(
                      radius: 12,
                      backgroundColor: AppColors.primary.withValues(alpha: 0.15),
                      child: Text(
                        hostName.isNotEmpty ? hostName[0].toUpperCase() : 'H',
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.primary),
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      hostName,
                      style: const TextStyle(fontSize: 12, color: Color(0xFF475569)),
                    ),
                  ],
                ),
                if (match.paymentMode == 'split_payment')
                  Text(
                    '${_formatCurrency(match.slotPrice)}/slot',
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.bold,
                      color: AppColors.primary,
                    ),
                  )
                else
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: Colors.green.shade50,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      'Bao sân (Free)',
                      style: TextStyle(fontSize: 11, color: Colors.green.shade800, fontWeight: FontWeight.bold),
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
