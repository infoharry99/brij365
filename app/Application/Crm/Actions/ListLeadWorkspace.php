<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\LeadWorkspaceData;
use App\Domain\Crm\Services\CrmWorkspaceOptions;
use App\Domain\Crm\Services\LeadRegister;
use App\Models\Lead;
use App\Models\User;

final class ListLeadWorkspace
{
    public function __construct(private readonly LeadRegister $register, private readonly CrmWorkspaceOptions $options) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters): LeadWorkspaceData
    {
        return new LeadWorkspaceData(
            leads: $this->register->for($user, $filters),
            filters: $filters,
            companies: $this->options->companies($user),
            projects: $this->options->projects($user),
            campaigns: $this->options->campaigns($user),
            partners: $this->options->partners($user),
            sources: $this->options->leadSources($user),
            stages: $this->options->stages(),
            statuses: $this->options->statuses(),
            canCreate: $user->can('create', Lead::class),
        );
    }
}
