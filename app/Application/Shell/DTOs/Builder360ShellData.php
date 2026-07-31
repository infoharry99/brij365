<?php

namespace App\Application\Shell\DTOs;

final readonly class Builder360ShellData
{
    /**
     * @param list<ShellNavigationGroupData> $navigation
     * @param list<ShellRoleOptionData> $roles
     * @param list<ShellProjectOptionData> $projects
     */
    public function __construct(
        public string $theme,
        public string $userName,
        public string $userInitial,
        public string $activeRoleSlug,
        public string $activeRoleName,
        public bool $canSwitchRoles,
        public array $roles,
        public ?int $activeProjectId,
        public string $activeProjectLabel,
        public bool $canSwitchProjects,
        public array $projects,
        public int $unreadNotifications,
        public array $navigation,
    ) {
    }
}
