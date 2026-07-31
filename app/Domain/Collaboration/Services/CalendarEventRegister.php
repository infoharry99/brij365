<?php

namespace App\Domain\Collaboration\Services;

use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\Collaboration\CollaborationService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;

final class CalendarEventRegister
{
    public function __construct(
        private readonly CollaborationService $collaboration,
        private readonly CompanyScopeService $companyScope,
        private readonly PaginationPolicy $pagination,
    ) {}

    /** @param array<string, mixed> $filters @return LengthAwarePaginator<int, CalendarEvent> */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $query = CalendarEvent::query()->with($this->collaboration->eventRelations());
        $this->companyScope->apply($query, $user);

        return $query
            ->when(! $user->hasPermission('collaboration.view') && ! $user->hasPermission('collaboration.manage'), fn ($builder) => $builder->where('organizer_user_id', $user->id))
            ->when(isset($filters['status']), fn ($builder) => $builder->where('status', $filters['status']))
            ->when(isset($filters['event_type']), fn ($builder) => $builder->where('event_type', $filters['event_type']))
            ->when(isset($filters['project_id']), fn ($builder) => $builder->where('project_id', $filters['project_id']))
            ->when(isset($filters['date_from']), fn ($builder) => $builder->whereDate('starts_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($builder) => $builder->whereDate('starts_at', '<=', $filters['date_to']))
            ->when(isset($filters['q']), fn ($builder) => $builder->where(fn ($nested) => $nested->where('title', 'like', '%'.$filters['q'].'%')->orWhere('event_number', 'like', '%'.$filters['q'].'%')))
            ->orderBy('starts_at')
            ->paginate($this->pagination->workspacePerPage());
    }
}
