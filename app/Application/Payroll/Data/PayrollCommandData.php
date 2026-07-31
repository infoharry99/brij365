<?php

namespace App\Application\Payroll\Data;

use App\Models\User;
use Illuminate\Http\Request;

final readonly class PayrollCommandData
{
    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes, public User $actor, public ?Request $request = null) {}
}
