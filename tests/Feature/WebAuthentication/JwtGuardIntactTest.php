<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JwtGuardIntactTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_still_returns_a_jwt_token_from_the_api_guard(): void
    {
        User::factory()->create([
            'email' => 'api@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => 'api@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user']);
    }
}
