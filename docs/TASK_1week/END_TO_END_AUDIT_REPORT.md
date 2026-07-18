# 🔍 QS_PROJECT — END-TO-END AUDIT REPORT & ACTION PLAN
> **Phiên bản:** Post-merge audit · **Ngày:** 2026-07-18  
> **Thực hiện bởi:** Senior Tech Lead & QA Manager  
> **Phạm vi:** Backend Laravel · Frontend Vue 3 · Mobile Flutter · Python Face-Service · Docker/Infra

---

## PHẦN 1 — AUDIT REPORT TOÀN DIỆN

### Thang phân loại mức độ nghiêm trọng

| Badge | Ý nghĩa |
|-------|---------|
| 🔴 **CRITICAL** | Gây mất tiền / lỗ hổng bảo mật nghiêm trọng / crash production |
| 🟠 **HIGH** | Ảnh hưởng trực tiếp UX/Logic kinh doanh, cần fix trong sprint tới |
| 🟡 **MEDIUM** | Debt kỹ thuật, performance, trải nghiệm giảm nhưng chưa gây sập hệ thống |
| 🟢 **INFO** | Gợi ý cải tiến, best practice |

---

## 1. 🏗️ KIẾN TRÚC & DATABASE

### 🔴 CRITICAL — Tên file migration có khoảng trắng

**File:** `2026_06_05_000002_harden_payment_idempotency_state copy.php`

Tên file migration chứa khoảng trắng (`state copy.php`). Trên nhiều hệ thống Linux và CI/CD pipelines, file này có thể không được autoload đúng, dẫn đến **schema production thiếu 3 cột quan trọng** của bảng `payments` (`post_payment_status`, `post_payment_started_at`, `post_payment_last_error`), phá vỡ toàn bộ cơ chế idempotency thanh toán.

```
❌ 2026_06_05_000002_harden_payment_idempotency_state copy.php
✅ 2026_06_05_000002_harden_payment_idempotency_state.php
```

### 🔴 CRITICAL — `generateOrderCode()` không đảm bảo uniqueness dưới tải cao

**File:** [`OrderService.php:758`](file:///d:/source_code/laravel/qs_project/backend/app/Services/OrderService.php)

```php
// HIỆN TẠI — CÓ RỦI RO TRÙNG LẶP
private function generateOrderCode(): string
{
    return 'ORD' . strtoupper(uniqid()) . rand(10, 99);
}
```

`uniqid()` dựa vào microseconds — dưới tải đồng thời cao (Flash Sale), xác suất collision là có thực. Bảng `orders` có `UNIQUE` constraint trên `order_code` → insert sẽ fail với exception không bắt, khiến user mất tiền đã thanh toán mà không có đơn hàng.

**Fix:** Thay bằng `Str::uuid()` hoặc Snowflake ID.

### 🔴 CRITICAL — Thiếu multi-branch (multi-tenant) hoàn toàn

Sau khi scan **111 migrations**, không có bất kỳ cột `branch_id` hay `store_id` nào ở các bảng cốt lõi (`products`, `orders`, `inventory_transactions`, `promotions`). Hệ thống **không hỗ trợ đa chi nhánh**. Nếu mở rộng sau này, sẽ phải viết lại toàn bộ schema và business logic.

### 🟠 HIGH — Thiếu index nghiêm trọng trên các bảng hot

Sau phân tích các migration file, phát hiện các bảng truy vấn thường xuyên nhưng **thiếu composite index**:

| Bảng | Cột thiếu Index | Impact |
|------|----------------|--------|
| `orders` | `(user_id, fulfillment_status)` | Trang lịch sử đơn hàng slow |
| `orders` | `(fulfillment_status, created_at)` | Dashboard admin slow |
| `order_items` | `(variant_id)` | Báo cáo bán hàng slow |
| `cart_items` | `(cart_id, variant_id)` | Cart sync slow |
| `product_variants` | `(product_id, status)` | Product listing slow |
| `loyalty_transactions` | `(user_id, type, status)` | Lịch sử điểm slow |
| `wallet_transactions` | `(wallet_id, created_at)` | Sao kê ví slow |
| `attendances` | `(admin_id, check_in_at)` | Báo cáo chấm công slow |

### 🟠 HIGH — `calculateSubtotalAndValidateStock()` chạy TRƯỚC transaction

**File:** [`OrderService.php:110` & `416`](file:///d:/source_code/laravel/qs_project/backend/app/Services/OrderService.php)

```php
// NGOÀI transaction — stock có thể thay đổi giữa check và lock
$subtotal = $this->calculateSubtotalAndValidateStock($cartItems);
...
// Mới vào transaction
$result = DB::transaction(function () ... {
    $this->lockAndValidateStock($cartItems); // Lock lần 2
```

Có 2 lần check stock: lần 1 ngoài transaction (validate để tính giá), lần 2 trong transaction (lock thật). Đây là pattern đúng **nếu** giá được re-validate trong transaction. Tuy nhiên, **subtotal và grandTotal được tính từ lần check 1 (giá cũ)**, nếu admin thay đổi giá sản phẩm giữa 2 lần check → user có thể mua với giá sai. Cần validate lại price trong `lockAndValidateStock`.

### 🟡 MEDIUM — `reserved_stock` không được sử dụng nhất quán

Bảng `product_variants` có cột `reserved_stock` nhưng logic `lockAndValidateStock` chỉ check `stock >= quantity` mà **không trừ `reserved_stock`**. Trong Flash Sale với hàng nghìn concurrent requests, oversell vẫn có thể xảy ra nếu `stock` chưa kịp deduct.

### 🟡 MEDIUM — Không có soft delete trên `orders`

Bảng `orders` không có `SoftDeletes`. Nếu admin vô tình xóa đơn hàng (qua Tinker hay raw SQL), không thể khôi phục.

---

## 2. 🔒 SECURITY & PERFORMANCE

### 🔴 CRITICAL — `XssSanitizer` bỏ qua HTML fields mà không sanitize đúng cách

**File:** [`XssSanitizer.php`](file:///d:/source_code/laravel/qs_project/backend/app/Http/Middleware/XssSanitizer.php)

```php
$skipHtmlFields = ['content', 'description', 'body', 'html_content'];
if (is_string($input) && !in_array($key, $skipHtmlFields)) {
    $input = strip_tags($input); // Chỉ strip, không encode!
}
```

- **Vấn đề 1:** `strip_tags()` không xử lý được các payload XSS trong attributes như `<img src=x onerror="alert(1)">` — `strip_tags` sẽ chỉ bỏ tag nhưng giữ lại nếu tag được viết theo cách lạ.
- **Vấn đề 2:** Các field trong `$skipHtmlFields` (`description`, `content`) hoàn toàn không được sanitize, ai cũng có thể lưu HTML tùy ý vào DB.
- **Fix:** Dùng `HTMLPurifier` hoặc `mews/purifier` cho HTML fields; dùng `htmlspecialchars()` cho text fields.

### 🔴 CRITICAL — Face Service exposed không có Authentication

**File:** [`face-service/app.py`](file:///d:/source_code/laravel/qs_project/face-service/app.py), [`docker-compose.yml`](file:///d:/source_code/laravel/qs_project/docker-compose.yml)

```yaml
# docker-compose.yml line 99
expose:
  - "8001"
```

Face Service chỉ dùng `expose` (internal network) — ổn cho Docker. Tuy nhiên:
- Không có **API key / Bearer token** authentication trên `/encode` và `/verify` endpoints.
- Nếu ai đó trong cùng Docker network (ví dụ một container bị compromise) có thể gọi tự do để khai thác face encodings của nhân viên.
- `TODO(security)` vẫn còn trong code — cho thấy security chưa được xử lý.

### 🔴 CRITICAL — `MoMoController` xử lý payment state trong Return URL (không nên)

**File:** [`MoMoController.php:54-73`](file:///d:/source_code/laravel/qs_project/backend/app/Http/Controllers/MoMoController.php)

`momoReturn()` đang **update `payment_status = 'paid'`** trực tiếp trong Return URL callback (là URL redirect của user). Return URL **không đáng tin cậy** — user có thể giả mạo/replay. Chỉ IPN (server-to-server webhook) mới nên update trạng thái. `PaymentProcessingService` xử lý đúng, nhưng `MoMoController` cũ vẫn tồn tại song song và có thể được route gọi → **double-execution risk**.

### 🟠 HIGH — JWT Refresh token stored trong sessionStorage

**File:** [`auth.js:24`](file:///d:/source_code/laravel/qs_project/frontend/src/stores/auth.js)

```js
const getStorageToken = () => sessionStorage.getItem(STORAGE_KEYS.token) || '';
```

`sessionStorage` tốt hơn `localStorage` về XSS vì không persist qua tab. Tuy nhiên, token vẫn accessible bởi JS — nếu có XSS, token bị lộ. Lý tưởng là dùng **HttpOnly cookie** cho access token. Hiện tại `refreshPromise` không có timeout xử lý trường hợp refresh bị treo vô hạn.

### 🟠 HIGH — Rate Limiting thiếu trên nhiều endpoint quan trọng

Trong [`api.php`](file:///d:/source_code/laravel/qs_project/backend/routes/api.php), kiểm tra:
- `POST /api/orders` — **Không có** throttle → user có thể spam tạo đơn hàng
- `POST /api/cart/items` — **Không có** throttle → cart spam
- `POST /api/contacts` — **Không có** throttle → email spam
- `GET /api/products` (listing) — **Không có** throttle → scraping dễ dàng
- Chỉ auth routes mới có throttle (login: 20/min, register: 10/min, OTP: 3/min)

### 🟠 HIGH — Nginx thiếu Security Headers

**File:** [`nginx/backend.conf`](file:///d:/source_code/laravel/qs_project/nginx/backend.conf)

Hoàn toàn thiếu:
- `X-Frame-Options: DENY` → Clickjacking
- `X-Content-Type-Options: nosniff`
- `Content-Security-Policy` header
- `Strict-Transport-Security` (HSTS) — chưa có HTTPS termination
- `Referrer-Policy`

### 🟡 MEDIUM — `ngrok-skip-browser-warning` header trong production axios

**File:** [`axios.js:10`](file:///d:/source_code/laravel/qs_project/frontend/src/axios.js)

```js
'ngrok-skip-browser-warning': '69420',
```

Header này chỉ dùng cho dev/ngrok tunnel. Để lại trong production code → lộ thông tin môi trường phát triển.

### 🟡 MEDIUM — Meilisearch chạy với `MEILI_ENV: development`

**File:** [`docker-compose.yml:70`](file:///d:/source_code/laravel/qs_project/docker-compose.yml)

```yaml
MEILI_ENV: development  # ← Sẽ expose search preview UI và API keys
```

Trong môi trường production, phải set `MEILI_ENV: production` và restrict access.

### 🟡 MEDIUM — Face Service không giới hạn request rate

**File:** [`face-service/app.py`](file:///d:/source_code/laravel/qs_project/face-service/app.py)

Endpoint `/encode` và `/verify` không có rate limiting. Dlib face recognition là CPU-intensive — có thể bị DoS bằng cách spam requests.

---

## 3. 💻 CODE QUALITY — FRONTEND & MOBILE

### 🟠 HIGH — CartController `getGuestDetails` không dùng `effective_price`

**File:** [`CartController.php:245`](file:///d:/source_code/laravel/qs_project/backend/app/Http/Controllers/CartController.php)

```php
'price' => $variant->price,  // ← Giá gốc, không phải giá sale!
'line_total' => $variant->price * $item['quantity'],  // ← Sai với sale items
```

Guest checkout hiển thị `price` raw thay vì `effective_price` (tính cả sale_price). Guest có thể checkout với giá sai và bị charge sai amount.

### 🟠 HIGH — Flutter: `provider` package — Không dispose listeners

**File:** [`mobile/lib/providers/`](file:///d:/source_code/laravel/qs_project/mobile/lib/providers/)

`AuthProvider` và `CartProvider` extends `ChangeNotifier`. Nếu widget không gọi `removeListener()` khi dispose → **memory leak** khi navigate liên tục giữa các màn hình (đặc biệt trên màn hình checkout và payment flow).

### 🟠 HIGH — Flutter thiếu dependency cho offline/payment

Trong `pubspec.yaml`, thiếu:
- **`flutter_riverpod` hoặc `bloc`** — `provider` thuần không đủ mạnh cho app phức tạp
- **`connectivity_plus`** — Không detect mất mạng khi thanh toán
- **`flutter_stripe` hoặc MoMo/VNPay SDK** — Không có payment SDK chính thức, phải implement manual HTTP
- **`cached_network_image`** ✅ có — tốt
- Không có **unit test dependency** ngoài `flutter_test` cơ bản

### 🟡 MEDIUM — Vue 3 Pinia stores — `useCourtBookingStore` rất lớn (9.8KB)

**File:** [`stores/useCourtBookingStore.js`](file:///d:/source_code/laravel/qs_project/frontend/src/stores/useCourtBookingStore.js) (9,853 bytes)

Store court booking quá fat — mix lẫn UI state, API calls, và business logic trong cùng một store. Cần refactor thành composables và service layer riêng.

### 🟡 MEDIUM — Over-fetching trong OrderService response

**File:** [`OrderService.php`](file:///d:/source_code/laravel/qs_project/backend/app/Services/OrderService.php)

`getUserOrders()` load eager relationships bao gồm `items` (kèm `comment`) cho mỗi đơn trong danh sách → `is_reviewed` check. Với user có 100+ đơn hàng, đây là N+1 equivalent dưới dạng eager load quá mức.

### 🟡 MEDIUM — `sessionSync.js` và Broadcast Logout — Tiềm ẩn memory leak

**File:** [`sessionSync.js`](file:///d:/source_code/laravel/qs_project/frontend/src/sessionSync.js) (4,510 bytes)

BroadcastChannel listeners không được cleanup khi component unmount có thể dẫn đến memory leak trong SPA long-session.

### 🟡 MEDIUM — Frontend `.env` chứa `VITE_` prefix nhưng không validate

Frontend env không có runtime validation — nếu `VITE_API_URL` bị thiếu, app fallback về localhost thay vì fail rõ ràng, gây ra lỗi khó debug trên production.

---

## 4. 🧪 QA & DEPLOY

### 🔴 CRITICAL — Unit Test coverage gần như bằng 0

```
tests/Unit/ExampleTest.php  — 243 bytes (chỉ là placeholder!)
tests/Feature/             — 6 files
```

**Không có** Unit Test cho:
- `OrderService` (logic phức tạp nhất — tính giá, coupon, wallet, combo)
- `LoyaltyService` (burn/earn points)
- `CartService`
- `CouponService`
- `ProductVariant` accessors (`effective_price`, `is_on_sale`)

Chỉ có Feature Tests cho: Auth, CourtBooking, Loyalty, Payment, Wallet — nhưng **không có** cho Order creation, Cart, Flash Sale, Affiliate.

### 🔴 CRITICAL — Docker compose frontend chạy `npm run dev` trên production

**File:** [`docker-compose.yml:89`](file:///d:/source_code/laravel/qs_project/docker-compose.yml)

```yaml
command: sh -c "npm install && npm run dev -- --host 0.0.0.0 --port 3302"
```

`npm run dev` là Vite dev server — **KHÔNG phải production build**. Nó:
- Không minify/optimize code
- Expose Vite HMR WebSocket
- Chạy với source maps đầy đủ → lộ toàn bộ source code
- Hiệu năng kém hơn nhiều so với production build

### 🟠 HIGH — Không có CI/CD Pipeline (GitHub Actions / GitLab CI)

Không tìm thấy bất kỳ file `.github/workflows/*.yml` hay `.gitlab-ci.yml` nào trong repository. Deploy hiện tại là **manual** — không có automated test, lint, build validation trước khi merge.

### 🟠 HIGH — `docker-compose.yml` dùng chung cho dev lẫn prod

Không có `docker-compose.prod.yml` hay environment separation. Một số settings dangerous cho production:
- `MEILI_ENV: development`
- Frontend đang chạy dev server
- Không có health check cho Redis
- Backend volume mount `./backend:/var/www` (live reload — không nên trên prod)

### 🟠 HIGH — Backend Dockerfile không có multi-stage build

**File:** [`backend/Dockerfile`](file:///d:/source_code/laravel/qs_project/backend/Dockerfile)

Image production nặng hơn cần thiết (dev deps được bao gồm). Cần multi-stage build để loại bỏ `devDependencies`.

### 🟡 MEDIUM — Meilisearch port 7700 exposed public

**File:** [`docker-compose.yml:67`](file:///d:/source_code/laravel/qs_project/docker-compose.yml)

```yaml
ports:
  - "7700:7700"  # ← Public exposed!
```

Meilisearch admin UI và API ở port 7700 đang exposed ra internet thay vì chỉ internal. Ai cũng có thể đọc toàn bộ search index (tên sản phẩm, giá, etc.).

### 🟡 MEDIUM — Redis container không có password / health check

```yaml
redis:
  image: redis:alpine
  restart: always
  # Không có password, không có healthcheck
```

Redis không có authentication và không có health check. Nếu Redis down, các service phụ thuộc (cache, queue, session) sẽ fail silently.

---

## TỔNG HỢP CRITICAL ISSUES — QUICK REFERENCE

| # | Issue | Severity | File |
|---|-------|----------|------|
| 1 | Tên migration có khoảng trắng → schema thiếu | 🔴 CRITICAL | `migrations/` |
| 2 | `generateOrderCode()` có thể trùng | 🔴 CRITICAL | `OrderService.php` |
| 3 | Không hỗ trợ multi-branch | 🔴 CRITICAL | Toàn bộ schema |
| 4 | XSS Sanitizer bỏ qua HTML fields | 🔴 CRITICAL | `XssSanitizer.php` |
| 5 | Face Service không có auth | 🔴 CRITICAL | `face-service/app.py` |
| 6 | MoMo Return URL update payment state | 🔴 CRITICAL | `MoMoController.php` |
| 7 | Unit Test coverage = 0% | 🔴 CRITICAL | `tests/Unit/` |
| 8 | Docker prod chạy `npm run dev` | 🔴 CRITICAL | `docker-compose.yml` |
| 9 | Thiếu index trên bảng hot | 🟠 HIGH | Database schema |
| 10 | Rate limiting thiếu trên Orders/Cart | 🟠 HIGH | `api.php` |
| 11 | Nginx thiếu security headers | 🟠 HIGH | `nginx/backend.conf` |
| 12 | Guest cart dùng price gốc thay vì effective | 🟠 HIGH | `CartController.php` |
| 13 | Flutter không dispose ChangeNotifier listeners | 🟠 HIGH | `providers/` |
| 14 | Không có CI/CD pipeline | 🟠 HIGH | Repo root |
| 15 | Meilisearch port exposed public | 🟡 MEDIUM | `docker-compose.yml` |

---
---

## PHẦN 2 — ACTION PLAN: TIMELINE 1 TUẦN

### 👥 Phân công nhân sự

| Thành viên | Role | Trách nhiệm chính |
|-----------|------|-------------------|
| **TÀI** | Frontend Dev | UI/UX, Dashboard thống kê, CSS fixes |
| **VŨ** | Frontend Dev | Chatbot AI UI, Payment flow UI, SEO/SSR |
| **DƯƠNG** | Backend Lead | Database refactor, Race condition, Security API |
| **THÀNH** | Backend Dev | AI Chatbot logic, Queue, Payment fixes |
| **BÌNH** | Mobile + QA Lead | Flutter fixes, CI/CD, Code review, Release |

---

## 📅 DAY 1 — THỨ HAI: KICKOFF & CRITICAL FIXES SETUP

> **Milestone:** Tất cả hiểu rõ issues, môi trường sẵn sàng, bắt đầu fix critical bugs.

### DƯƠNG — Backend (Database & Security Foundation)
- [ ] **[CRITICAL]** Rename migration file: `harden_payment_idempotency_state copy.php` → xóa khoảng trắng, chạy `php artisan migrate:fresh --seed` trên staging để xác nhận
- [ ] **[CRITICAL]** Fix `generateOrderCode()` → thay bằng `Str::uuid()` hoặc custom Snowflake ID, đảm bảo không collide
- [ ] **[HIGH]** Viết migration mới thêm composite indexes: `orders(user_id, fulfillment_status)`, `orders(fulfillment_status, created_at)`, `order_items(variant_id)`, `cart_items(cart_id, variant_id)`, `product_variants(product_id, status)`

### THÀNH — Backend (Security & Payment)
- [ ] **[CRITICAL]** Fix `XssSanitizer.php` → cài `mews/purifier`, dùng HTMLPurifier cho HTML fields, `htmlspecialchars()` cho text fields
- [ ] **[CRITICAL]** Refactor `MoMoController::momoReturn()` → xóa toàn bộ logic update `payment_status`, chỉ đọc trạng thái và return response UI; tất cả state updates chuyển về IPN handler
- [ ] Cài `mews/purifier`: `composer require mews/purifier`

### TÀI — Frontend (Audit & Setup)
- [ ] Audit toàn bộ các component đang render HTML từ API (description, content fields) — kiểm tra có dùng `v-html` không và liệu đã sanitize chưa
- [ ] Remove `'ngrok-skip-browser-warning': '69420'` khỏi `axios.js`
- [ ] Setup và verify môi trường dev local chạy ổn

### VŨ — Frontend (Audit & Setup)
- [ ] Audit router/routes — liệt kê tất cả các route chưa có `meta: { title }` cho SEO
- [ ] Kiểm tra `sessionSync.js` — xác định BroadcastChannel có được cleanup đúng không
- [ ] Cài thêm `vite-plugin-sitemap` nếu cần SEO

### BÌNH — Mobile + QA Setup
- [ ] **[CRITICAL]** Setup GitHub Actions workflow file: `.github/workflows/ci.yml` với steps: lint PHP, run PHPUnit, build Docker
- [ ] Đọc toàn bộ `MOBILE_AUDIT_REPORT.md` đã có trong `mobile/` — tổng hợp priority list
- [ ] Setup Flutter test environment, chạy `flutter analyze` để biết số lượng warnings hiện tại
- [ ] Tạo branch `release/week-fix` từ `main`

---

## 📅 DAY 2 — THỨ BA: BACKEND CORE FIXES

> **Milestone:** Tất cả CRITICAL backend bugs được fix hoặc đang trong PR.

### DƯƠNG — Backend (Stock & Race Condition)
- [ ] **[HIGH]** Fix `lockAndValidateStock()` → thêm price re-validation trong transaction để tránh tính giá từ lần check ngoài transaction
- [ ] **[MEDIUM]** Implement `reserved_stock` deduction pattern: khi `lockAndValidateStock`, check `stock - reserved_stock >= quantity`
- [ ] Thêm remaining indexes: `loyalty_transactions(user_id, type, status)`, `wallet_transactions(wallet_id, created_at)`, `attendances(admin_id, check_in_at)`
- [ ] **[HIGH]** Thêm throttle middleware cho `POST /api/orders` (10/min), `POST /api/cart/items` (30/min), `POST /api/contacts` (5/min)

### THÀNH — Backend (Face Service & AI)
- [ ] **[CRITICAL]** Thêm API key authentication cho Face Service — thêm dependency `python-jose` hoặc đơn giản là shared secret header `X-Internal-Secret`, validate trong FastAPI middleware
- [ ] Thêm rate limiting cho Face Service (`slowapi` library)
- [ ] **[HIGH]** Refactor AI Chatbot (`GeminiService.php`) — kiểm tra có đang cache responses không, nếu không → thêm Redis cache cho FAQ responses
- [ ] Fix `MoMoController` (tiếp theo từ Day 1 nếu chưa xong)

### TÀI — Frontend (UI Fixes)
- [ ] Fix tất cả components đang dùng `v-html` mà không sanitize — thêm DOMPurify
- [ ] Bắt đầu xây dựng Dashboard thống kê mới (Layout + Chart components skeleton)
- [ ] Fix responsive layouts đang broken (audit từ Day 1)

### VŨ — Frontend (SEO & Payment Flow)
- [ ] Thêm `<title>` dynamic và `<meta name="description">` cho tất cả pages trong router
- [ ] Kiểm tra và fix payment callback pages (VNPay return, MoMo return) — đảm bảo UI render đúng khi payment success/fail
- [ ] Fix `sessionSync.js` — cleanup BroadcastChannel listener trong `onUnmounted` hook

### BÌNH — CI/CD + Flutter
- [ ] Hoàn thiện GitHub Actions CI: `php artisan test`, `./vendor/bin/pint --test` (lint), build Docker image
- [ ] **[HIGH]** Fix Flutter `AuthProvider` và `CartProvider` — thêm `dispose()` method cleanup listeners
- [ ] Bắt đầu audit và fix memory leaks trên payment screens Flutter

---

## 📅 DAY 3 — THỨ TƯ: BACKEND COMPLETION + FRONTEND FEATURES

> **Milestone:** Backend PR reviews xong, Frontend features đang build.

### DƯƠNG — Backend (Cleanup & Docs)
- [ ] **[HIGH]** Fix `nginx/backend.conf` — thêm security headers:
  ```nginx
  add_header X-Frame-Options "DENY" always;
  add_header X-Content-Type-Options "nosniff" always;
  add_header Referrer-Policy "strict-origin-when-cross-origin" always;
  add_header Content-Security-Policy "default-src 'self'..." always;
  ```
- [ ] Fix Docker `docker-compose.yml`: thêm Redis healthcheck + password, fix Meilisearch sang `MEILI_ENV: production`, đổi Meilisearch port sang internal-only (xóa port mapping public)
- [ ] Tạo `docker-compose.prod.yml` với overrides cho production (remove volume mounts, production configs)
- [ ] Review và merge PRs của Thành từ Day 1-2

### THÀNH — Backend (Queue & Jobs)
- [ ] **[HIGH]** Kiểm tra tất cả email-sending và heavy operations trong controllers — chuyển sang Laravel Queue (`dispatch(new SomeJob())`)
- [ ] Verify `OrderCreatedAdmin` event và notification jobs đều chạy qua queue không synchronous
- [ ] Kiểm tra `QUEUE_CONNECTION=redis` đã được set trên production env chưa
- [ ] **[MEDIUM]** Refactor `GeminiService` chatbot responses → thêm streaming hoặc timeout handling

### TÀI — Frontend (Dashboard)
- [ ] Hoàn thiện Dashboard thống kê: Revenue chart (Chart.js/Recharts), Top products table, Order status breakdown
- [ ] Implement lazy-loading cho heavy components (dynamic import)
- [ ] Fix CSS inconsistencies phát hiện từ Day 1

### VŨ — Frontend (Chatbot AI UI)
- [ ] Build Chatbot AI UI component: floating button, chat window, message list, typing indicator
- [ ] Integrate với backend ChatbotController API
- [ ] Test và fix edge cases (empty response, error states, network timeout)
- [ ] Implement message history persistence (sessionStorage)

### BÌNH — Mobile (Payment Integration)
- [ ] Fix Flutter payment flow: VNPay/MoMo deep link handling
- [ ] Thêm `connectivity_plus` package → show offline banner khi mất mạng
- [ ] Fix Android back navigation trong payment WebView
- [ ] Viết Flutter widget tests cho AuthScreen và LoginForm

---

## 📅 DAY 4 — THỨ NĂM: INTEGRATION & TESTING

> **Milestone:** Code Freeze bắt đầu. Tất cả features đã code xong, chỉ fix bugs.

### DƯƠNG — CODE FREEZE REVIEW
- [ ] Review tất cả PRs còn lại của team
- [ ] Chạy `php artisan route:list` → verify tất cả endpoints có middleware đúng
- [ ] Chạy `php artisan migrate --pretend` trên staging → verify migrations sạch
- [ ] Performance test: dùng Postman collection để test các APIs quan trọng với concurrent requests

### THÀNH — Viết Feature Tests
- [ ] Viết Feature Test cho `OrderController::store` — test stock deduction, coupon apply, wallet discount
- [ ] Viết Feature Test cho `CartController` — test add/update/remove items
- [ ] Viết Feature Test cho MoMo IPN handler — test idempotency (gọi 2 lần không bị duplicate)

### TÀI — Frontend Polish
- [ ] Final polish Dashboard thống kê — animation, responsive, dark mode check
- [ ] Fix tất cả console warnings/errors
- [ ] Verify tất cả API calls dùng `effective_price` thay vì `price` raw

### VŨ — Frontend Integration Test
- [ ] End-to-end test luồng thanh toán: Add to cart → Checkout → VNPay/MoMo → Return
- [ ] Test Chatbot trên các browser khác nhau (Chrome, Firefox, Safari)
- [ ] Kiểm tra SEO meta tags đã đúng chưa — dùng Lighthouse audit

### BÌNH — Integration Testing
- [ ] **CODE FREEZE** — Announce freeze, không merge features mới
- [ ] Chạy `flutter test` — collect số lượng tests passed/failed
- [ ] Test toàn bộ app Flutter trên physical device (Android + iOS simulator)
- [ ] Chạy PHPUnit: `php artisan test --coverage` — collect coverage report

---

## 📅 DAY 5 — THỨ SÁU: BUG FIXES & STAGING VALIDATION

> **Milestone:** Tất cả CRITICAL và HIGH bugs đã fix. Staging deploy hoạt động ổn định.

### DƯƠNG + THÀNH — Backend Bug Fixes
- [ ] Fix tất cả bugs phát hiện từ integration testing Day 4
- [ ] Verify staging environment với real MoMo/VNPay sandbox
- [ ] Chạy lại PHPUnit — tất cả tests phải PASS
- [ ] Xem xét và xử lý các `MEDIUM` issues còn lại nếu thời gian cho phép

### TÀI + VŨ — Frontend Bug Fixes
- [ ] Fix tất cả bugs phát hiện từ end-to-end testing
- [ ] Build production bundle: `npm run build` — verify output
- [ ] Update Docker frontend command từ `npm run dev` → serve static build (nginx serve `dist/`)
- [ ] Lighthouse audit tất cả pages: target Performance ≥ 80, Accessibility ≥ 90

### BÌNH — QA Final Review
- [ ] Code review toàn bộ PRs lần cuối — approve/reject
- [ ] Test manual theo checklist:
  - [ ] Register → Login → Add to cart → Checkout COD
  - [ ] Checkout VNPay (sandbox)
  - [ ] Checkout MoMo (sandbox)
  - [ ] Apply coupon → order
  - [ ] Guest checkout
  - [ ] Chatbot AI conversation
  - [ ] Flutter: chấm công bằng face recognition
  - [ ] Flutter: xem đơn hàng, theo dõi shipping
- [ ] Viết staging deploy checklist

---

## 📅 DAY 6 — THỨ BẢY: STAGING FINAL + PRODUCTION PREP

> **Milestone:** Staging green. Production deployment plan sẵn sàng.

### DƯƠNG — Production Infrastructure
- [ ] Finalize `docker-compose.prod.yml` với tất cả security configs
- [ ] Verify tất cả environment variables production đã được set (`.env.prod`)
- [ ] Setup Redis password trong production
- [ ] Chuẩn bị rollback plan: backup DB snapshot, document rollback steps

### THÀNH — Final Backend Verification
- [ ] Chạy `php artisan config:cache`, `route:cache`, `view:cache` — verify không có errors
- [ ] Verify Queue worker đang chạy: `php artisan queue:work --daemon`
- [ ] Test Cron jobs: `php artisan schedule:run` — verify attendance cron, flash sale cron, etc.
- [ ] Verify Meilisearch indexes đã được build

### TÀI + VŨ — Production Build
- [ ] Build final production bundle
- [ ] Verify static assets CDN hoặc được serve đúng
- [ ] Final cross-browser test: Chrome, Firefox, Safari, Edge
- [ ] Mobile responsive test trên các kích thước màn hình

### BÌNH — Final QA Sign-off
- [ ] Tổng hợp **QA Sign-off Report**: danh sách tất cả bugs đã fix, còn lại
- [ ] Confirm tất cả CRITICAL issues đã resolve
- [ ] Approve deploy lên production

---

## 📅 DAY 7 — CHỦ NHẬT: GO-LIVE

> **Milestone:** Production deployment, monitoring active.

### Timeline Go-Live (Khuyến nghị deploy 8:00-10:00 sáng Chủ nhật — traffic thấp nhất)

| Thời gian | Hoạt động | Người thực hiện |
|-----------|-----------|----------------|
| 7:30 | Backup DB production (snapshot) | DƯƠNG |
| 8:00 | Enable maintenance mode: `php artisan down` | DƯƠNG |
| 8:05 | Pull latest code từ branch `release/week-fix` | DƯƠNG |
| 8:10 | Deploy backend Docker: `docker-compose -f docker-compose.prod.yml up -d` | DƯƠNG |
| 8:20 | Chạy `php artisan migrate --force` | DƯƠNG |
| 8:25 | Chạy `php artisan cache:clear` + warmup | THÀNH |
| 8:30 | Deploy frontend production build | VŨ |
| 8:35 | Smoke test APIs trên production (Postman) | THÀNH |
| 8:40 | Smoke test Frontend (manual checkout flow) | TÀI |
| 8:45 | Smoke test Flutter app (test flight / internal track) | BÌNH |
| 9:00 | Disable maintenance: `php artisan up` | DƯƠNG |
| 9:00-12:00 | **Monitoring**: Logs, Error rate, Response time | TẤT CẢ |

### Post Go-Live Monitoring (Tất cả theo dõi)
- [ ] Monitor Laravel logs: `docker logs ocean_backend -f`
- [ ] Monitor error rate (5xx responses)
- [ ] Monitor Queue: `php artisan queue:monitor`
- [ ] Monitor Meilisearch search performance
- [ ] Sẵn sàng rollback trong 30 phút nếu có critical issue

---

## 📋 CHECKLIST TỔNG HỢP THEO NGƯỜI

### ✅ TÀI — Frontend UI/UX
- [ ] Remove `ngrok-skip-browser-warning` header
- [ ] Audit và fix `v-html` với DOMPurify
- [ ] Build Dashboard thống kê mới (chart, table, KPIs)
- [ ] Fix CSS responsive issues
- [ ] Implement lazy loading components
- [ ] Production build verification
- [ ] Final Lighthouse audit

### ✅ VŨ — Frontend SEO/Payment/Chatbot
- [ ] Dynamic `<title>` và `<meta>` cho tất cả pages
- [ ] Fix `sessionSync.js` BroadcastChannel cleanup
- [ ] Build Chatbot AI UI component
- [ ] Fix payment return URL UI (VNPay/MoMo)
- [ ] End-to-end payment flow test
- [ ] Production bundle deploy

### ✅ DƯƠNG — Backend DB/Security/Infra
- [ ] Rename migration file (remove space)
- [ ] Fix `generateOrderCode()` → UUID-based
- [ ] Thêm 8+ composite indexes
- [ ] Fix `lockAndValidateStock()` price revalidation
- [ ] Fix `reserved_stock` logic
- [ ] Thêm rate limiting trên Orders, Cart, Contacts
- [ ] Fix nginx security headers
- [ ] Fix Docker compose production configs
- [ ] Create `docker-compose.prod.yml`
- [ ] Production deploy + maintenance mode management

### ✅ THÀNH — Backend Logic/Queue/AI
- [ ] Fix `XssSanitizer.php` với HTMLPurifier
- [ ] Refactor `MoMoController::momoReturn()` — remove payment state update
- [ ] Add Face Service API key authentication
- [ ] Add Face Service rate limiting
- [ ] Move heavy operations to Laravel Queue
- [ ] Verify Queue worker + Cron jobs on production
- [ ] Viết Feature Tests (OrderController, CartController, MoMo IPN)
- [ ] Fix AI Chatbot caching + timeout handling

### ✅ BÌNH — Mobile/QA/CI-CD
- [ ] Setup GitHub Actions CI workflow
- [ ] Read `MOBILE_AUDIT_REPORT.md` — fix priority issues
- [ ] Fix Flutter `dispose()` memory leaks (AuthProvider, CartProvider)
- [ ] Add `connectivity_plus` offline handling
- [ ] Fix Flutter payment deep link
- [ ] Fix Android back navigation in WebView
- [ ] Viết Flutter widget tests
- [ ] Code review tất cả PRs (Day 4-5)
- [ ] QA Sign-off Report
- [ ] Approve production deploy

---

## 📊 KPI SUCCESS METRICS — CUỐI TUẦN

| Metric | Target |
|--------|--------|
| CRITICAL bugs resolved | 8/8 (100%) |
| HIGH bugs resolved | ≥ 80% |
| PHPUnit test coverage | ≥ 40% (từ ~5%) |
| Lighthouse Performance | ≥ 80 |
| Lighthouse Accessibility | ≥ 90 |
| Zero 5xx errors trong 2h sau go-live | ✅ |
| CI/CD pipeline green | ✅ |
| Payment flow E2E pass | ✅ (VNPay + MoMo sandbox) |

---

> 📌 **Ghi chú từ Tech Lead:** Ưu tiên tuyệt đối trong tuần này là 8 CRITICAL issues. Nếu thiếu thời gian, DROP các MEDIUM issues xuống sprint sau. **Không deploy nếu còn CRITICAL bug chưa fix.** BÌNH là người duy nhất có quyền approve final release.
