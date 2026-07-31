<?php

namespace App\Domain\Scoring\Services;

use App\Models\ScoringRule;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class ScoringRuleEffectiveSlotGuard
{
    private const GOVERNED_STATUSES = [
        'approved',
        'scheduled',
        'active',
        'superseded',
        'retired',
    ];

    /**
     * The caller must hold the company/family transaction lock before invoking this guard.
     *
     * @throws ValidationException
     */
    public function assertAvailableAndOrdered(ScoringRule $rule, CarbonInterface $effectiveAt): void
    {
        $family = ScoringRule::query()
            ->where('company_id', $rule->company_id)
            ->where('rule_key', $rule->rule_key)
            ->whereKeyNot($rule->id)
            ->whereIn('status', self::GOVERNED_STATUSES)
            ->whereNotNull('effective_at')
            ->orderBy('version')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'version', 'status', 'effective_at']);

        foreach ($family as $version) {
            if ($version->effective_at->equalTo($effectiveAt)) {
                throw ValidationException::withMessages([
                    'effective_at' => 'Another governed version of this scoring rule already uses the selected effective time.',
                ]);
            }

            if ((int) $version->version < (int) $rule->version && $version->effective_at->greaterThan($effectiveAt)) {
                throw ValidationException::withMessages([
                    'effective_at' => 'A newer scoring-rule version must become effective after every earlier governed version.',
                ]);
            }

            if ((int) $version->version > (int) $rule->version && $version->effective_at->lessThan($effectiveAt)) {
                throw ValidationException::withMessages([
                    'effective_at' => 'This scoring-rule version cannot become effective after a later governed version.',
                ]);
            }
        }
    }
}
