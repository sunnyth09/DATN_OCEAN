import 'dart:convert';
import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../config/app_theme.dart';
import '../home_screen.dart';
import '../services/auth_service.dart';
import 'login_screen.dart';
import 'category_screen.dart';
import 'cart_screen.dart';
import 'order_screen.dart';
import 'profile_screen.dart';


class MainWrapper extends StatefulWidget {
  final int initialIndex;

  const MainWrapper({super.key, this.initialIndex = 0});

  @override
  State<MainWrapper> createState() => _MainWrapperState();
}

class _MainWrapperState extends State<MainWrapper> {
  late int _selectedIndex;
  int _cartBadgeCount = 0;

  @override
  void initState() {
    super.initState();
    _selectedIndex = widget.initialIndex;
    _fetchCartCount();
  }

  Future<void> _fetchCartCount() async {
    final loggedIn = await AuthService.isLoggedIn();
    if (!loggedIn) return;
    try {
      final res = await ApiClient().dio.get('/cart');
      if (res.statusCode == 200) {
        final data = res.data['data'];
        if (data != null && data['items'] != null) {
          int count = 0;
          for (var item in data['items']) {
            count += (int.tryParse(item['quantity'].toString()) ?? 1);
          }
          if (mounted) setState(() => _cartBadgeCount = count);
        }
      }
    } catch (_) {}
  }

 
  void _onItemTapped(int index) async {
    // Chặn luồng nếu nhấn tab yêu cầu đăng nhập
    if (index >= 2) {
      final loggedIn = await AuthService.isLoggedIn();
      if (!loggedIn) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Vui lòng đăng nhập để tiếp tục')),
          );
          Navigator.push(context, MaterialPageRoute(builder: (context) => const LoginScreen()));
        }
        return;
      }
    }
    setState(() {
      _selectedIndex = index;
    });
    _fetchCartCount();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _selectedIndex,
        children: [
          const HomeScreen(),
          const CategoryScreen(),
          CartScreen(key: ValueKey('cart_$_selectedIndex')), // Recreates to fetch fresh data
          OrderScreen(key: ValueKey('order_$_selectedIndex')), // Also recreate OrderScreen
          ProfileScreen(key: ValueKey('profile_$_selectedIndex')),
        ],
      ),
      bottomNavigationBar: _buildBottomNavigationBar(),
    );
  }

  Widget _buildBottomNavigationBar() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10, offset: const Offset(0, -4)),
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _buildNavItem(Icons.home_outlined, Icons.home, 'Trang chủ', 0),
              _buildNavItem(Icons.grid_view_outlined, Icons.grid_view, 'Sản phẩm', 1),
              _buildNavItem(Icons.shopping_cart_outlined, Icons.shopping_cart, 'Giỏ hàng', 2, badgeCount: _cartBadgeCount),
              _buildNavItem(Icons.receipt_long_outlined, Icons.receipt_long, 'Đơn hàng', 3),
              _buildNavItem(Icons.person_outline, Icons.person, 'Cá nhân', 4),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem(IconData unselectedIcon, IconData selectedIcon, String label, int index, {int badgeCount = 0}) {
    final isSelected = _selectedIndex == index;
    return GestureDetector(
      onTap: () => _onItemTapped(index),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primarySoft : Colors.transparent,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Icon(isSelected ? selectedIcon : unselectedIcon, color: isSelected ? AppColors.primary : const Color(0xFF94A3B8), size: 24),
                if (badgeCount > 0)
                  Positioned(
                    right: -6, top: -4,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: const BoxDecoration(color: Colors.red, shape: BoxShape.circle),
                      child: Text(badgeCount > 99 ? '99+' : badgeCount.toString(), style: const TextStyle(fontSize: 8, color: Colors.white, fontWeight: FontWeight.bold)),
                    ),
                  ),
              ],
            ),
            if (isSelected) const SizedBox(height: 4),
            if (isSelected) Text(label, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppColors.primary)),
          ],
        ),
      ),
    );
  }
}
