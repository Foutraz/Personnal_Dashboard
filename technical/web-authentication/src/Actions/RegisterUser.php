<?php

namespace Technical\WebAuthentication\Actions;

use Functional\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Technical\WebAuthentication\Exceptions\EmailAlreadyTakenException;

class RegisterUser
{
    /**
     * Create a new user from registration data with a hashed password.
     *
     * @param  array{name: string, email: string, password: string}  $data
     *
     * @throws EmailAlreadyTakenException
     */
    public function __invoke(array $data): User
    {
        if (User::query()->where('email', $data['email'])->exists()) {
            throw new EmailAlreadyTakenException;
        }

        return User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }
}
