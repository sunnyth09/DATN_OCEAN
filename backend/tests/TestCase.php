<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (app()->environment('testing')) {
            config([
                'broadcasting.default' => 'log',
                'broadcasting.connections.log.driver' => 'log',
                'jwt.secret' => config('jwt.secret') ?: 'testing-jwt-secret-key-for-ci-only',
                'services.turnstile.secret_key' => config('services.turnstile.secret_key') ?: '1x0000000000000000000000000000000AA',
            ]);
        }
    }
}
