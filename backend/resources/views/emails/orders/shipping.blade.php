@component('mail::message')
# Đơn hàng của bạn đã được tạo vận đơn GHN

Xin chào {{ $customerName }},

Đơn hàng **#{{ $orderCode }}** đã được đồng bộ sang Giao Hàng Nhanh.

@if($ghnOrderCode)
**Mã vận đơn GHN:** {{ $ghnOrderCode }}
@endif

@if($trackingUrl)
@component('mail::button', ['url' => $trackingUrl])
Theo dõi đơn hàng
@endcomponent
@endif

@if($ghnTrackingUrl)
Bạn cũng có thể tra cứu trực tiếp trên GHN tại: [{{ $ghnTrackingUrl }}]({{ $ghnTrackingUrl }})
@endif

Cảm ơn bạn đã mua sắm tại Quyền Sport.

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
