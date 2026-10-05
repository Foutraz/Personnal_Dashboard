<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Actions\FindOrCreateSocialUser;
use Technical\WebAuthentication\Exceptions\DeletedAccountSignInException;
use Technical\WebAuthentication\Exceptions\GoogleIdentityMismatchException;
use Technical\WebAuthentication\Exceptions\UnverifiedAccountLinkException;
use Technical\WebAuthentication\Exceptions\UnverifiedGoogleEmailException;
use Tests\Feature\WebAuthentication\Concerns\FakesGoogleSignIn;
use Tests\TestCase;

class GoogleAccountLinkingTest extends TestCase
{
    use FakesGoogleSignIn, RefreshDatabase;

    private const VICTIM_EMAIL = 'victim@example.com';

    private const VICTIM_GOOGLE_ID = 'google-victim';

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

    #[Test]
    public function it_refuses_a_google_identity_swap_on_an_account_linked_to_another_identity(): void
    {
        $owner = User::factory()->verified()->create([
            'email' => self::VICTIM_EMAIL,
            'google_id' => 'google-owner',
        ]);
        $this->signInWithGoogleAs('google-intruder', self::VICTIM_EMAIL);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => __('web-authentication::auth.social_link_refused')]);
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertSame('google-owner', $owner->fresh()->google_id);
        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function it_throws_a_named_exception_when_the_account_is_linked_to_another_identity(): void
    {
        User::factory()->verified()->create([
            'email' => self::VICTIM_EMAIL,
            'google_id' => 'google-owner',
        ]);

        $this->expectException(GoogleIdentityMismatchException::class);

        app(FindOrCreateSocialUser::class)($this->googleUser('google-intruder', self::VICTIM_EMAIL));
    }

    #[Test]
    public function it_refuses_to_create_an_account_when_google_has_not_verified_the_email(): void
    {
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL, emailVerified: false);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => __('web-authentication::auth.social_link_refused')]);
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function it_refuses_to_link_a_verified_account_when_google_has_not_verified_the_email(): void
    {
        $owner = User::factory()->verified()->create(['email' => self::VICTIM_EMAIL]);
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL, emailVerified: false);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));

        $this->assertFalse(Auth::guard('web')->check());
        $this->assertNull($owner->fresh()->google_id);
    }

    #[Test]
    public function it_throws_a_named_exception_when_google_has_not_verified_the_email(): void
    {
        $this->expectException(UnverifiedGoogleEmailException::class);

        app(FindOrCreateSocialUser::class)($this->googleUser(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL, emailVerified: false));
    }

    #[Test]
    public function it_refuses_a_google_user_that_carries_no_email_verified_claim(): void
    {
        $socialUser = GoogleUser::fake([
            'id' => self::VICTIM_GOOGLE_ID,
            'email' => self::VICTIM_EMAIL,
            'name' => 'Google User',
            'nickname' => null,
        ]);

        $this->expectException(UnverifiedGoogleEmailException::class);

        app(FindOrCreateSocialUser::class)($socialUser);
    }

    #[Test]
    public function it_refuses_a_social_user_that_is_not_an_oauth_two_user(): void
    {
        $socialUser = Mockery::mock(SocialUser::class);
        $socialUser->shouldReceive('getId')->andReturn(self::VICTIM_GOOGLE_ID);
        $socialUser->shouldReceive('getEmail')->andReturn(self::VICTIM_EMAIL);

        $this->expectException(UnverifiedGoogleEmailException::class);

        app(FindOrCreateSocialUser::class)($socialUser);
    }

    #[Test]
    public function it_signs_in_an_already_linked_identity_even_when_google_reports_the_email_unverified(): void
    {
        $linked = User::factory()->verified()->create([
            'email' => self::VICTIM_EMAIL,
            'google_id' => self::VICTIM_GOOGLE_ID,
        ]);
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL, emailVerified: false);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertSame($linked->id, Auth::guard('web')->id());
    }

    #[Test]
    public function it_refuses_a_google_identity_held_by_a_soft_deleted_account(): void
    {
        $deleted = User::factory()->verified()->create([
            'email' => 'former@example.com',
            'google_id' => self::VICTIM_GOOGLE_ID,
            'deleted_at' => now(),
        ]);
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => __('web-authentication::auth.social_link_refused')]);
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertSoftDeleted('users', ['id' => $deleted->id]);
        $this->assertSame(1, User::query()->withTrashed()->count());
    }

    #[Test]
    public function it_refuses_an_email_held_by_a_soft_deleted_account(): void
    {
        $deleted = User::factory()->verified()->create([
            'email' => self::VICTIM_EMAIL,
            'deleted_at' => now(),
        ]);
        $this->signInWithGoogleAs(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => __('web-authentication::auth.social_link_refused')]);
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertSoftDeleted('users', ['id' => $deleted->id]);
        $this->assertNull(User::query()->withTrashed()->findOrFail($deleted->id)->google_id);
        $this->assertSame(1, User::query()->withTrashed()->count());
    }

    #[Test]
    public function it_throws_a_named_exception_when_the_matching_account_is_soft_deleted(): void
    {
        User::factory()->verified()->create([
            'email' => self::VICTIM_EMAIL,
            'deleted_at' => now(),
        ]);

        $this->expectException(DeletedAccountSignInException::class);

        app(FindOrCreateSocialUser::class)($this->googleUser(self::VICTIM_GOOGLE_ID, self::VICTIM_EMAIL));
    }
}
