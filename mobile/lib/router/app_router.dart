import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../screens/main_wrapper.dart';
import '../screens/home_screen.dart';
import '../screens/category_screen.dart';
import '../screens/court_booking_screen.dart';
import '../screens/cart_screen.dart';
import '../screens/order_screen.dart';
import '../screens/profile_screen.dart';
import '../screens/product_detail_screen.dart';
import '../screens/product_list_screen.dart';
import '../screens/login_screen.dart';
import '../screens/register_screen.dart';
import '../screens/forgot_password_screen.dart';
import '../screens/change_password_screen.dart';
import '../screens/checkout_screen.dart';
import '../screens/payment_webview_screen.dart';
import '../screens/order_success_screen.dart';
import '../screens/order_detail_screen.dart';
import '../screens/address_screen.dart';
import '../screens/booking_history_screen.dart';
import '../screens/coupon_screen.dart';
import '../screens/my_coupons_screen.dart';
import '../screens/edit_profile_screen.dart';
import '../screens/favorite_screen.dart';
import '../screens/flash_sale_screen.dart';
import '../screens/notification_screen.dart';
import '../screens/pos_scanner_screen.dart';
import '../screens/return_requests_screen.dart';
import '../screens/review_screen.dart';
import '../screens/onboarding_screen.dart';
import '../screens/search_screen.dart';
import '../screens/create_return_request_screen.dart';
import '../screens/loyalty_screen.dart';
import '../screens/chat_screen.dart';

final GlobalKey<NavigatorState> rootNavigatorKey = GlobalKey<NavigatorState>();
final GlobalKey<NavigatorState> shellNavigatorHomeKey = GlobalKey<NavigatorState>(debugLabel: 'homeTab');
final GlobalKey<NavigatorState> shellNavigatorShopKey = GlobalKey<NavigatorState>(debugLabel: 'shopTab');
final GlobalKey<NavigatorState> shellNavigatorCourtKey = GlobalKey<NavigatorState>(debugLabel: 'courtTab');
final GlobalKey<NavigatorState> shellNavigatorMeKey = GlobalKey<NavigatorState>(debugLabel: 'meTab');

GoRouter createRouter({required bool isFirstLaunch}) {
  return GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: isFirstLaunch ? '/onboarding' : '/home',
    routes: [
      GoRoute(
        path: '/onboarding',
        builder: (context, state) => const OnboardingScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/register',
        builder: (context, state) => const RegisterScreen(),
      ),
      GoRoute(
        path: '/forgot-password',
        builder: (context, state) => const ForgotPasswordScreen(),
      ),
      GoRoute(
        path: '/search',
        builder: (context, state) => const SearchScreen(),
      ),
      
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          return MainWrapper(navigationShell: navigationShell);
        },
        branches: [
          StatefulShellBranch(
            navigatorKey: shellNavigatorHomeKey,
            routes: [
              GoRoute(
                path: '/home',
                builder: (context, state) => const HomeScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            navigatorKey: shellNavigatorShopKey,
            routes: [
              GoRoute(
                path: '/shop',
                builder: (context, state) => const CategoryScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            navigatorKey: shellNavigatorCourtKey,
            routes: [
              GoRoute(
                path: '/court',
                builder: (context, state) => const CourtBookingScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            navigatorKey: shellNavigatorMeKey,
            routes: [
              GoRoute(
                path: '/me',
                builder: (context, state) => const ProfileScreen(),
              ),
            ],
          ),
        ],
      ),

      // Global routes (pushed on top of root navigator)
      GoRoute(
        path: '/cart',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) {
          return CartScreen(
            onContinueShopping: () {
              context.go('/shop');
            },
          );
        },
      ),
      GoRoute(
        path: '/orders',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) {
          final tabParam = state.uri.queryParameters['tab'];
          final initialIndex = int.tryParse(tabParam ?? '') ?? (state.extra as int? ?? 0);
          return OrderScreen(initialIndex: initialIndex);
        },
      ),
      GoRoute(
        path: '/product-detail',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) {
          final product = state.extra as Map<String, dynamic>;
          return ProductDetailScreen(product: product);
        },
      ),
      GoRoute(
        path: '/product-list',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) {
          final extra = state.extra as Map<String, dynamic>?;
          return ProductListScreen(
            categoryName: extra?['categoryName'],
            categoryId: extra?['categoryId'],
            searchQuery: extra?['searchQuery'],
          );
        },
      ),
      GoRoute(
        path: '/checkout',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) {
          final extra = state.extra as Map<String, dynamic>?;
          return CheckoutScreen(
            initialCoupon: extra?['appliedCoupon'] as Map<String, dynamic>?,
            initialDiscount: extra?['discountAmount'] as int?,
          );
        },
      ),
      GoRoute(
        path: '/payment-webview',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) {
          final extra = state.extra as Map<String, dynamic>;
          return PaymentWebviewScreen(
            url: extra['url'] as String,
            paymentMethod: extra['paymentMethod'] as String,
            orderCode: extra['orderCode'] as String?,
            grandTotal: extra['grandTotal'] as num?,
          );
        },
      ),
      GoRoute(
        path: '/order-success',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) {
          final extra = state.extra as Map<String, dynamic>?;
          return OrderSuccessScreen(
            orderCode: extra?['orderCode'] as String?,
            grandTotal: extra?['grandTotal'] as num?,
            orderId: extra?['orderId']?.toString(),
          );
        },
      ),
      GoRoute(
        path: '/order-detail',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) {
          final orderId = state.extra as String;
          return OrderDetailScreen(orderId: orderId);
        },
      ),
      GoRoute(
        path: '/address',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) => const AddressScreen(),
      ),
      GoRoute(
        path: '/booking-history',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) => const BookingHistoryScreen(),
      ),
      GoRoute(
        path: '/change-password',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) => const ChangePasswordScreen(),
      ),
      GoRoute(
        path: '/coupon',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) => const CouponScreen(),
      ),
      GoRoute(
        path: '/my-coupons',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) => const MyCouponsScreen(),
      ),
      GoRoute(
        path: '/edit-profile',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) {
          final user = state.extra as Map<String, dynamic>;
          return EditProfileScreen(userData: user);
        },
      ),
      GoRoute(
        path: '/favorite',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) => const FavoriteScreen(),
      ),
      GoRoute(
        path: '/flash-sale',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) => const FlashSaleScreen(),
      ),
      GoRoute(
        path: '/notification',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) => const NotificationScreen(),
      ),
      GoRoute(
        path: '/pos-scanner',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) => const PosScannerScreen(),
      ),
      GoRoute(
        path: '/return-requests',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) => const ReturnRequestsScreen(),
      ),
      GoRoute(
        path: '/review',
        parentNavigatorKey: rootNavigatorKey,
        builder: (context, state) {
          final extra = state.extra as Map<String, dynamic>;
          return ReviewScreen(
            orderItem: extra['orderItem'],
            productId: extra['productId'],
            productName: extra['productName'],
            productImage: extra['productImage'],
          );
        },
      ),
      GoRoute(
        path: '/create-return/:orderId',
        builder: (context, state) {
          final orderId = state.pathParameters['orderId']!;
          return CreateReturnRequestScreen(orderId: orderId);
        },
      ),
      GoRoute(
        path: '/loyalty',
        builder: (context, state) => const LoyaltyScreen(),
      ),
      GoRoute(
        path: '/chat',
        builder: (context, state) => ChatScreen(
          inquiryProduct: state.extra as Map<String, dynamic>?,
        ),
      ),
    ],
  );
}
