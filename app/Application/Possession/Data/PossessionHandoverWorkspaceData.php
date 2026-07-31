<?php
namespace App\Application\Possession\Data;
use Illuminate\Pagination\LengthAwarePaginator; use Illuminate\Support\Collection;
final readonly class PossessionHandoverWorkspaceData
{ public function __construct(public LengthAwarePaginator $handovers,public array $filters,public Collection $projects,public Collection $bookings,public array $statuses,public array $abilities) {} public function toView(): array { return array_merge(get_object_vars($this),['canCreateHandover'=>$this->abilities['canCreateHandover']??false]); } }
