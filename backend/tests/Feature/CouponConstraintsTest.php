<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\CouponService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CouponConstraintsTest extends TestCase
{
    protected CouponService $couponService;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach (['coupon_categories', 'categories', 'orders', 'user_coupons', 'coupons', 'product_variants', 'products', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('full_name')->default('Customer');
            $table->string('email')->unique();
            $table->string('role')->default('customer');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id('category_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type')->default('percent');
            $table->decimal('value', 12, 2)->default(0);
            $table->decimal('max_discount_value', 12, 2)->nullable();
            $table->decimal('min_order_value', 12, 2)->default(0);
            $table->integer('usage_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->integer('user_usage_limit')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_first_order')->default(false);
            $table->boolean('auto_apply')->default(false);
            $table->integer('min_product_qty')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coupon_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('category_id');
        });

        Schema::create('user_coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('coupon_id');
            $table->integer('used_count')->default(0);
            $table->boolean('is_saved')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('fulfillment_status')->default('completed');
            $table->timestamps();
        });

        $this->couponService = app(CouponService::class);
        $this->user = User::create([
            'email' => 'test_coupon@ocean.vn',
            'full_name' => 'Test User',
        ]);
    }

    public function test_inactive_coupon_rejected()
    {
        Coupon::create([
            'code' => 'INACTIVE10',
            'type' => 'percent',
            'value' => 10,
            'is_active' => false,
        ]);

        $res = $this->couponService->checkCoupon($this->user->user_id, 'INACTIVE10', 500000);
        $this->assertFalse($res['success']);
    }

    public function test_min_order_value_constraint()
    {
        Coupon::create([
            'code' => 'MIN1M',
            'type' => 'fixed',
            'value' => 100000,
            'min_order_value' => 1000000,
            'is_active' => true,
        ]);

        $resFail = $this->couponService->checkCoupon($this->user->user_id, 'MIN1M', 500000);
        $this->assertFalse($resFail['success']);

        $resPass = $this->couponService->checkCoupon($this->user->user_id, 'MIN1M', 1200000);
        $this->assertTrue($resPass['success']);
    }

    public function test_global_usage_limit_constraint()
    {
        Coupon::create([
            'code' => 'LIMITED1',
            'type' => 'fixed',
            'value' => 50000,
            'usage_limit' => 1,
            'used_count' => 1,
            'is_active' => true,
        ]);

        $res = $this->couponService->checkCoupon($this->user->user_id, 'LIMITED1', 200000);
        $this->assertFalse($res['success']);
    }

    public function test_per_user_usage_limit_constraint()
    {
        $coupon = Coupon::create([
            'code' => 'ONCEPERUSER',
            'type' => 'percent',
            'value' => 15,
            'user_usage_limit' => 1,
            'is_active' => true,
        ]);

        UserCoupon::create([
            'user_id' => $this->user->user_id,
            'coupon_id' => $coupon->id,
            'used_count' => 1,
        ]);

        $res = $this->couponService->checkCoupon($this->user->user_id, 'ONCEPERUSER', 300000);
        $this->assertFalse($res['success']);
    }

    public function test_first_order_only_constraint()
    {
        Coupon::create([
            'code' => 'FIRSTORDER',
            'type' => 'percent',
            'value' => 20,
            'is_first_order' => true,
            'is_active' => true,
        ]);

        // User without orders -> Pass
        $resPass = $this->couponService->checkCoupon($this->user->user_id, 'FIRSTORDER', 500000);
        $this->assertTrue($resPass['success']);

        // Create an existing guest order matching user's email
        Order::create([
            'user_id' => null,
            'email' => 'test_coupon@ocean.vn',
            'fulfillment_status' => 'completed',
        ]);

        // User with existing email order -> Fail
        $resFail = $this->couponService->checkCoupon($this->user->user_id, 'FIRSTORDER', 500000);
        $this->assertFalse($resFail['success']);
    }

    public function test_category_restriction_constraint()
    {
        $catRacket = Category::create(['name' => 'Vợt']);
        $catShoe = Category::create(['name' => 'Giày']);

        $coupon = Coupon::create([
            'code' => 'RACKETONLY',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
        ]);
        $coupon->categories()->attach($catRacket->category_id);

        $shoeItem = (object) [
            'quantity' => 1,
            'variant' => (object) [
                'price' => 500000,
                'product' => (object) [
                    'category_id' => $catShoe->category_id,
                ],
            ],
        ];

        $racketItem = (object) [
            'quantity' => 1,
            'variant' => (object) [
                'price' => 1000000,
                'product' => (object) [
                    'category_id' => $catRacket->category_id,
                ],
            ],
        ];

        // Cart with only shoes -> Fail
        $resShoe = $this->couponService->applyCoupon($this->user->user_id, 'RACKETONLY', 500000, [$shoeItem]);
        $this->assertFalse($resShoe['success']);

        // Cart with racket -> Pass and calculate discount only on racket
        $resRacket = $this->couponService->applyCoupon($this->user->user_id, 'RACKETONLY', 1500000, [$shoeItem, $racketItem]);
        $this->assertTrue($resRacket['success']);
        // 10% of 1,000,000 (racket only) = 100,000
        $this->assertEquals(100000, $resRacket['discount_amount']);
    }
}
