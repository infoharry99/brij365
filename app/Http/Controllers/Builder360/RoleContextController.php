<?php

namespace App\Http\Controllers\Builder360;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Builder360\Builder360Bootstrap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleContextController extends Controller
{
    public function show(Request $request, Builder360Bootstrap $bootstrap): JsonResponse
    {
        $roleSlug = $request->session()->get('builder360.selected_role_slug');
        $projectId = $this->selectedProjectIdFromSession($request);
        $dashboardPeriod = $this->selectedDashboardPeriodFromSession($request);

        $payload = $bootstrap->forRoleContext($request->user(), is_string($roleSlug) ? $roleSlug : null, $projectId, $dashboardPeriod);
        $this->syncSelectedProjectSession($request, $payload);
        $this->syncDashboardPeriodSession($request, $payload);

        return response()->json($payload);
    }

    public function store(Request $request, Builder360Bootstrap $bootstrap, AuditLogger $auditLogger): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'role_slug' => ['required', 'string', 'max:80'],
        ]);

        $payload = $bootstrap->forRoleContext($request->user(), $validated['role_slug'], $this->selectedProjectIdFromSession($request), $this->selectedDashboardPeriodFromSession($request));
        $context = $payload['active_role_context'] ?? [];

        $request->session()->put('builder360.selected_role_slug', $context['role_slug'] ?? $validated['role_slug']);
        $this->syncSelectedProjectSession($request, $payload);
        $this->syncDashboardPeriodSession($request, $payload);

        $auditLogger->record(
            $request->user(),
            'dashboard.role_context.changed',
            'Changed Builder360 dashboard role context',
            null,
            [
                'selected_role_slug' => $context['role_slug'] ?? $validated['role_slug'],
                'selected_role_name' => $context['role_name'] ?? null,
                'effective_user_id' => $context['effective_user_id'] ?? null,
                'actor_user_id' => $request->user()?->id,
                'company_id' => $request->user()?->company_id,
            ],
            $request,
        );

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return redirect()->route('builder360.dashboard');
    }

    public function storeProject(Request $request, Builder360Bootstrap $bootstrap, AuditLogger $auditLogger): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable'],
        ]);

        $roleSlug = $request->session()->get('builder360.selected_role_slug');
        $roleSlug = is_string($roleSlug) ? $roleSlug : null;
        $rawProjectId = $validated['project_id'] ?? null;
        $projectId = null;

        if ($rawProjectId !== null && $rawProjectId !== '' && $rawProjectId !== 'all') {
            if (! is_numeric($rawProjectId) || (int) $rawProjectId < 1) {
                throw ValidationException::withMessages(['project_id' => 'Select a valid project or All Projects.']);
            }

            $projectId = (int) $rawProjectId;

            if (! $bootstrap->projectIsVisibleForRoleContext($request->user(), $roleSlug, $projectId)) {
                throw ValidationException::withMessages(['project_id' => 'The selected project is not available for your current role context.']);
            }
        }

        $payload = $bootstrap->forRoleContext($request->user(), $roleSlug, $projectId, $this->selectedDashboardPeriodFromSession($request));
        $this->syncSelectedProjectSession($request, $payload);
        $this->syncDashboardPeriodSession($request, $payload);

        $context = $payload['active_project_context'] ?? [];

        $auditLogger->record(
            $request->user(),
            'dashboard.project_context.changed',
            'Changed Builder360 dashboard project context',
            null,
            [
                'selected_project_id' => $context['project_id'] ?? null,
                'selected_project_code' => $context['project_code'] ?? null,
                'mode' => $context['mode'] ?? null,
                'actor_user_id' => $request->user()?->id,
                'role_slug' => $payload['active_role_context']['role_slug'] ?? $request->user()?->role?->slug,
            ],
            $request,
        );

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return redirect()->back()->with('status', 'Project view updated.');
    }

    public function storeDashboard(Request $request, Builder360Bootstrap $bootstrap, AuditLogger $auditLogger): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'period_key' => ['required', 'string', 'in:today,this_week,current_month,previous_month,current_quarter,current_financial_year,custom'],
            'date_from' => ['nullable', 'required_if:period_key,custom', 'date'],
            'date_to' => ['nullable', 'required_if:period_key,custom', 'date', 'after_or_equal:date_from'],
        ]);

        $period = [
            'key' => $validated['period_key'],
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ];
        $roleSlug = $request->session()->get('builder360.selected_role_slug');
        $roleSlug = is_string($roleSlug) ? $roleSlug : null;

        $payload = $bootstrap->forRoleContext($request->user(), $roleSlug, $this->selectedProjectIdFromSession($request), $period);
        $this->syncSelectedProjectSession($request, $payload);
        $this->syncDashboardPeriodSession($request, $payload);

        $dashboardPeriod = $payload['active_dashboard_period'] ?? [];

        $auditLogger->record(
            $request->user(),
            'dashboard.period_context.changed',
            'Changed Builder360 dashboard period context',
            null,
            [
                'period_key' => $dashboardPeriod['key'] ?? $validated['period_key'],
                'date_from' => $dashboardPeriod['date_from'] ?? null,
                'date_to' => $dashboardPeriod['date_to'] ?? null,
                'actor_user_id' => $request->user()?->id,
                'role_slug' => $payload['active_role_context']['role_slug'] ?? $request->user()?->role?->slug,
                'selected_project_id' => $payload['active_project_context']['project_id'] ?? null,
            ],
            $request,
        );

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return redirect()->route('builder360.dashboard')->with('status', 'Dashboard period updated.');
    }

    private function selectedProjectIdFromSession(Request $request): ?int
    {
        $projectId = $request->session()->get('builder360.selected_project_id');

        return is_numeric($projectId) && (int) $projectId > 0 ? (int) $projectId : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectedDashboardPeriodFromSession(Request $request): ?array
    {
        $period = $request->session()->get('builder360.dashboard_period');

        return is_array($period) ? $period : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncSelectedProjectSession(Request $request, array $payload): void
    {
        $projectId = $payload['active_project_context']['project_id'] ?? null;

        if (is_numeric($projectId) && (int) $projectId > 0) {
            $request->session()->put('builder360.selected_project_id', (int) $projectId);

            return;
        }

        $request->session()->forget('builder360.selected_project_id');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncDashboardPeriodSession(Request $request, array $payload): void
    {
        $period = $payload['active_dashboard_period'] ?? null;

        if (is_array($period) && isset($period['key'], $period['date_from'], $period['date_to'])) {
            $request->session()->put('builder360.dashboard_period', [
                'key' => $period['key'],
                'date_from' => $period['date_from'],
                'date_to' => $period['date_to'],
            ]);
        }
    }
}
