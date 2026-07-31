<?php

namespace App\Application\Hr\Data;

use App\Models\User;
use Illuminate\Http\Request;

final readonly class HrCommandData
{
    public function __construct(public array $attributes, public User $actor, public ?Request $request = null) {}
}
