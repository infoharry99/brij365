<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\EmployeeTaxProfileVerificationData;
use App\Models\EmployeeTaxProfile;
use App\Models\User;
use App\Services\Payroll\EmployeeTaxInputService;
use Illuminate\Http\Request;

final class VerifyEmployeeTaxProfile
{
    public function __construct(private readonly EmployeeTaxInputService $service) {}

    public function execute(EmployeeTaxProfile $profile, User $actor, EmployeeTaxProfileVerificationData $data, ?Request $request = null): EmployeeTaxProfile
    {
        return $this->service->verify($profile, $actor, $data, $request);
    }
}
