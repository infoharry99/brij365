<?php

namespace App\Domain\Recruitment\Data;

final readonly class InterviewScheduleConflictData
{
    public function __construct(
        public bool $candidateInterviewConflict,
        public bool $panelInterviewConflict,
        public bool $panelCalendarConflict,
    ) {}

    public function hasConflicts(): bool
    {
        return $this->candidateInterviewConflict
            || $this->panelInterviewConflict
            || $this->panelCalendarConflict;
    }

    /** @return array<string, string> */
    public function validationMessages(): array
    {
        $messages = [];

        if ($this->candidateInterviewConflict) {
            $messages['scheduled_at'] = 'The candidate already has an overlapping active interview.';
        }

        if ($this->panelInterviewConflict && $this->panelCalendarConflict) {
            $messages['panel_user_ids'] = 'One or more panel members have an overlapping interview or Builder360 Calendar event.';
        } elseif ($this->panelInterviewConflict) {
            $messages['panel_user_ids'] = 'One or more panel members have an overlapping active interview.';
        } elseif ($this->panelCalendarConflict) {
            $messages['panel_user_ids'] = 'One or more panel members have an overlapping Builder360 Calendar event.';
        }

        return $messages;
    }
}
