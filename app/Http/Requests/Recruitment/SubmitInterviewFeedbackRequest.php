<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Interview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubmitInterviewFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $interview = $this->route('interview');

        return $interview instanceof Interview
            && ($this->user()?->can('submitFeedback', $interview) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'recommendation' => ['required', 'string', Rule::in(['selected', 'second_round', 'hold', 'rejected'])],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'concerns' => ['nullable', 'string', 'max:2000'],
            'feedback_note' => ['nullable', 'string', 'max:3000'],
            'next_action' => ['nullable', 'string', 'max:1000'],
            'panel_weight' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'competency_scores' => ['nullable', 'array'],
            'competency_scores.role_competency' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'competency_scores.technical_capability' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'competency_scores.communication' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'competency_scores.culture_fit' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'competency_scores.problem_solving' => ['nullable', 'numeric', 'min:1', 'max:5'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $interview = $this->route('interview');
                $actor = $this->user();

                if (! $interview instanceof Interview || ! $actor) {
                    $validator->errors()->add('interview', 'The selected interview is invalid.');

                    return;
                }

                if (! in_array($interview->status, ['scheduled', 'rescheduled', 'completed'], true)) {
                    $validator->errors()->add('interview', 'Feedback can be submitted only for scheduled interviews.');
                }

                $feedbackEntries = collect($interview->feedback['entries'] ?? []);

                if ($feedbackEntries->contains(fn ($entry): bool => (int) ($entry['user_id'] ?? 0) === (int) $actor->id)) {
                    $validator->errors()->add('interview', 'This panel member has already submitted feedback for the interview.');
                }

                $panelIds = collect($interview->panel_user_ids ?? [])->map(fn ($id): int => (int) $id)->unique();
                $submittedPanelIds = $feedbackEntries
                    ->pluck('user_id')
                    ->map(fn ($id): int => (int) $id)
                    ->unique();

                if ($panelIds->isNotEmpty() && $submittedPanelIds->intersect($panelIds)->count() >= $panelIds->count()) {
                    $validator->errors()->add('interview', 'All panel feedback has already been submitted for this interview.');
                }
            },
        ];
    }
}
