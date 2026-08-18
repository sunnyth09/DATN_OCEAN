import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
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
    return NavigationBar(
      height: 60,
      selectedIndex: selectedIndex,
      onDestinationSelected: _onItemTapped,
      backgroundColor: Colors.white,
      indicatorColor: AppColors.primarySoft,
      indicatorShape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      elevation: 10,
      shadowColor: Colors.black.withValues(alpha: 0.08),
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      destinations: const [
        NavigationDestination(
          icon: Icon(Icons.home_outlined),
          selectedIcon: Icon(Icons.home_rounded, color: AppColors.primary),
          label: 'Trang chủ',
        ),
        NavigationDestination(
          icon: Icon(Icons.shopping_bag_outlined),
          selectedIcon: Icon(Icons.shopping_bag_rounded, color: AppColors.primary),
          label: 'Cửa hàng',
        ),
        NavigationDestination(
          icon: Icon(Icons.stadium_outlined),
          selectedIcon: Icon(Icons.stadium_rounded, color: AppColors.primary),
          label: 'Sân cầu',
        ),
        NavigationDestination(
          icon: Icon(Icons.person_outline_rounded),
          selectedIcon: Icon(Icons.person_rounded, color: AppColors.primary),
          label: 'Tài khoản',
        ),
      ],
    );
  }
}
