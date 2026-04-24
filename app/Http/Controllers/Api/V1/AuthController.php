<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], (bool) ($credentials['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'email' => ['Nieprawidlowy email lub haslo.'],
            ]);
        }

        $request->session()->regenerate();

        return $this->success([
            'user' => $request->user()?->loadMissing('roles:id,name'),
        ], 'Zalogowano pomyslnie.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success([
            'user' => $request->user()?->loadMissing('roles:id,name'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->success(null, 'Wylogowano pomyslnie.');
    }

    public function token(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:255'],
        ]);

        $token = $request->user()->createToken(
            $validated['name'],
            $validated['abilities'] ?? ['*']
        );

        return $this->success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $validated['abilities'] ?? ['*'],
        ], 'Token wygenerowany.', 201);
    }
}
