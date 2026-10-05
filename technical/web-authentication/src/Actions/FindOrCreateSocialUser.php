<?php

namespace Technical\WebAuthentication\Actions;

use Functional\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;
use Technical\WebAuthentication\Exceptions\UnverifiedAccountLinkException;

class FindOrCreateSocialUser
{
    /**
     * Resolve the local user matching the social account, linking or creating it as needed.
     *
     * @throws UnverifiedAccountLinkException
     */
    public function __invoke(SocialUser $socialUser): User
    {
        $linkedUser = User::query()->where('google_id', $socialUser->getId())->first();

        if ($linkedUser !== null) {
            return $linkedUser;
        }

        $existingUser = User::query()->where('email', $socialUser->getEmail())->first();

        if ($existingUser === null) {
            return $this->createVerifiedUser($socialUser);
        }

        if ($existingUser->email_verified_at === null) {
            throw new UnverifiedAccountLinkException($existingUser->id);
        }

        if ($existingUser->google_id === null) {
            $existingUser->update(['google_id' => $socialUser->getId()]);
        }

        return $existingUser;
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
