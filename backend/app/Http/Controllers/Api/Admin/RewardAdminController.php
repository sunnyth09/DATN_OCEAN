<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use Illuminate\Http\Request;

class RewardAdminController extends Controller
{
    public function index()
    {
        $rewards = Reward::orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $rewards,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'points_required' => 'required|integer|min:1',
            'type' => 'required|in:voucher,item',
            'image' => 'nullable|string',
        ]);

        $reward = Reward::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo quà tặng thành công',
            'data' => $reward,
        ], 201);
    }

    public function show($id)
    {
        $reward = Reward::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $reward,
        ]);
    }

    public function update(Request $request, $id)
    {
        $reward = Reward::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'points_required' => 'sometimes|integer|min:1',
            'type' => 'sometimes|in:voucher,item',
            'image' => 'nullable|string',
        ]);

        $reward->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật quà tặng thành công',
            'data' => $reward,
        ]);
    }

    public function destroy($id)
    {
        $reward = Reward::findOrFail($id);
        $reward->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa quà tặng thành công',
        ]);
    }
}
