@component('mail::message')
# ❌ Thông Báo Hủy Đơn Hàng

Xin chào **{{ $userName }}**,

@if($cancelledBy === 'admin')
Chúng tôi rất tiếc phải thông báo rằng đơn hàng của bạn đã bị **hủy bởi hệ thống/cửa hàng**.
@else
Đơn hàng của bạn đã được **hủy thành công** theo yêu cầu.
@endif

---

## Thông tin đơn hàng đã hủy

| Nội dung | Chi tiết |
|---|---|
| **Mã đơn hàng** | `{{ $orderCode }}` |
| **Ngày đặt** | {{ $orderDate }} |
| **Tổng tiền** | {{ $totalAmount }} |
| **Lý do hủy** | {{ $cancelReason }} |
@if(!empty($refundAmount))
| **Tiền hoàn lại** | **{{ $refundAmount }}** |
| **Nơi nhận hoàn** | {{ $refundDestination ?? 'Ví Ocean Sport' }} |
@endif

@if(!empty($refundAmount))
@component('mail::panel')
💰 **Thông tin hoàn tiền:** Số tiền **{{ $refundAmount }}** đã được xử lý hoàn trả về **{{ $refundDestination ?? 'Ví Ocean Sport' }}** của bạn. Bạn có thể sử dụng số dư này để đặt đơn hàng mới hoặc rút về tài khoản ngân hàng bất cứ lúc nào.
@endcomponent
@endif

---

@component('mail::button', ['url' => rtrim(config('app.frontend_url', 'http://localhost:3302'), '/') . '/profile/wallet', 'color' => 'success'])
💳 Xem số dư Ví của bạn
@endcomponent

Chúng tôi xin lỗi vì sự bất tiện này và mong sớm có cơ hội được phục vụ bạn!

**OCEAN SPORT — CỬA HÀNG THỂ THAO CAO CẤP**<br>
Hotline: **1900 6868** | Email: **contact@oceansport.vn**

<small>Email này được gửi tự động từ hệ thống Ocean Sport.</small>
@endcomponent
