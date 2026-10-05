<?php

namespace Tests\Feature\WebAuthentication;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Two\User as GoogleUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Actions\FindOrCreateSocialUser;
use Technical\WebAuthentication\Exceptions\BlankGoogleIdentityException;
use Technical\WebAuthentication\Exceptions\SocialSignInRefusedException;
use Tests\Feature\WebAuthentication\Concerns\FakesGoogleSignIn;
use Tests\Feature\WebAuthentication\Concerns\OpensRegistration;
use Tests\TestCase;

class GoogleBlankIdentityTest extends TestCase
{
    use FakesGoogleSignIn, OpensRegistration, RefreshDatabase;

    /**
     * @return array<string, array{mixed}>
     */
    public static function blankGoogleIds(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'spaces only' => ['   '],
        ];
    }

    private function blankGoogleUser(mixed $googleId): GoogleUser
    {
        return GoogleUser::fake([
            'id' => $googleId,
            'email' => 'victim@example.com',
            'name' => 'Google User',
            'nickname' => null,
            'email_verified' => true,
        ]);
    }

    #[Test]
    #[DataProvider('blankGoogleIds')]
    public function it_refuses_a_blank_google_id_instead_of_matching_an_unlinked_account(mixed $googleId): void
    {
        User::factory()->verified()->create(['email' => 'victim@example.com', 'google_id' => null]);

        $this->expectException(BlankGoogleIdentityException::class);

        app(FindOrCreateSocialUser::class)($this->blankGoogleUser($googleId));
    }

    #[Test]
    #[DataProvider('blankGoogleIds')]
    public function it_signs_nobody_in_when_the_callback_carries_a_blank_google_id(mixed $googleId): void
    {
        $this->allowRegistrationFor('victim@example.com');
        User::factory()->verified()->create(['email' => 'someone-else@example.com', 'google_id' => null]);
        $this->queueGoogleSignIns($this->blankGoogleUser($googleId));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email' => __('web-authentication::auth.social_link_refused')]);
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function it_gives_the_blank_identity_refusal_the_generic_social_sign_in_family(): void
    {
        $this->assertInstanceOf(SocialSignInRefusedException::class, new BlankGoogleIdentityException);
    }
}
