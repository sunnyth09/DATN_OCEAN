import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'network_image_widget.dart';

/// Module tạo hiệu ứng bay ảnh sản phẩm vào giỏ hàng (Fly to Cart Animation)
/// Chuẩn tương tự composable `useFlyToCart.js` trên bản Web:
/// - Tính toán quỹ đạo đường cong Parabol 2D (Quadratic Bezier Curve).
/// - Vừa bay vừa thu nhỏ, xoay nhẹ và mờ dần lao thẳng vào Icon Giỏ hàng.
/// - Kích hoạt callback khi chạm đích để Icon Giỏ hàng rung nảy.
class FlyToCartAnimator {
  FlyToCartAnimator._();

  static void fly({
    required BuildContext context,
    required GlobalKey targetKey,
    GlobalKey? startKey,
    Offset? startOffset,
    String? imageUrl,
    VoidCallback? onComplete,
    Duration duration = const Duration(milliseconds: 650),
  }) {
    final overlay = Overlay.maybeOf(context);
    if (overlay == null) {
      onComplete?.call();
      return;
    }

    // 1. Xác định vị trí đích (Target Rect - Icon Giỏ hàng trên AppBar)
    final targetRenderBox = targetKey.currentContext?.findRenderObject() as RenderBox?;
    if (targetRenderBox == null || !targetRenderBox.attached) {
      onComplete?.call();
      return;
    }
    final targetOffset = targetRenderBox.localToGlobal(Offset.zero);
    final targetCenter = Offset(
      targetOffset.dx + targetRenderBox.size.width / 2,
      targetOffset.dy + targetRenderBox.size.height / 2,
    );

    // 2. Xác định vị trí xuất phát (Start Rect - Ảnh sản phẩm / Nút bấm)
    Offset startCenter;
    double startSize = 75.0;

    if (startKey != null) {
      final startRenderBox = startKey.currentContext?.findRenderObject() as RenderBox?;
      if (startRenderBox != null && startRenderBox.attached) {
        final origin = startRenderBox.localToGlobal(Offset.zero);
        startCenter = Offset(
          origin.dx + startRenderBox.size.width / 2,
          origin.dy + startRenderBox.size.height / 2,
        );
        startSize = math.min(startRenderBox.size.width, 90.0);
      } else {
        startCenter = startOffset ?? Offset(MediaQuery.of(context).size.width / 2, MediaQuery.of(context).size.height / 2);
      }
    } else {
      startCenter = startOffset ?? Offset(MediaQuery.of(context).size.width / 2, MediaQuery.of(context).size.height / 2);
    }

    late OverlayEntry entry;
    entry = OverlayEntry(
      builder: (ctx) => _FlyingThumbnailWidget(
        startPos: startCenter,
        targetPos: targetCenter,
        initialSize: startSize,
        imageUrl: imageUrl,
        duration: duration,
        onAnimationEnd: () {
          entry.remove();
          onComplete?.call();
        },
      ),
    );

    overlay.insert(entry);
  }
}

class _FlyingThumbnailWidget extends StatefulWidget {
  final Offset startPos;
  final Offset targetPos;
  final double initialSize;
  final String? imageUrl;
  final Duration duration;
  final VoidCallback onAnimationEnd;

  const _FlyingThumbnailWidget({
    required this.startPos,
    required this.targetPos,
    required this.initialSize,
    this.imageUrl,
    required this.duration,
    required this.onAnimationEnd,
  });

  @override
  State<_FlyingThumbnailWidget> createState() => _FlyingThumbnailWidgetState();
}

class _FlyingThumbnailWidgetState extends State<_FlyingThumbnailWidget>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _curve;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: widget.duration);
    _curve = CurvedAnimation(parent: _ctrl, curve: Curves.easeInOutCubic);

    _ctrl.forward().then((_) {
      if (mounted) widget.onAnimationEnd();
    });
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  /// Tính toán tọa độ theo đường cong Bezier bậc hai P(t) = (1-t)^2*P0 + 2*(1-t)*t*P1 + t^2*P2
  Offset _computeBezier(double t) {
    final p0 = widget.startPos;
    final p2 = widget.targetPos;

    // Điểm kiểm soát uốn cong Parabol: nhô cao hơn và lệch nhẹ sang trái tạo góc ném tự nhiên
    final p1 = Offset(
      (p0.dx + p2.dx) / 2 - 40,
      math.min(p0.dy, p2.dy) - 90,
    );

    final oneMinusT = 1.0 - t;
    final x = oneMinusT * oneMinusT * p0.dx + 2 * oneMinusT * t * p1.dx + t * t * p2.dx;
    final y = oneMinusT * oneMinusT * p0.dy + 2 * oneMinusT * t * p1.dy + t * t * p2.dy;

    return Offset(x, y);
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _curve,
      builder: (context, child) {
        final t = _curve.value;
        final currentPos = _computeBezier(t);
        final currentScale = (1.0 - t * 0.82).clamp(0.18, 1.0);
        final currentOpacity = (1.0 - t * 0.45).clamp(0.4, 1.0);
        final currentRotation = (t * 0.35); // Xoay ~20 độ

        final size = widget.initialSize * currentScale;

        return Positioned(
          left: currentPos.dx - size / 2,
          top: currentPos.dy - size / 2,
          child: IgnorePointer(
            child: Opacity(
              opacity: currentOpacity,
              child: Transform.rotate(
                angle: currentRotation,
                child: Container(
                  width: size,
                  height: size,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(size * 0.22),
                    border: Border.all(
                      color: const Color(0xFFE63B6F).withValues(alpha: 0.5),
                      width: 1.5,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFFE63B6F).withValues(alpha: 0.3),
                        blurRadius: 14,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: widget.imageUrl != null && widget.imageUrl!.isNotEmpty
                      ? NetworkImageWidget(
                          imageUrl: widget.imageUrl!,
                          fit: BoxFit.cover,
                          width: size,
                          height: size,
                        )
                      : const Center(
                          child: Icon(
                            Icons.sports_tennis_rounded,
                            color: Color(0xFFE63B6F),
                            size: 22,
                          ),
                        ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}
