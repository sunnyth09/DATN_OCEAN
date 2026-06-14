# OceanShop — Các Chức năng Chưa hoàn thiện & Đang thiếu

> Báo cáo được tổng hợp từ việc phân tích source code Backend (Laravel 11), Frontend (Vue 3 SPA) và Mobile (Flutter).
> Ngày lập: 11/05/2026

---

## Phần 1: Các Chức năng CHƯA HOÀN THIỆN (Code đã tồn tại nhưng để trống / placeholder)

### 1.1. Controllers để nguyên scaffold (chỉ có khai báo, chưa implement logic)

| Controller | File | Trạng thái |
|---|---|---|
| `InventoryTransactionController` | `backend/app/Http/Controllers/InventoryTransactionController.php` | Toàn bộ 7 method (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`) để trống `//` |
| `CartItemController` | `backend/app/Http/Controllers/CartItemController.php` | Toàn bộ 7 method để trống `//` |
| `PaymentController` | `backend/app/Http/Controllers/PaymentController.php` | Toàn bộ 7 method để trống `//` |
| `ProductVariantController` | `backend/app/Http/Controllers/ProductVariantController.php` | Toàn bộ 7 method để trống `//` |
| `ProductImageController` | `backend/app/Http/Controllers/ProductImageController.php` | Toàn bộ 7 method để trống `//` |
| `OrderItemController` | `backend/app/Http/Controllers/OrderItemController.php` | Toàn bộ 7 method để trống `//` |
| `OrderStatusHistoryController` | `backend/app/Http/Controllers/OrderStatusHistoryController.php` | Toàn bộ 7 method để trống `//` |
| `PromotionController` | `backend/app/Http/Controllers/PromotionController.php` | Toàn bộ 7 method để trống `//` |
| `PromotionCategoryController` | `backend/app/Http/Controllers/PromotionCategoryController.php` | Toàn bộ 7 method để trống `//` |
| `PromotionProductController` | `backend/app/Http/Controllers/PromotionProductController.php` | Toàn bộ 7 method để trống `//` |
| `PromotionUsageController` | `backend/app/Http/Controllers/PromotionUsageController.php` | Toàn bộ 7 method để trống `//` |

**Đánh giá:** Các controller trên được `php artisan make:controller` tạo ra nhưng chưa viết nội dung. Logic thực tế của các chức năng này đang nằm rải rác trong controller lớn hơn (ví dụ: `ProductController` xử lý cả variant/image, `OrderController` xử lý cả order item). Đây là dấu hiệu cần refactor sang Service/Action classes.

---

### 1.2. BrandController — Chỉ có list, thiếu CRUD Admin

| Method | Trạng thái |
|---|---|
| `index()` | ✅ Hoàn thiện — trả về toàn bộ brands có cache Redis 24h |
| `store()`, `show()`, `update()`, `destroy()` | ❌ Để trống — Admin chưa có API quản lý thương hiệu |

**Hệ quả:** Admin không thể thêm/sửa/xóa thương hiệu qua API. Frontend nếu có giao diện quản lý Brand sẽ không hoạt động.

---

### 1.3. FlashSaleController — Mua Flash Sale không tạo Order

- Endpoint `POST /flash-sale/buy/{id}` kiểm tra tồn kho Redis, giảm stock, giới hạn user, tạo `order_code` dạng `FS-{timestamp}`.
- **Tuy nhiên không có lệnh tạo bản ghi `Order` và `OrderItem` trong database**.

**Hệ quả:** Khách hàng mua Flash Sale sẽ nhận được mã đơn hàng nhưng không có đơn hàng thực tế để theo dõi trong lịch sử đơn hàng, không có thanh toán, không có email xác nhận.

---

### 1.4. JWT Authentication — Access Token và Refresh Token trùng giá trị

Trong `AuthController::login()`:
```php
return response()->json([
    'access_token' => $token,
    'refresh_token' => $token,  // <-- trùng với access_token
    ...
]);
```

**Hệ quả:** Không có cơ chế refresh token độc lập. Token hết hạn sau 60 phút buộc user phải đăng nhập lại. Không thể revoke từng loại token riêng biệt.

---

### 1.5. GHN Shipping — Đồng bộ đơn hàng nhưng không lưu mã vận đơn

Trong `AdminOrderController::syncGHN()`:
```php
// Optionally, save the GHN order code to your database here
// if you add a 'shipping_code' column to the orders table.
// $order->update(['shipping_code' => $result['data']['order_code']]);
```

**Hệ quả:** Admin tạo được đơn GHN nhưng không lưu `shipping_code` / `tracking_number`. Khách hàng không thể tra cứu vận chuyển. Không có khả năng đồng bộ trạng thái giao hàng tự động từ GHN.

---

### 1.6. Admin Live Chat — Chưa có endpoint Admin reply

`ChatController` hiện tại chỉ có:
- `initSession()` — Khởi tạo session cho user
- `sendMessage()` — User gửi tin nhắn, broadcast `MessageSent`

**Thiếu:**
- Endpoint cho Admin nhận danh sách session đang mở
- Endpoint cho Admin gửi tin nhắn trả lời khách hàng
- Đánh dấu tin nhắn đã đọc (`is_read`) từ phía Admin
- Đóng session (`status = 'closed'`)

**Hệ quả:** Frontend có giao diện Admin Chat (`AdminChat.vue`) nhưng thiếu API backend để quản lý hội thoại hai chiều.

---

### 1.7. InventoryTransaction Model & Migration — Chưa được khai thác

- Đã có migration `create_inventory_transactions_table` và model `InventoryTransaction`.
- Controller `InventoryTransactionController` để trống toàn bộ.
- Không có command/job nào ghi nhận nhập/xuất/tồn kho.

**Hệ quả:** Hệ thống không có lịch sử biến động kho (audit trail), không thể kiểm tra ai đã chỉnh sửa tồn kho và khi nào.

---

### 1.8. SellerController — CRUD cơ bản nhưng thiếu phân quyền

- `update()` sử dụng `$request->all()` mà không validate, cho phép mass assignment.
- `destroy()` thực hiện xóa mềm nhưng không kiểm tra quan hệ (có thể xóa nhân viên đang có đơn hàng).
- Thiếu phân trang (`paginate`) trong `index()`.

---

## Phần 2: Các Chức năng ĐANG THIẾU (Chưa có code / Chưa có thiết kế)

### 2.1. Quy trình Đổi / Trả hàng (Return & Refund Flow)

**Mô tả:**
- Hiện tại chỉ có `payment_status = 'refunded'` tự động khi **Admin hủy đơn**.
- Không có flow cho **khách hàng** yêu cầu đổi/trả hàng sau khi nhận.

**Thiếu:**
- Model/Table `ReturnRequest` / `OrderReturn`
- API khách hàng gửi yêu cầu đổi/trả kèm lý do, ảnh minh chứng
- API Admin duyệt/từ chối yêu cầu đổi/trả
- Quy trình hoàn tiền tự động qua VNPay/MoMo (refund API)
- Cập nhật tồn kho khi đổi/trả thành công

---

### 2.2. Theo dõi Vận chuyển (Shipping Tracking)

**Thiếu:**
- Cột `shipping_code` / `tracking_number` trong bảng `orders`
- API tra cứu trạng thái vận chuyển từ GHN cho khách hàng
- Webhook nhận cập nhật trạng thái giao hàng tự động từ GHN
- Hiển thị timeline giao hàng trong Order Detail (frontend + mobile)

---

### 2.3. Quản lý Kho nâng cao (Advanced Warehouse Management)

**Thiếu:**
- Giao diện Admin nhập kho (nhập hàng từ nhà cung cấp)
- Cảnh báo tồn kho thấp (Low Stock Alert)
- Báo cáo xuất/nhập/tồn theo khoảng thời gian
- Quản lý nhiều kho (multi-warehouse)
- Điều phối tồn kho giữa các chi nhánh

---

### 2.4. Chương trình Khách hàng Thân thiết cấp bậc (Loyalty Tiers / VIP)

**Hiện tại:** Chỉ có `reward_points` điểm thưởng đơn thuần.

**Thiếu:**
- Cấp bậc thành viên: Bronze / Silver / Gold / Platinum
- Quyền lợi theo cấp bậc (giảm giá cố định, freeship, ưu tiên flash sale)
- Tự động nâng cấp / hạ cấp dựa trên chi tiêu tích lũy
- Bảng `loyalty_tiers`, `user_tier_history`

---

### 2.5. So sánh Sản phẩm (Product Comparison)

**Thiếu:**
- Bảng/Session lưu danh sách sản phẩm so sánh
- API trả về thông số so sánh theo từng danh mục
- Giao diện so sánh side-by-side (frontend + mobile)

---

### 2.6. Sản phẩm đã xem gần đây (Recently Viewed)

**Thiếu:**
- API ghi nhận sản phẩm đã xem (có thể lưu Redis hoặc DB)
- Hiển thị danh sách "Bạn vừa xem" ở Home / Product Detail
- Đồng bộ recently viewed giữa web và mobile

---

### 2.7. Thông báo Giá giảm / Có hàng lại (Price Drop & Back-in-Stock Alerts)

**Thiếu:**
- Đăng ký nhận thông báo khi sản phẩm giảm giá
- Đăng ký nhận thông báo khi sản phẩm hết hàng có hàng trở lại
- Queue job gửi email/push notification khi điều kiện thỏa mãn

---

### 2.8. Affiliate / Giới thiệu bạn bè (Referral Program)

**Thiếu:**
- Mã giới thiệu (`referral_code`) cho mỗi user
- Theo dõi đơn hàng được tạo từ link giới thiệu
- Tính hoa hồng / điểm thưởng cho người giới thiệu
- Bảng `referrals`, `affiliate_transactions`

---

### 2.9. Đơn hàng Định kỳ / Đăng ký (Subscription / Recurring Orders)

**Thiếu:**
- Đăng ký mua hàng định kỳ (ví dụ: 1 tháng / 3 tháng)
- Tự động tạo đơn hàng mới theo chu kỳ
- Quản lý subscription trong profile
- Bảng `subscriptions`

---

### 2.10. Quản lý Đa người bán (Multi-Vendor Marketplace)

**Hiện tại:**
- Bảng `orders` có cột `seller_id`.
- `SellerController` quản lý user có role `seller`.

**Thiếu:**
- Seller Dashboard riêng biệt (khác với Admin Dashboard)
- Seller chỉ xem được đơn hàng / sản phẩm / doanh thu của mình
- Commission / phí nền tảng trên mỗi đơn hàng
- Rút tiền / Ví điện tử cho Seller
- Đánh giá Seller (seller rating)

---

### 2.11. SEO & Marketing Tools

**Thiếu:**
- Quản lý Meta Tags (title, description, OG tags) cho từng sản phẩm / danh mục / bài viết
- Tạo Sitemap XML tự động
- Quản lý URL Slug / Redirect 301
- Tích hợp Google Analytics 4 / Facebook Pixel từ Admin panel
- A/B Testing cho banner / sản phẩm nổi bật

---

### 2.12. CMS / Trang nội dung tĩnh (Page Management)

**Thiếu:**
- Quản lý các trang tĩnh: Giới thiệu, Chính sách bảo mật, Điều khoản sử dụng, Hướng dẫn mua hàng
- WYSIWYG editor cho Admin tự tạo trang nội dung mà không cần developer
- Bảng `pages` hoặc `cms_blocks`

---

### 2.13. Gift Card / Store Credit

**Thiếu:**
- Tạo và quản lý thẻ quà tặng (Gift Card) có mã code và số dư
- Nạp tiền vào ví / Store Credit của user
- Thanh toán bằng Store Credit

---

### 2.14. Pre-order & Product Bundles

**Thiếu:**
- Chế độ đặt trước sản phẩm chưa có hàng (Pre-order)
- Combo / Bundle sản phẩm giá ưu đãi (mua kèm)

---

### 2.15. Multi-language & Multi-currency

**Thiếu:**
- Hỗ trợ đa ngôn ngữ (i18n cho cả backend lẫn frontend)
- Đa tiền tệ (VND là mặc định, chưa có chuyển đổi USD/EUR)

---

### 2.16. Advanced Role & Permission (ACL chi tiết)

**Hiện tại:** Role đơn giản (`customer`, `staff`, `seller`, `admin`).

**Thiếu:**
- Phân quyền chi tiết theo Policy/Permission (ví dụ: `staff` chỉ được xem đơn hàng nhưng không được xóa sản phẩm)
- Laravel Spatie Permission hoặc Gate chi tiết

---

## Phần 3: Các Vấn đề Kiến trúc / Kỹ thuật liên quan

### 3.1. God Controllers cần refactor

| Controller | Số dòng / Kích thước | Vấn đề |
|---|---|---|
| `ProductController.php` | ~1.200 dòng / 51 KB | Xử lý CRUD, variant, image, barcode, QR, import/export, search, filter, cache — quá nhiều trách nhiệm |
| `ChatbotController.php` | ~726 dòng / 27 KB | Xử lý Gemini API, function calling, conversation history, fallback — cần tách thành service classes |
| `OrderController.php` | ~577 dòng / 23 KB | Checkout logic phức tạp: địa chỉ, coupon, GHN shipping, stock, transaction, payment — cần CheckoutService |
| `VNPayController.php` | ~500+ dòng | Xử lý return URL, IPN, verify signature, idempotency, email — nên tách thành PaymentGatewayService |

### 3.2. Thiếu Unit / Feature Tests

- Thư mục `tests/` tồn tại nhưng chưa thấy test case nào được viết cho các business logic phức tạp (checkout, payment, flash sale, coupon).
- Không có CI/CD pipeline kiểm tra tự động.

### 3.3. Frontend State Management

- Không sử dụng Pinia hoặc Vuex. State được quản lý bằng `ref` cục bộ + `window.dispatchEvent`.
- Rủi ro: Memory leak, khó debug, không có time-travel debugging, không có global state persisted.

### 3.4. Mobile App — Thiếu Auto Refresh Token

- Dio interceptor chỉ log lỗi 401 nhưng không tự động gọi refresh token và retry request.
- User mobile sẽ bị đăng xuất đột ngột sau 60 phút.

---

## Phần 4: Khuyến nghị Ưu tiên

### Ưu tiên Cao (Khắc phục ngay)
1. **Hoàn thiện Flash Sale checkout** — Tạo Order + OrderItem khi mua Flash Sale.
2. **Sửa JWT Refresh** — Tách refresh token thành token riêng biệt với TTL dài hơn.
3. **Lưu shipping_code từ GHN** — Thêm cột và lưu mã vận đơn.
4. **Xóa/Refactor các controller placeholder** — Hoặc implement hoặc xóa để tránh lộ API dead-end.

### Ưu tiên Trung bình (Triển khai trong sprint tới)
5. **Xây dựng Return/Refund flow** — Cần thiết cho uy tín shop.
6. **Hoàn thiện Admin Live Chat** — API Admin reply + quản lý session.
7. **Inventory Transaction Log** — Audit trail cho tồn kho.
8. **Recently Viewed + Product Comparison** — Tăng engagement và conversion.

### Ưu tiên Thấp (Lộ trình dài hạn)
9. Multi-Vendor Marketplace
10. Loyalty Tiers / VIP Program
11. Subscription / Recurring Orders
12. Affiliate / Referral System
13. Multi-language & Multi-currency
14. SEO & CMS Page Builder

---

> **Ghi chú:** Một số chức năng "thiếu" có thể đã được thiết kế ở Database (có bảng/migration) nhưng chưa có API/Controller/Frontend tương ứng. Ví dụ: `inventory_transactions`, `chat_sessions`, `chat_messages`.
