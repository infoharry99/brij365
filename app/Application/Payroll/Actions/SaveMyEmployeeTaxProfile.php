<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\EmployeeTaxProfileDraftData;
use App\Models\EmployeeTaxProfile;
use App\Models\User;
use App\Services\Payroll\EmployeeTaxInputService;
use Illuminate\Http\Request;

final class SaveMyEmployeeTaxProfile
{
    public function __construct(private readonly EmployeeTaxInputService $service) {}

    public function execute(EmployeeTaxProfileDraftData $data, User $actor, ?Request $request = null): EmployeeTaxProfile
    {
        return $this->service->saveDraft($data, $actor, $request);
    }
}
