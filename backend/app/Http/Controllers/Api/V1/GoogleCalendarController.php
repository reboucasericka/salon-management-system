<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GoogleCalendarController extends Controller
{
    public function __construct(private readonly GoogleCalendarService $google) {}

    public function status(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $connection = $this->google->connection();

        return response()->json([
            'data' => [
                'configured' => $this->google->isConfigured(),
                'connected' => (bool) $connection?->refresh_token,
                'is_active' => (bool) $connection?->is_active,
                'ready' => (bool) $connection?->isReady(),
                'google_account_email' => $connection?->google_account_email,
                'calendar_id' => $connection?->calendar_id,
                'calendar_name' => $connection?->calendar_name,
                'last_synced_at' => $connection?->last_synced_at?->toIso8601String(),
                'connected_by' => $connection?->connected_by,
            ],
        ]);
    }

    public function connect(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if (! $this->google->isConfigured()) {
            throw ValidationException::withMessages([
                'google' => 'Configure GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET no .env.',
            ]);
        }

        $state = $this->google->createOAuthState((int) $request->user()->id);

        return response()->json([
            'data' => [
                'authorization_url' => $this->google->getAuthUrl($state),
            ],
        ]);
    }

    public function calendars(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $connection = $this->google->connection();
        if (! $connection?->refresh_token) {
            throw ValidationException::withMessages([
                'google' => 'Conecte a conta Google primeiro.',
            ]);
        }

        return response()->json([
            'data' => $this->google->listCalendars($connection),
        ]);
    }

    public function selectCalendar(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'calendar_id' => ['required', 'string', 'max:255'],
            'calendar_name' => ['nullable', 'string', 'max:255'],
        ]);

        $connection = $this->google->connection();
        if (! $connection?->refresh_token) {
            throw ValidationException::withMessages([
                'google' => 'Conecte a conta Google primeiro.',
            ]);
        }

        $calendars = collect($this->google->listCalendars($connection));
        $selected = $calendars->firstWhere('id', $data['calendar_id']);
        if (! $selected) {
            throw ValidationException::withMessages([
                'calendar_id' => 'Calendário inválido para esta conta.',
            ]);
        }

        $connection = $this->google->selectCalendar(
            $connection,
            $data['calendar_id'],
            $data['calendar_name'] ?? $selected['name'],
        );

        return response()->json([
            'message' => 'Calendário selecionado.',
            'data' => [
                'calendar_id' => $connection->calendar_id,
                'calendar_name' => $connection->calendar_name,
                'ready' => $connection->isReady(),
            ],
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $this->google->disconnect();

        return response()->json([
            'message' => 'Google Calendar desconectado. Os agendamentos locais foram preservados.',
        ]);
    }
}
