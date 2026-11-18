<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_own_profile(): void
    {
        $user = User::factory()->admin()->create([
            'first_name' => 'Admin',
            'last_name' => 'Sistema',
            'email' => 'admin@salon.test',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@salon.test')
            ->assertJsonPath('user.first_name', 'Admin')
            ->assertJsonMissingPath('user.password');
    }

    public function test_guest_cannot_fetch_profile(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->admin()->create([
            'first_name' => 'Admin',
            'last_name' => 'Sistema',
            'email' => 'admin@salon.test',
            'phone' => '900000000',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/auth/profile', [
            'first_name' => 'Ana',
            'last_name' => 'Silva',
            'email' => 'ana.silva@salon.test',
            'phone' => '912345678',
        ])
            ->assertOk()
            ->assertJsonPath('user.first_name', 'Ana')
            ->assertJsonPath('user.last_name', 'Silva')
            ->assertJsonPath('user.email', 'ana.silva@salon.test')
            ->assertJsonPath('user.phone', '912345678')
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Ana',
            'email' => 'ana.silva@salon.test',
        ]);
    }

    public function test_duplicate_email_returns_validation_error(): void
    {
        User::factory()->admin()->create([
            'email' => 'taken@salon.test',
        ]);

        $user = User::factory()->admin()->create([
            'email' => 'admin@salon.test',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/auth/profile', [
            'first_name' => 'Admin',
            'last_name' => 'Sistema',
            'email' => 'taken@salon.test',
            'phone' => null,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_email_is_normalized_on_profile_update(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@salon.test',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/auth/profile', [
            'first_name' => 'Admin',
            'last_name' => 'Sistema',
            'email' => '  Admin.New@Salon.Test ',
            'phone' => null,
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'admin.new@salon.test');
    }

    public function test_incorrect_current_password_is_rejected(): void
    {
        $user = User::factory()->admin()->create([
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $user->refresh();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_password_can_be_updated_with_correct_current_password(): void
    {
        $user = User::factory()->admin()->create([
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'password123',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])
            ->assertOk()
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('user.password');

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword', $user->password));
        $this->assertFalse(Hash::check('password123', $user->password));
    }

    public function test_guest_cannot_update_profile_or_password(): void
    {
        $this->patchJson('/api/v1/auth/profile', [
            'first_name' => 'X',
            'last_name' => 'Y',
            'email' => 'x@salon.test',
        ])->assertUnauthorized();

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'password123',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertUnauthorized();
    }
}
