<?php

namespace App\Domain\Collaboration\Services;

use App\Models\ProjectTeamAssignment;
use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class TaskPeopleCandidates
{
    /** @return Collection<int, User> */
    public function forActor(User $actor, ?WorkTask $task = null): Collection
    {
        $query = User::query()
            ->with(['role', 'employee'])
            ->where('company_id', $task?->company_id ?? $actor->company_id)
            ->where('status', 'active')
            ->orderBy('name');

        if ($task?->project_id && ! $actor->hasPermission('collaboration.manage')) {
            $projectUserIds = ProjectTeamAssignment::query()
                ->where('project_id', $task->project_id)
                ->where('status', 'active')
                ->pluck('user_id')
                ->push($actor->id)
                ->push($task->created_by_user_id)
                ->push($task->assigned_to_user_id)
                ->filter()
                ->unique();

            $query->whereIn('id', $projectUserIds);
        }

        return $query->get()
            ->reject(fn (User $candidate): bool => $candidate->hasPermission('partner.portal') || $candidate->hasPermission('buyer.view'))
            ->values();
    }

    public function assertEligible(User $actor, WorkTask $task, User $candidate): void
    {
        $eligible = $this->forActor($actor, $task)->contains(fn (User $option): bool => (int) $option->id === (int) $candidate->id);

        if (! $eligible) {
            throw ValidationException::withMessages([
                'assigned_to_user_id' => 'The selected employee is not available for this task.',
            ]);
        }
    }
}
