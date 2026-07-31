<?php

namespace App\Application\Procurement\Data;

use App\Models\User;
use Illuminate\Http\Request;

final readonly class ProcurementCommandData
{
    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes, public User $actor, public ?Request $request = null) {}
}
