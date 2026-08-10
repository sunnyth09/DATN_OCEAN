<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductComment;
use App\Models\Product;
use Carbon\Carbon;

class ProductCommentSeeder extends Seeder
{
    /**
     * Tạo đánh giá sản phẩm thực tế cho các sản phẩm từ ID 166 đến 246.
     * - Mỗi sản phẩm được đánh giá bởi đúng 5 user khác nhau.
     * - user_id lấy động từ bảng users (hoặc fallback từ 3-9 nếu bảng trống).
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
                'Vợt dùng rất êm, căng sẵn lực tốt, đập cầu rất đầm tay!',
                'Giày đi vừa vặn, êm chân, bám sân cực tốt không bị trơn trượt.',
                'Áo co giãn tốt, thấm hút mồ hôi nhanh, mặc rất mát khi vận động mạnh.',
                'Bóng nảy tốt, da mềm, đường may chắc chắn. Rất đáng tiền.'
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
                'Sản phẩm tốt, đúng mô tả. Giao hàng hơi chậm chút nhưng bù lại chất lượng rất ổn.',
                'Form giày hơi ôm so với chân mình, nhưng chất lượng da và đế thì không có gì chê.',
                'Chất liệu vải mát, co giãn tốt. Chỉ tiếc là màu ngoài đời tối hơn trong ảnh một tí.'
            ],
            3 => [
                'Sản phẩm tạm ổn, nhưng chưa đáp ứng hoàn toàn kỳ vọng. Chất liệu khá ổn.',
                'Bình thường thôi, không có gì nổi bật. Dùng được nhưng sẽ cân nhắc thương hiệu khác.',
                'Hàng nhận đúng nhưng size hơi nhỏ hơn so với thông số. Vẫn dùng được.',
                'Chất lượng trung bình cho tầm giá. Giao hàng bình thường, không có gì đặc biệt.',
                'Mua về dùng thử thấy chấp nhận được. Nhưng kỳ vọng cao hơn một chút.',
                'Sản phẩm tạm ổn, nhưng vải hơi mỏng so với hình dung của mình.',
                'Đế giày hơi cứng, cần đi vài hôm cho mềm ra. Giao hàng nhanh.',
                'Bình thường thôi, không có gì quá nổi bật. Dùng được ở mức cơ bản.'
            ],
        ];

        // Lấy danh sách sản phẩm từ 166 đến 246
        $products = \DB::table('products')
            ->whereBetween('product_id', [166, 246])
            ->get();

        if ($products->isEmpty()) {
            $this->command->warn('⚠️ Không tìm thấy sản phẩm nào trong khoảng ID từ 166 đến 246 trong DB. Sử dụng danh sách ID giả lập.');
            $productRatings = [];
            for ($id = 166; $id <= 246; $id++) {
                $productRatings[$id] = round(rand(40, 50) / 10, 1); // target rating từ 4.0 đến 5.0
            }
        } else {
            $productRatings = [];
            foreach ($products as $product) {
                $productRatings[$product->product_id] = $product->rating_avg ?: 4.5;
            }
        }

        // Lấy danh sách user_id thật từ bảng users
        $userIds = \DB::table('users')->pluck('user_id')->toArray();
        if (empty($userIds)) {
            $userIds = [3, 4, 5, 6, 7, 8, 9]; // fallback user_ids
        }

        // Xóa các bình luận cũ của các sản phẩm từ 166 đến 246 trước khi seed để tránh trùng lặp
        \DB::table('product_comments')
            ->whereBetween('product_id', [166, 246])
            ->delete();

        $comments = [];
        $numComments = 5; // Số lượng feedback cho mỗi sản phẩm

        foreach ($productRatings as $productId => $avgRating) {
            // Phân bổ rating để trung bình gần với avgRating
            $ratingPool = $this->generateRatingPool($avgRating, $numComments);
            
            // Xáo trộn và lấy ra các user khác nhau không bị trùng lặp trong cùng 1 sản phẩm
            $usedUsers = [];
            $tempUsers = $userIds;
            for ($i = 0; $i < $numComments; $i++) {
                if (empty($tempUsers)) {
                    $tempUsers = $userIds;
                }
                shuffle($tempUsers);
                $usedUsers[] = array_pop($tempUsers);
            }

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

        $this->command->info('✅ ProductCommentSeeder: Đã tạo ' . count($comments) . ' đánh giá cho ' . count($productRatings) . ' sản phẩm (IDs 166 -> 246).');
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
