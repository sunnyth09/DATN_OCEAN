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

---

@component('mail::button', ['url' => rtrim(config('app.frontend_url', 'http://localhost:3302'), '/') . '/product', 'color' => 'primary'])
🛍️ Khám phá sản phẩm khác
@endcomponent

Chúng tôi xin lỗi vì sự bất tiện này và mong sớm có cơ hội được phục vụ bạn!

**OCEAN SPORT — CỬA HÀNG THỂ THAO CAO CẤP**<br>
Hotline: **1900 6868** | Email: **contact@oceansport.vn**

<small>Email này được gửi tự động từ hệ thống Ocean Sport.</small>
@endcomponent
