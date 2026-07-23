<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserReward;

class UserRewardAdminController extends Controller
{
    public function index()
    {
        $userRewards = UserReward::with(['user', 'reward'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $userRewards
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,cancelled'
        ]);

        $userReward = UserReward::findOrFail($id);
        $userReward->status = $request->status;
        $userReward->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật trạng thái đổi quà thành công',
            'data' => $userReward
        ]);
    }
}
