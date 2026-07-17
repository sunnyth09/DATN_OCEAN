<?php

namespace Tests\Feature;

use App\Models\LoyaltyRule;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoyaltyServiceTest extends TestCase
{
    private LoyaltyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LoyaltyService();

        foreach (['loyalty_transactions', 'loyalty_rules', 'orders', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->default('customer');
            $table->string('status')->default('active');
            $table->integer('reward_points')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->string('order_code', 30)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('payment_method')->default('cod');
            $table->string('payment_status')->default('unpaid');
            $table->string('fulfillment_status')->default('pending');
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->boolean('email_sent')->default(false);
            $table->timestamps();
        });

        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type')->default('earn');
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->float('points_per_unit')->default(0);
            $table->float('vnd_per_point')->default(0);
            $table->integer('min_points')->default(0);
            $table->integer('max_points_per_order')->nullable();
            $table->float('max_burn_percent')->nullable();
            $table->integer('earn_expiry_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->integer('points');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->timestamps();
        });
    }

    // ─── EARN ────────────────────────────────────────────────────────────

    public function test_earn_from_order_floors_points_by_ten_thousand(): void
    {
        LoyaltyRule::create(['key' => 'ORDER_COMPLETE', 'type' => 'earn', 'points_per_unit' => 1, 'is_active' => true]);
        $user = $this->makeUser();
        $order = $this->makeOrder($user->user_id, 125000); // 12.5 → floor 12

        $tx = $this->service->earnFromOrder($user, $order);

        $this->assertNotNull($tx);
        $this->assertSame(12, $tx->points);
        $this->assertSame(12, $this->service->getBalance($user->user_id));
    }

    public function test_earn_from_order_returns_null_when_points_zero(): void
    {
        LoyaltyRule::create(['key' => 'ORDER_COMPLETE', 'type' => 'earn', 'points_per_unit' => 1, 'is_active' => true]);
        $user = $this->makeUser();
        $order = $this->makeOrder($user->user_id, 5000); // 0.5 → floor 0

        $this->assertNull($this->service->earnFromOrder($user, $order));
        $this->assertSame(0, $this->service->getBalance($user->user_id));
    }

    public function test_earn_returns_null_when_rule_inactive(): void
    {
        LoyaltyRule::create(['key' => 'ORDER_COMPLETE', 'type' => 'earn', 'points_per_unit' => 1, 'is_active' => false]);
        $user = $this->makeUser();
        $order = $this->makeOrder($user->user_id, 500000);

        $this->assertNull($this->service->earnFromOrder($user, $order));
    }

    // ─── BURN ────────────────────────────────────────────────────────────

    public function test_burn_points_decrements_balance(): void
    {
        LoyaltyRule::create(['key' => 'REDEEM_DISCOUNT', 'type' => 'burn', 'vnd_per_point' => 100, 'is_active' => true]);
        $user = $this->makeUser(500);
        $order = $this->makeOrder($user->user_id, 200000);

        $tx = $this->service->burnPoints($user, 300, $order);

        $this->assertSame(300, $tx->points);
        $this->assertSame(500, $tx->balance_before);
        $this->assertSame(200, $tx->balance_after);
        $this->assertSame(200, $this->service->getBalance($user->user_id));
    }

    public function test_burn_throws_when_insufficient_points(): void
    {
        LoyaltyRule::create(['key' => 'REDEEM_DISCOUNT', 'type' => 'burn', 'vnd_per_point' => 100, 'is_active' => true]);
        $user = $this->makeUser(100);
        $order = $this->makeOrder($user->user_id, 200000);

        $this->expectException(\Exception::class);
        $this->service->burnPoints($user, 300, $order);
    }

    public function test_burn_rejects_non_positive(): void
    {
        $user = $this->makeUser(100);
        $order = $this->makeOrder($user->user_id, 200000);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->burnPoints($user, 0, $order);
    }

    // ─── REFUND ──────────────────────────────────────────────────────────

    public function test_refund_points_restores_burned_amount(): void
    {
        LoyaltyRule::create(['key' => 'REDEEM_DISCOUNT', 'type' => 'burn', 'vnd_per_point' => 100, 'is_active' => true]);
        $user = $this->makeUser(500);
        $order = $this->makeOrder($user->user_id, 200000);
        $this->service->burnPoints($user, 300, $order);

        $tx = $this->service->refundPoints($user, $order);

        $this->assertNotNull($tx);
        $this->assertSame(300, $tx->points);
        $this->assertSame(500, $this->service->getBalance($user->user_id));
    }

    public function test_refund_is_idempotent(): void
    {
        LoyaltyRule::create(['key' => 'REDEEM_DISCOUNT', 'type' => 'burn', 'vnd_per_point' => 100, 'is_active' => true]);
        $user = $this->makeUser(500);
        $order = $this->makeOrder($user->user_id, 200000);
        $this->service->burnPoints($user, 300, $order);

        $this->service->refundPoints($user, $order);
        $second = $this->service->refundPoints($user, $order);

        $this->assertNull($second);
        $this->assertSame(500, $this->service->getBalance($user->user_id));
    }

    public function test_refund_returns_null_when_no_burn(): void
    {
        $user = $this->makeUser(100);
        $order = $this->makeOrder($user->user_id, 200000);

        $this->assertNull($this->service->refundPoints($user, $order));
    }

    // ─── ADMIN ADJUST ──────────────────────────────────────────────────────

    public function test_adjust_positive_adds_points(): void
    {
        $user = $this->makeUser(100);
        $tx = $this->service->adjustPoints($user->user_id, 50, 'Bonus', 1);

        $this->assertSame(50, $tx->points);
        $this->assertSame(150, $this->service->getBalance($user->user_id));
    }

    public function test_adjust_negative_cannot_go_below_zero(): void
    {
        $user = $this->makeUser(30);

        $this->expectException(\Exception::class);
        $this->service->adjustPoints($user->user_id, -50, 'Trừ điểm', 1);
    }

    // ─── EXPIRY ──────────────────────────────────────────────────────────

    public function test_expire_points_deducts_expired_earns(): void
    {
        $user = $this->makeUser(100);
        // earn transaction đã quá hạn
        LoyaltyTransaction::create([
            'user_id' => $user->user_id,
            'type' => 'earn',
            'points' => 40,
            'balance_before' => 60,
            'balance_after' => 100,
            'reference_type' => 'system',
            'expires_at' => now()->subDay(),
        ]);

        $count = $this->service->expirePoints();

        $this->assertSame(1, $count);
        $this->assertSame(60, $this->service->getBalance($user->user_id));
        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $user->user_id,
            'type' => 'expire',
            'points' => 40,
        ]);
    }

    public function test_expire_capped_at_current_balance(): void
    {
        $user = $this->makeUser(10); // balance thấp hơn điểm hết hạn
        LoyaltyTransaction::create([
            'user_id' => $user->user_id,
            'type' => 'earn',
            'points' => 40,
            'balance_before' => 0,
            'balance_after' => 40,
            'reference_type' => 'system',
            'expires_at' => now()->subDay(),
        ]);

        $this->service->expirePoints();

        // không thể expire âm — balance về 0, không xuống -30
        $this->assertSame(0, $this->service->getBalance($user->user_id));
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────

    private function makeUser(int $rewardPoints = 0): User
    {
        $user = User::create([
            'full_name' => 'Loyalty Tester',
            'email' => 'loyalty' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        // reward_points bị loại khỏi $fillable (chống mass-assignment) → set qua DB
        \Illuminate\Support\Facades\DB::table('users')
            ->where('user_id', $user->user_id)
            ->update(['reward_points' => $rewardPoints]);

        return $user->fresh();
    }

    private function makeOrder(int $userId, float $grandTotal): Order
    {
        return Order::create([
            'order_code' => 'ORD-' . strtoupper(uniqid()),
            'user_id' => $userId,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'completed',
            'grand_total' => $grandTotal,
            'email_sent' => false,
        ]);
    }
}
