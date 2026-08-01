<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => '0123456789abcdef0123456789abcdef',
            'services.turnstile.secret_key' => 'turnstile-test-secret',
        ]);

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true]),
        ]);

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('password_resets_otp');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('full_name', 120);
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role')->default('customer');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_resets_otp', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('otp', 255);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_mobile_flags_cannot_bypass_turnstile(): void
    {
        $this->postJson('/api/register', $this->registrationPayload([
            'is_mobile' => true,
            'turnstile_token' => null,
        ]), [
            'User-Agent' => 'Dart/3.0 Flutter',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $payload = $this->registrationPayload();
        unset($payload['password_confirmation']);

        $this->postJson('/api/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_registration_returns_an_authenticated_user_session(): void
    {
        $this->postJson('/api/register', $this->registrationPayload([
            'email' => 'NEW@EXAMPLE.COM',
        ]))->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.email', 'new@example.com')
            ->assertJsonPath('role', 'customer')
            ->assertJsonStructure(['access_token', 'user']);
    }

    public function test_refresh_route_accepts_tokens_before_auth_middleware_runs(): void
    {
        $route = collect(Route::getRoutes())->first(
            fn ($route) => $route->uri() === 'api/refresh' && in_array('POST', $route->methods(), true)
        );

        $this->assertNotNull($route);
        $this->assertNotContains('auth:api,admin', $route->gatherMiddleware());
    }

    public function test_expired_otp_session_cannot_reset_password(): void
    {
        $oldPassword = Hash::make('OldPassword1!');
        $hashedOtp = Hash::make('123456');
        $email = 'reset@example.com';

        DB::table('users')->insert([
            'full_name' => 'Reset User',
            'email' => $email,
            'password' => $oldPassword,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('password_resets_otp')->insert([
            'email' => $email,
            'otp' => $hashedOtp,
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(16),
        ]);

        $resetToken = hash('sha256', $email.$hashedOtp.config('app.key'));

        $this->postJson('/api/forgot-password/reset', [
            'email' => $email,
            'reset_token' => $resetToken,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('OldPassword1!', DB::table('users')->value('password')));
        $this->assertDatabaseMissing('password_resets_otp', ['email' => $email]);
    }

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'turnstile_token' => 'valid-turnstile-token',
        ], $overrides);
    }
}
