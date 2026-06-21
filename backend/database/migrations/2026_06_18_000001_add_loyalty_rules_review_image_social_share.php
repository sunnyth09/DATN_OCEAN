<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Upsert thêm rule REVIEW_WITH_IMAGE và SOCIAL_SHARE
        DB::table('loyalty_rules')->upsert([
            [
                'key'                  => 'REVIEW_WITH_IMAGE',
                'type'                 => 'earn',
                'name'                 => 'Đánh giá kèm hình ảnh',
                'description'          => 'Tặng thêm 50 điểm khi đánh giá sản phẩm kèm hình ảnh',
                'points_per_unit'      => 50,
                'vnd_per_point'        => 0,
                'min_points'           => 0,
                'max_points_per_order' => null,
                'max_burn_percent'     => null,
                'earn_expiry_days'     => 365,
                'is_active'            => true,
                'created_at'           => $now,
                'updated_at'           => $now,
            ],
            [
                'key'                  => 'SOCIAL_SHARE',
                'type'                 => 'earn',
                'name'                 => 'Chia sẻ sản phẩm',
                'description'          => 'Tặng 30 điểm khi chia sẻ sản phẩm lên mạng xã hội',
                'points_per_unit'      => 30,
                'vnd_per_point'        => 0,
                'min_points'           => 0,
                'max_points_per_order' => null,
                'max_burn_percent'     => null,
                'earn_expiry_days'     => 365,
                'is_active'            => true,
                'created_at'           => $now,
                'updated_at'           => $now,
            ],
        ], ['key'], [
            'type', 'name', 'description', 'points_per_unit', 'vnd_per_point',
            'min_points', 'max_points_per_order', 'max_burn_percent',
            'earn_expiry_days', 'is_active', 'updated_at',
        ]);

        // Cập nhật points cho các rule hiện có theo đúng spec mới
        DB::table('loyalty_rules')->where('key', 'REVIEW')->update([
            'points_per_unit' => 20,
            'description'     => 'Tặng 20 điểm khi viết đánh giá sản phẩm có nội dung',
            'updated_at'      => $now,
        ]);

        DB::table('loyalty_rules')->where('key', 'BIRTHDAY')->update([
            'points_per_unit' => 100,
            'description'     => 'Tặng 100 điểm vào ngày sinh nhật khách hàng',
            'updated_at'      => $now,
        ]);

        DB::table('loyalty_rules')->where('key', 'REFERRAL')->update([
            'points_per_unit' => 200,
            'description'     => 'Tặng 200 điểm khi giới thiệu bạn bè mua hàng thành công',
            'updated_at'      => $now,
        ]);

        DB::table('loyalty_rules')->where('key', 'ABANDONED_CART')->update([
            'points_per_unit' => 30,
            'description'     => 'Tặng 30 điểm khi quay lại hoàn tất đơn hàng từ giỏ bỏ quên',
            'updated_at'      => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('loyalty_rules')->whereIn('key', ['REVIEW_WITH_IMAGE', 'SOCIAL_SHARE'])->delete();
    }
};
