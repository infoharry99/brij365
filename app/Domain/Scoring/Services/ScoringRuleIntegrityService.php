<?php

namespace App\Domain\Scoring\Services;

use App\Models\ScoringRule;
use Illuminate\Validation\ValidationException;

final readonly class ScoringRuleIntegrityService
{
    public const ERROR_KEY = 'scoring_rule_integrity';

    public function __construct(private ScoringConfigurationChecksum $checksum)
    {
    }

    public function expectedChecksum(ScoringRule $rule): string
    {
        return $this->checksum->make($rule->configuration ?? []);
    }

    public function isUntampered(ScoringRule $rule): bool
    {
        $stored = (string) $rule->configuration_checksum;

        return strlen($stored) === 64 && hash_equals($stored, $this->expectedChecksum($rule));
    }

    /**
     * @throws ValidationException
     */
    public function assertUntampered(ScoringRule $rule): void
    {
        if ($this->isUntampered($rule)) {
            return;
        }

        throw ValidationException::withMessages([
            self::ERROR_KEY => 'The scoring rule failed its integrity check. Create a new governed version before using or advancing it.',
        ]);
    }
}
