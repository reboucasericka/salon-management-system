<?php

namespace Tests\Feature;

use App\Enums\ReminderType;
use App\Enums\UserRole;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $professional;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'UTC', 'cache.default' => 'array']);

        $this->admin = User::factory()->admin()->create();
        $this->professional = User::factory()->professional()->create();
        $this->client = User::factory()->client()->create();
    }

    public function test_admin_can_view_settings_with_fallback_when_missing(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.is_persisted', false)
            ->assertJsonPath('data.effective_timezone', 'UTC')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.reminder_24h_enabled', true)
            ->assertJsonPath('data.reminder_2h_enabled', true);

        $this->assertDatabaseCount('settings', 0);
    }

    public function test_admin_can_update_settings_once_without_duplicates(): void
    {
        Sanctum::actingAs($this->admin);

        $this->patchJson('/api/v1/settings', [
            'salon_name' => 'Salão Demo',
            'timezone' => 'Europe/Lisbon',
            'currency' => 'EUR',
            'contact_email' => 'contacto@example.com',
            'contact_phone' => '+351910000000',
            'address' => 'Rua Exemplo 1',
            'reminder_24h_enabled' => true,
            'reminder_2h_enabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.salon_name', 'Salão Demo')
            ->assertJsonPath('data.timezone', 'Europe/Lisbon')
            ->assertJsonPath('data.effective_timezone', 'Europe/Lisbon')
            ->assertJsonPath('data.reminder_2h_enabled', false)
            ->assertJsonPath('data.is_persisted', true);

        $this->patchJson('/api/v1/settings', [
            'salon_name' => 'Salão Atualizado',
        ])
            ->assertOk()
            ->assertJsonPath('data.salon_name', 'Salão Atualizado');

        $this->assertDatabaseCount('settings', 1);
    }

    public function test_invalid_timezone_returns_422(): void
    {
        Sanctum::actingAs($this->admin);

        $this->patchJson('/api/v1/settings', [
            'timezone' => 'Lisbon/Invalid',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['timezone']);
    }

    public function test_valid_portugal_timezones_are_accepted(): void
    {
        Sanctum::actingAs($this->admin);

        foreach (['Europe/Lisbon', 'Atlantic/Madeira', 'Atlantic/Azores'] as $tz) {
            $this->patchJson('/api/v1/settings', ['timezone' => $tz])
                ->assertOk()
                ->assertJsonPath('data.timezone', $tz)
                ->assertJsonPath('data.effective_timezone', $tz);
        }
    }

    public function test_professional_and_client_cannot_update_settings(): void
    {
        Sanctum::actingAs($this->professional);
        $this->patchJson('/api/v1/settings', ['salon_name' => 'X'])->assertForbidden();
        $this->getJson('/api/v1/settings')->assertForbidden();

        Sanctum::actingAs($this->client);
        $this->patchJson('/api/v1/settings', ['salon_name' => 'Y'])->assertForbidden();
        $this->getJson('/api/v1/settings')->assertForbidden();
    }

    public function test_public_settings_exposes_only_safe_fields(): void
    {
        Setting::create([
            'salon_name' => 'Salão Público',
            'timezone' => 'Europe/Lisbon',
            'currency' => 'EUR',
            'contact_email' => 'hello@example.com',
            'contact_phone' => '910000000',
            'address' => 'Coimbra',
            'reminder_24h_enabled' => false,
            'reminder_2h_enabled' => false,
        ]);

        $response = $this->getJson('/api/v1/public/settings')
            ->assertOk()
            ->assertJsonPath('data.salon_name', 'Salão Público')
            ->assertJsonPath('data.contact_email', 'hello@example.com')
            ->assertJsonMissingPath('data.reminder_24h_enabled')
            ->assertJsonMissingPath('data.reminder_2h_enabled')
            ->assertJsonMissingPath('data.is_persisted');

        $keys = array_keys($response->json('data'));
        sort($keys);
        $this->assertSame(
            ['address', 'contact_email', 'contact_phone', 'currency', 'salon_name', 'timezone'],
            $keys,
        );
    }

    public function test_reminder_flags_from_settings_are_respected(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00', 'UTC'));

        Setting::create([
            'reminder_24h_enabled' => false,
            'reminder_2h_enabled' => true,
            'timezone' => 'UTC',
            'currency' => 'EUR',
        ]);
        app(SettingsService::class)->forgetCache();

        $service = Service::create([
            'name' => 'Corte',
            'description' => 'Corte',
            'price' => 25,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $proUser = User::create([
            'first_name' => 'Ana',
            'last_name' => 'Pro',
            'email' => 'ana-settings@salon.test',
            'role' => UserRole::Professional,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $professional = Professional::create([
            'user_id' => $proUser->id,
            'name' => 'Ana Pro',
            'specialty' => 'Cabeleireira',
            'email' => 'ana-settings@salon.test',
            'is_active' => true,
        ]);

        $client = Client::create([
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'is_active' => true,
        ]);

        Appointment::create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'start_time' => now()->addHours(24)->addMinutes(30),
            'end_time' => now()->addHours(25)->addMinutes(30),
            'status' => Appointment::STATUS_CONFIRMED,
            'total_price' => 25,
            'is_active' => true,
        ]);

        Appointment::create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'start_time' => now()->addHours(2)->addMinutes(30),
            'end_time' => now()->addHours(3)->addMinutes(30),
            'status' => Appointment::STATUS_CONFIRMED,
            'total_price' => 25,
            'is_active' => true,
        ]);

        Artisan::call('appointments:send-reminders');

        Mail::assertSent(AppointmentReminderMail::class, 1);
        Mail::assertSent(
            AppointmentReminderMail::class,
            fn (AppointmentReminderMail $mail) => $mail->reminderType === ReminderType::TwoHours,
        );
        Mail::assertNotSent(
            AppointmentReminderMail::class,
            fn (AppointmentReminderMail $mail) => $mail->reminderType === ReminderType::TwentyFourHours,
        );

        Carbon::setTestNow();
    }

    public function test_settings_timezone_is_used_by_reminder_windows(): void
    {
        Mail::fake();

        Setting::create([
            'timezone' => 'Europe/Lisbon',
            'currency' => 'EUR',
            'reminder_24h_enabled' => true,
            'reminder_2h_enabled' => false,
        ]);
        app(SettingsService::class)->forgetCache();

        $this->assertSame('Europe/Lisbon', app(SettingsService::class)->timezone());

        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00', 'UTC'));

        $service = Service::create([
            'name' => 'Corte',
            'description' => 'Corte',
            'price' => 25,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
        $proUser = User::factory()->professional()->create();
        $professional = Professional::create([
            'user_id' => $proUser->id,
            'name' => 'Ana',
            'specialty' => 'Cabeleireira',
            'email' => 'ana2@salon.test',
            'is_active' => true,
        ]);
        $client = Client::create([
            'name' => 'Cliente TZ',
            'email' => 'tz@example.com',
            'is_active' => true,
        ]);

        $windowStart = Carbon::now('Europe/Lisbon')->addHours(24)->utc();
        $start = $windowStart->copy()->addMinutes(30);

        Appointment::create([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
            'total_price' => 25,
            'is_active' => true,
        ]);

        Artisan::call('appointments:send-reminders');

        Mail::assertSent(AppointmentReminderMail::class, 1);

        Carbon::setTestNow();
    }
}
