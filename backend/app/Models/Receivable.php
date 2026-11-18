<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ReceivableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receivable extends Model
{
    /** @use HasFactory<\Database\Factories\ReceivableFactory> */
    use HasFactory;

    public const OVERDUE_AFTER_DAYS = 30;

    protected $fillable = [
        'description',
        'amount',
        'received_at',
        'status',
        'payment_method',
        'client_id',
        'professional_id',
        'service_id',
        'sale_id',
        'created_by',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'date',
            'status' => ReceivableStatus::class,
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isReceived(): bool
    {
        return $this->status === ReceivableStatus::Received;
    }

    public function isOverdue(): bool
    {
        if ($this->status !== ReceivableStatus::Pending || $this->created_at === null) {
            return false;
        }

        return $this->created_at->lt(now()->subDays(self::OVERDUE_AFTER_DAYS));
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

    public function referenceLabel(): ?string
    {
        if ($this->relationLoaded('service') && $this->service) {
            return $this->service->name;
        }

        if ($this->relationLoaded('sale') && $this->sale) {
            return 'Venda #'.$this->sale->id;
        }

        if ($this->service_id) {
            return 'Serviço #'.$this->service_id;
        }

        if ($this->sale_id) {
            return 'Venda #'.$this->sale_id;
        }

        return null;
    }
}
