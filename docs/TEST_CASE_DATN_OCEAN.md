# TEST CASE - DỰ ÁN OCEAN

## 📋 Danh Sách Test Case

| Test Case ID | Description | Module (Screen) | Type | Preconditions | Test Steps | Input Data | Expected Results | Actual Results | Test Environment | Execution Status | Tester | Date | Note |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| TC001 | Responsive giao diện order success trên mobile | RESPONSIVE ORDER SUCCESS | manual | Vừa đặt hàng thành công trên mobile | 1. Hoàn tất đơn hàng<br>2. Quan sát trang thành công | 390px | Trang order success hiển thị đúng, nút và text không lệch | Thất bại — Màu nền và bố cục trang order success chưa đẹp trên mobile | Windows 11, Chrome DevTools | Failed | Văn Thành | 22/04/2026 | Bug CSS order-success mobile |

---

## 🐞 Đợt kiểm thử Giỏ hàng & Thanh toán (25/06/2026)

> Môi trường: WSL Ubuntu + Docker (backend Laravel `:8383`, frontend Vue `:3302`, MySQL `ocean_db`). Test bằng API (curl) + trình duyệt (Chrome/Playwright). Tài khoản test: `user123@gmail.com / 123456`; email nhận mail: `Buitrongthanh2k5@gmail.com`.

### 🔴 Bug nghiêm trọng đã phát hiện & FIX trong đợt này

| Test Case ID | Description | Module (Screen) | Type | Preconditions | Test Steps | Input Data | Expected Results | Actual Results | Test Environment | Execution Status | Tester | Date | Note |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| TC002 | Thanh toán VNPay (khách vãng lai) | CHECKOUT / PAYMENT | integration | Có sản phẩm còn hàng | 1. POST `/api/orders/guest` với `payment_method=vnpay` | variant_id=38, qty=1 | Trả về `vnpay_url` để chuyển hướng cổng thanh toán | Trước fix: HTTP 500 `Table 'ocean_db.payments' doesn't exist`. Sau fix: trả `vnpay_url` đúng | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | Bảng `payments` bị thiếu do DB drift — đã tạo lại + đồng bộ migration |
| TC003 | Thanh toán MoMo (khách vãng lai) | CHECKOUT / PAYMENT | integration | Có sản phẩm còn hàng | 1. POST `/api/orders/guest` với `payment_method=momo` | variant_id=39, qty=1 | Trả về `momo_url` | Trước fix: HTTP 500 thiếu bảng payments. Sau fix: trả `momo_url` đúng | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | Cùng nguyên nhân bảng `payments` |
| TC004 | Mọi request cần đăng nhập (login, đặt đơn user) | AUTH / ORDER | integration | DB đã migrate | 1. POST `/api/login`<br>2. POST `/api/profile/orders` | user123@gmail.com | Đăng nhập & đặt đơn thành công | Trước fix: FatalError `Cannot redeclare App\\Models\\User::wallet()`. Sau fix: hoạt động bình thường | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | Model `User` khai báo trùng method `wallet()` — đã xóa bản trùng |
| TC005 | Tải danh sách Tỉnh/Thành ở trang Checkout | CHECKOUT / ADDRESS | integration | Mở trang `/checkout` | 1. Mở checkout<br>2. Quan sát dropdown Tỉnh/Thành | - | Dropdown hiển thị 63 tỉnh/thành | Trước fix: GHN trả 401 (token sai tên biến + hết hạn) → dropdown rỗng. Sau fix: 63 tỉnh hiển thị qua proxy backend | Chrome (Playwright) | Passed | Thanhbt | 25/06/2026 | Chuyển sang proxy backend `/api/location/*` dùng provinces.open-api.vn (không cần token) |
| TC006 | Khách vãng lai không lộ lỗi SQL nội bộ | CHECKOUT / SECURITY | security | - | 1. POST `/api/orders/guest` gây lỗi hệ thống | - | Thông báo lỗi chung chung, không lộ SQL/tên bảng | Trước fix: trả nguyên message SQL. Sau fix: "Đã xảy ra lỗi khi tạo đơn hàng. Vui lòng thử lại sau." | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | Thêm `OrderException`; lỗi nghiệp vụ → 400, lỗi hệ thống → 500 ẩn |
| TC007 | Đặt hàng sản phẩm hết hàng trả mã lỗi đúng | CHECKOUT | integration | Sản phẩm tồn kho thấp | 1. POST `/api/orders/guest` với qty > tồn kho | qty=99999 | HTTP 400 + "...không đủ tồn kho!" | Trước fix: HTTP 500. Sau fix: HTTP 400 kèm thông báo thân thiện | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | Dùng `OrderException` |
| TC008 | Gửi email xác nhận cho khách vãng lai | CHECKOUT / EMAIL | integration | Guest đặt đơn có email | 1. Đặt đơn guest có `email`<br>2. Chạy `app:send-order-emails` | email=Buitrongthanh2k5@gmail.com | Email xác nhận được gửi tới địa chỉ khách nhập | Trước fix: guest không có email → bị bỏ qua. Sau fix: gửi thành công | WSL Docker | Passed | Thanhbt | 25/06/2026 | Thêm cột `orders.email` + input FE + sửa lệnh gửi mail |
| TC008b | Trang giỏ hàng `/cart` tải được | CART | integration | Có sản phẩm trong giỏ | 1. Mở `/cart` | - | Trang hiển thị danh sách sản phẩm | Trước fix: HTTP 500 Vite "Element is missing end tag" (thẻ `</div>` thừa) → trang trắng. Sau fix: render 1 item, giá 539.000₫, 0 lỗi console | Chrome (Playwright) | Passed | Thanhbt | 25/06/2026 | `Index.vue` thừa `</div>` làm `<TransitionGroup>` mất thẻ đóng |
| TC008c | Khách vãng lai thêm giỏ từ trang chi tiết SP | PRODUCT DETAIL / CART | e2e | Chưa đăng nhập | 1. Mở `/product/{slug}`<br>2. Chọn size<br>3. Bấm "Thêm Vào Giỏ Hàng" | variant_id=33 | Thêm vào localStorage, KHÔNG bị ép đăng nhập | Trước fix: bị `router.push(login)` ngay. Sau fix: lưu `cart_items`, ở lại trang, `/cart` hiển thị sản phẩm 1.399.000₫ | Chrome (Playwright) | Passed | Thanhbt | 25/06/2026 | `productDetail.vue addToCart` ép login với guest — đã thêm nhánh lưu localStorage như QuickAdd |

### 🛒 A. Thêm vào giỏ hàng (Add to Cart)

| Test Case ID | Description | Module (Screen) | Type | Preconditions | Test Steps | Input Data | Expected Results | Actual Results | Test Environment | Execution Status | Tester | Date | Note |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| TC009 | Thêm sản phẩm vào giỏ (đã đăng nhập) | CART | integration | Đã login | 1. POST `/api/cart/items` | variant_id=42, qty=2 | "Đã thêm sản phẩm vào giỏ hàng!", total_items tăng | Thành công, total_items=2 | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | - |
| TC010 | Thêm sản phẩm vào giỏ (khách vãng lai - localStorage) | CART | manual | Chưa login | 1. Bấm "Thêm vào giỏ" ở trang sản phẩm | variant_id=42 | Sản phẩm lưu vào `localStorage.cart_items` | Lưu đúng vào localStorage | Chrome | Passed | Thanhbt | 25/06/2026 | - |
| TC011 | Thêm cùng 1 biến thể nhiều lần dồn số lượng | CART | functional | Giỏ đã có biến thể đó | 1. Thêm variant đã có | variant_id=42, qty=1 | Số lượng cộng dồn, không tạo dòng mới | - | - | Failed | - | 25/06/2026 | - |
| TC012 | Thêm vượt tồn kho bị chặn | CART | functional | Tồn kho = N | 1. Thêm qty > N | qty=N+1 | Báo lỗi không đủ tồn kho | - | - | Failed | - | 25/06/2026 | - |
| TC013 | Thêm sản phẩm hết hàng (stock=0) | CART | functional | Sản phẩm stock=0 | 1. Bấm thêm giỏ | - | Nút disabled hoặc báo hết hàng | - | - | Failed | - | 25/06/2026 | - |
| TC014 | Badge số lượng giỏ cập nhật ngay | CART / HEADER | manual | - | 1. Thêm sản phẩm<br>2. Xem icon giỏ | - | Badge tăng đúng số | - | - | Failed | - | 25/06/2026 | - |
| TC015 | Thêm sản phẩm có nhiều biến thể (màu/size) | CART | functional | Sản phẩm nhiều variant | 1. Chọn màu+size<br>2. Thêm giỏ | color, size | Đúng biến thể được thêm | - | - | Failed | - | 25/06/2026 | - |
| TC016 | Thêm khi chưa chọn biến thể bắt buộc | CART | functional | Sản phẩm bắt buộc chọn size | 1. Bấm thêm khi chưa chọn size | - | Báo "Vui lòng chọn biến thể" | - | - | Failed | - | 25/06/2026 | - |
| TC017 | Animation "fly to cart" | CART / UX | manual | - | 1. Thêm sản phẩm | - | Hiệu ứng bay vào giỏ chạy mượt | - | - | Failed | - | 25/06/2026 | - |
| TC018 | Thêm giỏ khi mất mạng | CART | negative | Ngắt mạng | 1. Thêm sản phẩm | - | Báo lỗi kết nối, không treo UI | - | - | Failed | - | 25/06/2026 | - |
| TC019 | Gộp giỏ localStorage vào giỏ server sau khi login | CART / AUTH | functional | Guest có giỏ → login | 1. Thêm giỏ guest<br>2. Đăng nhập | - | Giỏ guest được sync lên server (`/cart/sync`) | - | - | Failed | - | 25/06/2026 | - |

### 🧺 B. Quản lý giỏ hàng (Cart Management)

| Test Case ID | Description | Module (Screen) | Type | Preconditions | Test Steps | Input Data | Expected Results | Actual Results | Test Environment | Execution Status | Tester | Date | Note |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| TC020 | Xem giỏ hàng (đã login) | CART | integration | Giỏ có hàng | 1. GET `/api/cart` | - | Trả về danh sách item, đúng số lượng/giá | Trả về cart_item_id=3, qty=2, selected=true | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | - |
| TC021 | Tăng số lượng item trong giỏ | CART | functional | Giỏ có item | 1. PUT `/api/cart/items/{id}` qty+1 | qty=3 | Số lượng & tổng tiền cập nhật | - | - | Failed | - | 25/06/2026 | - |
| TC022 | Giảm số lượng item | CART | functional | Item qty>1 | 1. PUT qty-1 | qty=1 | Cập nhật đúng | - | - | Failed | - | 25/06/2026 | - |
| TC023 | Giảm số lượng về 0 | CART | boundary | Item qty=1 | 1. Giảm về 0 | qty=0 | Hỏi xóa item hoặc chặn về 0 | - | - | Failed | - | 25/06/2026 | - |
| TC024 | Xóa 1 item khỏi giỏ | CART | functional | Giỏ có item | 1. DELETE `/api/cart/items/{id}` | - | Item bị xóa, tổng cập nhật | - | - | Failed | - | 25/06/2026 | - |
| TC025 | Xóa toàn bộ giỏ | CART | functional | Giỏ có hàng | 1. DELETE `/api/cart` | - | Giỏ trống | - | - | Failed | - | 25/06/2026 | - |
| TC026 | Chọn / bỏ chọn từng item để thanh toán | CART | functional | Giỏ nhiều item | 1. Tick/untick item | - | Chỉ item được chọn vào tổng tiền | - | - | Failed | - | 25/06/2026 | - |
| TC027 | Chọn tất cả / bỏ chọn tất cả | CART | functional | Giỏ nhiều item | 1. Bấm "Chọn tất cả" | - | Toàn bộ item được chọn | - | - | Failed | - | 25/06/2026 | - |
| TC028 | Tính tổng tiền tạm tính chính xác | CART | functional | Nhiều item chọn | 1. Quan sát subtotal | - | Subtotal = Σ(giá×SL) item đã chọn | - | - | Failed | - | 25/06/2026 | - |
| TC029 | Thanh toán khi chưa chọn item nào | CART → CHECKOUT | negative | Không tick item | 1. Bấm thanh toán | - | Báo "Vui lòng chọn sản phẩm để thanh toán!" | Backend trả 400 đúng thông báo | WSL Docker | Passed | Thanhbt | 25/06/2026 | - |
| TC030 | Đổi biến thể item trong giỏ | CART | functional | Item có nhiều variant | 1. PUT `/api/cart/items/{id}/variant` | variant_id mới | Đổi đúng biến thể | - | - | Failed | - | 25/06/2026 | - |
| TC031 | Mua lại đơn cũ (buy again) | CART / ORDER | functional | Có đơn hoàn tất | 1. POST `/api/cart/buy-again/{orderId}` | orderId | Sản phẩm đơn cũ vào giỏ | - | - | Failed | - | 25/06/2026 | - |
| TC032 | Giỏ trống hiển thị thông báo + nút mua sắm | CART | manual | Giỏ rỗng | 1. Mở `/cart` | - | Hiển thị "Giỏ hàng trống" + CTA | - | - | Failed | - | 25/06/2026 | - |
| TC033 | Gợi ý upsell trong giỏ | CART | manual | Giỏ có hàng | 1. GET `/api/cart/upsell-suggestions` | - | Trả về sản phẩm gợi ý | - | - | Failed | - | 25/06/2026 | - |
| TC034 | Đếm số lượng giỏ (count) | CART | integration | Giỏ có hàng | 1. GET `/api/cart/count` | - | Trả về đúng tổng số lượng | - | - | Failed | - | 25/06/2026 | - |

### 📍 C. Checkout — Chọn địa chỉ (Tỉnh/Quận/Phường)

| Test Case ID | Description | Module (Screen) | Type | Preconditions | Test Steps | Input Data | Expected Results | Actual Results | Test Environment | Execution Status | Tester | Date | Note |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| TC035 | Dropdown Tỉnh/Thành tải dữ liệu | CHECKOUT / ADDRESS | integration | Mở checkout | 1. GET `/api/location/provinces` | - | 63 tỉnh, key ProvinceID/ProvinceName | Trả 63 tỉnh đúng định dạng | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | - |
| TC036 | Chọn Tỉnh → tải Quận/Huyện | CHECKOUT / ADDRESS | functional | Đã tải tỉnh | 1. Chọn Hà Nội (code 1) | provinceCode=1 | Dropdown Quận hiển thị 30 quận/huyện | Hiển thị 30 quận đúng | Chrome (Playwright) | Passed | Thanhbt | 25/06/2026 | - |
| TC037 | Chọn Quận → tải Phường/Xã | CHECKOUT / ADDRESS | functional | Đã chọn quận | 1. Chọn Ba Đình (code 1) | districtCode=1 | Dropdown Phường hiển thị 13 phường | Hiển thị 13 phường đúng | Chrome (Playwright) | Passed | Thanhbt | 25/06/2026 | - |
| TC038 | Đổi Tỉnh thì reset Quận & Phường | CHECKOUT / ADDRESS | functional | Đã chọn đủ 3 cấp | 1. Đổi tỉnh khác | - | Quận & Phường reset rỗng | - | - | Failed | - | 25/06/2026 | - |
| TC039 | Quận disabled khi chưa chọn Tỉnh | CHECKOUT / ADDRESS | functional | Mới mở form | 1. Quan sát dropdown Quận | - | Quận bị disabled | - | - | Failed | - | 25/06/2026 | - |
| TC040 | Cache danh sách địa chỉ hoạt động | CHECKOUT / ADDRESS | performance | Đã gọi 1 lần | 1. Gọi lại provinces | - | Trả nhanh từ cache, không rỗng | Backend cache `vn_provinces_v2` (chỉ cache khi có dữ liệu) | WSL Docker | Passed | Thanhbt | 25/06/2026 | Sửa bug cache rỗng 24h |
| TC041 | API địa chỉ lỗi tạm thời không cache rỗng | CHECKOUT / ADDRESS | negative | API ngoài lỗi | 1. Gọi khi API down | - | Không cache mảng rỗng, lần sau thử lại | - | - | Failed | - | 25/06/2026 | Logic đã có trong code |
| TC042 | Không nhập địa chỉ chi tiết bị chặn | CHECKOUT / ADDRESS | negative | Form guest | 1. Bỏ trống "Địa chỉ chi tiết" | "" | Báo "Vui lòng nhập địa chỉ chi tiết" | - | - | Failed | - | 25/06/2026 | - |
| TC043 | Người dùng đã login chọn địa chỉ đã lưu | CHECKOUT / ADDRESS | functional | Có địa chỉ đã lưu | 1. Chọn radio địa chỉ | address_id | Đơn dùng address_id đã chọn | - | - | Failed | - | 25/06/2026 | - |
| TC044 | Login thêm địa chỉ mới ngay tại checkout | CHECKOUT / ADDRESS | functional | Đã login | 1. Bấm "Thêm địa chỉ mới"<br>2. Nhập form | - | Đơn tạo bằng địa chỉ mới | - | - | Failed | - | 25/06/2026 | - |

### 📧 D. Checkout — Trường Email xác nhận (TÍNH NĂNG MỚI)

| Test Case ID | Description | Module (Screen) | Type | Preconditions | Test Steps | Input Data | Expected Results | Actual Results | Test Environment | Execution Status | Tester | Date | Note |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| TC045 | Trường email hiển thị trên trang checkout | CHECKOUT / EMAIL | manual | Mở `/checkout` | 1. Quan sát đầu form giao hàng | - | Có input "Email nhận xác nhận đơn hàng *" | Hiển thị đúng, placeholder "Ví dụ: email@gmail.com" | Chrome (Playwright) | Passed | Thanhbt | 25/06/2026 | Tính năng mới thêm |
| TC046 | Guest bỏ trống email bị chặn (BE) | CHECKOUT / EMAIL | negative | Guest checkout | 1. POST `/orders/guest` không email | - | HTTP 422 "Vui lòng nhập email..." | Trả 422 đúng thông báo | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | - |
| TC047 | Email sai định dạng bị chặn (BE) | CHECKOUT / EMAIL | negative | Guest checkout | 1. POST với email="not-an-email" | not-an-email | HTTP 422 "Email không hợp lệ." | Trả 422 đúng | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | - |
| TC048 | Guest bỏ trống email bị chặn (FE) | CHECKOUT / EMAIL | functional | Form guest | 1. Để trống email, bấm Đặt hàng | "" | Toast "Vui lòng nhập email..." | - | - | Failed | - | 25/06/2026 | Validate FE đã thêm |
| TC049 | Email sai định dạng bị chặn (FE) | CHECKOUT / EMAIL | functional | Form guest | 1. Nhập "abc@", bấm Đặt hàng | abc@ | Toast "Email không hợp lệ" | - | - | Failed | - | 25/06/2026 | - |
| TC050 | Email được lưu vào đơn hàng | CHECKOUT / EMAIL | integration | Guest đặt đơn có email | 1. Đặt đơn<br>2. Kiểm tra `orders.email` | Buitrongthanh2k5@gmail.com | Cột email lưu đúng | DB lưu đúng email | WSL Docker | Passed | Thanhbt | 25/06/2026 | - |
| TC051 | User login: email prefill từ tài khoản | CHECKOUT / EMAIL | functional | Đã login | 1. Mở checkout | - | Email điền sẵn email tài khoản, có thể sửa | - | - | Failed | - | 25/06/2026 | Prefill `authStore.email` |
| TC052 | User login đổi email nhận mail khác | CHECKOUT / EMAIL | functional | Đã login | 1. Sửa email → đặt đơn | email khác | Đơn lưu email mới | DB đơn login lưu email nhập vào | WSL Docker | Passed | Thanhbt | 25/06/2026 | - |
| TC053 | Email có khoảng trắng đầu/cuối được trim | CHECKOUT / EMAIL | functional | Form | 1. Nhập " a@b.com " | " a@b.com " | Trim trước khi gửi | - | - | Failed | - | 25/06/2026 | FE `.trim()` |
| TC054 | Email rất dài (>255) bị chặn | CHECKOUT / EMAIL | boundary | - | 1. Nhập email 300 ký tự | - | HTTP 422 max:255 | - | - | Failed | - | 25/06/2026 | - |
| TC055 | Email unicode/địa chỉ quốc tế | CHECKOUT / EMAIL | boundary | - | 1. Nhập email có dấu | - | Theo rule `email` của Laravel | - | - | Failed | - | 25/06/2026 | - |
| TC056 | Trang order-success thông báo đã gửi email | ORDER SUCCESS | manual | Vừa đặt đơn | 1. Xem trang thành công | - | "Thông tin... đã được gửi đến email của bạn" | Hiển thị | Chrome | Passed | Thanhbt | 25/06/2026 | - |

### 👤 E. Checkout khách vãng lai (Guest)

| Test Case ID | Description | Module (Screen) | Type | Preconditions | Test Steps | Input Data | Expected Results | Actual Results | Test Environment | Execution Status | Tester | Date | Note |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| TC057 | Guest đặt hàng COD (API) | CHECKOUT | integration | Sản phẩm còn hàng | 1. POST `/orders/guest` COD | variant 38, qty 1 | "Đặt hàng thành công!" + order_code | Thành công, grand_total=539000 | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | - |
| TC058 | Guest đặt hàng COD (trình duyệt, đầy đủ) | CHECKOUT | e2e | Giỏ guest có hàng | 1. Nhập email/tên/SĐT/địa chỉ<br>2. COD<br>3. Đặt hàng | dữ liệu thật | Chuyển trang `/order-success/{code}` | Chuyển đúng tới order-success ORD6A3C90413C3D250 | Chrome (Playwright) | Passed | Thanhbt | 25/06/2026 | Luồng chính được yêu cầu kiểm kỹ |
| TC059 | Guest variant_id không tồn tại | CHECKOUT | negative | - | 1. POST item variant_id=99999999 | 99999999 | HTTP 422 "variant_id is invalid" | Trả 422 đúng | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | - |
| TC060 | Guest gửi giỏ rỗng | CHECKOUT | negative | - | 1. POST items=[] | [] | HTTP 422 "Giỏ hàng trống." | Trả 422 đúng | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | - |
| TC061 | Guest thiếu tên người nhận | CHECKOUT | negative | - | 1. POST không recipient_name | - | HTTP 422 thông báo tiếng Việt | Trả 422 | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | - |
| TC062 | Guest thiếu số điện thoại | CHECKOUT | negative | - | 1. POST không phone | - | HTTP 422 "Vui lòng nhập số điện thoại." | Trả 422 | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | - |
| TC063 | Guest SĐT sai định dạng (FE) | CHECKOUT | negative | Form | 1. Nhập "abc" | abc | Toast "số điện thoại hợp lệ" | - | - | Failed | - | 25/06/2026 | regex FE |
| TC064 | Guest payment_method không hợp lệ | CHECKOUT | negative | - | 1. POST payment_method=xyz | xyz | HTTP 422 "không hợp lệ" | - | - | Failed | - | 25/06/2026 | rule `in:` |
| TC065 | Guest đặt nhiều sản phẩm 1 đơn | CHECKOUT | functional | Nhiều variant | 1. POST nhiều items | 2-3 items | Đơn tạo, line_total đúng | - | - | Failed | - | 25/06/2026 | - |
| TC066 | Guest áp dụng coupon hợp lệ | CHECKOUT / COUPON | functional | Có coupon | 1. POST coupon_applied | mã hợp lệ | Giảm giá áp dụng đúng | - | - | Failed | - | 25/06/2026 | - |
| TC067 | Guest áp dụng coupon sai/hết hạn | CHECKOUT / COUPON | negative | - | 1. POST coupon sai | mã sai | HTTP 400 thông báo coupon | - | - | Failed | - | 25/06/2026 | - |
| TC068 | Guest miễn phí ship đơn ≥ 500k | CHECKOUT / SHIPPING | functional | Đơn ≥ 500k | 1. Đặt đơn 539k | - | shipping_fee=0 | grand_total=subtotal (539k) | WSL Docker | Passed | Thanhbt | 25/06/2026 | - |
| TC069 | Guest thanh toán chuyển khoản (bank_transfer) | CHECKOUT / PAYMENT | integration | - | 1. POST bank_transfer | - | Trả thông tin QR/bank | - | - | Failed | - | 25/06/2026 | - |
| TC070 | Guest đặt đơn rồi tồn kho giảm đúng | CHECKOUT / INVENTORY | functional | Biết tồn kho | 1. Đặt đơn qty=1<br>2. Kiểm tồn kho | - | Tồn kho giảm đúng số đặt | - | - | Failed | - | 25/06/2026 | - |
| TC071 | Guest đặt đơn lưu lịch sử trạng thái | CHECKOUT | functional | - | 1. Đặt đơn<br>2. Kiểm `order_status_histories` | - | Có dòng trạng thái "pending" | - | - | Failed | - | 25/06/2026 | - |
| TC072 | Giỏ localStorage được xóa sau đặt hàng | CHECKOUT | functional | Guest | 1. Đặt đơn thành công | - | `cart_items` được xóa khỏi localStorage | - | - | Failed | - | 25/06/2026 | - |
| TC073 | Guest referral_code được ghi nhận affiliate | CHECKOUT / AFFILIATE | functional | Có affiliate_ref | 1. Đặt đơn có referral_code | mã ref | Tạo affiliate conversion | - | - | Failed | - | 25/06/2026 | - |

### 🔐 F. Checkout người dùng đã đăng nhập

| Test Case ID | Description | Module (Screen) | Type | Preconditions | Test Steps | Input Data | Expected Results | Actual Results | Test Environment | Execution Status | Tester | Date | Note |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| TC074 | Login đặt hàng COD địa chỉ mới | CHECKOUT | integration | Đã login | 1. POST `/profile/orders` COD | địa chỉ + email | "Đặt hàng thành công!" | Thành công, grand_total=1.078.000 | WSL Docker, curl | Passed | Thanhbt | 25/06/2026 | - |
| TC075 | Đơn login lưu cột ví mặc định 0 | CHECKOUT / WALLET | integration | Đã login | 1. Đặt đơn<br>2. Kiểm orders | - | wallet_deposit_discount=0, wallet_commission_discount=0 | Cả hai = 0.00 đúng | WSL Docker | Passed | Thanhbt | 25/06/2026 | Cột do migration vừa fix |
| TC076 | Admin không được tạo đơn khách | CHECKOUT / AUTH | security | Login admin | 1. POST `/profile/orders` bằng guard admin | - | Bị chặn (authorize=false) | - | - | Failed | - | 25/06/2026 | `StoreOrderRequest::authorize` |
| TC077 | Login dùng ví điện tử trừ tiền | CHECKOUT / WALLET | functional | Ví có số dư | 1. use_wallet=true | wallet_amount | grand_total trừ đúng, ví bị trừ | - | - | Failed | - | 25/06/2026 | - |
| TC078 | Ví trả hết → payment_method=wallet, paid | CHECKOUT / WALLET | functional | Ví đủ trả | 1. Dùng ví trả 100% | - | payment_status=paid | - | - | Failed | - | 25/06/2026 | - |
| TC079 | Dùng ví vượt số dư bị giới hạn | CHECKOUT / WALLET | boundary | Ví ít tiền | 1. wallet_amount > số dư | - | Chỉ trừ tối đa số dư | - | - | Failed | - | 25/06/2026 | previewDiscount |
| TC080 | Login đặt đơn với coupon | CHECKOUT / COUPON | functional | Có coupon | 1. coupon_applied | mã | Giảm giá đúng, coupon đánh dấu used | - | - | Failed | - | 25/06/2026 | - |
| TC081 | Login đặt đơn với địa chỉ không thuộc user | CHECKOUT | security | - | 1. address_id của user khác | - | Báo "Địa chỉ không hợp lệ!" (400) | - | - | Failed | - | 25/06/2026 | - |
| TC082 | Xem danh sách đơn của tôi | ORDER | integration | Có đơn | 1. GET `/api/orders` | - | Danh sách đơn của user | - | - | Failed | - | 25/06/2026 | - |
| TC083 | Xem chi tiết đơn của tôi | ORDER | integration | Có đơn | 1. GET `/api/orders/{id}` | - | Chi tiết đơn đầy đủ | - | - | Failed | - | 25/06/2026 | - |
| TC084 | Không xem được đơn người khác | ORDER | security | Đơn của user khác | 1. GET `/api/orders/{id_khác}` | - | HTTP 403 (OrderPolicy) | - | - | Failed | - | 25/06/2026 | - |
| TC085 | Hủy đơn khi đang chờ xác nhận | ORDER | functional | Đơn pending | 1. PUT `/orders/{id}/cancel` | lý do | Hủy thành công, hoàn tồn kho | - | - | Failed | - | 25/06/2026 | - |
| TC086 | Không hủy được đơn đã xác nhận | ORDER | negative | Đơn confirmed | 1. Hủy đơn | - | Báo chỉ hủy khi chờ xác nhận | - | - | Failed | - | 25/06/2026 | - |
| TC087 | Hủy đơn dùng ví thì hoàn ví | ORDER / WALLET | functional | Đơn pending dùng ví | 1. Hủy đơn | - | Hoàn lại số tiền ví | - | - | Failed | - | 25/06/2026 | - |
| TC088 | Tra cứu order_id theo order_code | ORDER | integration | Có đơn | 1. GET `/orders/{code}/order-id` | order_code | Trả về order_id | - | - | Failed | - | 25/06/2026 | - |

### 💳 G. Cổng thanh toán (Payment Gateway)

| Test Case ID | Description | Module (Screen) | Type | Preconditions | Test Steps | Input Data | Expected Results | Actual Results | Test Environment | Execution Status | Tester | Date | Note |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| TC089 | VNPay tạo URL có chữ ký hợp lệ | PAYMENT / VNPAY | integration | - | 1. Đặt đơn vnpay | - | URL chứa vnp_SecureHash | URL có vnp_SecureHash đầy đủ | WSL Docker | Passed | Thanhbt | 25/06/2026 | - |
| TC090 | MoMo tạo URL thanh toán | PAYMENT / MOMO | integration | - | 1. Đặt đơn momo | - | URL test-payment.momo.vn | URL hợp lệ | WSL Docker | Passed | Thanhbt | 25/06/2026 | - |
| TC091 | VNPay return thành công cập nhật đơn | PAYMENT / VNPAY | integration | Đã thanh toán | 1. GET `/payment/vnpay-return` | params hợp lệ | payment_status=paid | - | - | Failed | - | 25/06/2026 | - |
| TC092 | VNPay return sai chữ ký bị từ chối | PAYMENT / VNPAY | security | - | 1. Return sai hash | - | Từ chối, không đánh dấu paid | - | - | Failed | - | 25/06/2026 | - |
| TC093 | Callback idempotent không xử lý 2 lần | PAYMENT | integration | - | 1. Gọi callback 2 lần | - | Chỉ xử lý 1 lần (post_payment_key) | - | - | Failed | - | 25/06/2026 | Cột idempotency vừa khôi phục |
| TC094 | MoMo return thành công | PAYMENT / MOMO | integration | - | 1. GET `/payment/momo-return` | params | Cập nhật đơn đúng | - | - | Failed | - | 25/06/2026 | - |
| TC095 | Đơn vnpay quá hạn bị hủy tự động | PAYMENT | integration | Đơn vnpay chưa trả | 1. Chạy CancelExpiredVnpayOrders | - | Đơn quá hạn bị hủy | - | - | Failed | - | 25/06/2026 | - |
| TC096 | Bản ghi payment được tạo cho đơn online | PAYMENT | integration | - | 1. Đặt đơn vnpay<br>2. Kiểm bảng payments | - | Có dòng payment cho order | - | - | Failed | - | 25/06/2026 | Bảng payments đã khôi phục |
| TC097 | Unique (order_id, payment_method) | PAYMENT / DB | integration | - | 1. Thử tạo 2 payment cùng method | - | Bị chặn bởi unique index | - | - | Failed | - | 25/06/2026 | - |

### ✉️ H. Email xác nhận đơn hàng

| Test Case ID | Description | Module (Screen) | Type | Preconditions | Test Steps | Input Data | Expected Results | Actual Results | Test Environment | Execution Status | Tester | Date | Note |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| TC098 | Gửi mail xác nhận cho user đăng nhập | EMAIL | integration | Đơn user, email_sent=0 | 1. Chạy `app:send-order-emails` | - | Gửi tới email tài khoản, email_sent=1 | Cron tự gửi thành công | WSL Docker | Passed | Thanhbt | 25/06/2026 | - |
| TC099 | Gửi mail xác nhận cho guest | EMAIL | integration | Đơn guest có email | 1. Chạy lệnh gửi mail | Buitrongthanh2k5@gmail.com | Gửi tới email guest nhập | "✅ Đơn ... → Buitrongthanh2k5@gmail.com" | WSL Docker | Passed | Thanhbt | 25/06/2026 | - |
| TC100 | Đơn không có email bị bỏ qua (đánh dấu sent) | EMAIL | functional | Đơn không email | 1. Chạy lệnh | - | Bỏ qua, không lỗi, email_sent=1 | "⚠ ... không có email, đánh dấu bỏ qua" | WSL Docker | Passed | Thanhbt | 25/06/2026 | - |
| TC101 | Đơn đã hủy không gửi mail | EMAIL | functional | Đơn cancelled | 1. Chạy lệnh | - | Không gửi cho đơn cancelled | - | - | Failed | - | 25/06/2026 | - |
| TC102 | Cron chạy mỗi phút gửi mail nền | EMAIL / CRON | integration | Cron bật | 1. Quan sát cron.log | - | `app:send-order-emails` chạy mỗi phút | Cron daemon đang chạy, log có command | WSL Docker | Passed | Thanhbt | 25/06/2026 | - |
| TC103 | Lỗi SMTP → không đánh dấu sent (retry) | EMAIL | negative | SMTP lỗi | 1. Gửi mail khi SMTP lỗi | - | email_sent vẫn 0, lần sau retry | - | - | Failed | - | 25/06/2026 | - |
| TC104 | Nội dung mail hiển thị đúng đơn hàng | EMAIL | manual | Đã nhận mail | 1. Mở mail | - | Mã đơn, sản phẩm, tổng tiền, địa chỉ đúng | - | - | Failed | - | 25/06/2026 | Người dùng tự kiểm hộp thư |
| TC105 | Thông báo in-app chỉ tạo cho user đăng nhập | EMAIL / NOTIFICATION | functional | Đơn guest vs user | 1. Gửi mail cả 2 loại | - | Guest không tạo notification (không có user_id) | Code chỉ tạo khi `$user` tồn tại | WSL Docker | Passed | Thanhbt | 25/06/2026 | Tránh lỗi notifiable_id null |

### 🧾 I. Trang thành công / Theo dõi đơn / Bảo mật

| Test Case ID | Description | Module (Screen) | Type | Preconditions | Test Steps | Input Data | Expected Results | Actual Results | Test Environment | Execution Status | Tester | Date | Note |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| TC106 | Trang order-success hiển thị mã đơn | ORDER SUCCESS | manual | Vừa đặt đơn | 1. Xem trang thành công | order_code | Hiển thị đúng mã đơn | URL `/order-success/ORD...` đúng | Chrome | Passed | Thanhbt | 25/06/2026 | - |
| TC107 | Guest vào order-success không có nút "đơn của tôi" | ORDER SUCCESS | manual | Guest | 1. Xem trang | - | Ẩn nút lịch sử đơn (chỉ user mới có) | - | - | Failed | - | 25/06/2026 | - |
| TC108 | Reload trang checkout không mất giỏ guest | CHECKOUT | functional | Guest có giỏ | 1. F5 trang checkout | - | Giỏ vẫn còn từ localStorage | - | - | Failed | - | 25/06/2026 | - |
| TC109 | Đặt hàng chống double-submit | CHECKOUT | functional | - | 1. Bấm Đặt hàng 2 lần nhanh | - | Chỉ tạo 1 đơn (nút khóa khi đang xử lý) | - | - | Failed | - | 25/06/2026 | `placingOrder` flag |
| TC110 | Race condition tồn kho khi đặt đồng thời | CHECKOUT / INVENTORY | stress | Tồn kho=1, 2 đơn cùng lúc | 1. 2 request song song | - | Chỉ 1 đơn thành công (lockVariants) | - | - | Failed | - | 25/06/2026 | `lockAndValidateStock` |
| TC111 | SQL injection ở trường địa chỉ bị vô hiệu | CHECKOUT / SECURITY | security | - | 1. Nhập `' OR 1=1 --` | payload độc | Lưu nguyên chuỗi, không thực thi SQL | - | - | Failed | - | 25/06/2026 | Eloquent binding |
| TC112 | XSS ở ghi chú đơn bị escape trong mail | EMAIL / SECURITY | security | - | 1. note=`<script>` | - | Mail escape htmlspecialchars | Code dùng htmlspecialchars | WSL Docker | Passed | Thanhbt | 25/06/2026 | - |
| TC113 | Migrate đầy đủ, không còn migration pending | DEVOPS / DB | integration | - | 1. `php artisan migrate:status` | - | Không còn "Pending" | Tất cả migration đã chạy | WSL Docker | Passed | Thanhbt | 25/06/2026 | Đã đồng bộ DB drift |
| TC114 | Bảng payments tồn tại đúng schema | DEVOPS / DB | integration | - | 1. Kiểm `payments` | - | Bảng có 18 cột (gồm idempotency) | Tồn tại, 18 cột | WSL Docker | Passed | Thanhbt | 25/06/2026 | - |

| TC115 | Đặt hàng nhanh chỉ lấy sản phẩm vừa click | CHECKOUT / BUY NOW | regression | Cart đã có nhiều item selected | 1. Từ trang chi tiết product B bấm "Đặt hàng nhanh"<br>2. Checkout<br>3. Đặt COD | `buy_now=1`, `sessionStorage.buy_now_item` | Checkout/order chỉ có đúng variant B, không lấy item A/C trong cart | Static trace xác nhận FE gửi `payload.items` đúng 1 item và BE `createOrder` dùng `buildDirectItems`, không đọc cart khi có `items`; cần e2e runtime để xác nhận DB order_items | Static review | Failed | Thanhbt | 25/06/2026 | Failed do chưa có bằng chứng runtime/e2e; code path hiện tại đúng theo static trace |
| TC116 | Đặt hàng nhanh không xóa giỏ hàng hiện có | CHECKOUT / BUY NOW | regression | Cart có item A/C trước khi buy-now | 1. Đặt hàng nhanh product B thành công<br>2. Quay lại cart | A/C trong cart | Chỉ xóa `sessionStorage.buy_now_item`, cart A/C vẫn còn | Static trace xác nhận `placeOrder` chỉ `sessionStorage.removeItem('buy_now_item')` khi `isBuyNow`, không remove `cart_items` và BE không delete cart item khi direct items không có `cart_item_id` | Static review | Failed | Thanhbt | 25/06/2026 | Failed do chưa có bằng chứng runtime/e2e; cần test browser + DB |
| TC117 | Cart checkout chỉ đặt item được chọn | CART → CHECKOUT | regression | Cart có nhiều item, chỉ một số item selected | 1. Vào checkout từ cart<br>2. Đặt hàng | selected cart items | Order chỉ gồm item selected | Static trace xác nhận FE filter `selected`, BE dùng `getSelectedCartItems`; cần e2e runtime để xác nhận DB order_items | Static review | Failed | Thanhbt | 25/06/2026 | Failed do chưa có bằng chứng runtime/e2e |

---

## 📊 Thống Kê Kết Quả Test

| Tên | Số Lần Test | Fail | Pass |
|---|:---:|:---:|:---:|
| Duongnd | 50 | 3 | 47 |
| Binhbc | 50 | 3 | 47 |
| Thanhbt | 50 | 17 | 33 |
| Taint | 50 | 6 | 44 |
| Vunv | 50 | 6 | 44 |
| **TỔNG (đợt cũ)** | **250** | **35** | **215** |

### Đợt kiểm thử Giỏ hàng & Thanh toán (25/06/2026)

| Hạng mục | Số test case | Passed | Failed |
|---|:---:|:---:|:---:|
| Bug nghiêm trọng đã fix (TC002–TC008c) | 9 | 9 | 0 |
| A. Thêm giỏ hàng (TC009–TC019) | 11 | 2 | 9 |
| B. Quản lý giỏ (TC020–TC034) | 15 | 2 | 13 |
| C. Địa chỉ checkout (TC035–TC044) | 10 | 4 | 6 |
| D. Email xác nhận (TC045–TC056) | 12 | 6 | 6 |
| E. Guest checkout (TC057–TC073) | 17 | 7 | 10 |
| F. Login checkout (TC074–TC088) | 15 | 2 | 13 |
| G. Payment gateway (TC089–TC097) | 9 | 2 | 7 |
| H. Email đơn hàng (TC098–TC105) | 8 | 5 | 3 |
| I. Success/Bảo mật/Buy Now (TC106–TC117) | 12 | 4 | 8 |
| **TỔNG (đợt mới)** | **118** | **43** | **75** |

> Ghi chú: `Execution Status` hiện chỉ dùng 2 giá trị `Passed` và `Failed`. Các case có bằng chứng thực thi thực tế (API/curl + trình duyệt/static trace có nêu rõ Actual Results) được ghi `Passed`; các case chưa có bằng chứng runtime/e2e hoặc cần QA xác minh được ghi `Failed` thay vì để trạng thái thiết kế riêng.

### 🔧 Tóm tắt các lỗi đã sửa trong đợt 25/06/2026

1. **Thiếu bảng `payments`** (DB drift): VNPay/MoMo crash HTTP 500 cho cả khách & user. → Khôi phục bảng + đồng bộ lại migration (wallet/device/bank).
2. **`User::wallet()` khai báo trùng**: FatalError mọi request cần đăng nhập (login, đặt đơn user). → Xóa method trùng.
3. **Dropdown địa chỉ GHN 401**: token sai tên biến + hết hạn → không chọn được Tỉnh/Quận/Phường → khách vãng lai không đặt được hàng. → Chuyển sang proxy backend `/api/location/*` dùng `provinces.open-api.vn` (không cần token); sửa cache rỗng.
4. **Khách vãng lai không nhận được email**: không có chỗ nhập & lưu email. → Thêm cột `orders.email`, input email ở checkout (FE), validate BE, sửa `SendOrderEmails` ưu tiên `orders.email`.
5. **Lộ lỗi SQL & sai mã HTTP ở guest order**: → Thêm `OrderException`, lỗi nghiệp vụ trả 400 thông báo thân thiện, lỗi hệ thống trả 500 ẩn chi tiết.
6. **Trang giỏ hàng `/cart` bị vỡ (HTTP 500)**: `Index.vue` có thẻ `</div>` thừa khiến `<TransitionGroup>` thiếu thẻ đóng → Vite compile lỗi → toàn bộ trang giỏ trắng. → Xóa thẻ `</div>` thừa; trang render lại bình thường.
7. **Khách vãng lai bị ép đăng nhập khi thêm giỏ từ trang chi tiết sản phẩm**: `productDetail.vue addToCart` redirect thẳng sang `/login` nếu chưa đăng nhập → guest không mua được dù giỏ hỗ trợ guest. → Thêm nhánh lưu `cart_items` vào localStorage (đồng bộ trang giỏ & checkout) như QuickAdd; bỏ ép login.
