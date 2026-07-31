<?php

namespace App\Domain\Hr\Services;

use App\Models\PerformanceReview;
use Illuminate\Validation\ValidationException;

final class PerformanceReviewConcurrencyGuard
{
    public function assertCurrent(PerformanceReview $review, int $expectedVersion): void
    {
        if ((int) $review->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages([
                'lock_version' => 'This performance review changed after you opened it. Refresh the review before continuing.',
            ]);
        }
    }

    public function nextVersion(PerformanceReview $review): int
    {
        return (int) $review->lock_version + 1;
    }
}
