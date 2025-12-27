<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotaDaily extends Model
{
    use HasFactory;

    protected $table = 'quota_daily';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'date',
        'fast_tokens',
        'fast_requests',
        'deep_tokens',
        'deep_requests',
        'grace_tokens',
        'grace_requests',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'fast_tokens' => 'integer',
            'fast_requests' => 'integer',
            'deep_tokens' => 'integer',
            'deep_requests' => 'integer',
            'grace_tokens' => 'integer',
            'grace_requests' => 'integer',
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

    public static function getOrCreateForUser(int $userId, ?string $date = null): self
    {
        $date = $date ?? now()->toDateString();

        return self::firstOrCreate(
            ['user_id' => $userId, 'date' => $date],
            [
                'fast_tokens' => 0,
                'fast_requests' => 0,
                'deep_tokens' => 0,
                'deep_requests' => 0,
                'grace_tokens' => 0,
                'grace_requests' => 0,
            ]
        );
    }
}

