<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\User;
use App\Services\CourtBookingService;
use App\Services\CourtBookingWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CourtBookingWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test-secret']);
        $this->createBookingSchema();
    }

    public function test_lock_token_must_match_same_slot(): void
    {
        $user = $this->user();
        $court = $this->court();
        auth()->guard('api')->setUser($user);

        $service = app(CourtBookingService::class);
        $lock = $service->lockSlot($this->payload($court->court_id, '19:00', '20:00'));

        $this->expectException(\Exception::class);

        $service->createBooking(array_merge(
            $this->payload($court->court_id, '19:00', '21:00'),
            ['payment_method' => 'cash', 'lock_token' => $lock->lock_token]
        ));
    }

    public function test_double_booking_is_blocked(): void
    {
        $court = $this->court();
        $firstUser = $this->user('first@example.com');
        $secondUser = $this->user('second@example.com');
        $service = app(CourtBookingService::class);

        auth()->guard('api')->setUser($firstUser);
        $lock = $service->lockSlot($this->payload($court->court_id, '19:00', '20:00'));
        $service->createBooking(array_merge(
            $this->payload($court->court_id, '19:00', '20:00'),
            ['payment_method' => 'cash', 'lock_token' => $lock->lock_token]
        ));

        auth()->guard('api')->setUser($secondUser);

        $this->expectException(\Exception::class);
        $service->lockSlot($this->payload($court->court_id, '19:00', '20:00'));
    }

    public function test_cancel_writes_status_history(): void
    {
        $court = $this->court();
        $user = $this->user();
        auth()->guard('api')->setUser($user);

        $service = app(CourtBookingService::class);
        $lock = $service->lockSlot($this->payload($court->court_id, '19:00', '20:00'));
        $booking = $service->createBooking(array_merge(
            $this->payload($court->court_id, '19:00', '20:00'),
            ['payment_method' => 'cash', 'lock_token' => $lock->lock_token]
        ));

        app(CourtBookingWorkflowService::class)->cancelByUser($booking, 'Test cancel', Request::create('/'));

        $this->assertDatabaseHas('court_booking_status_histories', [
            'booking_id' => $booking->booking_id,
            'new_status' => 'cancelled',
        ]);
    }

    public function test_expired_lock_does_not_block_slot(): void
    {
        $court = $this->court();
        $firstUser = $this->user('expired-first@example.com');
        $secondUser = $this->user('expired-second@example.com');
        $service = app(CourtBookingService::class);

        auth()->guard('api')->setUser($firstUser);
        $service->lockSlot($this->payload($court->court_id, '19:00', '20:00'));
        DB::table('court_booking_locks')->update(['expires_at' => now()->subMinute()]);

        auth()->guard('api')->setUser($secondUser);
        $lock = $service->lockSlot($this->payload($court->court_id, '19:00', '20:00'));

        $this->assertNotNull($lock->lock_token);
    }

    private function payload(int $courtId, string $start, string $end): array
    {
        return [
            'court_id' => $courtId,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
        ];
    }

    private function user(string $email = 'customer@example.com'): User
    {
        return User::create([
            'full_name' => 'Customer',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => 'customer',
            'status' => 'active',
        ]);
    }

    private function court(): Court
    {
        $court = Court::create([
            'court_name' => 'Court 1',
            'court_code' => 'COURT-TEST',
            'type' => 'standard',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        DB::table('court_prices')->insert([
            'court_id' => $court->court_id,
            'price_name' => 'Default',
            'day_type' => 'all',
            'from_time' => '06:00:00',
            'to_time' => '23:00:00',
            'price_per_hour' => 100000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $court;
    }

    private function createBookingSchema(): void
    {
        foreach ([
            'court_booking_payments',
            'court_activity_logs',
            'court_booking_services',
            'court_booking_status_histories',
            'court_booking_locks',
            'court_maintenances',
            'court_prices',
            'court_bookings',
            'court_services',
            'courts',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function ($table) {
            $table->id('user_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('role')->default('customer');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('courts', function ($table) {
            $table->id('court_id');
            $table->string('court_name');
            $table->string('court_code')->unique();
            $table->string('type')->default('standard');
            $table->text('description')->nullable();
            $table->string('surface')->nullable();
            $table->integer('max_players')->nullable();
            $table->string('status')->default('active');
            $table->string('image_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('court_prices', function ($table) {
            $table->id('price_id');
            $table->unsignedBigInteger('court_id');
            $table->string('price_name')->nullable();
            $table->string('day_type')->default('all');
            $table->time('from_time');
            $table->time('to_time');
            $table->decimal('price_per_hour', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('court_bookings', function ($table) {
            $table->id('booking_id');
            $table->string('booking_code')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->unsignedBigInteger('court_id');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('status')->default('pending');
            $table->integer('original_price');
            $table->integer('discount_amount')->default(0);
            $table->integer('service_amount')->default(0);
            $table->integer('total_amount');
            $table->integer('deposit_amount')->default(0);
            $table->integer('paid_amount')->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('checked_out_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason_type')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('note')->nullable();
            $table->string('source')->default('web');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('court_booking_locks', function ($table) {
            $table->id('lock_id');
            $table->unsignedBigInteger('court_id');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('lock_token')->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('court_booking_status_histories', function ($table) {
            $table->id('history_id');
            $table->unsignedBigInteger('booking_id');
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->string('note')->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('court_maintenances', function ($table) {
            $table->id('maintenance_id');
            $table->unsignedBigInteger('court_id');
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->string('status')->default('scheduled');
            $table->timestamps();
        });

        Schema::create('court_services', function ($table) {
            $table->id('service_id');
            $table->string('service_name');
            $table->string('service_code')->nullable();
            $table->string('unit')->nullable();
            $table->integer('unit_price');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('court_booking_services', function ($table) {
            $table->id('booking_service_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('service_id');
            $table->integer('quantity');
            $table->integer('unit_price');
            $table->integer('subtotal');
            $table->string('note')->nullable();
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();
        });

        Schema::create('court_activity_logs', function ($table) {
            $table->id('log_id');
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('court_booking_payments', function ($table) {
            $table->id('court_payment_id');
            $table->unsignedBigInteger('booking_id');
            $table->string('payment_type');
            $table->string('payment_method');
            $table->string('transaction_code')->nullable();
            $table->integer('amount');
            $table->string('status')->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->json('gateway_response')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
        });
    }
}
