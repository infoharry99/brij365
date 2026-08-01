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
        $companyId = $task?->company_id ?? $actor->company_id;

        $query = User::query()
            ->with(['role', 'employee'])
            ->where('status', 'active')
            ->where(function ($q) use ($companyId) {
                if ($companyId) {
                    $q->where('company_id', $companyId)
                        ->orWhereNull('company_id');
                }
            })
            ->orderBy('name');

        return $query->get()
            ->reject(function (User $candidate): bool {
                if ($candidate->isDirector()) {
                    return false;
                }

                $permissions = $candidate->role?->permissions ?? [];
                return in_array('partner.portal', $permissions, true) || in_array('buyer.view', $permissions, true);
            })
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
