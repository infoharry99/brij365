<?php

namespace App\Http\Requests\Collaboration;

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CalendarEventIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CalendarEvent::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['scheduled', 'rescheduled', 'completed', 'cancelled'])],
            'event_type' => ['nullable', 'string', Rule::in(['meeting', 'call', 'follow_up', 'demo', 'appointment', 'task_deadline', 'internal', 'client_event', 'reminder', 'site_visit', 'interview', 'payment_follow_up', 'inspection', 'training'])],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'view' => ['nullable', 'string', Rule::in(['month', 'week', 'day', 'list', 'employee', 'team'])],
            'focus_date' => ['nullable', 'date'],
            'scope' => ['nullable', 'string', Rule::in(['all', 'team', 'mine'])],
            'participant_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'event_id' => ['nullable', 'integer', 'min:1'],
            'summary' => ['nullable', 'string', Rule::in(['today', 'upcoming', 'pending', 'completed', 'missed', 'overdue'])],
            'invitation_response' => ['nullable', 'string', Rule::in(['pending', 'accepted', 'tentative', 'declined'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['status', 'event_type', 'project_id', 'date_from', 'date_to', 'q', 'page', 'view', 'focus_date', 'scope', 'participant_user_id', 'priority', 'event_id', 'summary', 'invitation_response'],
                );

                if ($validator->errors()->isNotEmpty() || ! $this->filled('project_id') || ! $this->user()) {
                    if ($validator->errors()->isNotEmpty() || ! $this->user()) {
                        return;
                    }
                }

                if ($this->filled('participant_user_id')) {
                    $participant = \App\Models\User::query()->find($this->integer('participant_user_id'));
                    if (! $participant || ! app(CompanyScopeService::class)->allows($this->user(), $participant->company_id)) {
                        $validator->errors()->add('participant_user_id', 'The selected participant is not available for your company.');
                    }
                }

                if (! $this->filled('project_id')) {
                    return;
                }

                $projectCompanyId = Project::query()
                    ->whereKey($this->integer('project_id'))
                    ->value('company_id');

                if (! app(CompanyScopeService::class)->allows($this->user(), $projectCompanyId)) {
                    $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                }
            },
        ];
    }
}
