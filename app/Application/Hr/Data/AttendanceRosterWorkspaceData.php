<?php

namespace App\Application\Hr\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class AttendanceRosterWorkspaceData
{
    /**
     * @param Collection<int, mixed> $employees
     * @param Collection<int, mixed> $shifts
     * @param Collection<int, mixed> $availableEntries
     * @param Collection<int, mixed> $draftRosters
     * @param array<string, bool> $abilities
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public LengthAwarePaginator $rosters,
        public LengthAwarePaginator $rotations,
        public LengthAwarePaginator $swaps,
        public LengthAwarePaginator $periodLocks,
        public Collection $employees,
        public Collection $shifts,
        public Collection $availableEntries,
        public Collection $draftRosters,
        public string $governedTimezone,
        public array $abilities,
        public array $filters,
    ) {}

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return get_object_vars($this);
    }
}
