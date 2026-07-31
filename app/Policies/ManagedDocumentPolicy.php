<?php

namespace App\Policies;

use App\Domain\Payroll\Services\EmployeeTaxInputAccess;
use App\Models\Booking;
use App\Models\Employee;
use App\Models\ManagedDocument;
use App\Models\User;
use App\Services\Partner\PartnerScopeService;
use App\Services\Security\CompanyScopeService;

class ManagedDocumentPolicy
{
    public function __construct(private readonly EmployeeTaxInputAccess $taxInputAccess) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('documents.view')
            || $user->hasPermission('documents.manage')
            || $user->hasPermission('documents.approve');
    }

    public function view(User $user, ManagedDocument $managedDocument): bool
    {
        if ($managedDocument->taxDeclarations()->exists()) {
            $employee = $managedDocument->relationLoaded('employeeOwner')
                ? $managedDocument->employeeOwner
                : Employee::query()->select(['id', 'company_id', 'user_id'])->find($managedDocument->owner_id);

            if ($employee?->user_id === $user->id && $this->taxInputAccess->hasAnyExplicit($user, ['employee.self_service'])) {
                return app(CompanyScopeService::class)->allows($user, $managedDocument->company_id);
            }

            return $this->taxInputAccess->canReview($user)
                && app(CompanyScopeService::class)->allows($user, $managedDocument->company_id);
        }

        if ($managedDocument->owner_type === 'employee') {
            $employee = $managedDocument->relationLoaded('employeeOwner')
                ? $managedDocument->employeeOwner
                : Employee::query()
                    ->select(['id', 'company_id', 'user_id'])
                    ->find($managedDocument->owner_id);

            if (! $employee) {
                return false;
            }

            if ($employee->user_id === $user->id && $this->taxInputAccess->hasAnyExplicit($user, ['employee.self_service'])) {
                return true;
            }

            if (($user->hasPermission('hr.view') || $user->hasPermission('hr.manage'))
                && app(CompanyScopeService::class)->allows($user, $managedDocument->company_id)) {
                return true;
            }
        }

        if ($user->hasPermission('buyer.view')) {
            $customer = $user->customer;

            if (! $customer) {
                return false;
            }

            if ($managedDocument->owner_type === 'customer') {
                return (int) $managedDocument->owner_id === (int) $customer->id;
            }

            if ($managedDocument->owner_type !== 'booking') {
                return false;
            }

            return $customer->bookings()->whereKey($managedDocument->owner_id)->exists();
        }

        if ($user->hasPermission('partner.portal') && $user->role?->scope_level === 'partner') {
            $partnerIds = app(PartnerScopeService::class)->activePartnerIdsForUser($user);

            if ($partnerIds === []) {
                return false;
            }

            if ($managedDocument->owner_type === 'partner') {
                return in_array((int) $managedDocument->owner_id, $partnerIds, true);
            }

            if ($managedDocument->owner_type !== 'booking') {
                return false;
            }

            return Booking::query()
                ->whereKey($managedDocument->owner_id)
                ->whereIn('partner_id', $partnerIds)
                ->exists();
        }

        if (! $this->viewAny($user)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $managedDocument->company_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('documents.manage');
    }

    public function approve(User $user, ManagedDocument $managedDocument): bool
    {
        if ($managedDocument->taxDeclarations()->exists()) {
            return $this->taxInputAccess->canApproveProof($user)
                && $managedDocument->status === 'submitted'
                && app(CompanyScopeService::class)->allows($user, $managedDocument->company_id)
                && $managedDocument->uploaded_by_user_id !== $user->id;
        }

        if (! $user->hasPermission('documents.approve')) {
            return false;
        }

        return $managedDocument->status === 'submitted'
            && app(CompanyScopeService::class)->allows($user, $managedDocument->company_id)
            && $managedDocument->uploaded_by_user_id !== $user->id;
    }
}
