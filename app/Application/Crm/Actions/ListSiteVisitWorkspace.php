<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\SiteVisitWorkspaceData;
use App\Models\Lead;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Crm\LeadEngagementService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;

final class ListSiteVisitWorkspace
{
    public function __construct(private readonly LeadEngagementService $engagement, private readonly CompanyScopeService $companyScope, private readonly PaginationPolicy $pagination) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters): SiteVisitWorkspaceData
    {
        $query = SiteVisit::query()->with($this->engagement->siteVisitRelations());
        $this->companyScope->apply($query, $user);
        $visits = $query
            ->when(isset($filters['lead_id']), fn ($builder) => $builder->where('lead_id', $filters['lead_id']))
            ->when(isset($filters['project_id']), fn ($builder) => $builder->where('project_id', $filters['project_id']))
            ->when(isset($filters['assigned_to_user_id']), fn ($builder) => $builder->where('assigned_to_user_id', $filters['assigned_to_user_id']))
            ->when(isset($filters['status']), fn ($builder) => $builder->where('status', $filters['status']))
            ->when(isset($filters['visit_mode']), fn ($builder) => $builder->where('visit_mode', $filters['visit_mode']))
            ->when(isset($filters['date_from']), fn ($builder) => $builder->whereDate('scheduled_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($builder) => $builder->whereDate('scheduled_at', '<=', $filters['date_to']))
            ->orderBy('scheduled_at')->paginate($this->pagination->workspacePerPage());

        $leadQuery = Lead::query()->with(['customer:id,name,email,phone', 'project:id,code,name', 'owner:id,name'])
            ->whereNotIn('status', ['won', 'lost'])->latest();
        $this->companyScope->apply($leadQuery, $user);

        $assigneeQuery = User::query()->with('role:id,name,slug,permissions')->whereNotNull('company_id')->where('status', 'active')->orderBy('name');
        $this->companyScope->apply($assigneeQuery, $user);
        $assignees = $assigneeQuery->get(['id', 'company_id', 'role_id', 'name', 'email'])
            ->filter(fn (User $option): bool => ! in_array('partner.portal', $option->role?->permissions ?? [], true) && ! in_array('buyer.view', $option->role?->permissions ?? [], true))->values();

        return new SiteVisitWorkspaceData(
            visits: $visits,
            filters: $filters,
            leads: $leadQuery->limit(100)->get(),
            assignees: $assignees,
            visitModes: ['site' => 'Site visit', 'office' => 'Office meeting', 'virtual' => 'Virtual meeting'],
            statuses: ['scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No show'],
            outcomes: ['interested' => 'Interested', 'follow_up_required' => 'Follow-up required', 'booking_expected' => 'Booking expected', 'not_interested' => 'Not interested', 'no_show' => 'No show'],
            canSchedule: $user->can('create', SiteVisit::class),
        );
    }
}
