# Affiliate Flow Implementation

## 1. Mục tiêu của flow

Tài liệu này mô tả flow hoàn chỉnh để triển khai Affiliate cho dự án hiện tại theo hướng:

- user A chia sẻ link sản phẩm có gắn mã giới thiệu
- user B bấm link khi chưa đăng nhập vẫn được lưu attribution
- sau khi B đăng nhập / đăng ký / thêm giỏ / checkout, hệ thống vẫn giữ mã giới thiệu
- khi B mua hàng thành công, A được ghi nhận commission

Flow này được thiết kế để khớp với kiến trúc hiện tại của repo:

- frontend Vue
- router client riêng
- backend Laravel API
- order lifecycle qua `OrderController` và `AdminOrderController`
- payment qua VNPay / MoMo / COD
    
---

## 2. Luồng tổng thể

```text
User A lấy link affiliate
-> gửi link sản phẩm cho User B
-> User B bấm link có ?ref=...
-> Frontend lưu referral vào localStorage/cookie
-> Nếu B chưa login thì vẫn giữ referral
-> B login hoặc register
-> Frontend gửi referral code lên backend để bind
-> B thêm sản phẩm vào giỏ / checkout / tạo order
-> Backend snapshot referral vào order conversion
-> Admin xử lý đơn đến completed
-> System release commission cho A
```

---

## 3. Link affiliate nên dùng

## 3.1. Format link

Nên dùng link sản phẩm thay vì chỉ gửi mã text.

Ví dụ:

```text
/product/123?ref=AFF123
```

hoặc:

```text
/product/ao-bong-da-nike?ref=AFF123
```

Có thể mở rộng thêm campaign:

```text
/product/ao-bong-da-nike?ref=AFF123&campaign=tiktok_kol_01
```

## 3.2. Vì sao dùng link tốt hơn mã tay

- user chỉ cần bấm, không phải nhớ mã
- conversion cao hơn
- biết được user vào từ sản phẩm nào
- dễ tracking hiệu quả affiliate
- phù hợp hành vi mua hàng thực tế

---

## 4. Flow chi tiết theo từng bước

## Bước 1. User A lấy link affiliate

### Mục tiêu

Cho phép user A có link riêng để chia sẻ.

### Dữ liệu cần có

Mỗi user cần có:

- `referral_code`

Ví dụ:

```text
AFF123
```

### Frontend hiển thị

Trong `/profile/affiliate`:

- mã giới thiệu
- nút copy link trang chủ
- nút copy link sản phẩm hiện tại

### Link tạo ra

Ví dụ:

```text
https://your-site.com/product/ao-bong-da-nike?ref=AFF123
```

---

## Bước 2. User B bấm link khi chưa đăng nhập

### Mục tiêu

Lưu được referral ngay cả khi user chưa có tài khoản hoặc chưa login.

### Cách làm

Khi frontend vào bất kỳ route nào, kiểm tra query string:

- nếu có `ref`
- validate sơ bộ
- lưu localStorage

### Dữ liệu nên lưu ở frontend

Key gợi ý:

```text
affiliate_ref
```

Giá trị:

```json
{
  "code": "AFF123",
  "landing_url": "/product/ao-bong-da-nike?ref=AFF123",
  "product_path": "/product/ao-bong-da-nike",
  "captured_at": 1770000000000,
  "expires_at": 1772592000000,
  "source": "product_link"
}
```

### TTL khuyến nghị

- 30 ngày

### Lưu ý

- dùng `localStorage` để qua được nhiều tab và sau khi refresh vẫn còn
- có thể mirror thêm cookie nếu sau này cần SSR/tracking backend sớm hơn

---

## Bước 3. Ghi nhớ nơi cần quay lại sau login

### Mục tiêu

Nếu user chưa login mà bấm mua hoặc checkout, sau login phải quay lại đúng chỗ.

### Cách làm

Trước khi redirect sang `/client/login`, lưu:

```text
redirect_after_login
```

Ví dụ:

```text
/product/ao-bong-da-nike?ref=AFF123
```

hoặc:

```text
/checkout
```

### Nơi nên lưu

- `sessionStorage`

### Vì sao dùng sessionStorage

- redirect path chỉ cần sống trong session hiện tại
- tránh lưu quá lâu các đường dẫn cũ

---

## Bước 4. User B login hoặc register

## 4.1. Nếu user register mới

### Flow

1. User mở form register
2. Frontend đọc `affiliate_ref` từ localStorage
3. Nếu còn hạn, gửi kèm `referral_code`
4. Backend validate code
5. Backend bind user mới với referrer

### Payload gợi ý

```json
{
  "full_name": "Nguyen Van B",
  "email": "b@example.com",
  "password": "secret",
  "referral_code": "AFF123"
}
```

### Backend xử lý

Trong `AuthController@register`:

1. Tạo user
2. Nếu có `referral_code`:
   - tìm user sở hữu code
   - chặn self-referral
   - gán `referred_by_user_id`
   - tạo record `referrals`

### Kết quả

Quan hệ A giới thiệu B đã được chốt ngay ở bước register.

---

## 4.2. Nếu user đã có tài khoản và login

### Vấn đề

User có thể:

- bấm link affiliate khi chưa login
- sau đó login bằng tài khoản cũ

### Hướng xử lý khuyến nghị

Sau login thành công:

1. frontend đọc `affiliate_ref`
2. gọi API bind referral nếu user chưa từng có referrer
3. backend quyết định có bind hay không

### API gợi ý

```text
POST /profile/affiliate/bind
```

Payload:

```json
{
  "referral_code": "AFF123"
}
```

### Rule backend

Chỉ bind nếu:

- user chưa có `referred_by_user_id`
- user chưa từng có order completed nếu bạn áp dụng rule “chỉ user mới”
- code referral hợp lệ

### Không bind nếu

- user đã có referrer
- user đã là khách cũ
- code là của chính user đó

---

## Bước 5. Quay lại đúng trang sau login

### Mục tiêu

User đăng nhập xong không bị mất flow mua hàng.

### Cách làm

Sau login success:

1. đọc `redirect_after_login`
2. nếu có thì `router.push(redirect_after_login)`
3. nếu không có thì về home hoặc profile

### Kết quả mong muốn

Ví dụ:

```text
User vào /product/ao-bong-da-nike?ref=AFF123
-> click mua
-> bị chuyển sang /client/login
-> login xong quay lại /product/ao-bong-da-nike?ref=AFF123
```

Lúc này:

- referral vẫn còn trong localStorage
- user tiếp tục add to cart bình thường

---

## Bước 6. User thêm vào giỏ hàng

### Mục tiêu

Không cần gắn referral ở cart item.

### Cách làm đúng

Ở phase đầu:

- không cần gắn `referral_code` vào từng cart item
- chỉ cần giữ referral ở user level hoặc session level

### Vì sao

Commission cuối cùng được quyết định theo order, không phải cart item.

Nếu gắn quá sớm vào cart item:

- dữ liệu phức tạp hơn
- chưa cần thiết

### Khuyến nghị

Chỉ snapshot referral ở lúc tạo order.

---

## Bước 7. User checkout và tạo order

### Mục tiêu

Đơn hàng phải mang attribution affiliate rõ ràng.

### Điểm móc hiện tại

Repo đang tạo order ở:

- `backend/app/Http/Controllers/OrderController.php`

### Flow backend nên làm

Khi `OrderController@store` tạo order thành công:

1. lấy `user_id`
2. tra `referred_by_user_id` hoặc `referrals` active của user
3. kiểm tra order này có đủ điều kiện affiliate không
4. tạo record `affiliate_conversion`

### Dữ liệu nên snapshot vào conversion

- `referrer_user_id`
- `referred_user_id`
- `order_id`
- `order_code`
- `order_subtotal`
- `order_discount_amount`
- `order_grand_total`
- `commission_base_amount`
- `commission_rate`
- `commission_amount`
- `status = pending`

### Công thức gợi ý

```text
commission_base_amount = subtotal sau giảm giá, không gồm shipping
commission_amount = commission_base_amount * commission_rate
```

### Chưa release ở bước này

Order mới tạo chỉ là:

- `conversion pending`

Chưa cộng tiền ngay.

---

## Bước 8. Payment success

### Mục tiêu

Biết đơn đã thanh toán nhưng chưa release commission quá sớm.

### Với online payment

Luồng hiện tại đã có:

- `VNPayController`
- `MoMoController`

### Nên làm gì ở bước này

Có thể update `affiliate_conversion` bằng thông tin:

- payment passed
- payment_status = paid

Nhưng vẫn nên giữ:

- `status = pending`

### Vì sao

Thanh toán xong chưa có nghĩa là đơn hoàn tất.

Vẫn còn các case:

- hủy đơn
- hoàn hàng
- hoàn tiền

---

## Bước 9. Admin xử lý đơn hàng đến completed

### Mục tiêu

Đây là thời điểm chính để release commission.

### Điểm móc hiện tại

Repo đang xử lý lifecycle đơn ở:

- `backend/app/Http/Controllers/AdminOrderController.php`

### Rule khuyến nghị

Khi:

- `fulfillment_status = completed`

thì:

- nếu conversion đang `pending`
- chuyển sang `approved` hoặc `released`

### Khuyến nghị trạng thái

Đơn giản nhất:

- `pending` -> `released`

Chuẩn hơn:

- `pending` -> `approved`
- sau holding days -> `released`

### Nếu muốn ra nhanh

MVP có thể dùng:

- `completed` là release luôn

---

## Bước 10. Ghi ví affiliate

### Nếu bạn dùng percent commission thật

Khi conversion được release:

1. tạo transaction ví
2. tăng số dư affiliate khả dụng

### Dữ liệu transaction

- `user_id`
- `conversion_id`
- `type = credit`
- `amount`
- `balance_before`
- `balance_after`
- `description`

### Lưu ý quan trọng

Không nên chỉ cập nhật một cột `current_balance` mà không có transaction log.

Phải có ledger để:

- audit
- xử lý dispute
- reverse commission

---

## Bước 11. Xử lý đơn bị hủy / hoàn tiền / hoàn hàng

### Mục tiêu

Commission không được sai khi đơn thất bại sau đó.

## 11.1. Nếu đơn bị hủy trước khi release

Khi:

- `fulfillment_status = cancelled`

thì:

- conversion `pending` -> `rejected`

Không cộng ví.

## 11.2. Nếu đơn đã release rồi mới refund / return

Khi:

- `payment_status = refunded`
- hoặc `fulfillment_status = returned`

thì:

- conversion `released` -> `reversed`
- tạo wallet transaction `debit`

### Điều kiện cần

Ví người giới thiệu phải có ledger rõ ràng.

---

## 5. Dữ liệu nên lưu ở đâu

## 5.1. Frontend storage

### localStorage

Lưu:

- `affiliate_ref`

Ví dụ:

```json
{
  "code": "AFF123",
  "landing_url": "/product/ao-bong-da-nike?ref=AFF123",
  "captured_at": 1770000000000,
  "expires_at": 1772592000000
}
```

### sessionStorage

Lưu:

- `redirect_after_login`

Ví dụ:

```text
/product/ao-bong-da-nike?ref=AFF123
```

---

## 5.2. Backend database

### Bảng `users`

Thêm:

- `referral_code`
- `referred_by_user_id`

### Bảng `referrals`

Lưu quan hệ giới thiệu.

### Bảng `affiliate_conversions`

Lưu mỗi order affiliate.

### Bảng `affiliate_wallet_transactions`

Lưu bút toán cộng/trừ commission.

---

## 6. Rule nghiệp vụ khuyến nghị cho dự án này

Đây là bộ rule phù hợp nhất để bắt đầu:

1. Mỗi user có 1 `referral_code`.
2. User bấm link sản phẩm có `?ref=...`.
3. Referral được lưu 30 ngày ở localStorage.
4. Nếu user register mới:
   - bind referral ngay lúc đăng ký.
5. Nếu user login tài khoản cũ:
   - chỉ bind nếu user chưa có referrer và chưa là khách cũ.
6. Chỉ tính commission cho đơn đầu tiên completed của referred user.
7. Commission tính theo phần trăm trên:
   - `subtotal` sau giảm giá
   - không tính phí ship
8. Chỉ release khi:
   - `fulfillment_status = completed`
   - và nếu là thanh toán online thì đơn không ở trạng thái failed/refunded
9. Nếu đơn bị hủy / hoàn:
   - reject hoặc reverse commission

---

## 7. API đề xuất

## Public / semi-public

### Resolve referral code

```text
GET /affiliate/resolve/{code}
```

Mục đích:

- kiểm tra code tồn tại
- trả tên người giới thiệu nếu muốn hiển thị

### Track click

```text
POST /affiliate/track-click
```

Payload:

```json
{
  "referral_code": "AFF123",
  "landing_url": "/product/ao-bong-da-nike?ref=AFF123"
}
```

## Auth user APIs

### Bind referral sau login

```text
POST /profile/affiliate/bind
```

### Overview

```text
GET /profile/affiliate/overview
```

### Conversion history

```text
GET /profile/affiliate/conversions
```

### Wallet history

```text
GET /profile/affiliate/wallet
```

---

## 8. Pseudo flow frontend

## 8.1. Khi vào site

```text
router.beforeEach
-> nếu query.ref tồn tại
-> lưu affiliate_ref vào localStorage
-> cho đi tiếp
```

## 8.2. Khi route yêu cầu login

```text
if no token
-> sessionStorage.setItem('redirect_after_login', to.fullPath)
-> redirect login
```

## 8.3. Sau login success

```text
đọc affiliate_ref
-> nếu còn hạn thì gọi API bind referral
-> đọc redirect_after_login
-> quay lại route trước đó
```

## 8.4. Khi register success

```text
gửi referral_code cùng payload register
-> backend bind luôn
```

---

## 9. Pseudo flow backend

## 9.1. Register

```text
receive referral_code
-> create user
-> validate referral_code
-> set referred_by_user_id
-> create referrals record
```

## 9.2. Bind after login

```text
receive referral_code
-> if user has no referrer
-> if user is eligible
-> bind referrer
-> create referrals record if not exists
```

## 9.3. Create order

```text
create order
-> if user has referrer
-> if order eligible
-> create affiliate_conversion pending
```

## 9.4. Complete order

```text
admin update order to completed
-> find affiliate_conversion
-> approve/release
-> create wallet credit
```

## 9.5. Cancel/refund order

```text
order cancelled or refunded
-> find affiliate_conversion
-> reject or reverse
-> if released then create wallet debit
```

---

## 10. Trạng thái nên dùng

## 10.1. Referral status

- `tracked`
- `registered`
- `qualified`
- `rejected`

## 10.2. Affiliate conversion status

- `pending`
- `approved`
- `released`
- `rejected`
- `reversed`

## 10.3. Wallet transaction type

- `credit`
- `debit`
- `adjustment`
- `withdraw_request`
- `withdraw_success`
- `withdraw_reject`

---

## 11. Edge cases bắt buộc xử lý

1. User bấm nhiều link affiliate khác nhau
   - chọn rule:
   - khuyến nghị: last valid referral wins trước khi bind

2. User đã có referrer rồi
   - link mới không được ghi đè

3. User login xong nhưng không mua ngay
   - referral vẫn còn tới khi hết TTL hoặc đã bind

4. User thêm giỏ trước, login sau
   - không vấn đề nếu referral được bind trước khi tạo order

5. User đổi sản phẩm sau khi vào từ link ban đầu
   - vẫn tính theo referrer đã captured

6. User mở nhiều tab
   - localStorage vẫn giữ cùng referral

7. User đã completed order từ trước rồi mới bấm link affiliate
   - nếu rule chỉ cho user mới, không bind

---

## 12. Flow chuẩn khuyến nghị để áp dụng ngay

Đây là flow nên dùng cho dự án này:

1. User A có `referral_code`.
2. A chia sẻ link sản phẩm dạng `?ref=CODE`.
3. User B bấm link.
4. Frontend lưu `affiliate_ref` vào localStorage trong 30 ngày.
5. Nếu B chưa login:
   - vẫn cho xem sản phẩm
   - khi cần login thì lưu `redirect_after_login`
6. Sau login/register:
   - frontend gửi `referral_code` lên backend để bind nếu đủ điều kiện
7. B thêm giỏ, checkout, tạo order.
8. `OrderController@store` tạo `affiliate_conversion` status `pending`.
9. Khi admin chuyển đơn sang `completed`:
   - release commission theo %
   - ghi vào wallet transaction
10. Nếu đơn bị hủy / hoàn:
   - reject hoặc reverse commission

---

## 13. Nên triển khai theo thứ tự nào

1. Migration thêm `referral_code`, `referred_by_user_id`
2. Router lưu `?ref=`
3. Register nhận `referral_code`
4. API bind sau login
5. Order tạo `affiliate_conversion`
6. Admin complete -> release commission
7. Profile affiliate page
8. Admin affiliate dashboard
9. Wallet ledger
10. Payout

---

## 14. Kết luận

Flow đúng cho dự án này là:

- **share link sản phẩm có `?ref=`**
- **lưu referral ở frontend ngay từ lúc click**
- **bind referral vào backend khi login/register**
- **snapshot referral vào order khi checkout**
- **chỉ release commission khi order completed**

Đây là cách ít friction nhất cho khách hàng, đúng với hành vi mua hàng thật, và bám sát được kiến trúc hiện tại của repo mà không cần bẻ flow checkout đang có.
