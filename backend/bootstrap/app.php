<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
            \App\Http\Middleware\XssSanitizer::class,
        ]);
        $middleware->alias([
            'role'          => \App\Http\Middleware\RoleMiddleware::class,
            'customer.only' => \App\Http\Middleware\EnsureCustomerOnly::class,
        ]);
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => $request->is('api/*') ? abort(response()->json(['message' => 'Unauthenticated.'], 401)) : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request, \Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });

        // Chuẩn hóa lỗi 500 chưa xử lý cho API: log chi tiết, trả generic (tránh leak stack trace / internal message)
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null; // để Laravel xử lý mặc định (web)
            }

            // Bỏ qua các exception đã có mapping chuẩn của Laravel (validation 422, auth 401, 403, 404, throttle 429, HttpException...)
            if (
                $e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
            ) {
                return null;
            }

            \Illuminate\Support\Facades\Log::error('Unhandled API exception', [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'url'       => $request->fullUrl(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => config('app.debug') ? $e->getMessage() : 'Đã có lỗi xảy ra, vui lòng thử lại sau.',
            ], 500);
        });
    })->create();
