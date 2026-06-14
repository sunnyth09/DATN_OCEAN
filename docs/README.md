# Ocean Project

Dự án này là một Boilerplate được khởi tạo hoàn toàn sạch sẽ, tách biệt làm hai khối kiến trúc chính:

- **Backend**: API RESTful xây dựng bằng Laravel 11.
- **Frontend**: Single Page Application (SPA) xây dựng bằng Vue 3 + Vite.

Toàn bộ hệ thống được chứa chung trong một kho lưu trữ (Monorepo) và được quản lý việc khởi chạy thông qua **Docker Compose**. Bạn không cần phải cài đặt PHP hay Node.js trên máy cục bộ để làm việc với dự án này.

---

## 🚀 Hướng Dẫn Cài Đặt Dành Cho Thành Viên Mới

Làm theo các bước sau để thiết lập môi trường phát triển (Local Development) trên máy của bạn.

### Yêu Cầu Hệ Thống

- Máy tính đã cài đặt [Docker](https://docs.docker.com/get-docker/) và Docker Compose.

### Bước 1: Clone dự án

Clone kho lưu trữ này về máy:

```bash
git clone <URL_CUA_REPO>
cd ocean-php3
```

### Bước 2: Khởi tạo biến môi trường (.env)

Dự án sử dụng **1 file `.env` duy nhất ở thư mục gốc (Root)** để quản lý biến môi trường cho cả Backend và Frontend.
Chạy lệnh sau để nhân bản file mẫu `env.example`:

```bash
cp .env.example .env
```

Mở file `.env` vừa tạo và điền các thông số liên kết Docker mặc định như sau (rất quan trọng để các container nhận diện nhau):

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=ocean_db
DB_USERNAME=root
DB_PASSWORD=root
```

### Bước 3: Khởi động hệ thống Docker

Chỉ cần chạy lệnh sau tại thư mục gốc của dự án:

```bash
docker compose up -d
```

Lệnh này sẽ tải các image cần thiết (MySQL, PHP, Node) và dựng lên 3 container:

1. `ocean_mysql`: Dịch vụ Cơ sở dữ liệu.
2. `ocean_backend`: Server PHP nội bộ phát API cho Laravel.
3. `ocean_frontend`: Node.js dev server chạy môi trường Vite của Vue.

### Bước 4: Cài đặt Dependency & Database cho Backend

Vì thư mục vendor chưa có, và Database mới trắng tinh, bạn cần chui vào Container `backend` vừa tạo để chạy lệnh cài đặt Composer và Migrate Database của Laravel:

```bash
# Cài đặt thư viện Backend
docker compose exec backend composer install

# Sinh mã khóa bảo mật
docker compose exec backend php artisan key:generate

# Khởi tạo các bảng vào cấu trúc Database
docker compose exec backend php artisan migrate --seed
```

---

## 🌐 Các Cổng Truy Cập (Ports)

Sau khi hệ thống khởi chạy thành công, bạn có thể xem kết quả trực tiếp trên trình duyệt hoặc dùng Postman thông qua các đường dẫn nội bộ (Localhost) như sau:

| Dịch Vụ          | Công Nghệ    | Đường dẫn truy cập cục bộ                              |
| ---------------- | ------------ | ------------------------------------------------------ |
| **Frontend SPA** | Vue 3 (Vite) | [http://localhost:3302](http://localhost:3302)         |
| **Backend API**  | Laravel 11   | [http://localhost:8383/api](http://localhost:8383/api) |
| **Database**     | MySQL 8.0    | `localhost:8306` (Dùng cho HeidiSQL)                   |

---

## 🛠 Cách Giao Tiếp Giữa Front-End và Back-End

Quá trình chia tách này yêu cầu các logic cũ (như trả về View bằng Inertia.js) bị xoá bỏ hoàn toàn.

- **Frontend** chịu trách nhiệm gọi API (sử dụng thư viện `Axios`).
- **Backend** chịu trách nhiệm tính toán logic và Data, trả về bằng định dạng **JSON** theo cấu trúc Route được khai báo trong `backend/routes/api.php` (Thay vì `web.php` như cũ).

Quá trình truy vấn bảo mật giữa 2 khối được thực hiện và chứng thực chéo thông qua kiến trúc của Laravel Sanctum.

## 📦 Cài Đặt Các Thư Viện Bổ Sung (Nâng Cao)

Nếu dự án yêu cầu thêm các module hoặc cấu hình xác thực, dưới đây là các lệnh bổ sung đã được tích hợp trong hệ thống:

### 1. Frontend: Cài đặt hệ thống thông báo (SweetAlert2)

Để cài đặt `sweetalert2` cho giao diện người dùng, hãy chạy lệnh sau thông qua container Frontend:

```bash
docker compose exec frontend npm install sweetalert2
```

### 2. Backend: Cài đặt hệ thống Xác thực (JWT Auth & Sanctum)

Dự án sử dụng xác thực API bằng JWT (JSON Web Token) và Sanctum. Để đảm bảo mọi thứ hoạt động tốt hoặc khi cần khởi tạo lại cấu hình, bạn có thể tham khảo các lệnh sau:

**Khởi tạo cấu hình cho Laravel Sanctum:**

```bash
docker compose exec backend php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
docker compose exec backend php artisan migrate
```

**Tích hợp và tạo Secret Key cho JWT Auth:**

```bash
docker compose exec backend composer require php-open-source-saver/jwt-auth
docker compose exec backend php artisan jwt:secret
```
