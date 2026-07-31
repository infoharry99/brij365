<?php

namespace App\Domain\Collaboration\Services;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Support\Collection;

final class CollaborationWorkspaceOptions
{
    // Yeh external roles hain — sabhi internal roles dikhenge
    private const EXTERNAL_ROLE_SLUGS = [
        'channel_partner',
        'executive_partner_broker',
        'buyer',
    ];

    public function __construct(private readonly CompanyScopeService $companyScope) {}

    /** @return Collection<int, Company> */
    public function companies(User $user): Collection
    {
        $query = Company::query()->orderBy('name');
        $this->companyScope->apply($query, $user, 'id');

        return $query->get();
    }

    /** @return Collection<int, Project> */
    public function projects(User $user): Collection
    {
        $query = Project::query()->orderBy('code');
        $this->companyScope->apply($query, $user);

        return $query->get();
    }

    /** @return Collection<int, User> */
    public function internalUsers(User $user): Collection
    {
        $query = User::query()
            ->with(['role', 'employee'])
            ->where('status', 'active')
            ->orderBy('name');

        if (($companyId = $this->companyScope->companyIdFor($user)) !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->get()->reject(
            // Sirf role slug se filter karo
            // Permission check NAHI — director ka ["*"] wildcard sabko true karta hai
            fn (User $option): bool => in_array(
                $option->role?->slug,
                self::EXTERNAL_ROLE_SLUGS,
                true
            )
        )->values();
    }

    /** @return array<string, string> */
    public function taskStatuses(): array { return \App\Domain\Collaboration\TaskLifecycle::statuses(); }

    /** @return array<string, string> */
    public function taskPriorities(): array { return ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical']; }

    /** @return array<string, string> */
    public function taskModuleContexts(): array
    {
        return ['crm' => 'CRM', 'sales' => 'Sales', 'collections' => 'Collections', 'construction' => 'Construction', 'possession' => 'Possession', 'maintenance' => 'Maintenance', 'mailbox' => 'Mailbox', 'chat' => 'Chat', 'hr' => 'HR', 'finance' => 'Finance', 'legal' => 'Legal'];
    }

    /** @return array<string, string> */
    public function eventTypes(): array { return ['meeting' => 'Meeting', 'site_visit' => 'Site Visit', 'interview' => 'Interview', 'payment_follow_up' => 'Payment Follow-up', 'inspection' => 'Inspection', 'training' => 'Training']; }

    /** @return array<string, string> */
    public function eventStatuses(): array { return ['scheduled' => 'Scheduled', 'rescheduled' => 'Rescheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled']; }
}