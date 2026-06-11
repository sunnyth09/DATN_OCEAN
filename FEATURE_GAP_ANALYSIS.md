# FEATURE GAP ANALYSIS

Ngày phân tích: 2026-06-05

## 1. Mục tiêu file này

File này trả lời 4 câu hỏi:

1. Hệ thống hiện đã có gì.
2. Hệ thống còn thiếu gì so với e-commerce chuẩn.
3. Tính năng nào nên bổ sung sớm.
4. Tính năng nào là nhóm nâng cao/enterprise.

## 2. Những gì dự án đã có

### 2.1 Core commerce

- Đăng ký, đăng nhập, refresh token, quên mật khẩu OTP
- Google login, Facebook login
- Hồ sơ tài khoản, avatar, đổi mật khẩu, sổ địa chỉ
- Danh mục, thương hiệu, sản phẩm, biến thể, SKU, barcode
- Ảnh sản phẩm và gallery
- Cart, checkout, coupon, order history, cancel order
- COD, VNPay, MoMo, bank transfer/SePay
- Wishlist
- Review và review moderation
- Blog/post category
- Inbox notification

### 2.2 Business nâng cao hơn mức MVP

- Flash sale
- Affiliate click/conversion/withdrawal
- POS và in PDF hóa đơn
- Return request workflow
- Live chat + chatbot
- Attendance/work shift
- Court booking với realtime lock slot

## 3. Gap theo module

| Module | Đã có | Còn thiếu | Mức độ |
|---|---|---|---|
| Authentication | JWT login, social login, OTP reset | Verify email, OTP login, 2FA, device/session management | Critical |
| User management | Profile, avatar, address | Login history UI hoàn chỉnh, session/device revoke | High |
| Product | Category, brand, variant, SKU, barcode, stock | Thuộc tính chuẩn hóa mạnh hơn, combo/bundle, multi-warehouse | High |
| Search | Search + Meilisearch | Search suggestion, typo tolerance tuning, merchandising rules | High |
| Cart | Add/update/remove/buy again/upsell | Guest cart, cross-device merge, saved cart | Medium |
| Checkout | Address, shipping fee, coupon, payment method | Guest checkout, split shipment, invoice/VAT flow | High |
| Order | Status history, cancel, admin update | Return/exchange analytics, SLA dashboard, fraud signals | Medium |
| Payment | COD, VNPay, MoMo, SePay | ZaloPay, Stripe, PayPal, retry/reconciliation dashboard | High |
| Marketing | Coupon, flash sale, affiliate | Combo, referral campaign engine, campaign segmentation | High |
| Loyalty | Reward points cơ bản | Membership tier, cashback, earn/burn rules, expiry policy | High |
| Review | Rating + text review | Ảnh/video review, Q&A product, verified buyer badges rõ hơn | Medium |
| Wishlist | Đã có | Share wishlist, wishlist alert, back-in-stock alert | Low |
| Blog/SEO | Blog basic + SEO fields | Sitemap, meta automation, canonical, JSON-LD, SSR/SSG | Critical |
| Notification | Inbox notification + realtime | Email template center, SMS, push/mobile push | High |
| Admin | Dashboard, users, products, orders | Fine-grained permission matrix, audit log, approval workflow | High |
| API | Khá nhiều endpoint | API docs, versioning strategy, contract testing | High |

## 4. Đối chiếu với e-commerce chuẩn hiện đại

### 4.1 Authentication

Đã có:

- register
- login
- logout
- forgot password
- social login Google/Facebook

Thiếu:

- verify email
- OTP login
- 2FA
- session/device management
- brute-force visibility dashboard

### 4.2 User management

Đã có:

- profile
- address list
- avatar

Thiếu:

- login history screen hoàn chỉnh
- quản lý thiết bị đăng nhập
- consent/privacy preference

### 4.3 Product

Đã có:

- category
- brand
- variant
- SKU
- barcode
- inventory tracking
- image management

Thiếu:

- attribute matrix chuẩn hóa sâu
- bundle/combo thật
- supplier management
- multi-warehouse
- low-stock alert workflow

### 4.4 Search

Đã có:

- search
- filter
- sort
- Meilisearch integration

Thiếu:

- autocomplete/suggestion rõ ràng
- synonym handling
- merchandising rule
- zero-result recovery

### 4.5 Cart / Checkout

Đã có:

- add to cart
- update quantity
- remove item
- coupon
- order placement
- shipping address

Thiếu:

- guest checkout
- abandoned checkout funnel analytics
- saved payment/profile checkout accelerator
- split shipment / backorder flow

### 4.6 Payment

Đã có:

- COD
- VNPay
- MoMo
- bank transfer via SePay

Thiếu:

- ZaloPay
- Stripe
- PayPal
- reconciliation dashboard
- payment retry workflow

### 4.7 Marketing / Loyalty

Đã có:

- voucher/coupon
- flash sale
- affiliate
- reward points cơ bản

Thiếu:

- combo
- referral campaign engine riêng
- cashback
- membership tier
- automation theo segment

### 4.8 Review / Community

Đã có:

- review text
- rating

Thiếu:

- ảnh review
- video review
- product Q&A
- reviewer reputation

### 4.9 Notification

Đã có:

- inbox notification
- realtime events
- email một số flow

Thiếu:

- SMS
- push notification
- centralized template management
- notification preference center

## 5. So sánh với Shopee, Tiki, Lazada, Amazon

## 5.1 Chức năng đã có

- Catalog có variant và pricing
- Cart và checkout đa phương thức thanh toán nội địa
- Coupon/flash sale/affiliate
- Wishlist, review, order history
- Admin quản lý product/order/user

## 5.2 Chức năng còn thiếu rõ rệt

- SEO platform-level
- marketplace-grade seller tooling
- recommendation/personalization engine
- marketing automation đa kênh
- return/exchange logistics sâu
- omnichannel inventory
- fraud/risk controls
- customer support ticketing chuẩn

## 5.3 Chức năng nên có sớm

- verify email
- ZaloPay hoặc Stripe tùy thị trường mục tiêu
- sitemap/canonical/JSON-LD/meta manager
- queue worker và observability cho jobs
- API documentation
- permission matrix thay vì chỉ `role` middleware

## 5.4 Chức năng nâng cao

- recommendation engine
- segmentation/CDP-lite
- loyalty tiers
- bundle/combo promotions
- A/B testing search & campaign
- warehouse/fulfillment workflow
- BI dashboard và anomaly detection

## 6. Kết luận gap analysis

Hiện trạng phù hợp với:

- Dự án commerce nội địa quy mô vừa
- Hệ thống bán hàng có thêm nhu cầu vận hành nội bộ
- MVP+ hoặc pre-production mở rộng

Chưa phù hợp với:

- SEO-first e-commerce
- marketplace nhiều seller chuẩn hóa
- enterprise commerce cần audit/compliance/automation chặt

Ưu tiên cao nhất nên tập trung vào 3 nhóm gap:

1. Security + payment correctness.
2. Runtime + queue + observability.
3. SEO + API documentation + missing payment/loyalty features.
