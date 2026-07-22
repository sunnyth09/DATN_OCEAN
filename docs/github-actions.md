# Hướng dẫn Triển khai CI/CD (GitHub Actions & SSH Deploy)

Tài liệu này cung cấp toàn bộ thông tin về quy trình CI/CD cho dự án bằng GitHub Actions và appleboy/ssh-action.

## 1. Kiến trúc & Luồng hoạt động

Dự án được chia làm 2 thành phần chính: **backend** (Laravel 12) và **frontend** (Vue 3 + Vite).
Luồng hoạt động của CI/CD (Workflow `deploy.yml`) diễn ra theo các bước sau mỗi khi có code push lên nhánh `main`:

1. **Checkout Code:** Lấy mã nguồn mới nhất từ GitHub.
2. **Backend (Laravel):**
   - Setup PHP 8.3.
   - Cài đặt các gói bằng `composer install`.
   - Chạy Laravel Pint để kiểm tra cú pháp (không thay đổi code).
   - Chạy PHPUnit để test các chức năng.
3. **Frontend (Vue):**
   - Setup Node.js 20.
   - Cài đặt dependencies bằng `npm ci`.
   - Build source code ra thư mục `dist` bằng `npm run build`.
   - Upload thư mục `dist` thành Artifact trên GitHub Actions để lưu trữ.
4. **Deploy Frontend:** Dùng SCP action để copy thư mục `dist` của frontend đẩy lên `PROJECT_PATH/backend/public` trên Server.
5. **Deploy Backend (SSH):** Kết nối SSH vào server để:
   - `git pull origin main` (kéo code backend).
   - `composer install --no-dev`.
   - Chạy `php artisan migrate --force`.
   - Chạy `php artisan optimize:clear` và `php artisan optimize`.
   - Tự động restart queue / reverb (nếu có).

## 2. Các Secret cần thiết trên GitHub

Để bảo mật, chúng ta tuyệt đối không lưu SSH key hay mật khẩu vào source code. Tất cả các giá trị nhạy cảm được cấu hình thông qua **GitHub Secrets**.

| Secret Name | Ý nghĩa / Mô tả | Ví dụ |
|-------------|----------------|-------|
| `HOST` | Địa chỉ IP hoặc Domain của Server (Hosting cPanel/VPS). | `123.45.67.89` hoặc `vietnix.com` |
| `PORT` | Cổng kết nối SSH của Server. | `22` hoặc `2222` |
| `USERNAME` | Tên người dùng SSH (hoặc username cPanel). | `root` hoặc `cpanel_user` |
| `SSH_KEY` | Private Key để đăng nhập SSH (Bắt đầu bằng `-----BEGIN OPENSSH PRIVATE KEY-----`). | (Nội dung key) |
| `PROJECT_PATH` | Đường dẫn tuyệt đối đến thư mục chứa toàn bộ dự án trên server. | `/home/user/public_html/qs_project` |

## 3. Hướng dẫn Thao tác tay (Checklist Cài đặt)

**Bước 1: Thiết lập SSH Key trên Server**
- Truy cập vào Server (VPS/cPanel) qua terminal.
- Chạy lệnh `ssh-keygen -t rsa -b 4096 -C "deploy@github"` (không nhập passphrase).
- Copy nội dung file Public Key (`~/.ssh/id_rsa.pub`) và thêm vào file `~/.ssh/authorized_keys` trên chính Server đó.
- Copy nội dung file Private Key (`~/.ssh/id_rsa`) để dùng cho Bước 3.

**Bước 2: Chuẩn bị Repository trên GitHub**
- Push toàn bộ mã nguồn hiện tại lên GitHub (bao gồm file `.github/workflows/deploy.yml` mới tạo).

**Bước 3: Cấu hình GitHub Secrets**
- Mở kho lưu trữ (Repository) trên GitHub.
- Đi tới **Settings** > **Secrets and variables** > **Actions**.
- Nhấn nút **New repository secret**.
- Lần lượt thêm các Secret: `HOST`, `PORT`, `USERNAME`, `SSH_KEY`, `PROJECT_PATH` với giá trị tương ứng. _Lưu ý nội dung `SSH_KEY` copy toàn bộ từ `-----BEGIN...` đến `...END-----`._

**Bước 4: Kiểm tra SSH thủ công (Tùy chọn)**
- Thử kết nối SSH bằng Private Key từ máy tính cá nhân để đảm bảo key hoạt động tốt trước khi chạy CI/CD.

**Bước 5: Kích hoạt Workflow**
- Thực hiện 1 commit mới (ví dụ sửa file README.md) và push lên nhánh `main`.
- Đi tới tab **Actions** trên GitHub để xem tiến trình chạy của Workflow.
- Nếu các icon màu xanh lá cây hiện ra, quá trình deploy đã thành công.

## 4. Quy trình Rollback khi gặp lỗi

Nếu việc triển khai (Deploy) làm lỗi Production, thực hiện các bước sau để khôi phục:

### Cách 1: Rollback bằng Git (Khuyên dùng)
- Mở Terminal trên máy tính, quay lại commit trước khi lỗi:
  ```bash
  git revert <commit-id-lỗi>
  git push origin main
  ```
- Workflow GitHub Actions sẽ tự động chạy lại và deploy phiên bản ổn định lên Server.

### Cách 2: Rollback bằng Tag/Release
- Nếu bạn có thói quen đánh Tag (Release) trên GitHub, bạn có thể sửa luồng Actions chỉ chạy khi tạo Release.
- Khi có lỗi, đăng nhập vào Server bằng SSH, chạy lệnh:
  ```bash
  cd $PROJECT_PATH
  git fetch --tags
  git checkout <tag-phiên-bản-cũ>
  composer install --no-dev
  php artisan optimize:clear
  ```

### Cách 3: Rollback thủ công
- SSH trực tiếp vào Server, sử dụng `git checkout <commit-id-ổn-định>`.
- Chạy lại các lệnh artisan để khôi phục hệ thống (cache, view, route).
- _Lưu ý: Nếu có lỗi liên quan đến Database, bạn cần khôi phục Database từ bản Backup gần nhất trên cPanel._

## 5. Cảnh báo Bảo mật & Debug

### 5.1. Các tệp tin bị cấm upload
Hãy kiểm tra file `.gitignore` đảm bảo các thành phần sau **KHÔNG** được đẩy lên Git:
- `.env`
- Thư mục `vendor/` (Backend)
- Thư mục `node_modules/` (Frontend)
- Thư mục `storage/logs/` (hoặc các file dữ liệu nhạy cảm của storage)
- Thư mục `.idea`, `.vscode/`

### 5.2. Cách Debug lỗi Deploy
- **Lỗi SSH/SCP (Authentication failed):** Kiểm tra lại `SSH_KEY` và `USERNAME`. Đảm bảo Public Key đã được nạp đúng cách vào `authorized_keys`.
- **Lỗi Migration:** Có thể do schema database bị xung đột, hãy kiểm tra lại file migration cuối cùng, truy cập log bằng lệnh: `tail -f storage/logs/laravel.log`.
- **Lỗi Composer Install:** Đảm bảo Server đang chạy đúng phiên bản PHP 8.3 và các extension (mbstring, xml...) đã bật.
