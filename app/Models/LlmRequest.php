<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LlmRequest extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'project_id',
        'api_key_id',
        'parent_request_id',
        'chunk_index',
        'request_id',
        'tier',
        'model_alias',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_usd',
        'latency_ms',
        'time_to_first_token_ms',
        'is_cached',
        'is_streaming',
        'is_decomposed',
        'status_code',
        'error_type',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cost_usd' => 'decimal:6',
            'latency_ms' => 'integer',
            'time_to_first_token_ms' => 'integer',
            'is_cached' => 'boolean',
            'is_streaming' => 'boolean',
            'is_decomposed' => 'boolean',
            'status_code' => 'integer',
            'chunk_index' => 'integer',
            'created_at' => 'datetime',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ProjectApiKey::class, 'api_key_id');
    }

    public function parentRequest(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_request_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_request_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isSuccess(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    public function isError(): bool
    {
        return $this->error_type !== null;
    }

    public function isChunk(): bool
    {
        return $this->parent_request_id !== null;
    }

    public function isPlanner(): bool
    {
        return $this->tier === 'planner';
    }

    /**
     * Calculate cost based on tier and token usage.
     */
    public function calculateCost(): float
    {
        $costs = config("codexflow.costs.{$this->tier}", ['input' => 0, 'output' => 0]);

        $inputCost = ($this->prompt_tokens / 1_000_000) * $costs['input'];
        $outputCost = ($this->completion_tokens / 1_000_000) * $costs['output'];

        return round($inputCost + $outputCost, 6);
    }
}


