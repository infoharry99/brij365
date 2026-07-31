<?php

namespace App\Application\Scoring\Actions;

use App\Domain\Scoring\Services\ActiveScoringRuleResolver;
use App\Domain\Scoring\Services\ScoringSubjectRegistry;
use App\Models\ScoreSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final readonly class RefreshCurrentScore
{
    public function __construct(
        private ActiveScoringRuleResolver $activeRules,
        private ScoringSubjectRegistry $subjects,
        private CalculateAndStoreScore $calculate,
    ) {}

    public function executeWhenReady(string $ruleKey, Model $source): ?ScoreSnapshot
    {
        $companyId = (int) $source->getAttribute('company_id');
        $rule = $this->activeRules->resolve($companyId, $ruleKey);
        if ($rule === null) {
            return null;
        }

        try {
            $subject = $this->subjects->subject($rule, $source->fresh());
        } catch (ValidationException $exception) {
            if ($exception->errors() !== [] && collect(array_keys($exception->errors()))->every(
                static fn (string $key): bool => $key === 'source_inputs',
            )) {
                return null;
            }

            throw $exception;
        }

        return $this->calculate->handle(
            $companyId,
            $ruleKey,
            $subject->type,
            $subject->id,
            $subject->inputs,
            $subject->metadata,
        );
    }
}
