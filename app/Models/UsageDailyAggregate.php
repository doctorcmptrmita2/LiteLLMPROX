<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageDailyAggregate extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'date',
        'fast_tokens',
        'fast_requests',
        'fast_cost_usd',
        'deep_tokens',
        'deep_requests',
        'deep_cost_usd',
        'grace_tokens',
        'grace_requests',
        'grace_cost_usd',
        'planner_tokens',
        'planner_requests',
        'total_tokens',
        'total_requests',
        'total_cost_usd',
        'cache_hits',
        'decomposed_requests',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'fast_tokens' => 'integer',
            'fast_requests' => 'integer',
            'fast_cost_usd' => 'decimal:4',
            'deep_tokens' => 'integer',
            'deep_requests' => 'integer',
            'deep_cost_usd' => 'decimal:4',
            'grace_tokens' => 'integer',
            'grace_requests' => 'integer',
            'grace_cost_usd' => 'decimal:4',
            'planner_tokens' => 'integer',
            'planner_requests' => 'integer',
            'total_tokens' => 'integer',
            'total_requests' => 'integer',
            'total_cost_usd' => 'decimal:4',
            'cache_hits' => 'integer',
            'decomposed_requests' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Static Methods
    |--------------------------------------------------------------------------
    */

    public static function getOrCreateForProject(int $projectId, string $date): self
    {
        return self::firstOrCreate(
            ['project_id' => $projectId, 'date' => $date],
            [
                'fast_tokens' => 0,
                'fast_requests' => 0,
                'fast_cost_usd' => 0,
                'deep_tokens' => 0,
                'deep_requests' => 0,
                'deep_cost_usd' => 0,
                'grace_tokens' => 0,
                'grace_requests' => 0,
                'grace_cost_usd' => 0,
                'planner_tokens' => 0,
                'planner_requests' => 0,
                'total_tokens' => 0,
                'total_requests' => 0,
                'total_cost_usd' => 0,
                'cache_hits' => 0,
                'decomposed_requests' => 0,
            ]
        );
    }
}


