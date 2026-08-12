<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use Illuminate\Http\Request;

/**
 * FavoriteController — API danh sách yêu thích.
 *
 * Cho phép user thêm/xoá sản phẩm khỏi danh sách yêu thích
 * và lấy danh sách sản phẩm đã lưu.
 */
class FavoriteController extends Controller
{
    /**
     * Lấy danh sách sản phẩm yêu thích (kèm detail sản phẩm).
     */
    public function index()
    {
        $user = auth('api')->user() ?? auth('admin')->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $favorites = Favorite::with(['product' => function ($query) {
            $query->with(['mainImage', 'lowestPriceVariant']);
        }])
            ->whereHas('product')
            ->where('user_id', $user->getKey())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $favorites,
        ]);
    }

    /**
     * Lấy mảng ID sản phẩm đã yêu thích để phục vụ state frontend
     */
    public function getFavoriteIds()
    {
        $user = auth('api')->user() ?? auth('admin')->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $ids = Favorite::where('user_id', $user->getKey())
            ->whereHas('product')
            ->pluck('product_id');

        return response()->json([
            'status' => 'success',
            'data' => $ids,
        ]);
    }

    /**
     * Thêm/Xóa sản phẩm khỏi danh sách yêu thích (Toggle)
     */
    public function toggle(Request $request)
    {
        $user = auth('api')->user() ?? auth('admin')->user();

        if (! $user) {
            return response()->json(['message' => 'Vui lòng đăng nhập để yêu thích sản phẩm'], 401);
        }

        $request->validate([
            'product_id' => 'required|exists:products,product_id',
        ]);

        $productId = $request->product_id;

        $favorite = Favorite::where('user_id', $user->getKey())
            ->where('product_id', $productId)
            ->first();

        if ($favorite) {
            // Đã thích -> Xóa
            $favorite->delete();

            return response()->json([
                'status' => 'success',
                'action' => 'removed',
                'message' => 'Đã bỏ yêu thích sản phẩm', // Optional msg
            ]);
        } else {
            // Chưa thích -> Thêm
            Favorite::create([
                'user_id' => $user->getKey(),
                'product_id' => $productId,
            ]);

            return response()->json([
                'status' => 'success',
                'action' => 'added',
                'message' => 'Đã thêm vào danh sách yêu thích', // Optional msg
            ]);
        }
    }
}
