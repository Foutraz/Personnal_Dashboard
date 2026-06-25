<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Actions\RegisterUser;
use Technical\WebAuthentication\Exceptions\EmailAlreadyTakenException;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_registers_a_user_and_logs_into_the_web_guard(): void
    {
        $response = $this->post('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertTrue(Auth::guard('web')->check());
    }

    #[Test]
    public function it_throws_when_registering_an_already_taken_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->expectException(EmailAlreadyTakenException::class);

        app(RegisterUser::class)([
            'name' => 'John Doe',
            'email' => 'taken@example.com',
            'password' => 'password123',
        ]);
    }
}
