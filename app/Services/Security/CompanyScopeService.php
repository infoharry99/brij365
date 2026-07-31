<?php

namespace App\Services\Security;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CompanyScopeService
{
    public function __construct(private readonly ActiveCompanyResolver $activeCompany) {}

    public function hasGlobalScope(User $user): bool
    {
        return $user->hasPermission('*') || $user->role?->scope_level === 'global';
    }

    public function hasUnrestrictedCompanyScope(User $user): bool
    {
        return ! $this->activeCompany->enabled() && $this->hasGlobalScope($user);
    }

    /**
     * Returns null for unrestricted global users and 0 for non-global users
     * without a company assignment so data access fails closed.
     */
    public function companyIdFor(User $user): ?int
    {
        if ($this->activeCompany->enabled()) {
            $activeCompanyId = $this->activeCompany->companyId();

            if ($activeCompanyId === null) {
                return 0;
            }

            if ($this->hasGlobalScope($user)) {
                return $activeCompanyId;
            }

            return (int) $user->company_id === $activeCompanyId ? $activeCompanyId : 0;
        }

        if ($this->hasGlobalScope($user)) {
            return null;
        }

        return $user->company_id ? (int) $user->company_id : 0;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, User $user, string $column = 'company_id'): Builder
    {
        $companyId = $this->companyIdFor($user);

        if ($companyId === null) {
            return $query;
        }

        return $query->where($column, $companyId);
    }

    public function allows(User $user, int|string|null $companyId): bool
    {
        if ($this->activeCompany->enabled()) {
            if (! $this->activeCompany->allows($companyId)) {
                return false;
            }

            return $this->hasGlobalScope($user)
                || (int) $user->company_id === (int) $companyId;
        }

        if ($this->hasGlobalScope($user)) {
            return true;
        }

        if ($user->company_id === null || $companyId === null) {
            return false;
        }

        return (int) $user->company_id === (int) $companyId;
    }

    /**
     * Global settings are valid read fallbacks for a user who has a real
     * company scope. Company-scoped settings must still pass the normal
     * active-company boundary.
     */
    public function allowsSettingRead(User $user, int|string|null $companyId): bool
    {
        if ($companyId !== null) {
            return $this->allowsSettingCompany($user, $companyId);
        }

        $resolvedCompanyId = $this->settingCompanyIdFor($user);

        return $resolvedCompanyId === null || $resolvedCompanyId > 0;
    }

    /**
     * Global setting mutation is reserved for an unrestricted global actor.
     * In single-company mode, every new or changed setting is bound to the
     * configured operating company.
     */
    public function allowsSettingMutation(User $user, int|string|null $companyId): bool
    {
        if ($companyId !== null) {
            return $this->allowsSettingCompany($user, $companyId);
        }

        return ! $this->activeCompany->enabled() && $user->hasPermission('*');
    }

    /**
     * System settings keep their historical stricter boundary: only an actor
     * with the wildcard permission may operate without an assigned company.
     * A global-scope role without that permission still fails closed.
     */
    public function settingCompanyIdFor(User $user): ?int
    {
        if ($this->activeCompany->enabled()) {
            $activeCompanyId = $this->activeCompany->companyId();

            if ($activeCompanyId === null) {
                return 0;
            }

            if ($user->hasPermission('*')) {
                return $activeCompanyId;
            }

            return (int) $user->company_id === $activeCompanyId ? $activeCompanyId : 0;
        }

        if ($user->hasPermission('*')) {
            return null;
        }

        return $user->company_id ? (int) $user->company_id : 0;
    }

    private function allowsSettingCompany(User $user, int|string $companyId): bool
    {
        if ($this->activeCompany->enabled()) {
            return $this->activeCompany->allows($companyId)
                && ($user->hasPermission('*') || (int) $user->company_id === (int) $companyId);
        }

        return $user->hasPermission('*')
            || ($user->company_id !== null && (int) $user->company_id === (int) $companyId);
    }

    /**
     * Prevent the wildcard-permission shortcut from crossing the configured
     * company boundary for model-specific authorization checks.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function allowsWildcardAbility(string $ability, array $arguments): bool
    {
        if (! $this->activeCompany->enabled()) {
            return true;
        }

        foreach ($arguments as $argument) {
            if ($ability === 'create' && $argument === Company::class) {
                return false;
            }

            if ($argument instanceof Company) {
                return $this->activeCompany->allows($argument->getKey());
            }

            if ($argument instanceof Model && array_key_exists('company_id', $argument->getAttributes())) {
                return $this->activeCompany->allows($argument->getAttribute('company_id'));
            }
        }

        return true;
    }
}
