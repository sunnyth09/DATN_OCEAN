import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/cart_provider.dart';

class MainWrapper extends StatefulWidget {
  final StatefulNavigationShell navigationShell;

  const MainWrapper({super.key, required this.navigationShell});

  @override
  State<MainWrapper> createState() => _MainWrapperState();
}

class _MainWrapperState extends State<MainWrapper> {
  void _refreshCart() {
    if (context.read<AuthProvider>().isAuthenticated) {
      context.read<CartProvider>().fetchCart(silent: true);
    }
  }

  void _onItemTapped(int index) {
    if (index >= 2) {
      final loggedIn = context.read<AuthProvider>().isAuthenticated;
      if (!loggedIn) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Vui lòng đăng nhập để tiếp tục')),
        );
        context.push('/login');
        return;
      }
    }

    widget.navigationShell.goBranch(
      index,
      initialLocation: index == widget.navigationShell.currentIndex,
    );
    if (index == 3) _refreshCart();
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
    final cartBadgeCount = context.watch<CartProvider>().itemCount;
    
    return NavigationBar(
      selectedIndex: selectedIndex,
      onDestinationSelected: _onItemTapped,
      backgroundColor: Colors.white,
      indicatorColor: AppColors.primarySoft,
      elevation: 8,
      shadowColor: Colors.black.withValues(alpha: 0.1),
      destinations: [
        const NavigationDestination(
          icon: Icon(Icons.home_outlined),
          selectedIcon: Icon(Icons.home, color: AppColors.primary),
          label: 'Home',
        ),
        const NavigationDestination(
          icon: Icon(Icons.grid_view_outlined),
          selectedIcon: Icon(Icons.grid_view, color: AppColors.primary),
          label: 'Shop',
        ),
        const NavigationDestination(
          icon: Icon(Icons.sports_tennis_outlined),
          selectedIcon: Icon(Icons.sports_tennis, color: AppColors.primary),
          label: 'Sân',
        ),
        NavigationDestination(
          icon: Badge(
            isLabelVisible: cartBadgeCount > 0,
            label: Text(cartBadgeCount > 99 ? '99+' : cartBadgeCount.toString()),
            child: const Icon(Icons.shopping_cart_outlined),
          ),
          selectedIcon: Badge(
            isLabelVisible: cartBadgeCount > 0,
            label: Text(cartBadgeCount > 99 ? '99+' : cartBadgeCount.toString()),
            child: const Icon(Icons.shopping_cart, color: AppColors.primary),
          ),
          label: 'Cart',
        ),
        const NavigationDestination(
          icon: Icon(Icons.receipt_long_outlined),
          selectedIcon: Icon(Icons.receipt_long, color: AppColors.primary),
          label: 'Orders',
        ),
        const NavigationDestination(
          icon: Icon(Icons.person_outline),
          selectedIcon: Icon(Icons.person, color: AppColors.primary),
          label: 'Me',
        ),
      ],
    );
  }
}

