import 'dart:ui';
import 'package:flutter/material.dart';
import '../config/app_theme.dart';

/// Toast thông báo trung tâm kính mờ phong cách OceanSport cao cấp:
/// - Đồng bộ 100% với màu sắc thương hiệu chủ đạo (`#E63B6F` Vivid Pink & Slate Navy).
/// - Nền Frosted Glass trắng ngọc tinh tế, viền hồng phấn thanh mảnh `#FFD1DF`.
/// - Icon huy hiệu gradient hồng rực rỡ với hiệu ứng bung nở êm ái (Scale & Fade Animation).
/// - Tự động tan biến sau 1.3s, hoàn toàn không chặn thao tác vuốt chạm của người dùng (`IgnorePointer`).
class AppToast {
  AppToast._();

  static OverlayEntry? _currentEntry;

  static void showAddToCartSuccess(
    BuildContext context, {
    String message = 'Đã thêm vào giỏ hàng!',
  }) {
    _showCenterToast(
      context: context,
      message: message,
      icon: Icons.check_rounded,
      iconColor: Colors.white,
      badgeColor: AppColors.primary,
      accentColor: AppColors.primary,
    );
  }

  static void showSuccess(
    BuildContext context, {
    required String message,
  }) {
    _showCenterToast(
      context: context,
      message: message,
      icon: Icons.check_rounded,
      iconColor: Colors.white,
      badgeColor: AppColors.primary,
      accentColor: AppColors.primary,
    );
  }

  static void showError(
    BuildContext context, {
    required String message,
  }) {
    _showCenterToast(
      context: context,
      message: message,
      icon: Icons.close_rounded,
      iconColor: Colors.white,
      badgeColor: const Color(0xFFEF4444),
      accentColor: const Color(0xFFEF4444),
    );
  }

  static void _showCenterToast({
    required BuildContext context,
    required String message,
    required IconData icon,
    Color iconColor = Colors.white,
    Color? badgeColor,
    Color? accentColor,
  }) {
    _currentEntry?.remove();
    _currentEntry = null;

    final overlay = Overlay.maybeOf(context);
    if (overlay == null) return;

    late OverlayEntry entry;
    entry = OverlayEntry(
      builder: (ctx) => _FrostedCenterToastWidget(
        message: message,
        icon: icon,
        iconColor: iconColor,
        badgeColor: badgeColor ?? AppColors.primary,
        accentColor: accentColor ?? AppColors.primary,
        onDismiss: () {
          if (_currentEntry == entry) {
            entry.remove();
            _currentEntry = null;
          }
        },
      ),
    );

    _currentEntry = entry;
    overlay.insert(entry);
  }
}

class _FrostedCenterToastWidget extends StatefulWidget {
  final String message;
  final IconData icon;
  final Color iconColor;
  final Color badgeColor;
  final Color accentColor;
  final VoidCallback onDismiss;

  const _FrostedCenterToastWidget({
    required this.message,
    required this.icon,
    required this.iconColor,
    required this.badgeColor,
    required this.accentColor,
    required this.onDismiss,
  });

  @override
  State<_FrostedCenterToastWidget> createState() => _FrostedCenterToastWidgetState();
}

class _FrostedCenterToastWidgetState extends State<_FrostedCenterToastWidget>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _scaleAnim;
  late Animation<double> _opacityAnim;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 230),
      reverseDuration: const Duration(milliseconds: 180),
    );

    _scaleAnim = Tween<double>(begin: 0.75, end: 1.0).animate(
      CurvedAnimation(parent: _ctrl, curve: Curves.easeOutBack),
    );

    _opacityAnim = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _ctrl, curve: Curves.easeOut),
    );

    _ctrl.forward();

    // Tự động đóng sau 1.3 giây
    Future.delayed(const Duration(milliseconds: 1300), () {
      if (mounted) {
        _ctrl.reverse().then((_) {
          if (mounted) widget.onDismiss();
        });
      }
    });
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Material(
        type: MaterialType.transparency,
        child: Center(
          child: AnimatedBuilder(
            animation: _ctrl,
            builder: (context, child) {
              return Opacity(
                opacity: _opacityAnim.value.clamp(0.0, 1.0),
                child: Transform.scale(
                  scale: _scaleAnim.value,
                  child: child,
                ),
              );
            },
            child: ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: BackdropFilter(
                filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
                child: Container(
                  constraints: const BoxConstraints(minWidth: 135, maxWidth: 195),
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.95), // White Frosted Glass sang trọng
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: widget.accentColor.withValues(alpha: 0.22),
                      width: 1.2,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: widget.accentColor.withValues(alpha: 0.18),
                        blurRadius: 28,
                        offset: const Offset(0, 8),
                      ),
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.05),
                        blurRadius: 10,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: [
                              widget.badgeColor,
                              widget.badgeColor == AppColors.primary
                                  ? const Color(0xFFFF6584)
                                  : widget.badgeColor.withValues(alpha: 0.85),
                            ],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                              color: widget.badgeColor.withValues(alpha: 0.38),
                              blurRadius: 12,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Icon(
                          widget.icon,
                          color: widget.iconColor,
                          size: 26,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        widget.message,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Color(0xFF0F172A), // Slate 900 rõ nét, hài hòa trên nền trắng
                          fontSize: 13.5,
                          fontWeight: FontWeight.w800,
                          height: 1.25,
                          letterSpacing: 0.1,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
