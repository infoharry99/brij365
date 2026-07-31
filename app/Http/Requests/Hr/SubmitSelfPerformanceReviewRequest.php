<?php

namespace App\Http\Requests\Hr;

use App\Models\PerformanceReview;
use Illuminate\Foundation\Http\FormRequest;

class SubmitSelfPerformanceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('performanceReview');

        return $review instanceof PerformanceReview
            && $this->user()?->can('submitSelf', $review) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'self_score' => ['required', 'numeric', 'min:1', 'max:10'],
            'kra_summary' => ['nullable', 'array'],
            'kra_summary.achievements' => ['nullable', 'string', 'max:2000'],
            'kra_summary.challenges' => ['nullable', 'string', 'max:2000'],
            'kra_summary.support_needed' => ['nullable', 'string', 'max:2000'],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'improvement_areas' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
