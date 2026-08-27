<?php

use App\Http\Middleware\EnsureCustomerOnly;
use App\Http\Middleware\FilterProfanity;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\VerifyCarrierWebhook;
use App\Http\Middleware\XssSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api_client.php',
        then: function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api_admin.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api_webhook.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            XssSanitizer::class,
        ]);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'customer.only' => EnsureCustomerOnly::class,
            'profanity' => FilterProfanity::class,
            'carrier.webhook' => VerifyCarrierWebhook::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? abort(response()->json(['message' => 'Unauthenticated.'], 401)) : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });

        // Tùy chỉnh thông báo lỗi 429 Too Many Requests sang tiếng Việt
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $retryAfter = $e->getHeaders()['Retry-After'] ?? null;
                $message = 'Bạn đã thao tác quá nhiều lần. Vui lòng thử lại sau'.($retryAfter ? " {$retryAfter} giây." : '.');

                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 429);
            }
        });

        // Xử lý tất cả các lỗi HttpException (403, 404, 400, 401,...) cho API: trả JSON sạch, không leak stack trace PHP
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $statusCode = $e->getStatusCode();
                $defaultMessages = [
                    400 => 'Yêu cầu không hợp lệ.',
                    401 => 'Bạn chưa đăng nhập hoặc phiên đăng nhập đã hết hạn.',
                    403 => 'Bạn không có quyền truy cập tính năng này.',
                    404 => 'Không tìm thấy dữ liệu yêu cầu.',
                    405 => 'Phương thức không được hỗ trợ.',
                ];
                $message = $e->getMessage() ?: ($defaultMessages[$statusCode] ?? 'Đã có lỗi xảy ra.');

                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], $statusCode, $e->getHeaders());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn chưa đăng nhập hoặc phiên làm việc đã kết thúc.',
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage() ?: 'Bạn không có quyền thực hiện hành động này.',
                ], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không tìm thấy dữ liệu yêu cầu.',
                ], 404);
            }
        });

        // Chuẩn hóa lỗi 500 chưa xử lý cho API: log chi tiết, trả JSON sạch (hoàn toàn không leak file, line, trace ra client)
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // để Laravel xử lý mặc định (web)
            }

            if ($e instanceof ValidationException || $e instanceof HttpResponseException) {
                return null;
            }

            Log::error('Unhandled API exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Đã có lỗi xảy ra, vui lòng thử lại sau.',
            ], 500);
        });
    })->create();
