<?php

namespace App\Http\Controllers;

use App\Models\UserBankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UserBankAccountController — Quản lý tài khoản ngân hàng liên kết.
 *
 * Tối đa 3 tài khoản/user.
 *
 * Endpoints (auth:api):
 *   GET    /api/wallet/bank-accounts           → Danh sách TK ngân hàng
 *   POST   /api/wallet/bank-accounts           → Thêm TK mới
 *   PUT    /api/wallet/bank-accounts/{id}      → Sửa TK
 *   DELETE /api/wallet/bank-accounts/{id}      → Xóa TK
 *   POST   /api/wallet/bank-accounts/{id}/default → Đặt mặc định
 */
class UserBankAccountController extends Controller
{
    /**
     * GET — Danh sách tài khoản ngân hàng.
     */
    public function index(): JsonResponse
    {
        $user = auth('api')->user();

        $accounts = UserBankAccount::where('user_id', $user->user_id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $accounts,
        ]);
    }

    /**
     * POST — Thêm tài khoản ngân hàng mới (tối đa 3).
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        // Giới hạn 3 TK
        $count = UserBankAccount::where('user_id', $user->user_id)->count();
        if ($count >= 3) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Bạn chỉ được liên kết tối đa 3 tài khoản ngân hàng.',
            ], 422);
        }

        $request->validate([
            'bank_name'       => 'required|string|max:100',
            'bank_short_name' => 'nullable|string|max:50',
            'bank_bin'        => 'nullable|string|max:10',
            'account_name'    => 'required|string|max:255',
            'account_number'  => 'required|string|max:50',
            'is_default'      => 'boolean',
        ], [
            'bank_name.required'      => 'Vui lòng nhập tên ngân hàng.',
            'account_name.required'   => 'Vui lòng nhập tên chủ tài khoản.',
            'account_number.required' => 'Vui lòng nhập số tài khoản.',
        ]);

        // Kiểm tra trùng
        $exists = UserBankAccount::where('user_id', $user->user_id)
            ->where('account_number', $request->account_number)
            ->where('bank_bin', $request->bank_bin)
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tài khoản này đã được liên kết.',
            ], 422);
        }

        DB::transaction(function () use ($user, $request, $count) {
            // Nếu là TK đầu tiên hoặc đặt mặc định → reset các TK khác
            $isDefault = $count === 0 || $request->boolean('is_default');

            if ($isDefault) {
                UserBankAccount::where('user_id', $user->user_id)
                    ->update(['is_default' => false]);
            }

            UserBankAccount::create([
                'user_id'         => $user->user_id,
                'bank_name'       => $request->bank_name,
                'bank_short_name' => $request->bank_short_name,
                'bank_bin'        => $request->bank_bin,
                'account_name'    => strtoupper(trim($request->account_name)),
                'account_number'  => trim($request->account_number),
                'is_default'      => $isDefault,
            ]);
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã liên kết tài khoản ngân hàng.',
        ], 201);
    }

    /**
     * PUT — Sửa tài khoản ngân hàng.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user    = auth('api')->user();
        $account = UserBankAccount::where('user_id', $user->user_id)->where('id', $id)->first();

        if (!$account) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy tài khoản'], 404);
        }

        $request->validate([
            'bank_name'       => 'required|string|max:100',
            'bank_short_name' => 'nullable|string|max:50',
            'bank_bin'        => 'nullable|string|max:10',
            'account_name'    => 'required|string|max:255',
            'account_number'  => 'required|string|max:50',
        ]);

        $account->update([
            'bank_name'       => $request->bank_name,
            'bank_short_name' => $request->bank_short_name,
            'bank_bin'        => $request->bank_bin,
            'account_name'    => strtoupper(trim($request->account_name)),
            'account_number'  => trim($request->account_number),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã cập nhật tài khoản ngân hàng.',
        ]);
    }

    /**
     * DELETE — Xóa tài khoản ngân hàng.
     */
    public function destroy(int $id): JsonResponse
    {
        $user    = auth('api')->user();
        $account = UserBankAccount::where('user_id', $user->user_id)->where('id', $id)->first();

        if (!$account) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy tài khoản'], 404);
        }

        $wasDefault = $account->is_default;
        $account->delete();

        // Nếu xóa TK mặc định → đặt TK đầu tiên còn lại làm mặc định
        if ($wasDefault) {
            UserBankAccount::where('user_id', $user->user_id)
                ->orderByDesc('created_at')
                ->first()
                ?->update(['is_default' => true]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã xóa tài khoản ngân hàng.',
        ]);
    }

    /**
     * POST — Đặt tài khoản mặc định.
     */
    public function setDefault(int $id): JsonResponse
    {
        $user    = auth('api')->user();
        $account = UserBankAccount::where('user_id', $user->user_id)->where('id', $id)->first();

        if (!$account) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy tài khoản'], 404);
        }

        DB::transaction(function () use ($user, $account) {
            UserBankAccount::where('user_id', $user->user_id)
                ->update(['is_default' => false]);
            $account->update(['is_default' => true]);
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Đã đặt tài khoản mặc định.',
        ]);
    }
}
