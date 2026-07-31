<?php

namespace App\Policies;

use App\Domain\Payroll\Services\EmployeeTaxInputAccess;
use App\Models\Employee;
use App\Models\EmployeeTaxProfile;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

final class EmployeeTaxProfilePolicy
{
    public function __construct(private readonly EmployeeTaxInputAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canReview($user);
    }

    public function view(User $user, EmployeeTaxProfile $profile): bool
    {
        if ($profile->employee?->user_id === $user->id && $this->access->hasAnyExplicit($user, ['employee.self_service'])) {
            return true;
        }

        return $this->viewAny($user) && $this->inCompany($user, $profile->company_id);
    }

    public function create(User $user, Employee $employee): bool
    {
        return $this->inCompany($user, $employee->company_id)
            && (($employee->user_id === $user->id && $this->access->hasAnyExplicit($user, ['employee.self_service']))
                || $this->access->hasAnyExplicit($user, ['payroll.manage']));
    }

    public function update(User $user, EmployeeTaxProfile $profile): bool
    {
        return in_array($profile->status, [EmployeeTaxProfile::STATUS_DRAFT, EmployeeTaxProfile::STATUS_LOCKED], true)
            && $this->canManageOwnOrPayroll($user, $profile);
    }

    public function submit(User $user, EmployeeTaxProfile $profile): bool
    {
        return $profile->status === EmployeeTaxProfile::STATUS_DRAFT
            && $this->canManageOwnOrPayroll($user, $profile);
    }

    public function verify(User $user, EmployeeTaxProfile $profile): bool
    {
        return $profile->status === EmployeeTaxProfile::STATUS_SUBMITTED
            && $this->inCompany($user, $profile->company_id)
            && $this->access->hasAnyExplicit($user, ['payroll.manage', 'compliance.manage'])
            && ! in_array($user->id, [$profile->created_by_user_id, $profile->submitted_by_user_id], true);
    }

    public function lock(User $user, EmployeeTaxProfile $profile): bool
    {
        return $profile->status === EmployeeTaxProfile::STATUS_VERIFIED
            && $this->inCompany($user, $profile->company_id)
            && $this->access->hasAnyExplicit($user, ['payroll.approve', 'compliance.manage'])
            && ! in_array($user->id, [
                $profile->created_by_user_id,
                $profile->submitted_by_user_id,
                $profile->verified_by_user_id,
            ], true);
    }

    private function canManageOwnOrPayroll(User $user, EmployeeTaxProfile $profile): bool
    {
        if (! $this->inCompany($user, $profile->company_id)) {
            return false;
        }

        return ($profile->employee?->user_id === $user->id && $this->access->hasAnyExplicit($user, ['employee.self_service']))
            || $this->access->hasAnyExplicit($user, ['payroll.manage']);
    }

    private function inCompany(User $user, int $companyId): bool
    {
        return app(CompanyScopeService::class)->allows($user, $companyId);
    }
}
