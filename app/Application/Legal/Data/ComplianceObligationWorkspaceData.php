<?php
namespace App\Application\Legal\Data;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
final readonly class ComplianceObligationWorkspaceData
{
    public function __construct(public LengthAwarePaginator $obligations,public array $filters,public Collection $projects,public Collection $assignees,public array $statuses,public array $frequencies,public array $priorities,public array $abilities) {}
    public function toView(): array { return array_merge(get_object_vars($this),['canCreateObligation'=>$this->abilities['canCreateObligation']??false]); }
}
