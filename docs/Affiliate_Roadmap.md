# Affiliate Feature Roadmap Cho Dự Án

## 1. Mục tiêu

Tính năng Affiliate nên được hiểu là:

- Người dùng hiện tại có thể chia sẻ link/mã giới thiệu.
- Người dùng mới đi vào hệ thống từ link đó sẽ được gắn nguồn giới thiệu.
- Khi người được giới thiệu phát sinh đơn hàng hợp lệ, hệ thống ghi nhận chuyển đổi.
- Người giới thiệu nhận thưởng theo quy tắc của chương trình.

Với dự án của bạn, đây là một bài toán rất hợp với hệ thống đang có vì hiện tại đã có sẵn:

- `User`
- `Cart` và `Order`
- `Payment`
- `Coupon`
- `Notification`
- `Profile` cho khách hàng
- `AdminOrderController` để quản lý vòng đời đơn hàng

Nói ngắn gọn: phần lõi thương mại điện tử đã có, Affiliate chỉ còn thiếu lớp "attribution + commission + payout/reporting".

---

## 2. Kết luận áp dụng cho dự án này

### Hướng nên làm

Không nên nhảy thẳng vào mô hình Affiliate trả tiền mặt đầy đủ ngay từ đầu.

Nên triển khai theo 2 tầng:

1. **MVP Referral**
   - Mỗi user có `referral_code`
   - Chia sẻ link giới thiệu
   - Ghi nhận người được giới thiệu
   - Thưởng bằng `reward_points`, voucher, hoặc số dư nội bộ
   - Không cần payout ra ngân hàng ở phase đầu

2. **Affiliate Full**
   - Có commission theo đơn
   - Có ví affiliate
   - Có yêu cầu rút tiền
   - Có đối soát khi đơn bị hủy, hoàn tiền, thất bại thanh toán

### Vì sao nên đi theo hướng này

Repo hiện tại đã có `reward_points`, `coupon`, `notifications`, `orders`, `payment_status`, `fulfillment_status`.

Điều đó có nghĩa là:

- Phase 1 có thể ra tính năng nhanh, ít rủi ro.
- Phase 2 mới thêm phần kế toán, đối soát, rút tiền, phức tạp hơn nhiều.

Nếu làm ngay full cash affiliate từ đầu, phần khó nhất không nằm ở UI mà nằm ở:

- chống gian lận
- xử lý đơn COD
- xử lý đơn bị hủy
- xử lý hoàn tiền
- chốt thời điểm commission hợp lệ
- đối soát payout

---

## 3. Tính năng nên hoạt động như thế nào

## 3.1. Luồng người dùng

1. User A đăng ký tài khoản hoặc đã có tài khoản.
2. Hệ thống cấp cho A một `referral_code`, ví dụ `QUYENA123`.
3. A chia sẻ link:

```text
https://your-frontend.com/?ref=QUYENA123
```

4. User B bấm link.
5. Frontend đọc `ref` từ URL và lưu vào:
   - cookie
   - hoặc `localStorage`
   - có thời hạn, ví dụ 30 ngày

6. B đăng ký tài khoản hoặc đăng nhập rồi mua hàng.
7. Backend xác nhận B được giới thiệu bởi A.
8. Khi B đặt đơn, hệ thống gắn attribution vào đơn hàng.
9. Khi đơn đủ điều kiện hợp lệ, hệ thống tạo commission/thưởng cho A.
10. A vào trang Profile để xem:
   - link giới thiệu
   - số click
   - số user đã giới thiệu
   - số đơn hợp lệ
   - số tiền/điểm được nhận
   - lịch sử commission

---

## 3.2. Luồng kỹ thuật chuẩn cho dự án của bạn

### Bước 1: Tracking link giới thiệu

Khi user truy cập trang có `?ref=CODE`:

- Frontend lưu mã này vào local storage hoặc cookie.
- Nên lưu thêm:
  - `referral_code`
  - `landing_url`
  - `captured_at`
  - `expires_at`

### Bước 2: Bind người được giới thiệu

Khi user đăng ký hoặc đăng nhập:

- Frontend gửi kèm `referral_code` đang lưu.
- Backend kiểm tra:
  - code có tồn tại không
  - có tự giới thiệu chính mình không
  - user này đã từng bị bind với ai chưa

Nếu hợp lệ:

- lưu `referred_by_user_id` cho user được giới thiệu
- hoặc tạo một record trong bảng `referrals`

### Bước 3: Gắn referral vào đơn hàng

Trong `OrderController@store`, khi tạo order:

- đọc `user_id`
- tra xem user có `referred_by_user_id` không
- nếu có, snapshot thông tin affiliate vào đơn

Không nên chỉ dựa vào `user.referred_by_user_id` về sau, vì quy tắc chương trình có thể thay đổi.  
Nên snapshot ngay tại thời điểm đơn được tạo hoặc dùng bảng mapping riêng cho order.

### Bước 4: Chưa cộng thưởng ngay khi order vừa tạo

Đây là điểm rất quan trọng.

Không nên cộng thưởng ở lúc:

- order vừa tạo
- VNPay vừa redirect về thành công
- COD vừa xác nhận

Nên tách 2 mốc:

- **conversion ghi nhận**: khi order được tạo thành công và có attribution
- **commission hợp lệ**: khi order đạt trạng thái đủ an toàn

### Bước 5: Khi nào commission hợp lệ

Với dự án này, nên dùng quy tắc:

- Đơn online (`vnpay`, `momo`, `bank_transfer`):
  - cần `payment_status = paid`
  - và `fulfillment_status` tối thiểu là `delivered` hoặc `completed`

- Đơn COD:
  - chỉ nên cộng khi `fulfillment_status = completed`
  - vì `AdminOrderController` hiện tại đang tự động chuyển COD sang `paid` khi `completed`

Khuyến nghị thực tế:

- **MVP:** commission được `approved` khi `fulfillment_status = completed`
- Nếu muốn chặt hơn: thêm thời gian chờ 3-7 ngày sau `completed` rồi mới `released`

### Bước 6: Nếu đơn bị hủy hoặc hoàn tiền

Khi đơn đi sang:

- `cancelled`
- `returned`
- `payment_status = refunded`

thì commission phải:

- không được tạo nếu chưa tạo
- hoặc bị `rejected/reversed` nếu đã tạo pending
- hoặc trừ ngược ví nếu đã release

Đây là chỗ bắt buộc phải bám vào các trạng thái hiện có trong:

- `OrderController`
- `AdminOrderController`
- `VNPayController`

---

## 4. Thiết kế nghiệp vụ đề xuất

## 4.1. Quy tắc chương trình

Nên chốt một bộ rule đơn giản trước:

- Mỗi user có 1 mã giới thiệu duy nhất.
- Chỉ user mới lần đầu mới được tính là referral hợp lệ.
- Mỗi user chỉ được bind với 1 referrer.
- Chỉ tính commission cho đơn đầu tiên, hoặc cho N đơn đầu tiên.
- Không tính cho đơn của chính referrer.
- Không cộng thưởng cho đơn bị hủy/hoàn.
- Có thời gian hiệu lực click, ví dụ 30 ngày.

### Rule MVP dễ vận hành nhất

- User B vào từ link của A.
- B đăng ký tài khoản.
- B hoàn tất đơn đầu tiên.
- A nhận:
  - `reward_points`, hoặc
  - voucher, hoặc
  - số dư affiliate nội bộ

### Rule mở rộng cho full affiliate

- A được nhận `%` theo `grand_total` hoặc `subtotal`.
- Có thể có các tier:
  - Bronze: 3%
  - Silver: 5%
  - Gold: 7%

Khuyến nghị:

- Tính trên `subtotal` sau giảm giá, trước phí ship
- Không tính trên `shipping_fee`

---

## 4.2. Đề xuất mô hình dữ liệu

### Tối thiểu cần có

#### Bảng `users`

Thêm các cột:

- `referral_code` unique
- `referred_by_user_id` nullable
- `affiliate_status` nullable, ví dụ `none|active|blocked`

`referred_by_user_id` giúp tra nhanh user này được ai giới thiệu.

#### Bảng `referral_visits`

Mục đích:

- lưu click vào link giới thiệu
- phục vụ thống kê traffic
- hỗ trợ chống spam ở mức cơ bản

Cột gợi ý:

- `id`
- `referrer_user_id`
- `referral_code`
- `session_key`
- `ip_address`
- `user_agent`
- `landing_url`
- `created_at`

#### Bảng `referrals`

Mục đích:

- lưu quan hệ giới thiệu giữa referrer và referred user
- là bản ghi nghiệp vụ chính

Cột gợi ý:

- `id`
- `referrer_user_id`
- `referred_user_id`
- `referral_code`
- `source`
- `status` (`tracked|registered|qualified|rejected`)
- `qualified_at`
- `notes`
- `created_at`

#### Bảng `affiliate_conversions`

Mục đích:

- nối referral với order
- snapshot đơn nào được tính affiliate

Cột gợi ý:

- `id`
- `referrer_user_id`
- `referred_user_id`
- `referral_id`
- `order_id`
- `order_code`
- `order_subtotal`
- `order_discount_amount`
- `order_grand_total`
- `commission_base_amount`
- `commission_rate`
- `commission_amount`
- `status` (`pending|approved|released|rejected|reversed`)
- `approved_at`
- `released_at`
- `rejected_at`
- `rejection_reason`
- `created_at`

#### Bảng `affiliate_wallet_transactions`

Mục đích:

- sổ cái ví affiliate
- không sửa số dư trực tiếp mà luôn ghi transaction

Cột gợi ý:

- `id`
- `user_id`
- `conversion_id`
- `type` (`credit|debit|adjustment|withdraw_request|withdraw_success|withdraw_reject`)
- `amount`
- `balance_before`
- `balance_after`
- `description`
- `meta`
- `created_at`

#### Bảng `affiliate_payout_requests`

Phase 2 mới cần.

Cột gợi ý:

- `id`
- `user_id`
- `amount`
- `bank_name`
- `bank_account_name`
- `bank_account_number`
- `status` (`pending|approved|paid|rejected`)
- `processed_by`
- `processed_at`
- `note`

---

## 4.3. Có nên tạo bảng `affiliate_program_settings` không

Nên có nếu bạn muốn admin đổi rule mà không cần sửa code.

Ví dụ:

- `cookie_ttl_days`
- `first_order_only`
- `min_order_amount`
- `commission_type`
- `commission_value`
- `commission_release_status`
- `holding_days`
- `payout_minimum_amount`

Nếu muốn đi nhanh hơn trong phase đầu:

- có thể hard-code trong config `backend/config/affiliate.php`

Đây là cách phù hợp hơn với repo hiện tại.

---

## 5. Tích hợp vào flow hiện tại của dự án

## 5.1. Frontend

### Điểm chạm nên thêm

1. `frontend/src/router/index.js`
   - bắt query `ref`
   - lưu referral code khi user vào site

2. `frontend/src/Pages/Client/Auth/Register.vue`
   - gửi `referral_code` nếu có

3. `frontend/src/Pages/Client/Cart/Checkout.vue`
   - có thể hiển thị:
     - “Đơn hàng này được ghi nhận từ giới thiệu của ...”
     - hoặc giữ ẩn hoàn toàn

4. `frontend/src/components/ProfileAside.vue`
   - thêm menu:
     - `Affiliate`

5. `frontend/src/Pages/Client/Profile/`
   - thêm page `ProfileAffiliate.vue`

### Nội dung page Affiliate trong Profile

- Mã giới thiệu của tôi
- Nút copy link
- Tổng click
- Tổng user đã đăng ký qua link
- Tổng đơn đủ điều kiện
- Hoa hồng chờ duyệt
- Hoa hồng khả dụng
- Lịch sử commission
- Trạng thái rút tiền

---

## 5.2. Backend API

### Public API

- `GET /affiliate/resolve/{code}`
  - kiểm tra mã có hợp lệ không

- `POST /affiliate/track-click`
  - ghi nhận click
  - có thể optional nếu bạn không cần tracking sâu ở MVP

### Auth API cho user

Đặt dưới prefix `profile` để đồng bộ với kiến trúc hiện tại:

- `GET /profile/affiliate/overview`
- `GET /profile/affiliate/conversions`
- `GET /profile/affiliate/wallet`
- `POST /profile/affiliate/payout-requests`

### Auth API cho admin

Đặt dưới `/admin/affiliate`:

- `GET /admin/affiliate/overview`
- `GET /admin/affiliate/referrals`
- `GET /admin/affiliate/conversions`
- `PUT /admin/affiliate/conversions/{id}/approve`
- `PUT /admin/affiliate/conversions/{id}/reject`
- `GET /admin/affiliate/payout-requests`
- `PUT /admin/affiliate/payout-requests/{id}/approve`
- `PUT /admin/affiliate/payout-requests/{id}/pay`
- `PUT /admin/affiliate/payout-requests/{id}/reject`

---

## 5.3. Backend nghiệp vụ cần cắm vào đâu

### A. Auth/Register

Trong `AuthController@register`:

- nhận `referral_code`
- bind referrer cho user mới
- tạo record `referrals`

### B. Checkout/Order Creation

Trong `OrderController@store`:

- sau khi tạo order
- nếu user có quan hệ referral hợp lệ
- tạo `affiliate_conversion` status `pending`

### C. Payment Success

Trong `VNPayController` và `MoMoController`:

- không nhất thiết release commission ở đây
- nhưng có thể đánh dấu conversion là:
  - “payment passed”
  - hoặc chỉ để phục vụ analytics

### D. Admin order status update

Trong `AdminOrderController@updateStatus` và `bulkUpdateStatus`:

- khi đơn sang `completed`
  - xét duyệt commission nếu đủ điều kiện

- khi đơn sang `cancelled` hoặc `returned`
  - reject/reverse conversion tương ứng

Đây là điểm móc quan trọng nhất vì vòng đời đơn hàng hiện đang đi qua controller này.

### E. Cron/Job

Nên có command/job riêng:

- `affiliate:release-commissions`
  - release commission sau N ngày

- `affiliate:reconcile-orders`
  - đối soát conversion với order status

- `affiliate:expire-tracking`
  - dọn dữ liệu tracking cũ nếu cần

---

## 6. Công thức tính thưởng nên chọn

## 6.1. Phương án nên dùng cho MVP

### Phương án A: thưởng điểm

Ví dụ:

- A giới thiệu B
- B hoàn tất đơn đầu tiên từ 300.000đ
- A nhận `100 reward_points`
- B nhận `coupon welcome affiliate`

Ưu điểm:

- cực dễ triển khai với hệ thống hiện có
- ít rủi ro tài chính
- không cần payout

Nhược điểm:

- không phải affiliate "tiền mặt" đúng nghĩa

### Phương án B: thưởng voucher

Ví dụ:

- A nhận 1 coupon 10%
- B nhận 1 coupon 5%

Ưu điểm:

- tận dụng được `CouponController` và `user_coupons`

Nhược điểm:

- khó hấp dẫn nếu mục tiêu là kéo cộng tác viên bán hàng

---

## 6.2. Phương án full affiliate

### Phương án C: commission %

Ví dụ:

- commission = `5% * commission_base_amount`

Trong đó:

- `commission_base_amount = subtotal - discount_allocated`

Nên tránh tính trên:

- `shipping_fee`
- đơn có `grand_total <= 0`

### Phương án D: commission cố định

Ví dụ:

- mỗi đơn đầu tiên hợp lệ: 30.000đ

Đây là mô hình đơn giản, dễ marketing, dễ kiểm soát chi phí.

### Khuyến nghị thực tế cho dự án

Nếu đây là website bán lẻ như hiện tại:

- **MVP:** fixed reward hoặc reward points
- **Phase 2:** fixed commission cho đơn đầu tiên
- **Phase 3:** percentage commission theo tier

---

## 7. Chống gian lận

Affiliate không khó ở CRUD, khó ở fraud.

Tối thiểu nên có các rule:

- Không cho tự giới thiệu:
  - cùng `user_id`
  - cùng email
  - cùng số điện thoại

- Chặn bind lại referrer:
  - 1 referred user chỉ thuộc 1 referrer

- Không tính commission nếu:
  - đơn bị hủy
  - đơn hoàn tiền
  - thanh toán thất bại

- Theo dõi tín hiệu rủi ro:
  - nhiều account chung IP
  - cùng device fingerprint
  - cùng địa chỉ giao hàng
  - cùng số điện thoại nhận hàng

Phase đầu chưa cần chặn quá mạnh, nhưng nên log dữ liệu để admin review.

---

## 8. Màn hình cần bổ sung

## 8.1. Phía khách hàng

### Profile Affiliate

Nên thêm trong khu vực profile hiện tại:

- menu `Affiliate`
- giao diện overview
- bảng lịch sử chuyển đổi
- bảng ví affiliate
- form yêu cầu rút tiền ở phase 2

### Product detail / landing

Có thể thêm:

- nút “Chia sẻ link giới thiệu”
- hoặc chỉ để trong Profile là đủ

### Checkout

Không bắt buộc hiển thị công khai referral code trên checkout.  
Tốt nhất là backend xử lý tự động để không làm rối UX.

---

## 8.2. Phía admin

### Dashboard Affiliate

Nên có:

- tổng số affiliate đang active
- tổng click
- tổng user được giới thiệu
- tổng đơn quy đổi
- tổng commission pending
- tổng commission released
- tỷ lệ chuyển đổi

### Quản lý conversion

Admin cần xem:

- referrer
- referred user
- order
- trạng thái đơn
- trạng thái payment
- commission
- trạng thái payout

### Quản lý payout

Admin cần:

- duyệt yêu cầu rút tiền
- từ chối
- đánh dấu đã chuyển khoản

---

## 9. Roadmap triển khai chi tiết

## Phase 0. Chốt rule nghiệp vụ

Mục tiêu:

- chốt chương trình trước khi viết code

Việc cần làm:

1. Chọn mô hình thưởng:
   - reward points
   - voucher
   - fixed tiền
   - percent commission

2. Chọn đối tượng được tính:
   - chỉ user mới
   - chỉ đơn đầu tiên
   - hay nhiều đơn

3. Chọn thời điểm release:
   - khi `delivered`
   - khi `completed`
   - hoặc sau `completed + N ngày`

4. Chọn chính sách anti-fraud.

Deliverable:

- 1 file rule nội bộ rõ ràng

---

## Phase 1. Data layer

Mục tiêu:

- tạo nền dữ liệu cho affiliate

Việc cần làm:

1. Migration thêm cột vào `users`
   - `referral_code`
   - `referred_by_user_id`
   - `affiliate_status`

2. Tạo bảng:
   - `referral_visits`
   - `referrals`
   - `affiliate_conversions`

3. Nếu làm full:
   - `affiliate_wallet_transactions`
   - `affiliate_payout_requests`

4. Tạo model Eloquent + relation:
   - `User`
   - `Order`
   - `Referral`
   - `AffiliateConversion`

Kết quả mong muốn:

- có thể truy vấn:
  - user này giới thiệu ai
  - user này được ai giới thiệu
  - đơn nào được ghi nhận affiliate

---

## Phase 2. Capture referral

Mục tiêu:

- lưu được nguồn giới thiệu từ lúc user vào site

Việc cần làm:

1. Trong frontend router:
   - đọc `?ref=...`
   - lưu vào local storage/cookie

2. Tạo helper dùng chung:
   - get referral code
   - validate TTL
   - clear code khi hết hạn

3. Optional:
   - gửi API `track-click`

Kết quả mong muốn:

- người dùng rời trang rồi quay lại vẫn còn attribution trong 30 ngày

---

## Phase 3. Bind referral vào user

Mục tiêu:

- khi user đăng ký thành công thì quan hệ giới thiệu được khóa lại

Việc cần làm:

1. Mở rộng `register` API nhận `referral_code`
2. Validate:
   - code tồn tại
   - không tự giới thiệu
   - user chưa có referrer
3. Tạo record `referrals`
4. Gán `referred_by_user_id` cho user mới

Kết quả mong muốn:

- quan hệ A giới thiệu B được lưu chắc chắn từ thời điểm đăng ký

---

## Phase 4. Bind referral vào order

Mục tiêu:

- mỗi order hợp lệ có thể truy vết ngược về affiliate source

Việc cần làm:

1. Trong `OrderController@store`
   - sau khi tạo order
   - tra referral của user
   - tạo `affiliate_conversion` status `pending`

2. Snapshot dữ liệu order:
   - subtotal
   - discount
   - grand_total
   - commission_base_amount
   - rule version

3. Bỏ qua các order không đủ điều kiện:
   - user không có referrer
   - self-referral
   - rule first-order-only không đạt

Kết quả mong muốn:

- order phát sinh từ referral được ghi nhận ổn định

---

## Phase 5. Commission engine

Mục tiêu:

- tự động tính thưởng đúng theo trạng thái đơn

Việc cần làm:

1. Viết service, ví dụ `AffiliateService`
   - `captureOrderConversion()`
   - `approveConversion()`
   - `releaseCommission()`
   - `reverseCommission()`

2. Gắn vào:
   - `AdminOrderController@updateStatus`
   - `bulkUpdateStatus`
   - `VNPayController`
   - `MoMoController`

3. Rule xử lý:
   - order completed -> approve/release
   - order cancelled -> reject
   - refunded -> reverse

Kết quả mong muốn:

- affiliate không còn là dữ liệu tĩnh, mà đi đúng theo lifecycle đơn hàng

---

## Phase 6. User profile affiliate

Mục tiêu:

- user nhìn thấy được hiệu quả link giới thiệu của mình

Việc cần làm:

1. Thêm route frontend:
   - `/profile/affiliate`

2. Thêm menu ở `ProfileAside.vue`

3. Tạo page overview gồm:
   - link referral
   - lượt click
   - user đăng ký
   - conversions
   - commission pending
   - commission released

4. Tạo API:
   - overview
   - conversions
   - wallet

Kết quả mong muốn:

- user có thể dùng tính năng chứ không chỉ admin thấy dữ liệu

---

## Phase 7. Admin management

Mục tiêu:

- admin có công cụ kiểm soát và đối soát

Việc cần làm:

1. Tạo màn hình admin affiliate
2. Danh sách referrer và conversion
3. Filter theo:
   - thời gian
   - trạng thái
   - user
   - order code

4. Có action:
   - approve
   - reject
   - reverse
   - export CSV

Kết quả mong muốn:

- admin vận hành được chương trình affiliate thật sự

---

## Phase 8. Wallet và payout

Mục tiêu:

- chuyển từ referral thưởng nội bộ sang affiliate có thể rút tiền

Việc cần làm:

1. Tạo wallet ledger
2. Tính balance từ transactions
3. Tạo yêu cầu rút tiền
4. Admin duyệt chi trả
5. Ghi bút toán:
   - request
   - success
   - reject

Kết quả mong muốn:

- hoàn thiện mô hình affiliate tiền mặt

---

## 10. Thứ tự triển khai khuyến nghị

Nếu mục tiêu là ra nhanh nhưng không làm ẩu, nên đi theo thứ tự này:

1. `referral_code` + bind user
2. tracking order conversion
3. commission pending theo order
4. profile affiliate cho user
5. admin affiliate dashboard
6. wallet nội bộ
7. payout

Đây là thứ tự hợp lý nhất với codebase hiện tại.

---

## 11. Các file/module hiện tại sẽ bị ảnh hưởng

## Backend

- `backend/app/Models/User.php`
- `backend/app/Models/Order.php`
- `backend/app/Http/Controllers/AuthController.php`
- `backend/app/Http/Controllers/OrderController.php`
- `backend/app/Http/Controllers/AdminOrderController.php`
- `backend/app/Http/Controllers/VNPayController.php`
- `backend/app/Http/Controllers/MoMoController.php`
- `backend/routes/api.php`
- `backend/database/migrations/*`

## Frontend

- `frontend/src/router/index.js`
- `frontend/src/components/ProfileAside.vue`
- `frontend/src/Pages/Client/Profile/ProfileLayout.vue`
- `frontend/src/Pages/Client/Profile/ProfileInfo.vue`
- `frontend/src/Pages/Client/Auth/Register.vue`
- `frontend/src/Pages/Client/Cart/Checkout.vue`
- `frontend/src/Pages/Client/Profile/ProfileAffiliate.vue` mới

## Admin frontend

- thêm page affiliate dashboard nếu admin panel của bạn đang dùng Vue route cùng hệ thống

---

## 12. Các quyết định kỹ thuật quan trọng cần chốt sớm

1. Thưởng theo `reward_points`, voucher hay tiền mặt.
2. Tính cho đơn đầu tiên hay mọi đơn.
3. Commission tính trên `subtotal` hay `grand_total`.
4. Khi nào release:
   - `delivered`
   - `completed`
   - `completed + holding days`
5. Có payout ngay phase đầu hay không.
6. Có cho admin chỉnh rule trên UI hay chỉ qua config.

Nếu chưa chốt được hết, tôi khuyến nghị default như sau:

- Chỉ user mới
- Chỉ đơn đầu tiên
- Tính khi `completed`
- MVP dùng `reward_points` hoặc fixed internal balance
- Phase 2 mới mở payout

---

## 13. Rủi ro nếu làm sai

### 1. Cộng thưởng quá sớm

Nếu cộng lúc order vừa tạo, đơn COD hủy sẽ làm sai commission.

### 2. Không snapshot rule theo order

Sau này đổi rule commission sẽ làm dữ liệu cũ lệch.

### 3. Chỉ lưu ref ở frontend, không bind vào backend

User đổi máy hoặc clear storage là mất attribution.

### 4. Không có ledger

Nếu chỉ lưu mỗi `current_balance`, sau này rất khó audit.

### 5. Không gắn với order lifecycle

Affiliate sẽ lệch với thực tế vận hành đơn hàng.

---

## 14. Đề xuất chốt cho dự án này

### Bản nên làm ngay

Một phiên bản thực dụng, hợp codebase hiện tại:

- Mỗi user có `referral_code`
- Frontend bắt `?ref=`
- Register bind `referred_by_user_id`
- Đơn đầu tiên của người được giới thiệu sẽ tạo `affiliate_conversion`
- Khi đơn `completed`, người giới thiệu nhận:
  - `reward_points` hoặc
  - fixed bonus nội bộ
- User xem được thống kê trong `/profile/affiliate`
- Admin xem được danh sách referral và conversion

### Bản nên để phase sau

- commission %
- multi-tier affiliate
- payout ra ngân hàng
- fraud scoring nâng cao
- campaign tracking theo UTM

---

## 15. Definition of Done

Tính năng được xem là xong khi đạt đủ:

1. User có mã giới thiệu riêng.
2. Link có `?ref=` được lưu đúng.
3. User mới đăng ký từ link được bind đúng referrer.
4. Order của referred user được gắn conversion.
5. Đơn `completed` tạo thưởng đúng.
6. Đơn `cancelled/refunded` không tạo hoặc bị reverse đúng.
7. User xem được lịch sử affiliate trong profile.
8. Admin xem và lọc được dữ liệu affiliate.
9. Có test cho:
   - self-referral
   - first-order-only
   - COD cancelled
   - online paid
   - completed then refunded

---

## 16. Tóm tắt một câu

Với dự án của bạn, Affiliate nên được làm theo hướng **Referral trước, Commission sau**: gắn mã giới thiệu vào user, gắn attribution vào order, và chỉ release thưởng khi đơn đi đến trạng thái `completed`; sau khi MVP ổn định mới mở rộng sang ví affiliate và payout.
