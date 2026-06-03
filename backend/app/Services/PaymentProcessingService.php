<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\VNPayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentProcessingService
{
    /**
     * Mapping VNPay response codes → thông báo tiếng Việt
     */
    private const VNPAY_RESPONSE_MESSAGES = [
        '07' => 'Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo, giao dịch bất thường).',
        '09' => 'Giao dịch không thành công: Thẻ/Tài khoản chưa đăng ký dịch vụ InternetBanking tại ngân hàng.',
        '10' => 'Giao dịch không thành công: Xác thực thông tin thẻ/tài khoản không đúng quá 3 lần.',
        '11' => 'Giao dịch không thành công: Đã hết hạn chờ thanh toán. Xin quý khách vui lòng thực hiện lại.',
        '12' => 'Giao dịch không thành công: Thẻ/Tài khoản bị khóa.',
        '13' => 'Giao dịch không thành công: Nhập sai mật khẩu xác thực (OTP). Xin quý khách vui lòng thực hiện lại.',
        '24' => 'Giao dịch không thành công: Khách hàng hủy giao dịch.',
        '51' => 'Giao dịch không thành công: Tài khoản không đủ số dư để thực hiện giao dịch.',
        '65' => 'Giao dịch không thành công: Tài khoản đã vượt quá hạn mức giao dịch trong ngày.',
        '75' => 'Ngân hàng thanh toán đang bảo trì.',
        '79' => 'Giao dịch không thành công: Nhập sai mật khẩu thanh toán quá số lần quy định.',
        '99' => 'Có lỗi xảy ra trong quá trình thanh toán.',
    ];

    /**
     * Xử lý VNPay Return URL (user redirect)
     */
    public function handleVnpayReturn(array $queryParams, string $ip): array
    {
        // Verify checksum
        $result = VNPayService::verifyReturn($queryParams);

        if (!$result['isValid']) {
            Log::warning('VNPay Return: Invalid secure hash', ['params' => $queryParams, 'ip' => $ip]);
            return [
                '_status'        => 400,
                'status'         => 'error',
                'message'        => 'Chữ ký không hợp lệ. Giao dịch có thể bị giả mạo.',
                'payment_status' => 'failed',
            ];
        }

        // Process in transaction
        $order = DB::transaction(function () use ($result, $queryParams, $ip) {
            $order = Order::where('order_code', $result['txnRef'])->lockForUpdate()->first();

            if (!$order) return null;
            if ($order->payment_status === 'paid') return $order; // idempotent

            if ($result['responseCode'] === '00') {
                // Verify amount
                if (abs($result['amount'] - $order->grand_total) > 1) {
                    Log::error('VNPay Return: Amount mismatch', [
                        'vnpay_amount' => $result['amount'],
                        'order_total'  => $order->grand_total,
                        'ip'           => $ip,
                    ]);
                    return 'amount_mismatch';
                }

                $order->update(['payment_status' => 'paid']);
                $this->upsertPayment($order, $result, $queryParams, 'success');

                Log::info('VNPay Return: Payment success', [
                    'order_code'     => $order->order_code,
                    'transaction_no' => $result['transactionNo'],
                    'ip'             => $ip,
                ]);
            } else {
                $order->update(['payment_status' => 'failed']);
                $this->upsertPayment($order, $result, $queryParams, 'failed');

                Log::info('VNPay Return: Payment failed', [
                    'order_code'    => $order->order_code,
                    'response_code' => $result['responseCode'],
                    'ip'            => $ip,
                ]);
            }

            return $order;
        });

        // Process result
        if ($order === null) {
            Log::error('VNPay Return: Order not found', ['txnRef' => $result['txnRef']]);
            return ['_status' => 404, 'status' => 'error', 'message' => 'Không tìm thấy đơn hàng.', 'payment_status' => 'failed'];
        }

        if ($order === 'amount_mismatch') {
            return ['_status' => 400, 'status' => 'error', 'message' => 'Số tiền thanh toán không khớp với đơn hàng.', 'payment_status' => 'failed'];
        }

        // Success
        if ($order->payment_status === 'paid' && $result['responseCode'] === '00') {
            $this->dispatchPostPaymentActions($order);

            return [
                '_status'        => 200,
                'status'         => 'success',
                'message'        => 'Thanh toán thành công!',
                'payment_status' => 'paid',
                'data'           => [
                    'order_code'     => $order->order_code,
                    'grand_total'    => $order->grand_total,
                    'transaction_no' => $result['transactionNo'],
                    'bank_code'      => $result['bankCode'],
                    'pay_date'       => $result['payDate'],
                ],
            ];
        }

        // Failed
        return [
            '_status'        => 200,
            'status'         => 'error',
            'message'        => $this->getResponseMessage($result['responseCode']),
            'payment_status' => 'failed',
            'data'           => [
                'order_code'    => $order->order_code,
                'grand_total'   => $order->grand_total,
                'response_code' => $result['responseCode'],
            ],
        ];
    }

    /**
     * Xử lý VNPay IPN (server-to-server)
     */
    public function handleVnpayIpn(array $queryParams, string $ip): array
    {
        Log::info('VNPay IPN received', [
            'txnRef' => $queryParams['vnp_TxnRef'] ?? 'N/A',
            'ip'     => $ip,
        ]);

        $result = VNPayService::verifyReturn($queryParams);

        if (!$result['isValid']) {
            Log::warning('VNPay IPN: Invalid checksum', ['ip' => $ip]);
            return ['RspCode' => '97', 'Message' => 'Invalid Checksum'];
        }

        $rspCode    = '00';
        $rspMessage = 'Confirm Success';

        DB::transaction(function () use ($result, $queryParams, $ip, &$rspCode, &$rspMessage) {
            $order = Order::where('order_code', $result['txnRef'])->lockForUpdate()->first();

            if (!$order) {
                $rspCode = '01';
                $rspMessage = 'Order not Found';
                return;
            }

            if ($order->payment_status === 'paid') {
                $rspCode = '02';
                $rspMessage = 'Order already confirmed';
                return;
            }

            if (abs($result['amount'] - $order->grand_total) > 1) {
                Log::error('VNPay IPN: Amount mismatch', [
                    'vnpay_amount' => $result['amount'],
                    'order_total'  => $order->grand_total,
                    'ip'           => $ip,
                ]);
                $rspCode = '04';
                $rspMessage = 'Invalid Amount';
                return;
            }

            if ($result['responseCode'] === '00') {
                $order->update(['payment_status' => 'paid']);
                $this->upsertPayment($order, $result, $queryParams, 'success');

                Log::info('VNPay IPN: Payment confirmed', [
                    'order_code'     => $order->order_code,
                    'transaction_no' => $result['transactionNo'],
                    'ip'             => $ip,
                ]);

                $this->dispatchPostPaymentActions($order);
            } else {
                $order->update(['payment_status' => 'failed']);
                $this->upsertPayment($order, $result, $queryParams, 'failed');

                Log::info('VNPay IPN: Payment failed', [
                    'order_code'    => $order->order_code,
                    'response_code' => $result['responseCode'],
                ]);
            }
        });

        return ['RspCode' => $rspCode, 'Message' => $rspMessage];
    }

    /**
     * Dispatch email + notification + cart cleanup
     */
    public function dispatchPostPaymentActions(Order $order): void
    {
        // Cart cleanup
        try {
            $cart = Cart::where('user_id', $order->user_id)->where('status', 'active')->first();
            if ($cart) {
                $orderVariantIds = $order->items()->pluck('variant_id')->toArray();
                CartItem::where('cart_id', $cart->cart_id)
                    ->whereIn('variant_id', $orderVariantIds)
                    ->where('selected', true)
                    ->delete();

                Log::info('VNPay post-payment: Cart items cleared', [
                    'order_code' => $order->order_code,
                    'user_id'    => $order->user_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('VNPay post-payment: Cart cleanup failed', [
                'order_code' => $order->order_code,
                'error'      => $e->getMessage(),
            ]);
        }

        // Admin realtime event
        try {
            event(new \App\Events\OrderCreatedAdmin($order));
        } catch (\Exception $e) {
            Log::error('VNPay post-payment: Realtime event failed', [
                'order_code' => $order->order_code,
                'error'      => $e->getMessage(),
            ]);
        }

        // Email + notification
        try {
            $this->sendPaymentConfirmationEmail($order);

            $methodLabel = 'VNPay';
            if ($order->payment_method === 'bank_transfer') {
                $methodLabel = 'Chuyển khoản ngân hàng (SePay)';
            } elseif ($order->payment_method === 'momo') {
                $methodLabel = 'Ví MoMo';
            }

            $notificationData = [
                'title'       => 'Thanh toán thành công',
                'message'     => 'Đơn hàng ' . $order->order_code . ' đã được thanh toán thành công qua ' . $methodLabel . '.',
                'order_code'  => $order->order_code,
                'grand_total' => $order->grand_total,
                'type'        => 'payment_success',
            ];

            DB::table('notifications')->insert([
                'id'              => Str::uuid(),
                'type'            => 'App\\Notifications\\OrderPaidNotification',
                'notifiable_type' => \App\Models\User::class,
                'notifiable_id'   => $order->user_id,
                'data'            => json_encode($notificationData),
                'read_at'         => null,
                'created_at'      => Carbon::now(),
                'updated_at'      => Carbon::now(),
            ]);

            event(new \App\Events\UserNotificationEvent($order->user_id, $notificationData));
        } catch (\Exception $e) {
            Log::error('VNPay post-payment: Email/notification failed', [
                'order_code' => $order->order_code,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    public function sendPaymentConfirmationEmail(Order $order): void
    {
        $order->load(['items', 'user']);
        $user = $order->user;

        if (!$user || empty($user->email)) return;

        $methodLabel = 'VNPay';
        if ($order->payment_method === 'bank_transfer') {
            $methodLabel = 'Chuyển khoản ngân hàng (SePay)';
        } elseif ($order->payment_method === 'momo') {
            $methodLabel = 'Ví MoMo';
        }

        $emailUser = config('mail.mailers.smtp.username');
        $emailPass = config('mail.mailers.smtp.password');

        if (!$emailUser) {
            $emailUser = config('services.email.username');
            $emailPass = config('services.email.password');
        }

        if (!$emailUser || !$emailPass) {
            Log::warning('Skip sending payment confirmation email: mail credentials missing.');
            return;
        }

        $transport = new \Symfony\Component\Mailer\Transport\Mip\EsmtpTransport('smtp.gmail.com', 587, false);
        // Fallback ESmtpTransport
        $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport('smtp.gmail.com', 587, false);
        $transport->setUsername($emailUser);
        $transport->setPassword($emailPass);
        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        $itemsHtml = '';
        foreach ($order->items as $item) {
            $variantInfo = $item->variant_name ? '(' . $item->color . '/' . $item->size . ')' : '';
            $itemsHtml .= '
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">' . htmlspecialchars($item->product_name) . ' ' . $variantInfo . ' x' . $item->quantity . '</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;">' . number_format($item->line_total, 0, ',', '.') . 'đ</td>
            </tr>';
        }

        $frontendUrl = config('app.frontend_url');

        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; background: #f9fafb; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="background: #16a34a; padding: 20px; text-align: center; color: white;">
                    <h2 style="margin: 0;">Thanh toán thành công!</h2>
                    <p style="margin: 5px 0 0;">Đơn hàng ' . $order->order_code . ' đã được xác nhận thanh toán qua ' . $methodLabel . '</p>
                </div>
                <div style="padding: 20px;">
                    <p>Xin chào <strong>' . htmlspecialchars($order->recipient_name) . '</strong>,</p>
                    <p>Chúng tôi xác nhận đơn hàng <strong>' . $order->order_code . '</strong> đã được thanh toán thành công vào lúc ' . now()->format('H:i d/m/Y') . '.</p>
                    
                    <h3 style="border-bottom: 2px solid #16a34a; padding-bottom: 5px; color: #333;">Chi tiết đơn hàng</h3>
                    <table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom: 20px;">
                        ' . $itemsHtml . '
                        <tr>
                            <td style="padding: 10px; text-align: right;">Tạm tính:</td>
                            <td style="padding: 10px; text-align: right;">' . number_format($order->subtotal, 0, ',', '.') . 'đ</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; text-align: right;">Phí vận chuyển:</td>
                            <td style="padding: 10px; text-align: right;">' . number_format($order->shipping_fee, 0, ',', '.') . 'đ</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; text-align: right;">Khuyến mãi:</td>
                            <td style="padding: 10px; text-align: right; color: green;">-' . number_format($order->discount_amount, 0, ',', '.') . 'đ</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; text-align: right; font-weight: bold; font-size: 16px;">TỔNG CỘNG:</td>
                            <td style="padding: 10px; text-align: right; font-weight: bold; font-size: 16px; color: #e53e3e;">' . number_format($order->grand_total, 0, ',', '.') . 'đ</td>
                        </tr>
                    </table>

                    <div style="background: #dcfce7; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;">
                        <p style="margin: 0; color: #166534;"><strong>Phương thức thanh toán:</strong> ' . $methodLabel . ' (Đã thanh toán)</p>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <a href="' . $frontendUrl . '/profile/orders" style="background: #0288d1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Xem lịch sử đơn hàng</a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';

        $emailMessage = (new \Symfony\Component\Mime\Email())
            ->from($emailUser)
            ->to($user->email)
            ->subject('Thanh toán thành công — Đơn hàng ' . $order->order_code)
            ->html($htmlBody);

        $mailer->send($emailMessage);

        Log::info('Payment confirmation email sent', [
            'order_code' => $order->order_code,
            'to'         => $user->email,
        ]);
    }

    private function upsertPayment(Order $order, array $result, array $queryParams, string $status): void
    {
        $data = [
            'transaction_code' => $result['transactionNo'],
            'amount'           => $result['amount'],
            'status'           => $status,
            'gateway_response' => $queryParams,
        ];

        if ($status === 'success') {
            $data['paid_at'] = now();
        }

        Payment::updateOrCreate(
            ['order_id' => $order->order_id, 'payment_method' => 'vnpay'],
            $data
        );
    }

    private function getResponseMessage(string $code): string
    {
        return self::VNPAY_RESPONSE_MESSAGES[$code] ?? 'Giao dịch không thành công. Mã lỗi: ' . $code;
    }
}
