<?php

namespace App\Services\Recruitment;

use App\Models\Interview;
use App\Models\User;
use Illuminate\Support\Collection;

class InterviewPanelHydrator
{
    /**
     * @param iterable<int, Interview> $interviews
     */
    public function hydrate(iterable $interviews): void
    {
        $collection = $interviews instanceof Collection ? $interviews : collect($interviews);

        if ($collection->isEmpty()) {
            return;
        }

        $panelIds = $collection
            ->flatMap(fn (Interview $interview): array => $interview->panel_user_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($panelIds->isEmpty()) {
            $collection->each(fn (Interview $interview) => $interview->setRelation('panelUsers', collect()));

            return;
        }

        $usersById = User::query()
            ->whereIn('id', $panelIds)
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $collection->each(function (Interview $interview) use ($usersById): void {
            $panelUsers = collect($interview->panel_user_ids ?? [])
                ->map(fn ($id) => $usersById->get((int) $id))
                ->filter()
                ->values();

            $interview->setRelation('panelUsers', $panelUsers);
        });
    }
}
