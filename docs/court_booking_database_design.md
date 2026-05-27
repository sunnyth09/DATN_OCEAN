# Thiết Kế Database — Module Quản Lý Đặt Sân Cầu Lông

> **Dự án:** DATN_OCEAN — Laravel API + Vue.js + Flutter  
> **Ngày thiết kế:** 2026-05-27  
> **Tác giả:** Senior Laravel Architect  
> **Phiên bản:** 1.0  
> **Trạng thái:** ✅ Đã audit thực tế — Sẵn sàng triển khai

---

## Mục lục

1. [Audit Database Hiện Tại](#1-audit-database-hiện-tại)
2. [Bảng Có Thể Tái Sử Dụng](#2-bảng-có-thể-tái-sử-dụng)
3. [Bảng Không Nên Đụng Vào](#3-bảng-không-nên-đụng-vào)
4. [Bảng Cần Mở Rộng](#4-bảng-cần-mở-rộng)
5. [Thiết Kế Chi Tiết Từng Bảng Mới](#5-thiết-kế-chi-tiết-từng-bảng-mới)
6. [Chống Đặt Trùng Lịch](#6-chống-đặt-trùng-lịch)
7. [Tích Hợp Payments Hiện Có](#7-tích-hợp-payments-hiện-có)
8. [Danh Sách Migration Đề Xuất](#8-danh-sách-migration-đề-xuất)
9. [Laravel Model Relationships](#9-laravel-model-relationships)
10. [ERD Dạng Text](#10-erd-dạng-text)
11. [Index và Tối Ưu Query](#11-index-và-tối-ưu-query)
12. [Rủi Ro Khi Triển Khai](#12-rủi-ro-khi-triển-khai)
13. [Checklist Task Triển Khai](#13-checklist-task-triển-khai)

---

## 1. Audit Database Hiện Tại

### 1.1 Quy ước đặt tên (Naming Convention)

Sau khi kiểm tra tất cả 59 migration hiện có, dự án áp dụng quy ước **nhất quán**:

| Quy tắc | Giá trị |
|---------|---------|
| Tên bảng | `snake_case`, số nhiều (`users`, `orders`, `payments`…) |
| Primary key | Custom name dạng `{tên_bảng_số_ít}_id` — VD: `user_id`, `order_id`, `payment_id` |
| Foreign key | Tên cột trùng với primary key bảng gốc |
| Kiểu PK | `bigIncrements` / `id('custom_name')` → `BIGINT UNSIGNED AUTO_INCREMENT` |
| Timestamps | `timestamps()` — hầu hết bảng dùng |
| Soft delete | Chỉ dùng ở `users` và `coupons` |
| Enum | Dùng trực tiếp `enum()` — không qua bảng lookup |
| JSON | `json()` hoặc `text()` cho data lớn |
| Decimal | `decimal(12, 2)` cho tiền tệ |

> **Ngoại lệ phát hiện:**
> - `coupons` dùng `bigIncrements('id')` thay vì `id('coupon_id')` — không nhất quán
> - `chat_sessions`, `flash_sales`, `attendances` dùng `id()` không đặt tên custom
> - `notifications` dùng `uuid` làm primary key — chuẩn Laravel Notifications

---

### 1.2 Danh sách tất cả bảng hiện có

| Tên bảng | Primary Key | Soft Delete | Ghi chú |
|----------|-------------|-------------|---------|
| `users` | `user_id` | ✅ | JWT Auth, role: admin/staff/customer/seller |
| `admins` | `admin_id` | ❌ | Bảng admin riêng — JWT Auth |
| `sessions` | `id` (string) | ❌ | Laravel session |
| `password_reset_tokens` | `email` | ❌ | — |
| `brands` | `brand_id` | ❌ | — |
| `categories` | `category_id` | ❌ | — |
| `addresses` | `address_id` | ❌ | Địa chỉ giao hàng |
| `products` | `product_id` | ❌ | — |
| `product_variants` | `variant_id` | ❌ | — |
| `product_images` | `image_id` | ❌ | — |
| `carts` | `cart_id` | ❌ | — |
| `cart_items` | `cart_item_id` | ❌ | — |
| `favorites` | `favorite_id` | ❌ | — |
| `promotions` | `promotion_id` | ❌ | — |
| `promotion_categories` | pivot | ❌ | — |
| `promotion_products` | pivot | ❌ | — |
| `promotion_usages` | `usage_id` | ❌ | — |
| `orders` | `order_id` | ❌ | `order_type`: online/pos |
| `order_items` | `order_item_id` | ❌ | — |
| `order_status_histories` | `history_id` | ❌ | Học pattern này cho booking |
| `payments` | `payment_id` | ❌ | ⚠️ `order_id NOT NULL` — không polymorphic |
| `inventory_transactions` | `id` | ❌ | — |
| `product_comments` | `id` | ❌ | Polymorphic commenter |
| `admins` | `admin_id` | ❌ | — |
| `password_resets_otp` | — | ❌ | — |
| `contacts` | — | ❌ | — |
| `coupons` | `id` (bigIncrements) | ✅ | PK không nhất quán |
| `user_coupons` | pivot | ❌ | — |
| `coupon_categories` | pivot | ❌ | — |
| `post_categories` | — | ❌ | — |
| `posts` | — | ❌ | — |
| `shipping_zones` | — | ❌ | — |
| `flash_sales` | `id` | ❌ | — |
| `flash_sale_items` | `id` | ❌ | — |
| `attendances` | `id` | ❌ | Chấm công nhân viên |
| `notifications` | `uuid` | ❌ | ✅ Polymorphic — dùng được cho booking |
| `chat_sessions` | `id` | ❌ | — |
| `chat_messages` | — | ❌ | — |
| `affiliate_clicks` | — | ❌ | — |
| `affiliate_conversions` | — | ❌ | — |
| `affiliate_withdrawals` | — | ❌ | — |

---

## 2. Bảng Có Thể Tái Sử Dụng

| Bảng | Cách tái sử dụng |
|------|-----------------|
| `users` | ✅ Dùng `user_id` làm FK cho `court_bookings.user_id` |
| `admins` | ✅ Dùng `admin_id` cho `court_bookings.staff_id` (lễ tân) |
| `notifications` | ✅ Đã polymorphic — gửi thông báo booking qua kênh `database` |
| `order_status_histories` | ✅ Học pattern — áp dụng cho `court_booking_status_histories` |

---

## 3. Bảng Không Nên Đụng Vào

| Bảng | Lý do |
|------|-------|
| `orders` | Không liên quan đến đặt sân; cấu trúc riêng cho e-commerce |
| `order_items` | Không liên quan |
| `addresses` | Địa chỉ giao hàng — không dùng cho booking sân |
| `carts` / `cart_items` | Không áp dụng |
| `promotions` / `coupons` | Module sân tự quản coupon nếu cần |
| `products` / `product_variants` | Không liên quan |
| `inventory_transactions` | Không liên quan |

---

## 4. Bảng Cần Mở Rộng

### 4.1 Bảng `payments` — **BẮT BUỘC** phân tích

```
Cấu trúc hiện tại:
  payment_id    | BIGINT PK
  order_id      | BIGINT NOT NULL FK → orders.order_id  ← VẤN ĐỀ
  payment_method| ENUM(cod, vnpay, momo, bank_transfer)
  amount        | DECIMAL(12,2)
  status        | ENUM(pending, success, failed, refunded)
  paid_at       | DATETIME NULLABLE
  gateway_response | JSON NULLABLE
```

**Kết luận:** `order_id` là NOT NULL với FK cascade → **không thể dùng chung cho booking sân mà không sửa bảng**.

> Xem Phương án A và B tại [Mục 7](#7-tích-hợp-payments-hiện-có).

---

## 5. Thiết Kế Chi Tiết Từng Bảng Mới

### 5.1 Bảng `courts` — Danh sách sân cầu lông

**Mục đích:** Lưu thông tin từng sân. Hỗ trợ 7 sân hiện tại và mở rộng thêm.

```php
Schema::create('courts', function (Blueprint $table) {
    $table->id('court_id');
    $table->string('court_name', 100);
    $table->string('court_code', 20)->unique();              // VD: "COURT-01"
    $table->enum('type', ['standard', 'vip', 'outdoor', 'indoor'])->default('standard');
    $table->text('description')->nullable();
    $table->string('surface', 50)->nullable();               // VD: "Gỗ", "Composite"
    $table->unsignedTinyInteger('max_players')->default(4);
    $table->enum('status', [
        'active',       // Đang hoạt động
        'inactive',     // Tạm ngưng
        'maintenance',  // Đang bảo trì
        'closed',       // Đóng cửa vĩnh viễn
    ])->default('active');
    $table->string('image_url')->nullable();
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->index('status');
    $table->index('sort_order');
});
```

| | |
|--|--|
| **Primary key** | `court_id` (BIGINT UNSIGNED) |
| **Soft delete** | ✅ — tránh mất lịch sử booking |
| **Timestamps** | ✅ |

---

### 5.2 Bảng `court_schedules` — Lịch hoạt động theo ngày/tuần

**Mục đích:** Định nghĩa khung giờ mở/đóng cửa từng sân theo từng ngày trong tuần.

```php
Schema::create('court_schedules', function (Blueprint $table) {
    $table->id('schedule_id');
    $table->unsignedBigInteger('court_id');
    $table->foreign('court_id')->references('court_id')->on('courts')->onDelete('cascade');
    $table->unsignedTinyInteger('day_of_week');  // 0=CN, 1=T2, ..., 6=T7
    $table->time('open_time');
    $table->time('close_time');
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->unique(['court_id', 'day_of_week']);
    $table->index(['court_id', 'day_of_week', 'is_active']);
});
```

---

### 5.3 Bảng `court_prices` — Giá theo khung giờ

**Mục đích:** Giá thuê sân theo khung giờ, loại ngày (thường/cuối tuần/lễ).

```php
Schema::create('court_prices', function (Blueprint $table) {
    $table->id('price_id');
    $table->unsignedBigInteger('court_id');
    $table->foreign('court_id')->references('court_id')->on('courts')->onDelete('cascade');
    $table->string('price_name', 100)->nullable();           // VD: "Giờ cao điểm"
    $table->enum('day_type', ['weekday', 'weekend', 'holiday', 'all'])->default('all');
    $table->time('from_time');
    $table->time('to_time');
    $table->decimal('price_per_hour', 10, 2);
    $table->boolean('is_active')->default(true);
    $table->timestamp('effective_from')->nullable();
    $table->timestamp('effective_to')->nullable();
    $table->timestamps();

    $table->index(['court_id', 'day_type', 'is_active']);
    $table->index(['from_time', 'to_time']);
});
```

---

### 5.4 Bảng `court_bookings` — Booking sân (bảng trung tâm)

**Mục đích:** Lưu toàn bộ thông tin mỗi lần đặt sân. Đây là bảng trung tâm của module.

```php
Schema::create('court_bookings', function (Blueprint $table) {
    $table->id('booking_id');
    $table->string('booking_code', 30)->unique();            // VD: "BK-20260527-0001"

    // AI/WHO
    $table->unsignedBigInteger('user_id');
    $table->foreign('user_id')->references('user_id')->on('users')->restrictOnDelete();
    $table->unsignedBigInteger('staff_id')->nullable();      // Lễ tân tạo hộ
    $table->foreign('staff_id')->references('admin_id')->on('admins')->nullOnDelete();

    // WHAT/WHERE
    $table->unsignedBigInteger('court_id');
    $table->foreign('court_id')->references('court_id')->on('courts')->restrictOnDelete();

    // WHEN
    $table->date('booking_date');
    $table->time('start_time');
    $table->time('end_time');
    $table->unsignedSmallInteger('duration_minutes');

    // TRẠNG THÁI
    $table->enum('status', [
        'pending',      // Chờ xác nhận
        'confirmed',    // Đã xác nhận
        'checked_in',   // Đã nhận sân
        'playing',      // Đang chơi
        'completed',    // Hoàn thành
        'cancelled',    // Huỷ
        'no_show',      // Không đến
        'extended',     // Đã gia hạn
    ])->default('pending');

    // TIỀN
    $table->decimal('original_price', 12, 2);
    $table->decimal('discount_amount', 12, 2)->default(0);
    $table->decimal('service_amount', 12, 2)->default(0);
    $table->decimal('total_amount', 12, 2);
    $table->decimal('deposit_amount', 12, 2)->default(0);
    $table->decimal('paid_amount', 12, 2)->default(0);

    // THANH TOÁN
    $table->enum('payment_status', [
        'unpaid', 'deposit_paid', 'partially_paid',
        'paid', 'refunded', 'partially_refunded',
    ])->default('unpaid');
    $table->enum('payment_method', [
        'cash', 'vnpay', 'momo', 'bank_transfer', 'pos_card', 'pos_transfer',
    ])->nullable();

    // THỜI GIAN THỰC
    $table->dateTime('checked_in_at')->nullable();
    $table->dateTime('checked_out_at')->nullable();
    $table->dateTime('confirmed_at')->nullable();
    $table->dateTime('cancelled_at')->nullable();

    // HUỶ / GHI CHÚ
    $table->enum('cancel_reason_type', [
        'customer_request', 'no_show', 'court_issue', 'maintenance', 'other',
    ])->nullable();
    $table->string('cancel_reason', 255)->nullable();
    $table->text('note')->nullable();

    // NGUỒN BOOKING
    $table->enum('source', ['web', 'mobile', 'pos', 'phone'])->default('web');

    $table->timestamps();
    $table->softDeletes();

    // INDEX
    $table->index(['court_id', 'booking_date', 'status']);
    $table->index(['court_id', 'booking_date', 'start_time', 'end_time']);
    $table->index(['user_id', 'status']);
    $table->index(['booking_date', 'status']);
    $table->index('booking_code');
});
```

| | |
|--|--|
| **Primary key** | `booking_id` |
| **Foreign key** | `user_id → users.user_id`, `staff_id → admins.admin_id`, `court_id → courts.court_id` |
| **Unique** | `booking_code` |
| **Soft delete** | ✅ — lịch sử booking phải giữ lại |
| **Timestamps** | ✅ |

---

### 5.5 Bảng `court_booking_status_histories` — Lịch sử trạng thái

**Mục đích:** Ghi lại mọi thay đổi trạng thái booking. Học theo pattern `order_status_histories`.

```php
Schema::create('court_booking_status_histories', function (Blueprint $table) {
    $table->id('history_id');
    $table->unsignedBigInteger('booking_id');
    $table->foreign('booking_id')->references('booking_id')->on('court_bookings')->cascadeOnDelete();
    $table->string('old_status', 50)->nullable();
    $table->string('new_status', 50);
    $table->string('note', 255)->nullable();
    $table->string('actor_type', 30)->nullable();   // 'user', 'admin', 'system'
    $table->unsignedBigInteger('actor_id')->nullable();
    $table->json('meta')->nullable();
    $table->timestamp('created_at')->useCurrent();

    $table->index(['booking_id', 'created_at']);
});
```

---

### 5.6 Bảng `court_booking_locks` — Giữ slot tạm thời

**Mục đích:** Tạm giữ slot khi user đang trong flow đặt sân (TTL 10 phút). Chống race condition.

```php
Schema::create('court_booking_locks', function (Blueprint $table) {
    $table->id('lock_id');
    $table->unsignedBigInteger('court_id');
    $table->foreign('court_id')->references('court_id')->on('courts')->cascadeOnDelete();
    $table->date('booking_date');
    $table->time('start_time');
    $table->time('end_time');
    $table->unsignedBigInteger('user_id')->nullable();
    $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
    $table->string('lock_token', 64)->unique();           // UUID để release
    $table->timestamp('expires_at');                      // Hết hạn sau 10 phút
    $table->timestamps();

    $table->index(['court_id', 'booking_date', 'start_time', 'end_time', 'expires_at']);
    $table->index('expires_at');                          // Cho job cleanup
});
```

> **Yêu cầu:** Schedule job `CleanExpiredBookingLocks` chạy mỗi **5 phút**.

---

### 5.7 Bảng `court_services` — Danh mục dịch vụ phát sinh

**Mục đích:** Danh sách dịch vụ bán thêm: nước uống, thuê vợt, mua cầu lông, khăn...

```php
Schema::create('court_services', function (Blueprint $table) {
    $table->id('service_id');
    $table->string('service_name', 100);
    $table->string('service_code', 30)->unique();         // VD: "WATER", "RACKET"
    $table->enum('unit', ['piece', 'bottle', 'set', 'hour', 'other'])->default('piece');
    $table->decimal('unit_price', 10, 2);
    $table->text('description')->nullable();
    $table->string('image_url')->nullable();
    $table->boolean('is_active')->default(true);
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['is_active', 'sort_order']);
});
```

---

### 5.8 Bảng `court_booking_services` — Dịch vụ phát sinh của booking

**Mục đích:** Ghi nhận dịch vụ thêm vào từng booking (trong check-in hoặc khi đang chơi).

```php
Schema::create('court_booking_services', function (Blueprint $table) {
    $table->id('booking_service_id');
    $table->unsignedBigInteger('booking_id');
    $table->foreign('booking_id')->references('booking_id')->on('court_bookings')->cascadeOnDelete();
    $table->unsignedBigInteger('service_id');
    $table->foreign('service_id')->references('service_id')->on('court_services')->restrictOnDelete();
    $table->unsignedSmallInteger('quantity')->default(1);
    $table->decimal('unit_price', 10, 2);                // Snapshot giá tại thời điểm thêm
    $table->decimal('subtotal', 10, 2);
    $table->string('note', 255)->nullable();
    $table->unsignedBigInteger('added_by')->nullable();
    $table->foreign('added_by')->references('admin_id')->on('admins')->nullOnDelete();
    $table->timestamps();

    $table->index('booking_id');
});
```

---

### 5.9 Bảng `court_maintenances` — Lịch bảo trì sân

**Mục đích:** Lên lịch bảo trì, tự động block slot không cho đặt.

```php
Schema::create('court_maintenances', function (Blueprint $table) {
    $table->id('maintenance_id');
    $table->unsignedBigInteger('court_id');
    $table->foreign('court_id')->references('court_id')->on('courts')->cascadeOnDelete();
    $table->string('title', 150);
    $table->text('description')->nullable();
    $table->dateTime('start_datetime');
    $table->dateTime('end_datetime');
    $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
    $table->unsignedBigInteger('created_by')->nullable();
    $table->foreign('created_by')->references('admin_id')->on('admins')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['court_id', 'start_datetime', 'end_datetime', 'status']);
});
```

---

### 5.10 Bảng `court_booking_payments` — Thanh toán booking sân

**Mục đích:** Bảng thanh toán riêng cho module đặt sân — không dùng `payments` cũ (do `order_id NOT NULL`).

```php
Schema::create('court_booking_payments', function (Blueprint $table) {
    $table->id('court_payment_id');
    $table->unsignedBigInteger('booking_id');
    $table->foreign('booking_id')->references('booking_id')->on('court_bookings')->cascadeOnDelete();
    $table->enum('payment_type', [
        'deposit',      // Thanh toán cọc
        'full',         // Thanh toán toàn phần
        'additional',   // Phần còn lại / dịch vụ phát sinh
        'refund',       // Hoàn tiền
    ]);
    $table->enum('payment_method', [
        'cash', 'vnpay', 'momo', 'bank_transfer', 'pos_card', 'pos_transfer',
    ]);
    $table->string('transaction_code', 120)->nullable();
    $table->decimal('amount', 12, 2);
    $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
    $table->dateTime('paid_at')->nullable();
    $table->json('gateway_response')->nullable();
    $table->string('note', 255)->nullable();
    $table->unsignedBigInteger('processed_by')->nullable();
    $table->foreign('processed_by')->references('admin_id')->on('admins')->nullOnDelete();
    $table->timestamps();

    $table->index(['booking_id', 'payment_type', 'status']);
    $table->index('transaction_code');
});
```

---

### 5.11 Bảng `court_booking_extensions` — Gia hạn giờ chơi

**Mục đích:** Ghi nhận mỗi lần gia hạn thêm giờ trong khi đang chơi.

```php
Schema::create('court_booking_extensions', function (Blueprint $table) {
    $table->id('extension_id');
    $table->unsignedBigInteger('booking_id');
    $table->foreign('booking_id')->references('booking_id')->on('court_bookings')->cascadeOnDelete();
    $table->time('original_end_time');
    $table->time('extended_end_time');
    $table->unsignedSmallInteger('extension_minutes');
    $table->decimal('extra_amount', 10, 2);
    $table->unsignedBigInteger('approved_by')->nullable();
    $table->foreign('approved_by')->references('admin_id')->on('admins')->nullOnDelete();
    $table->timestamps();

    $table->index('booking_id');
});
```

---

### 5.12 Bảng `court_activity_logs` — Nhật ký thao tác

**Mục đích:** Ghi lại mọi thao tác của nhân viên/admin lên module booking. Phục vụ audit.

```php
Schema::create('court_activity_logs', function (Blueprint $table) {
    $table->id('log_id');
    $table->string('actor_type', 30);                    // 'admin', 'user', 'system'
    $table->unsignedBigInteger('actor_id')->nullable();
    $table->string('action', 100);                       // VD: 'booking.confirm'
    $table->string('subject_type', 60)->nullable();      // VD: 'CourtBooking'
    $table->unsignedBigInteger('subject_id')->nullable();
    $table->json('old_data')->nullable();
    $table->json('new_data')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent', 255)->nullable();
    $table->timestamp('created_at')->useCurrent();

    $table->index(['actor_type', 'actor_id']);
    $table->index(['subject_type', 'subject_id']);
    $table->index('created_at');
    $table->index('action');
});
```

---

## 6. Chống Đặt Trùng Lịch

### 6.1 Định nghĩa trạng thái

```php
// Trạng thái ĐANG CHIẾM SÂN — blocking
const BLOCKING_STATUSES = ['pending', 'confirmed', 'checked_in', 'playing', 'extended'];

// Trạng thái KHÔNG CÒN CHIẾM SÂN
const FREE_STATUSES = ['cancelled', 'completed', 'no_show'];
```

### 6.2 Overlap Query

```php
// Kiểm tra court_id + date + [startTime, endTime] có bị chiếm không
$conflict = DB::table('court_bookings')
    ->where('court_id', $courtId)
    ->where('booking_date', $date)
    ->whereIn('status', ['pending', 'confirmed', 'checked_in', 'playing', 'extended'])
    ->where(function ($q) use ($startTime, $endTime) {
        // Overlap: start < requested_end AND end > requested_start
        $q->where('start_time', '<', $endTime)
          ->where('end_time', '>', $startTime);
    })
    ->lockForUpdate()
    ->exists();
```

### 6.3 Kiểm tra bảo trì overlap

```php
$maintenanceConflict = DB::table('court_maintenances')
    ->where('court_id', $courtId)
    ->whereIn('status', ['scheduled', 'in_progress'])
    ->where('start_datetime', '<', "$date $endTime")
    ->where('end_datetime', '>', "$date $startTime")
    ->exists();
```

### 6.4 Kiểm tra booking lock tạm

```php
$lockConflict = DB::table('court_booking_locks')
    ->where('court_id', $courtId)
    ->where('booking_date', $date)
    ->where('expires_at', '>', now())
    ->where('start_time', '<', $endTime)
    ->where('end_time', '>', $startTime)
    ->exists();
```

### 6.5 Flow hoàn chỉnh — Transaction + Lock

```php
public function createBooking(array $data): CourtBooking
{
    return DB::transaction(function () use ($data) {
        // 1. Kiểm tra booking overlap — lockForUpdate
        $conflict = DB::table('court_bookings')
            ->where('court_id', $data['court_id'])
            ->where('booking_date', $data['booking_date'])
            ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new BookingConflictException('Sân đã được đặt trong khung giờ này.');
        }

        // 2. Kiểm tra lock tạm
        $lockConflict = DB::table('court_booking_locks')
            ->where('court_id', $data['court_id'])
            ->where('booking_date', $data['booking_date'])
            ->where('expires_at', '>', now())
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->lockForUpdate()
            ->exists();

        if ($lockConflict) {
            throw new BookingConflictException('Slot đang được giữ tạm bởi người khác. Vui lòng thử lại sau.');
        }

        // 3. Kiểm tra bảo trì
        $maintenanceConflict = DB::table('court_maintenances')
            ->where('court_id', $data['court_id'])
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where('start_datetime', '<', $data['booking_date'] . ' ' . $data['end_time'])
            ->where('end_datetime', '>', $data['booking_date'] . ' ' . $data['start_time'])
            ->exists();

        if ($maintenanceConflict) {
            throw new BookingConflictException('Sân đang trong lịch bảo trì tại khung giờ này.');
        }

        // 4. Tạo booking
        $booking = CourtBooking::create($data);

        // 5. Ghi lịch sử
        CourtBookingStatusHistory::create([
            'booking_id' => $booking->booking_id,
            'old_status' => null,
            'new_status' => 'pending',
            'actor_type' => auth()->guard('api')->check() ? 'admin' : 'user',
            'actor_id'   => auth()->id(),
        ]);

        // 6. Xóa booking lock nếu có
        if (!empty($data['lock_token'])) {
            CourtBookingLock::where('lock_token', $data['lock_token'])->delete();
        }

        return $booking;
    });
}
```

---

## 7. Tích Hợp Payments Hiện Có

### Phân tích

Bảng `payments` hiện tại có `order_id NOT NULL` với FK cascade. **Không thể dùng chung** cho booking sân mà không sửa cấu trúc.

---

### Phương án A — Bảng riêng `court_booking_payments` ✅ KHUYẾN NGHỊ

| | Chi tiết |
|--|---------|
| **Ưu điểm** | Zero risk với module cũ; deploy độc lập; hỗ trợ `payment_type` (deposit/full/refund) |
| **Nhược điểm** | 2 bảng payment riêng; báo cáo tổng phải JOIN 2 bảng |
| **Rủi ro** | Thấp |
| **Khi nào chọn** | ✅ Khi timeline gấp hoặc module bán hàng đang live |

---

### Phương án B — Refactor `payments` sang Polymorphic

| | Chi tiết |
|--|---------|
| **Ưu điểm** | 1 bảng payment duy nhất; chuẩn Laravel polymorphic |
| **Nhược điểm** | Phải sửa migration data live; phải kiểm tra toàn bộ code payment cũ |
| **Rủi ro** | **CAO** |
| **Khi nào chọn** | Khi có sprint refactor riêng với đủ test coverage |

**Migration Phương án B (nếu chọn sau):**

```php
// 2026_xx_xx_000001_refactor_payments_to_polymorphic.php
Schema::table('payments', function (Blueprint $table) {
    $table->string('payable_type', 60)->nullable()->after('order_id');
    $table->unsignedBigInteger('payable_id')->nullable()->after('payable_type');
    $table->unsignedBigInteger('order_id')->nullable()->change(); // Bỏ NOT NULL
    $table->enum('payment_type', ['deposit', 'full', 'additional', 'refund'])
          ->default('full')->after('payable_id');
    $table->index(['payable_type', 'payable_id']);
});

// Backfill data cũ
DB::statement("
    UPDATE payments
    SET payable_type = 'App\\\\Models\\\\Order', payable_id = order_id
    WHERE order_id IS NOT NULL AND payable_type IS NULL
");
```

> ⚠️ **Phương án B đòi hỏi sửa:** `PaymentController`, `PaymentService`, `VnpayService`, `MomoService`, `Order` model.

---

## 8. Danh Sách Migration Đề Xuất

Thứ tự tạo theo dependency:

| STT | Tên file migration | Bảng | Phụ thuộc |
|-----|-------------------|------|-----------|
| 1 | `2026_05_28_000001_create_courts_table.php` | `courts` | Không |
| 2 | `2026_05_28_000002_create_court_schedules_table.php` | `court_schedules` | `courts` |
| 3 | `2026_05_28_000003_create_court_prices_table.php` | `court_prices` | `courts` |
| 4 | `2026_05_28_000004_create_court_bookings_table.php` | `court_bookings` | `courts`, `users`, `admins` |
| 5 | `2026_05_28_000005_create_court_booking_status_histories_table.php` | `court_booking_status_histories` | `court_bookings` |
| 6 | `2026_05_28_000006_create_court_booking_locks_table.php` | `court_booking_locks` | `courts`, `users` |
| 7 | `2026_05_28_000007_create_court_services_table.php` | `court_services` | Không |
| 8 | `2026_05_28_000008_create_court_booking_services_table.php` | `court_booking_services` | `court_bookings`, `court_services` |
| 9 | `2026_05_28_000009_create_court_maintenances_table.php` | `court_maintenances` | `courts`, `admins` |
| 10 | `2026_05_28_000010_create_court_booking_payments_table.php` | `court_booking_payments` | `court_bookings`, `admins` |
| 11 | `2026_05_28_000011_create_court_booking_extensions_table.php` | `court_booking_extensions` | `court_bookings`, `admins` |
| 12 | `2026_05_28_000012_create_court_activity_logs_table.php` | `court_activity_logs` | Không |

> **Rollback:** Chạy ngược từ 12 → 1.

---

## 9. Laravel Model Relationships

### Court

```php
class Court extends Model
{
    use SoftDeletes;
    protected $primaryKey = 'court_id';

    protected $fillable = [
        'court_name', 'court_code', 'type', 'description',
        'surface', 'max_players', 'status', 'image_url', 'sort_order',
    ];

    protected $casts = [
        'sort_order'  => 'integer',
        'max_players' => 'integer',
    ];

    public function bookings()     { return $this->hasMany(CourtBooking::class, 'court_id', 'court_id'); }
    public function prices()       { return $this->hasMany(CourtPrice::class, 'court_id', 'court_id'); }
    public function schedules()    { return $this->hasMany(CourtSchedule::class, 'court_id', 'court_id'); }
    public function maintenances() { return $this->hasMany(CourtMaintenance::class, 'court_id', 'court_id'); }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAvailable($query, $date, $startTime, $endTime)
    {
        return $query->active()
            ->whereDoesntHave('bookings', function ($q) use ($date, $startTime, $endTime) {
                $q->where('booking_date', $date)
                  ->whereIn('status', CourtBooking::BLOCKING_STATUSES)
                  ->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->whereDoesntHave('maintenances', function ($q) use ($date, $startTime, $endTime) {
                $q->whereIn('status', ['scheduled', 'in_progress'])
                  ->where('start_datetime', '<', "$date $endTime")
                  ->where('end_datetime', '>', "$date $startTime");
            });
    }
}
```

---

### CourtBooking

```php
class CourtBooking extends Model
{
    use SoftDeletes;
    protected $primaryKey = 'booking_id';

    const BLOCKING_STATUSES = ['pending', 'confirmed', 'checked_in', 'playing', 'extended'];
    const FREE_STATUSES     = ['cancelled', 'completed', 'no_show'];

    protected $fillable = [
        'booking_code', 'user_id', 'staff_id', 'court_id',
        'booking_date', 'start_time', 'end_time', 'duration_minutes',
        'status', 'original_price', 'discount_amount', 'service_amount',
        'total_amount', 'deposit_amount', 'paid_amount',
        'payment_status', 'payment_method',
        'checked_in_at', 'checked_out_at', 'confirmed_at', 'cancelled_at',
        'cancel_reason_type', 'cancel_reason', 'note', 'source',
    ];

    protected $casts = [
        'booking_date'   => 'date',
        'checked_in_at'  => 'datetime',
        'checked_out_at' => 'datetime',
        'confirmed_at'   => 'datetime',
        'cancelled_at'   => 'datetime',
        'original_price' => 'decimal:2',
        'total_amount'   => 'decimal:2',
        'paid_amount'    => 'decimal:2',
        'deposit_amount' => 'decimal:2',
    ];

    public function user()           { return $this->belongsTo(User::class, 'user_id', 'user_id'); }
    public function staff()          { return $this->belongsTo(Admin::class, 'staff_id', 'admin_id'); }
    public function court()          { return $this->belongsTo(Court::class, 'court_id', 'court_id'); }
    public function statusHistories(){ return $this->hasMany(CourtBookingStatusHistory::class, 'booking_id', 'booking_id'); }
    public function services()       { return $this->hasMany(CourtBookingService::class, 'booking_id', 'booking_id'); }
    public function payments()       { return $this->hasMany(CourtBookingPayment::class, 'booking_id', 'booking_id'); }
    public function extensions()     { return $this->hasMany(CourtBookingExtension::class, 'booking_id', 'booking_id'); }

    public function scopeOccupying($query)              { return $query->whereIn('status', self::BLOCKING_STATUSES); }
    public function scopeToday($query)                  { return $query->whereDate('booking_date', today()); }
    public function scopeUpcoming($query)               { return $query->where('booking_date', '>=', today())->whereIn('status', ['pending', 'confirmed']); }
    public function scopeBetweenTime($query, $s, $e)    { return $query->where('start_time', '<', $e)->where('end_time', '>', $s); }
    public function scopeForCourt($query, $courtId, $d) { return $query->where('court_id', $courtId)->where('booking_date', $d); }
}
```

---

### CourtPrice

```php
class CourtPrice extends Model
{
    protected $primaryKey = 'price_id';
    protected $fillable = ['court_id', 'price_name', 'day_type', 'from_time', 'to_time', 'price_per_hour', 'is_active', 'effective_from', 'effective_to'];
    protected $casts    = ['price_per_hour' => 'decimal:2', 'is_active' => 'boolean', 'effective_from' => 'datetime', 'effective_to' => 'datetime'];

    public function court() { return $this->belongsTo(Court::class, 'court_id', 'court_id'); }

    public function scopeActive($query) { return $query->where('is_active', true); }

    public function scopeForDateTime($query, $dayType, $time)
    {
        return $query->active()
            ->where(fn($q) => $q->where('day_type', $dayType)->orWhere('day_type', 'all'))
            ->where('from_time', '<=', $time)
            ->where('to_time', '>', $time)
            ->where(fn($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()));
    }
}
```

---

### CourtBookingPayment

```php
class CourtBookingPayment extends Model
{
    protected $primaryKey = 'court_payment_id';
    protected $fillable   = ['booking_id', 'payment_type', 'payment_method', 'transaction_code', 'amount', 'status', 'paid_at', 'gateway_response', 'note', 'processed_by'];
    protected $casts      = ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'gateway_response' => 'array'];

    public function booking()     { return $this->belongsTo(CourtBooking::class, 'booking_id', 'booking_id'); }
    public function processedBy() { return $this->belongsTo(Admin::class, 'processed_by', 'admin_id'); }

    public function scopeSuccessful($query) { return $query->where('status', 'success'); }
    public function scopeDeposit($query)    { return $query->where('payment_type', 'deposit'); }
}
```

---

## 10. ERD Dạng Text

```
users (user_id PK)
  ├── 1:N ──→ court_bookings (user_id FK)
  └── 1:N ──→ court_booking_locks (user_id FK)

admins (admin_id PK)
  ├── 1:N ──→ court_bookings (staff_id FK)
  ├── 1:N ──→ court_booking_payments (processed_by FK)
  ├── 1:N ──→ court_booking_services (added_by FK)
  ├── 1:N ──→ court_booking_extensions (approved_by FK)
  └── 1:N ──→ court_maintenances (created_by FK)

courts (court_id PK)
  ├── 1:N ──→ court_bookings (court_id FK)
  ├── 1:N ──→ court_schedules (court_id FK)
  ├── 1:N ──→ court_prices (court_id FK)
  ├── 1:N ──→ court_maintenances (court_id FK)
  └── 1:N ──→ court_booking_locks (court_id FK)

court_bookings (booking_id PK)
  ├── N:1 ──→ users (user_id)
  ├── N:1 ──→ admins (staff_id)
  ├── N:1 ──→ courts (court_id)
  ├── 1:N ──→ court_booking_status_histories (booking_id FK)
  ├── 1:N ──→ court_booking_services (booking_id FK)
  ├── 1:N ──→ court_booking_payments (booking_id FK)
  └── 1:N ──→ court_booking_extensions (booking_id FK)

court_booking_services (booking_service_id PK)
  ├── N:1 ──→ court_bookings (booking_id)
  └── N:1 ──→ court_services (service_id)

court_services (service_id PK)
  └── 1:N ──→ court_booking_services (service_id FK)

notifications (uuid PK) [HIỆN CÓ — POLYMORPHIC]
  └── Dùng để gửi thông báo booking đến users
```

---

## 11. Index và Tối Ưu Query

### Index bắt buộc (Critical Path)

```sql
-- Overlap check — dùng nhiều nhất
INDEX idx_booking_overlap ON court_bookings (court_id, booking_date, start_time, end_time, status);

-- Dashboard lịch sân hôm nay
INDEX idx_booking_date_status ON court_bookings (booking_date, status);

-- Lịch sử booking của user
INDEX idx_booking_user_status ON court_bookings (user_id, status);

-- Lock còn hiệu lực
INDEX idx_lock_overlap ON court_booking_locks (court_id, booking_date, start_time, end_time, expires_at);

-- Cleanup job
INDEX idx_lock_cleanup ON court_booking_locks (expires_at);
```

### Index quan trọng (Performance)

```sql
INDEX idx_price_lookup ON court_prices (court_id, day_type, is_active);
INDEX idx_maintenance_overlap ON court_maintenances (court_id, start_datetime, end_datetime, status);
INDEX idx_activity_subject ON court_activity_logs (subject_type, subject_id);
INDEX idx_activity_actor ON court_activity_logs (actor_type, actor_id);
```

### Query dashboard realtime "Lịch sân hôm nay"

```php
CourtBooking::with(['user:user_id,full_name,phone', 'court:court_id,court_name,court_code'])
    ->whereDate('booking_date', today())
    ->whereIn('status', ['confirmed', 'checked_in', 'playing', 'pending', 'extended'])
    ->orderBy('court_id')
    ->orderBy('start_time')
    ->get();
```

---

## 12. Rủi Ro Khi Triển Khai

| Rủi ro | Mức độ | Biện pháp |
|--------|--------|-----------|
| Race condition 2 user đặt cùng lúc 1 sân | 🔴 CAO | `DB::transaction` + `lockForUpdate()` bắt buộc |
| Booking lock tồn đọng nếu job cleanup lỗi | 🟡 TRUNG BÌNH | Schedule cleanup 5 phút + check `expires_at` trong query |
| Timezone bug khi lưu `date` + `time` riêng | 🟡 TRUNG BÌNH | Luôn theo `Asia/Ho_Chi_Minh`, set `APP_TIMEZONE` trong `.env` |
| 2 bảng payment song song gây nhầm lẫn | 🟡 TRUNG BÌNH | Document rõ ràng; refactor sau khi ổn định |
| `admin_id` vs `user_id` nhầm context | 🟡 TRUNG BÌNH | FK đúng: `user_id → users`, `staff_id → admins` |
| Sửa ENUM sau deploy | 🟢 THẤP | Dùng `DB::statement ALTER TABLE MODIFY` như dự án đã làm |
| Soft delete + data lớn gây query chậm | 🟢 THẤP | Index `deleted_at`; archive định kỳ |
| Realtime không đồng bộ web–mobile | 🟡 TRUNG BÌNH | Dùng `notifications` + Laravel Echo/Pusher/Soketi |

---

## 13. Checklist Task Triển Khai

### P0 — Bắt buộc (Sprint 1)

- [ ] Migration: `courts`
- [ ] Migration: `court_schedules`
- [ ] Migration: `court_prices`
- [ ] Migration: `court_bookings` (với đầy đủ index)
- [ ] Migration: `court_booking_status_histories`
- [ ] Migration: `court_booking_locks`
- [ ] Migration: `court_booking_payments`
- [ ] Seeder: 7 sân (COURT-01 → COURT-07)
- [ ] Seeder: Lịch hoạt động mặc định (T2–CN, 06:00–22:00)
- [ ] Seeder: Bảng giá mặc định
- [ ] Model: `Court`, `CourtSchedule`, `CourtPrice`, `CourtBooking`
- [ ] Model: `CourtBookingStatusHistory`, `CourtBookingLock`, `CourtBookingPayment`
- [ ] Service: `CourtBookingService::createBooking()` — transaction + lockForUpdate
- [ ] Service: `ConflictCheckService` — overlap booking + maintenance + lock
- [ ] Job: `CleanExpiredBookingLocks` — schedule mỗi 5 phút
- [ ] API: `GET /api/courts`
- [ ] API: `GET /api/courts/{id}/availability?date=&start=&end=`
- [ ] API: `POST /api/court-booking-locks` — giữ slot tạm
- [ ] API: `DELETE /api/court-booking-locks/{token}` — release lock
- [ ] API: `POST /api/court-bookings` — tạo booking
- [ ] API: `GET /api/court-bookings/{id}` — chi tiết
- [ ] API: `PATCH /api/court-bookings/{id}/confirm`
- [ ] API: `PATCH /api/court-bookings/{id}/check-in`
- [ ] API: `PATCH /api/court-bookings/{id}/check-out`
- [ ] API: `PATCH /api/court-bookings/{id}/cancel`

### P1 — Quan trọng (Sprint 2)

- [ ] Migration: `court_services`
- [ ] Migration: `court_booking_services`
- [ ] Migration: `court_maintenances`
- [ ] Migration: `court_booking_extensions`
- [ ] Model: `CourtService`, `CourtBookingService`, `CourtMaintenance`, `CourtBookingExtension`
- [ ] API: CRUD `court_services`
- [ ] API: `POST /api/court-bookings/{id}/services` — thêm dịch vụ
- [ ] API: CRUD `court_maintenances`
- [ ] API: `POST /api/court-bookings/{id}/extend` — gia hạn
- [ ] Tích hợp VNPay/Momo cho `court_booking_payments`
- [ ] Gửi notification booking: confirm + reminder 30 phút trước
- [ ] API Admin: `GET /api/admin/courts/today-schedule` — dashboard realtime

### P2 — Mở rộng (Sprint 3)

- [ ] Migration: `court_activity_logs`
- [ ] Model: `CourtActivityLog`
- [ ] Middleware tự động ghi activity log
- [ ] API Báo cáo: Doanh thu theo sân / ngày / tháng
- [ ] API Báo cáo: Hiệu suất sử dụng sân (% giờ đặt / tổng giờ)
- [ ] API Báo cáo: Top dịch vụ phát sinh
- [ ] Logic hoàn tiền tự động khi huỷ (theo policy)
- [ ] FCM push notification cho Flutter mobile

### P3 — Sau này (Backlog)

- [ ] Đặt sân định kỳ (weekly recurring booking)
- [ ] Đặt trước nhiều ngày (bulk booking)
- [ ] Tích lũy điểm khi đặt sân (`reward_points` trong `users`)
- [ ] Refactor `payments` sang polymorphic (Phương án B)
- [ ] Export lịch sân ra PDF/Excel
- [ ] Tích hợp Google Calendar reminder
- [ ] Waitlist khi sân kín
- [ ] Đánh giá sân sau khi chơi

---

*Tài liệu này dựa trên audit thực tế toàn bộ 59 migration và 34 model của dự án DATN_OCEAN tại thời điểm 2026-05-27. Không đoán mò — tất cả quyết định đều dựa trên codebase thực tế.*
