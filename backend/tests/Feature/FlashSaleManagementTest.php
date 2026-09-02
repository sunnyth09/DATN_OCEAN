<?php

namespace Tests\Feature;

use App\Models\FlashSale;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FlashSaleManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('flash_sale_items');
        Schema::dropIfExists('flash_sales');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('full_name', 120);
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->default('admin');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id('category_id');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id('product_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('min_price', 15, 2)->default(100000);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id('variant_id');
            $table->unsignedBigInteger('product_id');
            $table->string('sku')->nullable();
            $table->decimal('price', 15, 2)->default(100000);
            $table->integer('stock')->default(50);
            $table->timestamps();
        });

        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Flash Sale');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->string('status', 30)->default('draft');
            $table->boolean('is_combo')->default(false);
            $table->string('combo_label')->nullable();
            $table->timestamps();
        });

        Schema::create('flash_sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('flash_sale_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('campaign_price', 15, 2);
            $table->unsignedInteger('campaign_stock');
            $table->unsignedInteger('sold')->default(0);
            $table->timestamps();
        });
    }

    public function test_can_create_flash_sale_with_upcoming_status(): void
    {
        $admin = new User([
            'full_name' => 'Admin User',
            'email' => 'admin@oceansport.com',
        ]);
        $admin->forceFill(['role' => 'admin'])->save();

        $product = Product::create([
            'name' => 'Giày thể thao Pro',
            'min_price' => 200000,
            'status' => 'active',
        ]);

        $product->variants()->create([
            'sku' => 'GTT-01',
            'price' => 200000,
            'stock' => 50,
        ]);

        $payload = [
            'name' => 'Flash Sale Cuối Tuần',
            'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'status' => 'upcoming',
            'items' => [
                [
                    'product_id' => $product->product_id,
                    'campaign_price' => 150000,
                    'campaign_stock' => 20,
                ],
            ],
        ];

        $token = auth('api')->login($admin);

        $res = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admin/flash-sale', $payload);

        $res->assertOk();
        $res->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('flash_sales', [
            'name' => 'Flash Sale Cuối Tuần',
            'status' => 'upcoming',
        ]);
    }

    public function test_can_update_flash_sale_to_upcoming_status(): void
    {
        $admin = new User([
            'full_name' => 'Admin User',
            'email' => 'admin@oceansport.com',
        ]);
        $admin->forceFill(['role' => 'admin'])->save();

        $product = Product::create([
            'name' => 'Vợt Cầu Lông Carbon',
            'min_price' => 500000,
            'status' => 'active',
        ]);

        $product->variants()->create([
            'sku' => 'VCL-01',
            'price' => 500000,
            'stock' => 30,
        ]);

        $fs = FlashSale::create([
            'name' => 'Flash Sale Giữa Tuần',
            'start_time' => now()->addDays(1),
            'end_time' => now()->addDays(2),
            'status' => 'draft',
        ]);

        $fs->items()->create([
            'product_id' => $product->product_id,
            'campaign_price' => 350000,
            'campaign_stock' => 10,
            'sold' => 0,
        ]);

        $updatePayload = [
            'name' => 'Flash Sale Giữa Tuần Cập Nhật',
            'start_time' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'status' => 'upcoming',
            'items' => [
                [
                    'product_id' => $product->product_id,
                    'campaign_price' => 320000,
                    'campaign_stock' => 15,
                ],
            ],
        ];

        $token = auth('api')->login($admin);

        $res = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/admin/flash-sale/{$fs->id}", $updatePayload);

        $res->assertOk();
        $res->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('flash_sales', [
            'id' => $fs->id,
            'name' => 'Flash Sale Giữa Tuần Cập Nhật',
            'status' => 'upcoming',
        ]);
    }

    public function test_can_update_flash_sale_to_active_and_ended(): void
    {
        $admin = new User([
            'full_name' => 'Admin User',
            'email' => 'admin@oceansport.com',
        ]);
        $admin->forceFill(['role' => 'admin'])->save();

        $product = Product::create([
            'name' => 'Áo thể thao',
            'min_price' => 150000,
            'status' => 'active',
        ]);

        $product->variants()->create([
            'sku' => 'ATT-01',
            'price' => 150000,
            'stock' => 20,
        ]);

        $fs = FlashSale::create([
            'name' => 'Flash Sale Test Active',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
            'status' => 'draft',
        ]);

        $fs->items()->create([
            'product_id' => $product->product_id,
            'campaign_price' => 100000,
            'campaign_stock' => 10,
            'sold' => 0,
        ]);

        $token = auth('api')->login($admin);

        // Update sang active
        $res = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/admin/flash-sale/{$fs->id}", [
                'name' => 'Flash Sale Test Active',
                'start_time' => now()->subHour()->format('Y-m-d H:i:s'),
                'end_time' => now()->addHours(2)->format('Y-m-d H:i:s'),
                'status' => 'active',
                'items' => [
                    [
                        'product_id' => $product->product_id,
                        'campaign_price' => 100000,
                        'campaign_stock' => 10,
                    ],
                ],
            ]);

        $res->assertOk();
        $this->assertDatabaseHas('flash_sales', [
            'id' => $fs->id,
            'status' => 'active',
        ]);

        // Update sang ended
        $res2 = $this->withHeader('Authorization', "Bearer $token")
            ->putJson("/api/admin/flash-sale/{$fs->id}", [
                'name' => 'Flash Sale Test Active',
                'start_time' => now()->subHours(3)->format('Y-m-d H:i:s'),
                'end_time' => now()->subHour()->format('Y-m-d H:i:s'),
                'status' => 'ended',
                'items' => [
                    [
                        'product_id' => $product->product_id,
                        'campaign_price' => 100000,
                        'campaign_stock' => 10,
                    ],
                ],
            ]);

        $res2->assertOk();
        $this->assertDatabaseHas('flash_sales', [
            'id' => $fs->id,
            'status' => 'ended',
        ]);
    }
}
