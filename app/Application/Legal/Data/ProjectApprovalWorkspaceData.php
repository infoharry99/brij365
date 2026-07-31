<?php
namespace App\Application\Legal\Data;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
final readonly class ProjectApprovalWorkspaceData
{
    public function __construct(public LengthAwarePaginator $approvals,public array $filters,public Collection $projects,public array $statuses,public array $approvalTypes,public array $abilities) {}
    public function toView(): array { return array_merge(get_object_vars($this),['canCreateApproval'=>$this->abilities['canCreateApproval']??false]); }
}
