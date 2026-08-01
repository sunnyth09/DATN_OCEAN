<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentProcessingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentProcessingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['vnpay.hash_secret' => 'payment-test-secret']);

        Schema::disableForeignKeyConstraints();
        foreach (['payments', 'order_items', 'orders', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->default('customer');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->string('order_code', 30)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('recipient_name', 120)->default('Test User');
            $table->string('payment_method')->default('vnpay');
            $table->string('payment_status')->default('unpaid');
            $table->string('fulfillment_status')->default('pending');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->boolean('email_sent')->default(false);
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id('order_item_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('product_name')->nullable();
            $table->string('variant_name')->nullable();
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->unsignedBigInteger('order_id');
            $table->string('payment_method');
            $table->string('transaction_code')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->string('confirmed_source', 20)->nullable();
            $table->json('gateway_response')->nullable();
            $table->string('post_payment_key', 190)->nullable();
            $table->string('post_payment_status', 20)->nullable();
            $table->dateTime('post_payment_started_at')->nullable();
            $table->dateTime('post_payment_processed_at')->nullable();
            $table->string('post_payment_source', 20)->nullable();
            $table->text('post_payment_last_error')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'payment_method']);
        });
    }

    public function test_return_success_waits_for_ipn_confirmation(): void
    {
        $order = $this->createOrder();
        $service = $this->fakeService();

        $response = $service->handleVnpayReturn(
            $this->signedVnpayPayload($order->order_code, 125000, '00', 'TXN-RETURN-1'),
            '127.0.0.1'
        );

        $payment = Payment::where('order_id', $order->order_id)->where('payment_method', 'vnpay')->first();
        $order->refresh();

        $this->assertSame('processing', $response['status']);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNull($payment->confirmed_at);
        $this->assertNull($payment->post_payment_status);
        $this->assertSame(0, $service->dispatchAttempts);
    }

    public function test_ipn_success_processes_side_effects_only_once(): void
    {
        $order = $this->createOrder('ORD-IPN-ONCE');
        $service = $this->fakeService();
        $payload = $this->signedVnpayPayload($order->order_code, 125000, '00', 'TXN-IPN-1');

        $first = $service->handleVnpayIpn($payload, '127.0.0.1');
        $second = $service->handleVnpayIpn($payload, '127.0.0.1');

        $payment = Payment::where('order_id', $order->order_id)->where('payment_method', 'vnpay')->first();
        $order->refresh();

        $this->assertSame('00', $first['RspCode']);
        $this->assertSame('00', $second['RspCode']);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($payment->confirmed_at);
        $this->assertSame('completed', $payment->post_payment_status);
        $this->assertSame(1, $service->dispatchAttempts);
    }

    public function test_failed_return_after_confirmation_does_not_downgrade_payment(): void
    {
        $order = $this->createOrder('ORD-NO-DOWNGRADE');
        $service = $this->fakeService();

        $service->handleVnpayIpn(
            $this->signedVnpayPayload($order->order_code, 125000, '00', 'TXN-CONFIRMED-1'),
            '127.0.0.1'
        );

        $response = $service->handleVnpayReturn(
            $this->signedVnpayPayload($order->order_code, 125000, '24', 'TXN-CONFIRMED-1'),
            '127.0.0.1'
        );

        $payment = Payment::where('order_id', $order->order_id)->where('payment_method', 'vnpay')->first();
        $order->refresh();

        $this->assertSame('success', $response['status']);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('success', $payment->status);
        $this->assertSame(1, $service->dispatchAttempts);
    }

    public function test_failed_post_payment_can_retry_on_next_ipn(): void
    {
        $order = $this->createOrder('ORD-RETRY-IPN');
        $service = $this->fakeService(true);
        $payload = $this->signedVnpayPayload($order->order_code, 125000, '00', 'TXN-RETRY-1');

        $failedAttempt = $service->handleVnpayIpn($payload, '127.0.0.1');
        $failedPayment = Payment::where('order_id', $order->order_id)->where('payment_method', 'vnpay')->first();

        $this->assertSame('99', $failedAttempt['RspCode']);
        $this->assertSame('failed', $failedPayment->post_payment_status);
        $this->assertSame(1, $service->dispatchAttempts);

        $service->shouldFailDispatch = false;

        $successAttempt = $service->handleVnpayIpn($payload, '127.0.0.1');
        $retriedPayment = Payment::where('order_id', $order->order_id)->where('payment_method', 'vnpay')->first();

        $this->assertSame('00', $successAttempt['RspCode']);
        $this->assertSame('completed', $retriedPayment->post_payment_status);
        $this->assertSame(2, $service->dispatchAttempts);
    }

    private function createOrder(string $orderCode = 'ORD-PAYMENT-TEST'): Order
    {
        DB::table('users')->insert([
            'user_id' => DB::table('users')->count() + 1,
            'full_name' => 'Payment Tester',
            'email' => strtolower($orderCode).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::create([
            'order_code' => $orderCode,
            'user_id' => DB::table('users')->latest('user_id')->value('user_id'),
            'recipient_name' => 'Payment Tester',
            'payment_method' => 'vnpay',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'pending',
            'subtotal' => 120000,
            'discount_amount' => 0,
            'shipping_fee' => 5000,
            'grand_total' => 125000,
            'email_sent' => false,
        ]);
    }

    private function signedVnpayPayload(string $orderCode, int $amount, string $responseCode, string $transactionNo): array
    {
        $payload = [
            'vnp_Amount' => $amount * 100,
            'vnp_BankCode' => 'NCB',
            'vnp_OrderInfo' => 'Thanh toan don hang '.$orderCode,
            'vnp_PayDate' => '20260605123045',
            'vnp_ResponseCode' => $responseCode,
            'vnp_TmnCode' => 'TESTTMN',
            'vnp_TransactionNo' => $transactionNo,
            'vnp_TxnRef' => $orderCode,
        ];

        $signable = $payload;
        ksort($signable);

        $hashData = '';
        $index = 0;
        foreach ($signable as $key => $value) {
            $segment = urlencode($key).'='.urlencode((string) $value);
            $hashData .= $index === 0 ? $segment : '&'.$segment;
            $index++;
        }

        $payload['vnp_SecureHash'] = hash_hmac('sha512', $hashData, config('vnpay.hash_secret'));

        return $payload;
    }

    private function fakeService(bool $shouldFailDispatch = false): FakePaymentProcessingService
    {
        return new FakePaymentProcessingService($shouldFailDispatch);
    }
}

class FakePaymentProcessingService extends PaymentProcessingService
{
    public int $dispatchAttempts = 0;

    public function __construct(public bool $shouldFailDispatch = false)
    {
        parent::__construct();
    }

    public function dispatchPostPaymentActions(Order $order): void
    {
        $this->dispatchAttempts++;

        if ($this->shouldFailDispatch) {
            throw new \RuntimeException('Simulated post-payment failure');
        }
    }
}
