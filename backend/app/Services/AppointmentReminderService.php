<?php

namespace App\Services;

use App\Enums\ReminderType;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Lembretes de agendamento.
 *
 * Timezone e flags 24h/2h vêm de SettingsService (fallback config/env).
 * Não altera tabela appointment_reminders / idempotência / janelas.
 */
class AppointmentReminderService
{
    public function __construct(private readonly SettingsService $settingsService) {}

    /**
     * @return array{
     *   found: int,
     *   sent: int,
     *   skipped: int,
     *   failed: int,
     *   details: list<array<string, mixed>>
     * }
     */
    public function process(): array
    {
        $summary = [
            'found' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];

        if (! config('appointments.reminders.enabled', true)) {
            $summary['details'][] = ['reason' => 'reminders_disabled'];

            return $summary;
        }

        foreach (ReminderType::cases() as $type) {
            $typeConfig = config('appointments.reminders.types.'.$type->value);

            if (! is_array($typeConfig)) {
                continue;
            }

            if (! $this->settingsService->isReminderTypeEnabled($type)) {
                $summary['details'][] = [
                    'type' => $type->value,
                    'reason' => 'type_disabled_by_settings',
                ];

                continue;
            }

            $hoursBefore = (int) ($typeConfig['hours_before'] ?? $type->hoursBefore());
            $windowHours = (int) ($typeConfig['window_hours'] ?? 1);

            $result = $this->processType($type, $hoursBefore, $windowHours);

            $summary['found'] += $result['found'];
            $summary['sent'] += $result['sent'];
            $summary['skipped'] += $result['skipped'];
            $summary['failed'] += $result['failed'];
            $summary['details'] = array_merge($summary['details'], $result['details']);
        }

        return $summary;
    }

    /**
     * @return array{found: int, sent: int, skipped: int, failed: int, details: list<array<string, mixed>>}
     */
    private function processType(ReminderType $type, int $hoursBefore, int $windowHours): array
    {
        $tz = $this->settingsService->timezone();
        $now = Carbon::now($tz);

        // Janela: start_time ∈ [now + hoursBefore, now + hoursBefore + windowHours]
        // Comparação em UTC (armazenamento típico) para não depender do offset na query.
        $windowStart = $now->copy()->addHours($hoursBefore)->utc();
        $windowEnd = $now->copy()->addHours($hoursBefore + $windowHours)->utc();

        $statuses = config('appointments.reminders.eligible_statuses', [
            Appointment::STATUS_PENDING,
            Appointment::STATUS_CONFIRMED,
        ]);

        $appointments = Appointment::query()
            ->with(['client', 'service', 'professional'])
            ->whereIn('status', $statuses)
            ->whereBetween('start_time', [$windowStart, $windowEnd])
            ->whereDoesntHave('reminders', function ($query) use ($type) {
                $query->where('type', $type->value)->whereNotNull('sent_at');
            })
            ->orderBy('start_time')
            ->get();

        $found = $appointments->count();
        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $details = [];

        foreach ($appointments as $appointment) {
            $outcome = $this->sendForAppointment($appointment, $type, $windowStart);

            $details[] = [
                'appointment_id' => $appointment->id,
                'type' => $type->value,
                'outcome' => $outcome,
            ];

            match ($outcome) {
                'sent' => $sent++,
                'failed' => $failed++,
                default => $skipped++,
            };
        }

        return compact('found', 'sent', 'skipped', 'failed', 'details');
    }

    private function sendForAppointment(
        Appointment $appointment,
        ReminderType $type,
        Carbon $scheduledFor,
    ): string {
        $maxAttempts = (int) config('appointments.reminders.max_attempts', 5);

        try {
            return DB::transaction(function () use ($appointment, $type, $scheduledFor, $maxAttempts) {
                /** @var Appointment $locked */
                $locked = Appointment::query()
                    ->whereKey($appointment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($locked->status, config('appointments.reminders.eligible_statuses', []), true)) {
                    return 'skipped_status';
                }

                $locked->loadMissing(['client', 'service', 'professional']);
                $email = trim((string) ($locked->client?->email ?? ''));

                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return 'skipped_no_email';
                }

                $reminder = AppointmentReminder::query()->firstOrCreate(
                    [
                        'appointment_id' => $locked->id,
                        'type' => $type->value,
                    ],
                    [
                        'scheduled_for' => $scheduledFor,
                        'attempts' => 0,
                    ],
                );

                /** @var AppointmentReminder $reminder */
                $reminder = AppointmentReminder::query()
                    ->whereKey($reminder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($reminder->sent_at !== null) {
                    return 'already_sent';
                }

                if ($reminder->attempts >= $maxAttempts) {
                    return 'max_attempts';
                }

                try {
                    Mail::to($email)->send(new AppointmentReminderMail($locked, $type));
                } catch (Throwable $e) {
                    $reminder->update([
                        'attempts' => $reminder->attempts + 1,
                        'failed_at' => now(),
                        'last_error' => mb_substr($e->getMessage(), 0, 500),
                    ]);

                    Log::warning('Appointment reminder email failed.', [
                        'appointment_id' => $locked->id,
                        'type' => $type->value,
                        'error' => $e->getMessage(),
                    ]);

                    return 'failed';
                }

                $reminder->update([
                    'sent_at' => now(),
                    'failed_at' => null,
                    'last_error' => null,
                    'scheduled_for' => $scheduledFor,
                    'attempts' => $reminder->attempts + 1,
                ]);

                // Compatibilidade API/legado: qualquer reminder enviado.
                if (! $locked->reminder_sent) {
                    $locked->update(['reminder_sent' => true]);
                }

                return 'sent';
            });
        } catch (Throwable $e) {
            Log::warning('Appointment reminder processing error.', [
                'appointment_id' => $appointment->id,
                'type' => $type->value,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }
}
