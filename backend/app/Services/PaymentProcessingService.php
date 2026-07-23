<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentProcessingService
{
    private const CALLBACK_SOURCE_RETURN = 'return';
    private const CALLBACK_SOURCE_IPN = 'ipn';

    private const POST_PAYMENT_STATUS_PROCESSING = 'processing';
    private const POST_PAYMENT_STATUS_COMPLETED = 'completed';
    private const POST_PAYMENT_STATUS_FAILED = 'failed';

    private const POST_PAYMENT_LOCK_TIMEOUT_SECONDS = 300;

    public function __construct(
        protected ?WalletService $walletService = null
    ) {}

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
     * Return URL chỉ ghi nhận callback và trả trạng thái cho UI.
     * Không được chạy side effects tại đây.
     */
    public function handleVnpayReturn(array $queryParams, string $ip): array
    {
        $result = VNPayService::verifyReturn($queryParams);

        if (!$result['isValid']) {
            Log::warning('VNPay Return: Invalid secure hash', ['params' => $queryParams, 'ip' => $ip]);

            return [
                '_status' => 400,
                'status' => 'error',
                'message' => 'Chữ ký không hợp lệ. Giao dịch có thể bị giả mạo.',
                'payment_status' => 'failed',
            ];
        }

        // ── Wallet Deposit (WDP prefix) ──
        if (str_starts_with($result['txnRef'], 'WDP')) {
            return $this->handleWalletDepositReturn($result);
        }

        $outcome = DB::transaction(function () use ($result, $queryParams, $ip) {
            $order = Order::where('order_code', $result['txnRef'])->lockForUpdate()->first();

            if (!$order) {
                return ['type' => 'order_not_found'];
            }

            if (abs($result['amount'] - $order->grand_total) > 1) {
                Log::error('VNPay Return: Amount mismatch', [
                    'order_code' => $order->order_code,
                    'vnpay_amount' => $result['amount'],
                    'order_total' => $order->grand_total,
                    'ip' => $ip,
                ]);

                return ['type' => 'amount_mismatch'];
            }

            $payment = $this->getOrCreateVnpayPayment($order);
            $callbackStatus = $result['responseCode'] === '00' ? 'success' : 'failed';

            $this->syncPaymentFromCallback(
                $payment,
                $order,
                $result,
                $queryParams,
                self::CALLBACK_SOURCE_RETURN,
                $callbackStatus,
                false
            );

            $order->refresh();
            $payment->refresh();

            $isConfirmed = $this->isPaymentConfirmed($order, $payment);

            if ($callbackStatus === 'failed' && !$isConfirmed) {
                Log::info('VNPay Return: Payment failed before confirmation', [
                    'order_code' => $order->order_code,
                    'response_code' => $result['responseCode'],
                    'ip' => $ip,
                ]);

                return [
                    'type' => 'failed',
                    'order' => $order,
                    'payment' => $payment,
                    'result' => $result,
                ];
            }

            if ($callbackStatus === 'failed' && $isConfirmed) {
                Log::warning('VNPay Return: Ignored conflicting failed callback after confirmation', [
                    'order_code' => $order->order_code,
                    'transaction_no' => $payment->transaction_code,
                    'response_code' => $result['responseCode'],
                    'ip' => $ip,
                ]);
            } else {
                Log::info('VNPay Return: Callback recorded', [
                    'order_code' => $order->order_code,
                    'transaction_no' => $result['transactionNo'],
                    'confirmed' => $isConfirmed,
                    'ip' => $ip,
                ]);
            }

            return [
                'type' => $isConfirmed ? 'confirmed' : 'awaiting_ipn',
                'order' => $order,
                'payment' => $payment,
                'result' => $result,
            ];
        });

        if ($outcome['type'] === 'order_not_found') {
            Log::error('VNPay Return: Order not found', ['txnRef' => $result['txnRef']]);

            return [
                '_status' => 404,
                'status' => 'error',
                'message' => 'Không tìm thấy đơn hàng.',
                'payment_status' => 'failed',
            ];
        }

        if ($outcome['type'] === 'amount_mismatch') {
            return [
                '_status' => 400,
                'status' => 'error',
                'message' => 'Số tiền thanh toán không khớp với đơn hàng.',
                'payment_status' => 'failed',
            ];
        }

        $order = $outcome['order'];
        $payment = $outcome['payment'];

        if ($outcome['type'] === 'failed') {
            return [
                '_status' => 200,
                'status' => 'error',
                'message' => $this->getResponseMessage($result['responseCode']),
                'payment_status' => $order->payment_status,
                'data' => [
                    'order_code' => $order->order_code,
                    'grand_total' => $order->grand_total,
                    'response_code' => $result['responseCode'],
                ],
            ];
        }

        if ($outcome['type'] === 'awaiting_ipn') {
            return [
                '_status' => 202,
                'status' => 'processing',
                'message' => 'Giao dịch đã được ghi nhận. Hệ thống đang chờ IPN từ VNPay để xác nhận thanh toán.',
                'payment_status' => $order->payment_status,
                'data' => [
                    'order_code' => $order->order_code,
                    'grand_total' => $order->grand_total,
                    'transaction_no' => $payment->transaction_code,
                ],
            ];
        }

        return [
            '_status' => 200,
            'status' => 'success',
            'message' => 'Thanh toán thành công!',
            'payment_status' => $order->payment_status,
            'data' => [
                'order_code' => $order->order_code,
                'grand_total' => $order->grand_total,
                'transaction_no' => $payment->transaction_code,
                'bank_code' => $result['bankCode'],
                'pay_date' => $result['payDate'],
                'confirmed_at' => optional($payment->confirmed_at)->toDateTimeString(),
                'post_payment_status' => $payment->post_payment_status,
            ],
        ];
    }

    /**
     * IPN là nguồn xác nhận thanh toán chính.
     * Tại đây vừa confirm payment, vừa claim quyền chạy side effects đúng 1 lần.
     */
    public function handleVnpayIpn(array $queryParams, string $ip): array
    {
        Log::info('VNPay IPN received', [
            'txnRef' => $queryParams['vnp_TxnRef'] ?? 'N/A',
            'ip' => $ip,
        ]);

        $result = VNPayService::verifyReturn($queryParams);

        if (!$result['isValid']) {
            Log::warning('VNPay IPN: Invalid checksum', ['ip' => $ip]);

            return ['RspCode' => '97', 'Message' => 'Invalid Checksum'];
        }

        // ── Wallet Deposit (WDP prefix) ──
        if (str_starts_with($result['txnRef'], 'WDP')) {
            return $this->handleWalletDepositIpn($result);
        }

        $outcome = DB::transaction(function () use ($result, $queryParams, $ip) {
            $order = Order::where('order_code', $result['txnRef'])->lockForUpdate()->first();

            if (!$order) {
                return ['type' => 'order_not_found'];
            }

            if (abs($result['amount'] - $order->grand_total) > 1) {
                Log::error('VNPay IPN: Amount mismatch', [
                    'order_code' => $order->order_code,
                    'vnpay_amount' => $result['amount'],
                    'order_total' => $order->grand_total,
                    'ip' => $ip,
                ]);

                return ['type' => 'amount_mismatch'];
            }

            $payment = $this->getOrCreateVnpayPayment($order);
            $callbackStatus = $result['responseCode'] === '00' ? 'success' : 'failed';

            $this->syncPaymentFromCallback(
                $payment,
                $order,
                $result,
                $queryParams,
                self::CALLBACK_SOURCE_IPN,
                $callbackStatus,
                $callbackStatus === 'success'
            );

            $payment->refresh();
            $order->refresh();

            if ($callbackStatus === 'failed') {
                Log::info('VNPay IPN: Payment failed', [
                    'order_code' => $order->order_code,
                    'response_code' => $result['responseCode'],
                    'ip' => $ip,
                ]);

                return ['type' => 'failed'];
            }

            $shouldRunSideEffects = $this->claimPostPaymentProcessing($payment, self::CALLBACK_SOURCE_IPN);

            Log::info('VNPay IPN: Payment confirmed', [
                'order_code' => $order->order_code,
                'transaction_no' => $payment->transaction_code,
                'side_effects_claimed' => $shouldRunSideEffects,
                'post_payment_status' => $payment->post_payment_status,
                'ip' => $ip,
            ]);

            return [
                'type' => 'success',
                'order_id' => $order->order_id,
                'payment_id' => $payment->payment_id,
                'should_run_side_effects' => $shouldRunSideEffects,
            ];
        });

        if ($outcome['type'] === 'order_not_found') {
            return ['RspCode' => '01', 'Message' => 'Order not Found'];
        }

        if ($outcome['type'] === 'amount_mismatch') {
            return ['RspCode' => '04', 'Message' => 'Invalid Amount'];
        }

        if ($outcome['type'] === 'failed') {
            return ['RspCode' => '00', 'Message' => 'Confirm Success'];
        }

        if ($outcome['should_run_side_effects']) {
            $order = Order::with(['items', 'user'])->find($outcome['order_id']);

            if (!$order) {
                $this->markPostPaymentFailed($outcome['payment_id'], 'Order missing after payment confirmation.');

                return ['RspCode' => '99', 'Message' => 'Post-payment actions failed'];
            }

            try {
                $this->dispatchPostPaymentActions($order);
                $this->markPostPaymentCompleted($outcome['payment_id']);
            } catch (\Throwable $e) {
                Log::error('VNPay IPN: Post-payment actions failed', [
                    'order_id' => $outcome['order_id'],
                    'payment_id' => $outcome['payment_id'],
                    'error' => $e->getMessage(),
                ]);

                $this->markPostPaymentFailed($outcome['payment_id'], $e->getMessage());

                return ['RspCode' => '99', 'Message' => 'Post-payment actions failed'];
            }
        }

        return ['RspCode' => '00', 'Message' => 'Confirm Success'];
    }

    /**
     * Dispatch email + notification + cart cleanup.
     * Hàm này chỉ nên được gọi sau khi IPN đã claim quyền xử lý.
     */
    public function dispatchPostPaymentActions(Order $order): void
    {
        $payment = $this->getPaymentForOrder($order);

        if (!$payment) {
            throw new \RuntimeException('Missing payment row for post-payment actions.');
        }

        try {
            if ($order->user_id && !$this->hasCompletedPostPaymentStep($payment, 'cart_cleanup')) {
                $cart = Cart::where('user_id', $order->user_id)
                    ->where('status', 'active')
                    ->first();

                if ($cart) {
                    $orderVariantIds = $order->items()->pluck('variant_id')->filter()->toArray();

                    if ($orderVariantIds !== []) {
                        CartItem::where('cart_id', $cart->cart_id)
                            ->whereIn('variant_id', $orderVariantIds)
                            ->where('selected', true)
                            ->delete();
                    }
                }

                $this->markPostPaymentStepCompleted($payment, 'cart_cleanup');

                Log::info('VNPay post-payment: Cart items cleared', [
                    'order_code' => $order->order_code,
                    'user_id' => $order->user_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('VNPay post-payment: Cart cleanup failed', [
                'order_code' => $order->order_code,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        try {
            if (!$this->hasCompletedPostPaymentStep($payment, 'admin_event')) {
                event(new \App\Events\OrderCreatedAdmin($order));
                $this->markPostPaymentStepCompleted($payment, 'admin_event');
            }
        } catch (\Exception $e) {
            Log::error('VNPay post-payment: Realtime event failed', [
                'order_code' => $order->order_code,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        try {
            if (!$this->hasCompletedPostPaymentStep($payment, 'customer_email')) {
                $this->sendPaymentConfirmationEmail($order);
                $this->markPostPaymentStepCompleted($payment, 'customer_email');
            }

            $methodLabel = 'VNPay';
            if ($order->payment_method === 'bank_transfer') {
                $methodLabel = 'Chuyển khoản ngân hàng (SePay)';
            } elseif ($order->payment_method === 'momo') {
                $methodLabel = 'Ví MoMo';
            }

            if ($order->user_id && !$this->hasCompletedPostPaymentStep($payment, 'user_notification')) {
                $paymentEventKey = $this->makePostPaymentKey($order->order_code, $this->resolveTransactionCode($order));
                $notificationData = [
                    'title' => 'Thanh toán thành công',
                    'message' => 'Đơn hàng ' . $order->order_code . ' đã được thanh toán thành công qua ' . $methodLabel . '.',
                    'order_code' => $order->order_code,
                    'grand_total' => $order->grand_total,
                    'type' => 'payment_success',
                    'payment_event_key' => $paymentEventKey,
                ];

                DB::table('notifications')->updateOrInsert(
                    ['id' => $this->makeNotificationId($paymentEventKey)],
                    [
                        'type' => 'App\\Notifications\\OrderPaidNotification',
                        'notifiable_type' => \App\Models\User::class,
                        'notifiable_id' => $order->user_id,
                        'data' => json_encode($notificationData),
                        'read_at' => null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]
                );

                event(new \App\Events\UserNotificationEvent($order->user_id, $notificationData));
                $this->markPostPaymentStepCompleted($payment, 'user_notification');
            }
        } catch (\Exception $e) {
            Log::error('VNPay post-payment: Email/notification failed', [
                'order_code' => $order->order_code,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function sendPaymentConfirmationEmail(Order $order): bool
    {
        $order->loadMissing(['items', 'user']);
        $user = $order->user;
        $recipientEmail = $order->email ?: ($user->email ?? null);

        if (empty($recipientEmail)) {
            return false;
        }

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
            return false;
        }

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

        $actionUrl = $this->buildOrderActionUrl($order);
        $actionLabel = $order->user_id ? 'Xem lịch sử đơn hàng' : 'Theo dõi đơn hàng';

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
                        <a href="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '" style="background: #0288d1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">' . htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') . '</a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';

        $emailMessage = (new \Symfony\Component\Mime\Email())
            ->from($emailUser)
            ->to($recipientEmail)
            ->subject('Thanh toán thành công — Đơn hàng ' . $order->order_code)
            ->html($htmlBody);

        $mailer->send($emailMessage);

        Log::info('Payment confirmation email sent', [
            'order_code' => $order->order_code,
            'to' => $recipientEmail,
        ]);

        return true;
    }

    private function buildOrderActionUrl(Order $order): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url', 'http://localhost:3302')), '/');

        if ($order->user_id) {
            return $frontendUrl . '/profile/orders';
        }

        $token = $this->ensureTrackingToken($order);
        return $token ? $frontendUrl . '/tracking/' . $token : $frontendUrl . '/tracking';
    }

    private function ensureTrackingToken(Order $order): ?string
    {
        if ($order->tracking_token) {
            return $order->tracking_token;
        }

        $order->tracking_token = hash('sha256', $order->order_code . Str::random(40) . microtime(true));
        $order->save();

        return $order->tracking_token;
    }

    private function getOrCreateVnpayPayment(Order $order): Payment
    {
        $payment = Payment::where('order_id', $order->order_id)
            ->where('payment_method', 'vnpay')
            ->first();

        if ($payment) {
            return $payment;
        }

        return Payment::create([
            'order_id' => $order->order_id,
            'payment_method' => 'vnpay',
            'amount' => $order->grand_total,
            'status' => 'pending',
        ]);
    }

    private function syncPaymentFromCallback(
        Payment $payment,
        Order $order,
        array $result,
        array $queryParams,
        string $source,
        string $callbackStatus,
        bool $confirmPayment
    ): void {
        $gatewayResponse = $this->mergeGatewayResponse($payment->gateway_response, $source, $queryParams, $result);
        $isAlreadyConfirmed = $this->isPaymentConfirmed($order, $payment);

        $attributes = [
            'transaction_code' => $result['transactionNo'] ?: $payment->transaction_code,
            'amount' => $result['amount'],
            'gateway_response' => $gatewayResponse,
        ];

        if ($callbackStatus === 'failed') {
            if ($isAlreadyConfirmed) {
                $attributes['status'] = 'success';

                Log::warning('VNPay callback ignored because payment is already confirmed', [
                    'order_code' => $order->order_code,
                    'source' => $source,
                    'response_code' => $result['responseCode'],
                    'transaction_no' => $result['transactionNo'] ?? null,
                ]);
            } else {
                $attributes['status'] = 'failed';

                if ($source === self::CALLBACK_SOURCE_IPN && $order->payment_status !== 'paid') {
                    $order->update(['payment_status' => 'failed']);
                }
            }
        } elseif ($confirmPayment || $payment->status === 'success' || $isAlreadyConfirmed) {
            $attributes['status'] = 'success';
        } elseif ($payment->status === null) {
            $attributes['status'] = 'pending';
        }

        if ($confirmPayment) {
            $attributes['paid_at'] = $payment->paid_at ?? now();
            $attributes['confirmed_at'] = $payment->confirmed_at ?? now();
            $attributes['confirmed_source'] = $payment->confirmed_source ?? $source;

            if ($order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'paid']);
            }
        } elseif ($callbackStatus === 'failed') {
            $attributes['confirmed_source'] = $payment->confirmed_source;
        }

        $payment->forceFill($attributes)->save();
    }

    private function mergeGatewayResponse(
        mixed $existingGatewayResponse,
        string $source,
        array $queryParams,
        array $result
    ): array {
        $gatewayResponse = is_array($existingGatewayResponse) ? $existingGatewayResponse : [];
        $callbacks = $gatewayResponse['callbacks'] ?? [];
        $history = $callbacks[$source] ?? [];

        $history[] = [
            'received_at' => now()->toDateTimeString(),
            'response_code' => $result['responseCode'] ?? null,
            'transaction_no' => $result['transactionNo'] ?? null,
            'payload' => $queryParams,
        ];

        $callbacks[$source] = $history;
        $gatewayResponse['callbacks'] = $callbacks;

        return $gatewayResponse;
    }

    private function claimPostPaymentProcessing(Payment $payment, string $source): bool
    {
        if ($payment->post_payment_status === self::POST_PAYMENT_STATUS_COMPLETED) {
            return false;
        }

        if (
            $payment->post_payment_status === self::POST_PAYMENT_STATUS_PROCESSING
            && !$this->isPostPaymentProcessingStale($payment)
        ) {
            return false;
        }

        $payment->forceFill([
            'post_payment_key' => $this->makePostPaymentKey($payment->order->order_code, $payment->transaction_code),
            'post_payment_status' => self::POST_PAYMENT_STATUS_PROCESSING,
            'post_payment_started_at' => now(),
            'post_payment_processed_at' => null,
            'post_payment_source' => $source,
            'post_payment_last_error' => null,
        ])->save();

        return true;
    }

    private function isPostPaymentProcessingStale(Payment $payment): bool
    {
        if (!$payment->post_payment_started_at) {
            return true;
        }

        return $payment->post_payment_started_at->lt(
            now()->subSeconds(self::POST_PAYMENT_LOCK_TIMEOUT_SECONDS)
        );
    }

    private function markPostPaymentCompleted(int $paymentId): void
    {
        Payment::whereKey($paymentId)->update([
            'post_payment_status' => self::POST_PAYMENT_STATUS_COMPLETED,
            'post_payment_processed_at' => now(),
            'post_payment_last_error' => null,
        ]);
    }

    private function markPostPaymentFailed(int $paymentId, string $errorMessage): void
    {
        Payment::whereKey($paymentId)->update([
            'post_payment_status' => self::POST_PAYMENT_STATUS_FAILED,
            'post_payment_last_error' => mb_substr($errorMessage, 0, 2000),
        ]);
    }

    private function isPaymentConfirmed(Order $order, Payment $payment): bool
    {
        return $order->payment_status === 'paid' || $payment->confirmed_at !== null;
    }

    private function makePostPaymentKey(string $orderCode, ?string $transactionCode): string
    {
        return $orderCode . ':' . ($transactionCode ?: 'no-transaction');
    }

    private function resolveTransactionCode(Order $order): ?string
    {
        return Payment::where('order_id', $order->order_id)
            ->where('payment_method', $order->payment_method)
            ->value('transaction_code');
    }

    private function getPaymentForOrder(Order $order): ?Payment
    {
        return Payment::where('order_id', $order->order_id)
            ->where('payment_method', $order->payment_method)
            ->first();
    }

    private function hasCompletedPostPaymentStep(Payment $payment, string $step): bool
    {
        $steps = $payment->gateway_response['post_payment_steps'] ?? [];

        return !empty($steps[$step]['completed_at']);
    }

    private function markPostPaymentStepCompleted(Payment $payment, string $step): void
    {
        $gatewayResponse = is_array($payment->gateway_response) ? $payment->gateway_response : [];
        $steps = $gatewayResponse['post_payment_steps'] ?? [];

        $steps[$step] = [
            'completed_at' => now()->toDateTimeString(),
        ];

        $gatewayResponse['post_payment_steps'] = $steps;

        $payment->forceFill([
            'gateway_response' => $gatewayResponse,
        ])->save();
    }

    private function makeNotificationId(string $paymentEventKey): string
    {
        return substr(hash('sha256', 'payment-success:' . $paymentEventKey), 0, 36);
    }

    private function getResponseMessage(string $code): string
    {
        return self::VNPAY_RESPONSE_MESSAGES[$code] ?? 'Giao dịch không thành công. Mã lỗi: ' . $code;
    }

    // ═══════════════════════════════════════════════════════════════
    // WALLET DEPOSIT VIA VNPAY
    // ═══════════════════════════════════════════════════════════════

    /**
     * VNPay Return cho wallet deposit — trả kết quả cho UI.
     */
    private function handleWalletDepositReturn(array $result): array
    {
        $depositCode = $result['txnRef'];
        $isSuccess   = $result['responseCode'] === '00';

        if (!$isSuccess) {
            return [
                '_status' => 200,
                'status'  => 'error',
                'message' => $this->getResponseMessage($result['responseCode']),
                'payment_status' => 'failed',
                'data' => [
                    'deposit_code' => $depositCode,
                    'type'         => 'wallet_deposit',
                ],
            ];
        }

        return [
            '_status' => 200,
            'status'  => 'success',
            'message' => 'Nạp tiền vào ví thành công!',
            'payment_status' => 'paid',
            'data' => [
                'deposit_code' => $depositCode,
                'amount'       => $result['amount'],
                'type'         => 'wallet_deposit',
            ],
        ];
    }

    /**
     * VNPay IPN cho wallet deposit — xác nhận + credit vào ví.
     */
    private function handleWalletDepositIpn(array $result): array
    {
        $depositCode = $result['txnRef'];

        if ($result['responseCode'] !== '00') {
            Log::info('VNPay IPN: Wallet deposit payment failed', [
                'deposit_code'  => $depositCode,
                'response_code' => $result['responseCode'],
            ]);

            DB::table('wallet_deposits')
                ->where('deposit_code', $depositCode)
                ->where('status', 'pending')
                ->update(['status' => 'failed', 'updated_at' => now()]);

            return ['RspCode' => '00', 'Message' => 'Confirm Success'];
        }

        try {
            DB::transaction(function () use ($depositCode, $result) {
                $deposit = DB::table('wallet_deposits')
                    ->where('deposit_code', $depositCode)
                    ->lockForUpdate()
                    ->first();

                if (!$deposit) {
                    Log::warning('VNPay IPN Wallet: Deposit code not found', ['code' => $depositCode]);
                    return;
                }

                // Idempotency: bỏ qua nếu deposit đã ở trạng thái terminal (đã xử lý xong
                // hoặc đã đánh dấu thất bại). Chống double-credit khi VNPay gửi failed IPN
                // trước rồi success IPN sau cho cùng deposit_code.
                if (in_array($deposit->status, ['completed', 'failed'], true)) {
                    return;
                }

                // Verify amount
                if (abs($result['amount'] - (float) $deposit->amount) > 1) {
                    Log::error('VNPay IPN Wallet: Amount mismatch', [
                        'deposit_code' => $depositCode,
                        'expected'     => $deposit->amount,
                        'received'     => $result['amount'],
                    ]);
                    return;
                }

                // Credit vào ví
                $this->walletService->credit(
                    userId: $deposit->user_id,
                    amount: (float) $deposit->amount,
                    type: 'deposit',
                    opts: [
                        'description' => 'Nạp ví qua VNPay',
                        'metadata'    => [
                            'deposit_code'   => $depositCode,
                            'transaction_no' => $result['transactionNo'],
                            'bank_code'      => $result['bankCode'],
                            'method'         => 'vnpay',
                        ],
                    ]
                );

                // Cập nhật trạng thái deposit
                DB::table('wallet_deposits')
                    ->where('deposit_code', $depositCode)
                    ->update([
                        'status'                 => 'completed',
                        'gateway_transaction_id' => $result['transactionNo'],
                        'completed_at'           => now(),
                        'updated_at'             => now(),
                    ]);

                Log::info('VNPay IPN Wallet: Deposit completed', [
                    'user_id'      => $deposit->user_id,
                    'deposit_code' => $depositCode,
                    'amount'       => $deposit->amount,
                ]);
            });

            return ['RspCode' => '00', 'Message' => 'Confirm Success'];

        } catch (\Exception $e) {
            Log::error('VNPay IPN Wallet: Deposit processing error', [
                'deposit_code' => $depositCode,
                'error'        => $e->getMessage(),
            ]);

            return ['RspCode' => '99', 'Message' => 'Unknown error'];
        }
    }
}
