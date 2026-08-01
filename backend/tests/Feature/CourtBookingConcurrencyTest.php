<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtBookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup initial data for court and pricing
        $this->court = Court::create([
            'court_name' => 'Sân 1',
            'court_code' => 'SAN-1',
            'type' => 'standard',
            'status' => 'active',
        ]);

        CourtPrice::create([
            'court_id' => $this->court->court_id,
            'day_type' => 'all',
            'from_time' => '00:00:00',
            'to_time' => '23:59:59',
            'price_per_hour' => 100000,
            'is_active' => true,
        ]);
    }

    public function test_concurrent_locks_prevent_race_condition()
    {
        // 1. Tạo nhiều users
        $users = User::factory()->count(5)->create();
        $date = now()->addDays(1)->format('Y-m-d');
        $startTime = '08:00';
        $endTime = '09:00';

        // 2. Chạy đồng thời 5 request gọi API lock vào cùng 1 khung giờ
        $responses = [];
        // Note: For a true concurrency test we would need to fork processes or use parallel testing tools.
        // For standard PHPUnit, we'll simulate sequential calls to ensure the first one locks successfully
        // and subsequent ones fail gracefully due to the first lock.

        $firstUser = $users[0];
        $firstResponse = $this->actingAs($firstUser, 'api')
            ->postJson('/api/court-bookings/lock', [
                'court_id' => $this->court->court_id,
                'booking_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);

        $firstResponse->assertStatus(200); // 1st lock should succeed

        $secondUser = $users[1];
        $secondResponse = $this->actingAs($secondUser, 'api')
            ->postJson('/api/court-bookings/lock', [
                'court_id' => $this->court->court_id,
                'booking_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);

        // Second lock should fail because it's already locked by the first user
        $secondResponse->assertStatus(400);
    }
}
