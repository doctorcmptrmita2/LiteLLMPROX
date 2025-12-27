<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotaMonthly extends Model
{
    use HasFactory;

    protected $table = 'quota_monthly';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'month',
        'fast_input_tokens',
        'fast_output_tokens',
        'fast_requests',
        'deep_input_tokens',
        'deep_output_tokens',
        'deep_requests',
        'planner_tokens',
    ];

    protected function casts(): array
    {
        return [
            'fast_input_tokens' => 'integer',
            'fast_output_tokens' => 'integer',
            'fast_requests' => 'integer',
            'deep_input_tokens' => 'integer',
            'deep_output_tokens' => 'integer',
            'deep_requests' => 'integer',
            'planner_tokens' => 'integer',
            'updated_at' => 'datetime',
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
    | Static Methods
    |--------------------------------------------------------------------------
    */

    public static function getOrCreateForUser(int $userId, ?string $month = null): self
    {
        $month = $month ?? now()->format('Y-m');

        return self::firstOrCreate(
            ['user_id' => $userId, 'month' => $month],
            [
                'fast_input_tokens' => 0,
                'fast_output_tokens' => 0,
                'fast_requests' => 0,
                'deep_input_tokens' => 0,
                'deep_output_tokens' => 0,
                'deep_requests' => 0,
                'planner_tokens' => 0,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function getTotalFastTokens(): int
    {
        return $this->fast_input_tokens + $this->fast_output_tokens;
    }

    public function getTotalDeepTokens(): int
    {
        return $this->deep_input_tokens + $this->deep_output_tokens;
    }
}

