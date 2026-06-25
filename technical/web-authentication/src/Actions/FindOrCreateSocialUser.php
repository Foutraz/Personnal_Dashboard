<?php

namespace Technical\WebAuthentication\Actions;

use Functional\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;

class FindOrCreateSocialUser
{
    /**
     * Resolve the local user matching the social account, linking or creating it as needed.
     */
    public function __invoke(SocialUser $socialUser): User
    {
        $existingUser = User::query()->where('email', $socialUser->getEmail())->first();

        if ($existingUser !== null) {
            if ($existingUser->google_id === null) {
                $existingUser->update(['google_id' => $socialUser->getId()]);
            }

            return $existingUser;
        }

        return User::query()->create([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $socialUser->getEmail(),
            'email' => $socialUser->getEmail(),
            'google_id' => $socialUser->getId(),
            'password' => Hash::make(Str::random(32)),
            'email_verified_at' => now(),
        ]);
    }
}
