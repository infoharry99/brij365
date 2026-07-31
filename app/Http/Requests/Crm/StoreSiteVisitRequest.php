<?php

namespace App\Http\Requests\Crm;

use App\Models\Lead;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SiteVisit::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'visit_mode' => ['required', 'string', Rule::in(['site', 'office', 'virtual'])],
            'meeting_location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url', 'max:1024'],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'attendees' => ['nullable', 'array', 'max:20'],
            'attendees.*.name' => ['required_with:attendees', 'string', 'max:255'],
            'attendees.*.phone' => ['nullable', 'string', 'max:40'],
            'attendees.*.role' => ['nullable', 'string', 'max:80'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();
                $lead = Lead::query()->whereKey($this->integer('lead_id'))->first();

                if ($actor && $lead && ! app(CompanyScopeService::class)->allows($actor, $lead->company_id)) {
                    $validator->errors()->add('lead_id', 'The selected lead is not available for your company.');
                }

                if ($actor && $this->filled('assigned_to_user_id')) {
                    $assignee = User::query()->whereKey($this->integer('assigned_to_user_id'))->first();

                    if ($assignee && ! app(CompanyScopeService::class)->allows($actor, $assignee->company_id)) {
                        $validator->errors()->add('assigned_to_user_id', 'The assigned user must belong to your company.');
                    }
                }
            },
        ];
    }
}
