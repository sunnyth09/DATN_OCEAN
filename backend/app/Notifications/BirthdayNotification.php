<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BirthdayNotification — Thông báo chúc mừng sinh nhật
 *
 * Kênh gửi: mail (email thật) + database (inbox trong app)
 *
 * Logic:
 * - Nhận vào mã giảm giá (coupon_code) và giá trị giảm (discount_value)
 * - Gửi email HTML chúc mừng sinh nhật kèm mã giảm giá
 * - Lưu notification vào bảng notifications để user xem trong inbox
 */
class BirthdayNotification extends Notification
{
    use Queueable;

    protected string $couponCode;

    protected string $discountValue;

    /**
     * Constructor — nhận thông tin mã giảm giá
     *
     * @param  string  $couponCode  Mã giảm giá (VD: "BIRTHDAY-5-0402")
     * @param  string  $discountValue  Giá trị giảm (VD: "10%")
     */
    public function __construct(string $couponCode, string $discountValue)
    {
        $this->couponCode = $couponCode;
        $this->discountValue = $discountValue;
    }

    /**
     * via() — Xác định các kênh gửi notification
     *
     * Cú pháp: return mảng chứa tên kênh
     * - 'mail'     → gửi email qua SMTP
     * - 'database' → lưu vào bảng notifications
     *
     * @param  mixed  $notifiable  Đối tượng nhận notification (User model)
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * toMail() — Xây dựng nội dung email
     *
     * Sử dụng MailMessage fluent API để tạo email HTML:
     * - subject()  → Tiêu đề email
     * - greeting() → Lời chào (sử dụng tên user)
     * - line()     → Dòng nội dung
     * - action()   → Nút CTA (Call-to-Action) dẫn tới trang web
     *
     * @param  mixed  $notifiable  User model (có trường full_name, email)
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:3302')), '/');

        return (new MailMessage)
            ->subject('[Ocean Sport] 🎂 Chúc Mừng Sinh Nhật — Quà tặng đặc biệt dành riêng cho bạn!')
            ->greeting("Xin chào {$notifiable->full_name}! 🎉")
            ->line('Hôm nay là sinh nhật của bạn! Đội ngũ Ocean Sport xin gửi lời chúc mừng sinh nhật tốt đẹp, may mắn và hạnh phúc nhất! 🎊')
            ->line("🎁 Quà tặng sinh nhật: Mã giảm giá **{$this->discountValue}**")
            ->line("📋 Mã coupon của bạn: **{$this->couponCode}**")
            ->line('⏰ Mã có hiệu lực trong 7 ngày kể từ hôm nay.')
            ->action('🛒 Mua sắm & Áp dụng mã ngay ➔', $frontendUrl.'/coupon')
            ->line('Chúc bạn có một ngày sinh nhật thật trọn vẹn và nhiều niềm vui! 🥳');
    }

    /**
     * toDatabase() / toArray() — Dữ liệu lưu vào bảng notifications (cột data)
     *
     * Cú pháp: return mảng associative, Laravel tự động JSON encode
     * Dữ liệu này sẽ được frontend đọc để hiển thị trong inbox notification
     *
     * @param  mixed  $notifiable  User model
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => 'birthday',
            'title' => '🎂 Chúc Mừng Sinh Nhật!',
            'message' => "Chúc mừng sinh nhật {$notifiable->full_name}! Bạn được tặng mã giảm giá {$this->discountValue}: {$this->couponCode}. Hạn sử dụng 7 ngày.",
            'coupon_code' => $this->couponCode,
            'discount_value' => $this->discountValue,
        ];
    }
}
