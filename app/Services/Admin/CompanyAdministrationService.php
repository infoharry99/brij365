<?php

namespace App\Services\Admin;

use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyAdministrationService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createCompany(array $data, User $actor, Request $request): Company
    {
        return DB::transaction(function () use ($data, $actor, $request): Company {
            $company = Company::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?? null,
                'state' => $data['state'],
                'status' => $data['status'] ?? 'active',
            ]);

            $this->auditLogger->record(
                $actor,
                'admin.company.created',
                'Created company '.$company->code,
                $company,
                [
                    'code' => $company->code,
                    'name' => $company->name,
                    'state' => $company->state,
                    'status' => $company->status,
                ],
                $request,
            );

            return $company->loadCount(['branches', 'projects', 'users']);
        });
    }
}
