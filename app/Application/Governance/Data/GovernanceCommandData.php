<?php
namespace App\Application\Governance\Data;
use App\Models\User;
use Illuminate\Http\Request;
final readonly class GovernanceCommandData { public function __construct(public array $attributes, public User $actor, public Request $request) {} }
