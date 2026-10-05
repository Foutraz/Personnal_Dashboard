<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Actions\FindOrCreateSocialUser;
use Technical\WebAuthentication\Exceptions\UnverifiedAccountLinkException;
use Tests\TestCase;

class GoogleAccountLinkingTest extends TestCase
{
    use RefreshDatabase;

    private const VICTIM_EMAIL = 'victim@example.com';

    private const VICTIM_GOOGLE_ID = 'google-victim';

    private function googleUser(string $googleId, string $email): SocialUser
    {
        $socialUser = Mockery::mock(SocialUser::class);
        $socialUser->shouldReceive('getId')->andReturn($googleId);
        $socialUser->shouldReceive('getEmail')->andReturn($email);
        $socialUser->shouldReceive('getName')->andReturn('Victim');
        $socialUser->shouldReceive('getNickname')->andReturn(null);

        return $socialUser;
    }

    private function signInWithGoogleAs(string $googleId, string $email): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($this->googleUser($googleId, $email));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    #[Test]
    public function it_refuses_to_link_google_to_an_unverified_account_sharing_the_email(): void
    {
        $squatter = User::factory()->unverified()->create(['email' => self::VICTIM_EMAIL]);
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL);

        $this->get('/auth/google/callback');

        $this->assertFalse(Auth::guard('web')->check());
        $this->assertNull($squatter->fresh()->google_id);
        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function it_throws_a_named_exception_when_the_account_to_link_is_unverified(): void
    {
        User::factory()->unverified()->create(['email' => self::VICTIM_EMAIL]);

        $this->expectException(UnverifiedAccountLinkException::class);

        app(FindOrCreateSocialUser::class)($this->googleUser(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL));
    }

    #[Test]
    public function it_sends_the_user_back_to_the_login_screen_with_a_generic_message(): void
    {
        User::factory()->unverified()->create(['email' => self::VICTIM_EMAIL]);
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL);

        $response = $this->get('/auth/google/callback');

        $message = __('web-authentication::auth.social_link_refused');
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => $message]);
        $this->assertNotSame('web-authentication::auth.social_link_refused', $message);
        $this->assertStringNotContainsString(self::VICTIM_EMAIL, $message);
    }

    #[Test]
    public function it_shows_the_generic_message_on_the_login_screen(): void
    {
        User::factory()->unverified()->create(['email' => self::VICTIM_EMAIL]);
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL);

        $response = $this->followingRedirects()->get('/auth/google/callback');

        $response->assertSee(__('web-authentication::auth.social_link_refused'));
    }

    #[Test]
    public function it_links_google_to_a_verified_account_sharing_the_email(): void
    {
        $owner = User::factory()->verified()->create(['email' => self::VICTIM_EMAIL]);
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertSame($owner->id, Auth::guard('web')->id());
        $this->assertSame(self::VICTIM_GOOGLE_ID, $owner->fresh()->google_id);
        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function it_signs_in_an_unverified_account_already_linked_to_the_same_google_identity(): void
    {
        $linked = User::factory()->unverified()->create([
            'email' => self::VICTIM_EMAIL,
            'google_id' => self::VICTIM_GOOGLE_ID,
        ]);
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertSame($linked->id, Auth::guard('web')->id());
    }

    #[Test]
    public function it_finds_the_linked_account_by_google_identity_when_the_email_changed(): void
    {
        $linked = User::factory()->unverified()->create([
            'email' => 'renamed@example.com',
            'google_id' => self::VICTIM_GOOGLE_ID,
        ]);
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertSame($linked->id, Auth::guard('web')->id());
        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function it_creates_a_verified_user_when_no_account_shares_the_email(): void
    {
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $created = User::query()->where('email', self::VICTIM_EMAIL)->firstOrFail();
        $this->assertSame(self::VICTIM_GOOGLE_ID, $created->google_id);
        $this->assertNotNull($created->email_verified_at);
        $this->assertSame($created->id, Auth::guard('web')->id());
    }
}
