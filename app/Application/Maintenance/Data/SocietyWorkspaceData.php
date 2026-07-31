<?php
namespace App\Application\Maintenance\Data;
use Illuminate\Pagination\LengthAwarePaginator; use Illuminate\Support\Collection;
final readonly class SocietyWorkspaceData { public function __construct(public LengthAwarePaginator $societies,public array $filters,public Collection $projects,public array $statuses,public array $associationTypes,public array $abilities) {} public function toView(): array { return array_merge(get_object_vars($this),['canCreateSociety'=>$this->abilities['canCreateSociety']??false]); } }
