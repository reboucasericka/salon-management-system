<?php

namespace App\Console\Commands;

use App\Services\AppointmentGoogleSyncService;
use Illuminate\Console\Command;

class SyncGoogleCalendarAppointmentsCommand extends Command
{
    protected $signature = 'appointments:sync-google {--limit=50 : Máximo de appointments a processar}';

    protected $description = 'Retry idempotente de sync Google Calendar (pending/failed)';

    public function handle(AppointmentGoogleSyncService $sync): int
    {
        $summary = $sync->retryPending((int) $this->option('limit'));

        $this->info('Google Calendar sync retry.');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Encontrados', $summary['found']],
                ['Sincronizados', $summary['synced']],
                ['Removidos', $summary['removed']],
                ['Falharam', $summary['failed']],
            ],
        );

        return self::SUCCESS;
    }
}
