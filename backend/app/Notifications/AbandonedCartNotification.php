<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * AbandonedCartNotification — Thông báo nhắc nhở giỏ hàng bỏ quên
 *
 * Kênh gửi: mail + database (inbox)
 * Tính chất: Tế nhị, nhẹ nhàng — không gây phiền cho user
 *
 * Logic:
 * - Nhận thông tin số lượng sản phẩm trong giỏ hàng và điểm thưởng đã tặng
 * - Gửi email nhẹ nhàng nhắc nhở kèm thông tin điểm thưởng
 * - Lưu notification vào inbox
 */
class AbandonedCartNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected int $itemCount;

    protected int $pointsAwarded;

    /**
     * Constructor
     *
     * @param  int  $itemCount  Số sản phẩm trong giỏ hàng
     * @param  int  $pointsAwarded  Số điểm thưởng đã tặng
     */
    public function __construct(int $itemCount, int $pointsAwarded)
    {
        $this->itemCount = $itemCount;
        $this->pointsAwarded = $pointsAwarded;
    }

    /**
     * via() — Kênh gửi: mail + database
     *
     * Gửi cả email (tế nhị) + lưu inbox
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * toMail() — Email nhắc nhở tế nhị
     *
     * Nội dung nhẹ nhàng, không ép buộc user phải thanh toán
     * Tặng điểm thưởng như một gesture thiện chí
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:3302')), '/');

        return (new MailMessage)
            ->subject('[Ocean Sport] 🛒 Giỏ hàng của bạn đang chờ — Hoàn tất đơn nhận ngay ưu đãi!')
            ->greeting("Xin chào {$notifiable->full_name}! 👋")
            ->line("Bạn có **{$this->itemCount} sản phẩm** đang nằm trong giỏ hàng, đừng để lỡ mất những món đồ thể thao yêu thích nhé! 😊")
            ->line('Ocean Sport vẫn đang lưu giữ giỏ hàng cho bạn để bạn có thể tiếp tục thanh toán bất kỳ lúc nào.')
            ->line("🎁 Ngoài ra, Ocean Sport tặng thêm cho bạn **{$this->pointsAwarded} điểm thưởng** vào tài khoản tích điểm!")
            ->action('🛒 Quay lại giỏ hàng & Đặt ngay ➔', $frontendUrl.'/cart')
            ->line('Nếu bạn cần hỗ trợ thêm, hãy liên hệ Hotline 1900 6868 của chúng tôi nhé! 💙');
    }

    /**
     * toArray() — Dữ liệu lưu vào bảng notifications
     *
     * Chứa thông tin số sản phẩm và điểm thưởng đã tặng
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => 'abandoned_cart',
            'title' => '🛒 Giỏ hàng đang chờ bạn!',
            'message' => "Bạn có {$this->itemCount} sản phẩm trong giỏ hàng. Chúng tôi đã tặng bạn {$this->pointsAwarded} điểm thưởng!",
            'item_count' => $this->itemCount,
            'points_awarded' => $this->pointsAwarded,
        ];
    }
}
