<?php

namespace Tests\Feature\WebAuthentication;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PerEmailHourlyThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const ATTEMPTS_PER_HOUR = 30;

    private function loginFromAddress(string $email, int $address): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '10.0.'.intdiv($address, 250).'.'.($address % 250 + 1)])
            ->postJson('/auth/login', ['email' => $email, 'password' => 'wrong-password']);
    }

    private function exhaustTheHourlyAllowance(string $email): void
    {
        for ($address = 1; $address <= self::ATTEMPTS_PER_HOUR; $address++) {
            $this->assertNotSame(429, $this->loginFromAddress($email, $address)->getStatusCode());
        }
    }

    #[Test]
    public function it_throttles_the_thirty_first_attempt_on_one_email_whatever_the_client_address(): void
    {
        $this->exhaustTheHourlyAllowance('jane@example.com');

        $this->loginFromAddress('jane@example.com', self::ATTEMPTS_PER_HOUR + 1)->assertTooManyRequests();
    }

    #[Test]
    public function it_counts_the_hourly_attempts_of_a_case_and_space_variant_of_the_email_together(): void
    {
        $this->exhaustTheHourlyAllowance('jane@example.com');

        $this->loginFromAddress(' JANE@Example.com ', self::ATTEMPTS_PER_HOUR + 1)->assertTooManyRequests();
    }

    #[Test]
    public function it_leaves_another_email_unaffected_by_the_hourly_limit(): void
    {
        $this->exhaustTheHourlyAllowance('jane@example.com');

        $this->assertNotSame(429, $this->loginFromAddress('john@example.com', self::ATTEMPTS_PER_HOUR + 1)->getStatusCode());
    }

    #[Test]
    public function it_releases_the_email_once_the_hour_has_passed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00:00', 'UTC'));
        $this->exhaustTheHourlyAllowance('jane@example.com');
        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00:01', 'UTC'));

        $this->assertNotSame(429, $this->loginFromAddress('jane@example.com', self::ATTEMPTS_PER_HOUR + 1)->getStatusCode());
    }

    #[Test]
    public function it_shares_no_hourly_allowance_between_requests_that_carry_no_email(): void
    {
        for ($address = 1; $address <= self::ATTEMPTS_PER_HOUR + 1; $address++) {
            $this->assertNotSame(429, $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.'.$address])->postJson('/auth/login', ['password' => 'wrong-password'])->getStatusCode());
        }
    }
}
