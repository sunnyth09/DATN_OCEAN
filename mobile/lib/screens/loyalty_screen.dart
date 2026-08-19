import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/loyalty_provider.dart';
import '../utils/format_utils.dart';
import '../widgets/network_image_widget.dart';
import '../widgets/app_empty_state.dart';
import '../widgets/app_toast.dart';

class LoyaltyScreen extends StatefulWidget {
  const LoyaltyScreen({super.key});

  @override
  State<LoyaltyScreen> createState() => _LoyaltyScreenState();
}

class _LoyaltyScreenState extends State<LoyaltyScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<LoyaltyProvider>().fetchLoyaltyData();
    });
  }

  double _tierProgress(int points) {
    if (points >= 5000) return 1.0;
    if (points >= 1000) return (points - 1000) / (5000 - 1000);
    if (points >= 500) return (points - 500) / (1000 - 500);
    return points / 500;
  }

  void _redeemReward(dynamic reward, LoyaltyProvider provider) {
    final pointsRequired = (reward['points_required'] as num?)?.toInt() ?? 0;
    if (provider.points >= pointsRequired) {
      showDialog(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text(
            'Xác nhận đổi quà',
            style: TextStyle(fontWeight: FontWeight.w800),
          ),
          content: Text(
            'Bạn có chắc muốn dùng $pointsRequired điểm để đổi phần quà "${reward['name'] ?? ''}" không?',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('Hủy', style: TextStyle(color: AppColors.textMuted)),
            ),
            ElevatedButton(
              onPressed: () async {
                Navigator.pop(dialogContext);
                final success = await provider.redeemReward(reward['id']);
                if (!mounted) return;
                if (success) {
                  AppToast.showSuccess(
                    context,
                    message: 'Đổi quà thành công! Bạn còn ${provider.points} điểm.',
                  );
                } else {
                  AppToast.showError(
                    context,
                    message: 'Đổi quà thất bại. Vui lòng thử lại.',
                  );
                }
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
              ),
              child: const Text('Đổi ngay', style: TextStyle(fontWeight: FontWeight.bold)),
            ),
          ],
        ),
      );
    } else {
      AppToast.showWarning(
        context,
        message: 'Bạn chưa đủ điểm để đổi phần quà này.',
      );
    }
  }
  Widget _buildDailyCheckIn(LoyaltyProvider provider) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: AppShadows.card,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Điểm danh hằng ngày',
                    style: TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 16,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  SizedBox(height: 4),
                  Text(
                    'Nhận thưởng mỗi ngày',
                    style: TextStyle(
                      fontSize: 13,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
              ),
              ElevatedButton(
                onPressed: provider.hasCheckedInToday || provider.isCheckingIn
                    ? null
                    : () async {
                        HapticFeedback.mediumImpact();
                        final res = await provider.checkInDaily();
                        if (!mounted) return;
                        if (res['success'] == true) {
                          HapticFeedback.heavyImpact();
                          AppToast.showSuccess(
                            context,
                            message: '${res['message']} (+${res['points_earned']} điểm)',
                          );
                        } else {
                          HapticFeedback.vibrate();
                          AppToast.showError(
                            context,
                            message: res['message'],
                          );
                        }
                      },
                style: ElevatedButton.styleFrom(
                  backgroundColor: provider.hasCheckedInToday ? Colors.grey[200] : AppColors.primary,
                  foregroundColor: provider.hasCheckedInToday ? AppColors.textMuted : Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                ),
                child: provider.isCheckingIn
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : Text(
                        provider.hasCheckedInToday ? 'Đã nhận' : 'Nhận ngay',
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          // Streak UI
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: List.generate(7, (index) {
              final day = index + 1;
              final isPast = day <= provider.checkInStreak;
              final isToday = day == provider.checkInStreak + 1;
              return Column(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: isPast
                          ? AppColors.primary
                          : (isToday && !provider.hasCheckedInToday ? AppColors.primary.withValues(alpha: 0.1) : Colors.grey[100]),
                      shape: BoxShape.circle,
                      border: isPast || (isToday && !provider.hasCheckedInToday)
                          ? Border.all(color: AppColors.primary.withValues(alpha: 0.5), width: 1.5)
                          : Border.all(color: Colors.transparent),
                    ),
                    child: Icon(
                      Icons.check_rounded,
                      size: 20,
                      color: isPast ? Colors.white : (isToday && !provider.hasCheckedInToday ? AppColors.primary : Colors.grey[400]),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'N$day',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: isPast || isToday ? FontWeight.bold : FontWeight.normal,
                      color: isPast ? AppColors.primary : AppColors.textMuted,
                    ),
                  ),
                ],
              );
            }),
          ),
        ],
      ),
    );
  }

  @override

  Widget build(BuildContext context) {
    final provider = context.watch<LoyaltyProvider>();
    final currentPoints = provider.points;
    final progressToNextTier = _tierProgress(currentPoints);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text(
          'Tích Điểm Đổi Quà VIP',
          style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/me');
            }
          },
        ),
      ),
      body: RefreshIndicator(
        color: AppColors.primary,
        onRefresh: () => context.read<LoyaltyProvider>().fetchLoyaltyData(),
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // VIP Membership Card
              Container(
                margin: const EdgeInsets.all(16),
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  gradient: AppGradients.vipGold,
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFFF59E0B).withValues(alpha: 0.35),
                      blurRadius: 20,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Row(
                          children: [
                            Icon(Icons.workspace_premium_rounded, color: Colors.white, size: 22),
                            SizedBox(width: 6),
                            Text(
                              'THẺ THÀNH VIÊN',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.5,
                              ),
                            ),
                          ],
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.25),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            provider.tier.toUpperCase(),
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w900,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                    Text(
                      '$currentPoints',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 44,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -1,
                      ),
                    ),
                    const Text(
                      'Điểm tích lũy hiện tại',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 20),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: LinearProgressIndicator(
                        value: progressToNextTier,
                        backgroundColor: Colors.white.withValues(alpha: 0.3),
                        valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
                        minHeight: 8,
                      ),
                    ),
                  ],
                ),
              ),

              // Daily Check In
              _buildDailyCheckIn(provider),

              const Padding(
                padding: EdgeInsets.fromLTRB(20, 12, 20, 12),
                child: Text(
                  'Danh sách quà tặng & voucher',
                  style: TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 18,
                    color: AppColors.textPrimary,
                    letterSpacing: -0.2,
                  ),
                ),
              ),

              if (provider.isLoading)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 40),
                  child: Center(
                    child: CircularProgressIndicator(color: AppColors.primary),
                  ),
                )
              else if (provider.rewards.isEmpty)
                AppEmptyState(
                  icon: Icons.card_giftcard_rounded,
                  title: 'Chưa có phần quà nào',
                  message: 'Các phần quà đổi thưởng hấp dẫn sẽ được cập nhật sớm.',
                )
              else
                ListView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: provider.rewards.length,
                  itemBuilder: (context, index) {
                    final reward = provider.rewards[index] as Map<String, dynamic>;
                    final pointsRequired = (reward['points_required'] as num?)?.toInt() ?? 0;
                    final canRedeem = currentPoints >= pointsRequired;
                    final imageUrl = AppConfig.imageUrl(reward['image']?.toString() ?? '');

                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: AppColors.border),
                        boxShadow: AppShadows.card,
                      ),
                      child: Row(
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(14),
                            child: imageUrl.isNotEmpty
                                ? NetworkImageWidget(
                                    imageUrl: imageUrl,
                                    width: 68,
                                    height: 68,
                                    fit: BoxFit.cover,
                                  )
                                : Container(
                                    width: 68,
                                    height: 68,
                                    decoration: BoxDecoration(
                                      color: AppColors.primaryContainer,
                                      borderRadius: BorderRadius.circular(14),
                                    ),
                                    child: const Icon(
                                      Icons.card_giftcard_rounded,
                                      color: AppColors.primary,
                                      size: 32,
                                    ),
                                  ),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  reward['name']?.toString() ?? 'Phần quà',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 15,
                                    color: AppColors.textPrimary,
                                  ),
                                ),
                                 Builder(
                                   builder: (_) {
                                     final desc = FormatUtils.stripHtml(reward['description']);
                                     if (desc.isEmpty) return const SizedBox.shrink();
                                     return Padding(
                                       padding: const EdgeInsets.only(top: 4),
                                       child: Text(
                                         desc,
                                         maxLines: 2,
                                         overflow: TextOverflow.ellipsis,
                                         style: const TextStyle(
                                           color: AppColors.textSecondary,
                                           fontSize: 12,
                                         ),
                                       ),
                                     );
                                   },
                                 ),
                                const SizedBox(height: 8),
                                Row(
                                  children: [
                                    const Icon(Icons.stars_rounded, color: AppColors.warning, size: 18),
                                    const SizedBox(width: 4),
                                    Text(
                                      '$pointsRequired điểm',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w900,
                                        color: AppColors.warning,
                                        fontSize: 13,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 10),
                          ElevatedButton(
                            onPressed: canRedeem ? () => _redeemReward(reward, provider) : null,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              disabledBackgroundColor: AppColors.surfaceDim,
                              foregroundColor: Colors.white,
                              disabledForegroundColor: AppColors.textMuted,
                              elevation: 0,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                              minimumSize: const Size(0, 38),
                            ),
                            child: const Text('Đổi', style: TextStyle(fontWeight: FontWeight.bold)),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              const SizedBox(height: 32),
            ],
          ),
        ),
      ),
    );
  }
}
