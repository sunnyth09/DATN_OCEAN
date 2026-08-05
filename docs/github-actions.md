# Hướng dẫn Triển khai CI/CD (GitHub Actions & SSH Deploy)

Tài liệu này cung cấp toàn bộ thông tin về quy trình CI/CD cho dự án bằng GitHub Actions và appleboy/ssh-action.

## 1. Kiến trúc & Luồng hoạt động

Dự án được chia làm 2 thành phần chính: **backend** (Laravel 12) và **frontend** (Vue 3 + Vite).

Có 2 workflow:

| Workflow | File | Trigger | Nhiệm vụ |
|----------|------|---------|----------|
| CI/CD Pipeline | `ci.yml` | push `main`/`release/*`, PR vào `main`/`production`/`release/*` | Chỉ chạy test (không deploy) |
| Build and Deploy | `deploy.yml` | **push `production`** + `workflow_dispatch` | Test → build → deploy → verify |

> ⚠️ **Chỉ nhánh `production` mới deploy.** Push lên `Dev` KHÔNG deploy.
> Xem [mục 6](#6-sự-cố-actions-báo-xanh-nhưng-web-vẫn-hiển-thị-giao-diện-cũ) để hiểu vì sao **không** dùng trigger `workflow_run`.

Luồng của `deploy.yml` khi push lên `production`:

1. **Job `test`:** Setup PHP 8.2 → `composer install` → Pint (cảnh báo, không chặn) → PHPUnit. Fail thì dừng, không deploy.
2. **Job `build_and_deploy`** (`needs: test`):
   - Checkout đúng commit vừa push (không cần `ref:`).
   - Setup PHP 8.3 + `composer install --no-dev` (backend).
   - Setup Node 20 (có cache npm) → `npm ci` → `npm run build` với các biến `VITE_*`.
   - Ghi `dist/version.json` chứa commit SHA để verify được về sau.
   - Upload artifact `frontend-build-<sha>` (giữ 7 ngày, dùng để rollback nhanh).
   - **SCP** `frontend/dist` lên `/tmp/frontend_dist_<run_id>` trên VPS.
   - **SSH deploy:**
     - Sanity check `index.html` tồn tại (nếu không → abort, không phá dist đang chạy).
     - `git reset --hard <sha>` (backend cùng chính xác commit đó).
     - **Atomic swap** dist: copy vào `dist_<run_id>` rồi `mv` đè lên `dist`.
     - Copy nginx config: `sites-available` dùng `cp -n` (giữ SSL của Certbot), `snippets` dùng `cp -f` (luôn cập nhật policy cache).
     - `nginx -t` **rồi mới** `nginx -s reload`.
     - `docker compose up -d --build` → `artisan optimize` → restart queue/reverb.
   - **Verify:** `curl` `version.json` trên site thật, **FAIL nếu SHA không khớp**.


## 2. Các Secret cần thiết trên GitHub

Để bảo mật, chúng ta tuyệt đối không lưu SSH key hay mật khẩu vào source code. Tất cả các giá trị nhạy cảm được cấu hình thông qua **GitHub Secrets**.

**Secrets** (Settings → Secrets and variables → Actions → *Secrets*) — tên phải khớp chính xác với `deploy.yml`:

| Secret Name | Ý nghĩa / Mô tả | Ví dụ |
|-------------|----------------|-------|
| `VPS_IP` | Địa chỉ IP hoặc domain của VPS. | `123.45.67.89` |
| `VPS_USER` | Tên người dùng SSH. | `root` |
| `VPS_SSH_KEY` | Private Key để đăng nhập SSH (bắt đầu bằng `-----BEGIN OPENSSH PRIVATE KEY-----`). | (Nội dung key) |
| `PROJECT_PATH` | Đường dẫn tuyệt đối tới thư mục dự án trên VPS. | `/home/user/qs_project` |
| `REVERB_APP_KEY` | App key của Laravel Reverb (có giá trị fallback trong workflow). | `ocean_realtime_key_2024` |

**Variables** (cùng trang, tab *Variables*) — đều **tuỳ chọn**, vì workflow đã có giá trị fallback:

| Variable | Mặc định nếu không set |
|----------|------------------------|
| `FRONTEND_URL` | `https://oceansport.bcbdev.id.vn` (dùng cho bước Verify) |
| `VITE_API_URL` | `https://apiocean.bcbdev.id.vn/api` |
| `VITE_BASE_URL` | `https://apiocean.bcbdev.id.vn` |
| `VITE_API_STORAGE` | `https://apiocean.bcbdev.id.vn/storage` |
| `VITE_REVERB_HOST` / `VITE_REVERB_PORT` / `VITE_REVERB_SCHEME` | `apiocean.bcbdev.id.vn` / `443` / `https` |
| `VITE_TURNSTILE_SITE_KEY`, `VITE_GOOGLE_CLIENT_ID` | (xem `deploy.yml`) |

> ⚠️ Port SSH đang **hard-code là 22** trong `deploy.yml`. Nếu VPS dùng port khác, sửa trực tiếp trong workflow hoặc thêm secret `VPS_PORT` rồi thay `port: 22` thành `port: ${{ secrets.VPS_PORT }}`.


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
- Merge code vào nhánh `production` và push (hoặc bấm **Run workflow** ở tab Actions để deploy lại thủ công).
- Đi tới tab **Actions** trên GitHub để xem tiến trình chạy của Workflow.
- Job phải xanh **đến hết bước `Verify deployment is live`** — bước này mới là bằng chứng web đã chạy đúng bản mới.

## 4. Quy trình Rollback khi gặp lỗi

Nếu việc triển khai (Deploy) làm lỗi Production, thực hiện các bước sau để khôi phục:

### Cách 1: Rollback bằng Git (Khuyên dùng)
- Mở Terminal trên máy tính, quay lại commit trước khi lỗi:
  ```bash
  git checkout production
  git revert <commit-id-lỗi>
  git push origin production
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

---

## 6. Sự cố "Actions báo xanh nhưng web vẫn hiển thị giao diện cũ"

### 6.1. Nguyên nhân gốc (đã fix)

Trước đây `deploy.yml` dùng trigger `workflow_run`:

```yaml
on:
  workflow_run:
    workflows: ["CI/CD Pipeline"]
    branches: [production]
```

**GitHub LUÔN thực thi phiên bản của workflow `workflow_run` nằm trên DEFAULT BRANCH** — default branch của repo này là `Dev`.

Hệ quả kép:

1. Mọi sửa đổi `deploy.yml` commit vào nhánh `production` **không bao giờ được chạy**. Hai commit `8a2ae81e` ("Fix GitHub Action checking out wrong branch") và `98d7bd9c` ("auto-clean dist") chỉ tồn tại trên `production` nên đã **không có tác dụng gì**.
2. Bản `deploy.yml` trên `Dev` không có `ref:` ở bước checkout → **build frontend từ code của `Dev`**, trong khi backend lại `git reset --hard origin/production`.

Tại thời điểm phát hiện, `production` đi trước `Dev` **49 file / +4109 dòng** trong `frontend/src`. "Giao diện cũ" chính là giao diện của nhánh `Dev`.

Hai lỗi phụ đi kèm:

- `cp -rn` (no-clobber) copy nginx config → vì `oceansport.conf` đã tồn tại trên VPS nên **fix no-cache cho `index.html` (commit `7f341b26`) chưa từng được áp dụng**.
- `killall -HUP nginx 2>/dev/null || true` reload không kèm `nginx -t` → config sai vẫn báo thành công.

### 6.2. Các thay đổi đã áp dụng

| Vấn đề | Cách fix |
|--------|----------|
| `workflow_run` chạy file từ default branch | Chuyển sang `on: push: branches: [production]`. Trigger `push` luôn chạy đúng file trên commit vừa push → không thể tái diễn |
| Build sai nhánh | Bỏ hẳn `ref:`; `push` đã checkout đúng commit. VPS `git reset --hard ${{ github.sha }}` (thay vì `origin/production`) để backend và frontend chắc chắn cùng một commit |
| Test chạy 2 lần | `ci.yml` bỏ `production` khỏi `push`; `deploy.yml` có job `test` riêng và `build_and_deploy` khai báo `needs: test` |
| Cache config không update được | Tách policy cache ra `nginx/vps/snippets/spa-cache.conf` — CI **luôn `cp -f`** file này. Còn `sites-available/*.conf` vẫn `cp -n` để giữ block SSL 443 của Certbot |
| Reload nginx nuốt lỗi | Chạy `nginx -t` trước, rồi `nginx -s reload`; không còn `|| true` |
| `rm -rf dist/*` gây trang trắng | **Atomic swap**: copy vào `dist_<run_id>` rồi `mv` đè lên `dist`. Kèm sanity check `index.html` tồn tại trước khi thay |
| Không biết bản nào đang chạy | Sinh `dist/version.json` chứa commit SHA; bước **Verify deployment is live** `curl` vào site và **FAIL nếu SHA không khớp** |

Từ nay **"Actions xanh" thực sự đồng nghĩa "web đã chạy đúng commit mới"**.

### 6.3. BẮT BUỘC: chạy 1 lần trên VPS trước lần deploy đầu tiên

Config hiện có trên VPS chưa có dòng `include snippets/spa-cache.conf;`, và CI **không** ghi đè `sites-available` (để bảo vệ SSL). Vì vậy phải thêm thủ công 1 lần:

```bash
ssh <VPS_USER>@<VPS_IP>

# 1. Backup trước khi sửa
sudo cp /etc/nginx/sites-available/oceansport.conf ~/oceansport.conf.bak

# 2. Tạo snippet (CI sẽ tự ghi đè file này ở các lần deploy sau)
sudo mkdir -p /etc/nginx/snippets
cd <PROJECT_PATH> && git fetch origin production && git checkout origin/production -- nginx/vps/snippets
sudo cp nginx/vps/snippets/spa-cache.conf /etc/nginx/snippets/

# 3. Xoá các location block cũ trong oceansport.conf và thay bằng include.
#    Mở editor, trong MỖI server block (cả :80 và :443 do Certbot tạo):
#      - XOÁ các block: location = /index.html, location /, location ~* \.(js|css...)$, location ~ /\.
#      - THÊM 1 dòng:   include snippets/spa-cache.conf;
#    GIỮ NGUYÊN toàn bộ dòng ssl_certificate / listen 443 của Certbot.
sudo nano /etc/nginx/sites-available/oceansport.conf

# 4. Kiểm tra rồi reload
sudo nginx -t && sudo systemctl reload nginx

# 5. Xác nhận header index.html đã no-store
curl -sI https://oceansport.bcbdev.id.vn/ | grep -i cache-control
# Kỳ vọng: cache-control: no-store, no-cache, must-revalidate, max-age=0
```

> Nếu `nginx -t` báo lỗi: `sudo cp ~/oceansport.conf.bak /etc/nginx/sites-available/oceansport.conf && sudo systemctl reload nginx`

### 6.4. Kiểm tra sau mỗi lần deploy

```bash
# Commit đang chạy thật trên web
curl -s https://oceansport.bcbdev.id.vn/version.json

# So với commit mới nhất của production
git rev-parse origin/production
```

Hai giá trị phải khớp. Bước `Verify deployment is live` trong workflow đã tự động kiểm tra việc này.

### 6.5. Lưu ý về default branch

Default branch vẫn là `Dev` (theo yêu cầu). Kiến trúc mới **không phụ thuộc default branch** nên điều này không còn gây lỗi. Tuy vậy cần nhớ:

- Muốn deploy production → **merge vào `production` và push**. Push lên `Dev` không deploy.
- `production` hiện đi trước `Dev` khá nhiều. Nên merge `production` về `Dev` để hai nhánh không phân kỳ tiếp:
  ```bash
  git checkout Dev && git merge origin/production && git push origin Dev
  ```

