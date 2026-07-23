# 📋 PHÂN CÔNG CÔNG VIỆC 1 TUẦN — QS_PROJECT
> **Sprint:** 2026-07-21 (Thứ 2) → 2026-07-27 (Chủ Nhật)  
> **Mục tiêu:** Fix toàn bộ CRITICAL & HIGH bugs, hoàn thiện tính năng, Go-Live cuối tuần

---

## 👥 THÀNH VIÊN & PHẠM VI TRÁCH NHIỆM

```
┌─────────────────────────────────────────────────────────────────────┐
│  TÀI  ──── Giao diện (Vue 3): Dashboard, UI/UX, Component fixes     │
│  VŨ   ──── Giao diện (Vue 3): Payment flow, Chatbot AI UI, SEO      │
│  DƯƠNG─── Backend Logic: Database, Race condition, Security, Infra  │
│  THÀNH─── Backend Logic: Payment fixes, AI Chatbot, Queue, Tests    │
│  BÌNH ──── Mobile Flutter + QA Lead: Review code & approve release  │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 📅 THỨ 2 (21/07) — KICKOFF & PHÂN TÍCH

> 🎯 **Milestone cuối ngày:** Môi trường dev sạch, tất cả hiểu rõ task, critical setup xong

---

### 🎨 TÀI — Frontend UI/UX

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| T1.1 | Audit tất cả components đang dùng `v-html` — ghi danh sách cần fix | `src/Pages/**/*.vue` | 🔴 CRITICAL |
| T1.2 | Kiểm tra responsive layout: danh sách màn hình bị vỡ layout trên mobile | `src/Pages/`, `src/layouts/` | 🟠 HIGH |
| T1.3 | Vẽ mockup/wireframe Dashboard thống kê mới (doanh thu, đơn hàng, top sản phẩm) | — | 🟠 HIGH |
| T1.4 | Audit CSS: thống kê các class bị trùng lặp, màu sắc không nhất quán | `src/assets/` | 🟡 MEDIUM |

**EOD Check:** Có mockup Dashboard + danh sách `v-html` cần fix

---

### 🎨 VŨ — Frontend SEO / Payment / Chatbot

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| V1.1 | Liệt kê tất cả pages trong router chưa có `meta.title` và `meta.description` | `src/router/index.js` | 🟠 HIGH |
| V1.2 | Xóa header `'ngrok-skip-browser-warning': '69420'` khỏi axios | `src/axios.js` (line 10) | 🟡 MEDIUM |
| V1.3 | Audit `sessionSync.js` — xác định BroadcastChannel có được cleanup không | `src/sessionSync.js` | 🟡 MEDIUM |
| V1.4 | Review UI luồng thanh toán VNPay/MoMo return — chụp màn hình bug hiện tại | `src/Pages/Payment/` | 🟠 HIGH |

**EOD Check:** Có danh sách pages thiếu SEO + bug list payment UI

---

### 🔧 DƯƠNG — Backend Database & Infrastructure

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| D1.1 | **[CRITICAL]** Rename migration file: xóa khoảng trắng, test `migrate:fresh` | `2026_06_05_000002_harden_payment_idempotency_state copy.php` | 🔴 CRITICAL |
| D1.2 | **[CRITICAL]** Fix `generateOrderCode()` → dùng `Str::uuid()` hoặc custom Snowflake | `app/Services/OrderService.php:758` | 🔴 CRITICAL |
| D1.3 | Viết migration mới thêm 5 indexes ưu tiên cao | `database/migrations/` | 🟠 HIGH |
| D1.4 | Kiểm tra `docker-compose.yml` — ghi nhận tất cả cấu hình cần sửa cho production | `docker-compose.yml` | 🟠 HIGH |

**EOD Check:** 2 CRITICAL bugs đã có PR, migration indexes đã viết xong

---

### 🔧 THÀNH — Backend Security & Payment

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| T1.1 | **[CRITICAL]** Fix `XssSanitizer.php` — cài `mews/purifier`, implement đúng | `app/Http/Middleware/XssSanitizer.php` | 🔴 CRITICAL |
| T1.2 | **[CRITICAL]** Refactor `MoMoController::momoReturn()` — xóa logic update payment state, chỉ trả về JSON cho UI | `app/Http/Controllers/MoMoController.php:31-87` | 🔴 CRITICAL |
| T1.3 | Audit Face Service — ghi lại plan thêm API key authentication | `face-service/app.py` | 🔴 CRITICAL |
| T1.4 | Audit GeminiService chatbot — ghi lại nơi cần thêm Redis cache | `app/Services/GeminiService.php` | 🟠 HIGH |

**EOD Check:** XssSanitizer + MoMo fix đã có PR sẵn sàng review

---

### 📱 BÌNH — Mobile Setup & QA Framework

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| B1.1 | Đọc toàn bộ `MOBILE_AUDIT_REPORT.md` — tạo priority list bugs cần fix | `mobile/MOBILE_AUDIT_REPORT.md` | 🟠 HIGH |
| B1.2 | Setup GitHub Actions CI: tạo `.github/workflows/ci.yml` với lint + PHPUnit | Repo root | 🟠 HIGH |
| B1.3 | Chạy `flutter analyze` — capture toàn bộ warnings hiện tại | `mobile/` | 🟡 MEDIUM |
| B1.4 | Tạo branch `release/sprint-fix` từ `main` cho cả team | Git | 🟢 INFO |

**EOD Check:** CI workflow đã push + branch release tạo xong

---

## 📅 THỨ 3 (22/07) — IMPLEMENTATION PHASE 1

> 🎯 **Milestone cuối ngày:** Tất cả CRITICAL backend bugs đang trong PR hoặc đã merge

---

### 🎨 TÀI — Fix v-html & Bắt đầu Dashboard

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| T2.1 | Cài `dompurify`: `npm install dompurify` — wrap tất cả `v-html` | `package.json`, các `.vue` files | 🔴 CRITICAL |
| T2.2 | Tạo composable `useSafeHtml.js` — dùng DOMPurify để sanitize trước khi render | `src/composables/useSafeHtml.js` | 🔴 CRITICAL |
| T2.3 | Bắt đầu code Dashboard: Layout 2 cột, Card KPI (Doanh thu, Đơn hàng, Khách mới) | `src/Pages/Admin/Dashboard/` | 🟠 HIGH |
| T2.4 | Fix responsive: header navigation bị vỡ trên màn hình < 768px | `src/layouts/AdminLayout.vue` | 🟠 HIGH |

---

### 🎨 VŨ — SEO & Payment UI Fix

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| V2.1 | Thêm `meta.title` và `meta.description` cho toàn bộ routes — dùng `useHead()` hoặc `document.title` | `src/router/index.js` | 🟠 HIGH |
| V2.2 | Fix `sessionSync.js` — thêm cleanup listener trong `window.addEventListener` với AbortController | `src/sessionSync.js` | 🟡 MEDIUM |
| V2.3 | Fix UI Payment Return page: VNPay/MoMo callback — hiện thị đúng trạng thái success/fail | `src/Pages/Payment/PaymentReturn.vue` | 🟠 HIGH |
| V2.4 | Bắt đầu Chatbot UI: floating button + chat window skeleton | `src/components/Chatbot/` | 🟠 HIGH |

---

### 🔧 DƯƠNG — Backend Indexes & Stock Logic

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| D2.1 | Thêm 4 indexes còn lại: `loyalty_transactions`, `wallet_transactions`, `attendances`, `product_variants` | `database/migrations/` | 🟠 HIGH |
| D2.2 | Fix `lockAndValidateStock()` — thêm price revalidation trong DB transaction | `app/Services/OrderService.php:731-745` | 🟠 HIGH |
| D2.3 | Fix `reserved_stock`: check `stock - reserved_stock >= quantity` | `app/Services/OrderService.php` | 🟡 MEDIUM |
| D2.4 | Thêm Rate Limiting: `POST /api/orders` (10/min), `POST /api/cart/items` (30/min), `POST /api/contacts` (5/min) | `routes/api.php` | 🟠 HIGH |

---

### 🔧 THÀNH — Face Service + AI Cache

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| TH2.1 | Thêm API Key auth cho Face Service — shared secret header `X-Internal-Secret` | `face-service/app.py` | 🔴 CRITICAL |
| TH2.2 | Thêm `slowapi` rate limiting cho Face Service `/encode` và `/verify` | `face-service/app.py`, `face-service/requirements.txt` | 🟠 HIGH |
| TH2.3 | Thêm Redis cache cho GeminiService FAQ responses (TTL 10 phút) | `app/Services/GeminiService.php` | 🟠 HIGH |
| TH2.4 | Chuyển email notifications sang Queue: verify `OrderCreatedAdmin` event dùng queue | `app/Events/OrderCreatedAdmin.php` | 🟠 HIGH |

---

### 📱 BÌNH — Flutter Memory Leaks + CI

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| B2.1 | Fix `AuthProvider` — thêm `@override dispose()` cleanup | `mobile/lib/providers/auth_provider.dart` | 🟠 HIGH |
| B2.2 | Fix `CartProvider` — thêm `@override dispose()` cleanup | `mobile/lib/providers/cart_provider.dart` | 🟠 HIGH |
| B2.3 | Thêm `connectivity_plus` package → offline banner widget | `mobile/pubspec.yaml`, `mobile/lib/widgets/` | 🟠 HIGH |
| B2.4 | **Review & Comment** PRs của Dương và Thành từ Thứ 2-3 | GitHub PR | 🔴 CRITICAL |

---

## 📅 THỨ 4 (23/07) — IMPLEMENTATION PHASE 2

> 🎯 **Milestone cuối ngày:** Frontend features 70% xong, Backend đang viết tests

---

### 🎨 TÀI — Dashboard Hoàn thiện

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| T3.1 | Dashboard: tích hợp API doanh thu theo ngày/tuần/tháng — dùng Chart.js hoặc ApexCharts | `src/Pages/Admin/Dashboard/` | 🟠 HIGH |
| T3.2 | Dashboard: bảng Top 5 sản phẩm bán chạy + Top 5 khách hàng | `src/Pages/Admin/Dashboard/` | 🟠 HIGH |
| T3.3 | Dashboard: biểu đồ phân bổ trạng thái đơn hàng (Donut chart) | `src/Pages/Admin/Dashboard/` | 🟠 HIGH |
| T3.4 | Fix CSS: token màu sắc nhất quán — tạo CSS variables cho color palette | `src/assets/main.css` | 🟡 MEDIUM |

---

### 🎨 VŨ — Chatbot AI UI Hoàn thiện

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| V3.1 | Chatbot: hoàn thiện chat window — message list, user/bot avatar, timestamp | `src/components/Chatbot/ChatWindow.vue` | 🟠 HIGH |
| V3.2 | Chatbot: typing indicator animation khi đang chờ response | `src/components/Chatbot/TypingIndicator.vue` | 🟡 MEDIUM |
| V3.3 | Chatbot: integrate API `/api/chatbot/message` + error handling | `src/components/Chatbot/ChatWindow.vue` | 🟠 HIGH |
| V3.4 | Chatbot: lưu lịch sử chat vào `sessionStorage` (tránh mất khi refresh) | `src/components/Chatbot/` | 🟡 MEDIUM |

---

### 🔧 DƯƠNG — Docker & Nginx Security

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| D3.1 | Fix `nginx/backend.conf` — thêm 5 security headers (X-Frame-Options, HSTS, CSP, nosniff, Referrer) | `nginx/backend.conf` | 🟠 HIGH |
| D3.2 | Fix `docker-compose.yml`: Redis thêm password + healthcheck, Meilisearch `MEILI_ENV: production` | `docker-compose.yml` | 🟠 HIGH |
| D3.3 | Xóa port mapping public của Meilisearch (7700) — chuyển sang internal only | `docker-compose.yml` | 🟡 MEDIUM |
| D3.4 | Tạo `docker-compose.prod.yml` với overrides production (no volume mounts, prod configs) | `docker-compose.prod.yml` (NEW) | 🟠 HIGH |

---

### 🔧 THÀNH — Feature Tests Backend

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| TH3.1 | Viết Feature Test: `OrderController::store` — test stock deduction + coupon apply | `tests/Feature/OrderWorkflowTest.php` (NEW) | 🟠 HIGH |
| TH3.2 | Viết Feature Test: `CartController` — test add/update/remove items | `tests/Feature/CartWorkflowTest.php` (NEW) | 🟠 HIGH |
| TH3.3 | Viết Feature Test: MoMo IPN idempotency — gọi 2 lần không bị duplicate | `tests/Feature/PaymentProcessingServiceTest.php` | 🟠 HIGH |
| TH3.4 | Viết Unit Test: `ProductVariant` accessors (`effective_price`, `is_on_sale`, `discount_percent`) | `tests/Unit/ProductVariantTest.php` (NEW) | 🟡 MEDIUM |

---

### 📱 BÌNH — Flutter Payment + Tests

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| B3.1 | Fix Flutter payment flow: VNPay/MoMo deep link handling (Android intent-filter) | `mobile/android/app/src/main/AndroidManifest.xml` | 🟠 HIGH |
| B3.2 | Fix Android back navigation trong payment WebView | `mobile/lib/screens/payment/` | 🟠 HIGH |
| B3.3 | Viết Flutter widget tests: `LoginScreen`, `LoginForm` validation | `mobile/test/widget/` | 🟡 MEDIUM |
| B3.4 | **Review PRs** của Tài và Vũ (XSS fix, Dashboard, Chatbot UI, Payment UI) | GitHub PR | 🔴 CRITICAL |

---

## 📅 THỨ 5 (24/07) — INTEGRATION TEST & CODE FREEZE

> 🎯 **Milestone cuối ngày:** CODE FREEZE. Tất cả features xong. Chỉ còn fix bugs từ test.

---

### 🎨 TÀI — Polish & Integration

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| T4.1 | Verify Dashboard: test với real API — kiểm tra loading state, empty state, error state | `src/Pages/Admin/Dashboard/` | 🟠 HIGH |
| T4.2 | Lazy loading: dynamic import cho các heavy pages (Dashboard, Chatbot, FlashSale) | `src/router/index.js` | 🟡 MEDIUM |
| T4.3 | Fix tất cả `console.error` / `console.warn` còn sót trong production code | `src/Pages/**` | 🟡 MEDIUM |
| T4.4 | Verify `effective_price` được dùng đúng ở tất cả Cart và Checkout components | `src/Pages/Cart/`, `src/Pages/Checkout/` | 🟠 HIGH |

---

### 🎨 VŨ — End-to-End Flow Test

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| V4.1 | E2E test luồng: Thêm giỏ → Checkout → VNPay (sandbox) → Return page | `src/Pages/Checkout/` | 🟠 HIGH |
| V4.2 | E2E test luồng: Guest checkout → MoMo payment | `src/Pages/Checkout/` | 🟠 HIGH |
| V4.3 | Test Chatbot: conversation flow, empty response, network error | `src/components/Chatbot/` | 🟡 MEDIUM |
| V4.4 | Lighthouse audit 5 trang quan trọng (Home, Product, Cart, Checkout, Dashboard) — ghi kết quả | Browser | 🟡 MEDIUM |

---

### 🔧 DƯƠNG — Performance Test & Final Review

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| D4.1 | Chạy `php artisan route:list` → verify tất cả routes có middleware đúng (auth, throttle) | CLI | 🟠 HIGH |
| D4.2 | Concurrent test với Postman: 10 requests đồng thời tới `POST /api/orders` — verify không collision | Postman | 🟠 HIGH |
| D4.3 | Chạy `php artisan migrate --pretend` trên staging — verify migrations sạch, không conflict | CLI | 🟠 HIGH |
| D4.4 | Review và **merge** tất cả PRs backend đã được Bình approve | GitHub | 🔴 CRITICAL |

---

### 🔧 THÀNH — Verify Tests & Queue

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| TH4.1 | Chạy `php artisan test` — tất cả tests phải PASS, ghi kết quả coverage | CLI | 🟠 HIGH |
| TH4.2 | Verify Queue Worker: test gửi email thực tế qua queue, kiểm tra job không bị stuck | `app/Jobs/` | 🟠 HIGH |
| TH4.3 | Verify Face Service authentication: test call không có secret → 401, có secret → 200 | Postman + `face-service/` | 🔴 CRITICAL |
| TH4.4 | Hoàn thiện chatbot caching — stress test: gọi 50 lần cùng câu hỏi → verify chỉ gọi Gemini API 1 lần | Redis + GeminiService | 🟡 MEDIUM |

---

### 📱 BÌNH — CODE FREEZE & QA Deep Dive

| # | Công việc | File liên quan | Độ ưu tiên |
|---|-----------|---------------|------------|
| B4.1 | **ANNOUNCE CODE FREEZE** — Không merge feature mới sau 17:00 hôm nay | Slack/Team | 🔴 CRITICAL |
| B4.2 | Chạy `php artisan test --coverage` — capture full coverage report | CLI | 🔴 CRITICAL |
| B4.3 | Chạy `flutter test` — ghi số tests passed/failed/error | `mobile/test/` | 🟠 HIGH |
| B4.4 | Test end-to-end trên physical Android device: Login → Browse → Cart → Checkout → Payment | Physical device | 🟠 HIGH |

---

## 📅 THỨ 6 (25/07) — BUG FIX & STAGING VALIDATION

> 🎯 **Milestone cuối ngày:** Staging 100% xanh. Không có CRITICAL bug còn tồn tại.

---

### 🎨 TÀI + VŨ — Frontend Bug Fixing

| # | Người | Công việc | Độ ưu tiên |
|---|-------|-----------|------------|
| F5.1 | **TÀI** | Fix tất cả bugs phát hiện từ testing Thứ 5 (Dashboard, Layout) | 🟠 HIGH |
| F5.2 | **VŨ** | Fix tất cả bugs từ payment flow + chatbot testing | 🟠 HIGH |
| F5.3 | **TÀI** | Build production bundle: `npm run build` — verify output size & no errors | 🔴 CRITICAL |
| F5.4 | **VŨ** | Update Docker frontend: đổi từ `npm run dev` → serve static `dist/` qua nginx | `frontend/Dockerfile`, `docker-compose.yml` | 🔴 CRITICAL |
| F5.5 | **CẢ 2** | Cross-browser test: Chrome, Firefox, Safari, Edge (mobile viewport) | Browser | 🟠 HIGH |

---

### 🔧 DƯƠNG + THÀNH — Backend Final Fixes

| # | Người | Công việc | Độ ưu tiên |
|---|-------|-----------|------------|
| B5.1 | **DƯƠNG** | Fix tất cả bugs từ concurrent test Thứ 5 | 🟠 HIGH |
| B5.2 | **THÀNH** | Fix tất cả test failures từ PHPUnit run | 🟠 HIGH |
| B5.3 | **DƯƠNG** | Verify staging deploy: `docker-compose -f docker-compose.prod.yml up -d` | 🟠 HIGH |
| B5.4 | **THÀNH** | Test real MoMo/VNPay sandbox trên staging environment | 🔴 CRITICAL |
| B5.5 | **DƯƠNG** | Chuẩn bị production rollback plan + DB backup procedure | Docs | 🟠 HIGH |

---

### 📱 BÌNH — Final QA Manual Testing

**Manual Test Checklist (phải pass 100%):**

| Test Case | Người test | Status |
|-----------|-----------|--------|
| Đăng ký tài khoản mới | BÌNH | ⬜ |
| Đăng nhập / Đăng xuất | BÌNH | ⬜ |
| Đăng nhập Google OAuth | BÌNH | ⬜ |
| Thêm sản phẩm vào giỏ hàng | BÌNH | ⬜ |
| Cập nhật số lượng / xóa khỏi giỏ | BÌNH | ⬜ |
| Áp dụng coupon discount | BÌNH | ⬜ |
| Checkout COD (user đã login) | BÌNH | ⬜ |
| Checkout Guest (không login) | BÌNH | ⬜ |
| Thanh toán VNPay sandbox | BÌNH | ⬜ |
| Thanh toán MoMo sandbox | BÌNH | ⬜ |
| Lịch sử đơn hàng | BÌNH | ⬜ |
| Chatbot AI trả lời | BÌNH | ⬜ |
| Dashboard thống kê Admin | BÌNH | ⬜ |
| Flutter: Login / xem sản phẩm | BÌNH | ⬜ |
| Flutter: Chấm công Face ID | BÌNH | ⬜ |
| Flutter: Nhận push notification | BÌNH | ⬜ |

---

## 📅 THỨ 7 (26/07) — STAGING FINAL + PRODUCTION PREPARATION

> 🎯 **Milestone cuối ngày:** Tất cả stakeholders approved. Production deploy plan sẵn sàng.

---

### 🎨 TÀI + VŨ — Frontend Production Ready

| # | Người | Công việc |
|---|-------|-----------|
| F6.1 | **TÀI** | Lighthouse audit lần cuối — Performance ≥ 80, Accessibility ≥ 90 |
| F6.2 | **VŨ** | Verify tất cả SEO meta tags trên production build |
| F6.3 | **CẢ 2** | Kiểm tra bundle size — nếu > 2MB, thêm code splitting |
| F6.4 | **CẢ 2** | Ghi lại release notes cho từng tính năng đã làm trong tuần |

---

### 🔧 DƯƠNG + THÀNH — Production Infrastructure

| # | Người | Công việc |
|---|-------|-----------|
| D6.1 | **DƯƠNG** | Finalize `docker-compose.prod.yml` với tất cả security configs |
| D6.2 | **DƯƠNG** | Verify tất cả production environment variables (`.env.prod`) đã set đúng |
| D6.3 | **THÀNH** | Verify Queue worker config + Cron jobs: `php artisan schedule:run` |
| D6.4 | **THÀNH** | Verify Meilisearch search index đã được build và search hoạt động |
| D6.5 | **DƯƠNG** | Chạy DB backup script — lưu snapshot production trước khi deploy |

---

### 📱 BÌNH — QA Sign-off & Release Approval

| # | Công việc |
|---|-----------|
| B6.1 | Tổng hợp **QA Sign-off Report**: danh sách bugs đã fix, còn lại, và risk assessment |
| B6.2 | Xác nhận tất cả 8 CRITICAL issues đã resolve — tick checklist |
| B6.3 | **Final Code Review** toàn bộ PRs lần cuối — APPROVE hoặc REQUEST CHANGES |
| B6.4 | **APPROVE PRODUCTION DEPLOY** — ký tắt release |

---

## 📅 CHỦ NHẬT (27/07) — GO-LIVE DAY 🚀

> 🎯 **Target deploy window:** 8:00 → 10:00 sáng (traffic thấp nhất)

### ⏱️ Deploy Timeline Chi Tiết

```
07:30 ──── DƯƠNG: Backup DB production (snapshot toàn bộ)
08:00 ──── DƯƠNG: php artisan down  →  Enable maintenance mode
08:05 ──── DƯƠNG: git pull origin release/sprint-fix  →  Pull code mới
08:10 ──── DƯƠNG: docker-compose -f docker-compose.prod.yml up -d --build
08:20 ──── DƯƠNG: php artisan migrate --force
08:25 ──── THÀNH: php artisan cache:clear + config:cache + route:cache
08:30 ──── VŨ:    Deploy frontend production build (static files)
08:35 ──── THÀNH: Smoke test APIs (Postman collection)
08:40 ──── TÀI:   Smoke test Frontend (manual checkout flow)
08:45 ──── BÌNH:  Smoke test Flutter (TestFlight / Internal track)
09:00 ──── DƯƠNG: php artisan up  →  Disable maintenance mode  ✅
09:00-12:00 ── ALL: Monitoring (logs, errors, response time)
```

### 🚨 Rollback Plan (nếu có critical error sau 09:00)
```
1. DƯƠNG: php artisan down  (maintenance mode)
2. DƯƠNG: git revert về commit cũ + docker rollback
3. DƯƠNG: Restore DB từ snapshot 07:30
4. BÌNH:  Thông báo team + stakeholders
5. ALL:   Post-mortem sau 2 giờ
```

---

## 📊 TỔNG HỢP CHECKLIST CUỐI TUẦN

### ✅ TÀI — Checklist

```
□ Cài DOMPurify + tạo useSafeHtml composable
□ Fix tất cả v-html không có sanitize
□ Dashboard: Revenue chart (ngày/tuần/tháng)
□ Dashboard: Top products & customers table
□ Dashboard: Order status donut chart
□ Fix CSS color variables nhất quán
□ Fix responsive layout mobile
□ Lazy loading heavy pages
□ Production build: npm run build ✓
□ Lighthouse Performance ≥ 80
```

### ✅ VŨ — Checklist

```
□ Xóa ngrok header khỏi axios.js
□ Dynamic <title> + <meta> tất cả pages
□ Fix sessionSync.js BroadcastChannel cleanup
□ Fix Payment Return UI (VNPay + MoMo)
□ Chatbot UI: floating button + chat window
□ Chatbot: typing indicator + error states
□ Chatbot: API integration + history storage
□ E2E test payment flows (VNPay + MoMo)
□ Docker: frontend serve production build (nginx)
□ Lighthouse Accessibility ≥ 90
```

### ✅ DƯƠNG — Checklist

```
□ [CRITICAL] Rename migration (remove space)
□ [CRITICAL] Fix generateOrderCode() → UUID
□ Thêm 8+ composite indexes (migration mới)
□ Fix lockAndValidateStock() price revalidation
□ Fix reserved_stock logic
□ Thêm rate limiting: Orders, Cart, Contacts
□ Fix nginx: 5 security headers
□ Fix docker-compose: Redis password + healthcheck
□ Meilisearch: production env + internal-only port
□ Tạo docker-compose.prod.yml
□ DB backup + rollback plan documentation
□ Staging deploy verification
```

### ✅ THÀNH — Checklist

```
□ [CRITICAL] Fix XssSanitizer với HTMLPurifier
□ [CRITICAL] Refactor MoMoController::momoReturn()
□ [CRITICAL] Face Service: API key authentication
□ Face Service: rate limiting (slowapi)
□ GeminiService: Redis cache cho FAQ responses
□ Email + heavy ops → Laravel Queue
□ Verify Queue worker + Cron jobs production
□ Feature Test: OrderController::store
□ Feature Test: CartController
□ Feature Test: MoMo IPN idempotency
□ Unit Test: ProductVariant accessors
□ PHPUnit 100% PASS trước Go-Live
```

### ✅ BÌNH — Checklist

```
□ Setup GitHub Actions CI (.github/workflows/ci.yml)
□ Tạo branch release/sprint-fix
□ Fix AuthProvider dispose()
□ Fix CartProvider dispose()
□ Thêm connectivity_plus offline handling
□ Fix Flutter payment deep link (Android)
□ Fix back navigation trong payment WebView
□ Widget tests: LoginScreen + Form validation
□ Review & Approve PRs Dương + Thành (Thứ 2, 3, 4)
□ Review & Approve PRs Tài + Vũ (Thứ 3, 4, 5)
□ Manual QA: tất cả 16 test cases pass
□ QA Sign-off Report
□ APPROVE PRODUCTION DEPLOY
```

---

## 📈 KPI SUCCESS METRICS — CUỐI SPRINT

| Metric | Target | Đo lường bởi |
|--------|--------|-------------|
| CRITICAL bugs resolved | **8/8 (100%)** | BÌNH |
| HIGH bugs resolved | **≥ 80%** | BÌNH |
| PHPUnit coverage | **≥ 40%** (từ ~5%) | THÀNH |
| Lighthouse Performance | **≥ 80** | VŨ |
| Lighthouse Accessibility | **≥ 90** | TÀI |
| 0 lỗi 5xx trong 2h post-deploy | **✅** | DƯƠNG |
| CI/CD pipeline green | **✅** | BÌNH |
| Payment flow E2E pass | **✅** (VNPay + MoMo sandbox) | VŨ |
| Flutter analyze 0 errors | **✅** | BÌNH |
| Deploy window ≤ 60 phút | **✅** | DƯƠNG |

---

> ⚠️ **RULE CỦA SPRINT:**
> - Không merge feature mới sau **17:00 Thứ 5** (Code Freeze)
> - Mọi PR phải được **BÌNH approve** trước khi merge vào `release/sprint-fix`
> - Nếu phát hiện CRITICAL bug mới → report ngay cho **BÌNH** để re-prioritize
> - Go-Live chỉ được thực hiện khi BÌNH ký **QA Sign-off Report**
