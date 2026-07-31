<?php

namespace App\Application\Hr\Data;

final readonly class EmployeeMovementChangeData
{
    public function __construct(
        public string $label,
        public string $value,
    ) {}
}
