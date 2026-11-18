<?php

namespace App\Enums;

enum ReminderType: string
{
    case TwentyFourHours = 'twenty_four_hours';
    case TwoHours = 'two_hours';

    public function label(): string
    {
        return match ($this) {
            self::TwentyFourHours => '24 horas',
            self::TwoHours => '2 horas',
        };
    }

    public function hoursBefore(): int
    {
        return match ($this) {
            self::TwentyFourHours => 24,
            self::TwoHours => 2,
        };
    }
}
