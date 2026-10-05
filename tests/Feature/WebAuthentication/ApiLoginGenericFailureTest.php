<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiLoginGenericFailureTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'password123';

    private function apiLogin(string $email, string $password): TestResponse
    {
        return $this->postJson('/auth/login', ['email' => $email, 'password' => $password]);
    }

    private function unknownEmailFailure(): TestResponse
    {
        return $this->apiLogin('nobody@example.com', self::PASSWORD);
    }

    #[Test]
    public function it_answers_a_generic_unauthorized_for_an_unknown_email(): void
    {
        $this->unknownEmailFailure()->assertUnauthorized();
    }

    #[Test]
    public function it_answers_a_generic_unauthorized_for_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => Hash::make(self::PASSWORD)]);

        $this->apiLogin('jane@example.com', 'wrong-password')->assertUnauthorized();
    }

    #[Test]
    public function it_answers_a_generic_unauthorized_for_a_soft_deleted_account(): void
    {
        User::factory()->create(['email' => 'gone@example.com', 'password' => Hash::make(self::PASSWORD)])->delete();

        $this->apiLogin('gone@example.com', self::PASSWORD)->assertUnauthorized();
    }

    #[Test]
    public function it_gives_the_same_answer_whether_the_email_is_unknown_deleted_or_the_password_is_wrong(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => Hash::make(self::PASSWORD)]);
        User::factory()->create(['email' => 'gone@example.com', 'password' => Hash::make(self::PASSWORD)])->delete();

        $unknownEmail = $this->unknownEmailFailure()->getContent();
        $wrongPassword = $this->apiLogin('jane@example.com', 'wrong-password')->getContent();
        $softDeleted = $this->apiLogin('gone@example.com', self::PASSWORD)->getContent();

        $this->assertSame($unknownEmail, $wrongPassword);
        $this->assertSame($unknownEmail, $softDeleted);
    }

    #[Test]
    public function it_still_validates_the_shape_of_the_login_payload(): void
    {
        $this->postJson('/auth/login', ['email' => 'not-an-email', 'password' => self::PASSWORD])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
