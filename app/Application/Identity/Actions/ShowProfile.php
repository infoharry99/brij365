<?php

namespace App\Application\Identity\Actions;

use App\Application\Identity\DTOs\AccountActivityData;
use App\Application\Identity\DTOs\ProfilePageData;
use App\Domain\Identity\Services\ProfileContextReader;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

final class ShowProfile
{
    public function __construct(
        private readonly ProfileContextReader $contexts,
        private readonly Session $session,
    ) {
    }

    public function handle(User $user): ProfilePageData
    {
        $user->loadMissing(['role', 'company', 'employee']);

        $roleSlug = $this->session->get('builder360.selected_role_slug');
        $projectId = $this->session->get('builder360.selected_project_id');
        $context = $this->contexts->read(
            $user,
            is_string($roleSlug) ? $roleSlug : null,
            is_numeric($projectId) ? (int) $projectId : null,
        );

        $activeRole = data_get($context, 'active_role_context.role_name');
        $activeProject = data_get($context, 'active_project_context.project_name');

        $activities = AuditEvent::query()
            ->where('user_id', $user->getKey())
            ->latest()
            ->limit(12)
            ->get(['action', 'event_type', 'created_at'])
            ->map(static fn (AuditEvent $event): AccountActivityData => new AccountActivityData(
                action: (string) $event->action,
                event: Str::headline((string) $event->event_type),
                occurredAt: $event->created_at?->format('d M Y, h:i A') ?? 'Time unavailable',
            ))
            ->values()
            ->all();

        return new ProfilePageData(
                name: (string) $user->name,
                email: (string) $user->email,
                initial: Str::upper(Str::substr((string) $user->name, 0, 1)),
                status: Str::headline((string) $user->status),
                assignedRole: $user->role?->name ?? 'Not assigned',
                activeRole: is_string($activeRole) ? $activeRole : ($user->role?->name ?? 'Not assigned'),
                accessLevel: Str::headline($user->role?->scope_level ?? 'not assigned'),
                permissionCount: count($user->role?->permissions ?? []),
                companyCode: $user->company?->code ?? 'Not assigned',
                companyName: $user->company?->name ?? 'No company assigned',
                employeeCode: $user->employee?->employee_code ?? 'Not linked',
                projectContext: is_string($activeProject) ? $activeProject : 'All Projects',
                emailVerified: $user->hasVerifiedEmail(),
                recentActivity: $activities,
        );
    }
}
