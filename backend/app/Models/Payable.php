<?php

namespace App\Models;

use App\Enums\PayableStatus;
use App\Enums\PayableType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payable extends Model
{
    /** @use HasFactory<\Database\Factories\PayableFactory> */
    use HasFactory;

    protected $fillable = [
        'description',
        'amount',
        'due_date',
        'paid_at',
        'status',
        'type',
        'notes',
        'supplier_id',
        'professional_id',
        'appointment_id',
        'created_by',
        'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'date',
            'status' => PayableStatus::class,
            'type' => PayableType::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function isPaid(): bool
    {
        return $this->status === PayableStatus::Paid;
    }

    public function isOverdue(): bool
    {
        if ($this->status !== PayableStatus::Pending || $this->due_date === null) {
            return false;
        }

        return $this->due_date->lt(now()->startOfDay());
    }

    public function displayStatus(): string
    {
        if ($this->isOverdue()) {
            return 'overdue';
        }

        return $this->status->value;
    }

    public function displayStatusLabel(): string
    {
        return match ($this->displayStatus()) {
            'overdue' => 'Vencido',
            default => $this->status->label(),
        };
    }
}
