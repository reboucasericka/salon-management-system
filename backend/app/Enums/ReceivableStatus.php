<?php

namespace App\Enums;

enum ReceivableStatus: string
{
    case Pending = 'pending';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Received => 'Recebido',
        };
    }
}
