<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Appointment reminders
    |--------------------------------------------------------------------------
    |
    | Intervalos e flags alinhados ao legado (24h/2h, janela de 1h).
    | Flags enable/disable podem ser sobrescritas por Settings (BD).
    | Timezone operacional vem de SettingsService (fallback APP_TIMEZONE).
    |
    */

    'reminders' => [
        'enabled' => env('APPOINTMENT_REMINDERS_ENABLED', true),

        'max_attempts' => (int) env('APPOINTMENT_REMINDERS_MAX_ATTEMPTS', 5),

        'eligible_statuses' => [
            'pending',
            'confirmed',
        ],

        'types' => [
            'twenty_four_hours' => [
                'enabled' => env('APPOINTMENT_REMINDER_24H_ENABLED', true),
                'hours_before' => 24,
                'window_hours' => 1,
            ],
            'two_hours' => [
                'enabled' => env('APPOINTMENT_REMINDER_2H_ENABLED', true),
                'hours_before' => 2,
                'window_hours' => 1,
            ],
        ],
    ],

];
