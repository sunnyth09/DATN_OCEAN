<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureCustomerOnly — Ngăn Admin/Staff sử dụng customer-only features.
 *
 * Thay thế pattern lặp lại trong AffiliateController và các controller khác:
 *   if (auth('admin')->check()) {
 *       return response()->json(['message' => '...'], 403);
 *   }
 *
 * Sử dụng:
 *   Route::middleware(['auth:api', 'customer.only'])->group(...)
 *
 * Hoặc trong constructor:
 *   $this->middleware('customer.only');
 */
class EnsureCustomerOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nếu request đến từ admin guard → từ chối
        if (auth('admin')->check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tài khoản quản trị/nhân viên không thể sử dụng tính năng này. Vui lòng đăng nhập bằng tài khoản khách hàng.',
            ], 403);
        }

        // Nếu không phải customer (chưa login qua 'api' guard)
        if (! auth('api')->check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để sử dụng tính năng này.',
            ], 401);
        }

        return $next($request);
    }
}
