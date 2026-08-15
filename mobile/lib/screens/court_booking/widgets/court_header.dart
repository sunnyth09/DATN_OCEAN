import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../config/app_theme.dart';
import '../../../models/court_booking_models.dart';

/// Card thông tin và bộ chọn sân cầu lông.
class CourtHeader extends StatelessWidget {
  final List<Court> courts;
  final int? selectedCourtId;
  final ValueChanged<int>? onSelectCourt;
  final Court? court;

  const CourtHeader({
    super.key,
    this.courts = const [],
    this.selectedCourtId,
    this.onSelectCourt,
    this.court,
  });

  const CourtHeader.single(Court singleCourt, {super.key})
      : courts = const [],
        selectedCourtId = null,
        onSelectCourt = null,
        court = singleCourt;

  @override
  Widget build(BuildContext context) {
    final activeCourt = court ??
        courts.firstWhere(
          (c) => c.id == selectedCourtId,
          orElse: () => courts.isNotEmpty
              ? courts.first
              : Court(
                  id: 0,
                  code: 'COURT',
                  name: 'Sân cầu lông',
                  type: 'indoor',
                  maxPlayers: 4,
                  status: 'active',
                ),
        );

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
        boxShadow: AppShadows.card,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: AppColors.primarySoft,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(Icons.sports_tennis_rounded, color: AppColors.primary, size: 28),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      activeCourt.name,
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 16,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${activeCourt.code} • ${activeCourt.type == 'indoor' ? 'Trong nhà' : 'Ngoài trời'} • Tối đa ${activeCourt.maxPlayers} người',
                      style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                    ),
                  ],
                ),
              ),
              IconButton(
                onPressed: () async {
                  final Uri url = Uri.parse('https://www.google.com/maps/search/?api=1&query=21.028511,105.804817');
                  if (await canLaunchUrl(url)) {
                    await launchUrl(url, mode: LaunchMode.externalApplication);
                  }
                },
                icon: const Icon(Icons.map_outlined, color: AppColors.primary),
                tooltip: 'Chỉ đường Google Maps',
              ),
            ],
          ),
          if (courts.length > 1) ...[
            const Divider(height: 24),
            const Text(
              'Chọn sân thi đấu:',
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: AppColors.textSecondary,
              ),
            ),
            const SizedBox(height: 8),
            SizedBox(
              height: 36,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: courts.length,
                separatorBuilder: (_, _) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final c = courts[index];
                  final isSelected = c.id == (selectedCourtId ?? activeCourt.id);
                  return GestureDetector(
                    onTap: () => onSelectCourt?.call(c.id),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                      decoration: BoxDecoration(
                        color: isSelected ? AppColors.primary : AppColors.surfaceDim,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(
                          color: isSelected ? AppColors.primary : AppColors.border,
                        ),
                      ),
                      child: Center(
                        child: Text(
                          c.name,
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                            color: isSelected ? Colors.white : AppColors.textPrimary,
                          ),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ],
      ),
    );
  }
}
