import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../config/app_theme.dart';
import '../providers/cart_provider.dart';

class MainWrapper extends StatefulWidget {
  final StatefulNavigationShell navigationShell;

  const MainWrapper({super.key, required this.navigationShell});

  @override
  State<MainWrapper> createState() => _MainWrapperState();
}

class _MainWrapperState extends State<MainWrapper> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CartProvider>().fetchCart(silent: true);
    });
  }

  void _onItemTapped(int index) {
    widget.navigationShell.goBranch(
      index,
      initialLocation: index == widget.navigationShell.currentIndex,
    );
  }

  @override
  Widget build(BuildContext context) {
    final selectedIndex = widget.navigationShell.currentIndex;
    return Scaffold(
      body: widget.navigationShell,
      bottomNavigationBar: _buildBottomNavigationBar(selectedIndex),
    );
  }

  Widget _buildBottomNavigationBar(int selectedIndex) {
    final items = const [
      _BottomNavItem(
        icon: Icons.home_outlined,
        activeIcon: Icons.home_rounded,
        label: 'Trang chủ',
      ),
      _BottomNavItem(
        icon: Icons.shopping_bag_outlined,
        activeIcon: Icons.shopping_bag_rounded,
        label: 'Cửa hàng',
      ),
      _BottomNavItem(
        icon: Icons.sports_tennis_outlined,
        activeIcon: Icons.sports_tennis_rounded,
        label: 'Sân cầu',
      ),
      _BottomNavItem(
        icon: Icons.person_outline_rounded,
        activeIcon: Icons.person_rounded,
        label: 'Tài khoản',
      ),
    ];

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
        border: const Border(
          top: BorderSide(color: Color(0xFFF1F5F9), width: 1),
        ),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.only(top: 6, bottom: 6),
          child: Row(
            children: List.generate(items.length, (index) {
              final item = items[index];
              final isSelected = selectedIndex == index;
              return Expanded(
                child: InkWell(
                  onTap: () {
                    HapticFeedback.selectionClick();
                    _onItemTapped(index);
                  },
                  splashFactory: NoSplash.splashFactory,
                  highlightColor: Colors.transparent,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 3.5),
                        decoration: BoxDecoration(
                          color: isSelected ? AppColors.primarySoft : Colors.transparent,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Icon(
                          isSelected ? item.activeIcon : item.icon,
                          size: 22,
                          color: isSelected ? AppColors.primary : const Color(0xFF94A3B8),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        item.label,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 11.5,
                          fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                          color: isSelected ? AppColors.primary : const Color(0xFF64748B),
                          letterSpacing: -0.2,
                          height: 1.15,
                        ),
                      ),
                    ],
                  ),
                ),
              );
            }),
          ),
        ),
      ),
    );
  }
}

class _BottomNavItem {
  final IconData icon;
  final IconData activeIcon;
  final String label;

  const _BottomNavItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
  });
}
