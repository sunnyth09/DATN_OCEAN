# AUDIT REPORT

Ngày phân tích: 2026-06-05  
Phạm vi: `backend` Laravel 12, `frontend` Vue 3.5, MySQL, Redis, Meilisearch, Docker stack

## 1. Tóm tắt điều hành

Dự án không còn ở mức skeleton. Đây là một codebase đã có nhiều nghiệp vụ thật: catalog, cart, checkout, order, coupon, affiliate, blog, POS, attendance, live chat, court booking, flash sale, realtime notification.

Điểm mạnh chính:

- Backend đã tách lớp theo `Controller -> Service -> Repository` ở nhiều module trọng yếu.
- Có JWT auth, role middleware, queue/jobs, scheduler, Redis, Meilisearch, Reverb.
- Có nhiều nghiệp vụ e-commerce thực tế hơn mức MVP thông thường: affiliate, flash sale, return request, POS, review moderation.
- Frontend đã có router, Pinia store, lazy-loading cho phần lớn admin pages.

Rủi ro chính:

- Bảo mật còn nhiều điểm chưa production-ready: log credential đăng nhập, CAPTCHA bị tắt trong controller, JWT lưu ở `sessionStorage`, thiếu email verification/2FA/policy layer.
- Một số side effect thanh toán có thể bị chạy lặp giữa return URL và IPN.
- Cache đang bị xóa toàn cục bằng `Cache::flush()` trong module sản phẩm.
- Môi trường chạy local hiện chưa xác minh được bằng Artisan/Test vì `backend/vendor/autoload.php` đang thiếu.
- SEO của frontend còn rất mỏng: mới có `document.title`, chưa có meta manager runtime, sitemap, canonical, structured data, SSR.

Điểm tổng quan:

| Hạng mục | Điểm | Nhận xét ngắn |
|---|---:|---|
| Kiến trúc | 74/100 | Có cấu trúc nhiều lớp, nhưng domain bị trộn giữa e-commerce, HR và court booking |
| Database | 72/100 | Khá đầy đủ, có lịch sử trạng thái; tài liệu dump đang lỗi thời so với migrations |
| Bảo mật | 58/100 | Có rate limit, OTP reset, upload reprocess avatar; nhưng còn nhiều lỗ hổng quy trình |
| Hiệu năng | 63/100 | Có cache/Redis/search/locking; nhưng cache invalidation và background processing chưa sạch |
| SEO | 35/100 | Chưa đạt chuẩn cho website commerce cần organic traffic |
| Production readiness | 61/100 | Có Docker, cron, Reverb; nhưng local/runtime consistency và queue worker còn yếu |

## 2. Kiến trúc hệ thống

### 2.1 Kiến trúc hiện tại

- Backend: Laravel `^12.0`, PHP `^8.2`, JWT auth (`php-open-source-saver/jwt-auth`)
- Frontend: Vue `^3.5.29`, Vite `^7.3.1`, Pinia `^3.0.4`, Vue Router `^5.0.3`
- Search: Laravel Scout + Meilisearch
- Cache/queue: Redis + database queue
- Realtime: Laravel Reverb + Echo + Pusher protocol
- Infra: Docker Compose gồm `db`, `redis`, `backend`, `nginx_backend`, `meilisearch`, `frontend`

### 2.2 Cấu trúc thư mục

- `backend/app/Http/Controllers`: 59 controller files
- `backend/app/Models`: 50 model files
- `backend/app/Services`: service layer cho auth, order, payment, product, affiliate, cart, court booking...
- `backend/app/Repositories`: repository layer cho product, order, cart, coupon, affiliate...
- `backend/database/migrations`: 81 migration files
- `frontend/src/Pages`: 80 page files
- `frontend/src/stores`: auth, cart, catalog, favorites, return requests, court booking, UI

### 2.3 Pattern đang dùng

Đang dùng pha trộn các pattern:

- Service layer: dùng tốt ở `AuthService`, `OrderService`, `ProductService`, `PaymentProcessingService`, `CourtBookingService`
- Repository layer: có ở product/order/cart/coupon/affiliate
- Fat route file: gần như toàn bộ API dồn vào `backend/routes/api.php`
- Realtime event-based: dùng cho order, notification, live chat, court booking
- Modular theo domain chưa triệt để: e-commerce, HR attendance và court booking cùng sống trong một monolith

### 2.4 Đánh giá chất lượng kiến trúc

Tốt:

- Có tách service/repository ở các domain lớn.
- Có transaction và `lockForUpdate()` ở order/payment/court booking.
- Có nền tảng tốt để mở rộng thêm domain.

Chưa tốt:

- `routes/api.php` quá dài, đang là điểm nghẽn maintainability.
- Không thấy `Policies`, `Listeners`, `Observers` theo chuẩn Laravel domain-driven.
- Có song song `users.role` và bảng `admins` với guard riêng, làm auth/authorization phức tạp.
- Route, controller, service và frontend đang chứa cả nghiệp vụ bán hàng lẫn vận hành nội bộ.

## 3. Database analysis

## 3.1 Nhóm bảng chính

Commerce core:

- `users`, `addresses`
- `categories`, `brands`, `products`, `product_variants`, `product_images`
- `carts`, `cart_items`
- `orders`, `order_items`, `order_status_histories`, `payments`
- `coupons`, `user_coupons`, `coupon_categories`
- `promotions`, `promotion_categories`, `promotion_products`, `promotion_usages`
- `favorites`, `product_comments`

Content/CRM:

- `posts`, `post_categories`
- `contacts`
- `notifications`
- `chat_sessions`, `chat_messages`

Growth:

- `affiliate_clicks`, `affiliate_conversions`, `affiliate_withdrawals`

Operations:

- `attendances`, `work_locations`, `work_shifts`, `shift_assignments`

Court booking:

- `courts`, `court_schedules`, `court_prices`, `court_bookings`
- `court_booking_locks`, `court_booking_status_histories`
- `court_booking_services`, `court_booking_payments`, `court_booking_extensions`
- `court_services`, `court_maintenances`, `court_activity_logs`

System:

- `cache`, `cache_locks`, `jobs`, `failed_jobs`, `job_batches`, `sessions`, `migrations`

Returns:

- `return_requests`

### 3.2 Quan hệ, khóa chính, khóa ngoại

Mức độ ràng buộc nhìn chung khá tốt:

- FK rõ giữa user-address-cart-order
- FK rõ giữa product-category-brand-variant-image
- FK rõ giữa order-order_item-payment-status_history
- FK rõ giữa affiliate tables và users/orders
- FK rõ giữa court booking tables và courts/users/services

Điểm tốt:

- Có nhiều unique index quan trọng: email, phone, sku, slug, coupon code, product variant combination
- Có bảng lịch sử trạng thái cho order và court booking
- Có soft delete ở các bảng chính: `users`, `products`, `posts`, `coupons`

Điểm cần lưu ý:

- File dump `backend/database/schema_utf8.sql` được export ngày 2026-04-02, nhưng migrations còn tiếp tục tới cuối 2026-05. Điều này làm tài liệu schema hiện có bị lệch so với source thật.
- `shipping_zones` có migration nhưng chưa thấy model/controller/service tương ứng.
- `coupon_categories` có dùng ở model coupon, nhưng không thấy trải nghiệm frontend rõ ràng cho coupon theo category.

### 3.3 Dư thừa / thiếu chuẩn hóa / audit

Nhận định:

- Chưa thấy bảng audit tổng quát cho mọi model; hiện chỉ có audit cục bộ theo domain như `order_status_histories`, `court_activity_logs`, `court_booking_status_histories`.
- Dữ liệu DB và code đang được mô tả bởi cả migration lẫn file `.sql` dump; cách này dễ gây drift.
- Có dấu hiệu domain mở rộng nhanh hơn quá trình chuẩn hóa tài liệu.

## 4. Phân tích chức năng hiện tại

| Module | Chức năng | Trạng thái |
|---|---|---|
| Authentication | Login, register, refresh JWT, Google/Facebook login | Đã có, nhưng thiếu verify email/2FA |
| Password reset | OTP email reset password | Đã có |
| User profile | Hồ sơ, avatar, đổi mật khẩu, địa chỉ | Đã có |
| Catalog | Category, brand, product, variant, gallery, featured, related | Đã có |
| Search | Search + Meilisearch fallback | Đã có cơ bản |
| Cart | Add/update/remove/change variant/clear/buy again/upsell | Đã có |
| Checkout | Order creation, coupon, shipping fee, payment method | Đã có |
| Orders | Order history, detail, cancel, status history | Đã có |
| Payments | COD, VNPay, MoMo, bank transfer/SePay | Đã có một phần |
| Returns | Return request workflow + admin approval/refund | Đã có |
| Reviews | Review after order + moderation | Đã có, chưa thấy ảnh review |
| Wishlist | Favorites / wishlist | Đã có |
| Marketing | Coupon, flash sale, promotion tables, affiliate | Đã có một phần |
| Loyalty | Reward points cơ bản | Đã có rất cơ bản |
| Blog | Post, post category, SEO fields | Đã có backend cơ bản |
| Notifications | Inbox notification + realtime event | Đã có, thiếu SMS/push thật |
| Admin | Dashboard, users, staff, orders, products, contacts, reviews, coupon | Đã có |
| POS | Search/scan/checkout/receipt PDF | Đã có |
| Attendance | Check-in/out, work locations, shifts | Đã có |
| Court booking | Booking, lock slot, payment, admin calendar/dashboard | Đã có mạnh |

## 5. Kiểm tra nghiệp vụ e-commerce

### 5.1 Những gì đã đáp ứng tốt

- Product catalog nhiều biến thể, SKU, barcode, stock, gallery
- Cart và checkout đủ dùng cho B2C cơ bản
- Coupon và flash sale đã vượt mức MVP
- Order lifecycle có history, payment status, cancel flow
- Review moderation, wishlist, affiliate, POS là điểm cộng

### 5.2 Những gì còn thiếu hoặc mới dừng ở mức cơ bản

- Verify email sau đăng ký
- OTP cho đăng nhập/đăng ký
- Social login mới có Google/Facebook
- Shipping zone hiện mới thấy DB/migration, chưa thành module quản trị đầy đủ
- Payment gateway theo yêu cầu thị trường Việt Nam còn thiếu ZaloPay
- Quốc tế còn thiếu Stripe/PayPal
- Loyalty mới chỉ là `reward_points`, chưa có tier/cashback/rule engine
- Review chưa thấy ảnh/video review
- SEO blog/catalog chưa hoàn chỉnh
- Chưa thấy recommendation engine, bundle/combo thực thụ, CRM segmentation

## 6. Frontend Vue.js

### 6.1 Kiến trúc frontend

- Vue Router dùng lazy-loading cho phần lớn route
- Pinia có store riêng cho auth, cart, catalog, favorites, returns, court booking
- Axios có interceptor refresh token
- Layout tách `ClientLayout`, `AdminLayout`, `SellerLayout`, `AuthLayout`

### 6.2 Điểm mạnh

- Route guard theo role hoạt động rõ ràng
- Frontend page coverage lớn cho cả client và admin
- Có session sync đa tab
- Có các component hữu ích: search modal, quick add, chatbot widget, virtual try-on, barcode scanner

### 6.3 Điểm yếu

- JWT lưu ở `sessionStorage`, không phải HTTP-only cookie
- SEO runtime rất mỏng dù đã cài `@unhead/vue` nhưng chưa thấy dùng
- Naming chưa thống nhất hoàn toàn (`Pages`, `admin`, `Home`, `productDetail.vue`, `login.vue`)
- Có nhiều logic trực tiếp trong page lớn, chưa tách composable/store triệt để

## 7. API analysis

Đánh giá nhanh:

- API coverage lớn
- Validation có nhưng chưa đồng đều, nhiều controller vẫn nhận `Request` trực tiếp
- Chưa thấy OpenAPI/Swagger/Postman source chính thức
- RESTful ở mức khá, nhưng có nhiều route action-style như `approve`, `reject`, `buy`, `initialize`, `track-click`, `qr-check-in`
- `backend/routes/api.php` đang gánh quá nhiều nghiệp vụ trong một file

Điểm đáng chú ý:

- Có route debug vẫn tồn tại trong codebase
- Có duplicate route `POST /payment/momo-ipn`
- Có chồng chéo trách nhiệm giữa route public và route admin ở một số controller sản phẩm

## 8. SEO

Hiện trạng:

- Có `robots.txt`
- Frontend chỉ cập nhật `document.title` trong router
- Chưa thấy `useHead`/`createHead` runtime dù package đã cài
- Chưa thấy sitemap generator
- Chưa thấy canonical URL
- Chưa thấy structured data / JSON-LD
- Chưa có SSR/SSG

Kết luận: chưa đủ chuẩn SEO cho website thương mại điện tử phụ thuộc organic traffic.

## 9. Security

Chi tiết tại `SECURITY_REPORT.md`.

Kết luận nhanh:

- Có nền tảng phòng thủ cơ bản: rate limiting, OTP reset, role middleware, upload avatar reprocess, image proxy có boundary check
- Nhưng vẫn còn các lỗi quy trình nghiêm trọng khiến điểm bảo mật chưa đạt production-ready

## 10. Performance

Chi tiết tại `PERFORMANCE_REPORT.md`.

Kết luận nhanh:

- Có cache, Redis, eager loading, search index, DB transaction lock
- Nhưng cache invalidation, queue runtime, unpaginated endpoints và SEO/CSR làm trải nghiệm scale-up chưa tốt

## 11. So sánh với e-commerce chuẩn

So với Shopee/Tiki/Lazada/Amazon, hệ thống hiện ở mức:

- Vượt MVP cơ bản
- Đủ để làm internal commerce platform hoặc SMB/Mid-market commerce
- Chưa đạt mức production commerce platform chuẩn enterprise

Khoảng cách chính:

- trust & security
- marketing automation
- loyalty nâng cao
- SEO stack
- observability
- CI/CD và runtime consistency

## 12. Kết luận

Dự án có nền tảng tốt hơn nhiều hệ thống Laravel + Vue mới bắt đầu vì đã có nhiều module thương mại điện tử thật. Tuy nhiên codebase đang tăng nhanh theo chiều rộng tính năng hơn là chiều sâu kiến trúc vận hành.

Muốn đưa hệ thống lên production ổn định, cần ưu tiên theo thứ tự:

1. Bịt rủi ro bảo mật và callback payment.
2. Chuẩn hóa runtime, queue, testability, tài liệu schema.
3. Chuẩn hóa API/frontend/SEO.
4. Hoàn thiện các gap thương mại điện tử còn thiếu.

Xem kế hoạch triển khai ở `ROADMAP_ECOMMERCE.md`.
