<?php

namespace App\Application\Hr\Data;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class PolicyAcknowledgementRegisterData
{
    public function __construct(public LengthAwarePaginator $acknowledgements, public array $policies) {}
}
