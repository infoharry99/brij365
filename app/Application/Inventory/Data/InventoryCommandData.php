<?php

namespace App\Application\Inventory\Data;

use App\Models\User;
use Illuminate\Http\Request;

final readonly class InventoryCommandData
{
    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes, public User $actor, public ?Request $request = null) {}
}
