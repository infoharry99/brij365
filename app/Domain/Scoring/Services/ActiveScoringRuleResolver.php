<?php

namespace App\Domain\Scoring\Services;

use App\Models\ScoringRule;
use Illuminate\Support\Facades\Cache;

final class ActiveScoringRuleResolver
{
    public function __construct(private readonly ScoringRuleIntegrityService $integrity)
    {
    }

    public function resolve(int $companyId, string $ruleKey): ?ScoringRule
    {
        $cacheKey = "scoring.active.{$companyId}.{$ruleKey}";
        $ruleId = Cache::remember($cacheKey, now()->addMinutes(15), static fn (): ?int => ScoringRule::query()
            ->where('company_id', $companyId)
            ->where('rule_key', $ruleKey)
            ->where('status', 'active')
            ->latest('version')
            ->value('id'));

        $rule = $ruleId === null ? null : ScoringRule::query()
            ->whereKey($ruleId)
            ->where('company_id', $companyId)
            ->where('rule_key', $ruleKey)
            ->where('status', 'active')
            ->first();

        if ($ruleId !== null && $rule === null) {
            Cache::forget($cacheKey);
            $rule = ScoringRule::query()
                ->where('company_id', $companyId)
                ->where('rule_key', $ruleKey)
                ->where('status', 'active')
                ->latest('version')
                ->first();

            Cache::put($cacheKey, $rule?->id, now()->addMinutes(15));
        }

        if ($rule !== null) {
            $this->integrity->assertUntampered($rule);
        }

        return $rule;
    }
}
