<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\PerformanceWorkspaceData;
use App\Domain\Hr\Services\PerformanceRegister;
use App\Models\PerformanceCycle;
use App\Models\User;

final class ListPerformanceWorkspace
{
    public function __construct(private readonly PerformanceRegister $register) {}

    public function execute(User $actor, array $filters, string $activeRegister): PerformanceWorkspaceData
    {
        $cycles = $activeRegister === 'cycles'
            ? $this->register->presentCycles($this->register->cycles($actor, $filters))
            : null;
        $reviews = $activeRegister === 'reviews'
            ? $this->register->presentReviews($actor, $this->register->reviews($actor, $filters))
            : null;

        return new PerformanceWorkspaceData(
            activeRegister: $activeRegister,
            summary: $this->register->summary($actor, $filters, $activeRegister),
            cycles: $cycles,
            reviews: $reviews,
            departmentRows: $activeRegister === 'dashboard' ? $this->register->departmentDashboard($actor, $filters) : collect(),
            companies: $activeRegister === 'cycles' ? $this->register->companies($actor) : collect(),
            projects: $activeRegister === 'cycles' ? $this->register->projects($actor) : collect(),
            employees: $activeRegister === 'reviews' ? $this->register->employees($actor) : collect(),
            managers: $activeRegister === 'reviews' ? $this->register->employees($actor) : collect(),
            departments: $this->register->departments($actor),
            activeCycles: in_array($activeRegister, ['reviews', 'dashboard'], true) ? $this->register->activeCycles($actor) : collect(),
            abilities: [
                'canCreateCycle' => $actor->can('create', PerformanceCycle::class),
                'canCreateReview' => $actor->can('create', \App\Models\PerformanceReview::class),
            ],
        );
    }
}
