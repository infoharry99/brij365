<?php

namespace App\Application\Finance\Data;

use App\Models\User;
use Illuminate\Http\Request;

final readonly class FinanceCommandData
{
    public function __construct(
        public array $attributes,
        public User $actor,
        public Request $request,
    ) {}
}
