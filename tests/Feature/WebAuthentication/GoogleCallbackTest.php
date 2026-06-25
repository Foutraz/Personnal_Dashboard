<?php

namespace Tests\Feature\WebAuthentication;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleCallbackTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_user_with_a_google_id_and_logs_into_the_web_guard(): void
    {
        $socialUser = Mockery::mock(SocialUser::class);
        $socialUser->shouldReceive('getId')->andReturn('google-12345');
        $socialUser->shouldReceive('getEmail')->andReturn('social@example.com');
        $socialUser->shouldReceive('getName')->andReturn('Social User');
        $socialUser->shouldReceive('getNickname')->andReturn(null);

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/');
        $this->assertDatabaseHas('users', [
            'email' => 'social@example.com',
            'google_id' => 'google-12345',
        ]);
        $this->assertTrue(Auth::guard('web')->check());

        $userId = Auth::guard('web')->id();
        $this->assertIsString($userId);
        $this->assertSame(26, strlen($userId));
    }
}
