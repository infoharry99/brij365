<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use App\Services\Operations\HealthCheckService;
use Tests\TestCase;

class OperationalHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_health_endpoint_returns_safe_liveness_payload(): void
    {
        $response = $this->getJson(route('health'));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', config('app.name'))
            ->assertJsonStructure([
                'status',
                'service',
                'timestamp',
            ])
            ->assertJsonMissingPath('checks')
            ->assertJsonMissingPath('database');
    }

    public function test_readiness_endpoint_requires_authenticated_operations_user(): void
    {
        $this->seed();

        $partner = User::where('email', 'sameer.bafna@partners.builder360.test')->firstOrFail();

        $this->getJson(route('operations.readiness'))->assertUnauthorized();

        $this->actingAs($partner)
            ->getJson(route('operations.readiness'))
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'operations.readiness.viewed',
        ]);
    }

    public function test_system_admin_can_view_sqlite_readiness_checks(): void
    {
        $this->seed();

        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('session.driver', 'database');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();
        $activeSettingCount = SystemSetting::query()->where('status', 'active')->count();
        $notificationCount = UserNotification::query()->count();
        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.database.connection', 'sqlite')
            ->assertJsonPath('checks.database.driver', 'sqlite')
            ->assertJsonPath('checks.database.sqlite.mode', 'memory')
            ->assertJsonPath('checks.database.sqlite.database', ':memory:')
            ->assertJsonPath('checks.database.sqlite.foreign_key_constraints_enabled', true)
            ->assertJsonPath('checks.database.sqlite.ready', true)
            ->assertJsonPath('checks.database.sqlite.failure', null)
            ->assertJsonPath('checks.migrations.status', 'ok')
            ->assertJsonPath('checks.migrations.pending_count', 0)
            ->assertJsonPath('checks.session.status', 'ok')
            ->assertJsonPath('checks.session.driver', 'database')
            ->assertJsonPath('checks.session.database_table', 'present')
            ->assertJsonPath('checks.session.lifetime_minutes', 120)
            ->assertJsonPath('checks.session.production_driver_acceptable', true)
            ->assertJsonPath('checks.session.failure', null)
            ->assertJsonPath('checks.auth.status', 'ok')
            ->assertJsonPath('checks.auth.guard', 'web')
            ->assertJsonPath('checks.auth.guard_driver', 'session')
            ->assertJsonPath('checks.auth.provider', 'users')
            ->assertJsonPath('checks.auth.provider_driver', 'eloquent')
            ->assertJsonPath('checks.auth.provider_model', User::class)
            ->assertJsonPath('checks.auth.provider_model_valid', true)
            ->assertJsonPath('checks.auth.users_table', 'present')
            ->assertJsonPath('checks.auth.missing_user_columns', [])
            ->assertJsonPath('checks.auth.email_verification_supported', true)
            ->assertJsonPath('checks.auth.account_status_supported', true)
            ->assertJsonPath('checks.auth.remember_token_supported', true)
            ->assertJsonPath('checks.auth.password_broker', 'users')
            ->assertJsonPath('checks.auth.password_reset_table', 'present')
            ->assertJsonPath('checks.auth.password_reset_table_name', 'password_reset_tokens')
            ->assertJsonPath('checks.auth.missing_password_reset_columns', [])
            ->assertJsonPath('checks.auth.password_reset_expire_minutes', 60)
            ->assertJsonPath('checks.auth.password_reset_expire_acceptable', true)
            ->assertJsonPath('checks.auth.password_reset_throttle_seconds', 60)
            ->assertJsonPath('checks.auth.password_reset_throttle_acceptable', true)
            ->assertJsonPath('checks.auth.password_timeout_seconds', 10800)
            ->assertJsonPath('checks.auth.password_timeout_acceptable', true)
            ->assertJsonPath('checks.auth.password_policy.min_length', 10)
            ->assertJsonPath('checks.auth.password_policy.max_length', 255)
            ->assertJsonPath('checks.auth.password_policy.require_mixed_case', true)
            ->assertJsonPath('checks.auth.password_policy.require_numbers', true)
            ->assertJsonPath('checks.auth.password_policy.require_symbols', true)
            ->assertJsonPath('checks.auth.password_policy.uncompromised', false)
            ->assertJsonPath('checks.auth.password_policy.max_compromised_threshold', 0)
            ->assertJsonPath('checks.auth.password_policy.requirements.min_length_at_least_10', true)
            ->assertJsonPath('checks.auth.password_policy.requirements.max_length_at_least_min_length', true)
            ->assertJsonPath('checks.auth.password_policy.requirements.mixed_case_required', true)
            ->assertJsonPath('checks.auth.password_policy.requirements.numbers_required', true)
            ->assertJsonPath('checks.auth.password_policy.requirements.symbols_required', true)
            ->assertJsonPath('checks.auth.password_policy.acceptable', true)
            ->assertJsonPath('checks.auth.password_policy.failure', null)
            ->assertJsonPath('checks.auth.failure', null)
            ->assertJsonPath('checks.authorization.status', 'ok')
            ->assertJsonPath('checks.authorization.roles_table', 'present')
            ->assertJsonPath('checks.authorization.missing_columns', [])
            ->assertJsonPath('checks.authorization.active_role_count', 14)
            ->assertJsonPath('checks.authorization.required_role_slugs', [
                'director',
                'sales_head',
                'construction_head',
                'finance_head',
                'hr_manager',
                'buyer',
                'employee',
                'payroll',
                'recruiter',
                'auditor',
                'compliance',
                'system_admin',
                'channel_partner',
                'executive_partner_broker',
            ])
            ->assertJsonPath('checks.authorization.missing_required_role_slugs', [])
            ->assertJsonPath('checks.authorization.allowed_scope_levels', [
                'global',
                'department',
                'self',
                'readonly',
                'partner',
            ])
            ->assertJsonPath('checks.authorization.invalid_scope_role_slugs', [])
            ->assertJsonPath('checks.authorization.invalid_permission_role_slugs', [])
            ->assertJsonPath('checks.authorization.wildcard_role_slugs', ['director'])
            ->assertJsonPath('checks.authorization.wildcard_role_present', true)
            ->assertJsonPath('checks.authorization.required_operational_role_slugs', [
                'director',
                'system_admin',
                'auditor',
            ])
            ->assertJsonPath('checks.authorization.operational_roles_with_active_users', [
                'auditor',
                'director',
                'system_admin',
            ])
            ->assertJsonPath('checks.authorization.missing_operational_role_user_slugs', [])
            ->assertJsonPath('checks.authorization.failure', null)
            ->assertJsonPath('checks.configuration.status', 'ok')
            ->assertJsonPath('checks.configuration.system_settings_table', 'present')
            ->assertJsonPath('checks.configuration.missing_columns', [])
            ->assertJsonPath('checks.configuration.active_setting_count', $activeSettingCount)
            ->assertJsonPath('checks.configuration.required_active_keys', [
                'hr.attendance.rules',
                'after_sales.sla_hours',
                'workflow.approval_chains',
                'governance.backup_dr',
                'payroll.tax_rules',
                'hr.leave.rules',
                'payroll.commission_rules',
                'finance.gst_rules',
                'construction.contractor_billing',
            ])
            ->assertJsonPath('checks.configuration.missing_active_keys', [])
            ->assertJsonPath('checks.configuration.allowed_value_types', [
                'json',
                'object',
                'array',
                'string',
                'number',
                'boolean',
            ])
            ->assertJsonPath('checks.configuration.invalid_active_setting_keys', [])
            ->assertJsonPath('checks.configuration.duplicate_active_scope_keys', [])
            ->assertJsonPath('checks.configuration.failure', null)
            ->assertJsonPath('checks.audit.status', 'ok')
            ->assertJsonPath('checks.audit.audit_events_table', 'present')
            ->assertJsonPath('checks.audit.missing_columns', [])
            ->assertJsonPath('checks.audit.required_indexes', [
                'audit_events_user_created_index',
                'audit_events_type_created_index',
                'audit_events_auditable_created_index',
                'audit_events_request_context_index',
            ])
            ->assertJsonPath('checks.audit.missing_indexes', [])
            ->assertJsonPath('checks.audit.redaction_self_test_passes', true)
            ->assertJsonPath('checks.audit.event_count', 0)
            ->assertJsonPath('checks.audit.last_audit_at', null)
            ->assertJsonPath('checks.audit.max_activity_age_hours', 24)
            ->assertJsonPath('checks.audit.activity_recent', false)
            ->assertJsonPath('checks.audit.production_activity_acceptable', true)
            ->assertJsonPath('checks.audit.failure', null)
            ->assertJsonPath('checks.notifications.status', 'ok')
            ->assertJsonPath('checks.notifications.user_notifications_table', 'present')
            ->assertJsonPath('checks.notifications.missing_columns', [])
            ->assertJsonPath('checks.notifications.required_indexes', [
                'notifications_recipient_created_index',
                'notifications_recipient_status_created_index',
                'notifications_recipient_category_status_index',
                'notifications_recipient_severity_status_index',
            ])
            ->assertJsonPath('checks.notifications.missing_indexes', [])
            ->assertJsonPath('checks.notifications.allowed_statuses', [
                'unread',
                'read',
                'archived',
            ])
            ->assertJsonPath('checks.notifications.notification_count', $notificationCount)
            ->assertJsonPath('checks.notifications.invalid_status_count', 0)
            ->assertJsonPath('checks.notifications.missing_recipient_count', 0)
            ->assertJsonPath('checks.notifications.unread_threshold.max_unread_per_user', 250)
            ->assertJsonPath('checks.notifications.unread_threshold.exceeded_users', [])
            ->assertJsonPath('checks.notifications.max_activity_age_hours', 24)
            ->assertJsonPath('checks.notifications.activity_recent', true)
            ->assertJsonPath('checks.notifications.production_activity_acceptable', true)
            ->assertJsonPath('checks.notifications.failure', null)
            ->assertJsonPath('checks.report_limits.status', 'ok')
            ->assertJsonPath('checks.report_limits.max_date_range_days', 366)
            ->assertJsonPath('checks.report_limits.max_export_rows', 500)
            ->assertJsonPath('checks.report_limits.max_export_rows_ceiling', 5000)
            ->assertJsonPath('checks.report_limits.requirements.date_range_within_operational_year', true)
            ->assertJsonPath('checks.report_limits.requirements.export_row_limit_within_ceiling', true)
            ->assertJsonPath('checks.payroll_controls.status', 'ok')
            ->assertJsonPath('checks.payroll_controls.period_year_min', 2020)
            ->assertJsonPath('checks.payroll_controls.period_year_max', 2100)
            ->assertJsonPath('checks.payroll_controls.working_days_min', 1)
            ->assertJsonPath('checks.payroll_controls.working_days_max', 31)
            ->assertJsonPath('checks.payroll_controls.requirements.period_window_not_unbounded', true)
            ->assertJsonPath('checks.payroll_controls.requirements.working_days_max_calendar_safe', true)
            ->assertJsonPath('checks.pagination.status', 'ok')
            ->assertJsonPath('checks.pagination.default_max_per_page', 50)
            ->assertJsonPath('checks.pagination.large_max_per_page', 100)
            ->assertJsonPath('checks.pagination.absolute_max_per_page', 100)
            ->assertJsonPath('checks.pagination.absolute_ceiling', 250)
            ->assertJsonPath('checks.pagination.default_per_page', 15)
            ->assertJsonPath('checks.pagination.workspace_per_page', 25)
            ->assertJsonPath('checks.pagination.large_per_page', 50)
            ->assertJsonPath('checks.pagination.requirements.default_per_page_within_default_max', true)
            ->assertJsonPath('checks.pagination.requirements.workspace_per_page_within_default_max', true)
            ->assertJsonPath('checks.pagination.requirements.large_per_page_within_large_max', true)
            ->assertJsonPath('checks.pagination.requirements.default_not_above_large', true)
            ->assertJsonPath('checks.pagination.requirements.large_not_above_absolute', true)
            ->assertJsonPath('checks.pagination.requirements.absolute_ceiling_operationally_safe', true)
            ->assertJsonPath('checks.money_input_limits.status', 'ok')
            ->assertJsonPath('checks.money_input_limits.limits.enterprise_amount_max', '999999999999.99')
            ->assertJsonPath('checks.money_input_limits.limits.payment_amount_max', '999999999999')
            ->assertJsonPath('checks.money_input_limits.limits.hr_amount_max', '999999999.99')
            ->assertJsonPath('checks.money_input_limits.limits.ctc_amount_max', '9999999999')
            ->assertJsonPath('checks.money_input_limits.requirements.all_limits_positive', true)
            ->assertJsonPath('checks.money_input_limits.requirements.hr_within_hr_ceiling', true)
            ->assertJsonPath('checks.money_input_limits.requirements.ctc_within_ctc_ceiling', true)
            ->assertJsonPath('checks.operational_input_limits.status', 'ok')
            ->assertJsonPath('checks.operational_input_limits.limits.procurement_quantity_max', '9999999')
            ->assertJsonPath('checks.operational_input_limits.limits.construction_quantity_max', '999999999')
            ->assertJsonPath('checks.operational_input_limits.limits.rate_max', '999999999')
            ->assertJsonPath('checks.operational_input_limits.limits.equipment_hours_max', '24')
            ->assertJsonPath('checks.operational_input_limits.requirements.all_limits_positive', true)
            ->assertJsonPath('checks.operational_input_limits.requirements.procurement_quantity_within_quantity_ceiling', true)
            ->assertJsonPath('checks.operational_input_limits.requirements.equipment_hours_within_daily_ceiling', true)
            ->assertJsonPath('checks.cache.status', 'ok')
            ->assertJsonPath('checks.cache.store', config('cache.default'))
            ->assertJsonPath('checks.queue.status', 'ok')
            ->assertJsonPath('checks.queue.connection', config('queue.default'))
            ->assertJsonPath('checks.queue.jobs_table', 'present')
            ->assertJsonPath('checks.queue.failed_jobs_driver', config('queue.failed.driver'))
            ->assertJsonPath('checks.queue.failed_jobs_table', 'present')
            ->assertJsonPath('checks.queue.batching_table', 'present')
            ->assertJsonPath('checks.queue.pending_jobs', 0)
            ->assertJsonPath('checks.queue.reserved_jobs', 0)
            ->assertJsonPath('checks.queue.failed_jobs', 0)
            ->assertJsonPath('checks.queue.backlog_acceptable', true)
            ->assertJsonPath('checks.queue.thresholds.max_pending_jobs', 1000)
            ->assertJsonPath('checks.queue.thresholds.max_reserved_jobs', 250)
            ->assertJsonPath('checks.queue.thresholds.max_failed_jobs', 0)
            ->assertJsonPath('checks.storage.status', 'ok')
            ->assertJsonPath('checks.storage.local_private_serving_enabled', false)
            ->assertJsonPath('checks.storage.production_acceptable', true)
            ->assertJsonPath('checks.document_uploads.status', 'ok')
            ->assertJsonPath('checks.document_uploads.allowed_extensions', [
                'pdf',
                'jpg',
                'jpeg',
                'png',
                'doc',
                'docx',
                'xls',
                'xlsx',
                'csv',
            ])
            ->assertJsonPath('checks.document_uploads.max_file_size_kb', 10240)
            ->assertJsonPath('checks.document_uploads.requirements.dangerous_extensions_blocked', true)
            ->assertJsonPath('checks.document_uploads.requirements.wildcard_mime_types_blocked', true)
            ->assertJsonPath('checks.document_uploads.requirements.sha256_checksum_supported', true)
            ->assertJsonPath('checks.backup.status', 'ok')
            ->assertJsonPath('checks.backup.driver', 'sqlite')
            ->assertJsonPath('checks.backup.strategy', 'builder360:sqlite-backup')
            ->assertJsonPath('checks.backup.configured_directory', 'backups/sqlite')
            ->assertJsonPath('checks.scheduler.status', 'ok')
            ->assertJsonPath('checks.scheduler.enabled', true)
            ->assertJsonPath('checks.scheduler.required_commands', [
                'collaboration:release-scheduled-messages',
                'builder360:sqlite-backup',
                'builder360:sqlite-backup-verify',
            ])
            ->assertJsonPath('checks.scheduler.missing_required_commands', [])
            ->assertJsonPath('checks.scheduler.production_acceptable', true)
            ->assertJsonPath('checks.assets.status', 'ok')
            ->assertJsonPath('checks.assets.asset_mode', 'classic_public_assets')
            ->assertJsonPath('checks.assets.public_root', 'present')
            ->assertJsonPath('checks.assets.required_files', [
                'css/builder360-classic.css',
                'js/builder360-classic.js',
            ])
            ->assertJsonPath('checks.assets.missing_files', [])
            ->assertJsonPath('checks.integrations.status', 'ok')
            ->assertJsonPath('checks.integrations.payment_gateway.provider', 'prototype')
            ->assertJsonPath('checks.integrations.payment_gateway.prototype_provider', true)
            ->assertJsonPath('checks.integrations.payment_gateway.webhook_secret_configured', false)
            ->assertJsonPath('checks.integrations.payment_gateway.production_acceptable', true)
            ->assertJsonPath('checks.integrations.payment_gateway.failure', null)
            ->assertJsonPath('checks.optimization.status', 'ok')
            ->assertJsonPath('checks.optimization.production_acceptable', true)
            ->assertJsonPath('checks.mail.status', 'ok')
            ->assertJsonPath('checks.mail.production_acceptable', true)
            ->assertJsonPath('checks.logging.status', 'ok')
            ->assertJsonPath('checks.logging.production_acceptable', true)
            ->assertJsonPath('checks.rate_limiting.status', 'ok')
            ->assertJsonPath('checks.rate_limiting.erp_read_limiter_registered', true)
            ->assertJsonPath('checks.rate_limiting.limits.erp_read_per_minute.configured', 1200)
            ->assertJsonPath('checks.rate_limiting.limits.erp_read_per_minute.minimum', 1)
            ->assertJsonPath('checks.rate_limiting.limits.erp_read_per_minute.maximum', 5000)
            ->assertJsonPath('checks.rate_limiting.limits.erp_read_per_minute.acceptable', true)
            ->assertJsonPath('checks.rate_limiting.limits.erp_write_per_minute.configured', 600)
            ->assertJsonPath('checks.rate_limiting.limits.erp_write_per_minute.minimum', 1)
            ->assertJsonPath('checks.rate_limiting.limits.erp_write_per_minute.maximum', 2500)
            ->assertJsonPath('checks.rate_limiting.limits.erp_write_per_minute.must_not_exceed_read_limit', true)
            ->assertJsonPath('checks.rate_limiting.limits.erp_write_per_minute.acceptable', true)
            ->assertJsonPath('checks.rate_limiting.route_coverage.required_business_middleware', [
                'web',
                'auth',
                'account.active',
                'verified',
                'throttle:erp-read',
                'erp.write_limit',
            ])
            ->assertJsonPath('checks.rate_limiting.route_coverage.missing_business_route_middleware', [])
            ->assertJsonPath('checks.rate_limiting.route_coverage.auth_lifecycle_route_issues', [])
            ->assertJsonPath('checks.rate_limiting.route_coverage.signed_integration_route_issues', [])
            ->assertJsonPath('checks.rate_limiting.failure', null)
            ->assertJsonPath('checks.csrf.status', 'ok')
            ->assertJsonPath('checks.csrf.csrf_middleware', ValidateCsrfToken::class)
            ->assertJsonPath('checks.csrf.approved_exempt_routes', [
                'finance.payment-gateway.webhook',
            ])
            ->assertJsonPath('checks.csrf.csrf_exempt_routes', [
                'finance.payment-gateway.webhook',
            ])
            ->assertJsonPath('checks.csrf.unexpected_csrf_exempt_routes', [])
            ->assertJsonPath('checks.csrf.missing_web_middleware_routes', [])
            ->assertJsonPath('checks.csrf.approved_exempt_route_issues', [])
            ->assertJsonPath('checks.csrf.failure', null)
            ->assertJsonPath('checks.exception_handling.status', 'ok')
            ->assertJsonPath('checks.exception_handling.factory_registered', true)
            ->assertJsonPath('checks.exception_handling.json_request_id_enabled', true)
            ->assertJsonPath('checks.exception_handling.include_debug_details', false)
            ->assertJsonPath('checks.exception_handling.generic_server_error_message_configured', true)
            ->assertJsonPath('checks.exception_handling.generic_server_error_message_safe', true)
            ->assertJsonPath('checks.exception_handling.production_debug_details_acceptable', true)
            ->assertJsonPath('checks.exception_handling.failure', null)
            ->assertJsonPath('checks.security.status', 'ok')
            ->assertJsonPath('checks.security.environment_profile', 'non_production')
            ->assertJsonPath('checks.security.production_requirements_enforced', false)
            ->assertJsonPath('checks.security.requirements.app_key_configured', true)
            ->assertJsonPath('checks.security.requirements.frame_options_safe', true)
            ->assertJsonPath('checks.security.requirements.content_type_options_nosniff', true)
            ->assertJsonPath('checks.security.requirements.referrer_policy_safe', true)
            ->assertJsonPath('checks.security.requirements.cross_origin_opener_policy_safe', true)
            ->assertJsonPath('checks.security.requirements.permissions_policy_restrictive', true)
            ->assertJsonPath('checks.security.requirements.content_security_policy_configured', true)
            ->assertJsonPath('checks.security.requirements.content_security_policy_baseline_directives_present', true)
            ->assertJsonPath('checks.security.requirements.content_security_policy_blocks_inline_scripts', true)
            ->assertJsonPath('checks.security.requirements.authenticated_no_store_enabled', true);

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('ok', $event->metadata['status']);
        $this->assertSame(app()->environment(), $event->metadata['environment']);
        $this->assertSame('ok', $event->metadata['check_statuses']['database']);
        $this->assertSame('ok', $event->metadata['check_statuses']['session']);
        $this->assertSame('ok', $event->metadata['check_statuses']['auth']);
        $this->assertSame('ok', $event->metadata['check_statuses']['authorization']);
        $this->assertSame('ok', $event->metadata['check_statuses']['configuration']);
        $this->assertSame('ok', $event->metadata['check_statuses']['audit']);
        $this->assertSame('ok', $event->metadata['check_statuses']['notifications']);
        $this->assertSame('ok', $event->metadata['check_statuses']['report_limits']);
        $this->assertSame('ok', $event->metadata['check_statuses']['payroll_controls']);
        $this->assertSame('ok', $event->metadata['check_statuses']['pagination']);
        $this->assertSame('ok', $event->metadata['check_statuses']['money_input_limits']);
        $this->assertSame('ok', $event->metadata['check_statuses']['operational_input_limits']);
        $this->assertSame('ok', $event->metadata['check_statuses']['document_uploads']);
        $this->assertSame('ok', $event->metadata['check_statuses']['scheduler']);
        $this->assertSame('ok', $event->metadata['check_statuses']['rate_limiting']);
        $this->assertSame('ok', $event->metadata['check_statuses']['csrf']);
        $this->assertSame('ok', $event->metadata['check_statuses']['exception_handling']);
        $this->assertSame('ok', $event->metadata['check_statuses']['security']);
    }

    public function test_readiness_is_degraded_when_auth_provider_model_is_invalid(): void
    {
        $this->seed();

        Config::set('auth.providers.users.model', 'App\\Models\\MissingOperationalAuthUser');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.auth.status', 'degraded')
            ->assertJsonPath('checks.auth.provider_model', 'App\\Models\\MissingOperationalAuthUser')
            ->assertJsonPath('checks.auth.provider_model_valid', false)
            ->assertJsonPath('checks.auth.failure', 'auth_provider_model_invalid');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['auth']);
    }

    public function test_readiness_is_degraded_when_password_reset_table_is_missing(): void
    {
        $this->seed();

        Schema::dropIfExists('password_reset_tokens');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.auth.status', 'degraded')
            ->assertJsonPath('checks.auth.password_reset_table', 'missing')
            ->assertJsonPath('checks.auth.password_reset_table_name', 'password_reset_tokens')
            ->assertJsonPath('checks.auth.missing_password_reset_columns', [
                'email',
                'token',
                'created_at',
            ])
            ->assertJsonPath('checks.auth.failure', 'password_reset_table_missing');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['auth']);
    }

    public function test_readiness_is_degraded_when_password_policy_is_weakened(): void
    {
        $this->seed();

        Config::set('security.password_policy.min_length', 8);
        Config::set('security.password_policy.require_symbols', false);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.auth.status', 'degraded')
            ->assertJsonPath('checks.auth.password_policy.min_length', 8)
            ->assertJsonPath('checks.auth.password_policy.require_symbols', false)
            ->assertJsonPath('checks.auth.password_policy.requirements.min_length_at_least_10', false)
            ->assertJsonPath('checks.auth.password_policy.requirements.symbols_required', false)
            ->assertJsonPath('checks.auth.password_policy.acceptable', false)
            ->assertJsonPath('checks.auth.password_policy.failure', 'password_policy_min_length_at_least_10_failed')
            ->assertJsonPath('checks.auth.failure', 'password_policy_invalid');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['auth']);
    }

    public function test_readiness_is_degraded_when_required_role_is_inactive(): void
    {
        $this->seed();

        Role::where('slug', 'system_admin')->update(['is_active' => false]);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.authorization.status', 'degraded')
            ->assertJsonPath('checks.authorization.missing_required_role_slugs', ['system_admin'])
            ->assertJsonPath('checks.authorization.failure', 'authorization_required_roles_missing');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['authorization']);
    }

    public function test_readiness_is_degraded_when_role_permissions_are_invalid(): void
    {
        $this->seed();

        Role::where('slug', 'auditor')->update(['permissions' => []]);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.authorization.status', 'degraded')
            ->assertJsonPath('checks.authorization.invalid_permission_role_slugs', ['auditor'])
            ->assertJsonPath('checks.authorization.failure', 'authorization_role_permissions_invalid');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['authorization']);
    }

    public function test_readiness_is_degraded_when_required_active_setting_is_missing(): void
    {
        $this->seed();

        SystemSetting::where('setting_key', 'payroll.tax_rules')->update(['status' => 'archived']);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.configuration.status', 'degraded')
            ->assertJsonPath('checks.configuration.missing_active_keys', ['payroll.tax_rules'])
            ->assertJsonPath('checks.configuration.failure', 'configuration_required_settings_missing');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['configuration']);
    }

    public function test_readiness_is_degraded_when_active_setting_is_invalid(): void
    {
        $this->seed();

        SystemSetting::where('setting_key', 'hr.attendance.rules')->update(['approved_at' => null]);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.configuration.status', 'degraded')
            ->assertJsonPath('checks.configuration.invalid_active_setting_keys', ['company:1:hr.attendance.rules'])
            ->assertJsonPath('checks.configuration.failure', 'configuration_active_settings_invalid');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['configuration']);
    }

    public function test_readiness_is_degraded_when_required_audit_index_is_missing(): void
    {
        $this->seed();

        Schema::table('audit_events', function ($table): void {
            $table->dropIndex('audit_events_request_context_index');
        });

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.audit.status', 'degraded')
            ->assertJsonPath('checks.audit.missing_indexes', ['audit_events_request_context_index'])
            ->assertJsonPath('checks.audit.failure', 'audit_indexes_missing');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['audit']);
    }

    public function test_readiness_is_degraded_when_audit_redaction_self_test_fails(): void
    {
        $this->seed();

        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            public function redactionSelfTestPasses(): bool
            {
                return false;
            }
        });

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.audit.status', 'degraded')
            ->assertJsonPath('checks.audit.redaction_self_test_passes', false)
            ->assertJsonPath('checks.audit.failure', 'audit_redaction_self_test_failed');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['audit']);
    }

    public function test_readiness_is_degraded_when_required_notification_index_is_missing(): void
    {
        $this->seed();

        Schema::table('user_notifications', function ($table): void {
            $table->dropIndex('notifications_recipient_status_created_index');
        });

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.notifications.status', 'degraded')
            ->assertJsonPath('checks.notifications.missing_indexes', ['notifications_recipient_status_created_index'])
            ->assertJsonPath('checks.notifications.failure', 'notifications_indexes_missing');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['notifications']);
    }

    public function test_readiness_is_degraded_when_notification_status_is_invalid(): void
    {
        $this->seed();

        UserNotification::query()->where('notification_number', 'NTF-10001')->update(['status' => 'stuck']);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.notifications.status', 'degraded')
            ->assertJsonPath('checks.notifications.invalid_status_count', 1)
            ->assertJsonPath('checks.notifications.failure', 'notifications_invalid_statuses');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['notifications']);
    }

    public function test_readiness_is_degraded_when_notification_unread_backlog_exceeds_threshold(): void
    {
        $this->seed();

        Config::set('builder360.notifications.max_unread_per_user', 0);

        $exceededUsers = UserNotification::query()
            ->select('recipient_user_id', DB::raw('count(*) as unread_count'))
            ->where('status', 'unread')
            ->groupBy('recipient_user_id')
            ->orderBy('recipient_user_id')
            ->get()
            ->map(fn (UserNotification $notification): array => [
                'recipient_user_id' => (int) $notification->recipient_user_id,
                'unread_count' => (int) $notification->unread_count,
            ])
            ->values()
            ->all();

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.notifications.status', 'degraded')
            ->assertJsonPath('checks.notifications.unread_threshold.max_unread_per_user', 0)
            ->assertJsonPath('checks.notifications.unread_threshold.exceeded_users', $exceededUsers)
            ->assertJsonPath('checks.notifications.failure', 'notifications_unread_threshold_exceeded');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['notifications']);
    }

    public function test_sqlite_readiness_is_degraded_when_foreign_keys_are_disabled(): void
    {
        $this->seed();

        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('database.connections.sqlite.foreign_key_constraints', false);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.database.status', 'degraded')
            ->assertJsonPath('checks.database.connection', 'sqlite')
            ->assertJsonPath('checks.database.driver', 'sqlite')
            ->assertJsonPath('checks.database.sqlite.mode', 'memory')
            ->assertJsonPath('checks.database.sqlite.foreign_key_constraints_enabled', false)
            ->assertJsonPath('checks.database.sqlite.ready', false)
            ->assertJsonPath('checks.database.sqlite.failure', 'sqlite_foreign_keys_disabled');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['database']);
    }

    public function test_readiness_is_degraded_when_document_upload_policy_allows_unsafe_files(): void
    {
        $this->seed();

        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('builder360.documents.allowed_extensions', ['pdf', 'php']);
        Config::set('builder360.documents.allowed_mime_types', ['application/pdf', '*/*']);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.document_uploads.status', 'degraded')
            ->assertJsonPath('checks.document_uploads.dangerous_extensions', ['php'])
            ->assertJsonPath('checks.document_uploads.unsafe_mime_types', ['*/*'])
            ->assertJsonPath('checks.document_uploads.failure', 'document_upload_dangerous_extensions_blocked');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['document_uploads']);
    }

    public function test_readiness_is_degraded_when_security_headers_are_unsafe(): void
    {
        $this->seed();

        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('security.headers.X-Frame-Options', 'ALLOWALL');
        Config::set('security.headers.X-Content-Type-Options', '');
        Config::set('security.headers.Referrer-Policy', 'unsafe-url');
        Config::set('security.headers.Cross-Origin-Opener-Policy', 'unsafe-none');
        Config::set('security.headers.Permissions-Policy', 'camera=*, microphone=*');
        Config::set('security.headers.Content-Security-Policy', "default-src *; script-src 'self' 'unsafe-inline'");

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.security.status', 'degraded')
            ->assertJsonPath('checks.security.requirements.frame_options_safe', false)
            ->assertJsonPath('checks.security.requirements.content_type_options_nosniff', false)
            ->assertJsonPath('checks.security.requirements.referrer_policy_safe', false)
            ->assertJsonPath('checks.security.requirements.cross_origin_opener_policy_safe', false)
            ->assertJsonPath('checks.security.requirements.permissions_policy_restrictive', false)
            ->assertJsonPath('checks.security.requirements.content_security_policy_baseline_directives_present', false)
            ->assertJsonPath('checks.security.requirements.content_security_policy_blocks_inline_scripts', false)
            ->assertJsonPath('checks.security.failures', [
                'frame_options_safe',
                'content_type_options_nosniff',
                'referrer_policy_safe',
                'cross_origin_opener_policy_safe',
                'permissions_policy_restrictive',
                'content_security_policy_baseline_directives_present',
                'content_security_policy_blocks_inline_scripts',
            ]);

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['security']);
    }

    public function test_readiness_is_degraded_when_report_limits_are_unsafe(): void
    {
        $this->seed();

        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('builder360.reports.max_date_range_days', 730);
        Config::set('builder360.reports.max_export_rows', 6000);
        Config::set('builder360.reports.max_export_rows_ceiling', 5000);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.report_limits.status', 'degraded')
            ->assertJsonPath('checks.report_limits.max_date_range_days', 730)
            ->assertJsonPath('checks.report_limits.max_export_rows', 6000)
            ->assertJsonPath('checks.report_limits.requirements.date_range_within_operational_year', false)
            ->assertJsonPath('checks.report_limits.requirements.export_row_limit_within_ceiling', false)
            ->assertJsonPath('checks.report_limits.failure', 'report_limits_date_range_within_operational_year');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['report_limits']);
    }

    public function test_readiness_is_degraded_when_payroll_controls_are_unsafe(): void
    {
        $this->seed();

        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('builder360.payroll.period_year_min', 1999);
        Config::set('builder360.payroll.period_year_max', 2200);
        Config::set('builder360.payroll.working_days_min', 1);
        Config::set('builder360.payroll.working_days_max', 45);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.payroll_controls.status', 'degraded')
            ->assertJsonPath('checks.payroll_controls.period_year_min', 1999)
            ->assertJsonPath('checks.payroll_controls.period_year_max', 2200)
            ->assertJsonPath('checks.payroll_controls.working_days_max', 45)
            ->assertJsonPath('checks.payroll_controls.requirements.period_min_year_reasonable', false)
            ->assertJsonPath('checks.payroll_controls.requirements.period_window_not_unbounded', false)
            ->assertJsonPath('checks.payroll_controls.requirements.working_days_max_calendar_safe', false)
            ->assertJsonPath('checks.payroll_controls.failure', 'payroll_controls_period_min_year_reasonable');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['payroll_controls']);
    }

    public function test_readiness_is_degraded_when_pagination_limits_are_unsafe(): void
    {
        $this->seed();

        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('builder360.pagination.default_per_page', 60);
        Config::set('builder360.pagination.workspace_per_page', 70);
        Config::set('builder360.pagination.large_per_page', 120);
        Config::set('builder360.pagination.default_max_per_page', 150);
        Config::set('builder360.pagination.large_max_per_page', 100);
        Config::set('builder360.pagination.absolute_max_per_page', 100);
        Config::set('builder360.pagination.absolute_ceiling', 500);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.pagination.status', 'degraded')
            ->assertJsonPath('checks.pagination.default_max_per_page', 150)
            ->assertJsonPath('checks.pagination.large_max_per_page', 100)
            ->assertJsonPath('checks.pagination.absolute_max_per_page', 100)
            ->assertJsonPath('checks.pagination.absolute_ceiling', 500)
            ->assertJsonPath('checks.pagination.default_per_page', 60)
            ->assertJsonPath('checks.pagination.workspace_per_page', 70)
            ->assertJsonPath('checks.pagination.large_per_page', 120)
            ->assertJsonPath('checks.pagination.requirements.large_per_page_within_large_max', false)
            ->assertJsonPath('checks.pagination.requirements.default_not_above_large', false)
            ->assertJsonPath('checks.pagination.requirements.large_not_above_absolute', true)
            ->assertJsonPath('checks.pagination.requirements.absolute_ceiling_operationally_safe', false)
            ->assertJsonPath('checks.pagination.failure', 'pagination_large_per_page_within_large_max');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['pagination']);
    }

    public function test_readiness_is_degraded_when_money_input_limits_are_unsafe(): void
    {
        $this->seed();

        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('builder360.money_input_limits.hr_amount_max', '1000000000.00');
        Config::set('builder360.money_input_limits.hr_amount_ceiling', '999999999.99');
        Config::set('builder360.money_input_limits.ctc_amount_max', '10000000000');
        Config::set('builder360.money_input_limits.ctc_amount_ceiling', '9999999999');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.money_input_limits.status', 'degraded')
            ->assertJsonPath('checks.money_input_limits.limits.hr_amount_max', '1000000000.00')
            ->assertJsonPath('checks.money_input_limits.limits.ctc_amount_max', '10000000000')
            ->assertJsonPath('checks.money_input_limits.requirements.hr_within_hr_ceiling', false)
            ->assertJsonPath('checks.money_input_limits.requirements.ctc_within_ctc_ceiling', false)
            ->assertJsonPath('checks.money_input_limits.failure', 'money_input_limits_hr_within_hr_ceiling');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['money_input_limits']);
    }

    public function test_readiness_is_degraded_when_operational_input_limits_are_unsafe(): void
    {
        $this->seed();

        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('builder360.operational_input_limits.procurement_quantity_max', '1000000000');
        Config::set('builder360.operational_input_limits.construction_quantity_max', '1000000000');
        Config::set('builder360.operational_input_limits.rate_max', '1000000000');
        Config::set('builder360.operational_input_limits.equipment_hours_max', '25');
        Config::set('builder360.operational_input_limits.quantity_ceiling', '999999999');
        Config::set('builder360.operational_input_limits.rate_ceiling', '999999999');
        Config::set('builder360.operational_input_limits.equipment_hours_ceiling', '24');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.operational_input_limits.status', 'degraded')
            ->assertJsonPath('checks.operational_input_limits.limits.procurement_quantity_max', '1000000000')
            ->assertJsonPath('checks.operational_input_limits.limits.construction_quantity_max', '1000000000')
            ->assertJsonPath('checks.operational_input_limits.limits.rate_max', '1000000000')
            ->assertJsonPath('checks.operational_input_limits.limits.equipment_hours_max', '25')
            ->assertJsonPath('checks.operational_input_limits.requirements.procurement_quantity_within_quantity_ceiling', false)
            ->assertJsonPath('checks.operational_input_limits.requirements.construction_quantity_within_quantity_ceiling', false)
            ->assertJsonPath('checks.operational_input_limits.requirements.rate_within_rate_ceiling', false)
            ->assertJsonPath('checks.operational_input_limits.requirements.equipment_hours_within_daily_ceiling', false)
            ->assertJsonPath('checks.operational_input_limits.failure', 'operational_input_limits_procurement_quantity_within_quantity_ceiling');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['operational_input_limits']);
    }

    public function test_readiness_is_degraded_when_required_classic_asset_is_missing(): void
    {
        $this->seed();

        Config::set('builder360.assets.required_public_files', ['css/missing-builder360-classic.css']);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.assets.status', 'degraded')
            ->assertJsonPath('checks.assets.asset_mode', 'classic_public_assets')
            ->assertJsonPath('checks.assets.required_files', [
                'css/missing-builder360-classic.css',
            ])
            ->assertJsonPath('checks.assets.missing_files', [
                'css/missing-builder360-classic.css',
            ])
            ->assertJsonPath('checks.assets.failure', 'classic_public_assets_missing');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['assets']);
    }

    public function test_production_readiness_is_degraded_when_deployment_caches_are_missing(): void
    {
        $this->seed();

        Config::set('app.env', 'production');
        Config::set('app.url', 'https://builder360.example.test');
        Config::set('app.debug', false);
        Config::set('session.encrypt', true);
        Config::set('session.secure', true);
        Config::set('session.http_only', true);
        Config::set('security.hsts.enabled', true);
        Config::set('security.headers.Content-Security-Policy', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; upgrade-insecure-requests");
        Config::set('cache.default', 'database');
        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('filesystems.default', 'local');
        Config::set('filesystems.disks.local.serve', false);
        Config::set('mail.default', 'smtp');
        Config::set('mail.from.address', 'noreply@builder360.example');
        Config::set('logging.default', 'single');
        Config::set('logging.channels.single.level', 'info');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.security.status', 'ok')
            ->assertJsonPath('checks.optimization.status', 'degraded')
            ->assertJsonPath('checks.optimization.configuration_cached', false)
            ->assertJsonPath('checks.optimization.routes_cached', false)
            ->assertJsonPath('checks.optimization.production_acceptable', false)
            ->assertJsonPath('checks.optimization.failure', 'configuration_and_routes_not_cached');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['optimization']);
    }

    public function test_auditor_and_director_can_view_readiness(): void
    {
        $this->seed();

        $auditor = User::where('email', 'ishaan.trivedi@builder360.test')->firstOrFail();
        $director = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();

        $this->actingAs($auditor)
            ->getJson(route('operations.readiness'))
            ->assertOk();

        $this->actingAs($director)
            ->getJson(route('operations.readiness'))
            ->assertOk();
    }

    public function test_production_readiness_is_degraded_for_unsafe_security_configuration(): void
    {
        $this->seed();

        Config::set('app.env', 'production');
        Config::set('app.url', 'http://builder360.example.test');
        Config::set('app.debug', true);
        Config::set('session.encrypt', false);
        Config::set('session.secure', false);
        Config::set('session.http_only', false);
        Config::set('security.hsts.enabled', false);
        Config::set('security.authenticated_cache.no_store_enabled', false);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.security.status', 'degraded')
            ->assertJsonPath('checks.security.environment_profile', 'production')
            ->assertJsonPath('checks.security.production_requirements_enforced', true)
            ->assertJsonPath('checks.security.requirements.app_url_uses_https', false)
            ->assertJsonPath('checks.security.requirements.debug_disabled', false)
            ->assertJsonPath('checks.security.requirements.session_encrypted', false)
            ->assertJsonPath('checks.security.requirements.secure_session_cookie', false)
            ->assertJsonPath('checks.security.requirements.http_only_session_cookie', false)
            ->assertJsonPath('checks.security.requirements.hsts_enabled', false)
            ->assertJsonPath('checks.security.requirements.content_security_policy_without_unsafe_inline', false)
            ->assertJsonPath('checks.security.requirements.authenticated_no_store_enabled', false)
            ->assertJsonPath('checks.security.failures', [
                'app_url_uses_https',
                'debug_disabled',
                'session_encrypted',
                'secure_session_cookie',
                'http_only_session_cookie',
                'hsts_enabled',
                'content_security_policy_without_unsafe_inline',
                'authenticated_no_store_enabled',
            ]);

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('production', $event->metadata['environment']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['security']);
    }

    public function test_production_readiness_is_degraded_for_unsafe_infrastructure_configuration(): void
    {
        $this->seed();

        Config::set('app.env', 'production');
        Config::set('app.url', 'https://builder360.example.test');
        Config::set('app.debug', false);
        Config::set('session.encrypt', true);
        Config::set('session.secure', true);
        Config::set('session.http_only', true);
        Config::set('security.hsts.enabled', true);
        Config::set('security.headers.Content-Security-Policy', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; upgrade-insecure-requests");
        Config::set('mail.default', 'smtp');
        Config::set('mail.from.address', 'noreply@builder360.example');
        Config::set('logging.default', 'single');
        Config::set('logging.channels.single.level', 'info');
        Config::set('cache.default', 'array');
        Config::set('queue.default', 'sync');
        Config::set('filesystems.default', 'public');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.security.status', 'ok')
            ->assertJsonPath('checks.cache.status', 'degraded')
            ->assertJsonPath('checks.cache.production_acceptable', false)
            ->assertJsonPath('checks.cache.failure', 'unsafe_production_cache_store')
            ->assertJsonPath('checks.queue.status', 'degraded')
            ->assertJsonPath('checks.queue.production_acceptable', false)
            ->assertJsonPath('checks.queue.failure', 'unsafe_production_queue_connection')
            ->assertJsonPath('checks.storage.status', 'degraded')
            ->assertJsonPath('checks.storage.production_acceptable', false)
            ->assertJsonPath('checks.storage.failure', 'unsafe_production_filesystem_disk');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['cache']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['queue']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['storage']);
    }

    public function test_production_readiness_is_degraded_when_raw_private_local_storage_serving_is_enabled(): void
    {
        $this->seed();

        Config::set('app.env', 'production');
        Config::set('app.url', 'https://builder360.example.test');
        Config::set('app.debug', false);
        Config::set('session.encrypt', true);
        Config::set('session.secure', true);
        Config::set('session.http_only', true);
        Config::set('security.hsts.enabled', true);
        Config::set('security.headers.Content-Security-Policy', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; upgrade-insecure-requests");
        Config::set('cache.default', 'database');
        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('filesystems.default', 'local');
        Config::set('filesystems.disks.local.serve', true);
        Config::set('mail.default', 'smtp');
        Config::set('mail.from.address', 'noreply@builder360.example');
        Config::set('logging.default', 'single');
        Config::set('logging.channels.single.level', 'info');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.security.status', 'ok')
            ->assertJsonPath('checks.storage.status', 'degraded')
            ->assertJsonPath('checks.storage.default_disk', 'local')
            ->assertJsonPath('checks.storage.local_private_serving_enabled', true)
            ->assertJsonPath('checks.storage.production_acceptable', false)
            ->assertJsonPath('checks.storage.failure', 'unsafe_production_local_storage_serving');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['storage']);
    }

    public function test_production_readiness_is_degraded_for_unsafe_mail_and_logging_configuration(): void
    {
        $this->seed();

        Config::set('app.env', 'production');
        Config::set('app.url', 'https://builder360.example.test');
        Config::set('app.debug', false);
        Config::set('session.encrypt', true);
        Config::set('session.secure', true);
        Config::set('session.http_only', true);
        Config::set('security.hsts.enabled', true);
        Config::set('security.headers.Content-Security-Policy', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; upgrade-insecure-requests");
        Config::set('cache.default', 'database');
        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('filesystems.default', 'local');
        Config::set('filesystems.disks.local.serve', false);
        Config::set('mail.default', 'log');
        Config::set('mail.from.address', 'hello@example.com');
        Config::set('logging.default', 'single');
        Config::set('logging.channels.single.level', 'debug');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.security.status', 'ok')
            ->assertJsonPath('checks.mail.status', 'degraded')
            ->assertJsonPath('checks.mail.mailer', 'log')
            ->assertJsonPath('checks.mail.placeholder_from_address', true)
            ->assertJsonPath('checks.mail.production_acceptable', false)
            ->assertJsonPath('checks.mail.failure', 'unsafe_production_mailer')
            ->assertJsonPath('checks.logging.status', 'degraded')
            ->assertJsonPath('checks.logging.channel', 'single')
            ->assertJsonPath('checks.logging.level', 'debug')
            ->assertJsonPath('checks.logging.production_acceptable', false)
            ->assertJsonPath('checks.logging.failure', 'unsafe_production_log_level');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['mail']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['logging']);
    }

    public function test_readiness_is_degraded_when_rate_limit_configuration_is_invalid(): void
    {
        $this->seed();

        Config::set('security.rate_limits.erp_read_per_minute', 0);
        Config::set('security.rate_limits.erp_write_per_minute', 10);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.rate_limiting.status', 'degraded')
            ->assertJsonPath('checks.rate_limiting.limits.erp_read_per_minute.configured', 0)
            ->assertJsonPath('checks.rate_limiting.limits.erp_read_per_minute.acceptable', false)
            ->assertJsonPath('checks.rate_limiting.limits.erp_write_per_minute.configured', 10)
            ->assertJsonPath('checks.rate_limiting.limits.erp_write_per_minute.acceptable', false)
            ->assertJsonPath('checks.rate_limiting.failure', 'rate_limit_config_invalid');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['rate_limiting']);
    }

    public function test_readiness_is_degraded_when_business_route_lacks_erp_rate_limiting(): void
    {
        $this->seed();

        Route::get('/unsafe-readiness-probe', fn () => response()->json(['ok' => true]))
            ->name('unsafe.readiness.probe');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.rate_limiting.status', 'degraded')
            ->assertJsonPath('checks.rate_limiting.route_coverage.missing_business_route_middleware.0.route', 'unsafe.readiness.probe')
            ->assertJsonPath('checks.rate_limiting.route_coverage.missing_business_route_middleware.0.uri', 'unsafe-readiness-probe')
            ->assertJsonPath('checks.rate_limiting.route_coverage.missing_business_route_middleware.0.missing_middleware', [
                'web',
                'auth',
                'account.active',
                'verified',
                'throttle:erp-read',
                'erp.write_limit',
            ])
            ->assertJsonPath('checks.rate_limiting.failure', 'business_route_rate_limit_missing');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['rate_limiting']);
    }

    public function test_readiness_is_degraded_when_unapproved_state_changing_route_disables_csrf(): void
    {
        $this->seed();

        Route::post('/unsafe-csrf-probe', fn () => response()->json(['ok' => true]))
            ->middleware('web')
            ->withoutMiddleware([ValidateCsrfToken::class])
            ->name('unsafe.csrf.probe');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.csrf.status', 'degraded')
            ->assertJsonPath('checks.csrf.unexpected_csrf_exempt_routes.0.route', 'unsafe.csrf.probe')
            ->assertJsonPath('checks.csrf.unexpected_csrf_exempt_routes.0.uri', 'unsafe-csrf-probe')
            ->assertJsonPath('checks.csrf.unexpected_csrf_exempt_routes.0.methods', ['POST'])
            ->assertJsonPath('checks.csrf.failure', 'unexpected_csrf_exemption');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['csrf']);
    }

    public function test_readiness_is_degraded_when_exception_request_id_correlation_is_disabled(): void
    {
        $this->seed();

        Config::set('security.exception_responses.json_request_id_enabled', false);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.exception_handling.status', 'degraded')
            ->assertJsonPath('checks.exception_handling.json_request_id_enabled', false)
            ->assertJsonPath('checks.exception_handling.failure', 'exception_request_id_disabled');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['exception_handling']);
    }

    public function test_production_readiness_is_degraded_when_exception_debug_details_are_enabled(): void
    {
        $this->seed();

        Config::set('app.env', 'production');
        Config::set('app.url', 'https://builder360.example.test');
        Config::set('app.debug', false);
        Config::set('session.encrypt', true);
        Config::set('session.secure', true);
        Config::set('session.http_only', true);
        Config::set('security.hsts.enabled', true);
        Config::set('security.headers.Content-Security-Policy', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; upgrade-insecure-requests");
        Config::set('security.exception_responses.include_debug_details', true);
        Config::set('cache.default', 'database');
        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('filesystems.default', 'local');
        Config::set('filesystems.disks.local.serve', false);
        Config::set('mail.default', 'smtp');
        Config::set('mail.from.address', 'noreply@builder360.example');
        Config::set('logging.default', 'single');
        Config::set('logging.channels.single.level', 'info');
        Config::set('builder360.integrations.payment_gateway.provider', 'razorpay');
        Config::set('builder360.integrations.payment_gateway.webhook_secret', 'configured-secret');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.security.status', 'ok')
            ->assertJsonPath('checks.exception_handling.status', 'degraded')
            ->assertJsonPath('checks.exception_handling.include_debug_details', true)
            ->assertJsonPath('checks.exception_handling.production_debug_details_acceptable', false)
            ->assertJsonPath('checks.exception_handling.failure', 'exception_debug_details_enabled_in_production');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['exception_handling']);
    }

    public function test_sqlite_readiness_reports_missing_file_database_as_degraded(): void
    {
        $this->seed();

        Config::set('database.connections.sqlite.database', database_path('missing-readiness.sqlite'));

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.database.status', 'degraded')
            ->assertJsonPath('checks.database.connection', 'sqlite')
            ->assertJsonPath('checks.database.driver', 'sqlite')
            ->assertJsonPath('checks.database.sqlite.mode', 'file')
            ->assertJsonPath('checks.database.sqlite.file_exists', false)
            ->assertJsonPath('checks.database.sqlite.ready', false)
            ->assertJsonPath('checks.database.sqlite.failure', 'sqlite_database_file_missing');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['database']);
    }

    public function test_database_queue_readiness_requires_failed_jobs_and_batching_tables(): void
    {
        $this->seed();

        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');

        Schema::dropIfExists('failed_jobs');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.queue.status', 'degraded')
            ->assertJsonPath('checks.queue.connection', 'database')
            ->assertJsonPath('checks.queue.jobs_table', 'present')
            ->assertJsonPath('checks.queue.failed_jobs_driver', 'database-uuids')
            ->assertJsonPath('checks.queue.failed_jobs_table', 'missing')
            ->assertJsonPath('checks.queue.batching_table', 'present')
            ->assertJsonPath('checks.queue.failure', 'failed_jobs_table_missing');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['queue']);
    }

    public function test_database_session_readiness_requires_sessions_table(): void
    {
        $this->seed();

        Config::set('session.driver', 'database');

        Schema::dropIfExists('sessions');

        $payload = app(HealthCheckService::class)->readiness();

        $this->assertSame('degraded', $payload['status']);
        $this->assertSame('degraded', $payload['checks']['session']['status']);
        $this->assertSame('database', $payload['checks']['session']['driver']);
        $this->assertSame('missing', $payload['checks']['session']['database_table']);
        $this->assertSame('session_table_missing', $payload['checks']['session']['failure']);
    }

    public function test_production_readiness_degrades_for_unsafe_session_driver(): void
    {
        $this->seed();

        Config::set('app.env', 'production');
        Config::set('app.url', 'https://builder360.example.test');
        Config::set('app.debug', false);
        Config::set('session.driver', 'cookie');
        Config::set('session.encrypt', true);
        Config::set('session.secure', true);
        Config::set('session.http_only', true);
        Config::set('security.hsts.enabled', true);
        Config::set('security.headers.Content-Security-Policy', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; upgrade-insecure-requests");
        Config::set('cache.default', 'database');
        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('filesystems.default', 'local');
        Config::set('filesystems.disks.local.serve', false);
        Config::set('mail.default', 'smtp');
        Config::set('mail.from.address', 'noreply@builder360.example');
        Config::set('logging.default', 'single');
        Config::set('logging.channels.single.level', 'info');
        Config::set('builder360.integrations.payment_gateway.provider', 'razorpay');
        Config::set('builder360.integrations.payment_gateway.webhook_secret', 'configured-secret');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.session.status', 'degraded')
            ->assertJsonPath('checks.session.driver', 'cookie')
            ->assertJsonPath('checks.session.database_table', 'not_applicable')
            ->assertJsonPath('checks.session.production_driver_acceptable', false)
            ->assertJsonPath('checks.session.failure', 'unsafe_production_session_driver')
            ->assertJsonPath('checks.security.status', 'ok');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['session']);
    }

    public function test_database_queue_readiness_degrades_when_pending_backlog_exceeds_threshold(): void
    {
        $this->seed();

        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('builder360.queue.max_pending_jobs', 0);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'Backlogged job'], JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.queue.status', 'degraded')
            ->assertJsonPath('checks.queue.pending_jobs', 1)
            ->assertJsonPath('checks.queue.thresholds.max_pending_jobs', 0)
            ->assertJsonPath('checks.queue.backlog_acceptable', false)
            ->assertJsonPath('checks.queue.failure', 'queue_pending_threshold_exceeded');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['queue']);
    }

    public function test_database_queue_readiness_degrades_when_failed_jobs_exceed_threshold(): void
    {
        $this->seed();

        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('builder360.queue.max_failed_jobs', 0);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) str()->uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'Failed job'], JSON_THROW_ON_ERROR),
            'exception' => 'Synthetic readiness failure fixture',
            'failed_at' => now(),
        ]);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.queue.status', 'degraded')
            ->assertJsonPath('checks.queue.failed_jobs', 1)
            ->assertJsonPath('checks.queue.thresholds.max_failed_jobs', 0)
            ->assertJsonPath('checks.queue.backlog_acceptable', false)
            ->assertJsonPath('checks.queue.failure', 'failed_jobs_threshold_exceeded');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['queue']);
    }

    public function test_production_queue_readiness_requires_failed_job_recording(): void
    {
        $this->seed();

        Config::set('app.env', 'production');
        Config::set('app.url', 'https://builder360.example.test');
        Config::set('app.debug', false);
        Config::set('session.encrypt', true);
        Config::set('session.secure', true);
        Config::set('session.http_only', true);
        Config::set('security.hsts.enabled', true);
        Config::set('security.headers.Content-Security-Policy', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; upgrade-insecure-requests");
        Config::set('cache.default', 'database');
        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'null');
        Config::set('filesystems.default', 'local');
        Config::set('filesystems.disks.local.serve', false);
        Config::set('mail.default', 'smtp');
        Config::set('mail.from.address', 'noreply@builder360.example');
        Config::set('logging.default', 'single');
        Config::set('logging.channels.single.level', 'info');

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.security.status', 'ok')
            ->assertJsonPath('checks.queue.status', 'degraded')
            ->assertJsonPath('checks.queue.connection', 'database')
            ->assertJsonPath('checks.queue.jobs_table', 'present')
            ->assertJsonPath('checks.queue.failed_jobs_driver', 'null')
            ->assertJsonPath('checks.queue.failed_jobs_table', 'not_applicable')
            ->assertJsonPath('checks.queue.failed_jobs_recording_enabled', false)
            ->assertJsonPath('checks.queue.production_connection_acceptable', true)
            ->assertJsonPath('checks.queue.production_acceptable', false)
            ->assertJsonPath('checks.queue.failure', 'failed_jobs_not_recorded');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['queue']);
    }

    public function test_production_readiness_is_degraded_for_prototype_payment_gateway(): void
    {
        $this->seed();

        Config::set('app.env', 'production');
        Config::set('app.url', 'https://builder360.example.test');
        Config::set('app.debug', false);
        Config::set('session.encrypt', true);
        Config::set('session.secure', true);
        Config::set('session.http_only', true);
        Config::set('security.hsts.enabled', true);
        Config::set('security.headers.Content-Security-Policy', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; upgrade-insecure-requests");
        Config::set('cache.default', 'database');
        Config::set('queue.default', 'database');
        Config::set('queue.failed.driver', 'database-uuids');
        Config::set('filesystems.default', 'local');
        Config::set('filesystems.disks.local.serve', false);
        Config::set('mail.default', 'smtp');
        Config::set('mail.from.address', 'noreply@builder360.example');
        Config::set('logging.default', 'single');
        Config::set('logging.channels.single.level', 'info');
        Config::set('builder360.integrations.payment_gateway.provider', 'prototype');
        Config::set('builder360.integrations.payment_gateway.webhook_secret', null);

        $admin = User::where('email', 'nikhil.desai@builder360.test')->firstOrFail();

        $response = $this->actingAs($admin)->getJson(route('operations.readiness'));

        $response
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.security.status', 'ok')
            ->assertJsonPath('checks.integrations.status', 'degraded')
            ->assertJsonPath('checks.integrations.payment_gateway.provider', 'prototype')
            ->assertJsonPath('checks.integrations.payment_gateway.prototype_provider', true)
            ->assertJsonPath('checks.integrations.payment_gateway.webhook_secret_configured', false)
            ->assertJsonPath('checks.integrations.payment_gateway.production_acceptable', false)
            ->assertJsonPath('checks.integrations.payment_gateway.failure', 'prototype_payment_gateway_provider');

        $event = AuditEvent::query()
            ->where('event_type', 'operations.readiness.viewed')
            ->where('user_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('degraded', $event->metadata['status']);
        $this->assertSame('degraded', $event->metadata['check_statuses']['integrations']);
    }
}

