import os, re

path = 'lib/screens/loyalty_screen.dart'
with open(path, 'r', encoding='utf-8') as f:
    text = f.read()

# Add imports
imports = '''import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../providers/loyalty_provider.dart';
import '../widgets/shimmer_loading.dart';
import '../config/app_theme.dart';'''
text = re.sub(r'import \'package:flutter/material\.dart\';\nimport \'package:go_router/go_router\.dart\';\nimport \'../config/app_theme\.dart\';', imports, text)

# Remove mock data
vars_to_remove = [
    r'  int _currentPoints = 1250;\s*',
    r'  String _currentTier = "Hạng Bạc";\s*',
    r'  double _progressToNextTier = 0\.6; // 60%\s*',
    r'  final List<Map<String, dynamic>> _rewards = \[.*?\];\s*'
]
for v in vars_to_remove:
    text = re.sub(v, '', text, flags=re.MULTILINE | re.DOTALL)

# Modify initState
init_state = '''
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<LoyaltyProvider>().fetchLoyaltyData();
    });
  }
'''
text = re.sub(r'class _LoyaltyScreenState extends State<LoyaltyScreen> \{', 'class _LoyaltyScreenState extends State<LoyaltyScreen> {' + init_state, text)

# Modify _redeemReward
redeem_method_old = r'void _redeemReward\(Map<String, dynamic> reward\) \{.*?(?=\n  @override\n  Widget build)'
redeem_method_new = '''void _redeemReward(dynamic reward, LoyaltyProvider provider) {
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
  }'''
text = re.sub(redeem_method_old, redeem_method_new + '\n', text, flags=re.MULTILINE | re.DOTALL)

# Modify build method
build_start = r'Widget build\(BuildContext context\) \{'
build_new = '''Widget build(BuildContext context) {
    final provider = context.watch<LoyaltyProvider>();
    final _currentPoints = provider.points;
    final _currentTier = provider.tier;
    final _rewards = provider.rewards;
    final _progressToNextTier = 0.5;
'''
text = re.sub(build_start, build_new, text)

# Add Shimmer for ListView
listview_old = r'ListView\.builder\([\s\S]*?shrinkWrap: true,[\s\S]*?physics: const NeverScrollableScrollPhysics\(\),[\s\S]*?padding: const EdgeInsets\.symmetric\(horizontal: 16\),[\s\S]*?itemCount: _rewards\.length,[\s\S]*?itemBuilder: \(context, index\) \{[\s\S]*?final reward = _rewards\[index\];[\s\S]*?final canRedeem = _currentPoints >= reward\[\'points_required\'\];[\s\S]*?return Container\([\s\S]*?margin: const EdgeInsets\.only\(bottom: 12\),[\s\S]*?padding: const EdgeInsets\.all\(16\),[\s\S]*?decoration: BoxDecoration\([\s\S]*?color: Colors\.white,[\s\S]*?borderRadius: BorderRadius\.circular\(16\),[\s\S]*?boxShadow: \[BoxShadow\(color: Colors\.black\.withValues\(alpha: 0\.03\), blurRadius: 10, offset: const Offset\(0, 4\)\)\],[\s\S]*?\),[\s\S]*?child: Row\([\s\S]*?children: \[[\s\S]*?Container\([\s\S]*?width: 60, height: 60,[\s\S]*?padding: const EdgeInsets\.all\(12\),[\s\S]*?decoration: BoxDecoration\(color: \(reward\[\'color\'\] as Color\)\.withValues\(alpha: 0\.1\), borderRadius: BorderRadius\.circular\(12\)\),[\s\S]*?child: Image\.network\(reward\[\'image\'\], color: reward\[\'color\'\]\),[\s\S]*?\),[\s\S]*?const SizedBox\(width: 16\),[\s\S]*?Expanded\([\s\S]*?child: Column\([\s\S]*?crossAxisAlignment: CrossAxisAlignment\.start,[\s\S]*?children: \[[\s\S]*?Text\(reward\[\'title\'\], style: const TextStyle\(fontWeight: FontWeight\.bold, fontSize: 15, color: Color\(0xFF0F172A\)\)\),[\s\S]*?const SizedBox\(height: 4\),[\s\S]*?Text\(reward\[\'description\'\], style: const TextStyle\(color: Color\(0xFF64748B\), fontSize: 12\)\),[\s\S]*?const SizedBox\(height: 8\),[\s\S]*?Row\([\s\S]*?children: \[[\s\S]*?const Icon\(Icons\.stars, color: Colors\.orange, size: 16\),[\s\S]*?const SizedBox\(width: 4\),[\s\S]*?Text\(\'\$\{reward\[\'points_required\'\]\} điểm\', style: const TextStyle\(fontWeight: FontWeight\.bold, color: Colors\.orange, fontSize: 13\)\),[\s\S]*?\],[\s\S]*?\),[\s\S]*?\],[\s\S]*?\),[\s\S]*?\),[\s\S]*?const SizedBox\(width: 12\),[\s\S]*?ElevatedButton\([\s\S]*?onPressed: \(\) => _redeemReward\(reward\),[\s\S]*?style: ElevatedButton\.styleFrom\([\s\S]*?backgroundColor: canRedeem \? AppColors\.primary : Colors\.grey\.shade300,[\s\S]*?foregroundColor: canRedeem \? Colors\.white : Colors\.grey,[\s\S]*?elevation: 0,[\s\S]*?shape: RoundedRectangleBorder\(borderRadius: BorderRadius\.circular\(8\)\),[\s\S]*?padding: const EdgeInsets\.symmetric\(horizontal: 16, vertical: 8\),[\s\S]*?minimumSize: const Size\(80, 36\),[\s\S]*?\),[\s\S]*?child: const Text\(\'Đổi\', style: TextStyle\(fontWeight: FontWeight\.bold\)\),[\s\S]*?\),[\s\S]*?\],[\s\S]*?\),[\s\S]*?\);[\s\S]*?\},[\s\S]*?\),'

listview_new = '''provider.isLoading
              ? ShimmerLoading(width: double.infinity, height: 100, count: 4)
              : ListView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: _rewards.length,
                  itemBuilder: (context, index) {
                    final reward = _rewards[index];
                    final canRedeem = _currentPoints >= reward['points_required'];
                    final color = Colors.orange; // Default color
                    final image = reward['image'] ?? 'https://cdn-icons-png.flaticon.com/512/2956/2956820.png';
                    
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
                            decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
                            child: Image.network(image, color: color),
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(reward['name'] ?? reward['title'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A))),
                                const SizedBox(height: 4),
                                Text(reward['description'] ?? '', style: const TextStyle(color: Color(0xFF64748B), fontSize: 12)),
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
                              backgroundColor: canRedeem ? AppColors.primary : Colors.grey.shade300,
                              foregroundColor: canRedeem ? Colors.white : Colors.grey,
                              elevation: 0,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                              minimumSize: const Size(80, 36),
                            ),
                            child: const Text('Đổi', style: TextStyle(fontWeight: FontWeight.bold)),
                          ),
                        ],
                      ),
                    );
                  },
                ),'''

text = re.sub(listview_old, listview_new, text)

with open(path, 'w', encoding='utf-8') as f:
    f.write(text)

print("Fixed loyalty screen")
