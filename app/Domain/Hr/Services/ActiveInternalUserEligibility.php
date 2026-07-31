<?php

namespace App\Domain\Hr\Services;

use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The single People/HR authority for selecting an active internal user.
 *
 * Role names and slugs are deliberately not used here. External portal
 * identities are identified by the permissions that grant portal access.
 */
final class ActiveInternalUserEligibility
{
    public function __construct(private readonly CompanyScopeService $scope) {}

    /** @return Collection<int, User> */
    public function forActor(User $actor, int|string|null $companyId = null): Collection
    {
        if ($companyId !== null && ! $this->scope->allows($actor, $companyId)) {
            return collect();
        }

        $query = $this->scope->apply(
            User::query()->with('role')->where('status', 'active'),
            $actor,
        );

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query
            ->orderBy('name')
            ->get(['id', 'role_id', 'company_id', 'name', 'email', 'status'])
            ->reject(fn (User $candidate): bool => $this->isExternalPortalUser($candidate))
            ->values();
    }

    public function isEligible(User $actor, User $candidate, int|string|null $companyId = null): bool
    {
        $candidate->loadMissing('role');

        if ($candidate->status !== 'active' || $candidate->company_id === null) {
            return false;
        }

        if (! $this->scope->allows($actor, $candidate->company_id)) {
            return false;
        }

        if ($companyId !== null && (int) $candidate->company_id !== (int) $companyId) {
            return false;
        }

        return ! $this->isExternalPortalUser($candidate);
    }

    public function assertEligible(
        User $actor,
        User $candidate,
        int|string|null $companyId = null,
        string $field = 'user_id',
        string $message = 'The selected user must be an active internal user in the company.',
    ): void {
        if (! $this->isEligible($actor, $candidate, $companyId)) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function assertIdsEligible(
        User $actor,
        array $userIds,
        int|string|null $companyId = null,
        string $field = 'user_ids',
        string $message = 'Every selected user must be an active internal user in the company.',
    ): void {
        $ids = collect($userIds)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $users = User::query()->with('role')->whereIn('id', $ids)->get();

        if ($users->count() !== $ids->count()
            || $users->contains(fn (User $candidate): bool => ! $this->isEligible($actor, $candidate, $companyId))) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function isExternalPortalUser(User $candidate): bool
    {
        $permissions = $candidate->role?->permissions ?? [];

        // Inspect the role's explicit portal grants rather than hasPermission().
        // Wildcard internal administrators can answer true for every ability,
        // but that does not make their identity an external portal identity.
        return in_array('partner.portal', $permissions, true)
            || in_array('buyer.view', $permissions, true);
    }
}
