<?php

namespace App\Application\Payroll\Actions;

use App\Models\EmployeeTaxProfile;
use App\Models\User;
use App\Services\Payroll\EmployeeTaxInputService;
use Illuminate\Http\Request;

final class SubmitEmployeeTaxProfile
{
    public function __construct(private readonly EmployeeTaxInputService $service) {}

    public function execute(EmployeeTaxProfile $profile, User $actor, int $lockVersion, ?Request $request = null): EmployeeTaxProfile
    {
        return $this->service->submit($profile, $actor, $lockVersion, $request);
    }
}
