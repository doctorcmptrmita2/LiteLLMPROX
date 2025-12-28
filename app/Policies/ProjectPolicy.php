<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine if user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Owner can view
        return $user->id === $project->user_id;
    }

    /**
     * Determine if user can create projects.
     */
    public function create(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Determine if user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        // Admin can update all
        if ($user->isAdmin()) {
            return true;
        }

        // Owner can update
        return $user->id === $project->user_id;
    }

    /**
     * Determine if user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        // Admin can delete all
        if ($user->isAdmin()) {
            return true;
        }

        // Owner can delete
        return $user->id === $project->user_id;
    }
}



