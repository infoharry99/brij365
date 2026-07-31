<?php

namespace App\Application\Payroll\Actions;

use App\Models\EmployeeTaxProfile;
use App\Models\User;
use App\Services\Payroll\EmployeeTaxInputService;
use Illuminate\Http\Request;

final class LockEmployeeTaxProfile
{
    public function __construct(private readonly EmployeeTaxInputService $service) {}

    public function execute(EmployeeTaxProfile $profile, User $actor, int $lockVersion, ?Request $request = null): EmployeeTaxProfile
    {
        return $this->service->lock($profile, $actor, $lockVersion, $request);
    }
}
