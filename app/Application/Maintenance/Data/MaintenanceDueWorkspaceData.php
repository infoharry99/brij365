<?php
namespace App\Application\Maintenance\Data;
use Illuminate\Pagination\LengthAwarePaginator; use Illuminate\Support\Collection;
final readonly class MaintenanceDueWorkspaceData { public function __construct(public LengthAwarePaginator $dues,public array $filters,public Collection $projects,public Collection $bookings,public Collection $customers,public array $statuses,public array $abilities) {} public function toView(): array { return array_merge(get_object_vars($this),['canCreateDue'=>$this->abilities['canCreateDue']??false]); } }
