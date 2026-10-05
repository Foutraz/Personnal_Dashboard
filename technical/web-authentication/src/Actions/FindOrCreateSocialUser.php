<?php

namespace Technical\WebAuthentication\Actions;

use Functional\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Two\User as GoogleUser;
use Technical\WebAuthentication\Exceptions\DeletedAccountSignInException;
use Technical\WebAuthentication\Exceptions\GoogleIdentityMismatchException;
use Technical\WebAuthentication\Exceptions\UnlistedGoogleEmailException;
use Technical\WebAuthentication\Exceptions\UnverifiedAccountLinkException;
use Technical\WebAuthentication\Exceptions\UnverifiedGoogleEmailException;
use Technical\WebAuthentication\Services\RegistrationAllowList;

class FindOrCreateSocialUser
{
    public function __construct(private readonly RegistrationAllowList $registrationAllowList) {}

    /**
     * Resolve the local user matching the social account, linking or creating it as needed.
     *
     * @throws UnverifiedAccountLinkException
     * @throws GoogleIdentityMismatchException
     * @throws UnverifiedGoogleEmailException
     * @throws DeletedAccountSignInException
     * @throws UnlistedGoogleEmailException
     */
    public function __invoke(SocialUser $socialUser): User
    {
        $linkedUser = User::query()->withTrashed()->where('google_id', $socialUser->getId())->first();

        if ($linkedUser !== null) {
            $this->assertNotDeleted($linkedUser);

            return $linkedUser;
        }

        $this->assertEmailVerifiedByGoogle($socialUser);

        $existingUser = User::query()->withTrashed()->where('email', $socialUser->getEmail())->first();

        if ($existingUser === null) {
            $this->assertRegistrationAllowed($socialUser);

            return $this->createVerifiedUser($socialUser);
        }

        $this->assertNotDeleted($existingUser);

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
     * Ensure Google itself vouches for the email of the signing-in identity.
     *
     * @throws UnverifiedGoogleEmailException
     */
    private function assertEmailVerifiedByGoogle(SocialUser $socialUser): void
    {
        $isVerified = $socialUser instanceof GoogleUser && ($socialUser->getRaw()['email_verified'] ?? false) === true;

        if (! $isVerified) {
            throw new UnverifiedGoogleEmailException((string) $socialUser->getId());
        }
    }

    /**
     * Ensure a brand new account may be opened for the email of the signing-in identity.
     *
     * @throws UnlistedGoogleEmailException
     */
    private function assertRegistrationAllowed(SocialUser $socialUser): void
    {
        if (! $this->registrationAllowList->permits((string) $socialUser->getEmail())) {
            throw new UnlistedGoogleEmailException((string) $socialUser->getId());
        }
    }

    /**
     * Ensure the matching account has not been soft-deleted.
     *
     * @throws DeletedAccountSignInException
     */
    private function assertNotDeleted(User $user): void
    {
        if ($user->trashed()) {
            throw new DeletedAccountSignInException($user->id);
        }
    }

    /**
     * Create the local user for a new Google identity with its email marked as verified.
     */
    private function createVerifiedUser(SocialUser $socialUser): User
    {
        $user = new User([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $socialUser->getEmail(),
            'email' => Str::lower(trim((string) $socialUser->getEmail())),
            'google_id' => $socialUser->getId(),
            'password' => Hash::make(Str::random(32)),
        ]);

        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
