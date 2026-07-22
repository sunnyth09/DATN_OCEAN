import 'package:flutter/material.dart';

import 'package:provider/provider.dart';
import '../providers/loyalty_provider.dart';
import '../widgets/shimmer_loading.dart';
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

  // Mock data for Loyalty Points and Rewards
String _currentTier = "Hạng Bạc";
final List<Map<String, dynamic>> _rewards = [
    {
      'id': 1,
      'title': 'Voucher Giảm 50K',
      'points_required': 500,
      'description': 'Áp dụng cho mọi đơn hàng trên 200K',
      'image': 'https://cdn-icons-png.flaticon.com/512/2956/2956820.png',
      'color': Colors.orange,
    },
    {
      'id': 2,
      'title': 'Giảm 10% Đặt Sân',
      'points_required': 800,
      'description': 'Áp dụng khi đặt sân bất kỳ',
      'image': 'https://cdn-icons-png.flaticon.com/512/861/861512.png',
      'color': Colors.green,
    },
    {
      'id': 3,
      'title': 'Voucher Giảm 100K',
      'points_required': 1200,
      'description': 'Áp dụng cho mọi đơn hàng trên 500K',
      'image': 'https://cdn-icons-png.flaticon.com/512/2956/2956820.png',
      'color': Colors.red,
    },
    {
      'id': 4,
      'title': 'Tặng 1 Cuốn Cán Vợt',
      'points_required': 2000,
      'description': 'Đổi quà trực tiếp tại cửa hàng',
      'image': 'https://cdn-icons-png.flaticon.com/512/3050/3050239.png',
      'color': Colors.blue,
    }
  ];

  void _redeemReward(dynamic reward, LoyaltyProvider provider) {
    if (provider.points >= reward['points_required']) {
      showDialog(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Xác nhận đổi quà'),
          content: Text('Bạn có chắc chắn muốn dùng ${reward['points_required']} điểm để đổi "${reward['name'] ?? reward['title']}" không?'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('Hủy', style: TextStyle(color: Colors.grey))),
            ElevatedButton(
              onPressed: () async {
                Navigator.pop(context);
                final success = await context.read<LoyaltyProvider>().redeemReward(reward['id']);
                if (!mounted) return;
                if (success) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                    content: Text('Đổi quà thành công! Bạn còn ${context.read<LoyaltyProvider>().points} điểm.'),
                    backgroundColor: Colors.green,
                  ));
                } else {
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
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
    final _currentPoints = provider.points;
    
    
    final _progressToNextTier = 0.5;

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
                        child: Text(_currentTier, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Text('$_currentPoints', style: const TextStyle(color: Colors.white, fontSize: 40, fontWeight: FontWeight.w900)),
                  const Text('Điểm tích lũy', style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w500)),
                  const SizedBox(height: 24),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: _progressToNextTier,
                      backgroundColor: Colors.white.withValues(alpha: 0.2),
                      valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
                      minHeight: 6,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text('Thêm 750 điểm nữa để lên Hạng Vàng', style: TextStyle(color: Colors.white70, fontSize: 12)),
                ],
              ),
            ),
            
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: const Text('Danh sách quà tặng', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Color(0xFF0F172A))),
            ),
            
            ListView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: _rewards.length,
              itemBuilder: (context, index) {
                final reward = _rewards[index];
                final canRedeem = _currentPoints >= reward['points_required'];
                
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
                        decoration: BoxDecoration(color: (reward['color'] as Color).withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
                        child: Image.network(reward['image'], color: reward['color']),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(reward['title'], style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A))),
                            const SizedBox(height: 4),
                            Text(reward['description'], style: const TextStyle(color: Color(0xFF64748B), fontSize: 12)),
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                const Icon(Icons.stars, color: Colors.orange, size: 16),
                                const SizedBox(width: 4),
                                Text('${reward['points_required']} điểm', style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.orange, fontSize: 13)),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 12),
                      ElevatedButton(
                        onPressed: () => _redeemReward(reward, provider),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: canRedeem ? const Color(0xFFE63B6F) : const Color(0xFFCBD5E1),
                          foregroundColor: Colors.white,
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
