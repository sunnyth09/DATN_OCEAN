<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Court;
use App\Models\CourtBooking;
use App\Models\OpenPlay;
use App\Models\OpenPlayParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class OpenPlayFlowEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected User $host;
    protected User $player1;
    protected User $player2;
    protected Court $court;
    protected CourtBooking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tạo Users
        $this->host = User::factory()->create([
            'email' => 'host_' . uniqid() . '@example.com',
            'full_name' => 'Host Player',
            'phone' => '0987' . random_int(100000, 999999),
        ]);

        $this->player1 = User::factory()->create([
            'email' => 'player1_' . uniqid() . '@example.com',
            'full_name' => 'Player One',
            'phone' => '0912' . random_int(100000, 999999),
        ]);

        $this->player2 = User::factory()->create([
            'email' => 'player2_' . uniqid() . '@example.com',
            'full_name' => 'Player Two',
            'phone' => '0934' . random_int(100000, 999999),
        ]);

        // 2. Tìm hoặc tạo Court
        $this->court = Court::first() ?? Court::create([
            'court_name' => 'Sân Cầu Lông Số 1',
            'court_code' => 'COURT_TEST_01',
            'type' => 'standard',
            'status' => 'active',
        ]);

        // 3. Tạo Booking cho Host
        $this->booking = CourtBooking::create([
            'booking_code' => 'BK' . strtoupper(uniqid()),
            'user_id' => $this->host->user_id,
            'court_id' => $this->court->court_id ?? $this->court->id,
            'booking_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
            'duration_minutes' => 120,
            'original_price' => 200000,
            'total_amount' => 200000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
    }

    public function test_full_open_play_end_to_end_flow()
    {
        $hostToken = JWTAuth::fromUser($this->host);
        $player1Token = JWTAuth::fromUser($this->player1);
        $player2Token = JWTAuth::fromUser($this->player2);

        $bookingId = $this->booking->booking_id ?? $this->booking->id;

        // BƯỚC 1: Host khởi tạo mời người chơi từ Booking (Init for booking)
        $initResponse = $this->actingAs($this->host, 'api')
            ->postJson("/api/open-plays/init-for-booking/{$bookingId}", [
                'title' => 'Kèo giao lưu cầu lông tối thứ 6',
                'max_players' => 2, // 1 host + 1 participant để test full nhanh
                'join_mode' => 'auto',
                'payment_mode' => 'split_payment',
            ]);

        $initResponse->assertStatus(200);
        $openPlayId = $initResponse->json('data.id');
        $this->assertNotNull($openPlayId);

        // BƯỚC 2: Kiểm tra danh sách Public Kèo Giao Lưu
        $publicListResponse = $this->getJson('/api/open-plays');
        $publicListResponse->assertStatus(200);
        $matches = $publicListResponse->json('data.data');
        $found = collect($matches)->firstWhere('id', $openPlayId);
        $this->assertNotNull($found, 'Trận đấu phải xuất hiện trong danh sách Open Play công khai.');

        // BƯỚC 3: Người chơi 1 (Player 1) xem chi tiết trận đấu
        $detailResponse = $this->getJson("/api/open-plays/{$openPlayId}");
        $detailResponse->assertStatus(200);
        $this->assertEquals('Kèo giao lưu cầu lông tối thứ 6', $detailResponse->json('data.title'));

        // BƯỚC 4: Người chơi 1 tham gia trận đấu (Join Open Play)
        $joinResponse = $this->actingAs($this->player1, 'api')
            ->postJson("/api/open-plays/{$openPlayId}/join", [
                'guest_phone' => $this->player1->phone,
            ]);

        if ($joinResponse->status() !== 201) {
            dump($joinResponse->json());
        }

        $joinResponse->assertStatus(201);
        $this->assertEquals('confirmed', $joinResponse->json('data.status'));

        // BƯỚC 5: Kiểm tra trận đấu đã chuyển sang trạng thái FULL vì đủ 2 người (Host + Player 1)
        $matchAfterJoin = OpenPlay::find($openPlayId);
        $this->assertEquals(2, $matchAfterJoin->current_players);
        $this->assertEquals('full', $matchAfterJoin->status);

        // BƯỚC 6: Người chơi 2 (Player 2) cố tham gia khi đã FULL -> Tự động hoặc chọn tham gia danh sách chờ (Waitlist)
        $waitlistResponse = $this->actingAs($this->player2, 'api')
            ->postJson("/api/open-plays/{$openPlayId}/waitlist");

        $waitlistResponse->assertStatus(200);
        $this->assertEquals(1, $waitlistResponse->json('data.position'));

        // BƯỚC 7: Kiểm tra API /api/my-open-plays cho cả Host và Player 1
        $hostMyPlaysResponse = $this->actingAs($this->host, 'api')
            ->getJson('/api/my-open-plays');

        $hostMyPlaysResponse->assertStatus(200);
        $hostedList = $hostMyPlaysResponse->json('data.hosted');
        $this->assertTrue(collect($hostedList)->contains('id', $openPlayId), 'Host phải thấy trận trong danh mục Hosted.');

        $player1MyPlaysResponse = $this->actingAs($this->player1, 'api')
            ->getJson('/api/my-open-plays');

        $player1MyPlaysResponse->assertStatus(200);
        $joinedList = $player1MyPlaysResponse->json('data.joined');
        $this->assertTrue(collect($joinedList)->contains('id', $openPlayId), 'Player 1 phải thấy trận trong danh mục Joined.');

        // BƯỚC 8: Check-in QR
        $participant = OpenPlayParticipant::where('open_play_id', $openPlayId)
            ->where('user_id', $this->player1->user_id)
            ->first();
        
        $this->assertNotNull($participant);
        $this->assertNotNull($participant->check_in_token);

        $checkInResponse = $this->actingAs($this->host, 'api')
            ->postJson("/api/open-plays/{$openPlayId}/check-in", [
                'check_in_token' => $participant->check_in_token,
            ]);

        $checkInResponse->assertStatus(200);
        $participant->refresh();
        $this->assertEquals('checked_in', $participant->status);
    }
}
