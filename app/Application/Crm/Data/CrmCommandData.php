<?php

namespace App\Application\Crm\Data;

use App\Models\User;
use Illuminate\Http\Request;

final readonly class CrmCommandData
{
    /** @param array<string,mixed> $attributes */
    public function __construct(public array $attributes, public User $actor, public ?Request $request = null) {}
}
