import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../config/app_theme.dart';
import '../providers/loyalty_provider.dart';
import '../widgets/app_toast.dart';

class LuckyWheelScreen extends StatefulWidget {
  const LuckyWheelScreen({super.key});

  @override
  State<LuckyWheelScreen> createState() => _LuckyWheelScreenState();
}

class _LuckyWheelScreenState extends State<LuckyWheelScreen> with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _animation;
  
  List<dynamic> _prizes = [];
  bool _isLoading = true;
  bool _isSpinning = false;
  double _currentAngle = 0;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 4),
    );
    
    _controller.addListener(() {
      // Simulate ticking haptic feedback as it spins
      if (_isSpinning && _controller.value < 0.95 && DateTime.now().millisecondsSinceEpoch % 150 < 20) {
        HapticFeedback.selectionClick();
      }
    });

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadPrizes();
    });
  }

  Future<void> _loadPrizes() async {
    final provider = context.read<LoyaltyProvider>();
    final prizes = await provider.fetchLuckyWheelPrizes();
    if (mounted) {
      setState(() {
        _prizes = prizes;
        _isLoading = false;
      });
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _spin() async {
    if (_isSpinning || _prizes.isEmpty) return;

    final provider = context.read<LoyaltyProvider>();
    
    if (provider.points < 50) {
      AppToast.showError(context, message: 'Bạn không đủ xu để quay (Cần 50 Xu)');
      return;
    }

    setState(() {
      _isSpinning = true;
    });

    final result = await provider.spinLuckyWheel();
    if (!mounted) return;

    if (result['success'] == true) {
      final int prizeIndex = result['prize_index'];
      final prize = result['prize'];
      
      final double sliceAngle = 2 * math.pi / _prizes.length;
      
      final targetAngle = _currentAngle + (10 * 2 * math.pi) - (prizeIndex * sliceAngle) - (sliceAngle / 2) - (math.pi / 2);

      _animation = Tween<double>(begin: _currentAngle, end: targetAngle).animate(
        CurvedAnimation(parent: _controller, curve: Curves.easeOutQuart),
      );

      _controller.forward(from: 0).then((_) {
        _currentAngle = targetAngle % (2 * math.pi);
        setState(() {
          _isSpinning = false;
        });

        HapticFeedback.heavyImpact();
        _showResultDialog(prize);
      });
    } else {
      setState(() {
        _isSpinning = false;
      });
      AppToast.showError(context, message: result['message'] ?? 'Có lỗi xảy ra');
    }
  }

  void _showResultDialog(dynamic prize) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: Text(
            prize['type'] == 'empty' ? 'Rất tiếc!' : 'Chúc mừng!',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontWeight: FontWeight.w900,
              color: prize['type'] == 'empty' ? AppColors.textMuted : AppColors.primary,
            ),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                prize['type'] == 'empty' ? Icons.sentiment_dissatisfied_rounded : Icons.card_giftcard_rounded,
                size: 64,
                color: prize['type'] == 'empty' ? Colors.grey : AppColors.secondary,
              ),
              const SizedBox(height: 16),
              Text(
                prize['type'] == 'empty'
                    ? 'Chúc bạn may mắn lần sau nhé!'
                    : 'Bạn đã trúng phần thưởng:\n${prize['name']}',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
            ],
          ),
          actions: [
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.pop(context);
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                ),
                child: const Text('Xác nhận', style: TextStyle(fontWeight: FontWeight.bold)),
              ),
            ),
          ],
        );
      },
    );
  }

  Color _hexToColor(String hexString) {
    var hexColor = hexString.replaceAll('#', '');
    if (hexColor.length == 6) {
      hexColor = 'FF$hexColor';
    }
    return Color(int.parse(hexColor, radix: 16));
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<LoyaltyProvider>();
    return Scaffold(
      backgroundColor: const Color(0xFF1E1E2C), // Dark background for contrast
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.white),
        title: const Text(
          'Vòng Quay May Mắn',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.white))
          : Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // Points balance
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(30),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.monetization_on_rounded, color: Colors.amber, size: 24),
                      const SizedBox(width: 8),
                      Text(
                        '${provider.points} Xu',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 40),
                
                // Wheel container
                Stack(
                  alignment: Alignment.center,
                  children: [
                    // Outer glow/border
                    Container(
                      width: 320,
                      height: 320,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: Colors.amber.shade200,
                        boxShadow: [
                          BoxShadow(
                            color: Colors.amber.withValues(alpha: 0.5),
                            blurRadius: 40,
                            spreadRadius: 10,
                          ),
                        ],
                      ),
                      padding: const EdgeInsets.all(12),
                      child: Container(
                        decoration: const BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.white,
                        ),
                        child: AnimatedBuilder(
                          animation: _controller,
                          builder: (context, child) {
                            return Transform.rotate(
                              angle: _isSpinning ? _animation.value : _currentAngle,
                              child: CustomPaint(
                                painter: WheelPainter(
                                  prizes: _prizes,
                                  colors: _prizes.map((p) => _hexToColor(p['color'] ?? '#CCCCCC')).toList(),
                                ),
                                size: const Size(300, 300),
                              ),
                            );
                          },
                        ),
                      ),
                    ),
                    // Pointer
                    Positioned(
                      top: 0,
                      child: Transform.translate(
                        offset: const Offset(0, -10),
                        child: const Icon(
                          Icons.arrow_drop_down_circle_rounded,
                          color: Colors.redAccent,
                          size: 40,
                        ),
                      ),
                    ),
                    // Center dot
                    Container(
                      width: 40,
                      height: 40,
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(color: Colors.black26, blurRadius: 4, offset: Offset(0, 2))
                        ],
                      ),
                      child: const Center(
                        child: Icon(Icons.star_rounded, color: Colors.amber, size: 24),
                      ),
                    ),
                  ],
                ),
                
                const SizedBox(height: 60),
                
                // Spin button
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 40),
                  child: ElevatedButton(
                    onPressed: _isSpinning ? null : _spin,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.amber.shade600,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(30),
                      ),
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      minimumSize: const Size(double.infinity, 56),
                      elevation: 8,
                    ),
                    child: _isSpinning
                        ? const CircularProgressIndicator(color: Colors.white)
                        : const Text(
                            'QUAY NGAY (50 XU)',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                              letterSpacing: 1,
                            ),
                          ),
                  ),
                ),
              ],
            ),
    );
  }
}

class WheelPainter extends CustomPainter {
  final List<dynamic> prizes;
  final List<Color> colors;

  WheelPainter({required this.prizes, required this.colors});

  @override
  void paint(Canvas canvas, Size size) {
    if (prizes.isEmpty) return;

    final center = Offset(size.width / 2, size.height / 2);
    final radius = size.width / 2;
    final rect = Rect.fromCircle(center: center, radius: radius);
    final sliceAngle = 2 * math.pi / prizes.length;

    for (int i = 0; i < prizes.length; i++) {
      final paint = Paint()
        ..color = colors[i % colors.length]
        ..style = PaintingStyle.fill;
      
      canvas.drawArc(rect, i * sliceAngle, sliceAngle, true, paint);

      final borderPaint = Paint()
        ..color = Colors.white.withValues(alpha: 0.5)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2;
      canvas.drawArc(rect, i * sliceAngle, sliceAngle, true, borderPaint);

      canvas.save();
      canvas.translate(center.dx, center.dy);
      canvas.rotate(i * sliceAngle + sliceAngle / 2);
      
      final textSpan = TextSpan(
        text: prizes[i]['name'],
        style: const TextStyle(
          color: Colors.white,
          fontSize: 12,
          fontWeight: FontWeight.bold,
        ),
      );
      final textPainter = TextPainter(
        text: textSpan,
        textDirection: TextDirection.ltr,
        textAlign: TextAlign.right,
      );
      textPainter.layout();
      
      textPainter.paint(
        canvas,
        Offset(radius - textPainter.width - 16, -textPainter.height / 2),
      );
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => true;
}
