<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Hr\Concerns\ValidatesPerformanceReviewVersion;
use App\Models\PerformanceReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ClosePerformanceReviewRequest extends FormRequest
{
    use ValidatesPerformanceReviewVersion;

    public function authorize(): bool
    {
        $review = $this->route('performanceReview');

        return $review instanceof PerformanceReview
            && $this->user()?->can('close', $review) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $review = $this->route('performanceReview');

        return [
            'lock_version' => $this->performanceReviewVersionRules($review instanceof PerformanceReview ? $review : null),
            // Retained as optional compatibility inputs for historical, unscored reviews.
            // Governed reviews ignore these values and finalize from their pinned snapshot.
            'final_score' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_with:final_rating'],
            'final_rating' => ['nullable', 'string', 'max:40', 'required_with:final_score'],
            'hr_comments' => ['required', 'string', 'max:3000'],
            'pip_required' => ['nullable', 'boolean'],
            'pip_plan' => ['nullable', 'array'],
            'pip_plan.objectives' => ['nullable', 'array'],
            'pip_plan.objectives.*' => ['string', 'max:500'],
            'pip_plan.starts_on' => ['nullable', 'date'],
            'pip_plan.ends_on' => ['nullable', 'date', 'after_or_equal:pip_plan.starts_on'],
            'pip_plan.review_frequency' => ['nullable', 'string', 'max:80'],
            'pip_plan.owner' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function after(): array
    {
        return [$this->validatePipPlan(...)];
    }

    protected function validatePipPlan(Validator $validator): void
    {
        if ($this->boolean('pip_required') && ! $this->hasMeaningfulPipPlan()) {
            $validator->errors()->add('pip_plan', 'A PIP plan is required when the review is marked for PIP.');
        }
    }

    private function hasMeaningfulPipPlan(): bool
    {
        return collect($this->input('pip_plan.objectives', []))
            ->contains(static fn (mixed $objective): bool => is_string($objective) && trim($objective) !== '');
    }
}
