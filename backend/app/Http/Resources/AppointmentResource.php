<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Appointment */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'service_id' => $this->service_id,
            'professional_id' => $this->professional_id,
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'status' => $this->status,
            'notes' => $this->notes,
            'total_price' => $this->total_price,
            'is_active' => $this->is_active,
            'reminder_sent' => $this->reminder_sent,
            'google_event_id' => $this->when(
                $request->user()?->isAdmin() === true,
                $this->google_event_id,
            ),
            'google_sync_status' => $this->when(
                $request->user()?->isAdmin() === true,
                $this->google_sync_status,
            ),
            'google_synced_at' => $this->when(
                $request->user()?->isAdmin() === true,
                $this->google_synced_at?->toIso8601String(),
            ),
            'google_sync_error' => $this->when(
                $request->user()?->isAdmin() === true,
                $this->google_sync_error,
            ),
            'reminders' => $this->when(
                $this->relationLoaded('reminders'),
                fn () => $this->reminders->mapWithKeys(fn ($reminder) => [
                    $reminder->type instanceof \App\Enums\ReminderType
                        ? $reminder->type->value
                        : (string) $reminder->type => [
                            'sent_at' => $reminder->sent_at?->toIso8601String(),
                            'failed_at' => $reminder->failed_at?->toIso8601String(),
                            'attempts' => $reminder->attempts,
                        ],
                ]),
            ),
            'client' => new ClientResource($this->whenLoaded('client')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'professional' => new ProfessionalResource($this->whenLoaded('professional')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
