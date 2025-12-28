<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProjectApiKey extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'name',
        'key_prefix',
        'key_hash',
        'last_used_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    /**
     * Generate a new API key.
     * Returns [prefix, plaintext_key, hash]
     */
    public static function generateKey(): array
    {
        $prefix = config('codexflow.api_keys.prefix', 'cf_');
        $length = config('codexflow.api_keys.length', 40);
        
        $randomPart = Str::random($length);
        $plaintextKey = $prefix . $randomPart;
        $keyPrefix = substr($plaintextKey, 0, 12);
        $keyHash = Hash::make($plaintextKey);

        return [
            'prefix' => $keyPrefix,
            'plaintext' => $plaintextKey,
            'hash' => $keyHash,
        ];
    }

    /**
     * Verify a plaintext key against this key's hash.
     */
    public function verifyKey(string $plaintextKey): bool
    {
        return Hash::check($plaintextKey, $this->key_hash);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isActive(): bool
    {
        return !$this->isRevoked();
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    public function markUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Get masked version of the key for display.
     */
    public function getMaskedKey(): string
    {
        return $this->key_prefix . str_repeat('*', 32);
    }
}


