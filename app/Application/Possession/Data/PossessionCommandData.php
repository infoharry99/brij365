<?php
namespace App\Application\Possession\Data;
use App\Models\User; use Illuminate\Http\Request;
final readonly class PossessionCommandData { public function __construct(public array $attributes,public User $actor,public Request $request) {} }
