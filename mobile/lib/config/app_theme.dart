import 'package:flutter/material.dart';

/// ============================================================
/// HỆ THỐNG THEME TẬP TRUNG - ĐỒNG BỘ VỚI WEBSITE
/// ============================================================
/// Tất cả màu sắc, typography, styles được định nghĩa ở đây.
/// Dựa theo file design_datn.md và website hiện tại.
/// ============================================================

class AppColors {
  AppColors._();

  // ── Màu chủ đạo (Primary) ──
  static const Color primary = Color(0xFFE63B6F);
  static const Color primaryDark = Color(0xFFB50C4D);
  static const Color primaryLight = Color(0xFFFF8FAB);
  static const Color primaryContainer = Color(0xFFD82F65);
  static const Color onPrimary = Color(0xFFFFFFFF);

  // ── Màu chữ / Text ──
  static const Color textPrimary = Color(0xFF2D3436);
  static const Color textSecondary = Color(0xFF636E72);
  static const Color textMuted = Color(0xFF94A3B8);
  static const Color textDark = Color(0xFF0F172A);
  static const Color textLabel = Color(0xFF334155);

  // ── Bề mặt / Surface ──
  static const Color surface = Color(0xFFF8F9FA);
  static const Color surfaceDim = Color(0xFFD9DADB);
  static const Color background = Color(0xFFFFFFFF);
  static const Color cardBackground = Color(0xFFFFFFFF);

  // ── Viền / Border ──
  static const Color border = Color(0xFFE9ECEF);
  static const Color borderLight = Color(0xFFF1F3F5);
  static const Color divider = Color(0xFFE2E8F0);

  // ── Trạng thái ──
  static const Color success = Color(0xFF10B981);
  static const Color error = Color(0xFFEF4444);
  static const Color warning = Color(0xFFF59E0B);
  static const Color info = Color(0xFF3B82F6);

  // ── Tertiary (xanh lá) ──
  static const Color tertiary = Color(0xFF006B2D);

  // ── Overlay / Shadow ──
  static const Color shadowColor = Color(0x14293346); // rgba(45, 52, 70, 0.08)

  // ── Primary nhạt (cho chip, badge, selected bg) ──
  static Color get primarySoft => primary.withOpacity(0.10);
  static Color get primarySoftBg => primary.withOpacity(0.06);
}

class AppTextStyles {
  AppTextStyles._();

  // ── Headlines ──
  static const TextStyle headlineXl = TextStyle(
    fontSize: 48, fontWeight: FontWeight.w800, height: 56 / 48,
    letterSpacing: -0.02 * 48, color: AppColors.textDark,
  );
  static const TextStyle headlineLg = TextStyle(
    fontSize: 32, fontWeight: FontWeight.w700, height: 40 / 32,
    letterSpacing: -0.01 * 32, color: AppColors.textDark,
  );
  static const TextStyle headlineLgMobile = TextStyle(
    fontSize: 28, fontWeight: FontWeight.w700, height: 36 / 28,
    color: AppColors.textDark,
  );
  static const TextStyle headlineMd = TextStyle(
    fontSize: 24, fontWeight: FontWeight.w700, height: 32 / 24,
    color: AppColors.textDark,
  );

  // ── Body ──
  static const TextStyle bodyLg = TextStyle(
    fontSize: 18, fontWeight: FontWeight.w400, height: 28 / 18,
    color: AppColors.textPrimary,
  );
  static const TextStyle bodyMd = TextStyle(
    fontSize: 16, fontWeight: FontWeight.w400, height: 24 / 16,
    color: AppColors.textPrimary,
  );

  // ── Labels ──
  static const TextStyle labelMd = TextStyle(
    fontSize: 14, fontWeight: FontWeight.w600, height: 20 / 14,
    letterSpacing: 0.01 * 14, color: AppColors.textPrimary,
  );
  static const TextStyle labelSm = TextStyle(
    fontSize: 12, fontWeight: FontWeight.w700, height: 16 / 12,
    letterSpacing: 0.05 * 12, color: AppColors.textPrimary,
  );
}

class AppTheme {
  AppTheme._();

  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(
        seedColor: AppColors.primary,
        primary: AppColors.primary,
        onPrimary: AppColors.onPrimary,
        secondary: const Color(0xFF586062),
        surface: AppColors.surface,
        error: AppColors.error,
        brightness: Brightness.light,
      ),
      scaffoldBackgroundColor: AppColors.surface,
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.primary,
        foregroundColor: AppColors.onPrimary,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: TextStyle(
          fontSize: 18, fontWeight: FontWeight.w700,
          color: AppColors.onPrimary,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.primary,
          foregroundColor: AppColors.onPrimary,
          elevation: 0,
          padding: const EdgeInsets.symmetric(vertical: 16),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
          textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.background,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.primary, width: 2),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        hintStyle: const TextStyle(color: AppColors.textMuted, fontSize: 14),
      ),
      cardTheme: CardThemeData(
        color: AppColors.cardBackground,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: const BorderSide(color: AppColors.borderLight),
        ),
      ),
      dividerTheme: const DividerThemeData(color: AppColors.divider, thickness: 1),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: AppColors.background,
        selectedItemColor: AppColors.primary,
        unselectedItemColor: AppColors.textMuted,
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.primary,
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.textDark,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      fontFamily: 'Plus Jakarta Sans',
    );
  }
}
