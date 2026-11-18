<?php

namespace App\Services;

use App\Enums\ReminderType;
use App\Models\Setting;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Configuração operacional global do salão (singleton).
 *
 * Legado: tabela Settings existia (CRUD admin) mas quase não era consumida;
 * reminders/timezone/email vinham de appsettings/IConfiguration.
 *
 * Laravel: uma linha em `settings`; secrets continuam no `.env`.
 */
class SettingsService
{
    private const CACHE_KEY = 'settings.current';

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'salon_name' => null,
            'timezone' => null,
            'currency' => 'EUR',
            'contact_email' => null,
            'contact_phone' => null,
            'address' => null,
            'reminder_24h_enabled' => (bool) config('appointments.reminders.types.twenty_four_hours.enabled', true),
            'reminder_2h_enabled' => (bool) config('appointments.reminders.types.two_hours.enabled', true),
        ];
    }

    /**
     * Settings persistido ou instância em memória com defaults (sem inventar branding).
     */
    public function current(): Setting
    {
        return Cache::remember(self::CACHE_KEY, 60, function () {
            $stored = Setting::query()->first();

            if ($stored) {
                return $stored;
            }

            return tap(new Setting, function (Setting $setting) {
                $setting->forceFill($this->defaults());
            });
        });
    }

    public function exists(): bool
    {
        return Setting::query()->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): Setting
    {
        $setting = Setting::query()->first();

        if ($setting === null) {
            $setting = Setting::query()->create(array_merge($this->defaults(), $data));
        } else {
            $setting->fill($data);
            $setting->save();
        }

        Cache::forget(self::CACHE_KEY);

        return $setting->fresh() ?? $setting;
    }

    public function timezone(): string
    {
        $candidate = trim((string) ($this->current()->timezone ?? ''));

        if ($candidate !== '' && $this->isValidTimezone($candidate)) {
            return $candidate;
        }

        return (string) config('app.timezone', 'UTC');
    }

    public function salonName(): string
    {
        $name = trim((string) ($this->current()->salon_name ?? ''));

        return $name !== '' ? $name : (string) config('app.name');
    }

    public function currency(): string
    {
        $currency = strtoupper(trim((string) ($this->current()->currency ?? '')));

        return $currency !== '' ? $currency : 'EUR';
    }

    public function reminder24hEnabled(): bool
    {
        if (! $this->exists()) {
            return (bool) config('appointments.reminders.types.twenty_four_hours.enabled', true);
        }

        return (bool) $this->current()->reminder_24h_enabled;
    }

    public function reminder2hEnabled(): bool
    {
        if (! $this->exists()) {
            return (bool) config('appointments.reminders.types.two_hours.enabled', true);
        }

        return (bool) $this->current()->reminder_2h_enabled;
    }

    public function isReminderTypeEnabled(ReminderType $type): bool
    {
        return match ($type) {
            ReminderType::TwentyFourHours => $this->reminder24hEnabled(),
            ReminderType::TwoHours => $this->reminder2hEnabled(),
        };
    }

    /**
     * Campos seguros para o site público.
     * Branding vazio permanece null para o frontend usar o fallback visual atual.
     *
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        $settings = $this->current();
        $salonName = trim((string) ($settings->salon_name ?? ''));

        return [
            'salon_name' => $salonName !== '' ? $salonName : null,
            'timezone' => $this->timezone(),
            'currency' => $this->currency(),
            'contact_email' => $settings->contact_email,
            'contact_phone' => $settings->contact_phone,
            'address' => $settings->address,
        ];
    }

    public function isValidTimezone(string $timezone): bool
    {
        try {
            new DateTimeZone($timezone);

            return in_array($timezone, timezone_identifiers_list(), true);
        } catch (Throwable) {
            return false;
        }
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
