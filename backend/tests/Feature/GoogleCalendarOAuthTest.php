<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoogleCalendarOAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => 'http://127.0.0.1:5173',
            'google.client_id' => 'test-client-id',
            'google.client_secret' => 'test-client-secret',
            'google.redirect_uri' => 'http://127.0.0.1:8000/google-calendar/callback',
            'google.settings_path' => '/panel/admin/settings',
        ]);

        $this->admin = User::factory()->admin()->create();
    }

    public function test_connect_generates_signed_state_in_authorization_url(): void
    {
        Sanctum::actingAs($this->admin);

        $url = $this->getJson('/api/v1/google-calendar/connect')
            ->assertOk()
            ->json('data.authorization_url');

        $state = $this->stateFromAuthUrl($url);
        $this->assertNotSame('', $state);
        $this->assertTrue(app(GoogleCalendarService::class)->isValidOAuthState($state));
        $this->assertStringContainsString('state=', $url);
    }

    public function test_state_nonce_persists_until_callback(): void
    {
        Sanctum::actingAs($this->admin);

        $url = $this->getJson('/api/v1/google-calendar/connect')
            ->assertOk()
            ->json('data.authorization_url');
        $state = $this->stateFromAuthUrl($url);

        $google = app(GoogleCalendarService::class);
        $this->assertTrue($google->isValidOAuthState($state));
        $this->assertSame($this->admin->id, $google->consumeOAuthState($state)['user_id'] ?? null);
        $this->assertNull($google->consumeOAuthState($state));
    }

    public function test_callback_with_valid_state_is_accepted_and_redirects_to_frontend(): void
    {
        Sanctum::actingAs($this->admin);
        $state = $this->connectAndGetState();

        $this->partialMock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('handleCallback')->once()->andReturn(new GoogleCalendarConnection);
        });

        $this->get('/google-calendar/callback?state='.urlencode($state).'&code=google-auth-code')
            ->assertRedirect('http://127.0.0.1:5173/panel/admin/settings?google=connected');
    }

    public function test_callback_with_invalid_state_is_rejected_to_frontend(): void
    {
        Sanctum::actingAs($this->admin);
        $this->connectAndGetState();

        $this->get('/google-calendar/callback?state=tampered-state&code=google-auth-code')
            ->assertRedirect('http://127.0.0.1:5173/panel/admin/settings?google=invalid_state');
    }

    public function test_state_cannot_be_reused(): void
    {
        Sanctum::actingAs($this->admin);
        $state = $this->connectAndGetState();

        $this->partialMock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('handleCallback')->once()->andReturn(new GoogleCalendarConnection);
        });

        $query = '/google-calendar/callback?state='.urlencode($state).'&code=google-auth-code';
        $this->get($query)->assertRedirect('http://127.0.0.1:5173/panel/admin/settings?google=connected');
        $this->get($query)->assertRedirect('http://127.0.0.1:5173/panel/admin/settings?google=invalid_state');
    }

    public function test_token_exchange_failure_redirects_to_frontend_error(): void
    {
        Sanctum::actingAs($this->admin);
        $state = $this->connectAndGetState();

        $this->partialMock(GoogleCalendarService::class, function ($mock) {
            $mock->shouldReceive('handleCallback')->once()->andThrow(new \RuntimeException('token fail'));
        });

        $this->get('/google-calendar/callback?state='.urlencode($state).'&code=google-auth-code')
            ->assertRedirect('http://127.0.0.1:5173/panel/admin/settings?google=error');
    }

    public function test_callback_does_not_redirect_to_backend_origin(): void
    {
        $response = $this->get('/google-calendar/callback?state=bad&code=abc');

        $response->assertRedirect('http://127.0.0.1:5173/panel/admin/settings?google=invalid_state');
        $this->assertStringNotContainsString(
            'http://127.0.0.1:8000',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_unsigned_state_is_rejected(): void
    {
        Sanctum::actingAs($this->admin);
        $this->connectAndGetState();

        $this->get('/google-calendar/callback?state=not-a-signed-payload&code=google-auth-code')
            ->assertRedirect('http://127.0.0.1:5173/panel/admin/settings?google=invalid_state');
    }

    public function test_array_state_is_rejected(): void
    {
        $this->get('/google-calendar/callback?state[]=a&state[]=b&code=google-auth-code')
            ->assertRedirect('http://127.0.0.1:5173/panel/admin/settings?google=invalid_state');
    }

    public function test_tokens_remain_encrypted_at_rest(): void
    {
        $connection = GoogleCalendarConnection::query()->create([
            'connected_by' => $this->admin->id,
            'google_account_email' => 'salon@gmail.com',
            'calendar_id' => 'primary',
            'access_token' => 'access-token-secret',
            'refresh_token' => 'refresh-token-secret',
            'token_expires_at' => now()->addHour(),
            'scopes' => config('google.scopes'),
            'is_active' => true,
        ]);

        $raw = DB::table('google_calendar_connections')->where('id', $connection->id)->first();

        $this->assertNotSame('access-token-secret', $raw->access_token);
        $this->assertNotSame('refresh-token-secret', $raw->refresh_token);
        $this->assertSame('access-token-secret', $connection->fresh()->access_token);
    }

    public function test_professional_cannot_start_oauth(): void
    {
        $professional = User::create([
            'first_name' => 'Pro',
            'last_name' => 'User',
            'email' => 'pro-oauth@salon.test',
            'role' => UserRole::Professional,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        Sanctum::actingAs($professional);
        $this->getJson('/api/v1/google-calendar/connect')->assertForbidden();
    }

    private function connectAndGetState(): string
    {
        $url = $this->getJson('/api/v1/google-calendar/connect')
            ->assertOk()
            ->json('data.authorization_url');

        return $this->stateFromAuthUrl($url);
    }

    private function stateFromAuthUrl(string $url): string
    {
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertIsString($query['state'] ?? null);

        return (string) $query['state'];
    }
}
