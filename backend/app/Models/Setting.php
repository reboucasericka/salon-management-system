<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'salon_name',
        'timezone',
        'currency',
        'contact_email',
        'contact_phone',
        'address',
        'reminder_24h_enabled',
        'reminder_2h_enabled',
    ];

    protected function casts(): array
    {
        return [
            'reminder_24h_enabled' => 'boolean',
            'reminder_2h_enabled' => 'boolean',
        ];
    }
}
