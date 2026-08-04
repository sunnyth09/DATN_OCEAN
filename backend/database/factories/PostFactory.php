<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->randomElement([
            'Cách chọn giày thể thao phù hợp cho từng bộ môn',
            'Bí quyết bảo quản vợt cầu lông luôn bền đẹp',
            'Top phụ kiện thể thao nên có trong túi tập',
            'Lịch tập cardio giúp tăng sức bền mỗi tuần',
            'Kinh nghiệm chọn bóng đá cho sân cỏ nhân tạo',
            'Hướng dẫn phối đồ thể thao năng động khi ra sân',
            'Những lỗi thường gặp khi mua dụng cụ tennis',
            'Dấu hiệu nên thay mới giày chạy bộ của bạn',
            'Cách chọn size áo thể thao thoải mái khi vận động',
            'Xu hướng trang phục thể thao nổi bật năm 2026',
        ]).' '.$this->faker->unique()->numberBetween(1000, 9999);

        $postType = $this->faker->randomElement(['news', 'promotion', 'guide', 'review']);
        $publishedAt = $this->faker->dateTimeBetween('-3 months', 'now');

        return [
            'post_category_id' => PostCategory::query()->inRandomOrder()->value('post_category_id')
                ?? PostCategory::query()->create([
                    'name' => 'Tin tức thể thao',
                    'slug' => 'tin-tuc-the-thao',
                    'description' => 'Các bài viết tin tức, hướng dẫn và đánh giá sản phẩm thể thao.',
                    'sort_order' => 1,
                    'is_active' => true,
                ])->post_category_id,
            'author_id' => 1,
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => $this->faker->sentence(18),
            'content' => collect($this->faker->paragraphs(6))->map(fn ($paragraph) => "<p>{$paragraph}</p>")->implode("\n"),
            'thumbnail_url' => $this->faker->randomElement([
                'storage/uploads/posts/post-badminton.jpg',
                'storage/uploads/posts/post-running.jpg',
                'storage/uploads/posts/post-football.jpg',
                'storage/uploads/posts/post-tennis.jpg',
                'storage/uploads/posts/post-training.jpg',
            ]),
            'banner_url' => $this->faker->randomElement([
                'storage/uploads/posts/banner-badminton.jpg',
                'storage/uploads/posts/banner-running.jpg',
                'storage/uploads/posts/banner-football.jpg',
                'storage/uploads/posts/banner-tennis.jpg',
                'storage/uploads/posts/banner-training.jpg',
            ]),
            'seo_title' => $title,
            'seo_description' => $this->faker->sentence(20),
            'seo_keywords' => $this->faker->randomElement([
                'thể thao, dụng cụ thể thao, ocean sport',
                'giày thể thao, phụ kiện thể thao, luyện tập',
                'cầu lông, tennis, bóng đá, chạy bộ',
                'mua sắm thể thao, hướng dẫn chọn đồ thể thao',
            ]),
            'post_type' => $postType,
            'status' => 'published',
            'is_featured' => $this->faker->boolean(25),
            'view_count' => $this->faker->numberBetween(25, 2500),
            'published_at' => $publishedAt,
        ];
    }
}
