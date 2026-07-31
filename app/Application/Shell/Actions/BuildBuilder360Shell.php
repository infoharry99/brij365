<?php

namespace App\Application\Shell\Actions;

use App\Application\Shell\DTOs\Builder360ShellData;
use App\Application\Shell\DTOs\ShellNavigationGroupData;
use App\Application\Shell\DTOs\ShellNavigationItemData;
use App\Application\Shell\DTOs\ShellProjectOptionData;
use App\Application\Shell\DTOs\ShellRoleOptionData;
use App\Support\Builder360Icon;
use App\Support\Builder360ModuleNavigation;
use Illuminate\Support\Str;

final class BuildBuilder360Shell
{
    /** @param array<string, mixed> $bootstrap */
    public function handle(array $bootstrap, string $theme): Builder360ShellData
    {
        $user = is_array($bootstrap['user'] ?? null) ? $bootstrap['user'] : [];
        $activeRole = is_array($bootstrap['active_role_context'] ?? null) ? $bootstrap['active_role_context'] : [];
        $activeProject = is_array($bootstrap['active_project_context'] ?? null) ? $bootstrap['active_project_context'] : [];

        $navigation = collect($bootstrap['modules'] ?? [])->map(function (array $group) use ($bootstrap): ShellNavigationGroupData {
            $items = collect($group['items'] ?? [])->map(function (array $item) use ($bootstrap): ShellNavigationItemData {
                $routeKey = $item['route'] ?? $item['slug'] ?? null;
                $url = Builder360ModuleNavigation::urlFor(is_string($routeKey) ? $routeKey : null, $bootstrap);
                $isClassicDashboard = $routeKey === 'dashboard' && request()->routeIs('builder360.classic.dashboard');

                return new ShellNavigationItemData(
                    slug: (string) ($item['slug'] ?? $routeKey ?? 'module'),
                    name: (string) ($item['name'] ?? $item['slug'] ?? 'Module'),
                    iconClass: Builder360Icon::classFor(is_string($item['icon'] ?? null) ? $item['icon'] : null),
                    url: $isClassicDashboard ? route('builder360.classic.dashboard') : $url,
                    active: $isClassicDashboard || Builder360ModuleNavigation::isActive(is_string($routeKey) ? $routeKey : null),
                );
            })->values()->all();

            return new ShellNavigationGroupData(
                label: (string) ($group['group'] ?? 'Modules'),
                items: $items,
            );
        })->values()->all();

        $roles = collect($bootstrap['roles'] ?? [])->map(static fn (array $role): ShellRoleOptionData => new ShellRoleOptionData(
            slug: (string) ($role['slug'] ?? ''),
            name: (string) ($role['name'] ?? 'Role'),
        ))->filter(static fn (ShellRoleOptionData $role): bool => $role->slug !== '')->values()->all();

        $projects = collect($bootstrap['projects'] ?? [])->map(static fn (array $project): ShellProjectOptionData => new ShellProjectOptionData(
            id: (int) ($project['id'] ?? 0),
            code: (string) ($project['code'] ?? $project['name'] ?? 'Project'),
            name: (string) ($project['name'] ?? $project['code'] ?? 'Project'),
        ))->filter(static fn (ShellProjectOptionData $project): bool => $project->id > 0)->values()->all();

        $userName = (string) ($user['name'] ?? 'User');
        $projectName = $activeProject['project_name'] ?? $activeProject['project_code'] ?? null;

        return new Builder360ShellData(
            theme: $theme,
            userName: $userName,
            userInitial: Str::upper(Str::substr($userName, 0, 1)),
            activeRoleSlug: (string) ($activeRole['role_slug'] ?? $user['role'] ?? ''),
            activeRoleName: (string) ($activeRole['role_name'] ?? $user['role'] ?? 'Role'),
            canSwitchRoles: (bool) ($activeRole['can_switch_roles'] ?? false),
            roles: $roles,
            activeProjectId: is_numeric($activeProject['project_id'] ?? null) ? (int) $activeProject['project_id'] : null,
            activeProjectLabel: is_string($projectName) && $projectName !== '' ? $projectName : 'All Projects',
            canSwitchProjects: (bool) ($activeProject['can_switch_projects'] ?? false),
            projects: $projects,
            unreadNotifications: max(0, (int) data_get($bootstrap, 'notifications.summary.unread', 0)),
            navigation: $navigation,
        );
    }
}
