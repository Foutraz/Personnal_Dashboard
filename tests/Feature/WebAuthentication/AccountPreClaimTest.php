<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\WebAuthentication\Concerns\FakesGoogleSignIn;
use Tests\Feature\WebAuthentication\Concerns\OpensRegistration;
use Tests\TestCase;

class AccountPreClaimTest extends TestCase
{
    use FakesGoogleSignIn, OpensRegistration, RefreshDatabase;

    private const VICTIM_EMAIL = 'victim@example.com';

    private const VICTIM_GOOGLE_ID = 'google-victim';

    private const ATTACKER_PASSWORD = 'attacker-password';

    protected function setUp(): void
    {
        parent::setUp();

        $this->allowRegistrationFor(self::VICTIM_EMAIL);
    }

    private function victimSignsInWithGoogle(): void
    {
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL);
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

    #[Test]
    public function it_stops_an_unverified_google_email_from_pre_claiming_the_victims_account(): void
    {
        $this->queueGoogleSignIns(
            $this->googleUser('google-attacker', self::VICTIM_EMAIL, emailVerified: false),
            $this->googleUser(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL),
        );

        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertSame(0, User::query()->count());

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $victim = User::query()->where('email', self::VICTIM_EMAIL)->firstOrFail();
        $this->assertSame(self::VICTIM_GOOGLE_ID, $victim->google_id);
        $this->assertNotNull($victim->email_verified_at);
        $this->assertSame($victim->id, Auth::guard('web')->id());
        $this->assertSame(1, User::query()->count());
    }
}
