<?php

namespace App\Http\Requests\Hr\Concerns;

use App\Models\PerformanceReview;
use Closure;

trait ValidatesPerformanceReviewVersion
{
    /** @return array<int, mixed> */
    protected function performanceReviewVersionRules(?PerformanceReview $review): array
    {
        return [
            'required',
            'integer',
            'min:1',
            static function (string $attribute, mixed $value, Closure $fail) use ($review): void {
                if ($review instanceof PerformanceReview && (int) $value !== (int) $review->lock_version) {
                    $fail('This performance review changed after you opened it. Refresh the review before continuing.');
                }
            },
        ];
    }
}
