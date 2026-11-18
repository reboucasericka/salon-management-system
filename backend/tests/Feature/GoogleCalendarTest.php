<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Enums\GoogleSyncStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\GoogleCalendarConnection;
use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\AppointmentGoogleSyncService;
use App\Services\GoogleCalendarService;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class GoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    private Professional $professional;

    private User $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-10 08:00:00', 'UTC'));

        config([
            'google.client_id' => 'test-client-id',
            'google.client_secret' => 'test-client-secret',
            'google.redirect_uri' => 'http://127.0.0.1:8000/google-calendar/callback',
        ]);

        Setting::query()->create([
            'salon_name' => 'Salão Teste',
            'timezone' => 'Europe/Lisbon',
            'currency' => 'EUR',
            'reminder_24h_enabled' => true,
            'reminder_2h_enabled' => true,
        ]);
        app(SettingsService::class)->forgetCache();

        $this->admin = User::factory()->admin()->create();

        $this->service = Service::create([
            'name' => 'Corte',
            'description' => 'Corte',
            'price' => 25,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $professionalUser = User::create([
            'first_name' => 'Ana',
            'last_name' => 'Profissional',
            'email' => 'ana-google@salon.test',
            'role' => UserRole::Professional,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->professional = Professional::create([
            'user_id' => $professionalUser->id,
            'name' => 'Ana Profissional',
            'specialty' => 'Cabeleireira',
            'email' => 'ana-google@salon.test',
            'is_active' => true,
        ]);
        $this->professional->services()->attach($this->service->id);

        foreach ([DayOfWeek::Monday, DayOfWeek::Tuesday] as $day) {
            ProfessionalSchedule::factory()
                ->forProfessional($this->professional)
                ->onDay($day)
                ->window('09:00', '18:00')
                ->create();
        }

        $this->client = Client::create([
            'name' => 'Cliente Google',
            'email' => 'cliente-google@example.com',
            'phone' => '910000000',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function activateConnection(): GoogleCalendarConnection
    {
        return GoogleCalendarConnection::query()->create([
            'connected_by' => $this->admin->id,
            'google_account_email' => 'salon@gmail.com',
            'calendar_id' => 'primary',
            'calendar_name' => 'Principal',
            'access_token' => 'access-token-secret',
            'refresh_token' => 'refresh-token-secret',
            'token_expires_at' => now()->addHour(),
            'scopes' => config('google.scopes'),
            'is_active' => true,
        ]);
    }

    public function test_admin_can_read_status_without_tokens(): void
    {
        $this->activateConnection();
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/google-calendar/status')
            ->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.google_account_email', 'salon@gmail.com');

        $json = $response->json('data');
        $this->assertArrayNotHasKey('access_token', $json);
        $this->assertArrayNotHasKey('refresh_token', $json);
    }

    public function test_professional_and_client_cannot_manage_google_calendar(): void
    {
        $professional = User::factory()->professional()->create();
        $client = User::factory()->client()->create();

        Sanctum::actingAs($professional);
        $this->getJson('/api/v1/google-calendar/status')->assertForbidden();
        $this->getJson('/api/v1/google-calendar/connect')->assertForbidden();

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/google-calendar/status')->assertForbidden();
        $this->deleteJson('/api/v1/google-calendar/disconnect')->assertForbidden();
    }

    public function test_connect_returns_authorization_url_with_state(): void
    {
        $mock = Mockery::mock(GoogleCalendarService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('createOAuthState')->once()->with($this->admin->id)->andReturn('state-test-123');
        $mock->shouldReceive('getAuthUrl')->with('state-test-123')->andReturn('https://accounts.google.com/o/oauth2/auth?state=state-test-123');
        $this->app->instance(GoogleCalendarService::class, $mock);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/google-calendar/connect')
            ->assertOk()
            ->assertJsonPath('data.authorization_url', 'https://accounts.google.com/o/oauth2/auth?state=state-test-123');
    }

    public function test_create_appointment_syncs_google_event(): void
    {
        $this->activateConnection();

        $google = Mockery::mock(GoogleCalendarService::class);
        $google->shouldReceive('isConfigured')->andReturn(true);
        $google->shouldReceive('activeConnection')->andReturn(GoogleCalendarConnection::query()->first());
        $google->shouldReceive('createOrUpdateEvent')->once()->andReturn('evt_created_1');
        $this->app->instance(GoogleCalendarService::class, $google);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/appointments', [
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::parse('2026-08-11 10:00:00')->toIso8601String(),
            'status' => 'pending',
        ])->assertCreated()
            ->assertJsonPath('data.google_sync_status', GoogleSyncStatus::Synced->value)
            ->assertJsonPath('data.google_event_id', 'evt_created_1');

        $this->assertDatabaseHas('appointments', [
            'google_event_id' => 'evt_created_1',
            'google_sync_status' => GoogleSyncStatus::Synced->value,
        ]);
    }

    public function test_google_failure_does_not_rollback_appointment(): void
    {
        $this->activateConnection();

        $google = Mockery::mock(GoogleCalendarService::class);
        $google->shouldReceive('isConfigured')->andReturn(true);
        $google->shouldReceive('activeConnection')->andReturn(GoogleCalendarConnection::query()->first());
        $google->shouldReceive('createOrUpdateEvent')->once()->andThrow(new \RuntimeException('Google API down'));
        $this->app->instance(GoogleCalendarService::class, $google);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/appointments', [
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::parse('2026-08-11 11:00:00')->toIso8601String(),
        ])->assertCreated();

        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseHas('appointments', [
            'google_sync_status' => GoogleSyncStatus::Failed->value,
        ]);
    }

    public function test_update_reuses_event_id_idempotent(): void
    {
        $this->activateConnection();

        $appointment = Appointment::create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::parse('2026-08-11 12:00:00'),
            'end_time' => Carbon::parse('2026-08-11 13:00:00'),
            'status' => Appointment::STATUS_PENDING,
            'total_price' => 25,
            'is_active' => true,
        ]);
        $appointment->forceFill([
            'google_event_id' => 'evt_existing',
            'google_sync_status' => GoogleSyncStatus::Synced->value,
        ])->save();

        $google = Mockery::mock(GoogleCalendarService::class);
        $google->shouldReceive('isConfigured')->andReturn(true);
        $google->shouldReceive('activeConnection')->andReturn(GoogleCalendarConnection::query()->first());
        $google->shouldReceive('createOrUpdateEvent')
            ->once()
            ->with(Mockery::on(fn (Appointment $a) => $a->google_event_id === 'evt_existing'))
            ->andReturn('evt_existing');
        $this->app->instance(GoogleCalendarService::class, $google);

        Sanctum::actingAs($this->admin);

        $this->putJson('/api/v1/appointments/'.$appointment->id, [
            'start_time' => Carbon::parse('2026-08-11 14:00:00')->toIso8601String(),
        ])->assertOk()
            ->assertJsonPath('data.google_event_id', 'evt_existing');
    }

    public function test_cancel_deletes_google_event(): void
    {
        $this->activateConnection();

        $appointment = Appointment::create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::parse('2026-08-11 15:00:00'),
            'end_time' => Carbon::parse('2026-08-11 16:00:00'),
            'status' => Appointment::STATUS_CONFIRMED,
            'total_price' => 25,
            'is_active' => true,
        ]);
        $appointment->forceFill([
            'google_event_id' => 'evt_to_delete',
            'google_sync_status' => GoogleSyncStatus::Synced->value,
        ])->save();

        $google = Mockery::mock(GoogleCalendarService::class);
        $google->shouldReceive('isConfigured')->andReturn(true);
        $google->shouldReceive('activeConnection')->andReturn(GoogleCalendarConnection::query()->first());
        $google->shouldReceive('deleteEvent')->once()->andReturnNull();
        $this->app->instance(GoogleCalendarService::class, $google);

        Sanctum::actingAs($this->admin);

        $this->deleteJson('/api/v1/appointments/'.$appointment->id)->assertOk();

        $appointment->refresh();
        $this->assertNull($appointment->google_event_id);
        $this->assertSame(GoogleSyncStatus::NotApplicable->value, $appointment->google_sync_status);
        $this->assertSame(Appointment::STATUS_CANCELED, $appointment->status);
    }

    public function test_retry_command_is_idempotent(): void
    {
        $this->activateConnection();

        $appointment = Appointment::create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::parse('2026-08-11 16:00:00'),
            'end_time' => Carbon::parse('2026-08-11 17:00:00'),
            'status' => Appointment::STATUS_PENDING,
            'total_price' => 25,
            'is_active' => true,
        ]);
        $appointment->forceFill([
            'google_sync_status' => GoogleSyncStatus::Failed->value,
            'google_sync_error' => 'temporary',
        ])->save();

        $google = Mockery::mock(GoogleCalendarService::class);
        $google->shouldReceive('isConfigured')->andReturn(true);
        $google->shouldReceive('activeConnection')->andReturn(GoogleCalendarConnection::query()->first());
        $google->shouldReceive('createOrUpdateEvent')->once()->andReturn('evt_retry');
        $this->app->instance(GoogleCalendarService::class, $google);

        Artisan::call('appointments:sync-google');

        $appointment->refresh();
        $this->assertSame('evt_retry', $appointment->google_event_id);
        $this->assertSame(GoogleSyncStatus::Synced->value, $appointment->google_sync_status);

        // Segundo retry sem pending/failed → 0 createOrUpdate
        Artisan::call('appointments:sync-google');
    }

    public function test_map_event_uses_settings_timezone(): void
    {
        $appointment = Appointment::create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::parse('2026-08-11 10:00:00', 'UTC'),
            'end_time' => Carbon::parse('2026-08-11 11:00:00', 'UTC'),
            'status' => Appointment::STATUS_PENDING,
            'total_price' => 25,
            'is_active' => true,
        ]);

        $event = app(GoogleCalendarService::class)->mapAppointmentToEvent($appointment);

        $this->assertSame('Europe/Lisbon', $event['start']['timeZone']);
        $this->assertSame('Europe/Lisbon', $event['end']['timeZone']);
        $this->assertStringContainsString('Corte', $event['summary']);
        $this->assertSame((string) $appointment->id, $event['extendedProperties']['private']['appointmentId']);
    }

    public function test_tokens_encrypted_at_rest(): void
    {
        $connection = $this->activateConnection();
        $raw = \Illuminate\Support\Facades\DB::table('google_calendar_connections')
            ->where('id', $connection->id)
            ->first();

        $this->assertNotSame('access-token-secret', $raw->access_token);
        $this->assertNotSame('refresh-token-secret', $raw->refresh_token);
        $this->assertSame('access-token-secret', $connection->fresh()->access_token);
    }

    public function test_disconnected_sync_marks_not_applicable(): void
    {
        $appointment = Appointment::create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::parse('2026-08-11 17:00:00'),
            'end_time' => Carbon::parse('2026-08-11 18:00:00'),
            'status' => Appointment::STATUS_PENDING,
            'total_price' => 25,
            'is_active' => true,
        ]);

        app(AppointmentGoogleSyncService::class)->sync($appointment);
        $appointment->refresh();

        $this->assertSame(GoogleSyncStatus::NotApplicable->value, $appointment->google_sync_status);
    }

    public function test_public_booking_triggers_sync(): void
    {
        $this->activateConnection();

        $google = Mockery::mock(GoogleCalendarService::class);
        $google->shouldReceive('isConfigured')->andReturn(true);
        $google->shouldReceive('activeConnection')->andReturn(GoogleCalendarConnection::query()->first());
        $google->shouldReceive('createOrUpdateEvent')->once()->andReturn('evt_public');
        $this->app->instance(GoogleCalendarService::class, $google);

        $this->postJson('/api/v1/public/appointments', [
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'date' => '2026-08-11',
            'time' => '10:00',
            'client_name' => 'Guest',
            'client_email' => 'guest-google@example.com',
        ])->assertCreated();

        $this->assertDatabaseHas('appointments', [
            'google_event_id' => 'evt_public',
            'google_sync_status' => GoogleSyncStatus::Synced->value,
        ]);
    }
}
