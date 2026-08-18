import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../config/app_config.dart';
import '../../../config/app_theme.dart';
import '../../../models/court_booking_models.dart';
import '../../../widgets/network_image_widget.dart';

/// Card thông tin và bộ chọn sân cầu lông chuẩn thể thao cao cấp.
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
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Top Court Image Banner ──
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
            child: SizedBox(
              height: 120,
              width: double.infinity,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  if (activeCourt.imageUrl != null && activeCourt.imageUrl!.isNotEmpty)
                    NetworkImageWidget(
                      imageUrl: AppConfig.imageUrl(activeCourt.imageUrl),
                      fit: BoxFit.cover,
                      errorWidget: _buildDefaultCourtBanner(context),
                    )
                  else
                    _buildDefaultCourtBanner(context),

                  // Dark gradient overlay for text readability
                  Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          Colors.black.withValues(alpha: 0.15),
                          Colors.black.withValues(alpha: 0.65),
                        ],
                      ),
                    ),
                  ),

                  // Top Badges: Surface & Rating
                  Positioned(
                    top: 10,
                    left: 12,
                    right: 12,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        // Surface Tag
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFFD97706), Color(0xFFF59E0B)],
                            ),
                            borderRadius: BorderRadius.circular(20),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.2),
                                blurRadius: 4,
                              ),
                            ],
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.verified_rounded, size: 12, color: Colors.white),
                              const SizedBox(width: 4),
                              Text(
                                activeCourt.surface ?? 'Thảm PVC BWF',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ],
                          ),
                        ),

                        // Star Rating Pill
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3.5),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.95),
                            borderRadius: BorderRadius.circular(16),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.1),
                                blurRadius: 4,
                              ),
                            ],
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.star_rounded, size: 13, color: Color(0xFFF59E0B)),
                              SizedBox(width: 3),
                              Text(
                                '4.9',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFF1E293B),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),

                  // Bottom Text inside banner
                  Positioned(
                    bottom: 10,
                    left: 12,
                    right: 12,
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                activeCourt.name,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 16.5,
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: -0.2,
                                  shadows: [
                                    Shadow(color: Colors.black54, blurRadius: 4),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                '${activeCourt.code} • ${activeCourt.type == 'indoor' ? 'Trong nhà' : 'Ngoài trời'} • Tối đa ${activeCourt.maxPlayers} người',
                                style: TextStyle(
                                  color: Colors.white.withValues(alpha: 0.9),
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),

          // ── Action Bar & Court Tabs ──
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Direction Button + Location Info
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(7),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF0FDF4),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(Icons.location_on_rounded, size: 18, color: Color(0xFF059669)),
                    ),
                    const SizedBox(width: 10),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Sân Cầu Lông Ocean Sport Club',
                            style: TextStyle(
                              fontSize: 12.5,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF1E293B),
                            ),
                          ),
                          Text(
                            'Hệ thống thảm tiêu chuẩn BWF quốc tế',
                            style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                          ),
                        ],
                      ),
                    ),
                    InkWell(
                      onTap: () async {
                        final Uri url = Uri.parse('https://www.google.com/maps/search/?api=1&query=21.028511,105.804817');
                        if (await canLaunchUrl(url)) {
                          await launchUrl(url, mode: LaunchMode.externalApplication);
                        }
                      },
                      borderRadius: BorderRadius.circular(20),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: const Color(0xFF059669),
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFF059669).withValues(alpha: 0.25),
                              blurRadius: 6,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.directions_rounded, size: 14, color: Colors.white),
                            SizedBox(width: 4),
                            Text(
                              'Chỉ đường',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 11.5,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),

                // Multi Court Selection Horizontal Pills
                if (courts.length > 1) ...[
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 10),
                    child: Divider(height: 1, color: Color(0xFFF1F5F9)),
                  ),
                  Row(
                    children: [
                      const Text(
                        'Chọn sân:',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF475569),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: SizedBox(
                          height: 32,
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
                                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 5),
                                  decoration: BoxDecoration(
                                    gradient: isSelected
                                        ? const LinearGradient(
                                            colors: [Color(0xFFE63B6F), Color(0xFFFF6584)],
                                          )
                                        : null,
                                    color: isSelected ? null : const Color(0xFFF8FAFC),
                                    borderRadius: BorderRadius.circular(16),
                                    border: Border.all(
                                      color: isSelected ? Colors.transparent : const Color(0xFFE2E8F0),
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
                                  child: Center(
                                    child: Text(
                                      c.name,
                                      style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                                        color: isSelected ? Colors.white : const Color(0xFF334155),
                                      ),
                                    ),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDefaultCourtBanner(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF065F46), Color(0xFF047857), Color(0xFF059669)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Stack(
        children: [
          // Badminton Court Lines Vector Emulation
          Positioned.fill(
            child: CustomPaint(
              painter: _BadmintonCourtPainter(),
            ),
          ),
        ],
      ),
    );
  }
}

/// Vẽ họa tiết vạch sơn sân cầu lông chuyên nghiệp
class _BadmintonCourtPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withValues(alpha: 0.22)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;

    final rect = Rect.fromLTWH(16, 12, size.width - 32, size.height - 24);
    canvas.drawRect(rect, paint);

    // Center net line
    canvas.drawLine(
      Offset(size.width / 2, 12),
      Offset(size.width / 2, size.height - 12),
      paint..strokeWidth = 2.0,
    );

    // Service lines
    canvas.drawLine(
      Offset(size.width / 2 - 35, 12),
      Offset(size.width / 2 - 35, size.height - 12),
      paint..strokeWidth = 1.0,
    );
    canvas.drawLine(
      Offset(size.width / 2 + 35, 12),
      Offset(size.width / 2 + 35, size.height - 12),
      paint..strokeWidth = 1.0,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
