import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../config/app_theme.dart';
import '../providers/auth_provider.dart';
import '../providers/cart_provider.dart';
import 'home_screen.dart';
import 'login_screen.dart';
import 'category_screen.dart';
import 'cart_screen.dart';
import 'order_screen.dart';
import 'profile_screen.dart';
import 'court_booking_screen.dart';

class MainWrapper extends StatefulWidget {
  final int initialIndex;

  const MainWrapper({super.key, this.initialIndex = 0});

  @override
  State<MainWrapper> createState() => _MainWrapperState();
}

class _MainWrapperState extends State<MainWrapper> {
  late int _selectedIndex;

  @override
  void initState() {
    super.initState();
    _selectedIndex = widget.initialIndex.clamp(0, 5);
    if (_selectedIndex == 3) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _refreshCart());
    }
  }

  void _refreshCart() {
    if (context.read<AuthProvider>().isAuthenticated) {
      context.read<CartProvider>().fetchCart(silent: true);
    }
  }

  Future<void> _onItemTapped(int index) async {
    if (index == _selectedIndex) return;

    if (index >= 2) {
      final loggedIn = context.read<AuthProvider>().isAuthenticated;
      if (!loggedIn) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Vui long dang nhap de tiep tuc')),
        );
        Navigator.push(
          context,
          MaterialPageRoute(builder: (context) => const LoginScreen()),
        );
        return;
      }
    }

    setState(() => _selectedIndex = index);
    if (index == 3) _refreshCart();
  }

  void _switchToTab(int index) {
    if (!mounted || index == _selectedIndex) return;
    setState(() => _selectedIndex = index);
    if (index == 3) _refreshCart();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _selectedIndex,
        children: [
          const HomeScreen(),
          const CategoryScreen(),
          const CourtBookingScreen(),
          CartScreen(onContinueShopping: () => _switchToTab(1)),
          const OrderScreen(),
          const ProfileScreen(),
        ],
      ),
      bottomNavigationBar: _buildBottomNavigationBar(),
    );
  }

  Widget _buildBottomNavigationBar() {
    // Badge giỏ hàng tự cập nhật theo CartProvider.
    final cartBadgeCount = context.watch<CartProvider>().itemCount;
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
          child: Row(
            children: [
              _buildNavItem(Icons.home_outlined, Icons.home, 'Home', 0),
              _buildNavItem(
                Icons.grid_view_outlined,
                Icons.grid_view,
                'Shop',
                1,
              ),
              _buildNavItem(
                Icons.sports_tennis_outlined,
                Icons.sports_tennis,
                'San',
                2,
              ),
              _buildNavItem(
                Icons.shopping_cart_outlined,
                Icons.shopping_cart,
                'Cart',
                3,
                badgeCount: cartBadgeCount,
              ),
              _buildNavItem(
                Icons.receipt_long_outlined,
                Icons.receipt_long,
                'Orders',
                4,
              ),
              _buildNavItem(Icons.person_outline, Icons.person, 'Me', 5),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem(
    IconData unselectedIcon,
    IconData selectedIcon,
    String label,
    int index, {
    int badgeCount = 0,
  }) {
    final isSelected = _selectedIndex == index;
    return Expanded(
      child: GestureDetector(
        onTap: () => _onItemTapped(index),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
          decoration: BoxDecoration(
            color: isSelected ? AppColors.primarySoft : Colors.transparent,
            borderRadius: BorderRadius.circular(18),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  Icon(
                    isSelected ? selectedIcon : unselectedIcon,
                    color: isSelected
                        ? AppColors.primary
                        : const Color(0xFF94A3B8),
                    size: 23,
                  ),
                  if (badgeCount > 0)
                    Positioned(
                      right: -6,
                      top: -4,
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: const BoxDecoration(
                          color: Colors.red,
                          shape: BoxShape.circle,
                        ),
                        child: Text(
                          badgeCount > 99 ? '99+' : badgeCount.toString(),
                          style: const TextStyle(
                            fontSize: 8,
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              if (isSelected) const SizedBox(height: 4),
              if (isSelected)
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: AppColors.primary,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
