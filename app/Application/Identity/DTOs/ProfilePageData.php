<?php

namespace App\Application\Identity\DTOs;

final readonly class ProfilePageData
{
    /**
     * @param list<AccountActivityData> $recentActivity
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $initial,
        public string $status,
        public string $assignedRole,
        public string $activeRole,
        public string $accessLevel,
        public int $permissionCount,
        public string $companyCode,
        public string $companyName,
        public string $employeeCode,
        public string $projectContext,
        public bool $emailVerified,
        public array $recentActivity,
    ) {
    }
}
