<?php

namespace App\Enums;

enum GoogleSyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Synced => 'Sincronizado',
            self::Failed => 'Falha',
            self::NotApplicable => 'Não aplicável',
        };
    }
}
