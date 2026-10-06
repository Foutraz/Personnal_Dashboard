<?php

namespace Technical\Authentication\Http\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Technical\Authentication\Http\Requests\LoginRequest;
use Tymon\JWTAuth\JWTGuard;

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

        /** @var JWTGuard $guard */
        $guard = auth('api');

        $token = $guard->attempt(['email' => $validated['email'], 'password' => $validated['password']]);

        if (! is_string($token)) {
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
        /** @var JWTGuard $guard */
        $guard = auth('api');

        return $this->respondWithToken($guard->refresh());
    }

    /**
     * Create the response with token
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $guard->factory()->getTTL() * 60,
            'user' => $guard->user(),
        ]);
    }
}
