<?php

namespace App\Services\Operations;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\UserNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentFilePolicy;
use App\Services\Governance\ReportLimitPolicy;
use App\Services\Payroll\PayrollRunControlPolicy;
use App\Services\Security\ActiveCompanyResolver;
use App\Support\ExceptionResponseFactory;
use App\Support\MoneyInputPolicy;
use App\Support\OperationalInputPolicy;
use App\Support\PaginationPolicy;
use App\Support\PasswordPolicy;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use App\Services\Operations\SqliteBackupService;
use Throwable;

class HealthCheckService
{
    /**
     * @return array<string, mixed>
     */
    public function liveness(): array
    {
        return [
            'status' => 'ok',
            'service' => config('app.name', 'Builder360 ERP CRM'),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        $checks = [
            'database' => $this->database(),
            'migrations' => $this->migrations(),
            'session' => $this->session(),
            'auth' => $this->auth(),
            'authorization' => $this->authorization(),
            'configuration' => $this->configuration(),
            'single_company' => $this->singleCompany(),
            'audit' => $this->audit(),
            'notifications' => $this->notifications(),
            'report_limits' => $this->reportLimits(),
            'payroll_controls' => $this->payrollControls(),
            'pagination' => $this->pagination(),
            'money_input_limits' => $this->moneyInputLimits(),
            'operational_input_limits' => $this->operationalInputLimits(),
            'cache' => $this->cache(),
            'queue' => $this->queue(),
            'storage' => $this->storage(),
            'document_uploads' => $this->documentUploads(),
            'backup' => $this->backup(),
            'scheduler' => $this->scheduler(),
            'assets' => $this->assets(),
            'integrations' => $this->integrations(),
            'optimization' => $this->optimization(),
            'mail' => $this->mail(),
            'logging' => $this->logging(),
            'rate_limiting' => $this->rateLimiting(),
            'csrf' => $this->csrfProtection(),
            'exception_handling' => $this->exceptionHandling(),
            'security' => $this->security(),
        ];

        $ready = collect($checks)->every(
            fn (array $check): bool => ($check['status'] ?? null) === 'ok'
        );

        return [
            'status' => $ready ? 'ok' : 'degraded',
            'service' => config('app.name', 'Builder360 ERP CRM'),
            'environment' => (string) config('app.env', app()->environment()),
            'checked_at' => now()->toISOString(),
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function singleCompany(): array
    {
        try {
            $resolver = app(ActiveCompanyResolver::class);
            $enabled = $resolver->enabled();
            $configuredCode = $resolver->configuredCode();
            $company = $resolver->resolve();
            $ready = $enabled
                && $configuredCode !== null
                && $company !== null
                && $company->status === 'active';

            return [
                'status' => $ready ? 'ok' : 'failed',
                'enabled' => $enabled,
                'configured_code' => $configuredCode,
                'company_id' => $company?->getKey(),
                'company_code' => $company?->code,
                'company_status' => $company?->status,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => $this->safeExceptionMessage($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function audit(): array
    {
        try {
            $auditTableReady = Schema::hasTable('audit_events');
            $requiredColumns = [
                'id',
                'user_id',
                'event_type',
                'auditable_type',
                'auditable_id',
                'action',
                'metadata',
                'ip_address',
                'request_method',
                'request_path',
                'request_id',
                'user_agent',
                'created_at',
                'updated_at',
            ];
            $missingColumns = $auditTableReady
                ? collect($requiredColumns)
                    ->reject(fn (string $column): bool => Schema::hasColumn('audit_events', $column))
                    ->values()
                    ->all()
                : $requiredColumns;
            $requiredIndexes = $this->configuredStringList('builder360.audit.required_indexes');
            $presentIndexes = $auditTableReady ? $this->tableIndexNames('audit_events') : [];
            $missingIndexes = collect($requiredIndexes)
                ->reject(fn (string $index): bool => in_array($index, $presentIndexes, true))
                ->values()
                ->all();
            $redactionSelfTestPasses = app(AuditLogger::class)->redactionSelfTestPasses();
            $eventCount = $auditTableReady ? AuditEvent::query()->count() : 0;
            $lastAuditAtValue = $auditTableReady
                ? AuditEvent::query()->latest('created_at')->value('created_at')
                : null;
            $lastAuditAt = $lastAuditAtValue !== null
                ? \Illuminate\Support\Carbon::parse($lastAuditAtValue)
                : null;
            $maxActivityAgeHours = max(1, (int) config('builder360.audit.max_activity_age_hours', 24));
            $activityRecent = $lastAuditAt !== null
                && $lastAuditAt->greaterThanOrEqualTo(now()->subHours($maxActivityAgeHours));
            $productionActivityAcceptable = ! $this->isProduction() || $activityRecent;
            $ready = $auditTableReady
                && $missingColumns === []
                && $missingIndexes === []
                && $redactionSelfTestPasses
                && $productionActivityAcceptable;

            return [
                'status' => $ready ? 'ok' : 'degraded',
                'audit_events_table' => $auditTableReady ? 'present' : 'missing',
                'required_columns' => $requiredColumns,
                'missing_columns' => $missingColumns,
                'required_indexes' => $requiredIndexes,
                'missing_indexes' => $missingIndexes,
                'redaction_self_test_passes' => $redactionSelfTestPasses,
                'event_count' => $eventCount,
                'last_audit_at' => $lastAuditAt?->toISOString(),
                'max_activity_age_hours' => $maxActivityAgeHours,
                'activity_recent' => $activityRecent,
                'production_activity_acceptable' => $productionActivityAcceptable,
                'failure' => $this->auditFailureReason(
                    $auditTableReady,
                    $missingColumns,
                    $missingIndexes,
                    $redactionSelfTestPasses,
                    $productionActivityAcceptable,
                ),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'audit_events_table' => Schema::hasTable('audit_events') ? 'present' : 'missing',
                'redaction_self_test_passes' => false,
                'failure' => 'audit_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @param  array<int, string>  $missingColumns
     * @param  array<int, string>  $missingIndexes
     */
    private function auditFailureReason(
        bool $auditTableReady,
        array $missingColumns,
        array $missingIndexes,
        bool $redactionSelfTestPasses,
        bool $productionActivityAcceptable,
    ): ?string {
        if (! $auditTableReady) {
            return 'audit_table_missing';
        }

        if ($missingColumns !== []) {
            return 'audit_columns_missing';
        }

        if ($missingIndexes !== []) {
            return 'audit_indexes_missing';
        }

        if (! $redactionSelfTestPasses) {
            return 'audit_redaction_self_test_failed';
        }

        if (! $productionActivityAcceptable) {
            return 'audit_activity_stale';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function notifications(): array
    {
        try {
            $notificationsTableReady = Schema::hasTable('user_notifications');
            $requiredColumns = [
                'id',
                'company_id',
                'recipient_user_id',
                'triggered_by_user_id',
                'notification_number',
                'channel',
                'category',
                'severity',
                'status',
                'title',
                'body',
                'action_url',
                'notifiable_type',
                'notifiable_id',
                'payload',
                'read_at',
                'archived_at',
                'created_at',
                'updated_at',
                'deleted_at',
            ];
            $missingColumns = $notificationsTableReady
                ? collect($requiredColumns)
                    ->reject(fn (string $column): bool => Schema::hasColumn('user_notifications', $column))
                    ->values()
                    ->all()
                : $requiredColumns;
            $requiredIndexes = $this->configuredStringList('builder360.notifications.required_indexes');
            $presentIndexes = $notificationsTableReady ? $this->tableIndexNames('user_notifications') : [];
            $missingIndexes = collect($requiredIndexes)
                ->reject(fn (string $index): bool => in_array($index, $presentIndexes, true))
                ->values()
                ->all();
            $allowedStatuses = $this->configuredStringList('builder360.notifications.allowed_statuses');
            $notificationCount = $notificationsTableReady ? UserNotification::query()->withTrashed()->count() : 0;
            $invalidStatusCount = $notificationsTableReady && $missingColumns === []
                ? UserNotification::query()->withTrashed()->whereNotIn('status', $allowedStatuses)->count()
                : 0;
            $missingRecipientCount = $notificationsTableReady && $missingColumns === []
                ? DB::table('user_notifications')
                    ->leftJoin('users', 'users.id', '=', 'user_notifications.recipient_user_id')
                    ->whereNull('users.id')
                    ->count()
                : 0;
            $maxUnreadPerUser = max(0, (int) config('builder360.notifications.max_unread_per_user', 250));
            $unreadThresholdExceededUsers = $notificationsTableReady && $missingColumns === []
                ? UserNotification::query()
                    ->select('recipient_user_id', DB::raw('count(*) as unread_count'))
                    ->where('status', 'unread')
                    ->groupBy('recipient_user_id')
                    ->havingRaw('count(*) > ?', [$maxUnreadPerUser])
                    ->orderBy('recipient_user_id')
                    ->get()
                    ->map(fn (UserNotification $notification): array => [
                        'recipient_user_id' => (int) $notification->recipient_user_id,
                        'unread_count' => (int) $notification->unread_count,
                    ])
                    ->values()
                    ->all()
                : [];
            $lastNotificationAtValue = $notificationsTableReady
                ? UserNotification::query()->withTrashed()->latest('created_at')->value('created_at')
                : null;
            $lastNotificationAt = $lastNotificationAtValue !== null
                ? \Illuminate\Support\Carbon::parse($lastNotificationAtValue)
                : null;
            $maxActivityAgeHours = max(1, (int) config('builder360.notifications.max_activity_age_hours', 24));
            $activityRecent = $lastNotificationAt !== null
                && $lastNotificationAt->greaterThanOrEqualTo(now()->subHours($maxActivityAgeHours));
            $productionActivityAcceptable = ! $this->isProduction() || $activityRecent;
            $ready = $notificationsTableReady
                && $missingColumns === []
                && $missingIndexes === []
                && $invalidStatusCount === 0
                && $missingRecipientCount === 0
                && $unreadThresholdExceededUsers === []
                && $productionActivityAcceptable;

            return [
                'status' => $ready ? 'ok' : 'degraded',
                'user_notifications_table' => $notificationsTableReady ? 'present' : 'missing',
                'required_columns' => $requiredColumns,
                'missing_columns' => $missingColumns,
                'required_indexes' => $requiredIndexes,
                'missing_indexes' => $missingIndexes,
                'allowed_statuses' => $allowedStatuses,
                'notification_count' => $notificationCount,
                'invalid_status_count' => $invalidStatusCount,
                'missing_recipient_count' => $missingRecipientCount,
                'unread_threshold' => [
                    'max_unread_per_user' => $maxUnreadPerUser,
                    'exceeded_users' => $unreadThresholdExceededUsers,
                ],
                'last_notification_at' => $lastNotificationAt?->toISOString(),
                'max_activity_age_hours' => $maxActivityAgeHours,
                'activity_recent' => $activityRecent,
                'production_activity_acceptable' => $productionActivityAcceptable,
                'failure' => $this->notificationsFailureReason(
                    $notificationsTableReady,
                    $missingColumns,
                    $missingIndexes,
                    $invalidStatusCount,
                    $missingRecipientCount,
                    $unreadThresholdExceededUsers,
                    $productionActivityAcceptable,
                ),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'user_notifications_table' => Schema::hasTable('user_notifications') ? 'present' : 'missing',
                'failure' => 'notifications_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @param  array<int, string>  $missingColumns
     * @param  array<int, string>  $missingIndexes
     * @param  array<int, array{recipient_user_id: int, unread_count: int}>  $unreadThresholdExceededUsers
     */
    private function notificationsFailureReason(
        bool $notificationsTableReady,
        array $missingColumns,
        array $missingIndexes,
        int $invalidStatusCount,
        int $missingRecipientCount,
        array $unreadThresholdExceededUsers,
        bool $productionActivityAcceptable,
    ): ?string {
        if (! $notificationsTableReady) {
            return 'notifications_table_missing';
        }

        if ($missingColumns !== []) {
            return 'notifications_columns_missing';
        }

        if ($missingIndexes !== []) {
            return 'notifications_indexes_missing';
        }

        if ($invalidStatusCount > 0) {
            return 'notifications_invalid_statuses';
        }

        if ($missingRecipientCount > 0) {
            return 'notifications_recipient_scope_invalid';
        }

        if ($unreadThresholdExceededUsers !== []) {
            return 'notifications_unread_threshold_exceeded';
        }

        if (! $productionActivityAcceptable) {
            return 'notifications_activity_stale';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function tableIndexNames(string $table): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->pluck('name')
                ->filter()
                ->values()
                ->all();
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return collect(DB::select("SHOW INDEX FROM {$table}"))
                ->pluck('Key_name')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if ($driver === 'pgsql') {
            return collect(DB::select(
                'select indexname from pg_indexes where schemaname = current_schema() and tablename = ?',
                [$table],
            ))
                ->pluck('indexname')
                ->filter()
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(): array
    {
        try {
            $settingsTableReady = Schema::hasTable('system_settings');
            $requiredColumns = [
                'id',
                'company_id',
                'created_by_user_id',
                'approved_by_user_id',
                'scope_key',
                'setting_group',
                'setting_key',
                'label',
                'value_type',
                'value',
                'status',
                'version',
                'effective_from',
                'effective_to',
                'approved_at',
                'workflow_history',
                'metadata',
                'created_at',
                'updated_at',
                'deleted_at',
            ];
            $missingColumns = $settingsTableReady
                ? collect($requiredColumns)
                    ->reject(fn (string $column): bool => Schema::hasColumn('system_settings', $column))
                    ->values()
                    ->all()
                : $requiredColumns;
            $requiredActiveKeys = $this->configuredStringList('builder360.system_settings.required_active_keys');
            $allowedValueTypes = $this->configuredStringList('builder360.system_settings.allowed_value_types');

            if (! $settingsTableReady || $missingColumns !== []) {
                return [
                    'status' => 'degraded',
                    'system_settings_table' => $settingsTableReady ? 'present' : 'missing',
                    'required_columns' => $requiredColumns,
                    'missing_columns' => $missingColumns,
                    'required_active_keys' => $requiredActiveKeys,
                    'missing_active_keys' => $requiredActiveKeys,
                    'invalid_active_setting_keys' => [],
                    'duplicate_active_scope_keys' => [],
                    'failure' => $settingsTableReady ? 'configuration_columns_missing' : 'configuration_table_missing',
                ];
            }

            $effectiveDate = now()->toDateString();
            $activeSettings = SystemSetting::query()
                ->where('status', 'active')
                ->where(function ($query) use ($effectiveDate): void {
                    $query->whereNull('effective_from')
                        ->orWhereDate('effective_from', '<=', $effectiveDate);
                })
                ->where(function ($query) use ($effectiveDate): void {
                    $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $effectiveDate);
                })
                ->orderBy('setting_key')
                ->get(['id', 'scope_key', 'setting_group', 'setting_key', 'value_type', 'value', 'approved_at']);
            $activeSettingKeys = $activeSettings->pluck('setting_key')->unique()->values()->all();
            $missingActiveKeys = collect($requiredActiveKeys)
                ->reject(fn (string $key): bool => in_array($key, $activeSettingKeys, true))
                ->values()
                ->all();
            $invalidActiveSettingKeys = $activeSettings
                ->filter(fn (SystemSetting $setting): bool => ! $this->systemSettingIsValid($setting, $allowedValueTypes))
                ->map(fn (SystemSetting $setting): string => $setting->scope_key.':'.$setting->setting_key)
                ->values()
                ->all();
            $duplicateActiveScopeKeys = $activeSettings
                ->groupBy(fn (SystemSetting $setting): string => $setting->scope_key.':'.$setting->setting_key)
                ->filter(fn ($settings): bool => $settings->count() > 1)
                ->keys()
                ->values()
                ->all();
            $ready = $missingActiveKeys === []
                && $invalidActiveSettingKeys === []
                && $duplicateActiveScopeKeys === [];

            return [
                'status' => $ready ? 'ok' : 'degraded',
                'system_settings_table' => 'present',
                'required_columns' => $requiredColumns,
                'missing_columns' => [],
                'active_setting_count' => $activeSettings->count(),
                'required_active_keys' => $requiredActiveKeys,
                'active_setting_keys' => $activeSettingKeys,
                'missing_active_keys' => $missingActiveKeys,
                'allowed_value_types' => $allowedValueTypes,
                'invalid_active_setting_keys' => $invalidActiveSettingKeys,
                'duplicate_active_scope_keys' => $duplicateActiveScopeKeys,
                'failure' => $this->configurationFailureReason(
                    $missingActiveKeys,
                    $invalidActiveSettingKeys,
                    $duplicateActiveScopeKeys,
                ),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'system_settings_table' => Schema::hasTable('system_settings') ? 'present' : 'missing',
                'failure' => 'configuration_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @param  array<int, string>  $missingActiveKeys
     * @param  array<int, string>  $invalidActiveSettingKeys
     * @param  array<int, string>  $duplicateActiveScopeKeys
     */
    private function configurationFailureReason(
        array $missingActiveKeys,
        array $invalidActiveSettingKeys,
        array $duplicateActiveScopeKeys,
    ): ?string {
        if ($missingActiveKeys !== []) {
            return 'configuration_required_settings_missing';
        }

        if ($invalidActiveSettingKeys !== []) {
            return 'configuration_active_settings_invalid';
        }

        if ($duplicateActiveScopeKeys !== []) {
            return 'configuration_duplicate_active_scope_keys';
        }

        return null;
    }

    private function systemSettingIsValid(SystemSetting $setting, array $allowedValueTypes): bool
    {
        $scopeKey = (string) $setting->scope_key;

        if (! ($scopeKey === 'global' || preg_match('/^company:[1-9][0-9]*$/', $scopeKey) === 1)) {
            return false;
        }

        if (! in_array((string) $setting->value_type, $allowedValueTypes, true)) {
            return false;
        }

        if (! is_array($setting->value) || $setting->value === []) {
            return false;
        }

        return $setting->approved_at !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function authorization(): array
    {
        try {
            $rolesTableReady = Schema::hasTable('roles');
            $requiredColumns = ['id', 'slug', 'name', 'scope_level', 'permissions', 'is_active', 'created_at', 'updated_at'];
            $missingColumns = $rolesTableReady
                ? collect($requiredColumns)
                    ->reject(fn (string $column): bool => Schema::hasColumn('roles', $column))
                    ->values()
                    ->all()
                : $requiredColumns;

            $requiredRoleSlugs = $this->configuredStringList('builder360.authorization.required_role_slugs');
            $requiredOperationalRoleSlugs = $this->configuredStringList('builder360.authorization.required_operational_role_slugs');
            $allowedScopeLevels = $this->configuredStringList('builder360.authorization.allowed_scope_levels');

            if (! $rolesTableReady || $missingColumns !== []) {
                return [
                    'status' => 'degraded',
                    'roles_table' => $rolesTableReady ? 'present' : 'missing',
                    'required_columns' => $requiredColumns,
                    'missing_columns' => $missingColumns,
                    'required_role_slugs' => $requiredRoleSlugs,
                    'missing_required_role_slugs' => $requiredRoleSlugs,
                    'required_operational_role_slugs' => $requiredOperationalRoleSlugs,
                    'operational_roles_with_active_users' => [],
                    'missing_operational_role_user_slugs' => $requiredOperationalRoleSlugs,
                    'failure' => $rolesTableReady ? 'authorization_role_columns_missing' : 'authorization_roles_table_missing',
                ];
            }

            $activeRoles = Role::query()
                ->where('is_active', true)
                ->orderBy('slug')
                ->get(['id', 'slug', 'scope_level', 'permissions']);
            $activeRoleSlugs = $activeRoles->pluck('slug')->all();
            $missingRequiredRoleSlugs = collect($requiredRoleSlugs)
                ->reject(fn (string $slug): bool => in_array($slug, $activeRoleSlugs, true))
                ->values()
                ->all();
            $invalidPermissionRoleSlugs = $activeRoles
                ->filter(fn (Role $role): bool => ! $this->rolePermissionsAreValid($role->permissions))
                ->pluck('slug')
                ->values()
                ->all();
            $invalidScopeRoleSlugs = $activeRoles
                ->filter(fn (Role $role): bool => ! in_array((string) $role->scope_level, $allowedScopeLevels, true))
                ->pluck('slug')
                ->values()
                ->all();
            $wildcardRoleSlugs = $activeRoles
                ->filter(fn (Role $role): bool => in_array('*', $role->permissions ?? [], true))
                ->pluck('slug')
                ->values()
                ->all();
            $operationalRolesWithActiveUsers = DB::table('users')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->whereIn('roles.slug', $requiredOperationalRoleSlugs)
                ->where('roles.is_active', true)
                ->where('users.status', 'active')
                ->distinct()
                ->orderBy('roles.slug')
                ->pluck('roles.slug')
                ->all();
            $missingOperationalRoleUserSlugs = collect($requiredOperationalRoleSlugs)
                ->reject(fn (string $slug): bool => in_array($slug, $operationalRolesWithActiveUsers, true))
                ->values()
                ->all();
            $ready = $missingRequiredRoleSlugs === []
                && $invalidPermissionRoleSlugs === []
                && $invalidScopeRoleSlugs === []
                && $wildcardRoleSlugs !== []
                && $missingOperationalRoleUserSlugs === [];

            return [
                'status' => $ready ? 'ok' : 'degraded',
                'roles_table' => 'present',
                'required_columns' => $requiredColumns,
                'missing_columns' => [],
                'active_role_count' => $activeRoles->count(),
                'required_role_slugs' => $requiredRoleSlugs,
                'missing_required_role_slugs' => $missingRequiredRoleSlugs,
                'allowed_scope_levels' => $allowedScopeLevels,
                'invalid_scope_role_slugs' => $invalidScopeRoleSlugs,
                'invalid_permission_role_slugs' => $invalidPermissionRoleSlugs,
                'wildcard_role_slugs' => $wildcardRoleSlugs,
                'wildcard_role_present' => $wildcardRoleSlugs !== [],
                'required_operational_role_slugs' => $requiredOperationalRoleSlugs,
                'operational_roles_with_active_users' => $operationalRolesWithActiveUsers,
                'missing_operational_role_user_slugs' => $missingOperationalRoleUserSlugs,
                'failure' => $this->authorizationFailureReason(
                    $missingRequiredRoleSlugs,
                    $invalidPermissionRoleSlugs,
                    $invalidScopeRoleSlugs,
                    $wildcardRoleSlugs,
                    $missingOperationalRoleUserSlugs,
                ),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'roles_table' => Schema::hasTable('roles') ? 'present' : 'missing',
                'failure' => 'authorization_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @param  array<int, string>  $missingRequiredRoleSlugs
     * @param  array<int, string>  $invalidPermissionRoleSlugs
     * @param  array<int, string>  $invalidScopeRoleSlugs
     * @param  array<int, string>  $wildcardRoleSlugs
     * @param  array<int, string>  $missingOperationalRoleUserSlugs
     */
    private function authorizationFailureReason(
        array $missingRequiredRoleSlugs,
        array $invalidPermissionRoleSlugs,
        array $invalidScopeRoleSlugs,
        array $wildcardRoleSlugs,
        array $missingOperationalRoleUserSlugs,
    ): ?string {
        if ($missingRequiredRoleSlugs !== []) {
            return 'authorization_required_roles_missing';
        }

        if ($invalidPermissionRoleSlugs !== []) {
            return 'authorization_role_permissions_invalid';
        }

        if ($invalidScopeRoleSlugs !== []) {
            return 'authorization_role_scopes_invalid';
        }

        if ($wildcardRoleSlugs === []) {
            return 'authorization_wildcard_role_missing';
        }

        if ($missingOperationalRoleUserSlugs !== []) {
            return 'authorization_operational_role_users_missing';
        }

        return null;
    }

    /**
     * @param  mixed  $permissions
     */
    private function rolePermissionsAreValid(mixed $permissions): bool
    {
        if (! is_array($permissions) || $permissions === []) {
            return false;
        }

        $permissionValues = array_values($permissions);

        if (count($permissionValues) !== count(array_unique($permissionValues))) {
            return false;
        }

        foreach ($permissionValues as $permission) {
            if (! is_string($permission) || ! preg_match('/^(\*|[a-z0-9_.-]+)$/', $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function configuredStringList(string $key): array
    {
        return array_values(array_filter(
            array_map(fn (mixed $value): string => trim((string) $value), (array) config($key, [])),
            fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function backup(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'sqlite') {
            $verified = (bool) config('builder360.backups.external_database.verified', false);
            $provider = trim((string) config('builder360.backups.external_database.provider', ''));
            $runbookReference = trim((string) config('builder360.backups.external_database.runbook_reference', ''));
            $rpoMinutes = config('builder360.backups.external_database.rpo_minutes');
            $rtoMinutes = config('builder360.backups.external_database.rto_minutes');
            $lastRestoreTestedAt = config('builder360.backups.external_database.last_restore_tested_at');
            $productionReady = ! $this->isProduction() || ($verified && $provider !== '' && $runbookReference !== '');

            return [
                'status' => $productionReady ? 'ok' : 'degraded',
                'driver' => $driver,
                'strategy' => 'external_database_backup_required',
                'production_required' => $this->isProduction(),
                'verified' => $verified,
                'provider_configured' => $provider !== '',
                'runbook_configured' => $runbookReference !== '',
                'rpo_minutes' => $rpoMinutes === null || $rpoMinutes === '' ? null : (int) $rpoMinutes,
                'rto_minutes' => $rtoMinutes === null || $rtoMinutes === '' ? null : (int) $rtoMinutes,
                'last_restore_tested_at' => $lastRestoreTestedAt ?: null,
                'failure' => $productionReady ? null : $this->externalBackupFailureReason($verified, $provider, $runbookReference),
            ];
        }

        $connection = (string) config('database.default');

        if (config("database.connections.{$connection}.database") === ':memory:') {
            return [
                'status' => $this->isProduction() ? 'degraded' : 'ok',
                'driver' => 'sqlite',
                'strategy' => 'builder360:sqlite-backup',
                'configured_directory' => (string) config('builder360.backups.sqlite.directory', 'backups/sqlite'),
                'retention_days' => (int) config('builder360.backups.sqlite.retention_days', 30),
                'max_age_hours' => (int) config('builder360.backups.sqlite.max_age_hours', 24),
                'last_backup_at' => null,
                'failure' => $this->isProduction() ? 'sqlite_memory_database_not_backupable' : null,
            ];
        }

        $service = app(SqliteBackupService::class);
        $latest = $service->latestManifest();

        if ($latest === null) {
            return [
                'status' => $this->isProduction() ? 'degraded' : 'ok',
                'driver' => 'sqlite',
                'strategy' => 'builder360:sqlite-backup',
                'configured_directory' => $service->configuredDirectory(),
                'retention_days' => $service->configuredRetentionDays(),
                'max_age_hours' => $service->configuredMaxAgeHours(),
                'last_backup_at' => null,
                'failure' => $this->isProduction() ? 'sqlite_backup_missing' : null,
            ];
        }

        $verification = $service->verify();
        $createdAt = isset($latest['created_at']) ? \Illuminate\Support\Carbon::parse($latest['created_at']) : null;
        $recent = $createdAt !== null && $createdAt->greaterThanOrEqualTo(now()->subHours($service->configuredMaxAgeHours()));
        $ready = $recent && ($verification['status'] ?? null) === 'ok';

        return [
            'status' => $this->isProduction() && ! $ready ? 'degraded' : 'ok',
            'driver' => 'sqlite',
            'strategy' => 'builder360:sqlite-backup',
            'configured_directory' => $service->configuredDirectory(),
            'retention_days' => $service->configuredRetentionDays(),
            'max_age_hours' => $service->configuredMaxAgeHours(),
            'last_backup_at' => $latest['created_at'] ?? null,
            'last_backup_file' => $latest['backup_file'] ?? null,
            'last_backup_size_bytes' => $latest['size_bytes'] ?? null,
            'last_backup_checksum_valid' => (bool) ($verification['checksum_matches_manifest'] ?? false),
            'last_backup_integrity_valid' => (bool) ($verification['integrity_check']['ok'] ?? false),
            'last_backup_recent' => $recent,
            'failure' => $ready ? null : $this->backupFailureReason($recent, (string) ($verification['failure'] ?? 'sqlite_backup_verification_failed')),
        ];
    }

    private function backupFailureReason(bool $recent, string $verificationFailure): string
    {
        if (! $recent) {
            return 'sqlite_backup_stale';
        }

        return $verificationFailure;
    }

    private function externalBackupFailureReason(bool $verified, string $provider, string $runbookReference): ?string
    {
        if (! $this->isProduction()) {
            return null;
        }

        if (! $verified) {
            return 'external_database_backup_not_verified';
        }

        if ($provider === '') {
            return 'external_database_backup_provider_missing';
        }

        if ($runbookReference === '') {
            return 'external_database_backup_runbook_missing';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduler(): array
    {
        $enabled = (bool) config('builder360.scheduler.enabled', true);
        $requiredCommands = array_values(array_filter(
            array_map(fn (mixed $command): string => trim((string) $command), (array) config('builder360.scheduler.required_commands', [])),
            fn (string $command): bool => $command !== '',
        ));

        if (config('database.default') === 'sqlite') {
            $requiredCommands = array_values(array_unique(array_merge(
                $requiredCommands,
                array_values(array_filter(
                    array_map(fn (mixed $command): string => trim((string) $command), (array) config('builder360.scheduler.sqlite_required_commands', [])),
                    fn (string $command): bool => $command !== '',
                )),
            )));
        }

        try {
            $events = app(Schedule::class)->events();
            $registeredCommands = collect($events)
                ->map(fn (mixed $event): string => (string) ($event->command ?? ''))
                ->filter()
                ->values()
                ->all();

            $missingCommands = collect($requiredCommands)
                ->reject(fn (string $command): bool => collect($registeredCommands)->contains(
                    fn (string $registeredCommand): bool => str_contains($registeredCommand, $command)
                ))
                ->values()
                ->all();

            $ready = $enabled && $missingCommands === [];
            $status = $ready || (! $enabled && ! $this->isProduction()) ? 'ok' : 'degraded';

            return [
                'status' => $status,
                'enabled' => $enabled,
                'timezone' => (string) config('builder360.scheduler.timezone', config('app.timezone', 'UTC')),
                'output_path_configured' => filled(config('builder360.scheduler.output_path')),
                'registered_event_count' => count($registeredCommands),
                'required_commands' => $requiredCommands,
                'missing_required_commands' => $missingCommands,
                'production_acceptable' => ! $this->isProduction() || $ready,
                'failure' => $this->schedulerFailureReason($enabled, $missingCommands),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'enabled' => $enabled,
                'required_commands' => $requiredCommands,
                'missing_required_commands' => $requiredCommands,
                'production_acceptable' => false,
                'failure' => 'scheduler_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @param  array<int, string>  $missingCommands
     */
    private function schedulerFailureReason(bool $enabled, array $missingCommands): ?string
    {
        if (! $enabled) {
            return $this->isProduction() ? 'scheduler_disabled' : null;
        }

        if ($missingCommands !== []) {
            return 'required_scheduled_commands_missing';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function reportLimits(): array
    {
        try {
            return app(ReportLimitPolicy::class)->readiness();
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'failure' => 'report_limits_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payrollControls(): array
    {
        try {
            return app(PayrollRunControlPolicy::class)->readiness();
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'failure' => 'payroll_controls_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function pagination(): array
    {
        try {
            return app(PaginationPolicy::class)->readiness();
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'failure' => 'pagination_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function moneyInputLimits(): array
    {
        try {
            return app(MoneyInputPolicy::class)->readiness();
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'failure' => 'money_input_limits_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function operationalInputLimits(): array
    {
        try {
            return app(OperationalInputPolicy::class)->readiness();
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'failure' => 'operational_input_limits_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function database(): array
    {
        try {
            $connection = DB::connection();
            $connection->getPdo();
            $probe = DB::select('select 1 as ok');
            $driver = $connection->getDriverName();
            $databaseReady = isset($probe[0]);

            $payload = [
                'status' => $databaseReady ? 'ok' : 'degraded',
                'connection' => config('database.default'),
                'driver' => $driver,
            ];

            if ($driver === 'sqlite') {
                $sqlite = $this->sqliteDatabaseDiagnostics();
                $payload['sqlite'] = $sqlite;
                $payload['status'] = $databaseReady && $sqlite['ready'] ? 'ok' : 'degraded';
            }

            return $payload;
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'connection' => config('database.default'),
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sqliteDatabaseDiagnostics(): array
    {
        $connection = (string) config('database.default');
        $database = config("database.connections.{$connection}.database");
        $foreignKeyConstraintsEnabled = $this->sqliteForeignKeyConstraintsEnabled();

        if ($database === ':memory:') {
            $ready = $foreignKeyConstraintsEnabled;

            return [
                'mode' => 'memory',
                'database' => ':memory:',
                'foreign_key_constraints_enabled' => $foreignKeyConstraintsEnabled,
                'ready' => $ready,
                'failure' => $ready ? null : 'sqlite_foreign_keys_disabled',
            ];
        }

        if (! is_string($database) || trim($database) === '') {
            return [
                'mode' => 'file',
                'database' => null,
                'foreign_key_constraints_enabled' => $foreignKeyConstraintsEnabled,
                'ready' => false,
                'failure' => 'sqlite_database_path_missing',
            ];
        }

        $directory = dirname($database);
        $fileExists = is_file($database);
        $readable = $fileExists && is_readable($database);
        $writable = $fileExists && is_writable($database);
        $directoryWritable = is_dir($directory) && is_writable($directory);
        $ready = $fileExists && $readable && $writable && $directoryWritable && $foreignKeyConstraintsEnabled;

        return [
            'mode' => 'file',
            'database' => $database,
            'file_exists' => $fileExists,
            'readable' => $readable,
            'writable' => $writable,
            'directory_writable' => $directoryWritable,
            'foreign_key_constraints_enabled' => $foreignKeyConstraintsEnabled,
            'ready' => $ready,
            'failure' => $ready ? null : $this->sqliteFailureReason(
                $fileExists,
                $readable,
                $writable,
                $directoryWritable,
                $foreignKeyConstraintsEnabled
            ),
        ];
    }

    private function sqliteForeignKeyConstraintsEnabled(): bool
    {
        $connection = (string) config('database.default');

        if (! (bool) config("database.connections.{$connection}.foreign_key_constraints", true)) {
            return false;
        }

        $row = DB::selectOne('pragma foreign_keys');
        $values = array_values((array) $row);

        return (int) ($values[0] ?? 0) === 1;
    }

    private function sqliteFailureReason(
        bool $fileExists,
        bool $readable,
        bool $writable,
        bool $directoryWritable,
        bool $foreignKeyConstraintsEnabled
    ): string
    {
        if (! $fileExists) {
            return 'sqlite_database_file_missing';
        }

        if (! $readable) {
            return 'sqlite_database_file_not_readable';
        }

        if (! $writable) {
            return 'sqlite_database_file_not_writable';
        }

        if (! $directoryWritable) {
            return 'sqlite_database_directory_not_writable';
        }

        if (! $foreignKeyConstraintsEnabled) {
            return 'sqlite_foreign_keys_disabled';
        }

        return 'sqlite_database_unavailable';
    }

    /**
     * @return array<string, mixed>
     */
    private function migrations(): array
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return [
                    'status' => 'degraded',
                    'pending_count' => null,
                    'ran_count' => 0,
                    'message' => 'Migrations table is missing.',
                ];
            }

            $knownMigrations = collect(glob(database_path('migrations/*.php')) ?: [])
                ->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
                ->values();

            $ranMigrations = DB::table('migrations')
                ->pluck('migration')
                ->map(fn (string $migration): string => $migration)
                ->all();

            $pending = $knownMigrations
                ->reject(fn (string $migration): bool => in_array($migration, $ranMigrations, true))
                ->values();

            return [
                'status' => $pending->isEmpty() ? 'ok' : 'degraded',
                'pending_count' => $pending->count(),
                'ran_count' => count($ranMigrations),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'pending_count' => null,
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function auth(): array
    {
        try {
            $guard = (string) config('auth.defaults.guard', 'web');
            $guardConfig = (array) config("auth.guards.{$guard}", []);
            $provider = (string) ($guardConfig['provider'] ?? '');
            $providerConfig = (array) config("auth.providers.{$provider}", []);
            $providerDriver = (string) ($providerConfig['driver'] ?? '');
            $providerModel = (string) ($providerConfig['model'] ?? '');
            $providerModelValid = $providerDriver === 'eloquent'
                && class_exists($providerModel)
                && is_a($providerModel, AuthenticatableContract::class, true);

            $usersTableReady = Schema::hasTable('users');
            $requiredUserColumns = [
                'id',
                'role_id',
                'company_id',
                'name',
                'email',
                'email_verified_at',
                'password',
                'status',
                'remember_token',
                'created_at',
                'updated_at',
            ];
            $missingUserColumns = $usersTableReady
                ? collect($requiredUserColumns)
                    ->reject(fn (string $column): bool => Schema::hasColumn('users', $column))
                    ->values()
                    ->all()
                : $requiredUserColumns;

            $passwordBroker = (string) config('auth.defaults.passwords', 'users');
            $passwordBrokerConfig = (array) config("auth.passwords.{$passwordBroker}", []);
            $passwordResetTable = (string) ($passwordBrokerConfig['table'] ?? 'password_reset_tokens');
            $passwordResetTableReady = $passwordResetTable !== '' && Schema::hasTable($passwordResetTable);
            $passwordResetColumns = ['email', 'token', 'created_at'];
            $missingPasswordResetColumns = $passwordResetTableReady
                ? collect($passwordResetColumns)
                    ->reject(fn (string $column): bool => Schema::hasColumn($passwordResetTable, $column))
                    ->values()
                    ->all()
                : $passwordResetColumns;

            $expireMinutes = (int) ($passwordBrokerConfig['expire'] ?? 0);
            $throttleSeconds = (int) ($passwordBrokerConfig['throttle'] ?? 0);
            $passwordResetExpireAcceptable = $expireMinutes > 0 && $expireMinutes <= 1440;
            $passwordResetThrottleAcceptable = $throttleSeconds >= 0 && $throttleSeconds <= 86400;
            $passwordTimeoutSeconds = (int) config('auth.password_timeout', 10800);
            $passwordTimeoutAcceptable = $passwordTimeoutSeconds > 0 && $passwordTimeoutSeconds <= 86400;
            $passwordPolicy = PasswordPolicy::readiness();
            $ready = $usersTableReady
                && $missingUserColumns === []
                && $providerModelValid
                && $passwordResetTableReady
                && $missingPasswordResetColumns === []
                && $passwordResetExpireAcceptable
                && $passwordResetThrottleAcceptable
                && $passwordTimeoutAcceptable
                && $passwordPolicy['acceptable'] === true;

            return [
                'status' => $ready ? 'ok' : 'degraded',
                'guard' => $guard,
                'guard_driver' => (string) ($guardConfig['driver'] ?? ''),
                'provider' => $provider,
                'provider_driver' => $providerDriver,
                'provider_model' => $providerModel,
                'provider_model_valid' => $providerModelValid,
                'users_table' => $usersTableReady ? 'present' : 'missing',
                'required_user_columns' => $requiredUserColumns,
                'missing_user_columns' => $missingUserColumns,
                'email_verification_supported' => $usersTableReady && Schema::hasColumn('users', 'email_verified_at'),
                'account_status_supported' => $usersTableReady && Schema::hasColumn('users', 'status'),
                'remember_token_supported' => $usersTableReady && Schema::hasColumn('users', 'remember_token'),
                'password_broker' => $passwordBroker,
                'password_reset_table' => $passwordResetTableReady ? 'present' : 'missing',
                'password_reset_table_name' => $passwordResetTable,
                'missing_password_reset_columns' => $missingPasswordResetColumns,
                'password_reset_expire_minutes' => $expireMinutes,
                'password_reset_expire_acceptable' => $passwordResetExpireAcceptable,
                'password_reset_throttle_seconds' => $throttleSeconds,
                'password_reset_throttle_acceptable' => $passwordResetThrottleAcceptable,
                'password_timeout_seconds' => $passwordTimeoutSeconds,
                'password_timeout_acceptable' => $passwordTimeoutAcceptable,
                'password_policy' => $passwordPolicy,
                'failure' => $this->authFailureReason(
                    $usersTableReady,
                    $missingUserColumns,
                    $providerModelValid,
                    $passwordResetTableReady,
                    $missingPasswordResetColumns,
                    $passwordResetExpireAcceptable,
                    $passwordResetThrottleAcceptable,
                    $passwordTimeoutAcceptable,
                    $passwordPolicy['acceptable'] === true,
                ),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'guard' => config('auth.defaults.guard'),
                'provider' => null,
                'provider_model_valid' => false,
                'failure' => 'auth_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @param  array<int, string>  $missingUserColumns
     * @param  array<int, string>  $missingPasswordResetColumns
     */
    private function authFailureReason(
        bool $usersTableReady,
        array $missingUserColumns,
        bool $providerModelValid,
        bool $passwordResetTableReady,
        array $missingPasswordResetColumns,
        bool $passwordResetExpireAcceptable,
        bool $passwordResetThrottleAcceptable,
        bool $passwordTimeoutAcceptable,
        bool $passwordPolicyAcceptable,
    ): ?string {
        if (! $usersTableReady) {
            return 'auth_users_table_missing';
        }

        if ($missingUserColumns !== []) {
            return 'auth_user_columns_missing';
        }

        if (! $providerModelValid) {
            return 'auth_provider_model_invalid';
        }

        if (! $passwordResetTableReady) {
            return 'password_reset_table_missing';
        }

        if ($missingPasswordResetColumns !== []) {
            return 'password_reset_columns_missing';
        }

        if (! $passwordResetExpireAcceptable) {
            return 'password_reset_expire_out_of_range';
        }

        if (! $passwordResetThrottleAcceptable) {
            return 'password_reset_throttle_out_of_range';
        }

        if (! $passwordTimeoutAcceptable) {
            return 'password_timeout_out_of_range';
        }

        if (! $passwordPolicyAcceptable) {
            return 'password_policy_invalid';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function session(): array
    {
        try {
            $driver = (string) config('session.driver', 'database');
            $table = (string) config('session.table', 'sessions');
            $filesPath = (string) config('session.files', storage_path('framework/sessions'));
            $lifetime = (int) config('session.lifetime', 120);
            $sameSite = config('session.same_site');

            $databaseTableReady = $driver !== 'database' || Schema::hasTable($table);
            $filePathReady = $driver !== 'file' || (is_dir($filesPath) && is_writable($filesPath));
            $lifetimeAcceptable = $lifetime > 0 && $lifetime <= 1440;
            $sameSiteAcceptable = in_array($sameSite, ['lax', 'strict', 'none'], true);
            $productionDriverAcceptable = ! ($this->isProduction() && in_array($driver, ['array', 'cookie'], true));
            $ready = $databaseTableReady && $filePathReady && $lifetimeAcceptable && $sameSiteAcceptable && $productionDriverAcceptable;

            return [
                'status' => $ready ? 'ok' : 'degraded',
                'driver' => $driver,
                'lifetime_minutes' => $lifetime,
                'encrypted' => config('session.encrypt') === true,
                'http_only' => config('session.http_only') === true,
                'secure_cookie' => config('session.secure') === true,
                'same_site' => $sameSite,
                'database_table' => $driver === 'database'
                    ? ($databaseTableReady ? 'present' : 'missing')
                    : 'not_applicable',
                'file_path_writable' => $driver === 'file' ? $filePathReady : null,
                'production_driver_acceptable' => $productionDriverAcceptable,
                'failure' => $this->sessionFailureReason(
                    $databaseTableReady,
                    $filePathReady,
                    $lifetimeAcceptable,
                    $sameSiteAcceptable,
                    $productionDriverAcceptable
                ),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'driver' => config('session.driver'),
                'production_driver_acceptable' => false,
                'failure' => 'session_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    private function sessionFailureReason(
        bool $databaseTableReady,
        bool $filePathReady,
        bool $lifetimeAcceptable,
        bool $sameSiteAcceptable,
        bool $productionDriverAcceptable,
    ): ?string {
        if (! $databaseTableReady) {
            return 'session_table_missing';
        }

        if (! $filePathReady) {
            return 'session_file_path_not_writable';
        }

        if (! $lifetimeAcceptable) {
            return 'session_lifetime_out_of_range';
        }

        if (! $sameSiteAcceptable) {
            return 'session_same_site_invalid';
        }

        if (! $productionDriverAcceptable) {
            return 'unsafe_production_session_driver';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function cache(): array
    {
        $key = 'builder360:readiness:'.bin2hex(random_bytes(8));

        try {
            $store = (string) config('cache.default');
            Cache::put($key, 'ok', now()->addSeconds(10));
            $works = Cache::get($key) === 'ok';
            Cache::forget($key);
            $productionAcceptable = ! ($this->isProduction() && in_array($store, ['array', 'null'], true));

            return [
                'status' => $works && $productionAcceptable ? 'ok' : 'degraded',
                'store' => $store,
                'production_acceptable' => $productionAcceptable,
                'failure' => $works ? ($productionAcceptable ? null : 'unsafe_production_cache_store') : 'cache_probe_failed',
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'store' => config('cache.default'),
                'production_acceptable' => false,
                'failure' => 'cache_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        try {
            $connection = (string) config('queue.default');
            $jobsTable = (string) config("queue.connections.{$connection}.table", 'jobs');
            $jobsTableReady = $connection !== 'database' || Schema::hasTable($jobsTable);
            $failedDriver = (string) config('queue.failed.driver', 'database-uuids');
            $failedJobsTable = (string) config('queue.failed.table', 'failed_jobs');
            $failedJobsTableRequired = in_array($failedDriver, ['database', 'database-uuids'], true);
            $failedJobsReady = ! $failedJobsTableRequired || Schema::hasTable($failedJobsTable);
            $failedJobsRecordingEnabled = $failedDriver !== 'null';
            $batchingTable = (string) config('queue.batching.table', 'job_batches');
            $batchingReady = Schema::hasTable($batchingTable);
            $maxPendingJobs = max(0, (int) config('builder360.queue.max_pending_jobs', 1000));
            $maxReservedJobs = max(0, (int) config('builder360.queue.max_reserved_jobs', 250));
            $maxFailedJobs = max(0, (int) config('builder360.queue.max_failed_jobs', 0));
            $pendingJobs = $connection === 'database' && $jobsTableReady
                ? DB::table($jobsTable)->whereNull('reserved_at')->count()
                : null;
            $reservedJobs = $connection === 'database' && $jobsTableReady
                ? DB::table($jobsTable)->whereNotNull('reserved_at')->count()
                : null;
            $failedJobs = $failedJobsTableRequired && $failedJobsReady
                ? DB::table($failedJobsTable)->count()
                : null;
            $pendingJobsAcceptable = $pendingJobs === null || $pendingJobs <= $maxPendingJobs;
            $reservedJobsAcceptable = $reservedJobs === null || $reservedJobs <= $maxReservedJobs;
            $failedJobsAcceptable = $failedJobs === null || $failedJobs <= $maxFailedJobs;
            $productionConnectionAcceptable = ! ($this->isProduction() && in_array($connection, ['sync', 'null', 'deferred', 'background'], true));
            $failedJobsRecordingAcceptable = ! ($this->isProduction() && ! $failedJobsRecordingEnabled);
            $productionAcceptable = $productionConnectionAcceptable && $failedJobsRecordingAcceptable;
            $backlogAcceptable = $pendingJobsAcceptable && $reservedJobsAcceptable && $failedJobsAcceptable;
            $ready = $jobsTableReady && $failedJobsReady && $batchingReady && $productionAcceptable && $backlogAcceptable;

            return [
                'status' => $ready ? 'ok' : 'degraded',
                'connection' => $connection,
                'jobs_table' => $connection === 'database'
                    ? ($jobsTableReady ? 'present' : 'missing')
                    : 'not_applicable',
                'failed_jobs_driver' => $failedDriver,
                'failed_jobs_table' => $failedJobsTableRequired
                    ? ($failedJobsReady ? 'present' : 'missing')
                    : 'not_applicable',
                'failed_jobs_recording_enabled' => $failedJobsRecordingEnabled,
                'batching_table' => $batchingReady ? 'present' : 'missing',
                'pending_jobs' => $pendingJobs,
                'reserved_jobs' => $reservedJobs,
                'failed_jobs' => $failedJobs,
                'thresholds' => [
                    'max_pending_jobs' => $maxPendingJobs,
                    'max_reserved_jobs' => $maxReservedJobs,
                    'max_failed_jobs' => $maxFailedJobs,
                ],
                'backlog_acceptable' => $backlogAcceptable,
                'production_connection_acceptable' => $productionConnectionAcceptable,
                'production_acceptable' => $productionAcceptable,
                'failure' => $this->queueFailureReason(
                    $jobsTableReady,
                    $failedJobsReady,
                    $batchingReady,
                    $productionConnectionAcceptable,
                    $failedJobsRecordingAcceptable,
                    $pendingJobsAcceptable,
                    $reservedJobsAcceptable,
                    $failedJobsAcceptable
                ),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'connection' => config('queue.default'),
                'production_acceptable' => false,
                'failure' => 'queue_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    private function queueFailureReason(
        bool $jobsTableReady,
        bool $failedJobsReady,
        bool $batchingReady,
        bool $productionConnectionAcceptable,
        bool $failedJobsRecordingAcceptable,
        bool $pendingJobsAcceptable,
        bool $reservedJobsAcceptable,
        bool $failedJobsAcceptable,
    ): ?string {
        if (! $jobsTableReady) {
            return 'jobs_table_missing';
        }

        if (! $failedJobsReady) {
            return 'failed_jobs_table_missing';
        }

        if (! $batchingReady) {
            return 'job_batches_table_missing';
        }

        if (! $productionConnectionAcceptable) {
            return 'unsafe_production_queue_connection';
        }

        if (! $failedJobsRecordingAcceptable) {
            return 'failed_jobs_not_recorded';
        }

        if (! $pendingJobsAcceptable) {
            return 'queue_pending_threshold_exceeded';
        }

        if (! $reservedJobsAcceptable) {
            return 'queue_reserved_threshold_exceeded';
        }

        if (! $failedJobsAcceptable) {
            return 'failed_jobs_threshold_exceeded';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function storage(): array
    {
        $frameworkPath = storage_path('framework');
        $logsPath = storage_path('logs');

        $frameworkWritable = is_dir($frameworkPath) && is_writable($frameworkPath);
        $logsWritable = is_dir($logsPath) && is_writable($logsPath);
        $defaultDisk = (string) config('filesystems.default');
        $localPrivateServingEnabled = config('filesystems.disks.local.serve') === true;
        $productionAcceptable = ! (
            $this->isProduction()
            && ($defaultDisk === 'public' || $localPrivateServingEnabled)
        );

        return [
            'status' => $frameworkWritable && $logsWritable && $productionAcceptable ? 'ok' : 'degraded',
            'framework_writable' => $frameworkWritable,
            'logs_writable' => $logsWritable,
            'default_disk' => $defaultDisk,
            'local_private_serving_enabled' => $localPrivateServingEnabled,
            'production_acceptable' => $productionAcceptable,
            'failure' => $this->storageFailureReason(
                $frameworkWritable,
                $logsWritable,
                $defaultDisk,
                $localPrivateServingEnabled,
            ),
        ];
    }

    private function storageFailureReason(
        bool $frameworkWritable,
        bool $logsWritable,
        string $defaultDisk,
        bool $localPrivateServingEnabled,
    ): ?string {
        if (! $frameworkWritable || ! $logsWritable) {
            return 'storage_not_writable';
        }

        if (! $this->isProduction()) {
            return null;
        }

        if ($defaultDisk === 'public') {
            return 'unsafe_production_filesystem_disk';
        }

        if ($localPrivateServingEnabled) {
            return 'unsafe_production_local_storage_serving';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentUploads(): array
    {
        try {
            return app(DocumentFilePolicy::class)->readiness();
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'failure' => 'document_upload_policy_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function assets(): array
    {
        $publicRoot = rtrim((string) config('builder360.assets.public_root', public_path()), DIRECTORY_SEPARATOR);
        $requiredFiles = array_values(array_filter(
            array_map(fn (mixed $entry): string => trim(str_replace('\\', '/', (string) $entry)), (array) config('builder360.assets.required_public_files', [
                'css/builder360-classic.css',
                'js/builder360-classic.js',
            ])),
            fn (string $entry): bool => $entry !== '' && ! str_contains($entry, '..') && ! str_starts_with($entry, '/'),
        ));

        $missingFiles = collect($requiredFiles)
            ->reject(function (string $file) use ($publicRoot): bool {
                $path = $publicRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);

                return is_file($path) && is_readable($path);
            })
            ->values()
            ->all();

        return [
            'status' => $missingFiles === [] ? 'ok' : 'degraded',
            'asset_mode' => 'classic_public_assets',
            'public_root' => is_dir($publicRoot) ? 'present' : 'missing',
            'required_files' => $requiredFiles,
            'missing_files' => $missingFiles,
            'failure' => $missingFiles === [] ? null : 'classic_public_assets_missing',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integrations(): array
    {
        $paymentGatewayProvider = strtolower(trim((string) config('builder360.integrations.payment_gateway.provider', 'prototype')));
        $paymentGatewayWebhookSecretConfigured = filled(config('builder360.integrations.payment_gateway.webhook_secret'));
        $prototypePaymentProvider = $this->isPrototypePaymentGatewayProvider($paymentGatewayProvider);
        $paymentGatewayProductionAcceptable = ! (
            $this->isProduction()
            && ($prototypePaymentProvider || ! $paymentGatewayWebhookSecretConfigured)
        );

        return [
            'status' => $paymentGatewayProductionAcceptable ? 'ok' : 'degraded',
            'payment_gateway' => [
                'provider' => $paymentGatewayProvider,
                'prototype_provider' => $prototypePaymentProvider,
                'webhook_secret_configured' => $paymentGatewayWebhookSecretConfigured,
                'production_acceptable' => $paymentGatewayProductionAcceptable,
                'failure' => $this->paymentGatewayFailureReason(
                    $prototypePaymentProvider,
                    $paymentGatewayWebhookSecretConfigured
                ),
            ],
        ];
    }

    private function isPrototypePaymentGatewayProvider(string $provider): bool
    {
        return $provider === ''
            || in_array($provider, ['prototype', 'demo', 'mock', 'sandbox', 'simulated', 'simulation'], true);
    }

    private function paymentGatewayFailureReason(
        bool $prototypePaymentProvider,
        bool $paymentGatewayWebhookSecretConfigured
    ): ?string {
        if (! $this->isProduction()) {
            return null;
        }

        if ($prototypePaymentProvider) {
            return 'prototype_payment_gateway_provider';
        }

        if (! $paymentGatewayWebhookSecretConfigured) {
            return 'payment_gateway_webhook_secret_missing';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function optimization(): array
    {
        $configurationCached = app()->configurationIsCached();
        $routesCached = app()->routesAreCached();
        $productionAcceptable = ! (
            $this->isProduction()
            && (! $configurationCached || ! $routesCached)
        );

        return [
            'status' => $productionAcceptable ? 'ok' : 'degraded',
            'configuration_cached' => $configurationCached,
            'routes_cached' => $routesCached,
            'configuration_cache_path' => app()->getCachedConfigPath(),
            'route_cache_path' => app()->getCachedRoutesPath(),
            'production_acceptable' => $productionAcceptable,
            'failure' => $this->optimizationFailureReason($configurationCached, $routesCached),
        ];
    }

    private function optimizationFailureReason(bool $configurationCached, bool $routesCached): ?string
    {
        if (! $this->isProduction()) {
            return null;
        }

        if (! $configurationCached && ! $routesCached) {
            return 'configuration_and_routes_not_cached';
        }

        if (! $configurationCached) {
            return 'configuration_not_cached';
        }

        if (! $routesCached) {
            return 'routes_not_cached';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mail(): array
    {
        $mailer = (string) config('mail.default');
        $fromAddress = (string) config('mail.from.address');
        $productionAcceptable = ! (
            $this->isProduction()
            && (
                in_array($mailer, ['array', 'log'], true)
                || $this->isPlaceholderEmailAddress($fromAddress)
            )
        );

        return [
            'status' => $productionAcceptable ? 'ok' : 'degraded',
            'mailer' => $mailer,
            'from_address_configured' => filled($fromAddress),
            'placeholder_from_address' => $this->isPlaceholderEmailAddress($fromAddress),
            'production_acceptable' => $productionAcceptable,
            'failure' => $this->mailFailureReason($mailer, $fromAddress),
        ];
    }

    private function mailFailureReason(string $mailer, string $fromAddress): ?string
    {
        if (! $this->isProduction()) {
            return null;
        }

        if (in_array($mailer, ['array', 'log'], true)) {
            return 'unsafe_production_mailer';
        }

        if ($this->isPlaceholderEmailAddress($fromAddress)) {
            return 'placeholder_mail_from_address';
        }

        return null;
    }

    private function isPlaceholderEmailAddress(string $address): bool
    {
        $normalized = strtolower(trim($address));

        return $normalized === ''
            || str_ends_with($normalized, '@example.com')
            || str_ends_with($normalized, '@example.test')
            || str_ends_with($normalized, '@localhost');
    }

    /**
     * @return array<string, mixed>
     */
    private function logging(): array
    {
        $channel = (string) config('logging.default');
        $level = strtolower((string) config("logging.channels.{$channel}.level", config('logging.channels.single.level', 'debug')));
        $productionAcceptable = ! (
            $this->isProduction()
            && (in_array($channel, ['emergency', 'null'], true) || $level === 'debug')
        );

        return [
            'status' => $productionAcceptable ? 'ok' : 'degraded',
            'channel' => $channel,
            'level' => $level,
            'production_acceptable' => $productionAcceptable,
            'failure' => $this->loggingFailureReason($channel, $level),
        ];
    }

    private function loggingFailureReason(string $channel, string $level): ?string
    {
        if (! $this->isProduction()) {
            return null;
        }

        if (in_array($channel, ['emergency', 'null'], true)) {
            return 'unsafe_production_log_channel';
        }

        if ($level === 'debug') {
            return 'unsafe_production_log_level';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function rateLimiting(): array
    {
        try {
            $erpReadPerMinute = (int) config('security.rate_limits.erp_read_per_minute', 1200);
            $erpWritePerMinute = (int) config('security.rate_limits.erp_write_per_minute', 600);
            $minErpReadPerMinute = max(1, (int) config('security.rate_limits.readiness_min_erp_read_per_minute', 1));
            $maxErpReadPerMinute = max(
                $minErpReadPerMinute,
                (int) config('security.rate_limits.readiness_max_erp_read_per_minute', 5000),
            );
            $minErpWritePerMinute = max(1, (int) config('security.rate_limits.readiness_min_erp_write_per_minute', 1));
            $maxErpWritePerMinute = max(
                $minErpWritePerMinute,
                (int) config('security.rate_limits.readiness_max_erp_write_per_minute', 2500),
            );
            $erpReadConfigAcceptable = $erpReadPerMinute >= $minErpReadPerMinute
                && $erpReadPerMinute <= $maxErpReadPerMinute;
            $erpWriteConfigAcceptable = $erpWritePerMinute >= $minErpWritePerMinute
                && $erpWritePerMinute <= $maxErpWritePerMinute
                && $erpWritePerMinute <= $erpReadPerMinute;
            $erpReadLimiterRegistered = RateLimiter::limiter('erp-read') !== null;
            $routeCoverage = $this->rateLimitedRouteCoverage();
            $ready = $erpReadConfigAcceptable
                && $erpWriteConfigAcceptable
                && $erpReadLimiterRegistered
                && $routeCoverage['missing_business_route_middleware'] === []
                && $routeCoverage['auth_lifecycle_route_issues'] === []
                && $routeCoverage['signed_integration_route_issues'] === [];

            return [
                'status' => $ready ? 'ok' : 'degraded',
                'erp_read_limiter_registered' => $erpReadLimiterRegistered,
                'limits' => [
                    'erp_read_per_minute' => [
                        'configured' => $erpReadPerMinute,
                        'minimum' => $minErpReadPerMinute,
                        'maximum' => $maxErpReadPerMinute,
                        'acceptable' => $erpReadConfigAcceptable,
                    ],
                    'erp_write_per_minute' => [
                        'configured' => $erpWritePerMinute,
                        'minimum' => $minErpWritePerMinute,
                        'maximum' => $maxErpWritePerMinute,
                        'must_not_exceed_read_limit' => true,
                        'acceptable' => $erpWriteConfigAcceptable,
                    ],
                ],
                'route_coverage' => $routeCoverage,
                'failure' => $this->rateLimitingFailureReason(
                    $erpReadLimiterRegistered,
                    $erpReadConfigAcceptable,
                    $erpWriteConfigAcceptable,
                    $routeCoverage,
                ),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'failure' => 'rate_limiting_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rateLimitedRouteCoverage(): array
    {
        $publicRoutes = [
            'health',
            'login',
            'login.store',
            'password.request',
            'password.email',
            'password.reset',
            'password.store',
        ];
        $publicStateChangingRoutes = [
            'prospect-inquiries.store',
        ];
        $authenticatedButNotVerifiedRoutes = [
            'logout',
            'verification.notice',
            'verification.verify',
            'verification.send',
        ];
        $signedIntegrationRouteMiddleware = [
            'finance.payment-gateway.webhook' => ['web', 'throttle:60,1'],
            'calendar.guest-invitations.show' => ['web', 'signed'],
            'calendar.guest-invitations.respond' => ['web', 'signed', 'throttle:20,1'],
        ];
        $requiredBusinessMiddleware = [
            'web',
            'auth',
            'account.active',
            'verified',
            'throttle:erp-read',
            'erp.write_limit',
        ];
        $requiredAuthLifecycleMiddleware = [
            'web',
            'auth',
            'account.active',
        ];
        $requiredPublicStateChangingMiddleware = [
            'web',
            'throttle:30,1',
        ];
        $requiredVerificationThrottleMiddleware = 'throttle:6,1';
        $missingBusinessRouteMiddleware = [];
        $publicStateChangingRouteIssues = [];
        $authLifecycleRouteIssues = [];
        $signedIntegrationRouteIssues = [];
        $namedRouteCount = 0;

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null) {
                continue;
            }

            $namedRouteCount++;
            $middleware = array_values(array_unique($route->gatherMiddleware()));

            if (in_array($name, $publicRoutes, true)) {
                continue;
            }

            if (in_array($name, $publicStateChangingRoutes, true)) {
                $missing = array_values(array_diff($requiredPublicStateChangingMiddleware, $middleware));

                if ($missing !== []) {
                    $publicStateChangingRouteIssues[] = [
                        'route' => $name,
                        'uri' => $route->uri(),
                        'missing_middleware' => $missing,
                    ];
                }

                if (in_array('auth', $middleware, true)) {
                    $publicStateChangingRouteIssues[] = [
                        'route' => $name,
                        'uri' => $route->uri(),
                        'forbidden_middleware' => ['auth'],
                    ];
                }

                continue;
            }

            if (in_array($name, $authenticatedButNotVerifiedRoutes, true)) {
                $missing = array_values(array_diff($requiredAuthLifecycleMiddleware, $middleware));

                if ($missing !== []) {
                    $authLifecycleRouteIssues[] = [
                        'route' => $name,
                        'uri' => $route->uri(),
                        'missing_middleware' => $missing,
                    ];
                }

                if (in_array($name, ['verification.verify', 'verification.send'], true)
                    && ! in_array($requiredVerificationThrottleMiddleware, $middleware, true)) {
                    $authLifecycleRouteIssues[] = [
                        'route' => $name,
                        'uri' => $route->uri(),
                        'missing_middleware' => [$requiredVerificationThrottleMiddleware],
                    ];
                }

                continue;
            }

            if (array_key_exists($name, $signedIntegrationRouteMiddleware)) {
                $missing = array_values(array_diff($signedIntegrationRouteMiddleware[$name], $middleware));

                if ($missing !== []) {
                    $signedIntegrationRouteIssues[] = [
                        'route' => $name,
                        'uri' => $route->uri(),
                        'missing_middleware' => $missing,
                    ];
                }

                if (in_array('auth', $middleware, true)) {
                    $signedIntegrationRouteIssues[] = [
                        'route' => $name,
                        'uri' => $route->uri(),
                        'forbidden_middleware' => ['auth'],
                    ];
                }

                continue;
            }

            $missing = array_values(array_diff($requiredBusinessMiddleware, $middleware));

            if ($missing !== []) {
                $missingBusinessRouteMiddleware[] = [
                    'route' => $name,
                    'uri' => $route->uri(),
                    'missing_middleware' => $missing,
                ];
            }
        }

        return [
            'named_route_count' => $namedRouteCount,
            'public_routes_exempt' => $publicRoutes,
            'public_state_changing_routes' => $publicStateChangingRoutes,
            'auth_lifecycle_routes' => $authenticatedButNotVerifiedRoutes,
            'signed_integration_routes' => array_keys($signedIntegrationRouteMiddleware),
            'required_business_middleware' => $requiredBusinessMiddleware,
            'missing_business_route_middleware' => $missingBusinessRouteMiddleware,
            'public_state_changing_route_issues' => $publicStateChangingRouteIssues,
            'auth_lifecycle_route_issues' => $authLifecycleRouteIssues,
            'signed_integration_route_issues' => $signedIntegrationRouteIssues,
        ];
    }

    /**
     * @param  array<string, mixed>  $routeCoverage
     */
    private function rateLimitingFailureReason(
        bool $erpReadLimiterRegistered,
        bool $erpReadConfigAcceptable,
        bool $erpWriteConfigAcceptable,
        array $routeCoverage,
    ): ?string {
        if (! $erpReadLimiterRegistered) {
            return 'erp_read_limiter_missing';
        }

        if (! $erpReadConfigAcceptable || ! $erpWriteConfigAcceptable) {
            return 'rate_limit_config_invalid';
        }

        if (($routeCoverage['missing_business_route_middleware'] ?? []) !== []) {
            return 'business_route_rate_limit_missing';
        }

        if (($routeCoverage['public_state_changing_route_issues'] ?? []) !== []) {
            return 'public_state_changing_route_rate_limit_missing';
        }

        if (($routeCoverage['auth_lifecycle_route_issues'] ?? []) !== []) {
            return 'auth_lifecycle_rate_limit_missing';
        }

        if (($routeCoverage['signed_integration_route_issues'] ?? []) !== []) {
            return 'signed_integration_rate_limit_missing';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function csrfProtection(): array
    {
        try {
            $approvedExemptRoutes = [
                'finance.payment-gateway.webhook',
            ];
            $csrfMiddleware = ValidateCsrfToken::class;
            $stateChangingRouteCount = 0;
            $csrfExemptRoutes = [];
            $unexpectedCsrfExemptRoutes = [];
            $missingWebMiddlewareRoutes = [];
            $approvedExemptRouteIssues = [];

            foreach (Route::getRoutes() as $route) {
                $name = $route->getName();

                if ($name === null || ! $this->routeHasStateChangingMethod($route->methods())) {
                    continue;
                }

                $stateChangingRouteCount++;
                $middleware = array_values(array_unique($route->gatherMiddleware()));
                $excludedMiddleware = array_values(array_unique($route->excludedMiddleware()));
                $csrfExcluded = in_array($csrfMiddleware, $excludedMiddleware, true);

                if ($csrfExcluded) {
                    $csrfExemptRoutes[] = $name;
                }

                if (! in_array('web', $middleware, true) && ! in_array($name, $approvedExemptRoutes, true)) {
                    $missingWebMiddlewareRoutes[] = [
                        'route' => $name,
                        'uri' => $route->uri(),
                        'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                    ];
                }

                if ($csrfExcluded && ! in_array($name, $approvedExemptRoutes, true)) {
                    $unexpectedCsrfExemptRoutes[] = [
                        'route' => $name,
                        'uri' => $route->uri(),
                        'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                    ];
                }
            }

            foreach ($approvedExemptRoutes as $routeName) {
                $route = Route::getRoutes()->getByName($routeName);

                if ($route === null) {
                    $approvedExemptRouteIssues[] = [
                        'route' => $routeName,
                        'issue' => 'route_missing',
                    ];

                    continue;
                }

                $excludedMiddleware = array_values(array_unique($route->excludedMiddleware()));

                if (! in_array($csrfMiddleware, $excludedMiddleware, true)) {
                    $approvedExemptRouteIssues[] = [
                        'route' => $routeName,
                        'issue' => 'csrf_exemption_missing',
                    ];
                }
            }

            $ready = $unexpectedCsrfExemptRoutes === []
                && $missingWebMiddlewareRoutes === []
                && $approvedExemptRouteIssues === [];

            return [
                'status' => $ready ? 'ok' : 'degraded',
                'csrf_middleware' => $csrfMiddleware,
                'state_changing_route_count' => $stateChangingRouteCount,
                'approved_exempt_routes' => $approvedExemptRoutes,
                'csrf_exempt_routes' => $csrfExemptRoutes,
                'unexpected_csrf_exempt_routes' => $unexpectedCsrfExemptRoutes,
                'missing_web_middleware_routes' => $missingWebMiddlewareRoutes,
                'approved_exempt_route_issues' => $approvedExemptRouteIssues,
                'failure' => $this->csrfFailureReason(
                    $unexpectedCsrfExemptRoutes,
                    $missingWebMiddlewareRoutes,
                    $approvedExemptRouteIssues,
                ),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'failure' => 'csrf_probe_exception',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @param  array<int, string>  $methods
     */
    private function routeHasStateChangingMethod(array $methods): bool
    {
        return array_values(array_diff($methods, ['GET', 'HEAD', 'OPTIONS'])) !== [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $unexpectedCsrfExemptRoutes
     * @param  array<int, array<string, mixed>>  $missingWebMiddlewareRoutes
     * @param  array<int, array<string, mixed>>  $approvedExemptRouteIssues
     */
    private function csrfFailureReason(
        array $unexpectedCsrfExemptRoutes,
        array $missingWebMiddlewareRoutes,
        array $approvedExemptRouteIssues,
    ): ?string {
        if ($unexpectedCsrfExemptRoutes !== []) {
            return 'unexpected_csrf_exemption';
        }

        if ($missingWebMiddlewareRoutes !== []) {
            return 'state_changing_route_missing_web_middleware';
        }

        if ($approvedExemptRouteIssues !== []) {
            return 'approved_csrf_exemption_invalid';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptionHandling(): array
    {
        $factoryRegistered = class_exists(ExceptionResponseFactory::class);
        $jsonRequestIdEnabled = (bool) config('security.exception_responses.json_request_id_enabled', true);
        $includeDebugDetails = (bool) config('security.exception_responses.include_debug_details', false);
        $genericServerErrorMessage = trim((string) config(
            'security.exception_responses.generic_server_error_message',
            'An unexpected server error occurred. Provide the request_id to support.',
        ));
        $productionDebugDetailsAcceptable = ! ($this->isProduction() && $includeDebugDetails);
        $genericMessageAcceptable = $genericServerErrorMessage !== ''
            && ! str_contains(strtolower($genericServerErrorMessage), 'exception')
            && ! str_contains(strtolower($genericServerErrorMessage), 'stack');
        $ready = $factoryRegistered
            && $jsonRequestIdEnabled
            && $productionDebugDetailsAcceptable
            && $genericMessageAcceptable;

        return [
            'status' => $ready ? 'ok' : 'degraded',
            'factory_registered' => $factoryRegistered,
            'json_request_id_enabled' => $jsonRequestIdEnabled,
            'include_debug_details' => $includeDebugDetails,
            'generic_server_error_message_configured' => $genericServerErrorMessage !== '',
            'generic_server_error_message_safe' => $genericMessageAcceptable,
            'production_debug_details_acceptable' => $productionDebugDetailsAcceptable,
            'failure' => $this->exceptionHandlingFailureReason(
                $factoryRegistered,
                $jsonRequestIdEnabled,
                $productionDebugDetailsAcceptable,
                $genericMessageAcceptable,
            ),
        ];
    }

    private function exceptionHandlingFailureReason(
        bool $factoryRegistered,
        bool $jsonRequestIdEnabled,
        bool $productionDebugDetailsAcceptable,
        bool $genericMessageAcceptable,
    ): ?string {
        if (! $factoryRegistered) {
            return 'exception_response_factory_missing';
        }

        if (! $jsonRequestIdEnabled) {
            return 'exception_request_id_disabled';
        }

        if (! $productionDebugDetailsAcceptable) {
            return 'exception_debug_details_enabled_in_production';
        }

        if (! $genericMessageAcceptable) {
            return 'exception_generic_message_invalid';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function security(): array
    {
        $environment = (string) config('app.env', app()->environment());
        $production = $environment === 'production';
        $sameSite = config('session.same_site');
        $headers = (array) config('security.headers', []);
        $xFrameOptions = strtoupper(trim((string) ($headers['X-Frame-Options'] ?? '')));
        $xContentTypeOptions = strtolower(trim((string) ($headers['X-Content-Type-Options'] ?? '')));
        $referrerPolicy = strtolower(trim((string) ($headers['Referrer-Policy'] ?? '')));
        $crossOriginOpenerPolicy = strtolower(trim((string) ($headers['Cross-Origin-Opener-Policy'] ?? '')));
        $permissionsPolicy = strtolower(trim((string) ($headers['Permissions-Policy'] ?? '')));
        $contentSecurityPolicy = (string) config('security.headers.Content-Security-Policy', '');
        $hstsMaxAge = (int) config('security.hsts.max_age', 0);
        $hstsPreload = config('security.hsts.preload') === true;
        $hstsIncludeSubdomains = config('security.hsts.include_subdomains') === true;

        $requirements = [
            'app_key_configured' => filled(config('app.key')),
            'app_url_uses_https' => $this->appUrlUsesHttps(),
            'debug_disabled' => config('app.debug') === false,
            'session_encrypted' => config('session.encrypt') === true,
            'secure_session_cookie' => config('session.secure') === true,
            'http_only_session_cookie' => config('session.http_only') === true,
            'same_site_policy_configured' => in_array($sameSite, ['lax', 'strict', 'none'], true),
            'hsts_enabled' => config('security.hsts.enabled') === true,
            'hsts_max_age_at_least_one_year' => $hstsMaxAge >= 31536000,
            'hsts_preload_requires_subdomains' => ! $hstsPreload || $hstsIncludeSubdomains,
            'frame_options_safe' => in_array($xFrameOptions, ['DENY', 'SAMEORIGIN'], true),
            'content_type_options_nosniff' => $xContentTypeOptions === 'nosniff',
            'referrer_policy_safe' => in_array($referrerPolicy, ['no-referrer', 'same-origin', 'strict-origin', 'strict-origin-when-cross-origin'], true),
            'cross_origin_opener_policy_safe' => in_array($crossOriginOpenerPolicy, ['same-origin', 'same-origin-allow-popups'], true),
            'permissions_policy_restrictive' => $this->permissionsPolicyIsRestrictive($permissionsPolicy),
            'content_security_policy_configured' => filled($contentSecurityPolicy),
            'content_security_policy_baseline_directives_present' => $this->contentSecurityPolicyHasBaselineDirectives($contentSecurityPolicy),
            'content_security_policy_blocks_inline_scripts' => ! preg_match("/script-src[^;]*'unsafe-inline'/i", $contentSecurityPolicy),
            'content_security_policy_without_unsafe_inline' => ! str_contains($contentSecurityPolicy, "'unsafe-inline'"),
            'authenticated_no_store_enabled' => config('security.authenticated_cache.no_store_enabled') === true,
        ];

        $requiredKeys = $production
            ? array_keys($requirements)
            : [
                'app_key_configured',
                'same_site_policy_configured',
                'frame_options_safe',
                'content_type_options_nosniff',
                'referrer_policy_safe',
                'cross_origin_opener_policy_safe',
                'permissions_policy_restrictive',
                'content_security_policy_configured',
                'content_security_policy_baseline_directives_present',
                'content_security_policy_blocks_inline_scripts',
            ];

        $failures = collect($requiredKeys)
            ->reject(fn (string $key): bool => $requirements[$key] === true)
            ->values()
            ->all();

        return [
            'status' => empty($failures) ? 'ok' : 'degraded',
            'environment_profile' => $production ? 'production' : 'non_production',
            'production_requirements_enforced' => $production,
            'requirements' => $requirements,
            'failures' => $failures,
        ];
    }

    private function permissionsPolicyIsRestrictive(string $permissionsPolicy): bool
    {
        if ($permissionsPolicy === '' || str_contains($permissionsPolicy, '*')) {
            return false;
        }

        foreach (['camera=()', 'microphone=()', 'payment=()', 'usb=()'] as $requiredDirective) {
            if (! str_contains($permissionsPolicy, $requiredDirective)) {
                return false;
            }
        }

        return true;
    }

    private function contentSecurityPolicyHasBaselineDirectives(string $contentSecurityPolicy): bool
    {
        $policy = strtolower($contentSecurityPolicy);

        foreach (['default-src', 'base-uri', 'object-src', 'frame-ancestors', 'script-src'] as $directive) {
            if (! str_contains($policy, $directive)) {
                return false;
            }
        }

        return ! str_contains($policy, 'script-src *')
            && ! preg_match('/script-src[^;]*(http:|https:|\*)/i', $contentSecurityPolicy);
    }

    private function isProduction(): bool
    {
        return (string) config('app.env', app()->environment()) === 'production';
    }

    private function appUrlUsesHttps(): bool
    {
        $url = (string) config('app.url');

        return str_starts_with(strtolower($url), 'https://');
    }
}
