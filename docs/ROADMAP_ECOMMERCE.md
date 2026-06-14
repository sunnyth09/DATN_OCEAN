# ROADMAP HOÀN THIỆN DỰ ÁN

Ngày lập roadmap: 2026-06-05

## Giai đoạn 1 - Hoàn thiện nền tảng

Ước lượng:

- Độ khó: Hard
- Thời gian: 2-3 tuần

| Task | Ưu tiên | Thời gian | Độ khó | Module |
|---|---|---|---|---|
| [ ] Xóa log credential đăng nhập, chuẩn hóa security logging | Critical | 0.5 ngày | Easy | Auth/Security |
| [ ] Sửa idempotency cho payment callback return/IPN | Critical | 1-2 ngày | Hard | Payment/Order |
| [ ] Bật lại CAPTCHA hoặc thay anti-bot flow có kiểm soát | Critical | 0.5-1 ngày | Medium | Auth |
| [ ] Loại route debug khỏi production code path | Critical | 0.5 ngày | Easy | API/Operations |
| [ ] Tách public catalog API khỏi admin catalog API | High | 1-2 ngày | Medium | Product/API |
| [ ] Thay `Cache::flush()` bằng invalidation theo key/tag | High | 1-2 ngày | Medium | Cache/Product |
| [ ] Chuẩn hóa runtime local: khôi phục `vendor`, chạy được Artisan/Test | Critical | 1 ngày | Medium | DevEx |
| [ ] Tài liệu hóa schema thật từ migrations thay cho dump lỗi thời | High | 1 ngày | Medium | Database |

## Giai đoạn 2 - Hoàn thiện nghiệp vụ

Ước lượng:

- Độ khó: Hard
- Thời gian: 3-4 tuần

| Task | Ưu tiên | Thời gian | Độ khó | Module |
|---|---|---|---|---|
| [ ] Bổ sung verify email sau đăng ký | Critical | 1 ngày | Medium | Auth |
| [ ] Bổ sung ZaloPay hoặc Stripe theo thị trường mục tiêu | High | 3-5 ngày | Hard | Payment |
| [ ] Hoàn thiện shipping zone thành module quản trị thật | High | 2-3 ngày | Medium | Shipping |
| [ ] Thêm review image upload + moderation | Medium | 2 ngày | Medium | Review |
| [ ] Hoàn thiện loyalty: rule earn/burn, expiry, transaction history | High | 3-4 ngày | Hard | Loyalty |
| [ ] Hoàn thiện combo/bundle promotion nếu theo định hướng retail | Medium | 2-4 ngày | Hard | Marketing |
| [ ] Chuẩn hóa FormRequest cho các flow còn dùng `Request` trực tiếp | High | 2-3 ngày | Medium | API |
| [ ] Thêm policy layer cho order, return, coupon, product admin | High | 2-3 ngày | Hard | Authorization |

## Giai đoạn 3 - Marketing

Ước lượng:

- Độ khó: Medium
- Thời gian: 2-3 tuần

| Task | Ưu tiên | Thời gian | Độ khó | Module |
|---|---|---|---|---|
| [ ] Hoàn thiện SEO metadata runtime bằng `@unhead/vue` | Critical | 1-2 ngày | Medium | Frontend/SEO |
| [ ] Tạo sitemap.xml, robots policy đầy đủ, canonical URL | Critical | 1-2 ngày | Medium | SEO |
| [ ] Bổ sung structured data cho product, breadcrumb, blog | High | 1-2 ngày | Medium | SEO |
| [ ] Search suggestion/autocomplete + synonym tuning | High | 2-3 ngày | Medium | Search |
| [ ] Notification center đa kênh: email template + SMS/push roadmap | Medium | 3-5 ngày | Hard | Notification |
| [ ] Tạo landing/support cho affiliate rõ ràng hơn | Medium | 1-2 ngày | Medium | Affiliate |

## Giai đoạn 4 - Tối ưu hệ thống

Ước lượng:

- Độ khó: Hard
- Thời gian: 2-3 tuần

| Task | Ưu tiên | Thời gian | Độ khó | Module |
|---|---|---|---|---|
| [ ] Thêm queue worker service riêng trong deployment | Critical | 1 ngày | Medium | Infra/Queue |
| [ ] Thêm monitoring cho jobs, failed_jobs, payment callbacks | High | 1-2 ngày | Medium | Operations |
| [ ] Tối ưu image pipeline: resize, webp, cache headers, CDN | High | 2-4 ngày | Hard | Media/Frontend |
| [ ] Phân trang và tối ưu endpoint blog/admin list lớn | Medium | 1-2 ngày | Easy | API |
| [ ] Benchmark query/search/flash sale hotspots | Medium | 2 ngày | Medium | Backend/DB |
| [ ] Dọn tách `routes/api.php` theo bounded context | Medium | 2-3 ngày | Medium | Architecture |

## Giai đoạn 5 - Production Ready

Ước lượng:

- Độ khó: Hard
- Thời gian: 2-4 tuần

| Task | Ưu tiên | Thời gian | Độ khó | Module |
|---|---|---|---|---|
| [ ] Tạo OpenAPI/Swagger hoặc Postman collection chính thức | High | 2 ngày | Medium | API |
| [ ] Thiết lập CI chạy lint/test/build | Critical | 2-3 ngày | Medium | DevOps |
| [ ] Bổ sung feature test cho auth, payment, order, coupon, returns | Critical | 3-5 ngày | Hard | Testing |
| [ ] Chuẩn hóa rollback/deploy checklist | High | 1 ngày | Medium | Deployment |
| [ ] Thiết lập secret management và config audit | High | 1 ngày | Medium | Security/Infra |
| [ ] Review toàn bộ permission matrix admin/seller/staff | High | 2 ngày | Medium | Authorization |

## Giai đoạn 6 - Enterprise Features

Ước lượng:

- Độ khó: Hard
- Thời gian: 4-8 tuần

| Task | Ưu tiên | Thời gian | Độ khó | Module |
|---|---|---|---|---|
| [ ] Membership tiers + cashback engine | Medium | 1-2 tuần | Hard | Loyalty |
| [ ] Recommendation/personalization engine | Medium | 1-2 tuần | Hard | Search/Marketing |
| [ ] Multi-warehouse / fulfillment workflow | Medium | 2-3 tuần | Hard | Inventory/Operations |
| [ ] BI dashboard và anomaly monitoring | Medium | 1-2 tuần | Hard | Analytics |
| [ ] Approval workflow/audit log cấp enterprise | Medium | 1-2 tuần | Hard | Admin/Security |
| [ ] SSO/2FA bắt buộc cho backoffice | Medium | 1 tuần | Hard | Security |

## Ưu tiên thực thi đề xuất

### Nhóm Critical nên làm ngay

- Xóa credential logging
- Sửa payment callback idempotency
- Bật lại anti-bot/CAPTCHA
- Gỡ debug routes khỏi production path
- Khôi phục runtime/testability local
- Thêm queue worker rõ ràng
- Hoàn thiện verify email
- Hoàn thiện SEO nền tảng

### Nhóm High nên làm kế tiếp

- Policy layer
- Payment gateway còn thiếu
- Shipping zone module
- Loyalty thực thụ
- API documentation
- CI/test coverage

## Kết quả kỳ vọng sau roadmap

Sau 3 giai đoạn đầu:

- Hệ thống đủ an toàn và rõ ràng để chạy production thật.
- Có thể scale marketing/SEO tốt hơn.
- Có nền tảng tốt để tiếp tục mở rộng loyalty, marketplace hoặc enterprise features.

Sau 6 giai đoạn:

- Hệ thống chuyển từ mức “nhiều tính năng” sang mức “vận hành được và mở rộng được”.
