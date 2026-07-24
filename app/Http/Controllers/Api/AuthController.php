<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower($request->string('email')->toString()),
        ]);

        $validated = $request->validate([
            'email' => 'required|string|email:rfc|max:255|unique:users,email',
            'password' => ['required', 'string', Password::min(10)->letters()->mixedCase()->numbers()],
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'avatar_url' => 'nullable|url:http,https|max:500',
        ]);

        $user = User::create([
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'full_name' => Str::squish($validated['full_name']),
            'phone' => $validated['phone'] ?? null,
            'avatar_url' => $validated['avatar_url'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente',
            'data' => ['user' => $user],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower($request->string('email')->toString()),
        ]);

        $request->validate([
            'email' => 'required|string|email:rfc|max:255',
            'password' => 'required|string|max:255',
        ]);

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        if (! $user || ! $this->passwordIsValid($user, $request->string('password')->toString())) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas',
                'data' => null,
            ], 401);
        }

        Auth::login($user);

        if ($user->is_banned) {
            Auth::logout();

            return response()->json([
                'success' => false,
                'message' => 'La cuenta no está habilitada.',
                'data' => null,
            ], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth-token', ['app:access'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'data' => ['user' => $user, 'token' => $token],
        ]);
    }

    /**
     * Accept legacy bcryptjs hashes once and immediately upgrade them to Laravel bcrypt.
     */
    private function passwordIsValid(User $user, string $plainPassword): bool
    {
        $storedHash = (string) $user->getAuthPassword();
        $isLegacyBcryptJs = str_starts_with($storedHash, '$2a$') || str_starts_with($storedHash, '$2b$');

        if ($isLegacyBcryptJs) {
            if (! password_verify($plainPassword, $storedHash)) {
                return false;
            }

            $user->forceFill([
                'password' => Hash::make($plainPassword),
            ])->save();

            return true;
        }

        try {
            return Hash::check($plainPassword, $storedHash);
        } catch (RuntimeException) {
            return false;
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente',
            'data' => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Usuario autenticado',
            'data' => ['user' => $request->user()],
        ]);
    }
}
