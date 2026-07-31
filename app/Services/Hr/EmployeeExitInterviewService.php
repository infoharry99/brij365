<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeExitInterview;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeExitInterviewService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
        private readonly CompanyScopeService $companyScope,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function schedule(array $data, User $actor, ?Request $request = null): EmployeeExitInterview
    {
        return DB::transaction(function () use ($data, $actor, $request): EmployeeExitInterview {
            $employee = Employee::query()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();

            $exitInterview = EmployeeExitInterview::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'employee_separation_settlement_id' => $data['employee_separation_settlement_id'] ?? null,
                'scheduled_by_user_id' => $actor->id,
                'interview_number' => $this->nextInterviewNumber(),
                'status' => 'scheduled',
                'interview_due_on' => $data['interview_due_on'],
                'questionnaire_template' => $data['questionnaire_template'] ?? $this->defaultQuestionnaire(),
                'workflow_history' => [
                    $this->workflowEvent('scheduled', $actor, $data['note'] ?? 'Exit interview scheduled.'),
                ],
            ]);

            if ($employee->user) {
                $this->notifications->sendToUser($employee->user, [
                    'category' => 'hr',
                    'severity' => 'info',
                    'title' => 'Exit interview scheduled',
                    'body' => 'Please complete your confidential exit interview questionnaire.',
                    'action_url' => route('hr.exit-interviews.index', ['employee_id' => $employee->id], false),
                    'payload' => ['interview_number' => $exitInterview->interview_number],
                ], $actor, $exitInterview);
            }

            $this->auditLogger->record(
                $actor,
                'hr.exit_interview.scheduled',
                'Scheduled employee exit interview',
                $exitInterview,
                ['interview_number' => $exitInterview->interview_number, 'employee_code' => $employee->employee_code],
                $request,
            );

            return $exitInterview->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submit(EmployeeExitInterview $employeeExitInterview, array $data, User $actor, ?Request $request = null): EmployeeExitInterview
    {
        return DB::transaction(function () use ($employeeExitInterview, $data, $actor, $request): EmployeeExitInterview {
            $exitInterview = EmployeeExitInterview::query()->whereKey($employeeExitInterview->id)->lockForUpdate()->firstOrFail();
            $history = $exitInterview->workflow_history ?? [];
            $history[] = $this->workflowEvent('submitted', $actor, 'Exit interview questionnaire submitted.');

            $exitInterview->forceFill([
                'submitted_by_user_id' => $actor->id,
                'status' => 'submitted',
                'submitted_at' => now(),
                'separation_reason' => $data['separation_reason'],
                'rehire_recommendation' => $data['rehire_recommendation'],
                'overall_experience_rating' => $data['overall_experience_rating'],
                'manager_relationship_rating' => $data['manager_relationship_rating'] ?? null,
                'workload_rating' => $data['workload_rating'] ?? null,
                'compensation_rating' => $data['compensation_rating'] ?? null,
                'public_feedback' => $data['public_feedback'] ?? null,
                'improvement_suggestions' => $data['improvement_suggestions'] ?? null,
                'confidential_responses' => $data['confidential_responses'],
                'risk_flags' => array_values(array_unique($data['risk_flags'] ?? [])),
                'scoring_inputs' => array_replace($exitInterview->scoring_inputs ?? [], $data['scoring_inputs'] ?? []),
                'workflow_history' => $history,
            ])->save();

            $this->notifications->sendToPermission(['hr.manage'], [
                'category' => 'hr',
                'severity' => $exitInterview->risk_flags ? 'warning' : 'info',
                'title' => 'Exit interview submitted',
                'body' => "{$exitInterview->employee->name}'s exit interview is ready for HR review.",
                'action_url' => route('hr.exit-interviews.index', ['status' => 'submitted'], false),
                'payload' => ['interview_number' => $exitInterview->interview_number, 'risk_flags' => $exitInterview->risk_flags ?? []],
            ], $actor, $exitInterview, $exitInterview->company_id);

            $this->auditLogger->record(
                $actor,
                'hr.exit_interview.submitted',
                'Submitted employee exit interview',
                $exitInterview,
                ['interview_number' => $exitInterview->interview_number, 'employee_id' => $exitInterview->employee_id],
                $request,
            );

            return $exitInterview->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function review(EmployeeExitInterview $employeeExitInterview, array $data, User $actor, ?Request $request = null): EmployeeExitInterview
    {
        return DB::transaction(function () use ($employeeExitInterview, $data, $actor, $request): EmployeeExitInterview {
            $exitInterview = EmployeeExitInterview::query()->whereKey($employeeExitInterview->id)->lockForUpdate()->firstOrFail();
            $history = $exitInterview->workflow_history ?? [];
            $history[] = $this->workflowEvent('reviewed', $actor, 'HR reviewed exit interview.');

            $exitInterview->forceFill([
                'reviewed_by_user_id' => $actor->id,
                'status' => 'reviewed',
                'reviewed_at' => now(),
                'hr_review_notes' => $data['hr_review_notes'],
                'action_items' => collect($data['action_items'] ?? [])
                    ->map(fn (array $item): array => [
                        'owner' => $item['owner'],
                        'action' => $item['action'],
                        'due_on' => $item['due_on'] ?? null,
                        'status' => $item['status'] ?? 'open',
                    ])
                    ->values()
                    ->all(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'hr.exit_interview.reviewed',
                'Reviewed employee exit interview',
                $exitInterview,
                ['interview_number' => $exitInterview->interview_number, 'action_item_count' => count($exitInterview->action_items ?? [])],
                $request,
            );

            return $exitInterview->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters, User $actor): array
    {
        $interviews = EmployeeExitInterview::query()
            ->with('employee')
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['department']), fn ($query) => $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('department', $filters['department'])))
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('interview_due_on', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('interview_due_on', '<=', $filters['to']));

        $this->companyScope->apply($interviews, $actor);

        $interviews = $interviews->get();

        $riskFlags = $interviews
            ->flatMap(fn (EmployeeExitInterview $interview): array => $interview->risk_flags ?? [])
            ->filter()
            ->countBy()
            ->sortDesc()
            ->all();

        return [
            'total' => $interviews->count(),
            'status_counts' => $interviews->countBy('status')->all(),
            'reason_counts' => $interviews->whereNotNull('separation_reason')->countBy('separation_reason')->all(),
            'rehire_recommendation_counts' => $interviews->whereNotNull('rehire_recommendation')->countBy('rehire_recommendation')->all(),
            'average_ratings' => [
                'overall_experience' => round((float) $interviews->whereNotNull('overall_experience_rating')->avg('overall_experience_rating'), 2),
                'manager_relationship' => round((float) $interviews->whereNotNull('manager_relationship_rating')->avg('manager_relationship_rating'), 2),
                'workload' => round((float) $interviews->whereNotNull('workload_rating')->avg('workload_rating'), 2),
                'compensation' => round((float) $interviews->whereNotNull('compensation_rating')->avg('compensation_rating'), 2),
            ],
            'risk_flag_counts' => $riskFlags,
            'department_counts' => $interviews->pluck('employee.department')->filter()->countBy()->all(),
            'open_action_items' => $interviews
                ->flatMap(fn (EmployeeExitInterview $interview): array => $interview->action_items ?? [])
                ->filter(fn (array $item): bool => ($item['status'] ?? 'open') !== 'closed')
                ->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return ['employee.user', 'separationSettlement.employee.user', 'scheduledBy', 'submittedBy', 'reviewedBy'];
    }

    private function nextInterviewNumber(): string
    {
        return sprintf('EXI-%05d', EmployeeExitInterview::query()->withTrashed()->count() + 10001);
    }

    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return ['status' => $status, 'actor_user_id' => $actor->id, 'actor' => $actor->name, 'note' => $note, 'at' => now()->toISOString()];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function defaultQuestionnaire(): array
    {
        return [
            ['key' => 'primary_reason', 'label' => 'Primary reason for leaving', 'type' => 'choice'],
            ['key' => 'work_experience', 'label' => 'How would you describe your work experience?', 'type' => 'text'],
            ['key' => 'manager_feedback', 'label' => 'Feedback about manager and support received', 'type' => 'text'],
            ['key' => 'improvement_opportunities', 'label' => 'What should the company improve?', 'type' => 'text'],
            ['key' => 'rehire_context', 'label' => 'Would you consider rejoining in future?', 'type' => 'choice'],
        ];
    }
}
