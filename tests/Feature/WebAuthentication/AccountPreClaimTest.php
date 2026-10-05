<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountPreClaimTest extends TestCase
{
    use RefreshDatabase;

    private const VICTIM_EMAIL = 'victim@example.com';

    private const VICTIM_GOOGLE_ID = 'google-victim';

    private const ATTACKER_PASSWORD = 'attacker-password';

    private function victimSignsInWithGoogle(): void
    {
        $socialUser = Mockery::mock(SocialUser::class);
        $socialUser->shouldReceive('getId')->andReturn(self::VICTIM_GOOGLE_ID);
        $socialUser->shouldReceive('getEmail')->andReturn(self::VICTIM_EMAIL);
        $socialUser->shouldReceive('getName')->andReturn('Victim');
        $socialUser->shouldReceive('getNickname')->andReturn(null);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    #[Test]
    public function it_keeps_the_victim_out_of_an_account_registered_with_their_email(): void
    {
        $this->post('/register', [
            'name' => 'Attacker',
            'email' => self::VICTIM_EMAIL,
            'password' => self::ATTACKER_PASSWORD,
            'password_confirmation' => self::ATTACKER_PASSWORD,
        ])->assertRedirect('/dashboard');
        $attacker = User::query()->where('email', self::VICTIM_EMAIL)->firstOrFail();
        $this->post('/logout');
        $this->victimSignsInWithGoogle();

        $this->get('/auth/google/callback')->assertRedirect(route('login'));

        $this->assertFalse(Auth::guard('web')->check());
        $this->assertNull($attacker->fresh()->google_id);
        $this->assertTrue(Hash::check(self::ATTACKER_PASSWORD, $attacker->fresh()->password));
    }

    #[Test]
    public function it_stops_an_attacker_from_pre_claiming_the_victims_email_through_the_api(): void
    {
        $attacker = User::factory()->unverified()->create([
            'email' => 'attacker@example.com',
            'password' => Hash::make(self::ATTACKER_PASSWORD),
        ]);

        $response = $this->actingAs($attacker, 'api')->postJson('/api/users/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $attacker->id, 'attributes' => ['email' => self::VICTIM_EMAIL]],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame('attacker@example.com', $attacker->fresh()->email);
        $this->victimSignsInWithGoogle();
        $this->get('/auth/google/callback')->assertRedirect('/dashboard');
        $this->assertNotSame($attacker->id, Auth::guard('web')->id());
        $this->assertNull($attacker->fresh()->google_id);
    }
}
