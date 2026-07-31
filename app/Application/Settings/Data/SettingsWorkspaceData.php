<?php
namespace App\Application\Settings\Data;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
final readonly class SettingsWorkspaceData {
 public function __construct(public string $section,public LengthAwarePaginator $records,public array $filters,public Collection $companies,public Collection|array $groups=[],public Collection|array $keys=[],public array $statuses=[],public array $types=[],public bool $canCreate=false) {}
 public function toView(): array { return get_object_vars($this); }
}
