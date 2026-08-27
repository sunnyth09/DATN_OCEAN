<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Chatbot\ChatbotInfoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChatbotOrderTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach (['order_items', 'orders', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->default('customer');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->string('order_code', 30)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_fee', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('payment_method')->default('cod');
            $table->string('payment_status')->default('pending');
            $table->string('fulfillment_status')->default('shipping');
            $table->string('recipient_name')->default('Nguyễn Văn A');
            $table->string('recipient_phone')->default('0987654321');
            $table->string('shipping_address')->default('123 Đường ABC, Phường Tân Lợi, TP. Buôn Ma Thuột');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id('order_item_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->default(1);
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('product_name')->default('Áo Thể Thao Ocean Sport');
            $table->string('variant_name')->default('Đen / L');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(250000);
            $table->decimal('line_total', 15, 2)->default(250000);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function test_guest_can_lookup_order_by_code_only_with_masked_info(): void
    {
        $order = Order::create([
            'order_code' => 'ORD-2026-TEST01',
            'subtotal' => 250000,
            'grand_total' => 250000,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'fulfillment_status' => 'shipping',
            'recipient_name' => 'Nguyễn Văn An',
            'recipient_phone' => '0987654321',
            'shipping_address' => '123 Đường ABC, Phường Tân Lợi, TP. Buôn Ma Thuột',
        ]);

        OrderItem::create([
            'order_id' => $order->order_id,
            'product_name' => 'Áo Thể Thao Ocean Sport',
            'variant_name' => 'Đen / L',
            'quantity' => 1,
            'unit_price' => 250000,
            'line_total' => 250000,
        ]);

        $service = new ChatbotInfoService;
        $result = $service->getOrderStatus(['order_code' => 'ORD-2026-TEST01']);

        $this->assertEquals('success', $result['status']);
        $this->assertNotEmpty($result['data']);
        $this->assertEquals('ORD-2026-TEST01', $result['data']['order_code']);
        $this->assertEquals('Đang giao hàng', $result['data']['status']);

        // Kiểm tra bảo mật (masking) thông tin khách vãng lai
        $this->assertStringContainsString('****', $result['data']['recipient_phone']);
        $this->assertStringContainsString('***', $result['data']['shipping_address']);
        $this->assertCount(1, $result['data']['items']);
    }

    public function test_guest_lookup_invalid_order_code_returns_not_found(): void
    {
        $service = new ChatbotInfoService;
        $result = $service->getOrderStatus(['order_code' => 'ORD-INVALID-999999']);

        $this->assertEquals('not_found', $result['status']);
        $this->assertNull($result['data']);
    }
}
