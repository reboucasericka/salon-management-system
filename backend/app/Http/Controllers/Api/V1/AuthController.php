<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'first_name' => $request->string('first_name')->toString(),
            'last_name' => $request->string('last_name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone' => $request->input('phone'),
            'role' => UserRole::Client,
            'password' => $request->string('password')->toString(),
        ]);

        Client::create([
            'user_id' => $user->id,
            'name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);

        $deviceName = $request->string('device_name')->toString() ?: 'api-client';
        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load('clientProfile');

        return response()->json([
            'message' => 'Registo efetuado com sucesso.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Conta desativada.'],
            ]);
        }

        $deviceName = $request->string('device_name')->toString() ?: 'api-client';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => 'Login efetuado com sucesso.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('clientProfile');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->fill($request->only(['first_name', 'last_name', 'email', 'phone']));
        $user->save();
        $user->load('clientProfile');

        return response()->json([
            'message' => 'Perfil atualizado com sucesso.',
            'user' => new UserResource($user),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['A palavra-passe atual está incorreta.'],
            ]);
        }

        $user->password = $request->string('password')->toString();
        $user->save();

        return response()->json([
            'message' => 'Palavra-passe atualizada com sucesso.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sessão terminada com sucesso.',
        ]);
    }

    /**
     * Resposta genérica (anti user-enumeration), alinhada ao legado.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'message' => 'Se existir uma conta associada a este email, receberá instruções para redefinir a palavra-passe.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
                'token' => ['O link de redefinição é inválido ou expirou.'],
            ]);
        }

        return response()->json([
            'message' => 'Palavra-passe redefinida com sucesso. Pode entrar com a nova palavra-passe.',
        ]);
    }
}
