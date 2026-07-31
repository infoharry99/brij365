<?php
namespace App\Application\Legal\Data;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
final readonly class ReraRegistrationWorkspaceData
{
    public function __construct(public LengthAwarePaginator $registrations,public array $filters,public Collection $projects,public array $statuses,public array $abilities) {}
    public function toView(): array { return array_merge(get_object_vars($this),['canCreateRegistration'=>$this->abilities['canCreateRegistration']??false]); }
}
