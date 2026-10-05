<?php

namespace Technical\WebAuthentication\Actions;

use Functional\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Two\User as GoogleUser;
use Technical\WebAuthentication\Exceptions\GoogleIdentityMismatchException;
use Technical\WebAuthentication\Exceptions\UnverifiedAccountLinkException;
use Technical\WebAuthentication\Exceptions\UnverifiedGoogleEmailException;

class FindOrCreateSocialUser
{
    /**
     * Resolve the local user matching the social account, linking or creating it as needed.
     *
     * @throws UnverifiedAccountLinkException
     * @throws GoogleIdentityMismatchException
     * @throws UnverifiedGoogleEmailException
     */
    public function __invoke(SocialUser $socialUser): User
    {
        $linkedUser = User::query()->where('google_id', $socialUser->getId())->first();

        if ($linkedUser !== null) {
            return $linkedUser;
        }

        $this->assertEmailVerifiedByGoogle($socialUser);

        $existingUser = User::query()->where('email', $socialUser->getEmail())->first();

        if ($existingUser === null) {
            return $this->createVerifiedUser($socialUser);
        }

        if ($existingUser->email_verified_at === null) {
            throw new UnverifiedAccountLinkException($existingUser->id);
        }

        if ($existingUser->google_id !== null) {
            throw new GoogleIdentityMismatchException($existingUser->id);
        }

        $existingUser->update(['google_id' => $socialUser->getId()]);

        return $existingUser;
    }

    /**
     * @throws UnverifiedGoogleEmailException
     */
    private function assertEmailVerifiedByGoogle(SocialUser $socialUser): void
    {
        $isVerified = $socialUser instanceof GoogleUser && ($socialUser->getRaw()['email_verified'] ?? false) === true;

        if (! $isVerified) {
            throw new UnverifiedGoogleEmailException((string) $socialUser->getId());
        }
    }

    private function createVerifiedUser(SocialUser $socialUser): User
    {
        $user = new User([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $socialUser->getEmail(),
            'email' => $socialUser->getEmail(),
            'google_id' => $socialUser->getId(),
            'password' => Hash::make(Str::random(32)),
        ]);

        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
