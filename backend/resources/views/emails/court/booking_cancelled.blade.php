@component('mail::message')
# ❌ Lịch đặt sân đã bị hủy

Xin chào **{{ $userName }}**,

@if($cancelledBy === 'admin')
Chúng tôi rất tiếc phải thông báo rằng lịch đặt sân của bạn đã bị **hủy bởi nhân viên** do sự cố phát sinh.
@else
Lịch đặt sân của bạn đã được **hủy thành công** theo yêu cầu.
@endif

---

## Thông tin lịch đã hủy

| Nội dung | Chi tiết |
|---|---|
| **Mã đặt sân** | `{{ $bookingCode }}` |
| **Sân** | {{ $courtName }} |
| **Ngày** | {{ $bookingDate }} |
| **Giờ** | {{ $startTime }} — {{ $endTime }} |
| **Lý do hủy** | {{ $cancelReason }} |

---

@if($refundAmount)
### 💰 Thông tin hoàn tiền

Số tiền **{{ $refundAmount }}** sẽ được hoàn lại vào phương thức thanh toán ban đầu của bạn trong vòng **3-5 ngày làm việc**.

> Nếu sau 5 ngày bạn chưa nhận được, vui lòng liên hệ trực tiếp với chúng tôi.
@else
### Hoàn tiền

Không có khoản hoàn tiền cho lần hủy này @if($cancelledBy === 'user')(hủy trong vòng 2 giờ trước giờ chơi)@endif.
@endif

---

@component('mail::button', ['url' => rtrim(config('app.frontend_url', 'http://localhost:3302'), '/') . '/courts', 'color' => 'primary'])
🏸 Khám phá & Đặt sân khác
@endcomponent

Chúng tôi xin lỗi vì sự bất tiện này và mong sớm được phục vụ bạn trong lần tới!

**OCEAN SPORT — HỆ THỐNG SÂN CẦU LÔNG CHUYÊN NGHIỆP**<br>
Hotline: **1900 6868** | Email: **contact@oceansport.vn**

<small>Email này được gửi tự động từ hệ thống Ocean Sport.</small>
@endcomponent
