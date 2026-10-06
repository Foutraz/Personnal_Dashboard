<?php

namespace Tests\Feature\WebAuthentication;

use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationThrottlingTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED_ATTEMPTS = 5;

    private function assertThrottledAfterAllowedAttempts(Closure $attempt): void
    {
        for ($count = 1; $count <= self::ALLOWED_ATTEMPTS; $count++) {
            $this->assertNotSame(429, $attempt()->getStatusCode());
        }

        $attempt()->assertTooManyRequests();
    }

    private function webLogin(string $email): TestResponse
    {
        return $this->post('/login', ['email' => $email, 'password' => 'wrong-password']);
    }

    #[Test]
    public function it_throttles_the_sixth_web_login_attempt_within_a_minute(): void
    {
        $this->assertThrottledAfterAllowedAttempts(fn () => $this->webLogin('jane@example.com'));
    }

    #[Test]
    public function it_throttles_the_sixth_registration_attempt_within_a_minute(): void
    {
        $this->assertThrottledAfterAllowedAttempts(fn () => $this->post('/register', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]));
    }

    #[Test]
    public function it_throttles_the_sixth_api_login_attempt_within_a_minute(): void
    {
        $this->assertThrottledAfterAllowedAttempts(fn () => $this->postJson('/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]));
    }

    #[Test]
    public function it_counts_the_attempts_of_each_email_separately(): void
    {
        for ($count = 1; $count <= self::ALLOWED_ATTEMPTS; $count++) {
            $this->webLogin('jane@example.com');
        }

        $this->assertNotSame(429, $this->webLogin('john@example.com')->getStatusCode());
    }

    #[Test]
    public function it_ignores_the_case_of_the_email_when_counting_attempts(): void
    {
        for ($count = 1; $count <= self::ALLOWED_ATTEMPTS; $count++) {
            $this->webLogin('jane@example.com');
        }

        $this->webLogin('JANE@Example.com')->assertTooManyRequests();
    }
}
