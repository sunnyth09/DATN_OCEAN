<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\ProductComment;
use App\Models\ReturnRequest;
use App\Policies\OrderPolicy;
use App\Policies\ProductCommentPolicy;
use App\Policies\ReturnRequestPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ─── Policy Registration ────────────────────────────────────────
        // Laravel tự động resolve policy nếu Model ↔ Policy đặt tên đúng convention
        // (App\Models\Foo → App\Policies\FooPolicy), nhưng đăng ký tường minh
        // giúp IDE autocomplete và tránh nhầm lẫn.

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(ReturnRequest::class, ReturnRequestPolicy::class);
        Gate::policy(ProductComment::class, ProductCommentPolicy::class);

        RateLimiter::for('strict_api', function (Request $request) {
            $identifier = $request->user()?->id ?: $request->header('X-Device-ID') ?: $request->ip();
            return Limit::perMinute(60)->by($identifier);
        });
    }
}
