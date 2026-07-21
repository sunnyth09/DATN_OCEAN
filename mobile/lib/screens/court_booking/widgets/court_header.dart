import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../config/app_theme.dart';
import '../../../models/court_booking_models.dart';
import 'court_booking_chips.dart';

/// Card thông tin sân đang chọn.
class CourtHeader extends StatelessWidget {
  final Court court;

  const CourtHeader(this.court, {super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: courtCardDecoration(),
      child: Row(
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: AppColors.primarySoft,
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(Icons.stadium_outlined, color: AppColors.primary),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  court.name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${court.code} - ${court.type} - ${court.maxPlayers} nguoi',
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: () async {
              // Tọa độ giả định của sân để chỉ đường
              final Uri url = Uri.parse('https://www.google.com/maps/search/?api=1&query=21.028511,105.804817');
              if (await canLaunchUrl(url)) {
                await launchUrl(url, mode: LaunchMode.externalApplication);
              }
            },
            icon: const Icon(Icons.map_outlined, color: AppColors.primary),
            tooltip: 'Chỉ đường',
          ),
        ],
      ),
    );
  }
}
