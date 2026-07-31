<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\AttendanceRoster;
use App\Services\Hr\AttendanceRosterManager;
use InvalidArgumentException;

final class TransitionAttendanceRoster
{
    public function __construct(private readonly AttendanceRosterManager $manager) {}

    public function execute(AttendanceRoster $roster, string $target, HrCommandData $command): AttendanceRoster
    {
        $version = (int) $command->attributes['lock_version'];
        $note = $command->attributes['status_note'] ?? null;

        return match ($target) {
            'published' => $this->manager->publish($roster, $version, $command->actor, $note, $command->request),
            'locked' => $this->manager->lock($roster, $version, $command->actor, $note, $command->request),
            'reopened' => $this->manager->reopenRoster($roster, $version, $command->actor, (string) $note, $command->request),
            'cancelled' => $this->manager->cancel($roster, $version, $command->actor, (string) $note, $command->request),
            default => throw new InvalidArgumentException('Unsupported attendance roster transition.'),
        };
    }
}
