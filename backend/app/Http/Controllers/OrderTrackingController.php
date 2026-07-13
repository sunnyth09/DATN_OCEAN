<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class OrderTrackingController extends Controller
{
    public function __construct(
        protected OrderTrackingService $trackingService,
    ) {}

    public function show(int $id): JsonResponse
    {
        $userId = auth('api')->id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $order = Order::with(['address'])
            ->where('order_id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $this->trackingService->buildPayload($order),
        ]);
    }

    public function trackByToken(string $token): JsonResponse
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return $this->notFoundResponse();
        }

        $order = Order::with(['address'])
            ->where('tracking_token', $token)
            ->first();

        if (!$order) {
            return $this->notFoundResponse();
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->trackingService->buildPayload($order),
        ]);
    }

    public function trackByPhone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_code' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'regex:/^[0-9]{10,11}$/'],
        ]);

        $key = 'guest_tracking:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'status' => 'error',
                'message' => "Quá nhiều lần thử. Vui lòng thử lại sau {$seconds} giây.",
            ], 429);
        }
        RateLimiter::hit($key, 300);

        $phone = preg_replace('/\D+/', '', $validated['phone']);

        $order = Order::with(['address'])
            ->where('order_code', $validated['order_code'])
            ->where(function ($query) use ($phone) {
                $query->where('recipient_phone', $phone)
                    ->orWhereHas('address', fn ($addressQuery) => $addressQuery->where('phone', $phone));
            })
            ->first();

        if (!$order) {
            return $this->notFoundResponse();
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->trackingService->buildPayload($order),
        ]);
    }

    private function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Không tìm thấy đơn hàng. Vui lòng kiểm tra lại thông tin tra cứu.',
        ], 404);
    }
}
