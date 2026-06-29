# Báo cáo audit đồng bộ giao diện - backend

Ngày kiểm tra: 2026-06-14  
Phạm vi: Laravel backend, Vue frontend, route/API, kiến trúc theo `.skill` của dự án.  
Cam kết: Không chỉnh sửa source code Laravel/Vue. Chỉ tạo file báo cáo này trong `docs`.

## Kết luận nhanh

Trạng thái hiện tại: **chưa đồng bộ hoàn toàn và chưa clean đúng chuẩn `.skill`**.

Backend không có lỗi cú pháp PHP trong các file chính đã kiểm tra, route Laravel load được. Tuy nhiên frontend đang có một số API gọi sai hoặc chưa được khai báo route, một số request protected bỏ qua axios instance chuẩn nên dễ lỗi auth, nhiều Page/Component gọi API trực tiếp thay vì đi qua `services`, backend còn nhiều controller chứa query/business logic trực tiếp, chưa tách đủ Controller -> Service -> Repository.

## Lệnh kiểm tra đã chạy

- `php -l` cho các file `app`, `routes`, `config`, `database/migrations`, `database/seeders`, `tests`: không phát hiện lỗi cú pháp PHP.
- `php artisan route:list --path=api`: chạy được, liệt kê 269 route API.
- `npx vite build --outDir C:/tmp/qs_project_frontend_audit_dist --emptyOutDir`: không chạy tới bước build code vì lỗi môi trường `spawn EPERM` khi Vite/esbuild khởi động process.

## Lỗi P1 - ảnh hưởng chức năng

### 1. Trang quản lý Flash Sale gọi API chưa tồn tại

Frontend gọi các endpoint:

- `frontend/src/Pages/admin/AdminFlashSale.vue:55` gọi `GET /admin/flash-sale/search-products`
- `frontend/src/Pages/admin/AdminFlashSale.vue:102` gọi `GET /admin/flash-sale`
- `frontend/src/Pages/admin/AdminFlashSale.vue:135` gọi `PUT /admin/flash-sale/{id}`
- `frontend/src/Pages/admin/AdminFlashSale.vue:163` gọi `DELETE /admin/flash-sale/{id}`

Backend hiện chỉ khai báo:

- `backend/routes/api.php:175` `GET /flash-sale`
- `backend/routes/api.php:227` `POST /admin/flash-sale`
- `backend/routes/api.php:228` `POST /admin/flash-sale/{id}/initialize`

Trong khi `backend/app/Http/Controllers/Admin/FlashSaleController.php` có đủ `adminIndex`, `searchProducts`, `update`, `destroy`, nhưng controller này chưa được map vào route admin. Kết quả: trang Admin Flash Sale sẽ lỗi 404/405 cho danh sách, tìm sản phẩm, sửa và xóa.

### 2. Admin notifications bỏ qua axios instance chuẩn nên dễ lỗi 401

`.skill` yêu cầu dùng `frontend/src/axios.js` là axios instance duy nhất để gắn JWT, refresh token, FormData handling.

Nhưng các file sau import axios gốc và gọi thẳng `/api/admin/...`:

- `frontend/src/Pages/admin/AdminNotifications.vue:72`, `:88`, `:101`, `:112`, `:140`
- `frontend/src/components/BackOfficeShell.vue:130`, `:197`

Các route backend tương ứng là protected:

- `backend/routes/api.php:276` đến `:279` yêu cầu `auth:api,admin` và role admin/seller

Do không qua interceptor chuẩn, token không tự gắn vào header. Người dùng đã đăng nhập vẫn có thể thấy thông báo lỗi hoặc badge thông báo không cập nhật.

### 3. Component chấm công dùng sai endpoint

`frontend/src/components/GeolocationAPI.vue:34` gọi `POST /api/attendance/check-in`.

Backend route đúng là:

- `backend/routes/api.php:317` `POST /api/admin/attendance/check-in`
- `backend/routes/api.php:318` `POST /api/admin/attendance/check-out`

Nếu component này còn được dùng, check-in sẽ lỗi 404. Component cũng dùng axios gốc tại `frontend/src/components/GeolocationAPI.vue:20`, nên thiếu JWT interceptor.

### 4. Phân quyền route frontend chưa khớp backend

Backend cho phép `admin,seller,staff` vào nhóm thống kê và court admin:

- `backend/routes/api.php:315` nhóm `role:admin,seller,staff`
- `backend/routes/api.php:585` nhóm court admin `role:admin,staff,seller`

Frontend lại chặn seller ở một số màn:

- `frontend/src/router/index.js:264` `admin-stats` chỉ `admin,staff`
- `frontend/src/router/index.js:289` `admin-courts` chỉ `admin,staff`

Kết quả: API cho phép nhưng UI router không cho vào. Đây là lỗi đồng bộ phân quyền frontend-backend.

## Lỗi P2 - lệch chuẩn `.skill`, clean code chưa đạt

### 5. Giao diện gọi API trực tiếp trong Page/Component quá nhiều

Theo `.skill`, API caller nên nằm ở `frontend/src/services`, Page/Component không nên rải request trực tiếp.

Hiện có nhiều Page/Component import `@/axios` hoặc axios gốc để gọi API, ví dụ:

- `frontend/src/Pages/admin/AdminProduct.vue:3`
- `frontend/src/Pages/admin/AdminUsers.vue:318`
- `frontend/src/Pages/admin/AdminStats.vue:87`
- `frontend/src/Pages/Client/Cart/Checkout.vue:4`
- `frontend/src/Pages/Client/Home/productDetail.vue:4`
- `frontend/src/components/ProductCard.vue:9`

Điều này làm logic API phân tán, khó kiểm soát response shape, khó mock/test và dễ lệch endpoint khi backend thay đổi.

### 6. Có hard-code URL/port localhost trong frontend

Các vị trí đáng chú ý:

- `frontend/src/echo.js:16-24` hard-code port `8383` cho Reverb/auth endpoint
- `frontend/src/composables/useMeilisearch.js:4-7` fallback `localhost:7700`, `localhost:8383`, `masterKey`
- `frontend/src/components/QuickAddSlider.vue:7` hard-code `http://localhost:8383/storage/`
- `frontend/src/Pages/Client/Static/Contact.vue:222` fallback `http://localhost:8383/api`
- `frontend/src/stores/auth.js:56` fallback `http://localhost:8383`

`.skill` yêu cầu không hard-code URL. Các fallback này dễ vỡ khi deploy domain thật, chạy Docker/nginx, hoặc đổi port.

### 7. Backend controller còn chứa query và business logic trực tiếp

`.skill` yêu cầu Controller chỉ nhận request, validate, gọi Service, trả JSON; query nằm ở Repository, logic nằm ở Service.

Một số ví dụ lệch chuẩn:

- `backend/app/Http/Controllers/Api/Admin/CourtAdminController.php:13`, `:29`, `:40`, `:58`, `:70` query/create/update/delete trực tiếp trong controller.
- `backend/app/Http/Controllers/Api/CourtController.php:53-89` query nhiều model trực tiếp, `:93-174` tự tính slot/availability ngay trong controller.
- `backend/app/Http/Controllers/Admin/FlashSaleController.php:62-127` transaction, create/update item, cache, Redis/state handling trong controller.
- `backend/app/Http/Controllers/FlashSaleController.php:23-178` query, cache, Redis stock decrement, rule max-per-user ngay trong controller.

Đây chưa phải lỗi runtime ngay, nhưng chưa clean theo kiến trúc dự án và làm tăng rủi ro bug khi mở rộng.

### 8. Khai báo stack trong `.skill` lệch composer thực tế

`.skill:4` ghi stack là Laravel 10, nhưng `backend/composer.json:14` đang dùng `laravel/framework: ^12.0`.

Nếu team đang audit theo Laravel 10 convention, cần cập nhật lại tài liệu skill hoặc xác nhận version mục tiêu. Một số API/framework behavior giữa Laravel 10 và 12 có thể khác.

## Lỗi P3 - cleanup, rủi ro vận hành

### 9. Route MoMo IPN bị khai báo trùng

- `backend/routes/api.php:435` khai báo `POST /payment/momo-ipn`
- `backend/routes/api.php:562` khai báo lại `POST /payment/momo-ipn` với throttle

`route:list` chỉ hiện một route, nhưng code route bị trùng làm người đọc khó hiểu route nào là nguồn đúng và dễ gây sai middleware khi chỉnh tiếp.

### 10. Debug/scheduler routes còn nằm trong API runtime

Các route debug vẫn tồn tại, dù đã có auth admin:

- `backend/routes/api.php:444` `/run-abandoned-cart`
- `backend/routes/api.php:453` `/run-birthday`
- `backend/routes/api.php:462` `/cart-status`
- `backend/routes/api.php:497` `/run-order-emails`
- `backend/routes/api.php:506` `/pending-emails`

Với môi trường production, các route này nên được gate thêm bằng `app()->environment()` hoặc tách khỏi public API surface.

### 11. Role user đang dùng `customer`, trong khi `.skill` ghi `user`

`.skill` mô tả role: `admin | seller | staff | user`, nhưng code/migration/backend/frontend dùng `customer`:

- `backend/database/migrations/0001_01_01_000000_create_users_table.php:21`
- `backend/app/Http/Controllers/AdminUserController.php:88`, `:207`
- `frontend/src/Pages/admin/AdminUsers.vue:64`, `:135`, `:333`

Không gây lỗi nếu toàn hệ thống thống nhất theo `customer`, nhưng tài liệu skill đang lệch với code thật.

## Điểm đã ổn

- Backend route list load được, không lỗi bootstrap route.
- PHP syntax check qua các thư mục chính không phát hiện lỗi cú pháp.
- Frontend có axios instance trung tâm tại `frontend/src/axios.js`, có refresh token, gắn Authorization, xử lý FormData.
- Nhiều service đã đi đúng pattern `import api from '@/axios'`, ví dụ `authService`, `cartService`, `catalogService`, `courtBookingService`, `orderService`, `returnRequestService`.

## Khuyến nghị ưu tiên xử lý sau audit

1. Map đầy đủ route cho `Admin\FlashSaleController` hoặc chỉnh frontend dùng đúng endpoint hiện có.
2. Thay các axios gốc trong protected UI bằng `@/axios`, ưu tiên `AdminNotifications.vue`, `BackOfficeShell.vue`, `GeolocationAPI.vue`.
3. Đồng bộ role router frontend với middleware backend, đặc biệt seller ở thống kê/court.
4. Gom API calls từ Page/Component về `services`, sau đó để Store/Page gọi service.
5. Đưa hard-code host/port/key về `.env` và một helper URL chung.
6. Tách dần controller lớn sang Service/Repository theo flow `.skill`.
7. Xóa/gate route debug và dọn route duplicate MoMo.

## Ghi chú kiểm chứng

Frontend build chưa xác nhận được vì môi trường hiện tại chặn esbuild process với lỗi `spawn EPERM`. Không chạy test database để tránh nguy cơ tác động dữ liệu hiện có. Source code Laravel/Vue không bị chỉnh sửa trong lượt audit này.
