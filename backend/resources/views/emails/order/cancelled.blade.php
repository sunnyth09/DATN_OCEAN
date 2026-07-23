@component('mail::message')
# ❌ Đơn hàng đã bị hủy

Xin chào **{{ $userName }}**,

@if($cancelledBy === 'admin')
Chúng tôi rất tiếc phải thông báo rằng đơn hàng của bạn đã bị **hủy bởi cửa hàng**.
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

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:5173') . '/products', 'color' => 'primary'])
🛍️ Tiếp tục mua sắm
@endcomponent

Chúng tôi xin lỗi vì sự bất tiện này và mong sớm được phục vụ bạn trong lần tới.

**{{ config('app.name') }}**

<small>Email này được gửi tự động, vui lòng không reply.</small>
@endcomponent
