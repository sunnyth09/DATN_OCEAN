<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Kiểm tra user có role phù hợp để truy cập route không.
     * Sử dụng: Route::middleware('role:admin,staff')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = auth('admin')->user() ?? auth('api')->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn chưa đăng nhập!',
            ], 401);
        }

        if (! in_array($user->role, $roles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền truy cập chức năng này!',
            ], 403);
        }

        return $next($request);
    }
}
