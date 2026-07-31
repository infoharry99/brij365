<?php

namespace App\Application\Scoring\Actions;

use App\Domain\Scoring\Services\ActiveScoringRuleResolver;
use App\Domain\Scoring\Services\ScoringSubjectRegistry;
use App\Models\EmployeeExitInterview;
use App\Models\ScoreSnapshot;
use Illuminate\Validation\ValidationException;

final readonly class RefreshExitFeedbackScore
{
    public function __construct(
        private ActiveScoringRuleResolver $activeRules,
        private ScoringSubjectRegistry $subjects,
        private CalculateAndStoreScore $calculate,
    ) {}

    public function executeWhenReady(EmployeeExitInterview $interview): ?ScoreSnapshot
    {
        $rule = $this->activeRules->resolve((int) $interview->company_id, 'exit_feedback');
        $department = $interview->employee()->value('department');
        if ($rule === null || ! is_string($department) || $department === '') {
            return null;
        }

        $subjectModel = $this->subjects->eligibleQuery($rule)
            ->where('subject_key', str($department)->slug()->toString())
            ->first();
        if ($subjectModel === null) {
            return null;
        }

        try {
            $subject = $this->subjects->subject($rule, $subjectModel);
        } catch (ValidationException $exception) {
            if ($exception->errors() !== [] && collect(array_keys($exception->errors()))->every(
                static fn (string $key): bool => $key === 'source_inputs',
            )) {
                return null;
            }
            throw $exception;
        }

        return $this->calculate->handle(
            (int) $rule->company_id,
            'exit_feedback',
            $subject->type,
            $subject->id,
            $subject->inputs,
            $subject->metadata,
        );
    }
}
