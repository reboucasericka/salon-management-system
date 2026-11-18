<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Calendar (OAuth)
    |--------------------------------------------------------------------------
    |
    | Secrets only in .env. Tokens live encrypted in google_calendar_connections.
    | Appointments remain the source of truth; Google is outbound projection.
    |
    */

    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/google-calendar/callback'),

    /*
    | Vue admin settings path (appended to FRONTEND_URL after OAuth).
    */
    'settings_path' => '/panel/admin/settings',

    'scopes' => [
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
    ],

    'access_type' => 'offline',
    'prompt' => 'consent',

];
