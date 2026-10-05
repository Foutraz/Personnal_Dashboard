<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Actions\FindOrCreateSocialUser;
use Technical\WebAuthentication\Exceptions\SocialSignInRefusedException;
use Technical\WebAuthentication\Exceptions\UnlistedGoogleEmailException;
use Tests\Feature\WebAuthentication\Concerns\FakesGoogleSignIn;
use Tests\Feature\WebAuthentication\Concerns\OpensRegistration;
use Tests\TestCase;

class GoogleRegistrationAllowListTest extends TestCase
{
    use FakesGoogleSignIn, OpensRegistration, RefreshDatabase;

    private const ALLOWED_EMAIL = 'owner@example.com';

    private const GOOGLE_ID = 'google-owner';

    #[Test]
    public function it_creates_a_new_google_user_whose_email_is_listed(): void
    {
        $this->allowRegistrationFor(self::ALLOWED_EMAIL);
        $this->signInWithGoogleAs(self::GOOGLE_ID, self::ALLOWED_EMAIL);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['email' => self::ALLOWED_EMAIL, 'google_id' => self::GOOGLE_ID]);
        $this->assertTrue(Auth::guard('web')->check());
    }

    #[Test]
    public function it_refuses_a_new_google_user_whose_email_is_not_listed_and_creates_no_user(): void
    {
        $this->allowRegistrationFor(self::ALLOWED_EMAIL);
        $this->signInWithGoogleAs('google-stranger', 'stranger@example.com');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => __('web-authentication::auth.social_link_refused')]);
        $this->assertSame(0, User::query()->count());
        $this->assertFalse(Auth::guard('web')->check());
    }

    #[Test]
    public function it_creates_a_new_google_user_whose_email_is_a_case_variant_of_a_listed_one(): void
    {
        $this->allowRegistrationFor(self::ALLOWED_EMAIL);
        $this->signInWithGoogleAs(self::GOOGLE_ID, 'Owner@Example.com');

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function it_refuses_every_new_google_user_when_the_allow_list_is_empty(): void
    {
        $this->allowRegistrationFor();
        $this->signInWithGoogleAs(self::GOOGLE_ID, self::ALLOWED_EMAIL);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));

        $this->assertSame(0, User::query()->count());
        $this->assertFalse(Auth::guard('web')->check());
    }

    #[Test]
    public function it_throws_a_named_refusal_for_an_unlisted_google_email(): void
    {
        $this->allowRegistrationFor(self::ALLOWED_EMAIL);

        $this->expectException(UnlistedGoogleEmailException::class);

        app(FindOrCreateSocialUser::class)($this->googleUser('google-stranger', 'stranger@example.com'));
    }

    #[Test]
    public function it_gives_the_unlisted_google_email_refusal_the_generic_social_sign_in_family(): void
    {
        $refusal = new UnlistedGoogleEmailException('google-stranger');

        $this->assertInstanceOf(SocialSignInRefusedException::class, $refusal);
        $this->assertSame('google-stranger', $refusal->googleId);
    }

    #[Test]
    public function it_keeps_signing_in_a_google_user_already_linked_when_the_email_is_not_listed(): void
    {
        $this->allowRegistrationFor();
        $linkedUser = User::factory()->verified()->create(['email' => 'linked@example.com', 'google_id' => self::GOOGLE_ID]);
        $this->signInWithGoogleAs(self::GOOGLE_ID, 'linked@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertSame($linkedUser->id, Auth::guard('web')->id());
        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function it_keeps_linking_google_to_a_verified_account_when_the_email_is_not_listed(): void
    {
        $this->allowRegistrationFor();
        $existingUser = User::factory()->verified()->create(['email' => 'existing@example.com', 'google_id' => null]);
        $this->signInWithGoogleAs(self::GOOGLE_ID, 'existing@example.com');

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertSame(self::GOOGLE_ID, $existingUser->fresh()->google_id);
        $this->assertSame(1, User::query()->count());
    }
}
