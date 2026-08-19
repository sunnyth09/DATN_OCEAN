import 'dart:ui';
import 'package:flutter/material.dart';
import '../config/app_theme.dart';

/// Hệ thống Thông báo Đỉnh Cao Chuẩn Sàn TMĐT Quốc Tế (Top Dynamic Capsule & Center HUD)
/// - Phong cách Dynamic Island / Top Floating Capsule (Shopee Mall / TikTok Shop / Taobao tier).
/// - Thả nhẹ từ mép trên đỉnh màn hình, tự rút lên êm ái, KHÔNG BAO GIỜ che đáy hay nút bấm.
/// - Kính mờ Dark Frosted Glass sang trọng, icon hoạt họa gradient rực rỡ.
/// - Hỗ trợ vuốt lên để đóng nhanh hoặc tự động tan biến sau 2.2 giây.
class AppToast {
  AppToast._();

  static OverlayEntry? _currentEntry;

  /// 1. Thông báo Thành Công (Top Capsule)
  static void showSuccess(
    BuildContext context, {
    required String message,
    String? title,
  }) {
    _showTopCapsule(
      context: context,
      message: message,
      title: title,
      icon: Icons.check_rounded,
      badgeGradient: const LinearGradient(
        colors: [Color(0xFF10B981), Color(0xFF059669)],
      ),
      iconColor: Colors.white,
    );
  }

  /// 2. Thông báo Lưu Voucher Thành Công (Top Capsule)
  static void showVoucherSaved(
    BuildContext context, {
    required String message,
  }) {
    _showTopCapsule(
      context: context,
      message: message,
      icon: Icons.confirmation_number_rounded,
      badgeGradient: AppGradients.primary,
      iconColor: Colors.white,
    );
  }

  /// 3. Thông báo Lỗi / Thất Bại (Top Capsule)
  static void showError(
    BuildContext context, {
    required String message,
    String? title,
  }) {
    _showTopCapsule(
      context: context,
      message: message,
      title: title,
      icon: Icons.close_rounded,
      badgeGradient: const LinearGradient(
        colors: [Color(0xFFEF4444), Color(0xFFDC2626)],
      ),
      iconColor: Colors.white,
    );
  }

  /// 4. Thông báo Thông Tin / Lưu Ý (Top Capsule)
  static void showInfo(
    BuildContext context, {
    required String message,
    String? title,
  }) {
    _showTopCapsule(
      context: context,
      message: message,
      title: title,
      icon: Icons.info_outline_rounded,
      badgeGradient: const LinearGradient(
        colors: [Color(0xFF3B82F6), Color(0xFF2563EB)],
      ),
      iconColor: Colors.white,
    );
  }

  /// 5. Thông báo Cảnh Báo / Nhắc Nhở Nhập Liệu (Top Capsule)
  static void showWarning(
    BuildContext context, {
    required String message,
    String? title,
  }) {
    _showTopCapsule(
      context: context,
      message: message,
      title: title,
      icon: Icons.warning_amber_rounded,
      badgeGradient: const LinearGradient(
        colors: [Color(0xFFF59E0B), Color(0xFFD97706)],
      ),
      iconColor: Colors.white,
    );
  }

  /// 5. Center HUD Thêm Vào Giỏ Hàng (Bung nở giữa màn hình)
  static void showAddToCartSuccess(
    BuildContext context, {
    String message = 'Đã thêm vào giỏ hàng!',
  }) {
    _showCenterHUD(
      context: context,
      message: message,
      icon: Icons.shopping_bag_outlined,
      badgeColor: AppColors.primary,
    );
  }

  /// 6. Center HUD Yêu Thích
  static void showFavorite(
    BuildContext context, {
    required String message,
    bool isFavorited = true,
  }) {
    _showCenterHUD(
      context: context,
      message: message,
      icon: isFavorited ? Icons.favorite_rounded : Icons.favorite_border_rounded,
      badgeColor: isFavorited ? const Color(0xFFE11D48) : const Color(0xFF64748B),
    );
  }

  // ── Triển khai Top Floating Dynamic Capsule ──
  static void _showTopCapsule({
    required BuildContext context,
    required String message,
    String? title,
    required IconData icon,
    required Gradient badgeGradient,
    required Color iconColor,
  }) {
    _currentEntry?.remove();
    _currentEntry = null;

    final overlay = Overlay.maybeOf(context);
    if (overlay == null) return;

    late OverlayEntry entry;
    entry = OverlayEntry(
      builder: (ctx) => _TopFloatingCapsuleWidget(
        message: message,
        title: title,
        icon: icon,
        badgeGradient: badgeGradient,
        iconColor: iconColor,
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

  // ── Triển khai Center Frosted Glass HUD ──
  static void _showCenterHUD({
    required BuildContext context,
    required String message,
    required IconData icon,
    required Color badgeColor,
  }) {
    _currentEntry?.remove();
    _currentEntry = null;

    final overlay = Overlay.maybeOf(context);
    if (overlay == null) return;

    late OverlayEntry entry;
    entry = OverlayEntry(
      builder: (ctx) => _CenterFrostedHUDWidget(
        message: message,
        icon: icon,
        badgeColor: badgeColor,
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

/// Widget Top Floating Capsule (Dynamic Island Style)
class _TopFloatingCapsuleWidget extends StatefulWidget {
  final String message;
  final String? title;
  final IconData icon;
  final Gradient badgeGradient;
  final Color iconColor;
  final VoidCallback onDismiss;

  const _TopFloatingCapsuleWidget({
    required this.message,
    this.title,
    required this.icon,
    required this.badgeGradient,
    required this.iconColor,
    required this.onDismiss,
  });

  @override
  State<_TopFloatingCapsuleWidget> createState() => _TopFloatingCapsuleWidgetState();
}

class _TopFloatingCapsuleWidgetState extends State<_TopFloatingCapsuleWidget>
    with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<Offset> _slideAnim;
  late Animation<double> _opacityAnim;
  late Animation<double> _scaleAnim;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 320),
      reverseDuration: const Duration(milliseconds: 220),
    );

    _slideAnim = Tween<Offset>(
      begin: const Offset(0, -0.8),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _ctrl, curve: Curves.easeOutBack));

    _opacityAnim = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _ctrl, curve: Curves.easeOut),
    );

    _scaleAnim = Tween<double>(begin: 0.9, end: 1.0).animate(
      CurvedAnimation(parent: _ctrl, curve: Curves.easeOutBack),
    );

    _ctrl.forward();

    // Tự động đóng sau 2.4 giây
    Future.delayed(const Duration(milliseconds: 2400), () {
      if (mounted) {
        _dismissWithAnim();
      }
    });
  }

  void _dismissWithAnim() {
    _ctrl.reverse().then((_) {
      if (mounted) widget.onDismiss();
    });
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final topPadding = MediaQuery.of(context).padding.top;

    return Positioned(
      top: topPadding + 10,
      left: 16,
      right: 16,
      child: Material(
        type: MaterialType.transparency,
        child: SlideTransition(
          position: _slideAnim,
          child: FadeTransition(
            opacity: _opacityAnim,
            child: ScaleTransition(
              scale: _scaleAnim,
              child: GestureDetector(
                onTap: _dismissWithAnim,
                onVerticalDragUpdate: (details) {
                  if (details.primaryDelta != null && details.primaryDelta! < -4) {
                    _dismissWithAnim();
                  }
                },
                child: Center(
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(30),
                    child: BackdropFilter(
                      filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
                        decoration: BoxDecoration(
                          color: const Color(0xF20F172A), // Luxury Midnight Navy Dark Glass
                          borderRadius: BorderRadius.circular(30),
                          border: Border.all(
                            color: Colors.white.withValues(alpha: 0.16),
                            width: 1.2,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.28),
                              blurRadius: 24,
                              offset: const Offset(0, 8),
                            ),
                          ],
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            // Icon Gradient Orb
                            Container(
                              width: 28,
                              height: 28,
                              decoration: BoxDecoration(
                                gradient: widget.badgeGradient,
                                shape: BoxShape.circle,
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.2),
                                    blurRadius: 6,
                                  ),
                                ],
                              ),
                              child: Center(
                                child: Icon(
                                  widget.icon,
                                  color: widget.iconColor,
                                  size: 17,
                                ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Flexible(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (widget.title != null) ...[
                                    Text(
                                      widget.title!,
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 13.5,
                                        fontWeight: FontWeight.w900,
                                        letterSpacing: 0.1,
                                      ),
                                    ),
                                    const SizedBox(height: 1),
                                  ],
                                  Text(
                                    widget.message,
                                    style: TextStyle(
                                      color: widget.title != null
                                          ? const Color(0xFFCBD5E1)
                                          : Colors.white,
                                      fontSize: 13,
                                      fontWeight: widget.title != null
                                          ? FontWeight.w500
                                          : FontWeight.w700,
                                      letterSpacing: 0.1,
                                    ),
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 4),
                          ],
                        ),
                      ),
                    ),
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

/// Widget Center Frosted HUD (Taobao & Shopee Instant Actions)
class _CenterFrostedHUDWidget extends StatefulWidget {
  final String message;
  final IconData icon;
  final Color badgeColor;
  final VoidCallback onDismiss;

  const _CenterFrostedHUDWidget({
    required this.message,
    required this.icon,
    required this.badgeColor,
    required this.onDismiss,
  });

  @override
  State<_CenterFrostedHUDWidget> createState() => _CenterFrostedHUDWidgetState();
}

class _CenterFrostedHUDWidgetState extends State<_CenterFrostedHUDWidget>
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
              borderRadius: BorderRadius.circular(22),
              child: BackdropFilter(
                filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
                child: Container(
                  constraints: const BoxConstraints(minWidth: 135, maxWidth: 200),
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
                  decoration: BoxDecoration(
                    color: const Color(0xF00F172A), // Dark Frosted Glass sang trọng
                    borderRadius: BorderRadius.circular(22),
                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.16),
                      width: 1.2,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.35),
                        blurRadius: 28,
                        offset: const Offset(0, 8),
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
                              color: widget.badgeColor.withValues(alpha: 0.35),
                              blurRadius: 10,
                              offset: const Offset(0, 3),
                            ),
                          ],
                        ),
                        child: Icon(
                          widget.icon,
                          color: Colors.white,
                          size: 24,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        widget.message,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Colors.white,
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
