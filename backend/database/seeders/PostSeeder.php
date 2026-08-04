<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostCategory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            [
                'name' => 'Tin tức thể thao',
                'slug' => 'tin-tuc-the-thao',
                'description' => 'Cập nhật tin tức mới nhất về thể thao và xu hướng luyện tập.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Hướng dẫn chọn đồ',
                'slug' => 'huong-dan-chon-do',
                'description' => 'Kinh nghiệm chọn giày, quần áo và dụng cụ thể thao phù hợp.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Khuyến mãi Ocean Sport',
                'slug' => 'khuyen-mai-ocean-sport',
                'description' => 'Thông tin ưu đãi, flash sale và chương trình khuyến mãi.',
                'sort_order' => 3,
            ],
            [
                'name' => 'Đánh giá sản phẩm',
                'slug' => 'danh-gia-san-pham',
                'description' => 'Đánh giá chi tiết các sản phẩm thể thao nổi bật.',
                'sort_order' => 4,
            ],
        ])->mapWithKeys(function (array $category) {
            $model = PostCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'parent_id' => null,
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'thumbnail_url' => null,
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ]
            );

            return [$category['slug'] => $model->post_category_id];
        });

        $posts = [
            [
                'post_category_id' => $categories['tin-tuc-the-thao'],
                'title' => 'Ocean Sport ra mắt bộ sưu tập thể thao năng động 2026',
                'summary' => 'Bộ sưu tập mới tập trung vào chất liệu thoáng khí, form dáng linh hoạt và màu sắc trẻ trung cho người yêu vận động.',
                'content' => '<p>Ocean Sport giới thiệu bộ sưu tập thể thao 2026 với tinh thần năng động, hiện đại và phù hợp nhiều bộ môn khác nhau.</p><p>Các sản phẩm được tối ưu về độ thoáng khí, khả năng co giãn và độ bền để đồng hành cùng khách hàng trong quá trình tập luyện hằng ngày.</p><p>Bộ sưu tập bao gồm giày, áo thể thao, phụ kiện và dụng cụ hỗ trợ luyện tập với mức giá dễ tiếp cận.</p>',
                'thumbnail_url' => 'storage/uploads/posts/ocean-sport-collection-2026.jpg',
                'banner_url' => 'storage/uploads/posts/ocean-sport-collection-2026-banner.jpg',
                'post_type' => 'news',
                'is_featured' => true,
                'view_count' => 1280,
                'published_at' => Carbon::now()->subDays(2),
                'seo_keywords' => 'ocean sport, bộ sưu tập thể thao 2026, đồ thể thao',
            ],
            [
                'post_category_id' => $categories['huong-dan-chon-do'],
                'title' => '5 tiêu chí chọn giày chạy bộ phù hợp cho người mới bắt đầu',
                'summary' => 'Chọn đúng giày chạy bộ giúp giảm áp lực lên bàn chân, hạn chế chấn thương và duy trì cảm giác thoải mái khi tập luyện.',
                'content' => '<p>Giày chạy bộ phù hợp cần đáp ứng các yếu tố về size, độ ôm chân, độ đàn hồi của đế và trọng lượng tổng thể.</p><p>Người mới bắt đầu nên ưu tiên mẫu giày có đệm êm, phần upper thoáng khí và độ bám tốt trên nhiều bề mặt.</p><p>Ngoài ra, bạn nên thử giày vào cuối ngày để chọn size chính xác hơn vì bàn chân thường giãn nhẹ sau quá trình vận động.</p>',
                'thumbnail_url' => 'storage/uploads/posts/running-shoes-guide.jpg',
                'banner_url' => 'storage/uploads/posts/running-shoes-guide-banner.jpg',
                'post_type' => 'guide',
                'is_featured' => true,
                'view_count' => 965,
                'published_at' => Carbon::now()->subDays(5),
                'seo_keywords' => 'giày chạy bộ, chọn giày thể thao, hướng dẫn chạy bộ',
            ],
            [
                'post_category_id' => $categories['khuyen-mai-ocean-sport'],
                'title' => 'Flash Sale cuối tuần: Giảm giá đến 50% cho phụ kiện thể thao',
                'summary' => 'Ocean Sport triển khai chương trình flash sale cuối tuần với nhiều ưu đãi hấp dẫn cho phụ kiện và dụng cụ tập luyện.',
                'content' => '<p>Chương trình flash sale cuối tuần mang đến mức giảm giá hấp dẫn cho các nhóm sản phẩm như bình nước, băng cổ tay, túi thể thao và phụ kiện tập luyện.</p><p>Số lượng sản phẩm ưu đãi có hạn và được cập nhật liên tục trên website Ocean Sport.</p><p>Khách hàng nên thêm sản phẩm yêu thích vào giỏ hàng trước để không bỏ lỡ khung giờ giá tốt.</p>',
                'thumbnail_url' => 'storage/uploads/posts/weekend-flash-sale.jpg',
                'banner_url' => 'storage/uploads/posts/weekend-flash-sale-banner.jpg',
                'post_type' => 'promotion',
                'is_featured' => false,
                'view_count' => 1540,
                'published_at' => Carbon::now()->subDays(7),
                'seo_keywords' => 'flash sale, khuyến mãi thể thao, phụ kiện thể thao',
            ],
            [
                'post_category_id' => $categories['danh-gia-san-pham'],
                'title' => 'Đánh giá vợt cầu lông cân bằng cho lối chơi công thủ toàn diện',
                'summary' => 'Dòng vợt cân bằng là lựa chọn phù hợp cho người chơi phong trào muốn kiểm soát tốt cả tấn công lẫn phòng thủ.',
                'content' => '<p>Vợt cầu lông cân bằng thường có điểm cân bằng nằm ở mức trung tính, giúp người chơi dễ xoay trở trong các pha cầu nhanh.</p><p>Ưu điểm lớn nhất của nhóm vợt này là khả năng thích nghi tốt với nhiều phong cách đánh, từ bỏ nhỏ, điều cầu đến đập cầu.</p><p>Nếu bạn chưa xác định rõ lối chơi, đây là lựa chọn an toàn để bắt đầu và nâng cấp kỹ năng.</p>',
                'thumbnail_url' => 'storage/uploads/posts/badminton-racket-review.jpg',
                'banner_url' => 'storage/uploads/posts/badminton-racket-review-banner.jpg',
                'post_type' => 'review',
                'is_featured' => false,
                'view_count' => 720,
                'published_at' => Carbon::now()->subDays(10),
                'seo_keywords' => 'vợt cầu lông, đánh giá vợt, dụng cụ cầu lông',
            ],
            [
                'post_category_id' => $categories['huong-dan-chon-do'],
                'title' => 'Cách bảo quản quần áo thể thao luôn bền màu và khử mùi tốt',
                'summary' => 'Giặt và phơi đúng cách giúp quần áo thể thao giữ form, bền màu và hạn chế mùi khó chịu sau mỗi buổi tập.',
                'content' => '<p>Quần áo thể thao thường sử dụng chất liệu co giãn và thoát mồ hôi nhanh, vì vậy cần được giặt nhẹ và tránh nhiệt độ quá cao.</p><p>Bạn nên lộn trái sản phẩm trước khi giặt, dùng nước lạnh và hạn chế chất tẩy mạnh để bảo vệ sợi vải.</p><p>Sau khi giặt, hãy phơi ở nơi thoáng gió, tránh ánh nắng gắt trực tiếp trong thời gian dài.</p>',
                'thumbnail_url' => 'storage/uploads/posts/sportswear-care.jpg',
                'banner_url' => 'storage/uploads/posts/sportswear-care-banner.jpg',
                'post_type' => 'guide',
                'is_featured' => false,
                'view_count' => 610,
                'published_at' => Carbon::now()->subDays(14),
                'seo_keywords' => 'quần áo thể thao, bảo quản đồ thể thao, khử mùi quần áo',
            ],
        ];

        foreach ($posts as $post) {
            $slug = Str::slug($post['title']);

            Post::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'post_category_id' => $post['post_category_id'],
                    'author_id' => 1,
                    'title' => $post['title'],
                    'summary' => $post['summary'],
                    'content' => $post['content'],
                    'thumbnail_url' => $post['thumbnail_url'],
                    'banner_url' => $post['banner_url'],
                    'seo_title' => $post['title'],
                    'seo_description' => $post['summary'],
                    'seo_keywords' => $post['seo_keywords'],
                    'post_type' => $post['post_type'],
                    'status' => 'published',
                    'is_featured' => $post['is_featured'],
                    'view_count' => $post['view_count'],
                    'published_at' => $post['published_at'],
                ]
            );
        }

        Post::factory()->count(20)->create([
            'author_id' => 1,
            'status' => 'published',
        ]);
    }
}
