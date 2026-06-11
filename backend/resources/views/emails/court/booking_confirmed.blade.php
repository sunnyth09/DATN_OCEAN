@component('mail::message')
# ✅ Lịch đặt sân đã được xác nhận!

Xin chào **{{ $userName }}**,

Tuyệt vời! Lịch đặt sân của bạn đã được **xác nhận** bởi nhân viên. Chúng tôi rất mong được phục vụ bạn.

---

## Chi tiết lịch hẹn

| Nội dung | Chi tiết |
|---|---|
| **Mã đặt sân** | `{{ $bookingCode }}` |
| **Sân** | {{ $courtName }} |
| **Ngày thi đấu** | {{ $bookingDate }} |
| **Giờ thi đấu** | {{ $startTime }} — {{ $endTime }} |
| **Tổng tiền** | **{{ $totalAmount }}** |

---

### 📌 Lưu ý quan trọng

- Vui lòng đến sân **trước ít nhất 15 phút** so với giờ đặt
- Xuất **mã QR check-in** từ ứng dụng khi đến để được nhận sân nhanh
- Nếu không check-in trong vòng **15 phút** sau giờ bắt đầu, lịch sẽ bị hủy tự động

@component('mail::button', ['url' => config('app.frontend_url', 'http://localhost:5173') . '/my-bookings', 'color' => 'success'])
📱 Xem mã QR Check-in
@endcomponent

Chúc bạn có buổi tập luyện vui vẻ! 🏸

**{{ config('app.name') }}**

<small>Email này được gửi tự động, vui lòng không reply. Nếu cần hỗ trợ, liên hệ trực tiếp với sân.</small>
@endcomponent
