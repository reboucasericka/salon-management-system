<?php

namespace App\Models;

use App\Enums\ReminderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'type',
        'scheduled_for',
        'sent_at',
        'failed_at',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReminderType::class,
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }
}
