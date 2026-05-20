<?php

namespace Technical\Authentication\Http\Controllers;

use Functional\Users\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Technical\Authentication\Http\Requests\LoginRequest;

class AuthenticationController
{
    /**
     * Create a new AuthController instance.
     */
    public function __construct() {}

    /**
     * Connexion and getting a JWT Token
     *
     * @throws AuthenticationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            throw new AuthenticationException('User not found');
        }

        $credentials = [
            'email' => $user->email,
            'password' => $validated['password'],
        ];

        if (! $token = Auth('api')->attempt($credentials)) {
            throw new AuthenticationException('Unable to authenticate.');
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get authenticated user
     */
    public function me(): JsonResponse
    {
        return response()->json(auth()->user());
    }

    /**
     * Disconnect (disable token)
     */
    public function logout(): JsonResponse
    {
        auth()->logout();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Refresh token
     */
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Create the response with token
     */
    protected function respondWithToken($token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => auth()->user(),
        ]);
    }
}
