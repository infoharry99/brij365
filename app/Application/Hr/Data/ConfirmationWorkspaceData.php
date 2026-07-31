<?php

namespace App\Application\Hr\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class ConfirmationWorkspaceData
{
    public function __construct(public LengthAwarePaginator $cases, public Collection $employees, public Collection $departments, public array $abilities, public array $caseActions) {}

    public function toView(): array { return get_object_vars($this); }
}
