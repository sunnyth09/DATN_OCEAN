<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\AffiliateConversion;
use App\Repositories\AffiliateRepository;
use App\Repositories\AffiliateClickRepository;
use App\Repositories\AffiliateConversionRepository;
use App\Repositories\AffiliateWithdrawalRepository;
use App\Services\WalletService;
use App\Services\LoyaltyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AffiliateService
{
    public function __construct(
        protected AffiliateRepository $affiliateRepo,
        protected AffiliateClickRepository $clickRepo,
        protected AffiliateConversionRepository $conversionRepo,
        protected AffiliateWithdrawalRepository $withdrawalRepo,
        protected WalletService $walletService,
        protected LoyaltyService $loyaltyService
    ) {}

    // =====================================================================
    // USER METHODS
    // =====================================================================

    /**
     * Đăng ký affiliate cho user
     */
    public function register(int $userId): array
    {
        $profile = $this->affiliateRepo->getAffiliateProfile($userId);

        if (!$profile) {
            return $this->error('Không tìm thấy người dùng!', 404);
        }

        if ($profile->is_affiliate) {
            return $this->error('Bạn đã đăng ký affiliate rồi!', 422);
        }

        $referralCode = $this->generateUniqueReferralCode();

        $user = $this->affiliateRepo->updateUserAsAffiliate($userId, $referralCode);

        return $this->success('Đăng ký affiliate thành công!', [
            'referral_code' => $user->referral_code,
            'affiliate_registered_at' => $user->affiliate_registered_at,
        ]);
    }

    /**
     * Lấy thông tin affiliate profile
     */
    public function getProfile(int $userId): array
    {
        $profile = $this->affiliateRepo->getAffiliateProfile($userId);

        if (!$profile) {
            return $this->error('Không tìm thấy người dùng!', 404);
        }

        $summary = $profile->is_affiliate
            ? $this->conversionRepo->getSummaryByReferrer($userId)
            : null;

        $totalClicks = $profile->is_affiliate
            ? $this->clickRepo->countByReferrer($userId)
            : 0;

        return $this->success('Lấy thông tin affiliate thành công!', [
            'user_id' => $profile->user_id,
            'full_name' => $profile->full_name,
            'email' => $profile->email,
            'referral_code' => $profile->referral_code,
            'is_affiliate' => $profile->is_affiliate,
            'affiliate_registered_at' => $profile->affiliate_registered_at,
            'total_clicks' => $totalClicks,
            'summary' => $summary,
        ]);
    }

    /**
     * Ghi nhận click referral link
     */
    public function trackClick(array $data): array
    {
        $referralCode = $data['referral_code'] ?? null;

        if (!$referralCode) {
            return $this->error('Thiếu mã giới thiệu!', 422);
        }

        $referrer = $this->affiliateRepo->findByReferralCode($referralCode);

        if (!$referrer) {
            return $this->error('Mã giới thiệu không hợp lệ!', 404);
        }

        // Không cho tự click link chính mình
        $currentUserId = $data['user_id'] ?? null;
        if ($currentUserId && $currentUserId == $referrer->user_id) {
            return $this->error('Không thể tự giới thiệu chính mình!', 422);
        }

        $this->clickRepo->create([
            'referrer_id' => $referrer->user_id,
            'user_id' => $currentUserId,
            'product_id' => $data['product_id'] ?? null,
            'referral_code' => $referralCode,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
        ]);

        return $this->success('Đã ghi nhận lượt click!');
    }

    /**
     * Thống kê hoa hồng theo ngày/tháng/năm
     */
    public function getStatistics(int $userId, string $type = 'month'): array
    {
        if (!in_array($type, ['day', 'month', 'year'])) {
            $type = 'month';
        }

        $stats = $this->conversionRepo->getStatsByReferrer($userId, $type);

        return $this->success('Lấy thống kê thành công!', [
            'type' => $type,
            'data' => $stats,
        ]);
    }

    /**
     * Danh sách đơn hàng phát sinh hoa hồng
     */
    public function getConversions(int $userId): array
    {
        $conversions = $this->conversionRepo->getByReferrer($userId);

        return $this->success('Lấy danh sách đơn affiliate thành công!', $conversions);
    }

    /**
     * Gửi yêu cầu rút tiền
     */
    public function requestWithdrawal(int $userId, array $data): array
    {
        // Kiểm tra user là affiliate
        $profile = $this->affiliateRepo->getAffiliateProfile($userId);
        if (!$profile || !$profile->is_affiliate) {
            return $this->error('Bạn chưa đăng ký affiliate!', 403);
        }

        // Kiểm tra có yêu cầu pending chưa xử lý
        if ($this->withdrawalRepo->hasPendingWithdrawal($userId)) {
            return $this->error('Bạn đang có yêu cầu rút tiền chưa được xử lý. Vui lòng chờ duyệt!', 422);
        }

        $amount = (float) $data['amount'];
        $minAmount = config('affiliate.min_withdraw_amount', 100000);

        if ($amount < $minAmount) {
            return $this->error("Số tiền rút tối thiểu là " . number_format($minAmount) . " VND!", 422);
        }

        try {
            // Toàn bộ (kiểm tra pending + số dư + tạo yêu cầu) nằm trong 1 transaction có khóa
            // dòng ví để chống race: 2 request đồng thời không thể cùng vượt số dư khả dụng.
            $withdrawal = DB::transaction(function () use ($userId, $amount, $data) {
                // Khóa dòng ví trước khi đọc số dư hoa hồng.
                $wallet = \App\Models\Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

                // Số dư khả dụng = hoa hồng hiện có − (đã rút/đang chờ rút).
                $pendingOrWithdrawn = $this->withdrawalRepo->getTotalWithdrawnOrPending($userId);
                $availableBalance   = (float) $wallet->commission_balance - $pendingOrWithdrawn;

                if ($amount > $availableBalance) {
                    throw new \App\Exceptions\OrderException(
                        'Số dư hoa hồng khả dụng không đủ để rút (' . number_format(max(0, $availableBalance)) . ' VND)!'
                    );
                }

                // Kiểm tra pending BÊN TRONG lock để chống tạo trùng yêu cầu.
                if ($this->withdrawalRepo->hasPendingWithdrawal($userId)) {
                    throw new \App\Exceptions\OrderException('Bạn đang có yêu cầu rút tiền chưa được xử lý. Vui lòng chờ duyệt!');
                }

                return $this->withdrawalRepo->create([
                    'user_id'             => $userId,
                    'amount'              => $amount,
                    'bank_name'           => $data['bank_name'] ?? 'VNPay Payout',
                    'bank_account_name'   => $data['bank_account_name'] ?? '',
                    'bank_account_number' => $data['bank_account_number'] ?? '',
                    'status'              => 'pending',
                ]);
            });

            return $this->success('Gửi yêu cầu rút tiền thành công! Vui lòng chờ duyệt.', $withdrawal);
        } catch (\App\Exceptions\OrderException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Affiliate withdrawal error: ' . $e->getMessage());
            return $this->error('Lỗi khi gửi yêu cầu rút tiền!', 500);
        }
    }

    /**
     * Lịch sử rút tiền
     */
    public function getWithdrawals(int $userId): array
    {
        $withdrawals = $this->withdrawalRepo->getByUser($userId);

        return $this->success('Lấy lịch sử rút tiền thành công!', $withdrawals);
    }

    // =====================================================================
    // ORDER INTEGRATION
    // =====================================================================

    /**
     * Ghi nhận conversion khi đơn hàng tạo thành công.
     * Gọi từ OrderService::createOrder() SAU KHI order đã persist.
     */
    public function createConversionFromOrder(Order $order, ?string $referralCode): void
    {
        if (!$referralCode || !$order->user_id) {
            return;
        }

        try {
            $referrer = $this->affiliateRepo->findByReferralCode($referralCode);

            if (!$referrer) {
                return; // Mã không tồn tại, bỏ qua
            }

            // Không ghi nhận nếu buyer chính là referrer
            if ($order->user_id === $referrer->user_id) {
                return;
            }

            // Không ghi nhận trùng order
            if ($this->conversionRepo->findByOrderId($order->order_id)) {
                return;
            }

            $commissionRate = config('affiliate.commission_rate', 5);
            $commissionAmount = ($order->grand_total * $commissionRate) / 100;

            $this->conversionRepo->create([
                'referrer_id' => $referrer->user_id,
                'buyer_id' => $order->user_id,
                'order_id' => $order->order_id,
                'total_amount' => $order->grand_total,
                'commission_rate' => $commissionRate,
                'commission_amount' => round($commissionAmount, 2),
                'status' => 'pending',
            ]);
        } catch (\Exception $e) {
            // Không throw — affiliate lỗi không ảnh hưởng order flow
            Log::error('Affiliate conversion creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Cập nhật trạng thái hoa hồng khi đơn hàng thay đổi status.
     * Gọi từ AdminOrderController::updateStatus().
     */
    public function updateConversionOnStatusChange(Order $order, string $newStatus): void
    {
        try {
            $conversion = $this->conversionRepo->findByOrderId($order->order_id);

            if (!$conversion) {
                return;
            }

            // Đã paid hoặc cancelled → không thay đổi nữa
            if (in_array($conversion->status, ['paid', 'cancelled'])) {
                return;
            }

            $oldStatus = $conversion->status;

            if (in_array($newStatus, [OrderStatus::COMPLETED->value, OrderStatus::DELIVERED->value], true)) {
                $this->conversionRepo->updateStatusByOrderId($order->order_id, 'approved');

                // ★ Auto-deposit commission vào ví affiliate
                $this->depositCommissionToWallet($order);
            }

            if (in_array($newStatus, [
                OrderStatus::CANCELLED->value,
                OrderStatus::RETURN_APPROVED->value,
                OrderStatus::RETURNED->value,
                OrderStatus::REFUNDED->value,
            ], true)) {
                // Conditional update atomic: chỉ chuyển approved→cancelled đúng MỘT lần.
                // Hai request hủy đồng thời → chỉ request thắng race trả true → clawback
                // chạy đúng một lần (không double-deduct hoa hồng của conversion khác).
                $clawback = $this->conversionRepo->markCancelledFromApproved($order->order_id);

                // Với các status khác (pending) chỉ cần đánh dấu cancelled, không clawback.
                if (!$clawback && $oldStatus !== 'approved') {
                    $this->conversionRepo->updateStatusByOrderId($order->order_id, 'cancelled');
                }

                if ($clawback) {
                    // Thu hồi lại hoa hồng nếu đã được cộng trước đó (trừ đúng commission_balance).
                    try {
                        $this->walletService->debitCommission(
                            $conversion->referrer_id,
                            (float) $conversion->commission_amount,
                            "Thu hồi hoa hồng từ đơn hàng #{$order->order_code} (Hủy đơn/Trả hàng)",
                            $conversion->id,
                            AffiliateConversion::class
                        );
                    } catch (\Exception $e) {
                        Log::error("Failed to revoke commission for order #{$order->order_id}: " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Affiliate conversion status update failed: ' . $e->getMessage());
        }
    }

    // =====================================================================
    // ADMIN METHODS
    // =====================================================================

    public function adminGetConversions(): array
    {
        return $this->success('Danh sách conversions', $this->conversionRepo->adminList());
    }

    public function adminApproveConversion(int $id): array
    {
        $conversion = AffiliateConversion::find($id);
        if (!$conversion || $conversion->status !== 'pending') {
            return $this->error('Conversion không hợp lệ hoặc đã duyệt!', 422);
        }

        $updated = $this->conversionRepo->updateStatus($id, 'approved');
        if ($updated) {
            try {
                // Credit hoa hồng dùng chung luồng đã có khóa+dedupe (chống double-credit
                // khi admin-approve chạy song song với DELIVERED/COMPLETED auto-deposit).
                if ($conversion->order) {
                    $this->depositCommissionToWallet($conversion->order);

                    // Tích điểm giới thiệu cho referrer
                    $referrer = \App\Models\User::find($conversion->referrer_id);
                    if ($referrer) {
                        $this->loyaltyService->earnFromReferral($referrer, $conversion->order);
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to deposit commission on admin approve: " . $e->getMessage());
            }
        }
        return $updated
            ? $this->success('Đã duyệt hoa hồng!')
            : $this->error('Không tìm thấy conversion!', 404);
    }

    public function adminCancelConversion(int $id): array
    {
        $conversion = AffiliateConversion::find($id);
        if (!$conversion) {
            return $this->error('Không tìm thấy conversion!', 404);
        }
        $oldStatus = $conversion->status;
        $updated = $this->conversionRepo->updateStatus($id, 'cancelled');
        if ($updated && $oldStatus === 'approved') {
            try {
                // Thu hồi hoa hồng: trừ đúng commission_balance (không deposit-first).
                $this->walletService->debitCommission(
                    $conversion->referrer_id,
                    (float) $conversion->commission_amount,
                    "Thu hồi hoa hồng #" . $conversion->id . " bởi Admin",
                    $conversion->id,
                    AffiliateConversion::class
                );
            } catch (\Exception $e) {
                Log::error("Failed to revoke commission on admin cancel: " . $e->getMessage());
            }
        }
        return $updated
            ? $this->success('Đã hủy hoa hồng!')
            : $this->error('Không tìm thấy conversion!', 404);
    }

    public function adminGetWithdrawals(): array
    {
        return $this->success('Danh sách yêu cầu rút tiền', $this->withdrawalRepo->adminList());
    }

    public function adminApproveWithdrawal(int $id): array
    {
        $withdrawal = $this->withdrawalRepo->findById($id);
        if (!$withdrawal || $withdrawal->status !== 'pending') {
            return $this->error('Yêu cầu rút tiền không hợp lệ hoặc đã được xử lý!', 422);
        }

        // Duyệt: chuyển sang approved. Tiền đã bị "giữ" (tính vào getTotalWithdrawnOrPending)
        // ngay từ lúc user gửi yêu cầu nên không trừ ví lần nữa ở đây.
        return $this->withdrawalRepo->updateStatus($id, 'approved')
            ? $this->success('Đã duyệt yêu cầu rút tiền!')
            : $this->error('Không tìm thấy yêu cầu rút tiền!', 404);
    }

    public function adminRejectWithdrawal(int $id, ?string $note = null): array
    {
        try {
            return DB::transaction(function () use ($id, $note) {
                // Lock dòng withdrawal, re-check trạng thái để chống double-refund.
                $withdrawal = \App\Models\AffiliateWithdrawal::whereKey($id)->lockForUpdate()->first();
                if (!$withdrawal || !in_array($withdrawal->status, ['pending', 'approved'], true)) {
                    return $this->error('Yêu cầu rút tiền không hợp lệ hoặc đã được xử lý!', 422);
                }

                $affected = \App\Models\AffiliateWithdrawal::whereKey($id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->update(['status' => 'rejected', 'note' => $note]);

                if ($affected === 0) {
                    return $this->error('Yêu cầu đã được xử lý trước đó!', 422);
                }

                // KHÔNG credit lại commission_balance ở đây.
                // Mô hình giữ tiền: số dư khả dụng = commission_balance − getTotalWithdrawnOrPending()
                // (chỉ tính pending/approved/paid). Khi reject, row rời khỏi tổng đó nên khả dụng
                // tự phục hồi. Nếu credit thêm sẽ thành hoàn kép (double-refund).

                return $this->success('Đã từ chối yêu cầu rút tiền và hoàn lại số dư!');
            });
        } catch (\Exception $e) {
            Log::error('Affiliate reject withdrawal failed: ' . $e->getMessage());
            return $this->error('Từ chối yêu cầu rút tiền thất bại.', 500);
        }
    }

    public function adminMarkPaidWithdrawal(int $id): array
    {
        $withdrawal = $this->withdrawalRepo->findById($id);
        if (!$withdrawal || $withdrawal->status !== 'approved') {
            return $this->error('Chỉ có thể đánh dấu đã thanh toán cho yêu cầu đã duyệt!', 422);
        }

        return $this->withdrawalRepo->updateStatus($id, 'paid')
            ? $this->success('Đã đánh dấu thanh toán thành công!')
            : $this->error('Không tìm thấy yêu cầu rút tiền!', 404);
    }

    // =====================================================================
    // WALLET INTEGRATION
    // =====================================================================

    /**
     * Auto-deposit hoa hồng vào ví affiliate khi order completed/delivered.
     * Nạp vào commission_balance (không phải deposit_balance).
     */
    private function depositCommissionToWallet(Order $order): void
    {
        try {
            $conversion = $this->conversionRepo->findByOrderId($order->order_id);

            if (!$conversion) {
                return;
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($order, $conversion) {
                // Khóa row ví người nhận TRƯỚC để serialize các lần deposit đồng thời
                // cùng conversion (admin double-click, DELIVERED + COMPLETED race).
                // credit() mở nested transaction re-lock cùng row → reentrant, an toàn.
                $this->walletService->getOrCreateWallet($conversion->referrer_id);
                \App\Models\Wallet::where('user_id', $conversion->referrer_id)->lockForUpdate()->first();

                // Re-check chống duplicate BÊN TRONG khóa: request thứ 2 chờ request 1
                // commit rồi mới thấy transaction commission đã tồn tại → thoát.
                $alreadyDeposited = \App\Models\WalletTransaction::where('reference_type', AffiliateConversion::class)
                    ->where('reference_id', $conversion->id)
                    ->where('type', 'commission')
                    ->exists();

                if ($alreadyDeposited) {
                    Log::info('Affiliate commission already deposited to wallet', [
                        'conversion_id' => $conversion->id,
                        'order_id'      => $order->order_id,
                    ]);
                    return;
                }

                $this->walletService->credit(
                    userId: $conversion->referrer_id,
                    amount: (float) $conversion->commission_amount,
                    type: 'commission',  // → chảy vào commission_balance
                    opts: [
                        'reference_type' => AffiliateConversion::class,
                        'reference_id'   => $conversion->id,
                        'description'    => "Hoa hồng affiliate đơn #{$order->order_code}",
                        'metadata'       => [
                            'order_id'        => $order->order_id,
                            'order_code'      => $order->order_code,
                            'total_amount'    => $conversion->total_amount,
                            'commission_rate' => $conversion->commission_rate,
                        ],
                    ]
                );

                Log::info('Affiliate commission deposited to wallet', [
                    'referrer_id'       => $conversion->referrer_id,
                    'commission_amount' => $conversion->commission_amount,
                    'order_code'        => $order->order_code,
                ]);
            });

        } catch (\Exception $e) {
            // Không throw — wallet lỗi không ảnh hưởng affiliate status flow
            Log::error('Affiliate commission wallet deposit failed: ' . $e->getMessage(), [
                'order_id' => $order->order_id,
            ]);
        }
    }

    // =====================================================================
    // PRIVATE HELPERS
    // =====================================================================

    /**
     * Tạo mã referral unique dạng AAA-XXX-BBB
     */
    private function generateUniqueReferralCode(): string
    {
        $maxAttempts = 10;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = strtoupper(Str::random(3)) . '-' . strtoupper(Str::random(3)) . '-' . strtoupper(Str::random(3));

            if (!$this->affiliateRepo->referralCodeExists($code)) {
                return $code;
            }
        }

        // Fallback: thêm timestamp nếu bị trùng quá nhiều lần
        return strtoupper(Str::random(3)) . '-' . strtoupper(Str::random(3)) . '-' . substr(time(), -3);
    }

    private function success(string $message, $data = null): array
    {
        return [
            'status_code' => 200,
            'body' => [
                'status' => true,
                'message' => $message,
                'data' => $data,
            ],
        ];
    }

    private function error(string $message, int $statusCode): array
    {
        return [
            'status_code' => $statusCode,
            'body' => [
                'status' => false,
                'message' => $message,
            ],
        ];
    }
}
