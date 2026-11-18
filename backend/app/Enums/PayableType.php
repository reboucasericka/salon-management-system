<?php

namespace App\Enums;

enum PayableType: string
{
    case Expense = 'expense';
    case Commission = 'commission';
    case Supplier = 'supplier';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Despesa',
            self::Commission => 'Comissão',
            self::Supplier => 'Fornecedor',
            self::Other => 'Outro',
        };
    }
}
