<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * List all projects for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = $user->projects()
            ->withCount('activeApiKeys')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'status' => $project->status,
                'api_keys_count' => $project->active_api_keys_count,
                'created_at' => $project->created_at->toIso8601String(),
            ]);

        return response()->json([
            'data' => $projects,
            'total' => $projects->count(),
        ]);
    }

    /**
     * Create a new project.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();

        // Limit projects per user
        $projectCount = $user->projects()->count();
        if ($projectCount >= 10) {
            return response()->json([
                'error' => [
                    'message' => 'Maximum 10 projects per account.',
                    'type' => 'validation_error',
                    'code' => 'max_projects_exceeded',
                ],
            ], 422);
        }

        $project = $user->projects()->create([
            'name' => $request->name,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Project created successfully.',
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'status' => $project->status,
                'created_at' => $project->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Show a specific project.
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'status' => $project->status,
                'settings' => $project->settings,
                'created_at' => $project->created_at->toIso8601String(),
                'updated_at' => $project->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update a project.
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,paused',
        ]);

        $project->update($request->only(['name', 'status']));

        return response()->json([
            'message' => 'Project updated successfully.',
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'status' => $project->status,
                'updated_at' => $project->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a project.
     */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        // Soft delete - set status to deleted
        $project->update(['status' => 'deleted']);

        return response()->json([
            'message' => 'Project deleted successfully.',
        ]);
    }
}

