<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ProductComment;
use App\Policies\OrderPolicy;
use App\Policies\ReturnRequestPolicy;
use App\Policies\ProductCommentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
    }
}
