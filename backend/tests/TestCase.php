<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cố tình force environment = testing để test ko bị ảnh hưởng bởi local env
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        if (app()->environment('testing', 'local')) {
            config([
                'broadcasting.default' => 'log',
                'broadcasting.connections.log.driver' => 'log',
                'jwt.secret' => config('jwt.secret') ?: 'testing-jwt-secret-key-for-ci-only',
                'services.turnstile.secret_key' => config('services.turnstile.secret_key') ?: '1x0000000000000000000000000000000AA',
            ]);
        }
    }
}
