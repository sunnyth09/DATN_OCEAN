<?php

namespace Tests\Feature;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    private WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WalletService();

        foreach (['wallet_withdrawals', 'wallet_transactions', 'wallets', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id('wallet_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('deposit_balance', 15, 2)->default(0);
            $table->decimal('commission_balance', 15, 2)->default(0);
            $table->decimal('frozen_balance', 15, 2)->default(0);
            $table->decimal('total_deposited', 15, 2)->default(0);
            $table->decimal('total_commission', 15, 2)->default(0);
            $table->decimal('total_used', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->string('pin_hash')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id('transaction_id');
            $table->unsignedBigInteger('wallet_id');
            $table->string('transaction_code', 30)->unique();
            $table->string('type');
            $table->string('balance_type');
            $table->string('direction');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference_type', 191)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('status')->default('completed');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('wallet_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('withdrawal_code', 30)->unique();
            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('total_deducted', 15, 2);
            $table->string('bank_name', 100);
            $table->string('bank_account_name', 255);
            $table->string('bank_account_number', 50);
            $table->string('status')->default('processing');
            $table->text('note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    private function makeUser(int $id = 1): int
    {
        DB::table('users')->insert([
            'user_id'    => $id,
            'full_name'  => 'Wallet Tester',
            'email'      => "wallet{$id}@example.com",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function bankInfo(): array
    {
        return [
            'bank_name'           => 'Vietcombank',
            'bank_account_name'   => 'NGUYEN VAN A',
            'bank_account_number' => '0123456789',
        ];
    }

    public function test_credit_deposit_increases_deposit_balance_and_totals(): void
    {
        $userId = $this->makeUser();

        $tx = $this->service->credit($userId, 100000, 'deposit');

        $wallet = Wallet::where('user_id', $userId)->first();
        $this->assertSame('100000.00', $wallet->deposit_balance);
        $this->assertSame('100000.00', $wallet->total_deposited);
        $this->assertSame('0.00', $wallet->commission_balance);
        $this->assertSame('credit', $tx->direction);
        $this->assertSame('deposit', $tx->balance_type);
        $this->assertSame('0.00', $tx->balance_before);
        $this->assertSame('100000.00', $tx->balance_after);
    }

    public function test_credit_commission_goes_to_commission_balance(): void
    {
        $userId = $this->makeUser();

        $this->service->credit($userId, 50000, 'commission');

        $wallet = Wallet::where('user_id', $userId)->first();
        $this->assertSame('50000.00', $wallet->commission_balance);
        $this->assertSame('50000.00', $wallet->total_commission);
        $this->assertSame('0.00', $wallet->deposit_balance);
    }

    public function test_credit_rejects_non_positive_amount(): void
    {
        $userId = $this->makeUser();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->credit($userId, 0, 'deposit');
    }

    public function test_apply_order_discount_uses_deposit_first(): void
    {
        $userId = $this->makeUser();
        $this->service->credit($userId, 80000, 'deposit');
        $this->service->credit($userId, 100000, 'commission'); // max usable = 10% = 10000

        $result = $this->service->applyOrderDiscount($userId, 85000, 1);

        // deposit 80000 dùng hết, còn 5000 lấy từ commission (trong hạn mức 10000)
        $this->assertEqualsWithDelta(80000, $result['deposit_used'], 0.001);
        $this->assertEqualsWithDelta(5000, $result['commission_used'], 0.001);
        $this->assertEqualsWithDelta(85000, $result['total_discount'], 0.001);

        $wallet = Wallet::where('user_id', $userId)->first();
        $this->assertSame('0.00', $wallet->deposit_balance);
        $this->assertSame('95000.00', $wallet->commission_balance);
        $this->assertSame('85000.00', $wallet->total_used);
    }

    public function test_apply_order_discount_caps_commission_at_ten_percent(): void
    {
        $userId = $this->makeUser();
        // Không có deposit, chỉ có commission 100000 → chỉ dùng được 10000
        $this->service->credit($userId, 100000, 'commission');

        $result = $this->service->applyOrderDiscount($userId, 100000, 1);

        $this->assertEqualsWithDelta(0, $result['deposit_used'], 0.001);
        $this->assertEqualsWithDelta(10000, $result['commission_used'], 0.001);
        $this->assertEqualsWithDelta(10000, $result['total_discount'], 0.001);

        $wallet = Wallet::where('user_id', $userId)->first();
        $this->assertSame('90000.00', $wallet->commission_balance);
    }

    public function test_apply_order_discount_throws_when_no_balance(): void
    {
        $userId = $this->makeUser();
        $this->service->getOrCreateWallet($userId);

        $this->expectException(\Exception::class);
        $this->service->applyOrderDiscount($userId, 50000, 1);
    }

    public function test_withdraw_deducts_amount_plus_fee(): void
    {
        $userId = $this->makeUser();
        $this->service->credit($userId, 100000, 'deposit');

        $result = $this->service->withdraw($userId, 50000, $this->bankInfo());

        $this->assertEqualsWithDelta(50000, $result['amount'], 0.001);
        $this->assertEqualsWithDelta(1000, $result['fee'], 0.001);
        $this->assertEqualsWithDelta(51000, $result['total_deducted'], 0.001);

        $wallet = Wallet::where('user_id', $userId)->first();
        $this->assertSame('49000.00', $wallet->deposit_balance);

        $this->assertDatabaseHas('wallet_withdrawals', [
            'withdrawal_code' => $result['withdrawal_code'],
            'status'          => 'processing',
        ]);
    }

    public function test_withdraw_rejects_below_minimum(): void
    {
        $userId = $this->makeUser();
        $this->service->credit($userId, 100000, 'deposit');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->withdraw($userId, 5000, $this->bankInfo());
    }

    public function test_withdraw_rejects_when_balance_insufficient_for_fee(): void
    {
        $userId = $this->makeUser();
        // Đủ amount nhưng không đủ để cộng thêm phí
        $this->service->credit($userId, 50000, 'deposit');

        $this->expectException(\Exception::class);
        $this->service->withdraw($userId, 50000, $this->bankInfo());
    }

    public function test_reverse_order_discount_refunds_both_balance_types(): void
    {
        $userId = $this->makeUser();
        $this->service->credit($userId, 80000, 'deposit');
        $this->service->credit($userId, 100000, 'commission');
        $this->service->applyOrderDiscount($userId, 85000, 7); // deposit 80000 + commission 5000

        $this->service->reverseOrderDiscount($userId, 80000, 5000, 7);

        $wallet = Wallet::where('user_id', $userId)->first();
        $this->assertSame('80000.00', $wallet->deposit_balance);
        $this->assertSame('100000.00', $wallet->commission_balance);

        // LƯU Ý (hành vi bất đối xứng đã biết của reverseOrderDiscount):
        // - Phần commission được hoàn thủ công có trừ total_used (-= 5000).
        // - Phần deposit hoàn qua credit('refund') KHÔNG trừ total_used (còn cộng total_deposited).
        // Nên total_used = 85000 - 5000 = 80000, không về 0. Test khẳng định hành vi thực tại.
        $this->assertSame('80000.00', $wallet->total_used);
    }

    public function test_admin_adjust_cannot_make_balance_negative(): void
    {
        $userId = $this->makeUser();
        $this->service->credit($userId, 10000, 'deposit');

        $this->expectException(\Exception::class);
        $this->service->adminAdjust($userId, -20000, 'Trừ quá tay', 1);
    }

    public function test_admin_adjust_positive_updates_balance_and_total_deposited(): void
    {
        $userId = $this->makeUser();
        $this->service->credit($userId, 10000, 'deposit');

        $tx = $this->service->adminAdjust($userId, 5000, 'Bù khuyến mãi', 99);

        $wallet = Wallet::where('user_id', $userId)->first();
        $this->assertSame('15000.00', $wallet->deposit_balance);
        $this->assertSame('15000.00', $wallet->total_deposited);
        $this->assertSame('credit', $tx->direction);
        $this->assertSame(99, $tx->metadata['admin_id']);
    }
}
