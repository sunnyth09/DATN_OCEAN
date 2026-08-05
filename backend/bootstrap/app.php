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
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
                    'message' => $message,
                ], 429);
            }
        });

        // Chuẩn hóa lỗi 500 chưa xử lý cho API: log chi tiết, trả generic (tránh leak stack trace / internal message)
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // để Laravel xử lý mặc định (web)
            }

            // Bỏ qua các exception đã có mapping chuẩn của Laravel (validation 422, auth 401, 403, 404, throttle 429, HttpException...)
            if (
                $e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof HttpExceptionInterface
                || $e instanceof ModelNotFoundException
            ) {
                return null;
            }

            Log::error('Unhandled API exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => config('app.debug') ? $e->getMessage() : 'Đã có lỗi xảy ra, vui lòng thử lại sau.',
            ], 500);
        });
    })->create();
