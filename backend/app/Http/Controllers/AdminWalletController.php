<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletDeposit;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AdminWalletController — Quản lý ví từ admin panel.
 *
 * Tất cả routes qua middleware 'auth:admin' (đặt ở routes/api.php).
 *
 * Endpoints:
 *   GET  /admin/wallets              → Danh sách ví user
 *   GET  /admin/wallets/{userId}     → Chi tiết ví user
 *   POST /admin/wallets/{userId}/adjust → Điều chỉnh số dư
 */
class AdminWalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * GET /admin/wallets?search=xxx&sort=total_balance&per_page=20
     * Danh sách ví user.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->per_page ?? 20), 100);
        $search  = $request->search;
        $sort    = $request->sort ?? 'created_at';
        $order   = $request->order ?? 'desc';

        $allowedSorts = ['deposit_balance', 'commission_balance', 'total_used', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        $query = Wallet::with('user:user_id,full_name,email,phone,avatar_url');

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $wallets = $query->orderBy($sort, $order)->paginate($perPage);

        // Thêm computed fields
        $wallets->getCollection()->transform(function (Wallet $wallet) {
            return [
                'wallet_id'            => $wallet->wallet_id,
                'user_id'              => $wallet->user_id,
                'user'                 => $wallet->user ? [
                    'full_name'  => $wallet->user->full_name,
                    'email'      => $wallet->user->email,
                    'phone'      => $wallet->user->phone,
                    'avatar_url' => $wallet->user->avatar_url,
                ] : null,
                'deposit_balance'      => (float) $wallet->deposit_balance,
                'commission_balance'   => (float) $wallet->commission_balance,
                'total_balance'        => $wallet->getTotalBalance(),
                'frozen_balance'       => (float) $wallet->frozen_balance,
                'total_deposited'      => (float) $wallet->total_deposited,
                'total_commission'     => (float) $wallet->total_commission,
                'total_used'           => (float) $wallet->total_used,
                'status'               => $wallet->status,
                'created_at'           => $wallet->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $wallets,
        ]);
    }

    /**
     * GET /admin/wallets/{userId}
     * Chi tiết ví + lịch sử giao dịch.
     */
    public function show(Request $request, int $userId): JsonResponse
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User không tồn tại'], 404);
        }

        $balance = $this->walletService->getBalance($userId);
        $history = $this->walletService->getHistory(
            $userId,
            min((int) ($request->per_page ?? 20), 100),
            $request->type,
            $request->balance_type
        );

        return response()->json([
            'status' => 'success',
            'data'   => [
                'user' => [
                    'user_id'   => $user->user_id,
                    'full_name' => $user->full_name,
                    'email'     => $user->email,
                    'phone'     => $user->phone,
                ],
                'balance'      => $balance,
                'transactions' => $history,
            ],
        ]);
    }

    /**
     * POST /admin/wallets/{userId}/adjust
     * Admin điều chỉnh số dư ví (deposit_balance).
     *
     * Body: { delta: int, description: string }
     */
    public function adjust(Request $request, int $userId): JsonResponse
    {
        $admin = auth('admin')->user();

        $request->validate([
            'delta'       => 'required|numeric|not_in:0',
            'description' => 'required|string|max:500',
        ]);

        try {
            $tx = $this->walletService->adminAdjust(
                userId: $userId,
                delta: (float) $request->delta,
                description: $request->description,
                adminId: $admin->id,
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Đã điều chỉnh số dư ví',
                'data'    => [
                    'transaction_code' => $tx->transaction_code,
                    'amount'           => (float) $tx->amount,
                    'direction'        => $tx->direction,
                    'balance_after'    => (float) $tx->balance_after,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ════════════════════════════════════════════════════════════
    //  ADMIN DEPOSIT MANAGEMENT (Duyệt nạp tiền thủ công)
    // ════════════════════════════════════════════════════════════

    /**
     * GET /admin/wallets/deposits/pending?status=pending|completed|failed|all
     */
    public function pendingDeposits(Request $request): JsonResponse
    {
        $status  = $request->status ?? 'pending';
        $perPage = min((int) ($request->per_page ?? 30), 100);

        $query = WalletDeposit::with('user:user_id,full_name,email,phone')
            ->orderByDesc('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $deposits = $query->paginate($perPage);

        // Transform để flatten user info
        $deposits->getCollection()->transform(function ($d) {
            return [
                'id'            => $d->id,
                'deposit_code'  => $d->deposit_code,
                'user_id'       => $d->user_id,
                'full_name'     => $d->user?->full_name ?? 'N/A',
                'email'         => $d->user?->email ?? '',
                'phone'         => $d->user?->phone ?? '',
                'amount'        => (float) $d->amount,
                'method'        => $d->method,
                'status'        => $d->status,
                'completed_at'  => $d->completed_at?->toISOString(),
                'created_at'    => $d->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $deposits,
        ]);
    }

    /**
     * POST /admin/wallets/deposits/{depositId}/confirm
     * Admin xác nhận nạp tiền → credit ví user.
     */
    public function confirmDeposit(int $depositId): JsonResponse
    {
        try {
            $deposit = DB::transaction(function () use ($depositId) {
                // Lock dòng deposit và re-check trạng thái BÊN TRONG transaction để chống
                // double-credit khi có 2 request confirm đồng thời.
                $deposit = WalletDeposit::whereKey($depositId)->lockForUpdate()->first();

                if (!$deposit) {
                    throw new \App\Exceptions\OrderException('Không tìm thấy yêu cầu nạp tiền', 404);
                }

                if ($deposit->status !== 'pending') {
                    throw new \App\Exceptions\OrderException('Yêu cầu đã được xử lý trước đó', 422);
                }

                // Credit ví user
                $this->walletService->credit(
                    userId: $deposit->user_id,
                    amount: (float) $deposit->amount,
                    type: 'deposit',
                    opts: [
                        'description'    => 'Nạp ví - Admin duyệt - ' . $deposit->deposit_code,
                        'reference_type' => 'wallet_deposit',
                        'reference_id'   => $deposit->id,
                    ],
                );

                // Update có điều kiện status='pending' → nếu 0 dòng bị ảnh hưởng nghĩa là
                // request khác đã xử lý trước, ném lỗi để rollback credit vừa thực hiện.
                $affected = WalletDeposit::whereKey($deposit->id)
                    ->where('status', 'pending')
                    ->update([
                        'status'                 => 'completed',
                        'completed_at'           => now(),
                        'gateway_transaction_id' => 'ADMIN_CONFIRM',
                    ]);

                if ($affected === 0) {
                    throw new \App\Exceptions\OrderException('Yêu cầu đã được xử lý trước đó', 422);
                }

                return $deposit;
            });

            Log::info('Admin confirmed wallet deposit', [
                'deposit_id'   => $deposit->id,
                'user_id'      => $deposit->user_id,
                'amount'       => $deposit->amount,
                'admin_id'     => auth('admin')->id(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Đã duyệt nạp ' . number_format($deposit->amount) . '₫ vào ví user.',
            ]);
        } catch (\App\Exceptions\OrderException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 422);
        } catch (\Exception $e) {
            Log::error('Admin confirm deposit failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Duyệt nạp tiền thất bại, vui lòng thử lại.'], 500);
        }
    }

    /**
     * POST /admin/wallets/deposits/{depositId}/reject
     * Admin từ chối nạp tiền.
     */
    public function rejectDeposit(int $depositId): JsonResponse
    {
        $deposit = WalletDeposit::find($depositId);

        if (!$deposit) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy yêu cầu nạp tiền'], 404);
        }

        if ($deposit->status !== 'pending') {
            return response()->json(['status' => 'error', 'message' => 'Yêu cầu đã được xử lý trước đó'], 422);
        }

        $deposit->update([
            'status'       => 'failed',
            'completed_at' => now(),
        ]);

        Log::info('Admin rejected wallet deposit', [
            'deposit_id' => $deposit->id,
            'user_id'    => $deposit->user_id,
            'admin_id'   => auth('admin')->id(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã từ chối yêu cầu nạp tiền.',
        ]);
    }
    // ════════════════════════════════════════════════════════════
    //  ADMIN WITHDRAWAL MANAGEMENT (Duyệt rút tiền)
    // ════════════════════════════════════════════════════════════

    /**
     * GET /admin/wallets/withdrawals?status=processing|completed|failed|all
     */
    public function withdrawals(Request $request): JsonResponse
    {
        $status  = $request->status ?? 'all';
        $perPage = min((int) ($request->per_page ?? 30), 100);

        $query = DB::table('wallet_withdrawals as w')
            ->join('users as u', 'w.user_id', '=', 'u.user_id')
            ->select('w.*', 'u.full_name', 'u.email', 'u.phone')
            ->orderByDesc('w.created_at');

        if ($status !== 'all') {
            $query->where('w.status', $status);
        }

        $withdrawals = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $withdrawals,
        ]);
    }

    /**
     * PUT /admin/wallets/withdrawals/{id}/complete
     */
    public function completeWithdrawal(int $id): JsonResponse
    {
        try {
            $withdrawal = DB::transaction(function () use ($id) {
                // Lock dòng withdrawal và re-check trạng thái BÊN TRONG transaction để chống
                // race với thao tác reject/complete từ request khác.
                $withdrawal = DB::table('wallet_withdrawals')->where('id', $id)->lockForUpdate()->first();

                if (!$withdrawal) {
                    throw new \App\Exceptions\OrderException('Không tìm thấy yêu cầu rút tiền', 404);
                }

                if ($withdrawal->status !== 'processing') {
                    throw new \App\Exceptions\OrderException('Yêu cầu đã được xử lý trước đó', 422);
                }

                $affected = DB::table('wallet_withdrawals')
                    ->where('id', $withdrawal->id)
                    ->where('status', 'processing')
                    ->update([
                        'status'       => 'completed',
                        'completed_at' => now(),
                        'updated_at'   => now(),
                    ]);

                if ($affected === 0) {
                    throw new \App\Exceptions\OrderException('Yêu cầu đã được xử lý trước đó', 422);
                }

                return $withdrawal;
            });

            Log::info('Admin completed wallet withdrawal', [
                'withdrawal_id' => $id,
                'user_id'       => $withdrawal->user_id,
                'amount'        => $withdrawal->amount,
                'admin_id'      => auth('admin')->id(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Đã đánh dấu chuyển khoản thành công.',
            ]);
        } catch (\App\Exceptions\OrderException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 422);
        } catch (\Exception $e) {
            Log::error('Admin complete withdrawal failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Duyệt rút tiền thất bại.'], 500);
        }
    }

    /**
     * PUT /admin/wallets/withdrawals/{id}/reject
     */
    public function rejectWithdrawal(Request $request, int $id): JsonResponse
    {
        $note = $request->input('note', 'Bị từ chối bởi Admin');

        try {
            $withdrawal = DB::transaction(function () use ($id, $note) {
                // Lock dòng withdrawal và re-check trạng thái BÊN TRONG transaction để chống
                // double-refund khi có 2 request reject đồng thời.
                $withdrawal = DB::table('wallet_withdrawals')->where('id', $id)->lockForUpdate()->first();

                if (!$withdrawal) {
                    throw new \App\Exceptions\OrderException('Không tìm thấy yêu cầu rút tiền', 404);
                }

                if ($withdrawal->status !== 'processing') {
                    throw new \App\Exceptions\OrderException('Yêu cầu đã được xử lý trước đó', 422);
                }

                // Update có điều kiện status='processing' → 0 dòng nghĩa là đã bị xử lý bởi
                // request khác, ném lỗi để không hoàn tiền hai lần.
                $affected = DB::table('wallet_withdrawals')
                    ->where('id', $withdrawal->id)
                    ->where('status', 'processing')
                    ->update([
                        'status'       => 'failed',
                        'note'         => $note,
                        'completed_at' => now(),
                        'updated_at'   => now(),
                    ]);

                if ($affected === 0) {
                    throw new \App\Exceptions\OrderException('Yêu cầu đã được xử lý trước đó', 422);
                }

                // Hoàn tiền lại (amount + fee)
                $this->walletService->credit(
                    userId: $withdrawal->user_id,
                    amount: (float) $withdrawal->total_deducted,
                    type: 'refund',
                    opts: [
                        'description' => "Hoàn tiền do yêu cầu rút tiền {$withdrawal->withdrawal_code} bị từ chối. Lý do: {$note}",
                        'metadata'    => [
                            'withdrawal_id'   => $withdrawal->id,
                            'withdrawal_code' => $withdrawal->withdrawal_code
                        ]
                    ]
                );

                return $withdrawal;
            });

            Log::info('Admin rejected wallet withdrawal', [
                'withdrawal_id' => $id,
                'user_id'       => $withdrawal->user_id,
                'amount'        => $withdrawal->amount,
                'admin_id'      => auth('admin')->id(),
                'note'          => $note
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Đã từ chối và hoàn tiền lại vào ví cho khách hàng.',
            ]);
        } catch (\App\Exceptions\OrderException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $e->getCode() ?: 422);
        } catch (\Exception $e) {
            Log::error('Admin reject withdrawal failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Từ chối rút tiền thất bại.'], 500);
        }
    }
}
