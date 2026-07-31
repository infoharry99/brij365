<?php

namespace App\Application\Recruitment\Data;

use App\Models\User;
use Illuminate\Http\Request;

final readonly class RecruitmentCommandData
{
    public function __construct(public array $attributes, public User $actor, public ?Request $request = null) {}
}
