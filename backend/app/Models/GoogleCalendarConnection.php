<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarConnection extends Model
{
    protected $fillable = [
        'connected_by',
        'google_account_email',
        'calendar_id',
        'calendar_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'is_active',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
            'scopes' => 'array',
        ];
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function isReady(): bool
    {
        return $this->is_active
            && filled($this->refresh_token)
            && filled($this->calendar_id);
    }
}
