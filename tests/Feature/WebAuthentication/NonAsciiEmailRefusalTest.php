<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\WebAuthentication\Concerns\OpensRegistration;
use Tests\TestCase;

class NonAsciiEmailRefusalTest extends TestCase
{
    use OpensRegistration, RefreshDatabase;

    private const PASSWORD = 'password123';

    /**
     * @return array<string, array{string}>
     */
    public static function nonAsciiVariantsOfJane(): array
    {
        return [
            'combining grapheme joiner' => ["jane\u{034F}@example.com"],
            'combining acute accent' => ["jane\u{0301}@example.com"],
            'variation selector' => ["jane\u{FE0F}@example.com"],
            'fullwidth letter' => ["\u{FF4A}ane@example.com"],
            'kelvin sign' => ["\u{212A}ane@example.com"],
        ];
    }

    private function asciiRefusal(): string
    {
        return __('validation.ascii', ['attribute' => 'email']);
    }

    private function createJane(): User
    {
        return User::factory()->create(['email' => 'jane@example.com', 'password' => Hash::make(self::PASSWORD)]);
    }

    #[Test]
    #[DataProvider('nonAsciiVariantsOfJane')]
    public function it_refuses_a_non_ascii_email_on_the_web_login_with_a_validation_error(string $email): void
    {
        $this->createJane();

        $this->postJson('/login', ['email' => $email, 'password' => self::PASSWORD])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => $this->asciiRefusal()]);

        $this->assertFalse(Auth::guard('web')->check());
    }

    #[Test]
    #[DataProvider('nonAsciiVariantsOfJane')]
    public function it_refuses_a_non_ascii_email_on_the_api_login_with_a_validation_error(string $email): void
    {
        $this->createJane();

        $this->postJson('/auth/login', ['email' => $email, 'password' => self::PASSWORD])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => $this->asciiRefusal()]);
    }

    #[Test]
    #[DataProvider('nonAsciiVariantsOfJane')]
    public function it_refuses_a_non_ascii_email_on_the_registration_with_a_validation_error(string $email): void
    {
        $this->allowRegistrationFor($email, 'jane@example.com');

        $this->postJson('/register', [
            'name' => 'Jane Doe',
            'email' => $email,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => $this->asciiRefusal()]);

        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function it_refuses_the_kelvin_sign_variant_of_an_allowed_email_on_the_registration(): void
    {
        $this->allowRegistrationFor('kevin@example.com');

        $this->postJson('/register', [
            'name' => 'Kevin',
            'email' => "\u{212A}evin@example.com",
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => $this->asciiRefusal()]);

        $this->assertSame(0, User::query()->count());
        $this->assertFalse(Auth::guard('web')->check());
    }
}
