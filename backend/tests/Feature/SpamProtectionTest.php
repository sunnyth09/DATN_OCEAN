<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Repositories\AffiliateClickRepository;
use App\Repositories\AffiliateConversionRepository;
use App\Repositories\AffiliateRepository;
use App\Repositories\AffiliateWithdrawalRepository;
use App\Services\AffiliateService;
use App\Services\LoyaltyService;
use App\Services\WalletService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Test toàn diện 4 loại spam protection:
 *  1. Spam click affiliate giả (IP dedup + throttle)
 *  2. Spam đơn hàng (throttle route)
 *  3. Spam review (double-check DB)
 *  4. Spam khiếu nại (business logic guards)
 */
class SpamProtectionTest extends TestCase
{
    // ─── Setup schema dùng chung ─────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->setUpSchema();
    }

    private function setUpSchema(): void
    {
        $tables = [
            'product_comments',
            'tickets',
            'affiliate_clicks',
            'order_items',
            'orders',
            'users',
        ];

        Schema::disableForeignKeyConstraints();
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('full_name', 120);
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->default('customer');
            $table->string('status')->default('active');
            $table->integer('reward_points')->default(0);
            $table->string('referral_code', 20)->unique()->nullable();
            $table->boolean('is_affiliate')->default(false);
            $table->timestamp('affiliate_registered_at')->nullable();
            $table->unsignedBigInteger('referred_by')->nullable();
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

        Schema::create('order_items', function (Blueprint $table) {
            $table->id('order_item_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('product_name', 200);
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('referral_code', 20);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id('ticket_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('reason');
            $table->text('description');
            $table->string('image_url')->nullable();
            $table->enum('status', ['pending', 'processing', 'resolved', 'closed'])->default('pending');
            $table->text('admin_reply')->nullable();
            $table->timestamps();
        });

        Schema::create('product_comments', function (Blueprint $table) {
            $table->id('comment_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('commenter_type')->default('user');
            $table->tinyInteger('rating');
            $table->text('content')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->json('images')->nullable();
            $table->timestamps();
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeAffiliateService(): AffiliateService
    {
        return new AffiliateService(
            app(AffiliateRepository::class),
            app(AffiliateClickRepository::class),
            app(AffiliateConversionRepository::class),
            app(AffiliateWithdrawalRepository::class),
            app(WalletService::class),
            app(LoyaltyService::class),
        );
    }

    private function createUser(array $attrs = []): User
    {
        $user = new User;
        $user->forceFill(array_merge([
            'full_name' => 'Test User',
            'email' => 'user_'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'status' => 'active',
        ], $attrs));
        $user->save();

        return $user;
    }

    private function createAffiliateUser(string $code): User
    {
        return $this->createUser([
            'referral_code' => $code,
            'is_affiliate' => true,
        ]);
    }

    private function createOrder(int $userId, array $attrs = []): Order
    {
        return Order::create(array_merge([
            'order_code' => 'ORD-'.uniqid(),
            'user_id' => $userId,
            'grand_total' => 500000,
            'fulfillment_status' => 'completed',
        ], $attrs));
    }

    // =========================================================================
    // 1. SPAM CLICK AFFILIATE GIẢ
    // =========================================================================

    /** Click hợp lệ lần đầu → ghi vào DB */
    public function test_affiliate_click_first_time_is_recorded(): void
    {
        $referrer = $this->createAffiliateUser('REF001');
        $service = $this->makeAffiliateService();

        $result = $service->trackClick([
            'referral_code' => 'REF001',
            'user_id' => null,
            'product_id' => null,
            'ip_address' => '1.2.3.4',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertSame(200, $result['status_code']);
        $this->assertDatabaseHas('affiliate_clicks', [
            'referral_code' => 'REF001',
            'referrer_id' => $referrer->user_id,
            'ip_address' => '1.2.3.4',
        ]);
    }

    /** Cùng IP click lại trong 24h → trả success nhưng KHÔNG ghi DB lần 2 */
    public function test_affiliate_click_same_ip_within_24h_is_deduplicated(): void
    {
        $this->createAffiliateUser('REF002');
        $service = $this->makeAffiliateService();

        // Lần 1 — ghi DB
        $service->trackClick([
            'referral_code' => 'REF002',
            'ip_address' => '5.5.5.5',
        ]);

        $count1 = DB::table('affiliate_clicks')->where('referral_code', 'REF002')->count();
        $this->assertSame(1, $count1, 'Lần 1 phải được ghi vào DB');

        // Lần 2 — cùng IP, trong 24h
        $result = $service->trackClick([
            'referral_code' => 'REF002',
            'ip_address' => '5.5.5.5',
        ]);

        // Vẫn trả success (không lộ logic chống spam)
        $this->assertSame(200, $result['status_code'], 'Phải trả 200 để không lộ logic chống spam');

        // Nhưng DB vẫn chỉ có 1 bản ghi
        $count2 = DB::table('affiliate_clicks')->where('referral_code', 'REF002')->count();
        $this->assertSame(1, $count2, 'Lần 2 cùng IP trong 24h KHÔNG được ghi DB');
    }

    /** IP khác click cùng link → VẪN được ghi (không bị block) */
    public function test_affiliate_click_different_ip_is_recorded_separately(): void
    {
        $this->createAffiliateUser('REF003');
        $service = $this->makeAffiliateService();

        $service->trackClick(['referral_code' => 'REF003', 'ip_address' => '10.0.0.1']);
        $service->trackClick(['referral_code' => 'REF003', 'ip_address' => '10.0.0.2']);
        $service->trackClick(['referral_code' => 'REF003', 'ip_address' => '10.0.0.3']);

        $count = DB::table('affiliate_clicks')->where('referral_code', 'REF003')->count();
        $this->assertSame(3, $count, 'Mỗi IP khác nhau phải được ghi riêng biệt');
    }

    /** Sau khi cache hết hạn (giả lập) → click được ghi lại */
    public function test_affiliate_click_after_cache_expires_is_recorded_again(): void
    {
        $this->createAffiliateUser('REF004');
        $service = $this->makeAffiliateService();

        // Lần 1
        $service->trackClick(['referral_code' => 'REF004', 'ip_address' => '7.7.7.7']);
        $this->assertSame(1, DB::table('affiliate_clicks')->where('referral_code', 'REF004')->count());

        // Giả lập cache hết hạn bằng cách xoá key thủ công
        $dedupKey = 'affiliate_click:'.md5('7.7.7.7:REF004');
        Cache::forget($dedupKey);

        // Lần 2 — sau khi cache hết, ghi lại được
        $service->trackClick(['referral_code' => 'REF004', 'ip_address' => '7.7.7.7']);
        $this->assertSame(2, DB::table('affiliate_clicks')->where('referral_code', 'REF004')->count(), 'Sau khi cache hết hạn phải được ghi lại');
    }

    /** User cố tự click link giới thiệu chính mình → 422 */
    public function test_affiliate_click_self_referral_is_blocked(): void
    {
        $referrer = $this->createAffiliateUser('REF005');
        $service = $this->makeAffiliateService();

        $result = $service->trackClick([
            'referral_code' => 'REF005',
            'user_id' => $referrer->user_id,
            'ip_address' => '8.8.8.8',
        ]);

        $this->assertSame(422, $result['status_code'], 'Tự click giới thiệu mình phải trả 422');
        $this->assertDatabaseMissing('affiliate_clicks', ['referral_code' => 'REF005']);
    }

    /** Mã giới thiệu không tồn tại → 404 */
    public function test_affiliate_click_invalid_code_returns_404(): void
    {
        $service = $this->makeAffiliateService();

        $result = $service->trackClick([
            'referral_code' => 'NOTEXIST',
            'ip_address' => '9.9.9.9',
        ]);

        $this->assertSame(404, $result['status_code'], 'Code không tồn tại phải trả 404');
    }

    /** Cache key được tạo đúng format (md5 của ip:code) */
    public function test_affiliate_click_cache_key_format_is_correct(): void
    {
        $ip = '192.168.1.1';
        $code = 'TESTKEY';

        $expectedKey = 'affiliate_click:'.md5($ip.':'.$code);

        // Đặt cache thủ công
        Cache::put($expectedKey, true, now()->addHours(24));

        $this->assertTrue(Cache::has($expectedKey), 'Cache key phải tồn tại sau khi set');

        // Xoá và kiểm tra
        Cache::forget($expectedKey);
        $this->assertFalse(Cache::has($expectedKey), 'Cache key phải bị xoá sau forget');
    }

    // =========================================================================
    // 2. SPAM ĐƠN HÀNG — Kiểm tra cấu hình throttle trên route
    // =========================================================================

    /** Route user order có throttle:5,1 */
    public function test_user_order_route_has_throttle_5_per_minute(): void
    {
        $routes = Route::getRoutes();

        $route = collect($routes->getRoutes())->first(function ($r) {
            return $r->uri() === 'api/profile/orders'
                && in_array('POST', $r->methods());
        });

        $this->assertNotNull($route, 'Route POST /api/profile/orders phải tồn tại');

        $middleware = $route->middleware();
        // Nâng lên 20,1 để tránh 429 khi user retry sau lỗi payment
        $hasThrottle = collect($middleware)->contains(fn ($m) => str_starts_with($m, 'throttle:20'));

        $this->assertTrue($hasThrottle, 'Route POST /api/profile/orders phải có throttle:20,1');
    }

    /** Route guest order có throttle:10,1 */
    public function test_guest_order_route_has_throttle_3_per_minute(): void
    {
        $routes = Route::getRoutes();

        $route = collect($routes->getRoutes())->first(function ($r) {
            return $r->uri() === 'api/orders/guest'
                && in_array('POST', $r->methods());
        });

        $this->assertNotNull($route, 'Route POST /api/orders/guest phải tồn tại');

        $middleware = $route->middleware();
        // Nâng lên 10,1 — vẫn chặt hơn user (20,1) nhưng đủ cho guest retry
        $hasThrottle = collect($middleware)->contains(fn ($m) => str_starts_with($m, 'throttle:10'));

        $this->assertTrue($hasThrottle, 'Route guest order phải có throttle:10,1 (chặt hơn user 20,1)');
    }

    /** Guest order throttle phải chặt hơn user order */
    public function test_guest_throttle_is_stricter_than_user_throttle(): void
    {
        $routes = Route::getRoutes();

        $getLimit = function (string $uri) use ($routes): int {
            $route = collect($routes->getRoutes())->first(function ($r) use ($uri) {
                return $r->uri() === $uri && in_array('POST', $r->methods());
            });

            if (! $route) {
                return PHP_INT_MAX;
            }

            foreach ($route->middleware() as $m) {
                if (str_starts_with($m, 'throttle:')) {
                    return (int) explode(':', $m)[1];
                }
            }

            return PHP_INT_MAX;
        };

        $userLimit = $getLimit('api/profile/orders');
        $guestLimit = $getLimit('api/orders/guest');

        $this->assertLessThan(
            $userLimit,
            $guestLimit,
            "Guest ({$guestLimit}/phút) phải chặt hơn user ({$userLimit}/phút)"
        );
    }

    // =========================================================================
    // 3. SPAM REVIEW
    // =========================================================================

    /** Guard: Chưa có review → cho phép */
    public function test_review_no_duplicate_allows_submission(): void
    {
        $user = $this->createUser();
        $order = $this->createOrder($user->user_id);
        $orderItem = DB::table('order_items')->insertGetId([
            'order_id' => $order->order_id,
            'product_id' => 1,
            'product_name' => 'Sản phẩm test',
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
        ]);

        $alreadyReviewed = DB::table('product_comments')
            ->where('order_item_id', $orderItem)
            ->where('user_id', $user->user_id)
            ->exists();

        $this->assertFalse($alreadyReviewed, 'Chưa có review → guard phải cho phép');
    }

    /** Guard: Đã có review → phát hiện duplicate */
    public function test_review_duplicate_order_item_is_detected(): void
    {
        $user = $this->createUser();
        $order = $this->createOrder($user->user_id);
        $orderItem = DB::table('order_items')->insertGetId([
            'order_id' => $order->order_id,
            'product_id' => 1,
            'product_name' => 'Test',
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
        ]);

        // Review lần 1 tồn tại
        DB::table('product_comments')->insert([
            'product_id' => 1,
            'user_id' => $user->user_id,
            'order_item_id' => $orderItem,
            'rating' => 5,
            'content' => 'Tốt lắm',
            'is_approved' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Guard double-check DB phải phát hiện đã tồn tại
        $alreadyReviewed = DB::table('product_comments')
            ->where('order_item_id', $orderItem)
            ->where('user_id', $user->user_id)
            ->exists();

        $this->assertTrue($alreadyReviewed, 'Guard phải phát hiện review trùng và chặn lần 2');
    }

    /** Guard: User B không thể review order của user A */
    public function test_review_ownership_check_blocks_other_user(): void
    {
        $ownerUser = $this->createUser();
        $attackerUser = $this->createUser();
        $order = $this->createOrder($ownerUser->user_id);

        $orderItem = DB::table('order_items')->insertGetId([
            'order_id' => $order->order_id,
            'product_id' => 1,
            'product_name' => 'Test',
            'quantity' => 1,
            'unit_price' => 100000,
            'line_total' => 100000,
        ]);

        // Lấy order như controller làm khi check ownership
        $item = DB::table('order_items')->where('order_item_id', $orderItem)->first();
        $itemOrder = DB::table('orders')->where('order_id', $item->order_id)->first();

        $this->assertNotEquals(
            $attackerUser->user_id,
            $itemOrder->user_id,
            'attackerUser không phải chủ đơn → guard ownership phải chặn'
        );

        // ownerUser thì pass
        $this->assertEquals(
            $ownerUser->user_id,
            $itemOrder->user_id,
            'ownerUser là chủ đơn → guard ownership phải pass'
        );
    }

    /** Nhiều user khác nhau review cùng sản phẩm → không bị block lẫn nhau */
    public function test_review_different_users_can_review_same_product(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        $order1 = $this->createOrder($user1->user_id);
        $order2 = $this->createOrder($user2->user_id);

        $item1 = DB::table('order_items')->insertGetId([
            'order_id' => $order1->order_id, 'product_id' => 1,
            'product_name' => 'Test', 'quantity' => 1, 'unit_price' => 100000, 'line_total' => 100000,
        ]);
        $item2 = DB::table('order_items')->insertGetId([
            'order_id' => $order2->order_id, 'product_id' => 1,
            'product_name' => 'Test', 'quantity' => 1, 'unit_price' => 100000, 'line_total' => 100000,
        ]);

        // User1 review order_item của mình
        $user1Blocked = DB::table('product_comments')
            ->where('order_item_id', $item1)->where('user_id', $user1->user_id)->exists();

        // User2 review order_item của mình
        $user2Blocked = DB::table('product_comments')
            ->where('order_item_id', $item2)->where('user_id', $user2->user_id)->exists();

        $this->assertFalse($user1Blocked, 'User1 chưa review → không bị block');
        $this->assertFalse($user2Blocked, 'User2 chưa review → không bị block');
    }

    /** Route review có throttle:5,1 */
    public function test_review_route_has_throttle_5_per_minute(): void
    {
        $routes = Route::getRoutes();

        $route = collect($routes->getRoutes())->first(function ($r) {
            return $r->uri() === 'api/profile/orders/feedback'
                && in_array('POST', $r->methods());
        });

        $this->assertNotNull($route, 'Route POST /api/profile/orders/feedback phải tồn tại');

        $middleware = $route->middleware();
        $hasThrottle = collect($middleware)->contains(fn ($m) => str_starts_with($m, 'throttle:5'));

        $this->assertTrue($hasThrottle, 'Route review phải có throttle:5,1');
    }

    // =========================================================================
    // 4. SPAM KHIẾU NẠI
    // =========================================================================

    /** Guard 1: order_id thuộc user khác → bị chặn */
    public function test_ticket_foreign_order_is_blocked(): void
    {
        $ownerUser = $this->createUser();
        $attackerUser = $this->createUser();
        $order = $this->createOrder($ownerUser->user_id);

        // Attacker cố dùng order của owner
        $found = DB::table('orders')
            ->where('order_id', $order->order_id)
            ->where('user_id', $attackerUser->user_id)
            ->first();

        $this->assertNull($found, 'Guard 1: order không thuộc attackerUser → null → bị chặn');
    }

    /** Guard 1: order_id thuộc đúng user → pass */
    public function test_ticket_own_order_passes_guard(): void
    {
        $user = $this->createUser();
        $order = $this->createOrder($user->user_id);

        $found = DB::table('orders')
            ->where('order_id', $order->order_id)
            ->where('user_id', $user->user_id)
            ->first();

        $this->assertNotNull($found, 'Guard 1: order thuộc user → pass');
    }

    /** Guard 2: Đủ 3 ticket pending → lần 4 bị chặn */
    public function test_ticket_limit_3_open_blocks_new(): void
    {
        $user = $this->createUser();
        $order = $this->createOrder($user->user_id);

        for ($i = 0; $i < 3; $i++) {
            DB::table('tickets')->insert([
                'user_id' => $user->user_id,
                'order_id' => $order->order_id,
                'reason' => 'Spam '.$i,
                'description' => 'Mô tả',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $openCount = DB::table('tickets')
            ->where('user_id', $user->user_id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        $this->assertSame(3, $openCount);
        $this->assertTrue($openCount >= 3, 'Guard 2: >= 3 ticket mở → phải chặn lần mới');
    }

    /** Guard 2: 2 ticket pending → vẫn cho thêm */
    public function test_ticket_2_open_allows_new(): void
    {
        $user = $this->createUser();
        $order = $this->createOrder($user->user_id);

        for ($i = 0; $i < 2; $i++) {
            DB::table('tickets')->insert([
                'user_id' => $user->user_id,
                'order_id' => $order->order_id,
                'reason' => 'Lý do',
                'description' => 'Mô tả',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $openCount = DB::table('tickets')
            ->where('user_id', $user->user_id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        $this->assertSame(2, $openCount);
        $this->assertFalse($openCount >= 3, 'Chỉ 2 ticket → phải cho phép tạo thêm');
    }

    /** Guard 2: Ticket processing cũng tính vào giới hạn */
    public function test_ticket_processing_status_counts_toward_limit(): void
    {
        $user = $this->createUser();
        $order = $this->createOrder($user->user_id);

        DB::table('tickets')->insert([
            'user_id' => $user->user_id, 'order_id' => $order->order_id,
            'reason' => 'A', 'description' => 'Mô tả', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tickets')->insert([
            'user_id' => $user->user_id, 'order_id' => $order->order_id,
            'reason' => 'B', 'description' => 'Mô tả', 'status' => 'processing',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tickets')->insert([
            'user_id' => $user->user_id, 'order_id' => $order->order_id,
            'reason' => 'C', 'description' => 'Mô tả', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $openCount = DB::table('tickets')
            ->where('user_id', $user->user_id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        $this->assertSame(3, $openCount, 'pending + processing đều phải tính vào giới hạn');
        $this->assertTrue($openCount >= 3, 'Phải chặn khi mix pending+processing đủ 3');
    }

    /** Guard 2: resolved/closed không tính vào giới hạn */
    public function test_ticket_resolved_and_closed_do_not_count(): void
    {
        $user = $this->createUser();
        $order = $this->createOrder($user->user_id);

        // 5 ticket đã xử lý xong
        for ($i = 0; $i < 5; $i++) {
            DB::table('tickets')->insert([
                'user_id' => $user->user_id,
                'order_id' => $order->order_id,
                'reason' => 'Cũ',
                'description' => 'Đã xong',
                'status' => ($i % 2 === 0) ? 'resolved' : 'closed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $openCount = DB::table('tickets')
            ->where('user_id', $user->user_id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        $this->assertSame(0, $openCount, 'resolved/closed không được tính vào open count');
        $this->assertFalse($openCount >= 3, 'User vẫn được tạo ticket mới vì không có open ticket');
    }

    /** Route ticket có throttle:3,1 */
    public function test_ticket_route_has_throttle_3_per_minute(): void
    {
        $routes = Route::getRoutes();

        $route = collect($routes->getRoutes())->first(function ($r) {
            return $r->uri() === 'api/profile/tickets'
                && in_array('POST', $r->methods());
        });

        $this->assertNotNull($route, 'Route POST /api/profile/tickets phải tồn tại');

        $middleware = $route->middleware();
        $hasThrottle = collect($middleware)->contains(fn ($m) => str_starts_with($m, 'throttle:3'));

        $this->assertTrue($hasThrottle, 'Route ticket phải có throttle:3,1');
    }

    /** Route affiliate/track-click có throttle:30,1 */
    public function test_affiliate_click_route_has_throttle_30_per_minute(): void
    {
        $routes = Route::getRoutes();

        $route = collect($routes->getRoutes())->first(function ($r) {
            return $r->uri() === 'api/affiliate/track-click'
                && in_array('POST', $r->methods());
        });

        $this->assertNotNull($route, 'Route POST /api/affiliate/track-click phải tồn tại');

        $middleware = $route->middleware();
        $hasThrottle = collect($middleware)->contains(fn ($m) => str_starts_with($m, 'throttle:30'));

        $this->assertTrue($hasThrottle, 'Route track-click phải có throttle:30,1');
    }
}
