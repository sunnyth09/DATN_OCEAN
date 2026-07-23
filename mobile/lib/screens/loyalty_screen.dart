import 'package:flutter/material.dart';

import 'package:provider/provider.dart';
import '../providers/loyalty_provider.dart';
import '../config/app_config.dart';
import '../config/app_theme.dart';

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

  /// Tiến độ (0..1) tới tier kế tiếp theo ngưỡng backend: 500 Bạc, 1000 Vàng, 5000 Kim Cương.
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
        builder: (context) => AlertDialog(
          title: const Text('Xác nhận đổi quà'),
          content: Text('Bạn có chắc chắn muốn dùng $pointsRequired điểm để đổi "${reward['name'] ?? ''}" không?'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('Hủy', style: TextStyle(color: Colors.grey))),
            ElevatedButton(
              onPressed: () async {
                Navigator.pop(context);
                // Capture trước async gap để không dùng context sau await.
                final messenger = ScaffoldMessenger.of(context);
                final success = await provider.redeemReward(reward['id']);
                if (!mounted) return;
                if (success) {
                  messenger.showSnackBar(SnackBar(
                    content: Text('Đổi quà thành công! Bạn còn ${provider.points} điểm.'),
                    backgroundColor: Colors.green,
                  ));
                } else {
                  messenger.showSnackBar(const SnackBar(
                    content: Text('Đổi quà thất bại. Vui lòng thử lại.'),
                    backgroundColor: Colors.red,
                  ));
                }
              },
              style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary),
              child: const Text('Đổi ngay', style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Bạn không đủ điểm để đổi phần quà này.'),
        backgroundColor: Colors.orange,
      ));
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<LoyaltyProvider>();
    final currentPoints = provider.points;
    final progressToNextTier = _tierProgress(currentPoints);

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Tích điểm đổi quà', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFFE63B6F)),
      ),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header: Loyalty Card
            Container(
              margin: const EdgeInsets.all(16),
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFFE63B6F), Color(0xFFFF758C)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(20),
                boxShadow: [BoxShadow(color: const Color(0xFFE63B6F).withValues(alpha: 0.3), blurRadius: 15, offset: const Offset(0, 8))],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Thẻ Thành Viên', style: TextStyle(color: Colors.white70, fontSize: 14)),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(12)),
                        child: Text(provider.tier, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Text('$currentPoints', style: const TextStyle(color: Colors.white, fontSize: 40, fontWeight: FontWeight.w900)),
                  const Text('Điểm tích lũy', style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w500)),
                  const SizedBox(height: 24),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: progressToNextTier,
                      backgroundColor: Colors.white.withValues(alpha: 0.2),
                      valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
                      minHeight: 6,
                    ),
                  ),
                ],
              ),
            ),

            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: const Text('Danh sách quà tặng', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Color(0xFF0F172A))),
            ),

            if (provider.isLoading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 40),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (provider.rewards.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 40, horizontal: 16),
                child: Center(
                  child: Text('Chưa có phần quà nào để đổi.', style: TextStyle(color: Color(0xFF64748B))),
                ),
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
                  final imageUrl = AppConfig.imageUrl(reward['image']?.toString());

                  return Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.03), blurRadius: 10, offset: const Offset(0, 4))],
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 60, height: 60,
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(color: const Color(0xFFE63B6F).withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
                          child: imageUrl.isNotEmpty
                              ? Image.network(
                                  imageUrl,
                                  errorBuilder: (context, error, stack) => const Icon(Icons.card_giftcard, color: Color(0xFFE63B6F)),
                                )
                              : const Icon(Icons.card_giftcard, color: Color(0xFFE63B6F)),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(reward['name']?.toString() ?? 'Phần quà', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A))),
                              if (reward['description'] != null) ...[
                                const SizedBox(height: 4),
                                Text(reward['description'].toString(), style: const TextStyle(color: Color(0xFF64748B), fontSize: 12)),
                              ],
                              const SizedBox(height: 8),
                              Row(
                                children: [
                                  const Icon(Icons.stars, color: Colors.orange, size: 16),
                                  const SizedBox(width: 4),
                                  Text('$pointsRequired điểm', style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.orange, fontSize: 13)),
                                ],
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        ElevatedButton(
                          onPressed: canRedeem ? () => _redeemReward(reward, provider) : null,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFE63B6F),
                            disabledBackgroundColor: const Color(0xFFCBD5E1),
                            foregroundColor: Colors.white,
                            disabledForegroundColor: Colors.white,
                            elevation: 0,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            minimumSize: const Size(0, 36),
                          ),
                          child: const Text('Đổi', style: TextStyle(fontWeight: FontWeight.bold)),
                        ),
                      ],
                    ),
                  );
                },
              ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}
