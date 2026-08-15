import 'package:flutter/material.dart';
import '../config/app_theme.dart';

enum AppBadgeType { primary, success, warning, error, info, neutral }

class AppBadge extends StatelessWidget {
  final String label;
  final IconData? icon;
  final AppBadgeType type;
  final double fontSize;
  final EdgeInsetsGeometry padding;
  final double borderRadius;

  const AppBadge({
    super.key,
    required this.label,
    this.icon,
    this.type = AppBadgeType.primary,
    this.fontSize = 11,
    this.padding = const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    this.borderRadius = 8,
  });

  @override
  Widget build(BuildContext context) {
    Color bg;
    Color fg;

    switch (type) {
      case AppBadgeType.primary:
        bg = AppColors.primaryContainer;
        fg = AppColors.primary;
        break;
      case AppBadgeType.success:
        bg = AppColors.successLight;
        fg = AppColors.success;
        break;
      case AppBadgeType.warning:
        bg = AppColors.warningLight;
        fg = AppColors.warning;
        break;
      case AppBadgeType.error:
        bg = AppColors.errorLight;
        fg = AppColors.error;
        break;
      case AppBadgeType.info:
        bg = AppColors.infoLight;
        fg = AppColors.info;
        break;
      case AppBadgeType.neutral:
        bg = AppColors.surfaceDim;
        fg = AppColors.textSecondary;
        break;
    }

    return Container(
      padding: padding,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(borderRadius),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: fontSize + 2, color: fg),
            const SizedBox(width: 4),
          ],
          Text(
            label,
            style: TextStyle(
              fontSize: fontSize,
              fontWeight: FontWeight.w700,
              color: fg,
              letterSpacing: 0.1,
            ),
          ),
        ],
      ),
    );
  }
}
