<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtBooking;
use App\Models\OpenPlay;
use App\Models\OpenPlayParticipant;
use App\Models\OpenPlayWaitlist;
use App\Models\User;
use App\Services\OpenPlayService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OpenPlayWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => 'test-secret-key-for-open-play-testing']);
        $this->createTestSchema();
    }

    protected function createTestSchema(): void
    {
        Schema::dropIfExists('phone_otp_verifications');
        Schema::dropIfExists('open_play_waitlists');
        Schema::dropIfExists('open_play_participants');
        Schema::dropIfExists('open_plays');
        Schema::dropIfExists('court_bookings');
        Schema::dropIfExists('courts');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id('user_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('courts', function ($table) {
            $table->id('court_id');
            $table->string('court_name');
            $table->string('court_type')->default('standard');
            $table->string('surface_type')->default('wood');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('court_bookings', function ($table) {
            $table->id('booking_id');
            $table->string('booking_code')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('court_id');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status')->default('confirmed');
            $table->string('payment_status')->default('paid');
            $table->string('payment_method')->default('wallet');
            $table->integer('total_amount')->default(200000);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('open_plays', function ($table) {
            $table->id();
            $table->string('open_play_code', 32)->unique();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('host_user_id');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('sport_type', 50)->default('badminton');
            $table->string('skill_level', 50)->default('all_levels');
            $table->string('gender_rule', 50)->default('any');
            $table->string('match_type', 50)->default('doubles');
            $table->unsignedTinyInteger('max_players')->default(4);
            $table->unsignedTinyInteger('current_players')->default(1);
            $table->string('join_mode', 50)->default('auto');
            $table->string('payment_mode', 50)->default('host_pays');
            $table->unsignedInteger('slot_price')->default(0);
            $table->string('status', 50)->default('open');
            $table->text('rules')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('open_play_participants', function ($table) {
            $table->id();
            $table->unsignedBigInteger('open_play_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('guest_name', 100)->nullable();
            $table->string('guest_phone', 20)->nullable();
            $table->string('role', 50)->default('participant');
            $table->string('status', 50)->default('confirmed');
            $table->string('payment_status', 50)->default('free');
            $table->unsignedInteger('payment_amount')->default(0);
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_transaction_code', 100)->nullable();
            $table->dateTime('joined_at')->useCurrent();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->string('check_in_token', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('open_play_waitlists', function ($table) {
            $table->id();
            $table->unsignedBigInteger('open_play_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('status', 50)->default('waiting');
            $table->dateTime('promoted_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('phone_otp_verifications', function ($table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('otp', 255);
            $table->dateTime('expires_at');
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
        });
    }

    protected function createUser(string $email = 'user@example.com', string $name = 'Test User', string $phone = '0901234567'): User
    {
        return User::create([
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('password123'),
        ]);
    }

    protected function createCourt(): Court
    {
        return Court::create([
            'court_name' => 'Sân Cầu Lông Số 1',
            'court_type' => 'standard',
            'surface_type' => 'wood',
            'status' => 'active',
        ]);
    }

    protected function createBooking(int $userId, int $courtId): CourtBooking
    {
        return CourtBooking::create([
            'booking_code' => 'CB-TEST-'.uniqid(),
            'user_id' => $userId,
            'court_id' => $courtId,
            'booking_date' => now()->addDays(2)->toDateString(),
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'total_amount' => 200000,
        ]);
    }

    public function test_host_can_create_open_play_from_booking(): void
    {
        $host = $this->createUser('host@example.com', 'Host User');
        $court = $this->createCourt();
        $booking = $this->createBooking($host->user_id, $court->court_id);

        $service = app(OpenPlayService::class);
        $openPlay = $service->createOpenPlay([
            'booking_id' => $booking->booking_id,
            'title' => 'Giao lưu cầu lông tối thứ 6',
            'skill_level' => 'intermediate',
            'gender_rule' => 'mixed',
            'match_type' => 'doubles',
            'max_players' => 4,
            'join_mode' => 'auto',
            'payment_mode' => 'split_payment',
        ], $host->user_id);

        $this->assertDatabaseHas('open_plays', [
            'id' => $openPlay->id,
            'host_user_id' => $host->user_id,
            'title' => 'Giao lưu cầu lông tối thứ 6',
            'current_players' => 1,
            'max_players' => 4,
            'slot_price' => 50000, // 200,000 / 4
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('open_play_participants', [
            'open_play_id' => $openPlay->id,
            'user_id' => $host->user_id,
            'role' => 'host',
            'status' => 'confirmed',
        ]);
    }

    public function test_user_can_join_open_play_with_auto_mode(): void
    {
        $host = $this->createUser('host2@example.com', 'Host User');
        $player = $this->createUser('player1@example.com', 'Player One');
        $court = $this->createCourt();
        $booking = $this->createBooking($host->user_id, $court->court_id);

        $service = app(OpenPlayService::class);
        $openPlay = $service->createOpenPlay([
            'booking_id' => $booking->booking_id,
            'title' => 'Trận đấu mở',
            'max_players' => 4,
            'join_mode' => 'auto',
            'payment_mode' => 'host_pays',
        ], $host->user_id);

        $participant = $service->joinOpenPlay($openPlay->id, $player->user_id);

        $this->assertEquals('confirmed', $participant->status);
        $this->assertDatabaseHas('open_plays', [
            'id' => $openPlay->id,
            'current_players' => 2,
            'status' => 'open',
        ]);
    }

    public function test_concurrency_capacity_limit_blocks_overflow(): void
    {
        $host = $this->createUser('host3@example.com', 'Host User');
        $p1 = $this->createUser('p1@example.com', 'Player 1');
        $p2 = $this->createUser('p2@example.com', 'Player 2');
        $court = $this->createCourt();
        $booking = $this->createBooking($host->user_id, $court->court_id);

        $service = app(OpenPlayService::class);
        // max_players = 2: 1 Host + 1 Participant
        $openPlay = $service->createOpenPlay([
            'booking_id' => $booking->booking_id,
            'title' => 'Kèo 2 người',
            'max_players' => 2,
            'join_mode' => 'auto',
            'payment_mode' => 'host_pays',
        ], $host->user_id);

        // Player 1 joins -> full
        $service->joinOpenPlay($openPlay->id, $p1->user_id);

        $fresh = OpenPlay::find($openPlay->id);
        $this->assertEquals(2, $fresh->current_players);
        $this->assertEquals('full', $fresh->status);

        // Player 2 attempts to join -> exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OPEN_PLAY_FULL');
        $service->joinOpenPlay($openPlay->id, $p2->user_id);
    }

    public function test_participant_leave_triggers_waitlist_fifo_promotion(): void
    {
        $host = $this->createUser('host4@example.com', 'Host User');
        $p1 = $this->createUser('p1_leave@example.com', 'Player 1');
        $p2 = $this->createUser('p2_wait@example.com', 'Player 2 (Waitlist)');
        $court = $this->createCourt();
        $booking = $this->createBooking($host->user_id, $court->court_id);

        $service = app(OpenPlayService::class);
        $openPlay = $service->createOpenPlay([
            'booking_id' => $booking->booking_id,
            'title' => 'Kèo kiểm tra waitlist',
            'max_players' => 2,
            'join_mode' => 'auto',
            'payment_mode' => 'host_pays',
        ], $host->user_id);

        // p1 joins
        $service->joinOpenPlay($openPlay->id, $p1->user_id);

        // p2 joins waitlist
        $waitlist = $service->joinWaitlist($openPlay->id, $p2->user_id);
        $this->assertEquals(1, $waitlist->position);
        $this->assertEquals('waiting', $waitlist->status);

        // p1 leaves -> p2 should be automatically promoted
        $result = $service->leaveOpenPlay($openPlay->id, $p1->user_id);

        $this->assertEquals('Player 2 (Waitlist)', $result['promoted_user']);
        $this->assertDatabaseHas('open_play_participants', [
            'open_play_id' => $openPlay->id,
            'user_id' => $p2->user_id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('open_play_waitlists', [
            'id' => $waitlist->id,
            'status' => 'promoted',
        ]);
    }

    public function test_guest_otp_verification_flow(): void
    {
        $service = app(OpenPlayService::class);
        $phone = '0987654321';

        $sendRes = $service->sendGuestOtp($phone);
        $this->assertEquals('success', $sendRes['status']);

        $record = \App\Models\PhoneOtpVerification::where('phone', $phone)->first();
        $this->assertNotNull($record);

        // Verify with incorrect OTP -> fails
        $this->expectException(\Exception::class);
        $service->verifyGuestOtp($phone, '000000');
    }

    public function test_guest_otp_verification_success_creates_user(): void
    {
        $service = app(OpenPlayService::class);
        $phone = '0912345678';

        $sendRes = $service->sendGuestOtp($phone);
        $otp = $sendRes['dev_otp'];

        $verifyRes = $service->verifyGuestOtp($phone, $otp, 'Khách Vãng Lai Test');

        $this->assertEquals('success', $verifyRes['status']);
        $this->assertNotEmpty($verifyRes['token']);
        $this->assertEquals('Khách Vãng Lai Test', $verifyRes['user']['full_name']);
        $this->assertDatabaseHas('users', ['phone' => $phone]);
    }
}
