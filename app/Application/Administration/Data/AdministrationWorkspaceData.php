<?php

namespace App\Application\Administration\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class AdministrationWorkspaceData
{
    public function __construct(
        public string $section,
        public LengthAwarePaginator $records,
        public array $filters = [],
        public Collection|array $companies = [],
        public Collection|array $roles = [],
        public Collection|array $permissions = [],
        public array $statuses = [],
        public array $scopeLevels = [],
        public bool $canCreate = false,
    ) {}
    public function toView(): array { return get_object_vars($this); }
}
