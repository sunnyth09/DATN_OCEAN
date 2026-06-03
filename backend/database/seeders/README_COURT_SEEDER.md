# 📋 Court Booking Seeder — Dữ Liệu Demo Sân Cầu Lông

## 1. Danh sách Seeder đã tạo

| Seeder | Mô tả |
|--------|-------|
| `CourtSeeder` | 7 sân cầu lông + lịch hoạt động (court_schedules) + bảng giá (court_prices) |
| `CourtServiceSeeder` | 8 dịch vụ bổ sung (nước, vợt, giày, cầu lông, HLV, khăn...) |
| `CourtMaintenanceSeeder` | 5 lịch bảo trì (đã xong, đang làm, lên lịch, đã hủy) |
| `CourtBookingSeeder` | 40+ booking demo + tài khoản admin/staff/user + thanh toán + status history |

---

## 2. Tài khoản demo

### Admin (bảng `admins`)

| Email | Mật khẩu | Vai trò | Chức năng |
|-------|----------|---------|-----------|
| `court_admin@demo.com` | `password` | admin | Quản lý toàn bộ hệ thống sân |
| `court_staff@demo.com` | `password` | staff | Lễ tân check-in/out, xác nhận đặt sân |

### User (bảng `users`)

| Email | Mật khẩu | Tên |
|-------|----------|-----|
| `nguyen.an@demo.com` | `password` | Nguyễn Văn An |
| `tran.binh@demo.com` | `password` | Trần Thị Bình |
| `le.cuong@demo.com` | `password` | Lê Hoàng Cường |
| `pham.duc@demo.com` | `password` | Phạm Minh Đức |
| `vo.em@demo.com` | `password` | Võ Thanh Em |

> **Lưu ý**: Admin đã có sẵn của hệ thống (`admin123@gmail.com` / `123456`) vẫn hoạt động bình thường.

---

## 3. Dữ liệu đã seed

### 🏟️ 7 Sân cầu lông

| Sân | Mã | Loại | Mặt sân | Trạng thái |
|-----|-----|------|---------|-----------|
| Sân 1 | SAN-01 | standard | Thảm PVC | ✅ active |
| Sân 2 | SAN-02 | standard | Thảm PVC | ✅ active |
| Sân 3 | SAN-03 | vip | Sàn gỗ | ✅ active |
| Sân 4 | SAN-04 | vip | Sàn gỗ | ✅ active |
| Sân 5 | SAN-05 | indoor | Composite | ✅ active |
| Sân 6 | SAN-06 | outdoor | Bê tông phủ cao su | ✅ active |
| Sân 7 | SAN-07 | standard | Thảm PVC | 🔧 maintenance |

### 📅 Lịch hoạt động (court_schedules)

- **49 bản ghi** (7 sân × 7 ngày trong tuần)
- Ngày thường: 06:00 – 22:00
- Cuối tuần (T7, CN): 05:00 – 22:00
- Sân 7: lịch tạo nhưng `is_active = false` (đang bảo trì)

### 💰 Bảng giá (court_prices)

Mỗi sân có 4 mức giá:

| Khung | Day Type | Standard | VIP | Indoor | Outdoor |
|-------|----------|----------|-----|--------|---------|
| 05:00-08:00 | weekday | 64.000đ | 120.000đ | 80.000đ | 56.000đ |
| 08:00-17:00 | weekday | 80.000đ | 150.000đ | 100.000đ | 70.000đ |
| 17:00-22:00 | weekday | 104.000đ | 195.000đ | 130.000đ | 91.000đ |
| 05:00-22:00 | weekend | 96.000đ | 180.000đ | 120.000đ | 84.000đ |

### 🛒 Dịch vụ bổ sung (court_services)

| Dịch vụ | Mã | Đơn vị | Giá | Active |
|---------|-----|--------|-----|--------|
| Nước suối | WATER | bottle | 10.000đ | ✅ |
| Nước tăng lực | ENERGY | bottle | 15.000đ | ✅ |
| Cho thuê vợt | RACKET | set | 30.000đ | ✅ |
| Cầu lông (1 quả) | SHUTTLE | piece | 8.000đ | ✅ |
| Cầu lông (hộp 12) | SHUTTLE-BOX | set | 85.000đ | ✅ |
| Khăn lạnh | TOWEL | piece | 5.000đ | ✅ |
| Cho thuê giày | SHOES | set | 25.000đ | ✅ |
| HLV (1 giờ) | COACH | hour | 200.000đ | ❌ |

### 📋 Booking demo — Đầy đủ trạng thái

| Nhóm | Trạng thái | Số lượng | Ghi chú |
|------|-----------|----------|---------|
| Quá khứ (7 ngày) | `completed` | 21-35 | Đã thanh toán, có check-in/out |
| Hôm nay | `checked_in` | 1 | Đang chơi, đã đặt cọc |
| Hôm nay | `confirmed` | 2 | Chờ đến giờ |
| Hôm nay | `pending` | 2 | Chờ admin xác nhận |
| Quá khứ | `cancelled` | 2 | Khách hủy + CLB hủy |
| Quá khứ | `no_show` | 1 | Không đến |
| Tương lai (7 ngày) | `pending` / `confirmed` | 14-28 | Sắp tới |
| Quá khứ | `cancelled` + refund | 1 | Đã hoàn tiền |

### 💳 Thanh toán (court_booking_payments)

- `full` + `success`: Thanh toán đầy đủ
- `deposit` + `success`: Đặt cọc 50%
- `refund` + `refunded`: Hoàn tiền
- Phương thức: cash, vnpay, momo, bank_transfer

### 🔧 Bảo trì (court_maintenances)

| Sân | Tiêu đề | Trạng thái |
|-----|---------|-----------|
| Sân 1 | Thay thảm PVC | ✅ completed |
| Sân 2 | Sửa hệ thống đèn | ✅ completed |
| Sân 7 | Bảo trì tổng thể | 🔄 in_progress |
| Sân 3 | Kiểm tra sàn gỗ | 📅 scheduled |
| Sân 5 | Sửa quạt thông gió | ❌ cancelled |

---

## 4. Cách chạy Seeder

### Chạy tất cả (bao gồm seeder cũ)

```bash
docker compose exec backend php artisan db:seed
```

### Chạy riêng từng seeder sân cầu lông

```bash
# 1. Tạo 7 sân + lịch + giá
docker compose exec backend php artisan db:seed --class=CourtSeeder

# 2. Tạo dịch vụ
docker compose exec backend php artisan db:seed --class=CourtServiceSeeder

# 3. Tạo lịch bảo trì
docker compose exec backend php artisan db:seed --class=CourtMaintenanceSeeder

# 4. Tạo user demo + booking demo (chạy sau CourtSeeder + CourtServiceSeeder)
docker compose exec backend php artisan db:seed --class=CourtBookingSeeder
```

> ⚠️ Thứ tự quan trọng: `CourtSeeder` → `CourtServiceSeeder` → `CourtMaintenanceSeeder` → `CourtBookingSeeder`

### Chạy lại an toàn

- Tất cả seeder đều dùng **kiểm tra tồn tại** trước khi insert (check `court_code`, `service_code`, `email`...).
- Booking dùng mã `booking_code` unique → nếu chạy lại có thể bị trùng.
- Để reset sạch: `php artisan migrate:fresh --seed`

---

## 5. Bảng database được sử dụng

| Bảng | Seeder |
|------|--------|
| `courts` | CourtSeeder |
| `court_schedules` | CourtSeeder |
| `court_prices` | CourtSeeder |
| `court_services` | CourtServiceSeeder |
| `court_maintenances` | CourtMaintenanceSeeder |
| `admins` | CourtBookingSeeder |
| `users` | CourtBookingSeeder |
| `court_bookings` | CourtBookingSeeder |
| `court_booking_status_histories` | CourtBookingSeeder |
| `court_booking_payments` | CourtBookingSeeder |
| `court_booking_services` | CourtBookingSeeder |

**Bảng KHÔNG được seed** (vì tính chất runtime):
- `court_booking_locks` — tạo tự động khi user giữ slot
- `court_booking_extensions` — tạo khi admin gia hạn
- `court_activity_logs` — tạo tự động khi có thao tác

---

## 6. Luồng test dành cho Admin

### 6.1 Quản lý sân
- [x] Xem danh sách 7 sân → filter theo trạng thái
- [x] Sân 7 hiển thị "Bảo trì" → không cho đặt
- [x] Xem chi tiết sân: loại, mặt sân, giá theo khung giờ

### 6.2 Quản lý booking
- [x] Lọc booking theo trạng thái: pending, confirmed, checked_in, completed, cancelled, no_show
- [x] Lọc theo sân: Sân 1-7
- [x] Lọc theo ngày: 7 ngày trước → 7 ngày sau đều có dữ liệu
- [x] Xác nhận booking pending → confirmed
- [x] Check-in booking confirmed → checked_in
- [x] Check-out → completed
- [x] Hủy booking + nhập lý do

### 6.3 Doanh thu & Thanh toán
- [x] Xem doanh thu theo ngày (booking completed 7 ngày trước)
- [x] Xem thanh toán: cash, vnpay, momo, bank_transfer
- [x] Xem booking đã hoàn tiền (refunded)
- [x] So sánh doanh thu weekday vs weekend

### 6.4 Bảo trì
- [x] Xem lịch bảo trì theo trạng thái
- [x] Sân 7 đang in_progress → slot availability sẽ hiện "bảo trì"

---

## 7. Luồng test dành cho User

### 7.1 Đăng nhập
- Sử dụng email demo: `nguyen.an@demo.com` / `password`

### 7.2 Xem danh sách sân
- [x] 6 sân active hiển thị
- [x] Sân 7 (maintenance) không hiển thị trong danh sách

### 7.3 Đặt sân
- [x] Chọn sân → vào trang chi tiết
- [x] Chọn ngày → API trả về các slot theo giờ (06:00-22:00)
- [x] Slot đã đặt hiện "Đã đặt" (đỏ)
- [x] Slot trống hiện giá (xanh lá)
- [x] Chọn khung giờ → tổng tiền tự động tính
- [x] Chọn dịch vụ bổ sung (nước, vợt, cầu...)
- [x] Chọn phương thức thanh toán
- [x] Nhấn "Đặt Sân Ngay"

### 7.4 Lịch sử đặt sân
- [x] Xem booking đã hoàn thành (completed)
- [x] Xem booking đang chờ (pending/confirmed)
- [x] Xem booking đã hủy + lý do hủy
- [x] Hủy booking pending/confirmed

---

## 8. Đề xuất bổ sung database (nếu cần)

> Hiện tại tất cả bảng đã đủ cho nghiệp vụ quản lý sân cầu lông.
> Không phát hiện thiếu cột hay bảng nào cần bổ sung thêm.

**Gợi ý mở rộng** (không bắt buộc):
- Thêm `discount_code` trên `court_bookings` nếu muốn áp mã giảm giá riêng cho đặt sân
- Thêm bảng `court_reviews` nếu muốn khách đánh giá sân sau khi chơi
- Thêm `recurring_booking` nếu muốn hỗ trợ đặt sân cố định hàng tuần
