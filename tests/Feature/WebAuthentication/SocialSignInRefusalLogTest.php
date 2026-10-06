<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\User as GoogleUser;
use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Exceptions\BlankGoogleIdentityException;
use Technical\WebAuthentication\Exceptions\DeletedAccountSignInException;
use Technical\WebAuthentication\Exceptions\GoogleIdentityMismatchException;
use Technical\WebAuthentication\Exceptions\UnlistedGoogleEmailException;
use Technical\WebAuthentication\Exceptions\UnverifiedAccountLinkException;
use Technical\WebAuthentication\Exceptions\UnverifiedGoogleEmailException;
use Tests\Feature\WebAuthentication\Concerns\FakesGoogleSignIn;
use Tests\Feature\WebAuthentication\Concerns\OpensRegistration;
use Tests\TestCase;

class SocialSignInRefusalLogTest extends TestCase
{
    use FakesGoogleSignIn, OpensRegistration, RefreshDatabase;

    private const EMAIL = 'victim@example.com';

    private const GOOGLE_ID = 'google-victim';

    /**
     * @param  class-string  $refusal
     */
    private function assertRefusalLoggedOnceWith(string $refusal, string $identifier): void
    {
        Log::shouldHaveReceived('info')->once()->withArgs(
            fn (string $message, array $context): bool => ($context['refusal'] ?? null) === $refusal
                && str_contains($message, $identifier)
                && ! str_contains($message.json_encode($context), self::EMAIL),
        );
        Log::shouldNotHaveReceived('error');
    }

    #[Test]
    public function it_logs_the_refusal_of_a_google_email_that_google_did_not_verify(): void
    {
        Log::spy();
        $this->signInWithGoogleAs(self::GOOGLE_ID, self::EMAIL, emailVerified: false);

        $this->get('/auth/google/callback');

        $this->assertRefusalLoggedOnceWith(UnverifiedGoogleEmailException::class, self::GOOGLE_ID);
    }

    #[Test]
    public function it_logs_the_refusal_of_a_new_google_user_outside_the_allow_list(): void
    {
        Log::spy();
        $this->allowRegistrationFor('owner@example.com');
        $this->signInWithGoogleAs(self::GOOGLE_ID, self::EMAIL);

        $this->get('/auth/google/callback');

        $this->assertRefusalLoggedOnceWith(UnlistedGoogleEmailException::class, self::GOOGLE_ID);
    }

    #[Test]
    public function it_logs_the_refusal_to_link_an_unverified_account_with_its_id(): void
    {
        Log::spy();
        $account = User::factory()->unverified()->create(['email' => self::EMAIL]);
        $this->signInWithGoogleAs(self::GOOGLE_ID, self::EMAIL);

        $this->get('/auth/google/callback');

        $this->assertRefusalLoggedOnceWith(UnverifiedAccountLinkException::class, $account->id);
    }

    #[Test]
    public function it_logs_the_refusal_of_a_different_google_identity_with_the_account_id(): void
    {
        Log::spy();
        $account = User::factory()->verified()->create(['email' => self::EMAIL, 'google_id' => 'google-other']);
        $this->signInWithGoogleAs(self::GOOGLE_ID, self::EMAIL);

        $this->get('/auth/google/callback');

        $this->assertRefusalLoggedOnceWith(GoogleIdentityMismatchException::class, $account->id);
    }

    #[Test]
    public function it_logs_the_refusal_of_a_soft_deleted_account_with_its_id(): void
    {
        Log::spy();
        $account = User::factory()->verified()->create(['email' => self::EMAIL, 'google_id' => self::GOOGLE_ID]);
        $account->delete();
        $this->signInWithGoogleAs(self::GOOGLE_ID, self::EMAIL);

        $this->get('/auth/google/callback');

        $this->assertRefusalLoggedOnceWith(DeletedAccountSignInException::class, $account->id);
    }

    #[Test]
    public function it_logs_the_refusal_of_a_google_identity_without_an_id(): void
    {
        Log::spy();
        $this->queueGoogleSignIns(GoogleUser::fake(['id' => null, 'email' => self::EMAIL, 'email_verified' => true]));

        $this->get('/auth/google/callback');

        $this->assertRefusalLoggedOnceWith(BlankGoogleIdentityException::class, 'no id');
    }
}
