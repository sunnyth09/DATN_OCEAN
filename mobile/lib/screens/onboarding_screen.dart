import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'main_wrapper.dart';

class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  void _goToApp() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('is_first_launch', false);
    if (!mounted) return;
    context.go('/home');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: SafeArea(
        child: Column(
          children: [
            // Header: Skip button
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
              child: Align(
                alignment: Alignment.centerRight,
                child: GestureDetector(
                  onTap: _goToApp,
                  child: const Text('Bỏ qua', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF64748B))),
                ),
              ),
            ),
            
            const SizedBox(height: 24),
            
            // Image Placeholder (The dark box with 3D art on Figma)
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 32),
                child: Container(
                  width: double.infinity,
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(40),
                    boxShadow: [
                      BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 20, offset: const Offset(0, 10)),
                    ],
                  ),
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      // Placeholder icon
                      const Icon(Icons.sports_tennis, size: 100, color: Color(0xFFFF8FAB)),
                      // Hazy gradient glow effect logic would go here
                      Positioned(
                         bottom: 0,
                         child: Container(
                           width: 250,
                           height: 50,
                           decoration: BoxDecoration(
                             gradient: RadialGradient(
                               colors: [const Color(0xFFE63B6F).withValues(alpha: 0.5), Colors.transparent],
                               radius: 1.5,
                             ),
                           ),
                         ),
                      )
                    ],
                  ),
                ),
              ),
            ),
            
            const SizedBox(height: 48),
            
            // Text Content
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 40),
              child: Column(
                children: [
                  Text('Quyền Sport', textAlign: TextAlign.center, style: TextStyle(fontSize: 26, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), height: 1.2)),
                  SizedBox(height: 16),
                  Text('Khám phá hàng ngàn sản phẩm thể thao chính hãng — giày, quần áo, phụ kiện — chỉ với vài thao tác.', textAlign: TextAlign.center, style: TextStyle(fontSize: 14, color: Color(0xFF64748B), height: 1.6)),
                ],
              ),
            ),
            
            const SizedBox(height: 40),
            
            // Page Indicators
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(width: 20, height: 6, decoration: BoxDecoration(color: const Color(0xFFE63B6F), borderRadius: BorderRadius.circular(3))),
                const SizedBox(width: 6),
                Container(width: 6, height: 6, decoration: BoxDecoration(color: const Color(0xFFCBD5E1), borderRadius: BorderRadius.circular(3))),
                const SizedBox(width: 6),
                Container(width: 6, height: 6, decoration: BoxDecoration(color: const Color(0xFFCBD5E1), borderRadius: BorderRadius.circular(3))),
              ],
            ),
            
            const SizedBox(height: 40),
            
            // Button Tiếp tục
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _goToApp,
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 18),
                    backgroundColor: const Color(0xFFE63B6F),
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
                  ),
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                       Text('Tiếp tục', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white)),
                       SizedBox(width: 8),
                       Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 20)
                    ],
                  ),
                ),
              ),
            ),
            
            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }
}
