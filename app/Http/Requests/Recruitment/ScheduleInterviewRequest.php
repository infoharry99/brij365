<?php

namespace App\Http\Requests\Recruitment;

use App\Domain\Hr\Services\ActiveInternalUserEligibility;
use App\Domain\Recruitment\Services\InterviewScheduleAvailability;
use App\Models\Candidate;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ScheduleInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Candidate::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'integer', Rule::exists('candidates', 'id')],
            'round_name' => ['required', 'string', 'max:120'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'mode' => ['required', 'string', Rule::in(['phone', 'video', 'in_person'])],
            'venue_or_link' => ['nullable', 'string', 'max:500'],
            'panel_user_ids' => ['required', 'array', 'min:1', 'max:10'],
            'panel_user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $candidate = Candidate::query()->with('jobOpening:id,project_id')->whereKey($this->integer('candidate_id'))->first();

                if (
                    ! $candidate
                    || ! $user
                    || ! app(CompanyScopeService::class)->allows($user, $candidate->company_id)
                    || $candidate->status !== 'active'
                ) {
                    $validator->errors()->add('candidate_id', 'The selected candidate is not active for your company.');

                    return;
                }

                $companyId = $candidate->company_id;
                $scheduledAt = $this->date('scheduled_at');
                $durationMinutes = $this->integer('duration_minutes');
                if (! $scheduledAt || $durationMinutes < 15 || $durationMinutes > 480) {
                    return;
                }

                $panelIds = collect($this->input('panel_user_ids', []))->map(fn ($id): int => (int) $id)->unique()->values();

                try {
                    app(ActiveInternalUserEligibility::class)->assertIdsEligible(
                        $user,
                        $panelIds->all(),
                        $companyId,
                        'panel_user_ids',
                        'Every interview panel member must be an active internal user in the candidate company.',
                    );
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    foreach ($exception->errors() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add($field, $message);
                        }
                    }
                }

                if (! $validator->errors()->has('panel_user_ids')) {
                    $conflicts = app(InterviewScheduleAvailability::class)->inspect(
                        $candidate,
                        $panelIds->all(),
                        $scheduledAt,
                        $durationMinutes,
                    );

                    foreach ($conflicts->validationMessages() as $field => $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            },
        ];
    }
}
