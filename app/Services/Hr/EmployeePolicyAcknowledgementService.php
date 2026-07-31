<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeePolicyAcknowledgement;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SystemSettingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeePolicyAcknowledgementService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SystemSettingResolver $settings,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function policyCatalogue(Employee $employee): array
    {
        $policy = $this->attendanceGeofencePolicy($employee);

        return [$policy];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function acknowledge(array $data, User $actor, ?Request $request = null): EmployeePolicyAcknowledgement
    {
        return DB::transaction(function () use ($data, $actor, $request): EmployeePolicyAcknowledgement {
            $employee = Employee::query()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $policy = $this->policyFor($employee, $data['policy_key']);

            if ((int) $data['policy_version'] !== (int) $policy['policy_version']) {
                throw ValidationException::withMessages([
                    'policy_version' => 'The selected policy version is no longer active. Reload the acknowledgement list and try again.',
                ]);
            }

            $acknowledgement = EmployeePolicyAcknowledgement::query()
                ->where('employee_id', $employee->id)
                ->where('policy_key', $policy['policy_key'])
                ->where('policy_version', (int) $data['policy_version'])
                ->first();

            if (! $acknowledgement) {
                $acknowledgement = new EmployeePolicyAcknowledgement([
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                    'policy_key' => $policy['policy_key'],
                    'policy_title' => $policy['policy_title'],
                    'policy_version' => (int) $data['policy_version'],
                ]);
            }

            $history = $acknowledgement->workflow_history ?? [];
            $history[] = [
                'status' => 'acknowledged',
                'actor_user_id' => $actor->id,
                'actor' => $actor->name,
                'note' => 'Employee acknowledged policy version.',
                'at' => now()->toISOString(),
            ];

            $acknowledgement->forceFill([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'acknowledged_by_user_id' => $actor->id,
                'policy_title' => $policy['policy_title'],
                'status' => 'acknowledged',
                'acknowledgement_note' => $data['acknowledgement_note'] ?? null,
                'policy_snapshot' => $policy,
                'workflow_history' => $history,
                'acknowledged_at' => now(),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'hr.policy_acknowledgement.acknowledged',
                'Acknowledged employee policy',
                $acknowledgement,
                [
                    'employee_code' => $employee->employee_code,
                    'policy_key' => $acknowledgement->policy_key,
                    'policy_version' => $acknowledgement->policy_version,
                ],
                $request,
            );

            return $acknowledgement->load(['employee', 'acknowledgedBy']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function policyFor(Employee $employee, string $policyKey): array
    {
        return match ($policyKey) {
            'hr.attendance_geofence_policy' => $this->attendanceGeofencePolicy($employee),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceGeofencePolicy(Employee $employee): array
    {
        $default = [
            'policy_key' => 'hr.attendance_geofence_policy',
            'policy_title' => 'Attendance & Geofence Policy',
            'policy_version' => 1,
            'summary' => 'Defines attendance regularization, site geofence evidence, late/early exceptions, and manager/HR approval expectations.',
            'required_for_self_service' => true,
            'effective_from' => now()->startOfYear()->toDateString(),
        ];

        $configured = $this->settings->value($employee->company_id, 'hr.attendance_geofence_policy', $default);

        return array_replace($default, array_intersect_key($configured, $default));
    }
}
