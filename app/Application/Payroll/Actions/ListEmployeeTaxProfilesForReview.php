<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\EmployeeTaxProfileReviewWorkspaceData;
use App\Domain\Payroll\Services\EmployeeTaxInputRegister;
use App\Models\EmployeeTaxProfile;
use App\Models\User;

final class ListEmployeeTaxProfilesForReview
{
    public function __construct(private readonly EmployeeTaxInputRegister $register) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $actor, array $filters, ?EmployeeTaxProfile $selected = null): EmployeeTaxProfileReviewWorkspaceData
    {
        return $this->register->review($actor, $filters, $selected);
    }
}
