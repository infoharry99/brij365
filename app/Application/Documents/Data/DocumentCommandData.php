<?php

namespace App\Application\Documents\Data;

use App\Models\User;
use Illuminate\Http\Request;

final readonly class DocumentCommandData
{
    public function __construct(
        public array $attributes,
        public User $actor,
        public Request $request,
    ) {}
}
