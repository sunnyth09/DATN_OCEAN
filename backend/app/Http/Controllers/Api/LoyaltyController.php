<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\UserReward;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoyaltyController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();

        $tier = 'Đồng';
        if ($user->reward_points >= 5000) {
            $tier = 'Kim Cương';
        } elseif ($user->reward_points >= 1000) {
            $tier = 'Vàng';
        } elseif ($user->reward_points >= 500) {
            $tier = 'Bạc';
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'points' => $user->reward_points,
                'tier' => $tier,
                'last_check_in_at' => $user->last_check_in_at,
                'check_in_streak' => $user->check_in_streak ?? 0,
                'has_checked_in_today' => $user->last_check_in_at && Carbon::parse($user->last_check_in_at)->isToday(),
            ],
        ]);
    }

    public function rewards()
    {
        $rewards = Reward::all();

        return response()->json([
            'status' => 'success',
            'data' => $rewards,
        ]);
    }

    public function redeem(Request $request)
    {
        $request->validate([
            'reward_id' => 'required|exists:rewards,id',
        ]);

        $user = $request->user();
        $reward = Reward::findOrFail($request->reward_id);

        if ($user->reward_points < $reward->points_required) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không đủ điểm để đổi quà này.',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $oldPoints = $user->reward_points;

            // Deduct points
            $user->reward_points -= $reward->points_required;
            $user->save();

            // Record user reward
            $userReward = new UserReward;
            $userReward->user_id = $user->user_id;
            $userReward->reward_id = $reward->id;
            $userReward->points_spent = $reward->points_required;
            $userReward->status = 'completed'; // or pending if item
            $userReward->save();

            // Log loyalty transaction
            DB::table('loyalty_transactions')->insert([
                'user_id' => $user->user_id,
                'type' => 'burn',
                'points' => $reward->points_required,
                'balance_before' => $oldPoints,
                'balance_after' => $user->reward_points,
                'description' => 'Đổi quà: '.$reward->name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Đổi quà thành công!',
                'data' => $userReward,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Có lỗi xảy ra: '.$e->getMessage(),
            ], 500);
        }
    }
}
