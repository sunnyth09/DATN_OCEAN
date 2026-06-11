# SECURITY REPORT

Ngày phân tích: 2026-06-05  
Điểm bảo mật tổng quan: 58/100

## 1. Tóm tắt

Codebase đã có một số lớp phòng thủ tốt:

- Rate limiting cho login/register/forgot password/chat/payment callbacks
- OTP reset password có hash OTP trong DB
- Role middleware
- Avatar upload có reprocess ảnh bằng GD để loại metadata
- `image-proxy` có chặn path traversal

Tuy nhiên hệ thống vẫn còn một số lỗi quy trình và implementation đủ nghiêm trọng để chưa nên coi là production-ready.

## 2. Findings

| ID | Mức độ | Vấn đề | Bằng chứng | Tác động | Khuyến nghị |
|---|---|---|---|---|---|
| SEC-01 | Critical | Log thông tin đăng nhập thô | `backend/app/Http/Controllers/AuthController.php` ghi `Log::info("Login attempt", $credentials)` | Password có thể rơi vào log collector, backup, hoặc máy vận hành | Xóa logging credential ngay; chỉ log email đã mask và request id |
| SEC-02 | High | CAPTCHA đang bị vô hiệu hóa ở controller login/register | `AuthController` comment-out verify Turnstile dù `AuthService` vẫn có logic verify | Tăng mạnh khả năng brute-force, credential stuffing, spam register | Bật lại CAPTCHA đúng môi trường hoặc xóa dead-code để tránh hiểu sai |
| SEC-03 | High | JWT lưu ở `sessionStorage` phía client | `frontend/src/stores/auth.js`, `frontend/src/axios.js` | Nếu có XSS, token bị lấy trực tiếp | Chuyển sang HTTP-only secure cookie hoặc tối thiểu tăng CSP và harden XSS |
| SEC-04 | High | Side effect thanh toán có thể chạy lặp | `backend/app/Services/PaymentProcessingService.php` gọi `dispatchPostPaymentActions()` ở cả return path và IPN path | Email, notification, cleanup cart, event có thể bị bắn lặp khi callback xảy ra nhiều lần | Tạo cờ idempotency rõ ràng theo từng side effect hoặc payment event log |
| SEC-05 | Medium | Middleware `XssSanitizer` dùng `strip_tags()` toàn cục cho API | `backend/app/Http/Middleware/XssSanitizer.php` | Không đủ để chống XSS hiện đại, đồng thời có thể làm hỏng dữ liệu hợp lệ | Dùng output escaping, CSP, sanitize theo field cụ thể; không strip toàn cục |
| SEC-06 | Medium | Route debug vẫn tồn tại trong codebase | `backend/routes/api.php` có nhóm `/debug/*` | Dù có bảo vệ role admin, vẫn tăng attack surface và rủi ro vận hành | Loại khỏi production build hoặc khóa bằng `app()->environment()` |
| SEC-07 | Medium | Thiếu verify email và 2FA | Không thấy `MustVerifyEmail`, route verify email, hoặc 2FA flow | Account integrity thấp, khó chống account takeover | Bổ sung verify email trước mua hàng/affiliate và 2FA cho admin |
| SEC-08 | Medium | Chưa có policy layer chuẩn Laravel | Không thấy `Policies` trong `backend/app` | Authorization phụ thuộc nhiều vào route middleware và controller logic | Thêm policy cho order, return request, coupon, product admin actions |
| SEC-09 | Medium | Cho phép upload `svg` ở product images | `backend/app/Http/Controllers/ProductController.php` validate `svg` trong thumbnail/gallery/variant images | SVG public nếu không sanitize có thể thành vector XSS | Loại `svg` khỏi upload public hoặc sanitize SVG server-side |
| SEC-10 | Medium | Custom password reset dùng SQL/raw flow riêng, thiếu FormRequest | `backend/app/Http/Controllers/ForgotPasswordController.php` | Dễ drift logic, khó mở rộng policy/audit | Chuẩn hóa validation bằng FormRequest và event log reset flow |
| SEC-11 | Low | Có duplicate route `POST /payment/momo-ipn` | `backend/routes/api.php` khai báo 2 lần | Khó dự đoán middleware thật sự được áp dụng, tăng rủi ro sai cấu hình | Giữ lại một route duy nhất |

## 3. Đánh giá theo hạng mục yêu cầu

### SQL Injection

Mức độ: 72/100

Nhận xét:

- Phần lớn code dùng Eloquent/Query Builder
- Có một số raw SQL ở forgot password controller nhưng vẫn binding tham số
- Rủi ro chính không nằm ở SQLi mà nằm ở flow logic và auth/session

### XSS

Mức độ: 45/100

Nhận xét:

- Có nỗ lực strip input bằng middleware
- Cách làm hiện tại không đủ mạnh, lại có thể tạo false sense of security
- JWT lưu trong browser storage làm hậu quả XSS nặng hơn
- Upload SVG public là điểm cần đặc biệt lưu ý

### CSRF

Mức độ: 70/100

Nhận xét:

- API đang đi theo Bearer JWT nên ít phụ thuộc cookie session
- Nếu chuyển sang HTTP-only cookie trong tương lai phải thiết kế CSRF kỹ

### Mass Assignment

Mức độ: 68/100

Nhận xét:

- Nhiều model dùng fillable khá chặt
- Tuy nhiên codebase rộng và validation không đồng đều
- `User` có comment về `guarded` nhưng property đang là `guarded_attributes`, không có hiệu lực; may mắn là `fillable` hiện vẫn chặn phần lớn risk

### IDOR

Mức độ: 62/100

Nhận xét:

- Một số flow user-order-address có check user ownership
- Nhưng chưa có policy layer hệ thống để đảm bảo đồng nhất mọi resource

### Authentication

Mức độ: 52/100

Nhận xét:

- Có JWT, refresh token, social login
- Nhưng thiếu verify email, 2FA, session/device management
- Credential logging là lỗi nghiêm trọng

### Authorization

Mức độ: 57/100

Nhận xét:

- `role` middleware hoạt động
- Nhưng authorization còn coarse-grained, thiếu policy per-resource

### JWT Security

Mức độ: 50/100

Nhận xét:

- JWT dùng được cho SPA
- Refresh token đang trùng access token về mặt giá trị, không phải refresh token thật
- Token lưu trên browser storage là điểm trừ lớn

### Sanctum Security

Mức độ: N/A

Nhận xét:

- Không dùng Sanctum
- Có khai báo `sanctum/csrf-cookie` trong CORS config nhưng không thấy ứng dụng thực tế

### Session Security

Mức độ: 48/100

Nhận xét:

- Session browser-side chưa đủ mạnh do token storage pattern
- Chưa thấy revoke active sessions/device control

### Upload Security

Mức độ: 67/100

Nhận xét:

- Avatar flow làm khá tốt
- Product/post uploads vẫn thiên về validate cơ bản
- Cần xem lại SVG public và scan/security headers

## 4. Khuyến nghị ưu tiên

### P0

- Xóa log credential trong login flow
- Sửa idempotency payment callbacks
- Bật lại CAPTCHA hoặc thay thế bằng giải pháp anti-bot khác
- Loại route debug khỏi production exposure

### P1

- Chuyển JWT sang HTTP-only cookies hoặc ít nhất harden CSP + token lifecycle
- Bổ sung email verification và 2FA cho admin
- Loại bỏ upload SVG public hoặc sanitize
- Chuẩn hóa authorization bằng Policies

### P2

- Chuẩn hóa password reset flow bằng FormRequest + audit trail
- Tách security concerns khỏi middleware `strip_tags()` toàn cục
- Thêm monitoring cho login failure, OTP failure, payment callback anomalies

## 5. Kết luận

Hệ thống có nền tảng phòng thủ ban đầu nhưng hiện vẫn có nhiều lỗi đủ để gây sự cố thực tế khi mở production rộng. Trọng tâm xử lý không nằm ở viết thêm package bảo mật, mà nằm ở sửa các quyết định implementation đang làm yếu hệ thống:

- logging
- token storage
- callback idempotency
- authorization model
- anti-bot / account verification
