<?php

namespace App\Console\Commands;

use App\Services\AppointmentReminderService;
use Illuminate\Console\Command;

class SendAppointmentRemindersCommand extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Envia lembretes de agendamento (24h e 2h) para appointments elegíveis';

    public function handle(AppointmentReminderService $service): int
    {
        $summary = $service->process();

        $this->info('Lembretes processados.');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Encontrados', $summary['found']],
                ['Enviados', $summary['sent']],
                ['Ignorados', $summary['skipped']],
                ['Falharam', $summary['failed']],
            ],
        );

        if ($this->output->isVerbose() && $summary['details'] !== []) {
            $rows = collect($summary['details'])->map(fn (array $row) => [
                $row['appointment_id'] ?? '—',
                $row['type'] ?? ($row['reason'] ?? '—'),
                $row['outcome'] ?? ($row['reason'] ?? '—'),
            ])->all();

            $this->table(['Appointment', 'Tipo', 'Resultado'], $rows);
        }

        return self::SUCCESS;
    }
}
