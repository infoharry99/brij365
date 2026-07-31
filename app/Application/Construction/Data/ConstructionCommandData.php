<?php

namespace App\Application\Construction\Data;

use App\Models\User;
use Illuminate\Http\Request;

final readonly class ConstructionCommandData
{
    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes, public User $actor, public ?Request $request = null) {}
}
