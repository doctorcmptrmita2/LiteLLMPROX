<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectApiKeyController extends Controller
{
    /**
     * List all API keys for a project.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        // Authorization check - verify user owns project
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $keys = $project->apiKeys()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($key) => [
                'id' => $key->id,
                'name' => $key->name,
                'masked_key' => $key->getMaskedKey(),
                'last_used_at' => $key->last_used_at?->toIso8601String(),
                'created_at' => $key->created_at->toIso8601String(),
                'revoked_at' => $key->revoked_at?->toIso8601String(),
                'is_active' => $key->isActive(),
            ]);

        return response()->json([
            'data' => $keys,
            'total' => $keys->count(),
        ]);
    }

    /**
     * Create a new API key.
     * WARNING: The plaintext key is shown ONLY ONCE!
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        // Authorization check - verify user owns project
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Limit keys per project
        $activeKeyCount = $project->activeApiKeys()->count();
        if ($activeKeyCount >= 10) {
            return response()->json([
                'error' => [
                    'message' => 'Maximum 10 active API keys per project.',
                    'type' => 'validation_error',
                    'code' => 'max_keys_exceeded',
                ],
            ], 422);
        }

        // Generate key
        $keyData = ProjectApiKey::generateKey();

        // Create key record
        $apiKey = $project->apiKeys()->create([
            'name' => $request->name,
            'key_prefix' => $keyData['prefix'],
            'key_hash' => $keyData['hash'],
        ]);

        // Refresh to get created_at
        $apiKey->refresh();

        return response()->json([
            'message' => 'API key created successfully. Store this key securely - it will not be shown again.',
            'data' => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key' => $keyData['plaintext'], // ONLY shown once!
                'created_at' => $apiKey->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Revoke an API key.
     */
    public function destroy(Request $request, Project $project, ProjectApiKey $key): JsonResponse
    {
        // Authorization check - verify user owns project
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // Verify key belongs to project
        if ($key->project_id !== $project->id) {
            return response()->json([
                'error' => [
                    'message' => 'API key not found.',
                    'type' => 'not_found_error',
                    'code' => 'key_not_found',
                ],
            ], 404);
        }

        if ($key->isRevoked()) {
            return response()->json([
                'error' => [
                    'message' => 'API key is already revoked.',
                    'type' => 'validation_error',
                    'code' => 'key_already_revoked',
                ],
            ], 422);
        }

        $key->revoke();

        return response()->json([
            'message' => 'API key revoked successfully.',
            'data' => [
                'id' => $key->id,
                'revoked_at' => $key->revoked_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Rotate an API key (revoke old, create new with same name).
     */
    public function rotate(Request $request, Project $project, ProjectApiKey $key): JsonResponse
    {
        // Authorization check - verify user owns project
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // Verify key belongs to project
        if ($key->project_id !== $project->id) {
            return response()->json([
                'error' => [
                    'message' => 'API key not found.',
                    'type' => 'not_found_error',
                    'code' => 'key_not_found',
                ],
            ], 404);
        }

        $oldKeyName = $key->name;

        // Revoke old key
        $key->revoke();

        // Generate new key
        $keyData = ProjectApiKey::generateKey();

        // Create new key with same name
        $newKey = $project->apiKeys()->create([
            'name' => $oldKeyName . ' (rotated)',
            'key_prefix' => $keyData['prefix'],
            'key_hash' => $keyData['hash'],
        ]);

        return response()->json([
            'message' => 'API key rotated successfully. Store this key securely - it will not be shown again.',
            'data' => [
                'id' => $newKey->id,
                'name' => $newKey->name,
                'key' => $keyData['plaintext'], // ONLY shown once!
                'created_at' => $newKey->created_at->toIso8601String(),
                'previous_key_id' => $key->id,
            ],
        ], 201);
    }
}


