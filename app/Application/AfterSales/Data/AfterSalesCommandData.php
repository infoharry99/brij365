<?php

namespace App\Application\AfterSales\Data;

use App\Models\User;
use Illuminate\Http\Request;

final readonly class AfterSalesCommandData
{
    public function __construct(
        public array $attributes,
        public User $actor,
        public Request $request,
    ) {}
}
