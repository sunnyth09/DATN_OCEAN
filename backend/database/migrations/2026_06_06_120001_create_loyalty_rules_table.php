<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loyalty_rules')) {
            Schema::create('loyalty_rules', function (Blueprint $table) {
                $table->id();

                $table->string('key', 60)->unique();
                $table->enum('type', ['earn', 'burn']);
                $table->string('name', 150);
                $table->string('description', 300)->nullable();

                $table->decimal('points_per_unit', 10, 4)->default(0);
                $table->decimal('vnd_per_point', 10, 2)->default(0);
                $table->unsignedInteger('min_points')->default(0);
                $table->unsignedInteger('max_points_per_order')->nullable();
                $table->decimal('max_burn_percent', 5, 2)->nullable();
                $table->unsignedInteger('earn_expiry_days')->nullable();

                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $now = now();

        DB::table('loyalty_rules')->upsert([
            [
                'key' => 'ORDER_COMPLETE',
                'type' => 'earn',
                'name' => 'Mua hàng tích điểm',
                'description' => '1 điểm cho mỗi 10.000đ giá trị đơn hàng',
                'points_per_unit' => 1,
                'vnd_per_point' => 0,
                'min_points' => 0,
                'max_points_per_order' => null,
                'max_burn_percent' => null,
                'earn_expiry_days' => 365,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'FIRST_ORDER',
                'type' => 'earn',
                'name' => 'Bonus đơn hàng đầu tiên',
                'description' => 'Tặng 200 điểm cho đơn hàng đầu tiên',
                'points_per_unit' => 200,
                'vnd_per_point' => 0,
                'min_points' => 0,
                'max_points_per_order' => null,
                'max_burn_percent' => null,
                'earn_expiry_days' => 365,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'REFERRAL',
                'type' => 'earn',
                'name' => 'Giới thiệu bạn bè',
                'description' => 'Tặng 100 điểm khi bạn bè mua hàng thành công lần đầu',
                'points_per_unit' => 100,
                'vnd_per_point' => 0,
                'min_points' => 0,
                'max_points_per_order' => null,
                'max_burn_percent' => null,
                'earn_expiry_days' => 365,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'BIRTHDAY',
                'type' => 'earn',
                'name' => 'Quà sinh nhật',
                'description' => 'Tặng 300 điểm vào tháng sinh nhật',
                'points_per_unit' => 300,
                'vnd_per_point' => 0,
                'min_points' => 0,
                'max_points_per_order' => null,
                'max_burn_percent' => null,
                'earn_expiry_days' => 90,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'REVIEW',
                'type' => 'earn',
                'name' => 'Viết đánh giá sản phẩm',
                'description' => 'Tặng 20 điểm khi viết review sản phẩm có ít nhất 30 ký tự',
                'points_per_unit' => 20,
                'vnd_per_point' => 0,
                'min_points' => 0,
                'max_points_per_order' => null,
                'max_burn_percent' => null,
                'earn_expiry_days' => 365,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'ABANDONED_CART',
                'type' => 'earn',
                'name' => 'Nhắc giỏ hàng bỏ quên',
                'description' => 'Tặng 50 điểm khi có giỏ hàng bỏ quên',
                'points_per_unit' => 50,
                'vnd_per_point' => 0,
                'min_points' => 0,
                'max_points_per_order' => null,
                'max_burn_percent' => null,
                'earn_expiry_days' => 90,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'REDEEM_DISCOUNT',
                'type' => 'burn',
                'name' => 'Dùng điểm giảm giá',
                'description' => '1 điểm = 100đ giảm giá. Tối thiểu 100 điểm. Tối đa 30% giá trị đơn.',
                'points_per_unit' => 0,
                'vnd_per_point' => 100,
                'min_points' => 100,
                'max_points_per_order' => 5000,
                'max_burn_percent' => 30.00,
                'earn_expiry_days' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['key'], [
            'type',
            'name',
            'description',
            'points_per_unit',
            'vnd_per_point',
            'min_points',
            'max_points_per_order',
            'max_burn_percent',
            'earn_expiry_days',
            'is_active',
            'updated_at',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rules');
    }
};
