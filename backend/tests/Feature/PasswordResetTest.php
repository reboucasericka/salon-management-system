<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_mail_for_existing_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@salon.test',
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@salon.test',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Se existir uma conta associada a este email, receberá instruções para redefinir a palavra-passe.'
            );

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'reset@salon.test',
        ]);
    }

    public function test_forgot_password_same_response_for_unknown_email(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@salon.test',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Se existir uma conta associada a este email, receberá instruções para redefinir a palavra-passe.'
            );

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'nobody@salon.test',
        ]);
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'not-an-email',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@salon.test',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::broker()->createToken($user);

        Sanctum::actingAs($user);
        $user->createToken('old-session');
        $this->assertGreaterThan(0, $user->tokens()->count());

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@salon.test',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Palavra-passe redefinida com sucesso. Pode entrar com a nova palavra-passe.');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'reset@salon.test',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@salon.test',
            'password' => 'new-password',
        ])->assertOk();
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create([
            'email' => 'reset@salon.test',
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@salon.test',
            'token' => 'invalid-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable();
    }

    public function test_reset_password_rejects_expired_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@salon.test',
        ]);

        $token = Password::broker()->createToken($user);

        DB::table('password_reset_tokens')
            ->where('email', 'reset@salon.test')
            ->update(['created_at' => now()->subMinutes(181)]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@salon.test',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable();
    }

    public function test_reset_password_requires_confirmation(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@salon.test',
        ]);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@salon.test',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'mismatch',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
