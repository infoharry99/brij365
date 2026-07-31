<?php
namespace App\Application\Legal\Data;
use App\Models\User;
use Illuminate\Http\Request;
final readonly class LegalCommandData
{
    public function __construct(public array $attributes, public User $actor, public Request $request) {}
}
