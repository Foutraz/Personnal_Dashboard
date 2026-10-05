<?php

namespace Tests\Feature\WebAuthentication\Concerns;

use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;

trait FakesGoogleSignIn
{
    protected function googleUser(string $googleId, string $email, bool $emailVerified = true): SocialUser
    {
        return GoogleUser::fake([
            'id' => $googleId,
            'email' => $email,
            'name' => 'Google User',
            'nickname' => null,
            'email_verified' => $emailVerified,
        ]);
    }

    protected function signInWithGoogleAs(string $googleId, string $email, bool $emailVerified = true): void
    {
        $this->queueGoogleSignIns($this->googleUser($googleId, $email, $emailVerified));
    }

    protected function queueGoogleSignIns(SocialUser ...$socialUsers): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn(...$socialUsers);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
