<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\LuckyWheelPrize;
use App\Models\User;
use App\Models\UserCoupon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LuckyWheelAndCheckInTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach (['user_coupons', 'coupons', 'lucky_wheel_prizes', 'loyalty_transactions', 'loyalty_rules', 'notifications', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('full_name')->default('Test User');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->default('customer');
            $table->string('status')->default('active');
            $table->integer('reward_points')->default(0);
            $table->date('last_check_in_at')->nullable();
            $table->unsignedInteger('check_in_streak')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type')->default('percent');
            $table->decimal('value', 12, 2)->default(0);
            $table->decimal('max_discount_value', 12, 2)->nullable();
            $table->decimal('min_order_value', 12, 2)->default(0);
            $table->integer('usage_limit')->nullable();
            $table->integer('user_usage_limit')->default(1);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_first_order')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('coupon_id');
            $table->boolean('is_saved')->default(false);
            $table->integer('used_count')->default(0);
            $table->timestamps();
        });

        Schema::create('lucky_wheel_prizes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->integer('value')->default(0);
            $table->decimal('probability', 5, 2)->default(0);
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 20);
            $table->integer('points');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        $this->user = new User();
        $this->user->full_name = 'John Doe';
        $this->user->email = 'john@example.com';
        $this->user->reward_points = 100;
        $this->user->save();

        LuckyWheelPrize::create([
            'name' => '50 Xu',
            'type' => 'points',
            'value' => 50,
            'probability' => 100.00,
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'DISCOUNT10',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
        ]);
    }

    public function test_get_lucky_wheel_prizes(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/loyalty/lucky-wheel');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_check_in_workflow(): void
    {
        // Lần 1: Điểm danh thành công
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/loyalty/check-in');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'check_in_streak' => 1,
                    'points_earned' => 10,
                ],
            ]);

        // Lần 2 trong cùng ngày: Bị từ chối
        $duplicateResponse = $this->actingAs($this->user, 'api')
            ->postJson('/api/loyalty/check-in');

        $duplicateResponse->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Bạn đã điểm danh hôm nay rồi.',
            ]);
    }

    public function test_spin_lucky_wheel_insufficient_points(): void
    {
        $poorUser = new User();
        $poorUser->full_name = 'Poor User';
        $poorUser->email = 'poor@example.com';
        $poorUser->reward_points = 10;
        $poorUser->save();

        $response = $this->actingAs($poorUser, 'api')
            ->postJson('/api/loyalty/lucky-wheel/spin');

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Bạn cần 50 xu để quay vòng quay.',
            ]);
    }

    public function test_spin_lucky_wheel_success_and_history(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/loyalty/lucky-wheel/spin');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Quay thành công!',
            ]);

        // Kiểm tra lịch sử giao dịch
        $historyResponse = $this->actingAs($this->user, 'api')
            ->getJson('/api/loyalty/history');

        $historyResponse->assertStatus(200);

        // Kiểm tra loyalty summary
        $summaryResponse = $this->actingAs($this->user, 'api')
            ->getJson('/api/loyalty/summary');

        $summaryResponse->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'current_balance',
                    'total_earned',
                    'total_burned',
                    'last_check_in_at',
                    'check_in_streak',
                    'has_checked_in_today',
                ],
            ]);
    }
}
