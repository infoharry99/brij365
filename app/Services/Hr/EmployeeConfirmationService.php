<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeConfirmationCase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeConfirmationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createCase(array $data, User $actor, ?Request $request = null): EmployeeConfirmationCase
    {
        return DB::transaction(function () use ($data, $actor, $request): EmployeeConfirmationCase {
            $employee = Employee::query()
                ->whereKey($data['employee_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $manager = $this->resolveManager($employee, $data['manager_employee_id'] ?? null);

            $case = EmployeeConfirmationCase::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'manager_employee_id' => $manager?->id,
                'created_by_user_id' => $actor->id,
                'case_number' => $this->nextCaseNumber(),
                'status' => 'due',
                'probation_starts_on' => $data['probation_starts_on'] ?? $employee->joined_on?->toDateString(),
                'probation_ends_on' => $data['probation_ends_on'],
                'review_due_on' => $data['review_due_on'] ?? $data['probation_ends_on'],
                'workflow_history' => [
                    $this->workflowEvent('due', $actor, 'Confirmation case created.'),
                ],
            ]);

            if ($manager?->user) {
                $this->notifications->sendToUser($manager->user, [
                    'category' => 'hr',
                    'severity' => 'info',
                    'title' => 'Confirmation review due',
                    'body' => "Confirmation review is due for {$employee->name}.",
                    'action_url' => route('hr.confirmation-cases.index', ['manager_employee_id' => $manager->id], false),
                    'payload' => ['case_number' => $case->case_number],
                ], $actor, $case);
            }

            $this->auditLogger->record(
                $actor,
                'hr.confirmation.created',
                'Created employee confirmation case',
                $case,
                [
                    'case_number' => $case->case_number,
                    'employee_code' => $employee->employee_code,
                    'review_due_on' => $case->review_due_on?->toDateString(),
                ],
                $request,
            );

            return $case->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function recommend(EmployeeConfirmationCase $employeeConfirmationCase, array $data, User $actor, ?Request $request = null): EmployeeConfirmationCase
    {
        return DB::transaction(function () use ($employeeConfirmationCase, $data, $actor, $request): EmployeeConfirmationCase {
            $case = EmployeeConfirmationCase::query()
                ->with(['employee', 'managerEmployee'])
                ->whereKey($employeeConfirmationCase->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($case->status !== 'due') {
                throw ValidationException::withMessages(['confirmation_case' => 'Only due confirmation cases can be recommended by the manager.']);
            }

            $history = $case->workflow_history ?? [];
            $history[] = $this->workflowEvent('manager_recommended', $actor, $data['manager_comments']);

            $case->forceFill([
                'status' => 'manager_recommended',
                'manager_reviewer_user_id' => $actor->id,
                'manager_recommendation' => $data['manager_recommendation'],
                'manager_comments' => $data['manager_comments'],
                'review_scores' => $data['review_scores'] ?? [],
                'manager_submitted_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->notifications->sendToPermission(['hr.manage'], [
                'category' => 'hr',
                'severity' => 'info',
                'title' => 'Confirmation case ready for HR decision',
                'body' => "{$case->employee->name}'s confirmation review was recommended by the manager.",
                'action_url' => route('hr.confirmation-cases.index', ['status' => 'manager_recommended'], false),
                'payload' => ['case_number' => $case->case_number, 'recommendation' => $case->manager_recommendation],
            ], $actor, $case, $case->company_id);

            $this->auditLogger->record(
                $actor,
                'hr.confirmation.manager_recommended',
                'Submitted manager confirmation recommendation',
                $case,
                [
                    'case_number' => $case->case_number,
                    'employee_code' => $case->employee?->employee_code,
                    'recommendation' => $case->manager_recommendation,
                ],
                $request,
            );

            return $case->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function decide(EmployeeConfirmationCase $employeeConfirmationCase, array $data, User $actor, ?Request $request = null): EmployeeConfirmationCase
    {
        return DB::transaction(function () use ($employeeConfirmationCase, $data, $actor, $request): EmployeeConfirmationCase {
            $case = EmployeeConfirmationCase::query()
                ->with(['employee.user', 'managerEmployee'])
                ->whereKey($employeeConfirmationCase->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($case->status !== 'manager_recommended') {
                throw ValidationException::withMessages(['confirmation_case' => 'HR can decide only manager-recommended confirmation cases.']);
            }

            $decision = $data['hr_decision'];
            $status = match ($decision) {
                'confirm' => 'confirmed',
                'extend' => 'extended',
                'reject' => 'rejected',
                default => throw ValidationException::withMessages(['hr_decision' => 'Invalid HR decision.']),
            };

            $history = $case->workflow_history ?? [];
            $history[] = $this->workflowEvent($status, $actor, $data['hr_comments']);

            $case->forceFill([
                'status' => $status,
                'hr_reviewer_user_id' => $actor->id,
                'hr_decision' => $decision,
                'hr_comments' => $data['hr_comments'],
                'confirmation_effective_on' => $data['confirmation_effective_on'] ?? null,
                'extended_until' => $data['extended_until'] ?? null,
                'confirmation_letter_reference' => $data['confirmation_letter_reference'] ?? null,
                'hr_decided_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->applyDecisionToEmployee($case, $actor);

            if ($case->employee?->user) {
                $this->notifications->sendToUser($case->employee->user, [
                    'category' => 'hr',
                    'severity' => $status === 'confirmed' ? 'success' : 'warning',
                    'title' => 'Confirmation decision completed',
                    'body' => "Your confirmation case was {$status}.",
                    'action_url' => route('hr.confirmation-cases.index', ['employee_id' => $case->employee_id], false),
                    'payload' => ['case_number' => $case->case_number, 'status' => $status],
                ], $actor, $case);
            }

            $this->auditLogger->record(
                $actor,
                'hr.confirmation.decided',
                'Recorded HR confirmation decision',
                $case,
                [
                    'case_number' => $case->case_number,
                    'employee_code' => $case->employee?->employee_code,
                    'decision' => $decision,
                    'status' => $status,
                ],
                $request,
            );

            return $case->load($this->relations());
        });
    }

    private function resolveManager(Employee $employee, ?int $managerEmployeeId): ?Employee
    {
        $managerId = $managerEmployeeId ?: $employee->manager_employee_id;

        if (! $managerId) {
            return null;
        }

        $manager = Employee::query()
            ->where('company_id', $employee->company_id)
            ->whereKey($managerId)
            ->firstOrFail();

        if ((int) $manager->id === (int) $employee->id) {
            throw ValidationException::withMessages(['manager_employee_id' => 'An employee cannot be their own confirmation manager.']);
        }

        return $manager;
    }

    private function applyDecisionToEmployee(EmployeeConfirmationCase $case, User $actor): void
    {
        $employee = Employee::query()->whereKey($case->employee_id)->lockForUpdate()->firstOrFail();
        $profile = $employee->sensitive_profile ?? [];

        $profile['confirmation'] = [
            'case_number' => $case->case_number,
            'status' => $case->status,
            'decision' => $case->hr_decision,
            'confirmation_effective_on' => $case->confirmation_effective_on?->toDateString(),
            'extended_until' => $case->extended_until?->toDateString(),
            'letter_reference' => $case->confirmation_letter_reference,
            'decided_by' => $actor->name,
            'decided_at' => now()->toISOString(),
        ];

        $employee->forceFill([
            'status' => $case->status === 'rejected' ? 'on_notice' : 'active',
            'sensitive_profile' => $profile,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor_user_id' => $actor->id,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function nextCaseNumber(): string
    {
        return sprintf('CNF-%05d', EmployeeConfirmationCase::query()->withTrashed()->count() + 10001);
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return ['company', 'employee.user', 'managerEmployee.user', 'createdBy', 'managerReviewer', 'hrReviewer'];
    }
}
