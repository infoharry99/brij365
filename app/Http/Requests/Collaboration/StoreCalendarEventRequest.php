<?php

namespace App\Http\Requests\Collaboration;

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CalendarEvent::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_type' => ['required', 'string', Rule::in(['meeting', 'call', 'follow_up', 'demo', 'appointment', 'task_deadline', 'internal', 'client_event', 'reminder', 'site_visit', 'interview', 'payment_follow_up', 'inspection', 'training'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'timezone:all'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url:https', 'max:1024'],
            'visibility' => ['nullable', 'string', Rule::in(['internal', 'private'])],
            'attendees' => ['nullable', 'array', 'max:50'],
            'attendees.*.user_id' => ['required_with:attendees', 'integer', 'exists:users,id'],
            'attendees.*.response' => ['nullable', 'string', Rule::in(['pending', 'accepted', 'tentative', 'declined'])],
            'guest_emails' => ['nullable', 'string', 'max:5000'],
            'guests' => ['nullable', 'array', 'max:50'],
            'guests.*.name' => ['nullable', 'string', 'max:255'],
            'guests.*.email' => ['required_with:guests', 'email:rfc', 'max:255'],
            'reminders' => ['nullable', 'array', 'max:10'],
            'reminders.*.minutes_before' => ['required_with:reminders', 'integer', 'min:0', 'max:43200'],
            'related_type' => ['nullable', 'string', 'max:255'],
            'related_id' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.frequency' => ['nullable', Rule::in(['none', 'daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence.interval' => ['nullable', 'integer', 'min:1', 'max:52'],
            'recurrence.until_at' => ['nullable', 'date', 'after:starts_at'],
            'recurrence.occurrence_limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'client_token' => ['nullable', 'uuid'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:25600', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $guests = collect(preg_split('/[,;\r\n]+/', (string) $this->input('guest_emails', ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $email): array => ['email' => strtolower(trim($email)), 'name' => strtolower(trim($email))])
            ->unique('email')->values()->all();
        $this->merge([
            'timezone' => $this->input('timezone') ?: 'Asia/Kolkata',
            'guests' => array_values(array_merge((array) $this->input('guests', []), $guests)),
        ]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();

                if (! $actor) {
                    return;
                }

                $companyScope = app(CompanyScopeService::class);
                $explicitCompanyId = $this->filled('company_id') ? $this->integer('company_id') : null;
                $project = null;
                $hasCompanyBearingParticipant = $actor->company_id !== null;

                if ($explicitCompanyId !== null && ! $companyScope->allows($actor, $explicitCompanyId)) {
                    $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
                }

                if ($this->filled('project_id')) {
                    $project = Project::query()->whereKey($this->integer('project_id'))->first();

                    if ($project && ! $companyScope->allows($actor, $project->company_id)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                    }

                    if ($project && $explicitCompanyId !== null && (int) $project->company_id !== $explicitCompanyId) {
                        $validator->errors()->add('company_id', 'The selected company must match the selected project company.');
                    }
                }

                foreach ($this->input('attendees', []) as $index => $attendee) {
                    $user = User::query()->whereKey((int) ($attendee['user_id'] ?? 0))->first();

                    if ($user?->company_id !== null) {
                        $hasCompanyBearingParticipant = true;
                    }

                    if ($user && ! $companyScope->allows($actor, $user->company_id)) {
                        $validator->errors()->add("attendees.{$index}.user_id", 'All attendees must belong to your company.');
                    }

                    if ($user && $explicitCompanyId !== null && $user->company_id !== null && (int) $user->company_id !== $explicitCompanyId) {
                        $validator->errors()->add("attendees.{$index}.user_id", 'All attendees must belong to the selected company.');
                    }
                }

                if (
                    $companyScope->hasUnrestrictedCompanyScope($actor)
                    && $explicitCompanyId === null
                    && ! $project
                    && ! $hasCompanyBearingParticipant
                ) {
                    $validator->errors()->add('company_id', 'A company is required when creating a private company-level calendar event as a global user.');
                }
            },
        ];
    }
}
