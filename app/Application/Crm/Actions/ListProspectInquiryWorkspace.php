<?php
namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\ProspectInquiryWorkspaceData;
use App\Domain\Crm\Services\CrmWorkspaceOptions;
use App\Domain\Crm\Services\ProspectInquiryRegister;
use App\Models\ProspectInquiry;
use App\Models\User;

final class ListProspectInquiryWorkspace
{
    public function __construct(private readonly ProspectInquiryRegister $register, private readonly CrmWorkspaceOptions $options) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters): ProspectInquiryWorkspaceData
    {
        return new ProspectInquiryWorkspaceData(
            inquiries: $this->register->for($user, $filters), filters: $filters,
            projects: $this->options->projects($user), campaigns: $this->options->campaigns($user),
            assignees: $this->register->assignees($user), sources: $this->register->sources($user), channels: $this->register->channels($user),
            statuses: [ProspectInquiry::STATUS_NEW => 'New', ProspectInquiry::STATUS_DUPLICATE => 'Duplicate', ProspectInquiry::STATUS_ASSIGNED => 'Assigned', ProspectInquiry::STATUS_CONTACTED => 'Contacted', ProspectInquiry::STATUS_QUALIFIED => 'Qualified', ProspectInquiry::STATUS_CONVERTED => 'Converted', ProspectInquiry::STATUS_CLOSED_UNQUALIFIED => 'Closed - unqualified', ProspectInquiry::STATUS_CLOSED_DUPLICATE => 'Closed - duplicate'],
            metrics: $this->register->metrics($user), canManage: $user->hasPermission('crm.manage'),
        );
    }
}
