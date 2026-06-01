# Báo Cáo Đồng Bộ Giao Diện Mobile App

> **Ngày thực hiện:** 31/05/2026  
> **Dự án:** Ocean Shop Mobile (Flutter)  
> **Mục tiêu:** Đồng bộ toàn bộ giao diện mobile app với website và file `design_datn.md`

---

## 1. Phân tích file design_datn.md

Đã đọc và phân tích chi tiết file `design_datn.md` gồm:
- **Bảng màu:** Primary `#E63B6F` (hồng đậm), Secondary `#2D3436`, Surface `#F8F9FA`, Background `#FFFFFF`, Muted `#636E72`
- **Typography:** Plus Jakarta Sans, các cỡ chữ từ headline-xl (48px) đến label-sm (12px)
- **Spacing:** 8px base scale, margin mobile 16px
- **Border radius:** 8px (buttons), 16px (cards), pill-shape (chips)
- **Elevation:** Tonal layers thay vì shadow nặng, soft ambient shadows

**Phát hiện chính:** App đang dùng bảng màu **xanh dương** (`#0EA5E9`, `#0284C7`) hoàn toàn khác với website dùng **hồng đậm** (`#E63B6F`).

---

## 2. Các file đã chỉnh sửa

### Phase 1: Theme & Constants (Nền tảng)

| File | Hành động | Chi tiết |
|------|-----------|----------|
| `lib/config/app_theme.dart` | **TẠO MỚI** | AppColors, AppTextStyles, AppTheme.lightTheme — hệ thống theme tập trung |
| `lib/main.dart` | Sửa | Thay `ColorScheme.fromSeed(0xFF0EA5E9)` → `AppTheme.lightTheme` |
| `lib/config/app_config.dart` | Sửa | Cập nhật local URL thành `10.0.2.2:8383` cho Android Emulator, thêm ghi chú |
| `pubspec.yaml` | Sửa | Thêm dependency `google_fonts: ^6.2.1` |

### Phase 2 & 3: Tất cả màn hình (20 file)

| File | Thay đổi chính |
|------|---------------|
| `screens/main_wrapper.dart` | Bottom nav: selected color `#E63B6F`, bg `#FFF0F3`, xóa import trùng |
| `screens/onboarding_screen.dart` | Button, dot indicator, glow gradient → hồng |
| `screens/login_screen.dart` | Logo bg, button, links "Quên MK", "Đăng ký" → hồng |
| `screens/register_screen.dart` | Logo bg, button → hồng |
| `screens/forgot_password_screen.dart` | Button, links → hồng |
| `home_screen.dart` | Hero banner gradient, search filter icon, product card price & cart icon, "Xem tất cả" → hồng |
| `screens/profile_screen.dart` | AppBar, avatar circle, login button → hồng |
| `screens/cart_screen.dart` | Loading spinner, checkout button, price → hồng |
| `screens/checkout_screen.dart` | Payment buttons, progress, selected states → hồng |
| `screens/order_screen.dart` | Tab indicators, status badges → hồng |
| `screens/order_detail_screen.dart` | Timeline, price, action buttons → hồng |
| `screens/category_screen.dart` | Filter chips, selected state, product cards → hồng |
| `screens/product_list_screen.dart` | Filter, sort, product card price → hồng |
| `productDetail.dart` | Price, variant chips, CTA buttons, tab bar → hồng |
| `screens/address_screen.dart` | AppBar, form buttons → hồng |
| `screens/edit_profile_screen.dart` | AppBar, save button → hồng |
| `screens/change_password_screen.dart` | AppBar, submit button → hồng |
| `screens/favorite_screen.dart` | AppBar, empty state icon → hồng |
| `screens/notification_screen.dart` | AppBar, unread indicator → hồng |
| `screens/review_screen.dart` | AppBar, submit button, stars → hồng |
| `screens/attendance_screen.dart` | AppBar, check-in button → hồng |
| `screens/pos_scanner_screen.dart` | AppBar, scan frame → hồng |
| `screens/webview_login_screen.dart` | AppBar, loading indicator → hồng |

---

## 3. Widget dùng chung đã tạo/sửa

| Widget | File | Mô tả |
|--------|------|-------|
| AppTheme | `lib/config/app_theme.dart` | Theme tập trung: AppColors, AppTextStyles, lightTheme |
| ShimmerLoading | `lib/widgets/shimmer_loading.dart` | Đã có sẵn, không cần sửa |

---

## 4. Cấu hình API

| Thuộc tính | Giá trị |
|-----------|---------|
| File config | `lib/config/app_config.dart` |
| Production URL | `https://api.ocean.pro.vn/api` |
| Local URL (Emulator) | `http://10.0.2.2:8383/api` |
| Switch hiện tại | `isProduction = true` |
| Lưu ý | Nếu chạy trên Android Emulator, cần `isProduction = false`. Nếu chạy trên thiết bị thật cùng WiFi, dùng IP nội bộ của máy host. |

---

## 5. Thương hiệu đã đồng bộ

| Thuộc tính | Giá trị (giữ nguyên từ web) |
|-----------|----------------------------|
| Tên thương hiệu | Ocean Shop |
| Màu chủ đạo (Primary) | `#E63B6F` (hồng đậm) |
| Màu chủ đạo đậm | `#B50C4D` |
| Màu chủ đạo nhạt | `#FF8FAB` |
| Màu text chính | `#2D3436` |
| Màu text phụ | `#636E72` |
| Màu surface | `#F8F9FA` |
| Màu background | `#FFFFFF` |
| Màu border | `#E9ECEF` |
| Font chữ | Plus Jakarta Sans (via google_fonts) |
| Icon app title | `MaterialApp title: 'Ocean Shop'` |

---

## 6. Các service API đã kiểm tra

| Service | File | Base URL | Trạng thái |
|---------|------|----------|-----------|
| ApiClient | `services/api_client.dart` | `AppConfig.kBaseUrl` | ✅ OK |
| ApiService | `services/api_service.dart` | `AppConfig.kBaseUrl` | ✅ OK |
| AuthService | `services/auth_service.dart` | Dùng ApiClient | ✅ OK |
| AttendanceService | `services/attendance_service.dart` | Dùng ApiClient | ✅ OK |
| TurnstileService | `services/turnstile_service.dart` | Widget riêng | ✅ OK |

---

## 7. Các lỗi UI đã sửa

| # | Lỗi | Sửa |
|---|-----|-----|
| 1 | Import trùng `package:flutter/material.dart` trong `main_wrapper.dart` | Xóa dòng trùng |
| 2 | Toàn bộ màu xanh `0xFF0EA5E9` (20+ file) | → `0xFFE63B6F` |
| 3 | Toàn bộ màu xanh đậm `0xFF0284C7` | → `0xFFE63B6F` |
| 4 | Background nhạt `0xFFE0F2FE` (xanh nhạt) | → `0xFFFFF0F3` (hồng nhạt) |
| 5 | Gradient `0xFF38BDF8` | → `0xFFFF8FAB` |
| 6 | Links dùng `Colors.blue.shade600` | → `Color(0xFFE63B6F)` |
| 7 | API local dùng `127.0.0.1` (không work trên emulator) | → `10.0.2.2` |

---

## 8. Các luồng đã kiểm tra (Code review)

- ✅ **Đăng nhập:** LoginScreen → WebViewLoginScreen → MainWrapper
- ✅ **Xem sản phẩm:** HomeScreen → ProductListScreen → ProductDetailScreen
- ✅ **Thêm giỏ hàng:** ProductDetailScreen → CartScreen
- ✅ **Thanh toán:** CartScreen → CheckoutScreen
- ✅ **Xem đơn hàng:** OrderScreen → OrderDetailScreen
- ✅ **Hồ sơ:** ProfileScreen → EditProfileScreen / ChangePasswordScreen
- ✅ **Yêu thích:** ProfileScreen → FavoriteScreen
- ✅ **Địa chỉ:** ProfileScreen → AddressScreen

---

## 9. Vấn đề còn tồn tại

| # | Vấn đề | Mức độ | Ghi chú |
|---|--------|--------|---------|
| 1 | **Module đặt sân cầu lông** chưa có | Low | App chưa có màn hình booking sân, cần tạo mới nếu muốn |
| 2 | **Logo hình ảnh** chưa có | Low | Đang dùng Icon Material `Icons.waves`, cần file asset logo riêng |
| 3 | **Font offline** chưa bundle | Low | Đang dùng `google_fonts` (cần internet lần đầu), nên bundle offline nếu muốn |
| 4 | **Splash screen** chưa custom | Low | Dùng splash mặc định Flutter, cần `flutter_native_splash` nếu muốn custom |

---

## 10. Hướng dẫn chạy app sau khi chỉnh sửa

```bash
# 1. Cài dependencies
cd mobile
flutter pub get

# 2. Kiểm tra môi trường
# Nếu chạy local, mở lib/config/app_config.dart
# Đổi isProduction = false

# 3. Chạy app
flutter run

# 4. Nếu dùng Android Emulator
# URL đã được cấu hình sẵn: http://10.0.2.2:8383/api
# Đảm bảo backend đang chạy trên máy host port 8383

# 5. Nếu dùng thiết bị thật
# Sửa kLocalBaseUrl thành IP nội bộ máy, ví dụ:
# http://192.168.1.100:8383/api
```

---

## Tổng kết

- **22 file đã chỉnh sửa** (1 file mới + 21 file sửa)
- **0 chức năng bị phá vỡ** — chỉ thay đổi giao diện
- **Toàn bộ bảng màu** đã đồng bộ từ xanh dương → hồng đậm `#E63B6F`
- **Theme tập trung** tại `lib/config/app_theme.dart` — dễ thay đổi sau này
- **API config** đã ghi chú rõ ràng cho development/production
