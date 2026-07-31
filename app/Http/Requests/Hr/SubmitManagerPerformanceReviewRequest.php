<?php

namespace App\Http\Requests\Hr;

use App\Application\Hr\Data\ManagerPerformanceReviewData;
use App\Models\PerformanceReview;
use Illuminate\Foundation\Http\FormRequest;

class SubmitManagerPerformanceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('performanceReview');

        return $review instanceof PerformanceReview
            && $this->user()?->can('submitManager', $review) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'manager_score' => ['required', 'numeric', 'min:1', 'max:10'],
            'manager_comments' => ['required', 'string', 'max:3000'],
            'kpis' => ['nullable', 'array'],
            'kpis.*.name' => ['required_with:kpis', 'string', 'max:160'],
            'kpis.*.target' => ['nullable', 'string', 'max:255'],
            'kpis.*.weight' => ['required_with:kpis', 'numeric', 'min:0', 'max:100'],
            'kpis.*.actual' => ['nullable', 'string', 'max:255'],
            'kpis.*.score' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'scoring_inputs' => ['nullable', 'array:'.implode(',', ManagerPerformanceReviewData::ALLOWED_SCORING_INPUTS)],
            'scoring_inputs.kpi_achievement' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'scoring_inputs.kra_achievement' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'scoring_inputs.competencies' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'scoring_inputs.behaviour' => ['nullable', 'numeric', 'min:1', 'max:10'],
        ];
    }
}
