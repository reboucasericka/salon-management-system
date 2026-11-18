<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Integração Google Calendar (OAuth + REST API).
 *
 * Source of truth: appointments Laravel.
 * Direção: Laravel → Google (outbound).
 * Inbound (Google → appointments): NÃO nesta fase.
 *
 * Usa Google\Client + HTTP REST (sem google/apiclient-services),
 * para evitar o pacote monolítico problemático em Windows.
 */
class GoogleCalendarService
{
    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';

    public function __construct(private readonly SettingsService $settings) {}

    public function isConfigured(): bool
    {
        return filled(config('google.client_id')) && filled(config('google.client_secret'));
    }

    public function connection(): ?GoogleCalendarConnection
    {
        return GoogleCalendarConnection::query()->latest('id')->first();
    }

    public function activeConnection(): ?GoogleCalendarConnection
    {
        $connection = $this->connection();

        return $connection?->isReady() ? $connection : null;
    }

    public function makeClient(?GoogleCalendarConnection $connection = null): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('google.client_id'));
        $client->setClientSecret((string) config('google.client_secret'));
        $client->setRedirectUri((string) config('google.redirect_uri'));
        $client->setAccessType((string) config('google.access_type', 'offline'));
        $client->setPrompt((string) config('google.prompt', 'consent'));
        $client->setScopes(config('google.scopes', []));
        $client->setIncludeGrantedScopes(true);

        if ($connection?->access_token || $connection?->refresh_token) {
            $token = array_filter([
                'access_token' => $connection->access_token,
                'refresh_token' => $connection->refresh_token,
                'expires_in' => $connection->token_expires_at
                    ? max(0, $connection->token_expires_at->getTimestamp() - time())
                    : null,
                'created' => $connection->token_expires_at
                    ? $connection->token_expires_at->getTimestamp() - 3600
                    : time(),
            ], fn ($v) => $v !== null && $v !== '');

            $client->setAccessToken($token);

            if ($client->isAccessTokenExpired() && $connection->refresh_token) {
                $newToken = $client->fetchAccessTokenWithRefreshToken($connection->refresh_token);
                if (isset($newToken['error'])) {
                    throw new \RuntimeException('Falha ao renovar token Google.');
                }
                $this->persistToken($connection, $newToken, preserveRefresh: true);
                $client->setAccessToken(array_merge($token, $newToken));
            }
        }

        return $client;
    }

    public function getAuthUrl(string $state): string
    {
        $client = $this->makeClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * @param  array<string, mixed>  $token
     */
    public function handleCallback(string $code, User $admin): GoogleCalendarConnection
    {
        $client = $this->makeClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('Autorização Google inválida.');
        }

        $client->setAccessToken($token);

        $connection = $this->connection() ?? new GoogleCalendarConnection;
        $connection->connected_by = $admin->id;
        $connection->google_account_email = $this->resolveAccountEmail($client, $token);
        $connection->scopes = config('google.scopes');
        $connection->is_active = false;
        $this->persistToken($connection, $token, preserveRefresh: false);
        $connection->save();

        return $connection->fresh();
    }

    /**
     * @return list<array{id: string, name: string, primary: bool}>
     */
    public function listCalendars(GoogleCalendarConnection $connection): array
    {
        $payload = $this->apiGet($connection, '/users/me/calendarList', ['maxResults' => 50]);
        $items = $payload['items'] ?? [];

        $calendars = [];
        foreach ($items as $item) {
            $calendars[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['summary'] ?? $item['id'] ?? ''),
                'primary' => (bool) ($item['primary'] ?? false),
            ];
        }

        return array_values(array_filter($calendars, fn ($c) => $c['id'] !== ''));
    }

    public function selectCalendar(GoogleCalendarConnection $connection, string $calendarId, ?string $calendarName = null): GoogleCalendarConnection
    {
        $connection->calendar_id = $calendarId;
        $connection->calendar_name = $calendarName;
        $connection->is_active = true;
        $connection->save();

        return $connection->fresh();
    }

    public function disconnect(?GoogleCalendarConnection $connection = null): void
    {
        $connection ??= $this->connection();
        if (! $connection) {
            return;
        }

        try {
            if ($connection->access_token || $connection->refresh_token) {
                $client = $this->makeClient($connection);
                $token = $connection->access_token ?: $connection->refresh_token;
                if ($token) {
                    $client->revokeToken($token);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Google token revoke failed.', ['error' => $e->getMessage()]);
        }

        $connection->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'calendar_id' => null,
            'calendar_name' => null,
            'is_active' => false,
            'last_synced_at' => null,
        ]);
    }

    public function createOrUpdateEvent(Appointment $appointment): string
    {
        $connection = $this->activeConnection();
        if (! $connection) {
            throw new \RuntimeException('Google Calendar não está conectado.');
        }

        $event = $this->mapAppointmentToEvent($appointment);
        $calendarId = rawurlencode((string) $connection->calendar_id);

        if ($appointment->google_event_id) {
            try {
                $updated = $this->apiPut(
                    $connection,
                    "/calendars/{$calendarId}/events/".rawurlencode($appointment->google_event_id),
                    $event,
                );
                $connection->forceFill(['last_synced_at' => now()])->save();

                return (string) ($updated['id'] ?? $appointment->google_event_id);
            } catch (GoogleCalendarApiException $e) {
                if ($e->status !== 404) {
                    throw $e;
                }
            }
        }

        $created = $this->apiPost($connection, "/calendars/{$calendarId}/events", $event);
        $connection->forceFill(['last_synced_at' => now()])->save();

        $id = (string) ($created['id'] ?? '');
        if ($id === '') {
            throw new \RuntimeException('Google não devolveu event id.');
        }

        return $id;
    }

    public function deleteEvent(Appointment $appointment): void
    {
        $connection = $this->activeConnection();
        if (! $connection || ! $appointment->google_event_id) {
            return;
        }

        $calendarId = rawurlencode((string) $connection->calendar_id);
        $eventId = rawurlencode($appointment->google_event_id);

        try {
            $this->apiDelete($connection, "/calendars/{$calendarId}/events/{$eventId}");
            $connection->forceFill(['last_synced_at' => now()])->save();
        } catch (GoogleCalendarApiException $e) {
            if ($e->status !== 404) {
                throw $e;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function mapAppointmentToEvent(Appointment $appointment): array
    {
        $appointment->loadMissing(['client', 'service', 'professional']);
        $tz = $this->settings->timezone();
        $salon = $this->settings->salonName();

        $serviceName = $appointment->service?->name ?? 'Serviço';
        $clientName = $appointment->client?->name ?? 'Cliente';

        $startLocal = $appointment->start_time?->copy()->timezone($tz);
        $endLocal = $appointment->end_time?->copy()->timezone($tz);

        $description = implode("\n", array_filter([
            'Serviço: '.$serviceName,
            'Cliente: '.$clientName,
            'Profissional: '.($appointment->professional?->name ?? '—'),
            $appointment->total_price !== null ? 'Valor: '.$appointment->total_price : null,
            'Estado: '.$appointment->status,
            'Appointment ID: '.$appointment->id,
            'Origem: '.$salon,
        ]));

        return [
            'summary' => "{$serviceName} — {$clientName}",
            'description' => $description,
            'location' => $this->settings->current()->address ?: $salon,
            'start' => [
                'dateTime' => $startLocal?->toRfc3339String(),
                'timeZone' => $tz,
            ],
            'end' => [
                'dateTime' => $endLocal?->toRfc3339String(),
                'timeZone' => $tz,
            ],
            'extendedProperties' => [
                'private' => [
                    'appointmentId' => (string) $appointment->id,
                    'source' => 'salon-management-system',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private function persistToken(GoogleCalendarConnection $connection, array $token, bool $preserveRefresh): void
    {
        if (! empty($token['access_token'])) {
            $connection->access_token = $token['access_token'];
        }

        if (! empty($token['refresh_token'])) {
            $connection->refresh_token = $token['refresh_token'];
        } elseif (! $preserveRefresh && empty($connection->refresh_token)) {
            $connection->refresh_token = null;
        }

        if (isset($token['expires_in'])) {
            $connection->token_expires_at = now()->addSeconds((int) $token['expires_in']);
        }

        $connection->save();
    }

    public function generateOAuthState(): string
    {
        return Str::random(40);
    }

    /**
     * URL-safe signed state (user_id + nonce + expiry).
     * Nonce lives in cache for one-time use; authenticity does not depend on session.
     */
    public function createOAuthState(int $userId): string
    {
        $nonce = $this->generateOAuthState();
        $payload = rtrim(strtr(base64_encode((string) json_encode([
            'uid' => $userId,
            'n' => $nonce,
            'exp' => now()->addMinutes(15)->getTimestamp(),
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payload, (string) config('app.key'));

        Cache::put($this->oauthNonceKey($nonce), [
            'user_id' => $userId,
        ], now()->addMinutes(15));

        return $payload.'.'.$signature;
    }

    public function isValidOAuthState(mixed $state): bool
    {
        return $this->parseOAuthState($state) !== null;
    }

    /**
     * @return array{user_id: int}|null
     */
    public function consumeOAuthState(mixed $state): ?array
    {
        $parsed = $this->parseOAuthState($state);
        if ($parsed === null) {
            return null;
        }

        $cached = Cache::pull($this->oauthNonceKey($parsed['nonce']));
        if (! is_array($cached) || (int) ($cached['user_id'] ?? 0) !== $parsed['user_id']) {
            return null;
        }

        return ['user_id' => $parsed['user_id']];
    }

    /**
     * @return array{user_id: int, nonce: string}|null
     */
    private function parseOAuthState(mixed $state): ?array
    {
        if (! is_string($state) || $state === '' || ! str_contains($state, '.')) {
            return null;
        }

        [$payload, $signature] = explode('.', $state, 2);
        if ($payload === '' || $signature === '') {
            return null;
        }

        $expected = hash_hmac('sha256', $payload, (string) config('app.key'));
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $padded = strtr($payload, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad !== 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($padded, true);
        if (! is_string($decoded) || $decoded === '') {
            return null;
        }

        $data = json_decode($decoded, true);
        if (
            ! is_array($data)
            || empty($data['uid'])
            || empty($data['n'])
            || empty($data['exp'])
            || (int) $data['exp'] < time()
        ) {
            return null;
        }

        return [
            'user_id' => (int) $data['uid'],
            'nonce' => (string) $data['n'],
        ];
    }

    private function oauthNonceKey(string $nonce): string
    {
        return 'google_oauth_nonce:'.$nonce;
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private function resolveAccountEmail(GoogleClient $client, array $token): ?string
    {
        try {
            if (! empty($token['id_token'])) {
                $payload = $client->verifyIdToken($token['id_token']);
                if (is_array($payload) && ! empty($payload['email'])) {
                    return (string) $payload['email'];
                }
            }
        } catch (Throwable) {
            // email opcional — scopes mínimos não incluem userinfo
        }

        return null;
    }

    private function accessToken(GoogleCalendarConnection $connection): string
    {
        $client = $this->makeClient($connection);
        $token = $client->getAccessToken();
        $access = is_array($token) ? ($token['access_token'] ?? null) : null;
        if (! is_string($access) || $access === '') {
            throw new \RuntimeException('Access token Google indisponível.');
        }

        return $access;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function apiGet(GoogleCalendarConnection $connection, string $path, array $query = []): array
    {
        $response = Http::withToken($this->accessToken($connection))
            ->acceptJson()
            ->get(self::CALENDAR_API.$path, $query);

        return $this->decode($response->status(), $response->body());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function apiPost(GoogleCalendarConnection $connection, string $path, array $body): array
    {
        $response = Http::withToken($this->accessToken($connection))
            ->acceptJson()
            ->asJson()
            ->post(self::CALENDAR_API.$path, $body);

        return $this->decode($response->status(), $response->body());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function apiPut(GoogleCalendarConnection $connection, string $path, array $body): array
    {
        $response = Http::withToken($this->accessToken($connection))
            ->acceptJson()
            ->asJson()
            ->put(self::CALENDAR_API.$path, $body);

        return $this->decode($response->status(), $response->body());
    }

    private function apiDelete(GoogleCalendarConnection $connection, string $path): void
    {
        $response = Http::withToken($this->accessToken($connection))
            ->acceptJson()
            ->delete(self::CALENDAR_API.$path);

        if ($response->status() >= 400) {
            $this->decode($response->status(), $response->body());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(int $status, string $body): array
    {
        if ($status >= 400) {
            $message = 'Google Calendar API error';
            $json = json_decode($body, true);
            if (is_array($json)) {
                $message = (string) data_get($json, 'error.message', $message);
            }

            throw new GoogleCalendarApiException($message, $status);
        }

        if ($body === '' || $body === 'null') {
            return [];
        }

        $json = json_decode($body, true);
        if (! is_array($json)) {
            throw new GoogleCalendarApiException('Resposta Google inválida.', $status);
        }

        return $json;
    }
}
