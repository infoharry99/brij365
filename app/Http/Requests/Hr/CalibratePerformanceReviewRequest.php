<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Hr\Concerns\ValidatesPerformanceReviewVersion;
use App\Models\PerformanceReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CalibratePerformanceReviewRequest extends FormRequest
{
    use ValidatesPerformanceReviewVersion;

    public function authorize(): bool
    {
        $review = $this->route('performanceReview');

        return $review instanceof PerformanceReview && $this->user()?->can('calibrate', $review) === true;
    }

    public function rules(): array
    {
        $review = $this->route('performanceReview');

        return [
            'lock_version' => $this->performanceReviewVersionRules($review instanceof PerformanceReview ? $review : null),
            'hr_calibration' => ['required', 'numeric', 'min:0', 'max:100'],
            'hr_comments' => ['required', 'string', 'min:12', 'max:3000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $review = $this->route('performanceReview');
            if (! $review instanceof PerformanceReview || ! is_numeric($this->input('hr_calibration'))) {
                return;
            }

            $review->loadMissing('cycle');
            $score = (float) $this->input('hr_calibration');
            $minimum = (float) ($review->cycle?->rating_scale_min ?? 1);
            $maximum = (float) ($review->cycle?->rating_scale_max ?? 5);
            if ($score < $minimum || $score > $maximum) {
                $validator->errors()->add('hr_calibration', "The HR calibration score must be between {$minimum} and {$maximum}.");
            }
        }];
    }
}
