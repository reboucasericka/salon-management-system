<?php

namespace App\Services;

use App\Enums\GoogleSyncStatus;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orquestra sync Appointment → Google sem falhar o fluxo local.
 */
class AppointmentGoogleSyncService
{
    public function __construct(private readonly GoogleCalendarService $google) {}

    public function sync(Appointment $appointment): void
    {
        if (! $this->google->isConfigured() || ! $this->google->activeConnection()) {
            $appointment->forceFill([
                'google_sync_status' => GoogleSyncStatus::NotApplicable->value,
                'google_sync_error' => null,
            ])->save();

            return;
        }

        if ($appointment->status === Appointment::STATUS_CANCELED) {
            $this->remove($appointment);

            return;
        }

        try {
            $eventId = $this->google->createOrUpdateEvent($appointment);
            $appointment->forceFill([
                'google_event_id' => $eventId,
                'google_sync_status' => GoogleSyncStatus::Synced->value,
                'google_synced_at' => now(),
                'google_sync_error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Google Calendar sync failed.', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            $appointment->forceFill([
                'google_sync_status' => GoogleSyncStatus::Failed->value,
                'google_sync_error' => mb_substr($e->getMessage(), 0, 500),
            ])->save();
        }
    }

    public function remove(Appointment $appointment): void
    {
        if (! $this->google->isConfigured() || ! $this->google->activeConnection()) {
            $appointment->forceFill([
                'google_sync_status' => GoogleSyncStatus::NotApplicable->value,
            ])->save();

            return;
        }

        if (! $appointment->google_event_id) {
            $appointment->forceFill([
                'google_sync_status' => GoogleSyncStatus::NotApplicable->value,
                'google_sync_error' => null,
            ])->save();

            return;
        }

        try {
            $this->google->deleteEvent($appointment);
            $appointment->forceFill([
                'google_event_id' => null,
                'google_sync_status' => GoogleSyncStatus::NotApplicable->value,
                'google_synced_at' => now(),
                'google_sync_error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Google Calendar delete failed.', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            $appointment->forceFill([
                'google_sync_status' => GoogleSyncStatus::Failed->value,
                'google_sync_error' => mb_substr($e->getMessage(), 0, 500),
            ])->save();
        }
    }

    /**
     * Retry pending/failed appointments (idempotent).
     *
     * @return array{found: int, synced: int, failed: int, removed: int}
     */
    public function retryPending(int $limit = 50): array
    {
        $found = 0;
        $synced = 0;
        $failed = 0;
        $removed = 0;

        if (! $this->google->activeConnection()) {
            return compact('found', 'synced', 'failed', 'removed');
        }

        $appointments = Appointment::query()
            ->whereIn('google_sync_status', [
                GoogleSyncStatus::Pending->value,
                GoogleSyncStatus::Failed->value,
            ])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $found = $appointments->count();

        foreach ($appointments as $appointment) {
            $before = $appointment->google_event_id;
            $this->sync($appointment->fresh());
            $appointment->refresh();

            if ($appointment->google_sync_status === GoogleSyncStatus::Synced->value) {
                $synced++;
            } elseif ($appointment->status === Appointment::STATUS_CANCELED
                && $appointment->google_sync_status === GoogleSyncStatus::NotApplicable->value
                && $before) {
                $removed++;
            } elseif ($appointment->google_sync_status === GoogleSyncStatus::Failed->value) {
                $failed++;
            }
        }

        return compact('found', 'synced', 'failed', 'removed');
    }
}
