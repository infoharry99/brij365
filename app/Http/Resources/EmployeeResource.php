<?php

namespace App\Http\Resources;

use App\Domain\Hr\Services\EmployeeFieldVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $isRegisterResponse = $request->routeIs('hr.employees.index');
        $visibility = app(EmployeeFieldVisibility::class);
        $canViewCompensation = $actor !== null && $visibility->canViewCompensation($actor, $this->resource);
        $canViewSensitive = $actor !== null && $visibility->canViewSensitiveProfile($actor);
        $sensitiveProfile = $this->sensitive_profile ?? [];

        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code,
            'name' => $this->name,
            'designation' => $this->designation,
            'department' => $this->department,
            'grade' => $this->grade,
            'employment_type' => $this->employment_type,
            'status' => $this->status,
            'lock_version' => (int) $this->lock_version,
            'joined_on' => $this->joined_on?->toDateString(),
            'statutory_state' => $this->statutory_state,
            'monthly_ctc' => $this->when($canViewCompensation, fn () => (float) $this->monthly_ctc),
            'sensitive_profile' => $this->when($canViewSensitive && ! $isRegisterResponse, $sensitiveProfile),
            'sensitive_profile_masked' => $this->when(! $canViewSensitive && ! $isRegisterResponse && $sensitiveProfile !== [], fn () => $this->maskedSensitiveProfile($sensitiveProfile)),
            'company' => $this->whenLoaded('company', fn (): ?array => $this->company ? [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
                'state' => $this->company->state,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn (): ?array => $this->branch ? [
                'id' => $this->branch->id,
                'code' => $this->branch->code,
                'name' => $this->branch->name,
                'city' => $this->branch->city,
            ] : null),
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
                'city' => $this->project->city,
            ] : null),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'status' => $this->user->status,
                'role' => $this->user->relationLoaded('role') && $this->user->role ? [
                    'slug' => $this->user->role->slug,
                    'name' => $this->user->role->name,
                ] : null,
            ] : null),
            'manager' => $this->whenLoaded('manager', fn (): ?array => $this->manager ? [
                'id' => $this->manager->id,
                'employee_code' => $this->manager->employee_code,
                'name' => $this->manager->name,
                'designation' => $this->manager->designation,
            ] : null),
            'direct_reports_count' => $this->whenCounted('directReports'),
            'documents_count' => $this->whenCounted('managedDocuments'),
            'assets_count' => $this->whenCounted('assets'),
            'leave_requests_count' => $this->whenCounted('leaveRequests'),
            'attendance_records_count' => $this->whenCounted('attendanceRecords'),
            'payroll_items_count' => $this->whenCounted('payrollRunItems'),
            'tax_documents_count' => $this->whenCounted('taxDocuments'),
            'confirmation_cases_count' => $this->whenCounted('confirmationCases'),
            'separation_settlements_count' => $this->whenCounted('separationSettlements'),
            'expense_claims_count' => $this->whenCounted('expenseClaims'),
            'loans_count' => $this->whenCounted('loans'),
            'performance_reviews_count' => $this->whenCounted('performanceReviews'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function maskedSensitiveProfile(array $profile): array
    {
        $masked = [];

        foreach ($profile as $key => $value) {
            $masked[$key] = is_string($value) ? $this->maskValue($value) : 'restricted';
        }

        return $masked;
    }

    private function maskValue(string $value): string
    {
        if (str_contains($value, "\u{2022}") || mb_strlen($value) <= 4) {
            return $value;
        }

        return str_repeat("\u{2022}", max(mb_strlen($value) - 4, 0)).mb_substr($value, -4);
    }
}
