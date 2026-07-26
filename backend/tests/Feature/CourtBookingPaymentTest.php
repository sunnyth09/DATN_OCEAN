<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtBooking;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\CourtPrice;

class CourtBookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->court = Court::create([
            'court_name' => 'Sân 1',
            'court_code' => 'SAN-1',
            'type' => 'standard',
            'status' => 'active'
        ]);

        CourtPrice::create([
            'court_id' => $this->court->court_id,
            'day_type' => 'all',
            'from_time' => '00:00:00',
            'to_time' => '23:59:59',
            'price_per_hour' => 100000,
            'is_active' => true
        ]);
    }

    public function test_split_payment_records_deposit_and_full_payment()
    {
        $admin = Admin::create([
            'full_name' => 'Admin Test',
            'email' => 'admin_test@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
            'phone' => '0901234567',
        ]);
        $user = User::factory()->create();
        
        $booking = CourtBooking::create([
            'booking_code' => 'BK-TEST',
            'user_id' => $user->id,
            'court_id' => $this->court->court_id,
            'booking_date' => now()->addDays(1)->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'original_price' => 100000,
            'service_amount' => 0,
            'total_amount' => 100000,
            'payment_method' => 'cash',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'deposit_amount' => 0,
        ]);

        // Mock Admin API call to split payment
        $response = $this->actingAs($admin, 'admin')
            ->postJson("/api/admin/court-bookings/{$booking->booking_id}/split-payment", [
                'payments' => [
                    [
                        'payment_method' => 'vnpay',
                        'payment_type' => 'deposit',
                        'amount' => 30000,
                        'transaction_code' => 'VN123',
                        'status' => 'success',
                    ],
                    [
                        'payment_method' => 'cash',
                        'payment_type' => 'full',
                        'amount' => 70000,
                    ]
                ]
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Split payments recorded successfully.'
        ]);

        // Assert DB state
        $booking->refresh();
        $this->assertEquals(30000, $booking->deposit_amount);
        $this->assertEquals('paid', $booking->payment_status);
        
        $this->assertDatabaseHas('court_booking_payments', [
            'booking_id' => $booking->booking_id,
            'payment_method' => 'vnpay',
            'payment_type' => 'deposit',
            'amount' => 30000,
        ]);

        $this->assertDatabaseHas('court_booking_payments', [
            'booking_id' => $booking->booking_id,
            'payment_method' => 'cash',
            'payment_type' => 'full',
            'amount' => 70000,
        ]);
    }
}
