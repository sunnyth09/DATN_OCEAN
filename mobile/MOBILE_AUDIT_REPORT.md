# 📱 MOBILE AUDIT REPORT — QUYỀN SPORT APP

> **Auditor:** Senior Mobile Architect AI (Flutter / Clean Architecture / Mobile Security)
> **Date:** 2026-06-15
> **Project:** `d:\source_code\laravel\qs_project\mobile`
> **App Name:** Quyền Sport (Sports E-commerce + Court Booking)
> **Flutter SDK:** ^3.11.4 | **Dart SDK:** ^3.11.4

---

## 🔷 EXECUTIVE SUMMARY

Ứng dụng **Quyền Sport** là một Flutter app thương mại điện tử thể thao tích hợp đặt sân. App hiện đang ở giai đoạn **MVP hoàn chỉnh** với đầy đủ flow mua hàng, đặt sân và quản lý tài khoản. Tuy nhiên, dự án chưa áp dụng kiến trúc chuẩn (Clean Architecture / BLoC), quản lý state hoàn toàn bằng `setState`, và thiếu hoàn toàn test coverage. Còn một số rủi ro bảo mật cần xử lý trước khi release production.

---

## 📊 OVERALL SCORE

| Hạng mục            | Điểm | Nhận xét                                     |
|---------------------|------|----------------------------------------------|
| Architecture        | 4/10 | Flat structure, không Clean Architecture     |
| State Management    | 4/10 | Toàn bộ setState, không có BLoC/Provider     |
| API Design          | 6/10 | Dio + interceptor tốt, thiếu repository      |
| Security            | 5/10 | SecureStorage OK, nhưng còn nhiều rủi ro     |
| Performance         | 5/10 | Rebuild nhiều, Timer leak, API gọi dư        |
| UX/UI               | 7/10 | Giao diện đẹp, nhưng còn hardcode màu        |
| Testing             | 1/10 | Gần như không có test                        |
| Maintainability     | 3/10 | File quá lớn, logic lẫn UI                  |
| Feature Completeness| 7/10 | MVP đủ dùng, thiếu push notification        |

### 🎯 Production Readiness Score: **47/100**

> App chưa sẵn sàng production. Cần ít nhất hoàn thành Phase 1-3 trước khi release.

---

## 🏗️ GIAI ĐOẠN 1 — ARCHITECTURE AUDIT

### Folder Structure Hiện Tại

```
mobile/lib/
├── main.dart                 ← Entry point
├── home_screen.dart          ← ⚠️ Đặt ngoài thư mục screens (lỗi tổ chức)
├── productDetail.dart        ← ⚠️ Không theo convention (camelCase thay vì snake_case)
├── config/
│   ├── app_config.dart
│   └── app_theme.dart
├── models/
│   ├── product_model.dart    ← Chỉ 2 fields, quá đơn giản
│   └── court_booking_models.dart
├── screens/ (26 files)
├── services/
│   ├── api_client.dart
│   ├── api_service.dart      ← ⚠️ Dùng http package song song với Dio
│   ├── auth_service.dart
│   ├── attendance_service.dart
│   └── court_booking_service.dart
└── widgets/
    └── shimmer_loading.dart  ← Chỉ 1 widget shared
```

### Vấn Đề Cấu Trúc

| Hạng mục           | Điểm | Ghi chú                                          |
|--------------------|------|--------------------------------------------------|
| Structure          | 4/10 | Flat, file ở sai vị trí, không theo feature     |
| Scalability        | 3/10 | Không theo feature-first, khó mở rộng           |
| Maintainability    | 3/10 | 1 file = 668 lines (home), 1132 lines (court)   |
| Clean Architecture | 2/10 | Không có domain/usecase/repository layer        |

### Chi Tiết Vấn Đề

**Issue A-1: File đặt sai vị trí**
- `lib/home_screen.dart` — nên ở trong `lib/screens/`
- `lib/productDetail.dart` — naming không đúng convention (nên là `product_detail_screen.dart`)

**Issue A-2: Thiếu hoàn toàn Clean Architecture**
- Không có `domain/` layer (entities, use cases, repositories)
- Không có `data/` layer (data sources, DTOs, repository implementations)
- Không có `presentation/` layer phân tách rõ ràng
- Business logic nằm trực tiếp trong State class của màn hình

**Issue A-3: Cấu trúc không theo feature**
```
# ❌ Hiện tại (flat)
lib/screens/cart_screen.dart
lib/screens/checkout_screen.dart

# ✅ Nên theo feature-first
lib/features/cart/presentation/pages/cart_page.dart
lib/features/cart/presentation/bloc/cart_bloc.dart
lib/features/cart/domain/entities/cart_item.dart
lib/features/cart/data/repositories/cart_repository_impl.dart
```

**Issue A-4: Chỉ có 2 Models**
- `product_model.dart` chỉ có 4 fields — quá đơn giản so với data thực tế API
- Hầu hết screens dùng `Map<String, dynamic>` thay vì typed models → không type-safe

---

## 🔄 GIAI ĐOẠN 2 — STATE MANAGEMENT AUDIT

### State Management Pattern Đang Dùng

**100% `setState`** — không có bất kỳ state management library nào được sử dụng.

### Danh Sách Vấn Đề Nghiêm Trọng

---

#### Issue SM-1: Business Logic trong Widget
**Mô tả:** Hàm `fetchProducts()`, `fetchCart()`, `placeOrder()` nằm trực tiếp trong `State` class của widget. Vi phạm Single Responsibility Principle.

**File:**
```
lib/home_screen.dart (Line 40-97)
lib/screens/cart_screen.dart (Line 30-98)
lib/screens/checkout_screen.dart (Line 45-155)
```

**Cách sửa:**
```dart
// Tạo CartRepository
class CartRepository {
  final ApiClient _client;
  Future<CartModel> getCart() async { ... }
  Future<void> updateItem(int id, int qty) async { ... }
}

// Tạo CartBloc/Cubit
class CartCubit extends Cubit<CartState> {
  final CartRepository _repo;
  Future<void> loadCart() async {
    emit(CartLoading());
    final cart = await _repo.getCart();
    emit(CartLoaded(cart));
  }
}
```

---

#### Issue SM-2: Gọi API Trong Widget
**Mô tả:** Nhiều screens gọi API trực tiếp trong `initState` mà không qua service/repository abstraction.

**File:**
```
lib/home_screen.dart (Line 99-103) - initState gọi fetchProducts() và fetchCategories()
lib/screens/profile_screen.dart (Line 35-37)
lib/screens/order_screen.dart (Line 27-28)
lib/screens/main_wrapper.dart (Line 30-31)
```

---

#### Issue SM-3: State Bị Duplicate
**Mô tả:** Logic `_formatPrice()` bị duplicate trong ít nhất **6 files** khác nhau.

**File bị duplicate:**
```
lib/home_screen.dart (Line 657)
lib/screens/cart_screen.dart (Line 123)
lib/screens/checkout_screen.dart (Line 243)
lib/screens/order_screen.dart (Line 68)
lib/screens/product_list_screen.dart (Line 158)
lib/screens/category_screen.dart (Line 286)
```

**Cách sửa:** Tạo `lib/utils/format_utils.dart` và import chung.

---

#### Issue SM-4: Không Có State Dispose Đúng Cách
**Mô tả:** `CourtBookingScreen` dùng `Timer.periodic` — đây là nguồn memory leak tiềm năng.

**File:**
```
lib/screens/court_booking_screen.dart (Line 69)
```
```dart
// ⚠️ Timer.periodic tự động refresh mỗi 15 giây
_refreshTimer = Timer.periodic(const Duration(seconds: 15), (_) => _silentRefresh());
```

**Mức ảnh hưởng:** MEDIUM — dispose() đã được gọi, nhưng nếu screen được recreate nhiều lần (vì dùng ValueKey trong IndexedStack), timer có thể chạy song song.

---

#### Issue SM-5: Search Debounce Sai Cách Trong HomeScreen
**Mô tả:** `home_screen.dart` dùng `Future.delayed` thay vì `Timer` để debounce. Mỗi keystroke tạo một Future mới → nếu user gõ nhanh, nhiều navigation được trigger đồng thời.

**File:**
```
lib/home_screen.dart (Line 188-199)
```
```dart
// ❌ Sai
onChanged: (text) {
  Future.delayed(const Duration(milliseconds: 400), () {
    if (mounted) {
      Navigator.push(...); // Mở màn hình mỗi 400ms khi user gõ!
    }
  });
},
```

**Cách sửa:**
```dart
// ✅ Đúng — dùng Timer với debounce
Timer? _debounce;
onChanged: (text) {
  _debounce?.cancel();
  _debounce = Timer(const Duration(milliseconds: 500), () {
    if (text.trim().isNotEmpty) Navigator.push(...);
  });
},
```

---

## 🌐 GIAI ĐOẠN 3 — API AUDIT

### API Layer Hiện Tại

| Hạng mục           | Trạng thái | Điểm |
|--------------------|-----------|------|
| HTTP Client        | Dio ✅     | -    |
| Repository Pattern | ❌ Không có | -   |
| Service Layer      | Có (nhưng mỏng) ⚠️ | - |
| Interceptor        | ✅ Có (token injection) | - |
| Refresh Token      | ❌ Không có | -   |
| Retry Mechanism    | ❌ Không có | -   |
| Timeout            | ✅ 15s     | -    |
| Error Handling     | ⚠️ Cơ bản  | -    |

| Hạng mục       | Điểm |
|----------------|------|
| API Design     | 5/10 |
| Error Handling | 5/10 |
| Security       | 6/10 |

### Vấn Đề API

**Issue API-1: Dùng 2 HTTP Clients Song Song**
```
lib/services/api_service.dart (Line 2) ← dùng package:http
lib/services/api_client.dart (Line 3)  ← dùng Dio
lib/screens/forgot_password_screen.dart (Line 4) ← dùng http
```
**Mức độ:** HIGH — gây nhất quán, khó maintain, mỗi client có config khác nhau.

**Issue API-2: Không Có Repository Pattern**
- Screens gọi `ApiClient().dio.get(...)` trực tiếp mà không qua abstraction
- Khó mock khi test, khó swap implementation

**Issue API-3: Không Có Retry Mechanism**
- Khi network timeout, chỉ hiện error message, không retry
- Nên implement `RetryInterceptor` với exponential backoff

**Issue API-4: Không Có Refresh Token**
```
lib/services/api_client.dart (Line 84-87)
// ⚠️ Chỉ log lỗi 401, không refresh token
if (e.response?.statusCode == 401) {
  debugPrint('API Error 401: Unauthorized');
}
```
- Khi token hết hạn, user phải logout/login lại thủ công
- Không có cơ chế tự động refresh token

**Issue API-5: GHN ShopId Hardcode**
```
lib/screens/checkout_screen.dart (Line 123)
'ShopId': '5881673', // ⚠️ Hardcoded production value
```

**Issue API-6: Coupon Validation Phía Client (Logic Lỗi)**
```dart
// lib/screens/checkout_screen.dart (Line 162-167)
// ❌ Fetch toàn bộ coupon list rồi filter phía client
final res = await ApiClient().dio.get('/coupons/public');
final List coupons = res.data['data'] ?? [];
final coupon = coupons.firstWhere(
  (c) => c['code']...
```
- Không an toàn — coupon validation phải ở backend
- Nếu coupon list lớn → slow response

---

## 🔐 GIAI ĐOẠN 4 — AUTHENTICATION AUDIT

### Trạng Thái Authentication

| Tính năng          | Trạng thái |
|--------------------|-----------|
| Login              | ✅ Hoàn thiện |
| Register           | ✅ Hoàn thiện |
| Forgot Password    | ✅ OTP via email |
| Social Login       | ❌ UI có nhưng chức năng chưa implement |
| JWT               | ✅ Bearer token |
| Refresh Token      | ❌ Không có |
| Session Timeout    | ❌ Không handle |

### Security Risks

**Issue AUTH-1: Social Login Button Không Hoạt Động [HIGH]**
```dart
// lib/screens/login_screen.dart (Line 158-164)
// UI có Google, Facebook, Apple login nhưng
_buildSocialBtn(Icons.g_mobiledata, const Color(0xFFDB4437)), // ❌ onTap = null
```
- Misleading UX — user click nhưng không có gì xảy ra
- Cần xóa hoặc disable rõ ràng

**Issue AUTH-2: Không Có Email Validation Đúng Cách**
```dart
// lib/screens/forgot_password_screen.dart (Line 36)
if (email.isEmpty || !email.contains('@')) { // ⚠️ Quá đơn giản
```

**Issue AUTH-3: Token Không Được Validate**
```dart
// lib/services/auth_service.dart (Line 99-102)
static Future<bool> isLoggedIn() async {
  final token = await storage.read(key: keyToken);
  return token != null && token.isNotEmpty; // ❌ Không verify token còn hạn
}
```
- Token hết hạn vẫn báo `isLoggedIn = true`
- App sẽ crash khi gọi API và nhận 401

**Issue AUTH-4: Password Không Có Strength Check**
- Register và change password chỉ check độ dài
- Không check complexity (số, ký tự đặc biệt)

---

## 💾 GIAI ĐOẠN 5 — LOCAL STORAGE AUDIT

### Storage Sử Dụng

| Package                | Mục đích             | Đánh giá |
|------------------------|----------------------|---------|
| flutter_secure_storage | Token JWT, user data | ✅ Đúng  |
| shared_preferences     | First launch flag    | ✅ OK    |
| flutter_dotenv         | Env variables        | ⚠️ Xem bên dưới |

### Vấn Đề Storage

**Issue STG-1: .env File Được Bundle Vào App Asset [HIGH]**
```yaml
# pubspec.yaml (Line 76)
assets:
  - .env  # ⚠️ File .env được đưa vào APK/IPA
```
```dart
// main.dart (Line 13)
await dotenv.load(fileName: ".env");
```
- File `.env` được đóng gói trong APK, user có thể decompile và đọc
- `TOKEN_GHN` và các secrets khác bị lộ

**Đề xuất:** Lưu sensitive config trong `--dart-define` khi build, hoặc dùng Firebase Remote Config.

**Issue STG-2: FlutterSecureStorage Khởi Tạo Nhiều Lần**
```dart
// Mỗi method trong auth_service.dart đều new FlutterSecureStorage()
const storage = FlutterSecureStorage(aOptions: AndroidOptions(encryptedSharedPreferences: true));
```
- Nên tạo singleton, tránh overhead khởi tạo

**Đề xuất tối ưu:**
```dart
// ✅ Singleton pattern
class StorageService {
  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
  );
  static Future<String?> getToken() => _storage.read(key: 'access_token');
}
```

---

## 🎨 GIAI ĐOẠN 6 — UI/UX AUDIT

### Đánh Giá Tổng Quan

| Hạng mục     | Điểm | Ghi chú                                |
|--------------|------|----------------------------------------|
| Responsive   | 6/10 | Chưa test tablet, dùng MediaQuery ít  |
| Material 3   | 8/10 | Đã dùng `useMaterial3: true`          |
| Dark Mode    | 0/10 | Không có dark mode                    |
| Theme System | 7/10 | AppColors, AppTextStyles tốt          |
| Accessibility| 2/10 | Thiếu Semantics, không có a11y        |

### Vấn Đề UI/UX

**Issue UI-1: Hardcode Màu Sắc Rải Rác [HIGH]**
Dù đã có `AppColors` nhưng hầu hết screens không dùng:
```dart
// ❌ Hardcode color trong home_screen.dart (Line 151)
color: const Color(0xFF0F172A)

// ❌ Hardcode trong cart_screen.dart (Line 139)
backgroundColor: const Color(0xFFF8FAFC)

// ❌ Hardcode trong checkout_screen.dart (Line 129)
backgroundColor: const Color(0xFFE63B6F)
```
**Files bị ảnh hưởng:** `home_screen.dart`, `cart_screen.dart`, `checkout_screen.dart`, `order_screen.dart`, `profile_screen.dart`, `login_screen.dart`, `register_screen.dart`, `onboarding_screen.dart` — **tất cả các screens**.

**Issue UI-2: Font Không Được Load Đúng Cách [MEDIUM]**
```dart
// app_theme.dart (Line 173)
fontFamily: 'Plus Jakarta Sans', // ⚠️ Font không được khai báo trong pubspec.yaml
```
- Font family `Plus Jakarta Sans` không có trong `pubspec.yaml` fonts section
- App đang dùng `google_fonts` nhưng không reference qua `GoogleFonts.plusJakartaSans()`
- Font sẽ fallback về hệ thống

**Issue UI-3: Onboarding Chỉ Có 1 Trang [MEDIUM]**
```dart
// onboarding_screen.dart - page indicators hiển thị 3 trang
// nhưng thực tế chỉ có 1 nội dung (không có PageView)
```

**Issue UI-4: Widget Quá Lớn [HIGH]**
- `court_booking_screen.dart`: **1132 dòng** — God Widget
- `home_screen.dart`: **668 dòng** — quá lớn, khó bảo trì
- `checkout_screen.dart`: **664 dòng** — nên tách thành sub-widgets

**Issue UI-5: Không Có Accessibility [HIGH]**
- Không có `Semantics` widget
- Không có `tooltip` trên icon buttons
- Không hỗ trợ large text size

**Issue UI-6: Hardcode Strings Tiếng Việt [MEDIUM]**
- Toàn bộ app dùng string literal tiếng Việt
- Không có `l10n` / `intl` localization system
- Khó thêm ngôn ngữ mới sau này

**Issue UI-7: CourtBookingScreen Dùng Unencode Strings**
```dart
// court_booking_screen.dart (nhiều vị trí)
_showSnack('Chon san va khung gio truoc da bro.', isError: true);
'Dat san ngay' // ← không dấu
'Chua co dich vu dang ban.' // ← không dấu
```
Một số string bị thiếu dấu tiếng Việt — lỗi encoding hoặc copy-paste.

---

## ⚡ GIAI ĐOẠN 7 — PERFORMANCE AUDIT

| Hạng mục  | Điểm |
|-----------|------|
| Rendering | 5/10 |
| Memory    | 5/10 |
| Network   | 5/10 |

### Widget Performance

**Issue PF-1: Rebuild Không Cần Thiết [HIGH]**
```dart
// main_wrapper.dart (Line 65-66)
Future<void> _onItemTapped(int index) async {
  ...
  setState(() => _selectedIndex = index);
  _fetchCartCount(); // ⚠️ Gọi API mỗi lần tap nav bar!
}
```
- `_fetchCartCount()` được gọi mỗi khi user tap bottom nav → gọi API `/cart` liên tục

**Issue PF-2: IndexedStack Giữ Tất Cả Screens Trong Memory [MEDIUM]**
```dart
// main_wrapper.dart (Line 71-82)
body: IndexedStack(
  index: _selectedIndex,
  children: [
    const HomeScreen(),      // Luôn ở trong memory
    const CategoryScreen(),  // Luôn ở trong memory
    CourtBookingScreen(...), // Luôn ở trong memory
    CartScreen(...),         // Luôn ở trong memory
    OrderScreen(...),        // Luôn ở trong memory
    ProfileScreen(...),      // Luôn ở trong memory
  ],
),
```
- Tất cả 6 screens được khởi tạo ngay lúc load app
- `CourtBookingScreen` có `Timer.periodic` chạy nền ngay từ đầu

**Issue PF-3: Timer Refresh Mỗi 15 Giây Không Cần Thiết [MEDIUM]**
```dart
// court_booking_screen.dart (Line 69)
_refreshTimer = Timer.periodic(const Duration(seconds: 15), (_) => _silentRefresh());
```
- Timer chạy ngay cả khi user không ở tab đặt sân
- Mỗi 15 giây gọi ít nhất 3 API calls

**Issue PF-4: Image Loading Không Tối Ưu [MEDIUM]**
- Dùng `CachedNetworkImage` nhưng không có `memCacheHeight`/`memCacheWidth`
- Images fullsize được load vào memory mà không resize

**Issue PF-5: Network Requests Gọi Song Song Khi Init HomeScreen [LOW]**
```dart
// home_screen.dart (Line 99-103)
void initState() {
  fetchProducts(); // API 1
  fetchCategories(); // API 2 - không await song song
}
```
Hai requests này nên dùng `Future.wait([...])` để đảm bảo parallel execution có kiểm soát.

### Memory Issues

**Issue MEM-1: ScrollController Không Dispose Ở HomeScreen [HIGH]**
```dart
// home_screen.dart (Line 33)
final ScrollController _scrollController = ScrollController();

// dispose() có gọi _scrollController.dispose() ✅
// nhưng _scrollController không có listener được remove trước đó
```
Nhìn lại: không có listener add → không cần remove. Điều này OK.

**Issue MEM-2: CourtBookingScreen Tạo Nhiều Dio Instance Mới**
```dart
// checkout_screen.dart (Line 119)
final ghnDio = Dio(BaseOptions(...)); // ⚠️ Tạo Dio mới mỗi lần calculate shipping
```
- Nên dùng singleton hoặc closure-scoped Dio cho GHN

---

## 🔒 GIAI ĐOẠN 8 — CODE QUALITY AUDIT

### Code Smells

**Issue CQ-1: _formatPrice() Duplicate 6+ Lần [HIGH]**
- Vi phạm DRY principle nghiêm trọng
- Files: `home_screen.dart`, `cart_screen.dart`, `checkout_screen.dart`, `order_screen.dart`, `product_list_screen.dart`, `category_screen.dart`

**Issue CQ-2: God Widget — CourtBookingScreen [HIGH]**
- 1132 dòng trong 1 file
- Xử lý booking + payment + staff management + QR scan + extension
- Nên tách thành ít nhất 5-6 widget/page riêng

**Issue CQ-3: Dead Import**
```dart
// product_list_screen.dart (Line 3)
import 'dart:convert'; // ⚠️ Không dùng ở đâu trong file này
```

**Issue CQ-4: Dùng Dynamic Type Quá Nhiều [MEDIUM]**
```dart
// Hầu hết screens
List<dynamic> products = [];   // ❌ không type-safe
Map<String, dynamic>? cartData; // ❌ 
dynamic cartItems = [];        // ❌
```
Nên tạo Model class cho từng entity.

**Issue CQ-5: Magic Numbers / Hardcoded Values [MEDIUM]**
```dart
// checkout_screen.dart (Line 22)
int shippingFee = 35000; // Magic number

// court_booking_screen.dart (Line 69)
const Duration(seconds: 15) // Magic number

// checkout_screen.dart (Line 123)
'ShopId': '5881673', // Magic number (production ShopId hardcoded)
```

**Issue CQ-6: Error Handling Không Nhất Quán [MEDIUM]**
```dart
// Nhiều chỗ dùng catch (_) {} — nuốt error không log
} catch (_) {} // ❌ Silent failure

// Một số chỗ log đầy đủ
} catch (e) {
  debugPrint('...');
}
```

**Issue CQ-7: Business Logic Trong UI — _applyCoupon()**
```dart
// checkout_screen.dart (Line 157-194)
Future<void> _applyCoupon() async {
  // Fetch toàn bộ coupon list
  // Filter coupon phù hợp
  // Tính discount amount
  // Hiện SnackBar
  // Tất cả trong 1 hàm trong State class
}
```

---

## 📦 GIAI ĐOẠN 9 — DEPENDENCY AUDIT

### Danh Sách Package

| Package                    | Version   | Trạng thái       |
|----------------------------|-----------|-----------------|
| `http`                     | ^1.2.2    | ⚠️ Trùng với Dio |
| `dio`                      | ^5.9.2    | ✅ Dùng chính    |
| `shared_preferences`       | ^2.5.5    | ✅ OK            |
| `flutter_secure_storage`   | ^8.1.0    | ✅ Tốt           |
| `flutter_dotenv`           | ^6.0.0    | ⚠️ Không an toàn |
| `image_picker`             | ^1.1.2    | ✅ Cần thiết      |
| `mobile_scanner`           | ^7.2.0    | ✅ Cần thiết      |
| `network_info_plus`        | ^8.0.0    | ❓ Không rõ dùng ở đâu |
| `geolocator`               | ^14.0.1   | ❓ Không thấy dùng |
| `cached_network_image`     | ^3.4.1    | ✅ Cần thiết      |
| `shimmer`                  | ^3.0.0    | ✅ OK            |
| `google_fonts`             | ^6.2.1    | ⚠️ Import nhưng không dùng đúng |
| `intl`                     | ^0.20.2   | ✅ Dùng formatter |
| `permission_handler`       | ^12.0.1   | ✅ Cần thiết      |

### Phân Tích

#### Không/Ít Sử Dụng
- `network_info_plus` — không thấy usage trong codebase đã review
- `geolocator` — không thấy usage trong codebase đã review
- `google_fonts` — import trong `pubspec.yaml` nhưng không dùng `GoogleFonts.xxx()` API; font `Plus Jakarta Sans` được khai báo trong theme nhưng không bundle

#### Trùng Chức Năng
- `http` + `dio` đều được import — nên loại bỏ `http`, chỉ dùng `Dio`

#### Nên Thêm
- `flutter_bloc` hoặc `riverpod` — state management
- `freezed` + `json_serializable` — data classes & JSON serialization
- `get_it` — dependency injection
- `go_router` — declarative routing thay vì imperative Navigator.push
- `flutter_localizations` — localization
- `sentry_flutter` hoặc `firebase_crashlytics` — crash reporting

---

## 🔐 GIAI ĐOẠN 10 — MOBILE SECURITY AUDIT

### Android

| Hạng mục              | Trạng thái | Mức độ |
|-----------------------|-----------|-------|
| Proguard/R8          | ❓ Chưa kiểm tra | HIGH  |
| SSL Pinning          | ❌ Không có | HIGH  |
| Root Detection       | ❌ Không có | MEDIUM |
| Certificate Transparency | ❌    | LOW   |

### iOS

| Hạng mục    | Trạng thái | Mức độ |
|-------------|-----------|-------|
| ATS         | ❓ Chưa cấu hình rõ | MEDIUM |
| Keychain    | ✅ Dùng SecureStorage | OK  |

### Critical Security Issues

**🔴 CRITICAL-1: .env File Trong APK**
```yaml
# pubspec.yaml
assets:
  - .env  # ❌ BUNDLED VÀO APK/IPA
```
- User có thể decompile APK và đọc nội dung `.env`
- `TOKEN_GHN` và production URLs bị lộ
- **Fix ngay lập tức trước khi release**

```bash
# Fix: Dùng --dart-define khi build
flutter build apk --dart-define=TOKEN_GHN=xxx --dart-define=API_BASE_URL=xxx
```

**🔴 CRITICAL-2: isProduction Hardcoded False**
```dart
// app_config.dart (Line 36)
static const bool isProduction = false; // ❌ Code switch thủ công
```
- Nếu dev quên đổi, app release sẽ connect về localhost
- Phải dùng `--dart-define` hoặc build flavors

**🔴 HIGH-1: Không Có SSL Pinning**
- Không có SSL certificate pinning
- App dễ bị Man-in-the-Middle attack
- Đặc biệt nguy hiểm vì app xử lý thanh toán (VNPay, MoMo)

**🔴 HIGH-2: Debug Logs Bật Trong Production**
```dart
// api_client.dart (Line 62-68)
debugPrint('══════ API REQUEST ══════');
debugPrint('URL: ${options.baseUrl}${options.path}');
debugPrint('User-Agent: ${options.headers['User-Agent']}');
debugPrint('All Headers: ${options.headers}'); // ⚠️ In ra TOÀN BỘ headers bao gồm Bearer token!
debugPrint('Body: ${options.data}');
```
- Bearer token được print ra logs trong mọi request
- **Phải tắt trong production build**

**🔟 MEDIUM-1: Không Có Root/Jailbreak Detection**
- App không kiểm tra thiết bị bị root
- Dữ liệu trong SecureStorage có thể bị đọc trên thiết bị root

**🟡 LOW-1: Không Có Anti-Debugging**
- App có thể bị debug runtime trên thiết bị root

---

## 🔥 GIAI ĐOẠN 11 — FIREBASE AUDIT

| Tính năng         | Trạng thái | Đề xuất |
|-------------------|-----------|---------|
| Firebase Analytics | ❌ Chưa có | Nên triển khai |
| Firebase Crashlytics | ❌ Chưa có | **Cần thiết trước release** |
| FCM (Push Notification) | ❌ Chưa có | Nên triển khai |
| Firebase Remote Config | ❌ Chưa có | Nên dùng thay .env |
| Firebase App Check | ❌ Chưa có | Tăng security |

**Không có Firebase nào được tích hợp** — đây là điểm trừ lớn cho production readiness.

---

## 🧪 GIAI ĐOẠN 12 — TESTING AUDIT

### Thống Kê Test Coverage

| Loại Test   | Coverage | Files |
|-------------|----------|-------|
| Unit        | ~0%      | 0     |
| Widget      | ~0%      | 1 (boilerplate) |
| Integration | 0%       | 0     |

**Duy nhất 1 file test** là `test/widget_test.dart` — đây là file boilerplate tự động generate của Flutter và test này **KHÔNG PASS** vì nó test counter app trong khi app thực là Quyền Sport.

```dart
// test/widget_test.dart (Line 19)
expect(find.text('0'), findsOneWidget); // ❌ Test này sẽ fail với app thực
```

### Kế Hoạch Nâng Coverage Lên 80%

#### Unit Tests (Ưu tiên cao)
```dart
// test/services/auth_service_test.dart
test('login success saves token to secure storage', () async { ... });
test('login failure returns error message', () async { ... });
test('logout clears all stored data', () async { ... });

// test/utils/format_utils_test.dart
test('formatPrice formats 1000000 as 1.000.000 đ', () { ... });
test('formatPrice handles null input', () { ... });
```

#### Widget Tests (Ưu tiên trung bình)
```dart
// test/screens/login_screen_test.dart
testWidgets('shows error when email is empty', (tester) async { ... });
testWidgets('shows loading indicator during login', (tester) async { ... });
```

#### Integration Tests
```dart
// integration_test/auth_flow_test.dart
test('complete login flow', () async {
  // Navigate to login
  // Enter credentials
  // Verify navigation to home
});
```

---

## 📋 GIAI ĐOẠN 13 — FEATURE COMPLETENESS

### Authentication
| Tính năng          | Trạng thái |
|--------------------|-----------|
| Login (Email)      | ✅ Hoàn thiện |
| Register           | ✅ Hoàn thiện |
| Forgot Password    | ✅ OTP flow |
| Change Password    | ✅ Hoàn thiện |
| Social Login UI    | ⚠️ UI có, chức năng chưa có |
| Logout             | ✅ Hoàn thiện |
| Auto Login         | ✅ Hoàn thiện |

### E-Commerce
| Tính năng              | Trạng thái |
|------------------------|-----------|
| Product List           | ✅ Hoàn thiện + infinite scroll |
| Product Detail         | ✅ Hoàn thiện |
| Category Filter        | ✅ Hoàn thiện |
| Search + Debounce      | ✅ Hoàn thiện |
| Price Filter           | ✅ Hoàn thiện |
| Cart                   | ✅ Optimistic UI |
| Checkout               | ✅ COD + VNPay + MoMo |
| Address Management     | ✅ Hoàn thiện |
| Order History          | ✅ Hoàn thiện |
| Order Detail           | ✅ Hoàn thiện |
| Return Request         | ✅ Hoàn thiện |
| Favorites              | ✅ Hoàn thiện |
| Flash Sale             | ✅ Hoàn thiện |
| Coupon/Voucher         | ✅ Hoàn thiện |
| Product Review         | ✅ Hoàn thiện |
| GHN Shipping Calc      | ✅ Hoàn thiện |

### Court Booking
| Tính năng              | Trạng thái |
|------------------------|-----------|
| Xem sân                | ✅ Hoàn thiện |
| Chọn khung giờ         | ✅ Contiguous slot selection |
| Đặt sân                | ✅ Lock slot + booking |
| Lịch của tôi           | ✅ Hoàn thiện |
| Hủy đặt sân           | ✅ Hoàn thiện |
| QR Check-in            | ✅ Hoàn thiện |
| Extra services         | ✅ Hoàn thiện |
| Gia hạn                | ✅ Hoàn thiện |
| Staff dashboard        | ✅ Hoàn thiện |
| Lịch sử đặt sân        | ✅ Hoàn thiện |

### Profile & System
| Tính năng              | Trạng thái |
|------------------------|-----------|
| Edit Profile           | ✅ Hoàn thiện |
| Upload Avatar          | ✅ Hoàn thiện |
| Notification Screen    | ⚠️ UI có, chưa connect API |
| Push Notification      | ❌ Chưa có |
| POS Scanner            | ✅ Hoàn thiện |
| Attendance (Chấm công) | ✅ Hoàn thiện |
| My Coupons             | ✅ Hoàn thiện |
| Onboarding (MultiPage) | ⚠️ Chỉ 1 page |
| Dark Mode              | ❌ Chưa có |
| App Settings           | ❌ Chưa có |

---

## 📐 GIAI ĐOẠN 14 — SO SÁNH VỚI PRODUCTION STANDARD

### Đối Chiếu Với Ecommerce App Chuẩn Production

| Hạng mục                  | Điểm | So sánh                              |
|---------------------------|------|--------------------------------------|
| Architecture              | 4/10 | Production cần Clean Arch/BLoC       |
| Security                  | 5/10 | Cần SSL pinning, no debug logs       |
| Performance               | 5/10 | Cần lazy loading, cache optimization |
| UX/UI                     | 7/10 | Đẹp, nhưng thiếu dark mode, a11y     |
| Testing                   | 1/10 | Production cần >70% coverage         |
| Maintainability           | 3/10 | Cần refactor God widgets, DRY        |
| Feature Completeness      | 7/10 | MVP tốt, thiếu push notif            |
| DevOps/CI-CD              | 0/10 | Không có CI/CD pipeline              |
| Monitoring                | 0/10 | Không có Crashlytics/Analytics       |

### 🎯 Production Readiness Score: **47/100**

---

## 🗺️ GIAI ĐOẠN 15 — DETAILED ROADMAP

---

## Phase 1 — Critical Fixes (Tuần 1-2) 🔴

**Ưu tiên: NGAY LẬP TỨC trước release**

### 1.1 Bảo Mật Khẩn Cấp

- [ ] **Xóa .env khỏi assets** — Dùng `--dart-define` cho secrets
  ```bash
  flutter build apk \
    --dart-define=TOKEN_GHN=xxx \
    --dart-define=API_BASE_URL=https://api.ocean.pro.vn/api
  ```
- [ ] **Tắt debug logs trong production**
  ```dart
  // Chỉ log khi kDebugMode
  if (kDebugMode) {
    debugPrint('══════ API REQUEST ══════');
  }
  ```
- [ ] **Fix isProduction flag** — Dùng `--dart-define=IS_PRODUCTION=true`
- [ ] **Xóa hardcoded ShopId** — Đưa vào `--dart-define`

### 1.2 Bug Fixes

- [ ] **Fix search debounce trong HomeScreen** — Thay `Future.delayed` bằng `Timer`
- [ ] **Fix Social Login buttons** — Disable hoặc implement
- [ ] **Fix font family** — Khai báo đúng `Plus Jakarta Sans` trong pubspec
- [ ] **Fix widget_test.dart** — Cập nhật test cho app thực

### 1.3 Performance Quick Wins

- [ ] **Giảm _fetchCartCount() calls** — Chỉ fetch khi navigate tới cart tab
- [ ] **Tạo utility `format_utils.dart`** — Consolidate 6 duplicate `_formatPrice()`

---

## Phase 2 — Architecture Refactor (Tuần 3-6) 🟠

**Ưu tiên: Trước khi thêm feature mới**

### 2.1 State Management Migration

Migrate từ `setState` sang `flutter_bloc` (Cubit pattern):

```
lib/
├── features/
│   ├── auth/
│   │   ├── data/
│   │   │   ├── repositories/auth_repository_impl.dart
│   │   │   └── sources/auth_remote_source.dart
│   │   ├── domain/
│   │   │   ├── entities/user.dart
│   │   │   └── repositories/auth_repository.dart
│   │   └── presentation/
│   │       ├── bloc/auth_cubit.dart
│   │       └── pages/login_page.dart
│   ├── cart/
│   ├── products/
│   └── booking/
└── core/
    ├── network/api_client.dart
    ├── utils/format_utils.dart
    └── widgets/shimmer_loading.dart
```

### 2.2 Repository Pattern

```dart
// Tạo abstract interface
abstract class CartRepository {
  Future<CartModel> getCart();
  Future<void> updateItem(int id, int qty);
  Future<void> removeItem(int id);
}

// Implement
class CartRepositoryImpl implements CartRepository {
  final ApiClient _client;
  @override
  Future<CartModel> getCart() async {
    final res = await _client.dio.get('/cart');
    return CartModel.fromJson(res.data['data']);
  }
}
```

### 2.3 Typed Models

Thay `Map<String, dynamic>` bằng typed models với `freezed`:

```dart
// lib/features/cart/domain/entities/cart_item.dart
@freezed
class CartItem with _$CartItem {
  const factory CartItem({
    required int cartItemId,
    required int quantity,
    required ProductVariant variant,
    required Product product,
  }) = _CartItem;
}
```

### 2.4 Router Migration

```dart
// Dùng go_router thay imperative navigation
final router = GoRouter(
  routes: [
    GoRoute(path: '/', builder: (_, __) => const MainWrapper()),
    GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
    GoRoute(path: '/product/:id', builder: (ctx, state) => ProductDetailScreen(id: state.pathParameters['id']!)),
  ],
);
```

---

## Phase 3 — Performance Optimization (Tuần 7-8) 🟡

- [ ] **Lazy load navigation tabs** — Thay IndexedStack bằng lazy initialization
- [ ] **Image memory optimization** — Thêm `memCacheHeight`/`memCacheWidth`
- [ ] **Timer optimization** — Chỉ chạy refresh timer khi tab booking active
- [ ] **GHN Dio singleton** — Không tạo Dio instance mới mỗi lần
- [ ] **Background fetch** — Prefetch cart khi app resume
- [ ] **API caching** — Cache product list với TTL

---

## Phase 4 — Security Hardening (Tuần 9-10) 🔴

- [ ] **SSL Pinning** — Implement certificate pinning cho API production
  ```dart
  dio.httpClientAdapter = IOHttpClientAdapter(
    createHttpClient: () {
      final client = HttpClient();
      client.badCertificateCallback = (cert, host, port) => false;
      // Add certificate pinning logic
      return client;
    },
  );
  ```
- [ ] **Root Detection** — Dùng `flutter_jailbreak_detection`
- [ ] **Refresh Token** — Implement auto token refresh trong interceptor
- [ ] **Obfuscation** — Enable Dart obfuscation trong release build
  ```bash
  flutter build apk --obfuscate --split-debug-info=./debug-info
  ```
- [ ] **Remove debug info** — Tắt `debugShowCheckedModeBanner`
- [ ] **Security Headers** — Verify backend trả đúng security headers

---

## Phase 5 — Testing (Tuần 11-12) 🟡

- [ ] **Unit Tests cho Services** — `AuthService`, `CartRepository`, `CourtBookingService`
- [ ] **Unit Tests cho Utils** — `format_utils`, `app_config`
- [ ] **Widget Tests** — Login flow, Cart interactions
- [ ] **Integration Tests** — Full purchase flow, booking flow
- [ ] **Setup CI/CD** — GitHub Actions với auto test on PR

**Target: 70% unit test coverage**

---

## Phase 6 — Release Preparation (Tuần 13-14) 🟢

- [ ] **Integrate Firebase Crashlytics**
- [ ] **Integrate Firebase Analytics**
- [ ] **Implement Push Notification (FCM)**
- [ ] **Multi-page Onboarding** — Hoàn thiện 3 slides
- [ ] **Fix tiếng Việt thiếu dấu** — court_booking_screen
- [ ] **Accessibility improvements** — Thêm Semantics
- [ ] **Dark Mode** — Implement dark theme
- [ ] **App Store assets** — Screenshots, description
- [ ] **Privacy Policy** — Chính sách bảo mật
- [ ] **Performance profiling** — DevTools audit
- [ ] **Release build testing** — Test APK/IPA thực tế

---

## 🚀 QUICK WINS (Có thể làm ngay, <2 giờ mỗi item)

| # | Task | Effort | Impact |
|---|------|--------|--------|
| 1 | Xóa `.env` khỏi assets, dùng `--dart-define` | 1h | 🔴 Critical |
| 2 | Tắt debug logs khi `!kDebugMode` | 30m | 🔴 High |
| 3 | Tạo `FormatUtils.formatPrice()` shared | 30m | 🟡 Medium |
| 4 | Fix search debounce HomeScreen | 20m | 🟠 High |
| 5 | Fix font `Plus Jakarta Sans` trong pubspec | 15m | 🟡 Medium |
| 6 | Disable social login buttons (không implement) | 10m | 🟡 Medium |
| 7 | Fix widget_test.dart boilerplate | 20m | 🟡 Medium |
| 8 | Fix tiếng Việt thiếu dấu trong court_booking | 30m | 🟡 Medium |
| 9 | Move `home_screen.dart` → `screens/` | 5m | 🟢 Low |
| 10 | Rename `productDetail.dart` → `product_detail_screen.dart` | 5m | 🟢 Low |

---

## ⚠️ CRITICAL ISSUES SUMMARY

| # | Issue | File | Severity | Priority |
|---|-------|------|----------|---------|
| 1 | .env bundled vào APK | `pubspec.yaml:76` | 🔴 CRITICAL | P0 |
| 2 | Debug logs in production (in cả Bearer token) | `api_client.dart:62-68` | 🔴 HIGH | P0 |
| 3 | isProduction hardcoded false | `app_config.dart:36` | 🔴 HIGH | P0 |
| 4 | Không có Refresh Token | `api_client.dart:84-87` | 🔴 HIGH | P1 |
| 5 | Search debounce sai (Future.delayed) | `home_screen.dart:188` | 🔴 HIGH | P1 |
| 6 | Social login không hoạt động (misleading) | `login_screen.dart:158` | 🟠 MEDIUM | P1 |
| 7 | _formatPrice duplicate 6+ files | Multiple | 🟠 MEDIUM | P2 |
| 8 | Không có SSL Pinning | `api_client.dart` | 🔴 HIGH | P1 |
| 9 | God Widget 1132 lines | `court_booking_screen.dart` | 🟠 MEDIUM | P2 |
| 10 | Không có bất kỳ unit test | `test/` | 🟠 MEDIUM | P2 |
| 11 | GHN ShopId hardcoded | `checkout_screen.dart:123` | 🟡 LOW | P2 |
| 12 | Không có Firebase Crashlytics | — | 🟠 MEDIUM | P2 |
| 13 | Font Plus Jakarta Sans không bundle | `app_theme.dart:173` | 🟡 LOW | P3 |
| 14 | Thiếu dấu tiếng Việt | `court_booking_screen.dart` | 🟡 LOW | P3 |
| 15 | Dead import dart:convert | `product_list_screen.dart:3` | 🟢 INFO | P4 |

---

## 📅 SPRINT PLANNING SUGGESTION

### Sprint 1 (2 tuần) — Security & Quick Fixes
- Fix tất cả CRITICAL/HIGH security issues
- Fix search debounce bug
- Tạo FormatUtils shared
- Setup Firebase Crashlytics

### Sprint 2 (2 tuần) — Architecture Foundation
- Setup GetIt dependency injection
- Tạo core/network layer
- Migrate AuthService sang Repository pattern
- Add basic unit tests cho auth

### Sprint 3 (2 tuần) — State Management Migration
- Migrate CartScreen sang Cubit
- Migrate HomeScreen sang Cubit
- Migrate CheckoutScreen sang Cubit

### Sprint 4 (2 tuần) — Performance & UX
- Lazy load tabs
- Image optimization
- Fix font bundle
- Implement Dark Mode

### Sprint 5 (2 tuần) — Testing
- Unit test coverage >60%
- Widget test cho critical flows
- Integration test cho purchase flow

### Sprint 6 (2 tuần) — Release Preparation
- Firebase Analytics + FCM
- SSL Pinning
- Store assets
- Final testing

---

## 📊 REFACTORING PRIORITY MATRIX

```
              HIGH IMPACT
                   │
     SSL Pinning   │  FormatUtils   Cubit Migration
     Refresh Token │  Repository    Clean Arch
     Debug Logs   ─┼─────────────────────────────
     .env Issue    │  Font Fix      Onboarding
                   │  Social Login  Dark Mode
              LOW IMPACT
     LOW EFFORT ───┼─────────────────── HIGH EFFORT
```

**Quadrant 1 (High Impact, Low Effort) — DO FIRST:**
- Xóa `.env` khỏi assets
- Tắt debug logs production
- Fix search debounce
- Consolidate FormatUtils

**Quadrant 2 (High Impact, High Effort) — PLAN CAREFULLY:**
- Clean Architecture migration
- Cubit/BLoC state management
- SSL Pinning
- Refresh Token implementation

---

*Báo cáo được tạo tự động bởi AI Audit Tool. Version: 1.0.0*
*Last Updated: 2026-06-15T22:20:50+07:00*
