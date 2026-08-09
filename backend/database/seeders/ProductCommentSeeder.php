<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductComment;
use App\Models\Product;
use Carbon\Carbon;

class ProductCommentSeeder extends Seeder
{
    /**
     * Tạo đánh giá sản phẩm thực tế cho 20 sản phẩm.
     * - Mỗi sản phẩm được đánh giá bởi 3–6 user khác nhau.
     * - user_id từ 3–9 (người dùng thật, không phải Super Admin).
     * - commenter_type = 'user', is_approved = 1, images = null.
     * - rating và content đa dạng, phù hợp với từng loại sản phẩm thể thao.
     */
    public function run(): void
    {
        // Danh sách nội dung đánh giá theo rating
        $commentTemplates = [
            5 => [
                'Sản phẩm tuyệt vời, chất lượng vượt mong đợi! Giao hàng nhanh, đóng gói cẩn thận.',
                'Rất hài lòng với sản phẩm này. Chất liệu tốt, đúng mô tả. Sẽ mua thêm!',
                'Sản phẩm xịn lắm, dùng thử rồi thấy ổn hơn mong đợi nhiều. Đánh giá 5 sao!',
                'Chất lượng tuyệt, giá cả hợp lý. Shop tư vấn nhiệt tình, giao hàng đúng hẹn.',
                'Mua lần đầu nhưng rất ưng. Sản phẩm y hệt ảnh, chắc chắn sẽ quay lại mua.',
                'Đúng như mô tả, chất lượng cao. Đội ngũ hỗ trợ nhiệt tình. Rất đáng mua!',
                'Dùng được 2 tuần vẫn rất tốt. Không bị phai màu, bền hơn hàng tôi từng mua.',
                'Hàng chính hãng, tem nhãn rõ ràng. Cực kỳ hài lòng với lần mua này.',
            ],
            4 => [
                'Sản phẩm tốt, đúng với mô tả. Giao hàng hơi chậm một chút nhưng nhìn chung OK.',
                'Chất lượng khá ổn, giá hợp lý. Chỉ tiếc là màu sắc thực tế hơi khác ảnh một chút.',
                'Dùng thử thấy tốt, tuy nhiên phần đóng gói cần cải thiện hơn. Sản phẩm vẫn đủ tốt.',
                'Khá hài lòng với sản phẩm, dùng thoải mái. Sẽ cân nhắc mua thêm lần sau.',
                'Sản phẩm đúng mô tả, chất lượng tốt cho tầm giá này. Giao hàng đúng hẹn.',
                'Mua tặng bạn, bạn dùng thấy rất thích. Chất liệu tốt, thiết kế đẹp.',
                'Hàng nhận được ổn, đóng gói kỹ. Trừ 1 sao vì ship hơi lâu so với dự kiến.',
                'Chất lượng vừa phải với giá tiền. Nhìn chung dùng được, không có gì để phàn nàn.',
            ],
            3 => [
                'Sản phẩm tạm ổn, nhưng chưa đáp ứng hoàn toàn kỳ vọng. Chất liệu khá ổn.',
                'Bình thường thôi, không có gì nổi bật. Dùng được nhưng sẽ cân nhắc thương hiệu khác.',
                'Hàng nhận đúng nhưng size hơi nhỏ hơn so với thông số. Vẫn dùng được.',
                'Chất lượng trung bình cho tầm giá. Giao hàng bình thường, không có gì đặc biệt.',
                'Mua về dùng thử thấy chấp nhận được. Nhưng kỳ vọng cao hơn một chút.',
            ],
        ];

        // Dữ liệu: product_id => [rating_avg để định hướng rating]
        // Dựa theo rating_avg đã seeded từ CurrentSportsCatalogSeeder
        $productRatings = [
            1  => 4.7, // Vợt cầu lông BR 100
            2  => 4.6, // Giày pickleball Essential White
            3  => 4.7, // Vợt BR 500 White
            4  => 4.8, // Vợt BR Discover
            5  => 4.6, // Giày BS 500 White
            6  => 4.5, // Vợt Sensation 980 Purple
            7  => 4.7, // Vợt Sensation 190 Blue
            8  => 4.8, // Vợt Sensation 530 Green Black
            9  => 4.5, // Giày BS Lite 350 White Sea Blue
            10 => 4.9, // Bóng chuyền bãi biển BV100 Classic Turquoise
            11 => 4.6, // Bộ lưới bóng chuyền bãi biển BV300 Yellow
            12 => 4.7, // Bộ cần bóng chuyền BV300 Official
            13 => 4.5, // Pickleball Elitex 16MM Blue
            14 => 4.7, // Vợt Pickleball Kulima Open Blue
            15 => 4.6, // Giày tennis/pickleball All Court Light Grey Blue
            16 => 4.8, // Pickleball Paddle 100 Black
            17 => 4.6, // Bó 2 vợt pickleball Fun Play
            18 => 4.7, // Bóng chuyền VB300 Classic White Blue
            19 => 4.8, // Giày BS Sensation 500 White Blue
            20 => 4.5, // Giày cầu lông BS Lite 560 Crystal Orange
        ];

        $userIds = [3, 4, 5, 6, 7, 8, 9]; // user_id thật từ bảng users

        $comments = [];

        foreach ($productRatings as $productId => $avgRating) {
            // Mỗi sản phẩm có 4–6 đánh giá
            $numComments = rand(4, 6);

            // Phân bổ rating để trung bình gần với avgRating
            $ratingPool = $this->generateRatingPool($avgRating, $numComments);
            shuffle($userIds); // Xáo trộn để mỗi sản phẩm có user khác nhau
            $usedUsers = array_slice($userIds, 0, $numComments);

            // Spread created_at trong 6 tháng gần đây
            $baseDate = Carbon::now()->subMonths(6);

            foreach ($usedUsers as $idx => $userId) {
                $rating = $ratingPool[$idx];
                $content = $this->pickComment($commentTemplates, $rating, $productId);
                $createdAt = $baseDate->copy()->addDays(rand(0, 180))->addHours(rand(7, 22));

                $comments[] = [
                    'product_id'      => $productId,
                    'user_id'         => $userId,
                    'commenter_type'  => 'user',
                    'order_item_id'   => null,
                    'rating'          => $rating,
                    'content'         => $content,
                    'images'          => null,
                    'is_approved'     => 1,
                    'created_at'      => $createdAt,
                    'updated_at'      => $createdAt,
                ];
            }
        }

        // Insert tất cả comment một lần
        foreach (array_chunk($comments, 50) as $chunk) {
            \DB::table('product_comments')->insert($chunk);
        }

        // Cập nhật lại rating_count và rating_avg cho từng sản phẩm
        $this->updateProductRatings();

        $this->command->info('✅ ProductCommentSeeder: Đã tạo ' . count($comments) . ' đánh giá cho 20 sản phẩm.');
    }

    /**
     * Tạo pool rating để trung bình gần với $avg.
     */
    private function generateRatingPool(float $avg, int $count): array
    {
        $pool = [];
        $total = 0;

        for ($i = 0; $i < $count - 1; $i++) {
            // Sinh rating ngẫu nhiên gần avg: 4 hoặc 5 nếu avg >= 4.5, 3–5 nếu avg < 4.5
            if ($avg >= 4.7) {
                $r = rand(0, 100) < 75 ? 5 : 4; // 75% là 5 sao
            } elseif ($avg >= 4.4) {
                $r = rand(0, 100) < 55 ? 5 : 4; // 55% là 5 sao
            } else {
                $weights = [3 => 10, 4 => 50, 5 => 40];
                $r = $this->weightedRandom($weights);
            }
            $pool[] = $r;
            $total += $r;
        }

        // Rating cuối điều chỉnh để trung bình gần target
        $targetTotal = round($avg * $count);
        $last = max(1, min(5, $targetTotal - $total));
        $pool[] = $last;

        return $pool;
    }

    /**
     * Chọn nội dung comment dựa theo rating và product_id để không bị lặp.
     */
    private function pickComment(array $templates, int $rating, int $productId): string
    {
        // Fallback về 4 nếu rating không có template
        $key = isset($templates[$rating]) ? $rating : 4;
        $list = $templates[$key];
        return $list[($productId + $rating) % count($list)];
    }

    /**
     * Weighted random choice.
     */
    private function weightedRandom(array $weights): int
    {
        $total = array_sum($weights);
        $rand = rand(1, $total);
        $cumulative = 0;
        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) return $value;
        }
        return array_key_last($weights);
    }

    /**
     * Cập nhật rating_count và rating_avg trên bảng products sau khi seed xong.
     */
    private function updateProductRatings(): void
    {
        $stats = \DB::table('product_comments')
            ->where('is_approved', 1)
            ->selectRaw('product_id, COUNT(*) as cnt, AVG(rating) as avg_rating')
            ->groupBy('product_id')
            ->get();

        foreach ($stats as $stat) {
            \DB::table('products')
                ->where('product_id', $stat->product_id)
                ->update([
                    'rating_count' => $stat->cnt,
                    'rating_avg'   => round($stat->avg_rating, 1),
                ]);
        }

        $this->command->info('✅ Đã cập nhật rating_count và rating_avg cho ' . $stats->count() . ' sản phẩm.');
    }
}
