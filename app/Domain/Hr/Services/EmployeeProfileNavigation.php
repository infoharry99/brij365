<?php

namespace App\Domain\Hr\Services;

use App\Models\Employee;
use App\Models\User;

final class EmployeeProfileNavigation
{
    public function __construct(private readonly EmployeeFieldVisibility $fieldVisibility) {}

    /**
     * Build the Employee 360 destinations that the actor is authorized to open.
     *
     * @return array<int, array{key: string, label: string, url: string}>
     */
    public function links(Employee $employee, User $actor, bool $selfService = false): array
    {
        if (! $actor->can('view', $employee)) {
            return [];
        }

        $links = [
            [true, 'overview', 'Overview', $selfService ? route('hr.employees.me.profile') : route('hr.employees.show', $employee)],
            [$this->fieldVisibility->canViewSensitiveProfile($actor, $employee), 'work-profile', 'Work profile', route('hr.employees.profile-sections.show', $employee)],
            [! $selfService, 'movements', 'Lifecycle & movements', route('hr.employees.movements.index', $employee)],
            [true, 'documents', 'Documents', route('hr.employees.documents.index', $employee)],
            [$this->fieldVisibility->canViewCompensation($actor, $employee), 'payroll', 'Payroll summary', route('hr.employees.payroll-summary.show', $employee)],
            [$this->canViewAudit($actor), 'audit', 'Activity history', route('hr.employees.audit-events.index', $employee)],
        ];

        return collect($links)
            ->filter(static fn (array $link): bool => $link[0] === true)
            ->map(static fn (array $link): array => [
                'key' => $link[1],
                'label' => $link[2],
                'url' => $link[3],
            ])
            ->values()
            ->all();
    }

    public function isSelfServiceOnly(Employee $employee, User $actor): bool
    {
        return $employee->user_id === $actor->id
            && $actor->hasPermission('employee.self_service')
            && ! $actor->hasPermission('*')
            && ! $actor->hasPermission('hr.view')
            && ! $actor->hasPermission('hr.manage')
            && ! $actor->hasPermission('payroll.view')
            && ! $actor->hasPermission('payroll.manage')
            && ! $actor->hasPermission('payroll.approve');
    }

    private function canViewAudit(User $actor): bool
    {
        return $actor->hasPermission('*')
            || $actor->hasPermission('audit.view')
            || $actor->hasPermission('hr.manage');
    }
}
