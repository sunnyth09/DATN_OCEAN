# PERFORMANCE REPORT

Ngày phân tích: 2026-06-05  
Điểm hiệu năng tổng quan: 63/100

## 1. Tóm tắt

Hệ thống đã có các nền tảng quan trọng cho scale-up:

- Redis
- cache
- Meilisearch
- eager loading ở nhiều repository
- DB transaction và locking
- queue/jobs
- scheduler

Nhưng còn các điểm nghẽn làm hệ thống khó đạt production ổn định khi traffic tăng:

- invalidation cache quá thô
- runtime local/deploy chưa đồng nhất
- queue worker chưa hiện diện rõ trong compose stack
- một số endpoint chưa phân trang hoặc chưa giới hạn payload
- SEO còn CSR-only nên hiệu năng SEO/business bị ảnh hưởng

## 2. Findings

| ID | Mức độ | Vấn đề | Bằng chứng | Tác động | Khuyến nghị |
|---|---|---|---|---|---|
| PERF-01 | High | `Cache::flush()` được dùng trong module sản phẩm | `backend/app/Services/ProductService.php` | Thay đổi sản phẩm sẽ xóa toàn bộ cache hệ thống, ảnh hưởng flash sale, brands, categories, location cache... | Chuyển sang tag/key-based invalidation |
| PERF-02 | High | Docker stack chưa có queue worker service riêng | `docker-compose.yml` chỉ có `backend`, không có `queue-worker`; trong code lại có `SendBulkCouponEmail` job và queue database | Job có thể bị treo trong production stack nếu không có worker ngoài entrypoint | Tách service `queue-worker` hoặc supervisor process rõ ràng |
| PERF-03 | High | Local/runtime verification đang gãy vì thiếu `vendor/autoload.php` | chạy `php artisan route:list` và `php artisan test` lỗi do `backend/vendor/autoload.php` không tồn tại | Không xác minh được route/test thật, giảm mạnh độ tin cậy vận hành | Chuẩn hóa bootstrap dependency và lock local dev workflow |
| PERF-04 | Medium | Blog posts trả về toàn bộ records không phân trang | `backend/app/Http/Controllers/PostController.php` dùng `Post::with('category')->get()` | Sẽ chậm dần khi số bài viết tăng | Thêm pagination/filter/search |
| PERF-05 | Medium | Public catalog và admin-style query đang trộn vai trò | Frontend gọi `GET /products`; backend `ProductController@index` đi vào `listAdminProducts()` | Logic truy vấn lớn, khó cache chính xác, khó tối ưu public catalog độc lập | Tách rõ public catalog API và admin catalog API |
| PERF-06 | Medium | Search fallback từ Meilisearch về SQL LIKE | `backend/app/Services/ProductService.php` | Khi search engine lỗi, DB chịu tải truy vấn search đắt hơn | Thêm health check/search degradation strategy rõ ràng |
| PERF-07 | Medium | Notification mark-all chưa bulk SQL tối ưu | `backend/app/Http/Controllers/NotificationController.php` dùng collection `unreadNotifications->markAsRead()` | Với user có nhiều notification sẽ load object thừa | Dùng update query trực tiếp |
| PERF-08 | Medium | Ảnh sản phẩm chưa thấy pipeline optimize/CDN | upload lưu trực tiếp `storage/public` | Tăng băng thông, TTFB và Core Web Vitals kém | Tạo resize/webp pipeline, CDN hoặc image proxy chuẩn |
| PERF-09 | Medium | SEO và metadata hoàn toàn CSR | frontend chỉ dùng `document.title`; package `@unhead/vue` đã cài nhưng chưa dùng | Hiệu quả crawl/index thấp, CTR và organic traffic kém | Bổ sung meta runtime + sitemap + canonical; cân nhắc SSR/SSG |
| PERF-10 | Low | Route file API quá lớn | `backend/routes/api.php` gom hầu hết endpoints | Ảnh hưởng maintainability hơn raw performance, nhưng gây khó tối ưu route/domain | Tách route theo bounded context |

## 3. Đánh giá theo hạng mục yêu cầu

### N+1 Query

Mức độ: 74/100

Nhận xét:

- Nhiều repository/controller đã dùng `with()`
- Order, favorite, affiliate, court booking có eager loading khá ổn
- Chưa chứng minh toàn bộ code đã sạch N+1 vì không chạy profile runtime được

### Query chậm

Mức độ: 61/100

Nhận xét:

- Có search index và cache hỗ trợ
- Nhưng unpaginated post listing và fallback search về SQL LIKE sẽ là điểm đau khi dữ liệu lớn

### API chậm

Mức độ: 62/100

Nhận xét:

- API breadth lớn, nhưng chưa thấy tài liệu benchmark
- Một số endpoint action-style có thể lặp logic/phức tạp
- Chưa kiểm thử được route thật do môi trường local thiếu vendor

### Cache

Mức độ: 60/100

Nhận xét:

- Có áp dụng cache cho product, category, brands, flash sale, location
- Nhưng invalidation đang quá rộng ở product flow

### Redis

Mức độ: 76/100

Nhận xét:

- Dùng tốt cho flash sale stock, cache store, realtime ecosystem
- Có giá trị thực trong domain flash sale/court locking

### Queue

Mức độ: 52/100

Nhận xét:

- Có jobs và queue config
- Nhưng deployment stack hiện chưa chứng minh worker chạy ổn định

### Lazy / Eager loading

Mức độ: 72/100

Nhận xét:

- Backend có eager loading khá ổn
- Frontend có lazy-loaded admin pages

### Image optimization

Mức độ: 45/100

Nhận xét:

- Chưa thấy resize/compress responsive pipeline cho product images
- Chưa thấy CDN/image transformation layer

## 4. Frontend performance

Điểm tốt:

- Lazy load nhiều route admin
- Abort request ở product listing
- Session sync giảm race condition giữa tabs

Điểm yếu:

- Ảnh hero ngoài CDN công cộng nhưng chưa thấy preload strategy
- SEO/meta CSR-only
- Nhiều page lớn chứa nhiều logic trực tiếp
- Chưa thấy rõ skeleton/data virtualization cho danh sách lớn ở admin

## 5. Hạ tầng và deployment

Điểm tốt:

- Có Docker Compose
- Backend entrypoint có migrate, cron schedule, Reverb startup
- Có Redis và Meilisearch service

Điểm yếu:

- Không có queue worker service riêng trong compose
- Không có CI/CD manifest trong repo root để xác minh pipeline
- Runtime local hiện không tự chạy được Artisan vì dependency backend chưa đầy đủ

## 6. Khuyến nghị ưu tiên

### P0

- Thay `Cache::flush()` bằng invalidation theo key/tag
- Bổ sung queue worker rõ ràng trong deployment
- Sửa local bootstrap để `composer install`/`vendor` nhất quán

### P1

- Tách public catalog API khỏi admin catalog API
- Phân trang blog endpoints và các danh sách có khả năng tăng dữ liệu
- Bổ sung image optimization pipeline

### P2

- Theo dõi query slow log, queue metrics, callback payment metrics
- Tối ưu notification mark-all bằng bulk query
- Bổ sung SSR/SSG hoặc ít nhất meta/sitemap/canonical đầy đủ

## 7. Kết luận

Hệ thống có đủ nền tảng để tối ưu tốt, vì cache/Redis/search/transactions đã hiện diện. Vấn đề lớn nhất hiện tại không phải thiếu công nghệ, mà là:

- cách dùng cache
- cách vận hành queue
- độ nhất quán runtime
- việc trộn public/admin concerns

Nếu xử lý đúng 3 nhóm này, hiệu năng thực tế của hệ thống có thể tăng đáng kể mà chưa cần thay đổi kiến trúc lớn.
