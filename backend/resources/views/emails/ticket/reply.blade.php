@component('mail::message')
# 📋 Cập Nhật Khiếu Nại #{{ $ticketId }}

Xin chào **{{ $customerName }}**,

Khiếu nại của bạn tại **Ocean Sport** vừa được cập nhật. Dưới đây là thông tin chi tiết:

---

## Thông tin khiếu nại

| Nội dung | Chi tiết |
|---|---|
| **Mã khiếu nại** | `#{{ $ticketId }}` |
@if($orderCode)
| **Mã đơn hàng** | `#{{ $orderCode }}` |
@endif
| **Lý do khiếu nại** | {{ $reason }} |
| **Trạng thái hiện tại** | {{ $statusText }} |

---

@if($status === 'resolved')
✅ **Khiếu nại của bạn đã được giải quyết!**
@elseif($status === 'processing')
🔄 **Chúng tôi đang tiến hành xử lý khiếu nại của bạn.**
@elseif($status === 'closed')
🔒 **Khiếu nại đã được đóng.**
@else
⏳ **Khiếu nại của bạn đang trong hàng đợi xử lý.**
@endif

@if($adminReply)

---

## 💬 Phản hồi từ Admin

<div style="background: #f8f9fa; border-left: 4px solid #E63B6F; padding: 12px 16px; border-radius: 0 8px 8px 0; margin: 8px 0;">
{{ $adminReply }}
</div>

@endif

---

@component('mail::button', ['url' => $profileUrl, 'color' => 'primary'])
📋 Xem lịch sử khiếu nại
@endcomponent

Cảm ơn bạn đã tin tưởng **Ocean Sport**. Nếu có bất kỳ thắc mắc nào, vui lòng liên hệ với chúng tôi.

**OCEAN SPORT — CỬA HÀNG THỂ THAO CAO CẤP**<br>
Hotline: **1900 6868** | Email: **contact@oceansport.vn**

<small>Email này được gửi tự động từ hệ thống Ocean Sport khi có cập nhật về khiếu nại của bạn.</small>
@endcomponent

