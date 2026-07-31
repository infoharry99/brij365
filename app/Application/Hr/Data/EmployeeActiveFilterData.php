<?php

namespace App\Application\Hr\Data;

final readonly class EmployeeActiveFilterData
{
    public function __construct(
        public string $key,
        public string $label,
        public string $value,
    ) {}
}
