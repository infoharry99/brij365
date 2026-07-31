<?php

namespace App\Application\Dashboard\Data;

use Illuminate\Http\Request;

final readonly class DashboardContextData
{
    /**
     * @param  array<string, mixed>|null  $period
     */
    public function __construct(
        public ?string $roleSlug,
        public ?int $projectId,
        public ?array $period,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $roleSlug = $request->session()->get('builder360.selected_role_slug');
        $projectId = $request->session()->get('builder360.selected_project_id');
        $period = $request->session()->get('builder360.dashboard_period');

        return new self(
            roleSlug: is_string($roleSlug) ? $roleSlug : null,
            projectId: is_numeric($projectId) ? (int) $projectId : null,
            period: is_array($period) ? $period : null,
        );
    }
}
