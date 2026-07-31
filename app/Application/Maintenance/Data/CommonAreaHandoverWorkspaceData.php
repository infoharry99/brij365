<?php
namespace App\Application\Maintenance\Data;
use Illuminate\Pagination\LengthAwarePaginator; use Illuminate\Support\Collection;
final readonly class CommonAreaHandoverWorkspaceData { public function __construct(public LengthAwarePaginator $items,public array $filters,public Collection $projects,public Collection $societies,public array $statuses) {} public function toView(): array { return get_object_vars($this); } }
