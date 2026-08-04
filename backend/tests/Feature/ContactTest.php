<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable ThrottleRequests middleware for these tests to avoid 429
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_submit_contact_fails_without_turnstile()
    {
        $response = $this->postJson('/api/submitcontact', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'subject' => 'Test Subject',
            'message' => 'This is a test message',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Xác thực CAPTCHA thất bại! Vui lòng thử lại.',
            ]);
    }

    public function test_submit_contact_validates_required_fields()
    {
        // Mock Turnstile true
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson('/api/submitcontact', [
            'turnstile_token' => 'dummy_token',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
    }

    public function test_submit_contact_success()
    {
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true], 200),
        ]);

        $response = $this->postJson('/api/submitcontact', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Help',
            'message' => 'Need help',
            'turnstile_token' => 'dummy_token',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('contacts', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_submit_contact_email_success()
    {
        $response = $this->postJson('/api/submitcontactemail', [
            'email' => 'newsletter@example.com',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('contacts', [
            'email' => 'newsletter@example.com',
            'subject' => 'Đăng ký nhận bản tin',
        ]);
    }
}
