<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Callback OAuth browser (fora de /api/v1).
 */
class GoogleCalendarOAuthController extends Controller
{
    public function __construct(private readonly GoogleCalendarService $google) {}

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->redirectToSettings('error');
        }

        $state = $request->query('state', '');
        $code = $request->query('code', '');

        if (! $this->google->isValidOAuthState($state)) {
            return $this->redirectToSettings('invalid_state');
        }

        if (! is_string($code) || $code === '') {
            return $this->redirectToSettings('error');
        }

        $payload = $this->google->consumeOAuthState($state);
        if ($payload === null) {
            return $this->redirectToSettings('invalid_state');
        }

        $admin = User::query()->find($payload['user_id']);
        if (! $admin || ! $admin->isAdmin()) {
            return $this->redirectToSettings('forbidden');
        }

        try {
            $this->google->handleCallback($code, $admin);

            return $this->redirectToSettings('connected');
        } catch (Throwable $e) {
            Log::warning('Google OAuth callback failed.', ['error' => $e->getMessage()]);

            return $this->redirectToSettings('error');
        }
    }

    private function redirectToSettings(string $status): RedirectResponse
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $path = '/'.ltrim((string) config('google.settings_path', '/panel/admin/settings'), '/');

        return redirect()->away($frontend.$path.'?google='.$status);
    }
}
