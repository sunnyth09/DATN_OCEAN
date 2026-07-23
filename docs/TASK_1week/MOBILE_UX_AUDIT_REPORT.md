# 📱 MOBILE UX & NAVIGATION AUDIT REPORT
> **Dự án:** Quyen Sport E-commerce (Flutter Mobile App)
> **Thực hiện bởi:** Senior Mobile UX Architect & Senior Flutter Developer
> **Dựa trên source code thực tế**

---

## PHẦN 1: Xác định kiến trúc điều hướng
- **Kiến trúc hiện tại:** Sử dụng `Navigator 1.0` (push/pop thuần túy thông qua `MaterialPageRoute`) và `IndexedStack` để quản lý Bottom Navigation.
- **Phân tích:** 
  - Không sử dụng `GoRouter` hay `AutoRoute`.
  - Không có Nested Navigation chuẩn xác (các màn hình detail đè lên toàn bộ app thay vì nằm trong tab).
  - Không có ShellRoute hay Navigator 2.0.
- **Đánh giá:** ⚠ Cần cải thiện. Kiến trúc hiện tại sẽ khó xử lý Deep Link và gặp vấn đề memory leak / navigation history khi scale dự án.

---

## PHẦN 2: Kiểm tra Bottom Navigation
- **Chuẩn UX:** Bottom Navigation CHỈ được xuất hiện tại các màn hình cấp 1.
- **Kiểm tra thực tế:** App dùng custom `Container` đóng vai trò Bottom Nav trong `MainWrapper.dart` với `IndexedStack`.
- **Lỗi phát hiện:** 
  - Từ `ProductDetailScreen` (Màn hình cấp 2), action "Thêm vào giỏ" hoặc "Giỏ hàng icon" gọi `Navigator.push` đến một `MainWrapper` mới (`initialIndex: 3`).
  - Việc này dẫn đến việc stack navigation bị lặp lại (`MainWrapper` -> `ProductDetailScreen` -> `MainWrapper` mới). 
  - => Màn hình Checkout, Payment, Detail có thể gián tiếp bị bao bọc bởi nhiều tầng Bottom Navigation (Stack lồng nhau sai cách).
- **Đánh giá:** ✗ Sai.

---

## PHẦN 3: Kiểm tra AppBar
- **Kiểm tra thực tế:**
  - `HomeScreen`: Không có AppBar chuẩn (dùng SafeArea + Container custom) => Khá ổn cho Home.
  - `ProductDetailScreen`: Có AppBar với Title, Back, Cart, Share. Hợp lý.
  - `CheckoutScreen` & `CartScreen`: Có AppBar với Title, Back button. Hợp lý.
  - `OrderSuccessScreen`: Ẩn nút Back, điều hướng hợp lý.
  - `PosScannerScreen`: Có AppBar. Tuy nhiên theo chuẩn Scanner thì không nên có AppBar để tối ưu diện tích quét.
- **Đánh giá:** ⚠ Cần cải thiện.

---

## PHẦN 4: Kiểm tra Routing
- **Đánh giá:**
  - `push`: Dùng trực tiếp `MaterialPageRoute` (Khắp nơi).
  - `pushNamed`: Chưa được triển khai.
  - `replace`: Dùng `pushAndRemoveUntil` ở nhiều nơi để reset stack (Ví dụ về màn Home hoặc Cart).
  - `deep link`: Chưa tìm thấy.
  - `nested route`: Chưa tìm thấy.
  - `route guard`: Xử lý cục bộ bằng các IF statements (`if (!loggedIn) push(LoginScreen)`). Không có guard toàn cục.
- **Kết luận:** ✗ Sai (Routing chưa được chuẩn hóa thành file router chung).

---

## PHẦN 5: Kiểm tra Stack Navigation
- **Thực tế:**
  - `ProductDetail`: Mở bằng Stack Navigation (`push`). => Đúng.
  - `Checkout`: Mở bằng Stack Navigation (`push`). => Đúng.
  - **Lỗi:** Để quay về `Cart` hoặc `Home` từ một màn Stack (như Success), app đang dùng `pushAndRemoveUntil` tạo lại `MainWrapper` mới thay vì `popUntil` hoặc nhảy tab.
- **Đánh giá:** ⚠ Cần cải thiện. Việc mở màn thì đúng nhưng đóng/trở về lại sai luồng.

---

## PHẦN 6: Kiểm tra luồng Checkout
- **Chuẩn:** Cart ↓ Checkout ↓ Payment ↓ Success ↓ Order Detail
- **Thực tế source code:**
  - `CartScreen` -> `push` -> `CheckoutScreen`
  - `CheckoutScreen` -> `push` -> `PaymentWebviewScreen` HOẶC `pushAndRemoveUntil` tới `OrderSuccessScreen`.
  - Từ `OrderSuccessScreen` -> Chọn "Xem đơn hàng" -> `pushAndRemoveUntil` tới `MainWrapper(initialIndex: 4)`.
- **Đánh giá:** Luồng tiến thì đúng nhưng cách xử lý history stack chưa tối ưu. Việc clear stack liên tục khiến animation back chuyển cảnh bị giật và mất trạng thái các tab khác.

---

## PHẦN 7: Kiểm tra Fullscreen
- **Màn hình Camera/Scanner:** `PosScannerScreen`.
- **Thực tế:** `PosScannerScreen` vẫn còn hiển thị AppBar (màu hồng) ở phía trên. Không fullscreen.
- **Đánh giá:** ✗ Sai.

---

## PHẦN 8: Kiểm tra Search
- **Thực tế:** Chưa tìm thấy màn hình `SearchScreen` hay `SearchDelegate` độc lập trong thư mục `lib/screens/`.
- **Đánh giá:** Không đủ bằng chứng từ source code / Chưa được triển khai.

---

## PHẦN 9: Kiểm tra Animation
- **Thực tế:**
  - Không tìm thấy widget `Hero` ở các ảnh sản phẩm khi transition từ danh sách (`product_list`) sang chi tiết (`product_detail_screen.dart`).
  - Chuyển màn sử dụng default animation của `MaterialPageRoute` (Slide từ dưới lên hoặc từ trái sang).
  - AnimatedSwitcher / Fade chưa được tối ưu nhiều.
- **Đánh giá:** ⚠ Cần cải thiện. Chuyển màn hình còn cứng, thiếu seamless UX (như Hero animation cho thumbnail).

---

## PHẦN 10: Kiểm tra Responsive
- **Thực tế:** Fix cứng layout cho Phone. Không có logic split-screen, không thấy sử dụng `LayoutBuilder` để render `NavigationRail` trên Tablet/Desktop.
- **Đánh giá:** ✗ Sai. Không có Adaptive Layout.

---

## PHẦN 11: Kiểm tra Material Design 3
- **Thực tế:** 
  - `BottomNavigationBar` dùng custom `Container` tự vẽ bằng `Row`, `Expanded` và `AnimatedContainer`. Không dùng `NavigationBar` chuẩn của MD3.
  - Các button (`OutlinedButton`, `ElevatedButton`) tự custom style theo màu hồng của app, chưa tuân thủ chuẩn ColorScheme của MD3.
- **Đánh giá:** ⚠ Cần cải thiện.

---

## PHẦN 12: Kiểm tra Accessibility
- **Thực tế:**
  - Semantic: Chưa tìm thấy việc wrap các widget bằng `Semantics`.
  - Touch Target: Badge số lượng giỏ hàng trên icon kích thước quá bé (Font size 8, padding 4) vi phạm touch target 48x48dp.
- **Đánh giá:** ✗ Sai.

---

## PHẦN 13: Kiểm tra State Management
- **Thực tế:** Dùng `Provider` (`AuthProvider`, `CartProvider`).
- **Đánh giá Navigation State:** `MainWrapper` chứa state `_selectedIndex`. Tuy nhiên, vì các màn hình detail lại `push` ra một `MainWrapper` mới (đè lên cũ), Navigation State bị phân mảnh và build lại toàn bộ app (gây leak memory do các listeners chưa được dispose hợp lý).
- **Đánh giá:** ⚠ Cần cải thiện.

---

## PHẦN 14: Kiểm tra Performance
- **Thực tế:**
  - Có dùng `IndexedStack` (giữ state của các tab). => Tốt.
  - Giỏ hàng ở `MainWrapper` gọi API `fetchCart` liên tục (ở `initState` hoặc mỗi khi ấn tab 3) mà chưa có cơ chế Cache tối ưu.
  - Tab views tải đồng loạt ở `MainWrapper` dẫn đến khởi tạo chậm. Không có lazy load tab nội dung của `IndexedStack`.
- **Đánh giá:** ⚠ Cần cải thiện.

---

## PHẦN 15: Kiểm tra UX
- **Thực tế:**
  - Skeleton: Có class `ShimmerLoading` nhưng nhiều màn hình (như ProductDetail) chỉ dùng `CircularProgressIndicator` ở giữa màn hình => Trải nghiệm chớp giật.
  - Offline: Có widget `OfflineBanner` => Tốt.
  - Empty State: Màn hình giỏ hàng trống có thiết kế UI => Tốt.
  - Pull To Refresh: Đã triển khai ở `CartScreen`.
- **Đánh giá:** ⚠ Cần cải thiện (Cần đồng bộ dùng Skeleton loading).

---

## PHẦN 16: Bảng thống kê toàn bộ màn hình

| Screen | Bottom Nav | AppBar | Chuẩn UX | Ghi chú |
|--------|------------|--------|----------|---------|
| MainWrapper (Home, Tabs) | Bottom Nav ✓ | (Custom) | Đúng | Giao diện gốc, tab base. |
| ProductDetail | Bottom Nav ✗ | AppBar ✓ | Sai luồng | Action đẩy sang `MainWrapper` mới gây lặp stack. |
| Cart | Bottom Nav (Tab) ✓ | AppBar ✓ | Đúng | Xử lý back navigation khá cồng kềnh. |
| Checkout | Bottom Nav ✗ | AppBar ✓ | Đúng | Phù hợp UX. |
| OrderSuccess | Bottom Nav ✗ | AppBar ✓ | Sai (nên ko AppBar) | Ẩn back nhưng vẫn giữ AppBar không cần thiết. |
| PosScanner | Bottom Nav ✗ | AppBar ✓ | Sai | Cần full màn hình, overlay các button thay vì AppBar cứng. |

---

## PHẦN 17: Kiểm tra source code chi tiết

- **Tên file:** `lib/screens/product_detail_screen.dart`
- **Line Number:** 197 & 342
- **Function/Widget:** `_handleActionSelected`, AppBar Actions
- **Hiện trạng:** Đang gọi `Navigator.push(context, MaterialPageRoute(builder: (_) => const MainWrapper(initialIndex: 3)));`
- **Kết luận:** **=> Không đúng chuẩn.** Việc mở lại `MainWrapper` mới đè lên Stack cũ tạo ra infinite navigation stack và phá vỡ kiến trúc.

---

## PHẦN 18: Đề xuất chỉnh sửa

### Lỗi 1: Stack lồng nhau do chuyển tab sai cách
1. **Vấn đề:** Các action "Về trang chủ", "Tới giỏ hàng" từ màn hình cấp 2 (Detail) tạo ra `MainWrapper` mới.
2. **Nguyên nhân:** Developer dùng `Navigator.push` thay vì điều hướng lại tab ở root.
3. **Ảnh hưởng UX:** Bấm nút Back hệ thống sẽ quay về màn detail cũ thay vì thoát app; lag/memory leak.
4. **Giải pháp:** Sử dụng Provider để đổi `_selectedIndex` của `MainWrapper` hiện tại và pop màn hình detail về màn hình gốc. Thay vì push mới, dùng `Navigator.popUntil(context, (route) => route.isFirst);` kết hợp đổi tab.
5. **Ưu tiên:** **Critical**
6. **File cần sửa:** `lib/screens/product_detail_screen.dart`, `lib/screens/order_success_screen.dart`

### Lỗi 2: PosScannerScreen chưa full-screen
1. **Vấn đề:** Giao diện quét mã vạch bị chiếm diện tích bởi AppBar.
2. **Nguyên nhân:** Dùng Scaffold cơ bản với AppBar mặc định.
3. **Ảnh hưởng UX:** Giảm không gian camera, UX không hiện đại.
4. **Giải pháp:** Sử dụng `Stack` bao bọc toàn bộ màn hình, hiển thị luồng Camera toàn màn hình và dùng `Positioned` đặt nút Back hình tròn trong mờ (glassmorphism) ở góc trên.
5. **Ưu tiên:** **Medium**
6. **File cần sửa:** `lib/screens/pos_scanner_screen.dart`

### Lỗi 3: Thiếu Hero Animation
1. **Vấn đề:** Bấm từ Grid sản phẩm vào Chi tiết chưa có hiệu ứng zoom ảnh.
2. **Nguyên nhân:** Chưa wrap widget Image bằng `Hero()`.
3. **Ảnh hưởng UX:** Trải nghiệm chuyển cảnh cứng, giật cục.
4. **Giải pháp:** Thêm `Hero(tag: product.id, child: Image...)` ở cả màn List và Detail.
5. **Ưu tiên:** **Low**
6. **File cần sửa:** `lib/screens/product_detail_screen.dart`, màn hình List.

---

## PHẦN 19: Đưa điểm đánh giá

- Navigation: **40/100** (Kiến trúc yếu, stack lồng nhau sai cách)
- UX: **65/100** (UI sạch đẹp nhưng chuyển cảnh cứng, loading sơ sài)
- Flutter Best Practice: **45/100** (Phụ thuộc quá nhiều vào `pushAndRemoveUntil` manual, thiếu Router)
- Material 3: **50/100** (Tự vẽ widget quá nhiều, chưa dùng component chuẩn MD3)
- Performance: **60/100** (Dùng IndexedStack tốt nhưng chưa tối ưu Memory)
- Accessibility: **30/100** (Chưa quan tâm Touch Target và Semantic)
- Maintainability: **40/100** (Routing code rải rác, khó maintain khi scale)
- **Overall: 47/100 (Trung bình yếu - Cần nâng cấp kiến trúc Navigation ngay)**

---

## PHẦN 20: Kế hoạch cải tiến theo thứ tự ưu tiên

### Phase 1 (Critical): Xử lý dứt điểm lỗi đè Stack Navigation
- **Hành động:** Fix logic nút "Tới giỏ hàng" và "Xem đơn hàng".
- **Giải pháp:** Khởi tạo `NavigationProvider` để set tab, sử dụng `Navigator.popUntil(context, (route) => route.isFirst);` thay cho push `MainWrapper` mới.

### Phase 2 (High): Chuẩn hóa hệ thống Routing
- **Hành động:** Chuyển đổi từ Navigator 1.0 sang **GoRouter**.
- **Giải pháp:** Thiết lập `app_router.dart`, dùng `ShellRoute` cho `MainWrapper` để đảm bảo Bottom Navigation ở root và không bị chèn bởi các detail screens. Hỗ trợ Deep Link.

### Phase 3 (Medium): Tối ưu hóa UI/UX
- **Hành động:** Đồng bộ loading và UI components.
- **Giải pháp:** Thay `CircularProgressIndicator` thành `ShimmerLoading` skeleton. Sửa `PosScannerScreen.dart` thành camera full-screen không có AppBar. Bổ sung `Hero` animation.

### Phase 4 (Low): Nâng cao Accessibility và MD3
- **Hành động:** Nâng cấp tiếp cận người dùng và Design System.
- **Giải pháp:** Tăng touch target size (>= 48dp). Sử dụng chuẩn `NavigationBar` của Material 3 để thay thế custom Bottom Navigation hiện tại.
