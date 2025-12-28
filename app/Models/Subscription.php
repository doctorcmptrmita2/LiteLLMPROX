<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_code',
        'starts_at',
        'ends_at',
        'status',
        'is_trial',
        'trial_ends_at',
        'converted_from_trial',
        'payment_provider',
        'payment_ref',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'trial_ends_at' => 'datetime',
            'is_trial' => 'boolean',
            'converted_from_trial' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial']) && $this->ends_at >= now();
    }

    public function isTrial(): bool
    {
        return $this->is_trial && $this->status === 'trial';
    }

    public function isExpired(): bool
    {
        return $this->ends_at < now();
    }

    public function trialExpired(): bool
    {
        return $this->is_trial && $this->trial_ends_at && $this->trial_ends_at < now();
    }

    public function getPlanConfig(): ?array
    {
        return config("codexflow.plans.{$this->plan_code}");
    }

    public function getPlanName(): string
    {
        return $this->getPlanConfig()['name'] ?? $this->plan_code;
    }
}



