<?php

namespace App\Http\Resources;

use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Setting */
class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        return [
            'id' => $this->id,
            'salon_name' => $this->salon_name,
            'timezone' => $this->timezone,
            'effective_timezone' => $settings->timezone(),
            'currency' => $this->currency ?: 'EUR',
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'address' => $this->address,
            'reminder_24h_enabled' => (bool) $this->reminder_24h_enabled,
            'reminder_2h_enabled' => (bool) $this->reminder_2h_enabled,
            'is_persisted' => $this->exists,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
