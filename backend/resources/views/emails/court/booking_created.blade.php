@component('mail::message')
# 🏸 Đặt sân thành công!

Xin chào **{{ $userName }}**,

Chúng tôi đã nhận được yêu cầu đặt sân của bạn. Vui lòng chờ xác nhận từ nhân viên.

---

## Thông tin đặt sân

| Nội dung | Chi tiết |
|---|---|
| **Mã đặt sân** | `{{ $bookingCode }}` |
| **Sân** | {{ $courtName }} |
| **Ngày** | {{ $bookingDate }} |
| **Giờ** | {{ $startTime }} — {{ $endTime }} |
| **Tổng tiền** | **{{ $totalAmount }}** |

@if($services && count($services) > 0)
### Dịch vụ đặt kèm

@foreach($services as $item)
- {{ $item->service?->service_name ?? 'Dịch vụ' }} × {{ $item->quantity }} = {{ number_format($item->subtotal, 0, ',', '.') }}đ
@endforeach
@endif

---

### Hướng dẫn check-in

Khi đến sân, bạn có thể **xuất mã QR** từ trang lịch đặt sân của mình để nhân viên quét nhanh. Vui lòng đến sớm **ít nhất 10 phút** trước giờ chơi.

@component('mail::button', ['url' => rtrim(config('app.frontend_url', 'http://localhost:3302'), '/') . '/profile/court-bookings', 'color' => 'success'])
📱 Xem chi tiết lịch đặt sân
@endcomponent

Cảm ơn bạn đã tin tưởng và sử dụng dịch vụ của chúng tôi!

**OCEAN SPORT — HỆ THỐNG SÂN CẦU LÔNG CHUYÊN NGHIỆP**<br>
Hotline: **1900 6868** | Email: **contact@oceansport.vn**

<small>Email này được gửi tự động từ hệ thống Ocean Sport.</small>
@endcomponent
