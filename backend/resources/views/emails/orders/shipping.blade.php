@component('mail::message')
# 🚚 Đơn Hàng Đang Được Vận Chuyển

Xin chào **{{ $customerName }}**,

Đơn hàng **#{{ $orderCode }}** của bạn đã được đóng gói và bàn giao cho đơn vị vận chuyển.

@if(!empty($trackingCode))
**Mã vận đơn:** `{{ $trackingCode }}`
@endif

---

@if(!empty($trackingUrl))
@component('mail::button', ['url' => $trackingUrl, 'color' => 'primary'])
📍 Theo dõi hành trình đơn hàng
@endcomponent
@endif

Chúng tôi sẽ liên tục cập nhật trạng thái đơn hàng đến bạn cho tới khi kiện hàng được giao thành công.

Cảm ơn bạn đã đồng hành và mua sắm tại Ocean Sport!

**OCEAN SPORT — CỬA HÀNG THỂ THAO CAO CẤP**<br>
Hotline: **1900 6868** | Email: **contact@oceansport.vn**

<small>Email này được gửi tự động từ hệ thống Ocean Sport.</small>
@endcomponent
