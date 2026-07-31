<?php

namespace App\Application\Hr\Data;

use App\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class PolicyAcknowledgementWorkspaceData
{
    public function __construct(public LengthAwarePaginator $acknowledgements, public array $policies, public Collection $employees, public ?Employee $currentEmployee, public array $abilities, public bool $selfService) {}

    public function toView(): array { return get_object_vars($this); }
}
