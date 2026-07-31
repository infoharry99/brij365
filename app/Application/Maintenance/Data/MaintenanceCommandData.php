<?php
namespace App\Application\Maintenance\Data;
use App\Models\User; use Illuminate\Http\Request;
final readonly class MaintenanceCommandData { public function __construct(public array $attributes,public User $actor,public Request $request) {} }
