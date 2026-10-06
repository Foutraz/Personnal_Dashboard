<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\WebAuthentication\Concerns\OpensRegistration;
use Tests\TestCase;

class RegisterAllowListTest extends TestCase
{
    use OpensRegistration, RefreshDatabase;

    private const ALLOWED_EMAIL = 'owner@example.com';

    private const REFUSAL_EN = 'Registration is not available for this email address.';

    private const REFUSAL_FR = "L'inscription n'est pas disponible pour cette adresse e-mail.";

    private const ALLOWED_ATTEMPTS = 5;

    private function register(string $email): TestResponse
    {
        return $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
    }

    /**
     * @return list<string>
     */
    private function emailErrors(): array
    {
        return session('errors')->get('email');
    }

    #[Test]
    public function it_registers_an_email_listed_in_the_allow_list(): void
    {
        $this->allowRegistrationFor(self::ALLOWED_EMAIL);

        $this->register(self::ALLOWED_EMAIL)->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['email' => self::ALLOWED_EMAIL]);
        $this->assertTrue(Auth::guard('web')->check());
    }

    #[Test]
    public function it_refuses_an_email_missing_from_the_allow_list_and_creates_no_user(): void
    {
        $this->allowRegistrationFor(self::ALLOWED_EMAIL);

        $this->register('stranger@example.com')->assertSessionHasErrors('email');

        $this->assertSame(0, User::query()->count());
        $this->assertFalse(Auth::guard('web')->check());
    }

    #[Test]
    public function it_registers_a_case_variant_of_an_allowed_email(): void
    {
        $this->allowRegistrationFor(self::ALLOWED_EMAIL);

        $this->register('Owner@Example.COM')->assertRedirect('/dashboard');

        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function it_compares_against_a_list_written_with_spaces_and_capitals(): void
    {
        config(['web-authentication.registration.allowed_emails' => ' Other@Example.com , OWNER@example.com ,']);

        $this->register(self::ALLOWED_EMAIL)->assertRedirect('/dashboard');

        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function it_refuses_every_email_when_the_allow_list_is_empty(): void
    {
        $this->allowRegistrationFor();

        $this->register(self::ALLOWED_EMAIL)->assertSessionHasErrors('email');

        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function it_refuses_every_email_when_the_allow_list_is_not_configured(): void
    {
        config(['web-authentication.registration.allowed_emails' => null]);

        $this->register(self::ALLOWED_EMAIL)->assertSessionHasErrors('email');

        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function it_gives_the_same_refusal_whether_or_not_the_unlisted_email_has_an_account(): void
    {
        $this->allowRegistrationFor(self::ALLOWED_EMAIL);
        User::factory()->create(['email' => 'known@example.com']);
        app()->setLocale('en');

        $this->register('unknown@example.com');
        $unknownEmailErrors = $this->emailErrors();
        $this->register('known@example.com');
        $knownEmailErrors = $this->emailErrors();

        $this->assertSame([self::REFUSAL_EN], $unknownEmailErrors);
        $this->assertSame([self::REFUSAL_EN], $knownEmailErrors);
    }

    #[Test]
    public function it_still_refuses_an_allowed_email_that_already_has_an_account(): void
    {
        $this->allowRegistrationFor(self::ALLOWED_EMAIL);
        User::factory()->create(['email' => self::ALLOWED_EMAIL]);

        $this->register(self::ALLOWED_EMAIL)->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->count());
        $this->assertFalse(Auth::guard('web')->check());
    }

    #[Test]
    public function it_words_the_refusal_in_english(): void
    {
        app()->setLocale('en');

        $this->register('stranger@example.com');

        $this->assertSame([self::REFUSAL_EN], $this->emailErrors());
    }

    #[Test]
    public function it_words_the_refusal_in_french(): void
    {
        app()->setLocale('fr');

        $this->register('stranger@example.com');

        $this->assertSame([self::REFUSAL_FR], $this->emailErrors());
    }

    #[Test]
    public function it_still_throttles_refused_registration_attempts(): void
    {
        for ($count = 1; $count <= self::ALLOWED_ATTEMPTS; $count++) {
            $this->register('stranger@example.com')->assertSessionHasErrors('email');
        }

        $this->register('stranger@example.com')->assertTooManyRequests();
    }
}
