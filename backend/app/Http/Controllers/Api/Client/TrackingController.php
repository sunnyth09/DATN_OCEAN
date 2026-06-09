<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RecentlyViewedProduct;
use App\Models\SearchHistory;

class TrackingController extends Controller
{
    public function viewProduct(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'session_id' => 'nullable|string'
        ]);

        $userId = auth('api')->id();
        $sessionId = $request->session_id;

        if (!$userId && !$sessionId) {
            return response()->json(['message' => 'No tracking ID provided'], 400);
        }

        $query = RecentlyViewedProduct::where('product_id', $request->product_id);
        
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        $record = $query->first();

        if ($record) {
            $record->update(['viewed_at' => now()]);
        } else {
            RecentlyViewedProduct::create([
                'user_id' => $userId,
                'session_id' => $userId ? null : $sessionId,
                'product_id' => $request->product_id,
            ]);
        }

        return response()->json(['message' => 'Product view tracked successfully']);
    }

    public function getRecentlyViewed(Request $request)
    {
        $userId = auth('api')->id();
        $sessionId = $request->query('session_id');

        if (!$userId && !$sessionId) {
            return response()->json(['data' => []]);
        }

        $query = RecentlyViewedProduct::with(['product' => function($q) {
            // Load necessary relations for product mapping on frontend
            $q->with(['mainImage', 'images', 'category', 'lowestPriceVariant']);
        }]);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        $records = $query->orderBy('viewed_at', 'desc')->take(10)->get();
        
        // Exclude products that are deleted or missing
        $products = $records->pluck('product')->filter();

        return response()->json(['data' => $products->values()]);
    }

    public function getSearchHistory(Request $request)
    {
        $userId = auth('api')->id();
        $sessionId = $request->query('session_id');

        if (!$userId && !$sessionId) {
            return response()->json(['data' => []]);
        }

        $query = SearchHistory::query();

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        $records = $query->orderBy('updated_at', 'desc')->take(5)->get();

        return response()->json(['data' => $records]);
    }
}
