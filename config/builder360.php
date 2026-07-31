<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Operating Company
    |--------------------------------------------------------------------------
    |
    | Builder360 currently operates as a single-company, multi-project system.
    | Resolve the company by its stable business code so environments do not
    | depend on matching numeric ids. Company columns remain for isolation and
    | future expansion.
    |
    */

    'single_company' => [
        'enabled' => filter_var(env('BUILDER360_SINGLE_COMPANY', true), FILTER_VALIDATE_BOOL),
        'code' => env('BUILDER360_COMPANY_CODE', 'B360D'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Seed Credentials
    |--------------------------------------------------------------------------
    |
    | Local and test environments may use the documented Builder360 demo
    | password. Production seeding must provide an explicit password through
    | BUILDER360_DEMO_PASSWORD so shared public fixture credentials are not
    | created accidentally.
    |
    */

    'demo_seed_password' => env('BUILDER360_DEMO_PASSWORD'),

    'documents' => [
        'allowed_storage_disks' => array_values(array_filter(
            array_map('trim', explode(',', (string) env('BUILDER360_DOCUMENT_ALLOWED_DISKS', 'local,s3'))),
            fn (string $disk): bool => $disk !== '',
        )),
        'storage_path_prefix' => env('BUILDER360_DOCUMENT_STORAGE_PATH_PREFIX', 'documents/'),
        'allowed_extensions' => array_values(array_filter(
            array_map('trim', explode(',', (string) env('BUILDER360_DOCUMENT_ALLOWED_EXTENSIONS', 'pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv'))),
            fn (string $extension): bool => $extension !== '',
        )),
        'allowed_mime_types' => array_values(array_filter(
            array_map('trim', explode(',', (string) env('BUILDER360_DOCUMENT_ALLOWED_MIME_TYPES', 'application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain'))),
            fn (string $mimeType): bool => $mimeType !== '',
        )),
        'max_file_size_kb' => (int) env('BUILDER360_DOCUMENT_MAX_FILE_SIZE_KB', 10240),
        'max_file_size_ceiling_kb' => (int) env('BUILDER360_DOCUMENT_MAX_FILE_SIZE_CEILING_KB', 51200),
        'allowed_checksum_algorithms' => array_values(array_filter(
            array_map('trim', explode(',', (string) env('BUILDER360_DOCUMENT_ALLOWED_CHECKSUM_ALGORITHMS', 'sha256'))),
            fn (string $algorithm): bool => $algorithm !== '',
        )),
    ],

    'reports' => [
        'max_date_range_days' => (int) env('BUILDER360_REPORT_MAX_DATE_RANGE_DAYS', 366),
        'max_export_rows' => (int) env('BUILDER360_REPORT_MAX_EXPORT_ROWS', 500),
        'max_export_rows_ceiling' => (int) env('BUILDER360_REPORT_MAX_EXPORT_ROWS_CEILING', 5000),
    ],

    'payroll' => [
        'period_year_min' => (int) env('BUILDER360_PAYROLL_PERIOD_YEAR_MIN', 2020),
        'period_year_max' => (int) env('BUILDER360_PAYROLL_PERIOD_YEAR_MAX', 2100),
        'working_days_min' => (int) env('BUILDER360_PAYROLL_WORKING_DAYS_MIN', 1),
        'working_days_max' => (int) env('BUILDER360_PAYROLL_WORKING_DAYS_MAX', 31),
    ],

    'pagination' => [
        'default_per_page' => (int) env('BUILDER360_PAGINATION_DEFAULT_PER_PAGE', 15),
        'workspace_per_page' => (int) env('BUILDER360_PAGINATION_WORKSPACE_PER_PAGE', 25),
        'large_per_page' => (int) env('BUILDER360_PAGINATION_LARGE_PER_PAGE', 50),
        'default_max_per_page' => (int) env('BUILDER360_PAGINATION_DEFAULT_MAX_PER_PAGE', 50),
        'large_max_per_page' => (int) env('BUILDER360_PAGINATION_LARGE_MAX_PER_PAGE', 100),
        'absolute_max_per_page' => (int) env('BUILDER360_PAGINATION_ABSOLUTE_MAX_PER_PAGE', 100),
        'absolute_ceiling' => (int) env('BUILDER360_PAGINATION_ABSOLUTE_CEILING', 250),
    ],

    'money_input_limits' => [
        'enterprise_amount_max' => env('BUILDER360_MONEY_ENTERPRISE_AMOUNT_MAX', '999999999999.99'),
        'payment_amount_max' => env('BUILDER360_MONEY_PAYMENT_AMOUNT_MAX', '999999999999'),
        'hr_amount_max' => env('BUILDER360_MONEY_HR_AMOUNT_MAX', '999999999.99'),
        'ctc_amount_max' => env('BUILDER360_MONEY_CTC_AMOUNT_MAX', '9999999999'),
        'maintenance_cost_max' => env('BUILDER360_MONEY_MAINTENANCE_COST_MAX', '9999999999.99'),
        'commission_fixed_amount_max' => env('BUILDER360_MONEY_COMMISSION_FIXED_AMOUNT_MAX', '99999999'),
        'commission_target_amount_max' => env('BUILDER360_MONEY_COMMISSION_TARGET_AMOUNT_MAX', '9999999999'),
        'enterprise_amount_ceiling' => env('BUILDER360_MONEY_ENTERPRISE_AMOUNT_CEILING', '999999999999.99'),
        'hr_amount_ceiling' => env('BUILDER360_MONEY_HR_AMOUNT_CEILING', '999999999.99'),
        'ctc_amount_ceiling' => env('BUILDER360_MONEY_CTC_AMOUNT_CEILING', '9999999999'),
    ],

    'operational_input_limits' => [
        'procurement_quantity_max' => env('BUILDER360_OPERATIONAL_PROCUREMENT_QUANTITY_MAX', '9999999'),
        'construction_quantity_max' => env('BUILDER360_OPERATIONAL_CONSTRUCTION_QUANTITY_MAX', '999999999'),
        'rate_max' => env('BUILDER360_OPERATIONAL_RATE_MAX', '999999999'),
        'equipment_hours_max' => env('BUILDER360_OPERATIONAL_EQUIPMENT_HOURS_MAX', '24'),
        'quantity_ceiling' => env('BUILDER360_OPERATIONAL_QUANTITY_CEILING', '999999999'),
        'rate_ceiling' => env('BUILDER360_OPERATIONAL_RATE_CEILING', '999999999'),
        'equipment_hours_ceiling' => env('BUILDER360_OPERATIONAL_EQUIPMENT_HOURS_CEILING', '24'),
    ],

    'queue' => [
        'max_pending_jobs' => (int) env('BUILDER360_QUEUE_MAX_PENDING_JOBS', 1000),
        'max_reserved_jobs' => (int) env('BUILDER360_QUEUE_MAX_RESERVED_JOBS', 250),
        'max_failed_jobs' => (int) env('BUILDER360_QUEUE_MAX_FAILED_JOBS', 0),
    ],

    'authorization' => [
        'required_role_slugs' => array_values(array_filter(
            array_map('trim', explode(',', (string) env(
                'BUILDER360_REQUIRED_ROLE_SLUGS',
                'director,sales_head,construction_head,finance_head,hr_manager,buyer,employee,payroll,recruiter,auditor,compliance,system_admin,channel_partner,executive_partner_broker'
            ))),
            fn (string $slug): bool => $slug !== '',
        )),
        'required_operational_role_slugs' => array_values(array_filter(
            array_map('trim', explode(',', (string) env(
                'BUILDER360_REQUIRED_OPERATIONAL_ROLE_SLUGS',
                'director,system_admin,auditor'
            ))),
            fn (string $slug): bool => $slug !== '',
        )),
        'allowed_scope_levels' => ['global', 'department', 'self', 'readonly', 'partner'],
    ],

    'system_settings' => [
        'required_active_keys' => array_values(array_filter(
            array_map('trim', explode(',', (string) env(
                'BUILDER360_REQUIRED_ACTIVE_SETTING_KEYS',
                'hr.attendance.rules,after_sales.sla_hours,workflow.approval_chains,governance.backup_dr,payroll.tax_rules,hr.leave.rules,payroll.commission_rules,finance.gst_rules'
                .',construction.contractor_billing'
            ))),
            fn (string $key): bool => $key !== '',
        )),
        'allowed_value_types' => ['json', 'object', 'array', 'string', 'number', 'boolean'],
    ],

    'audit' => [
        'required_indexes' => array_values(array_filter(
            array_map('trim', explode(',', (string) env(
                'BUILDER360_REQUIRED_AUDIT_INDEXES',
                'audit_events_user_created_index,audit_events_type_created_index,audit_events_auditable_created_index,audit_events_request_context_index'
            ))),
            fn (string $index): bool => $index !== '',
        )),
        'max_activity_age_hours' => (int) env('BUILDER360_AUDIT_MAX_ACTIVITY_AGE_HOURS', 24),
    ],

    'notifications' => [
        'required_indexes' => array_values(array_filter(
            array_map('trim', explode(',', (string) env(
                'BUILDER360_REQUIRED_NOTIFICATION_INDEXES',
                'notifications_recipient_created_index,notifications_recipient_status_created_index,notifications_recipient_category_status_index,notifications_recipient_severity_status_index'
            ))),
            fn (string $index): bool => $index !== '',
        )),
        'allowed_statuses' => ['unread', 'read', 'archived'],
        'max_unread_per_user' => (int) env('BUILDER360_NOTIFICATIONS_MAX_UNREAD_PER_USER', 250),
        'max_activity_age_hours' => (int) env('BUILDER360_NOTIFICATIONS_MAX_ACTIVITY_AGE_HOURS', 24),
    ],

    'backups' => [
        'sqlite' => [
            'directory' => env('BUILDER360_SQLITE_BACKUP_DIR', 'backups/sqlite'),
            'retention_days' => (int) env('BUILDER360_SQLITE_BACKUP_RETENTION_DAYS', 30),
            'max_age_hours' => (int) env('BUILDER360_SQLITE_BACKUP_MAX_AGE_HOURS', 24),
        ],
        'external_database' => [
            'verified' => (bool) env('BUILDER360_EXTERNAL_DB_BACKUP_VERIFIED', false),
            'provider' => env('BUILDER360_EXTERNAL_DB_BACKUP_PROVIDER'),
            'runbook_reference' => env('BUILDER360_EXTERNAL_DB_BACKUP_RUNBOOK'),
            'rpo_minutes' => env('BUILDER360_EXTERNAL_DB_BACKUP_RPO_MINUTES'),
            'rto_minutes' => env('BUILDER360_EXTERNAL_DB_BACKUP_RTO_MINUTES'),
            'last_restore_tested_at' => env('BUILDER360_EXTERNAL_DB_BACKUP_LAST_RESTORE_TESTED_AT'),
        ],
    ],

    'scheduler' => [
        'enabled' => (bool) env('BUILDER360_SCHEDULER_ENABLED', true),
        'timezone' => env('BUILDER360_SCHEDULER_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
        'output_path' => env('BUILDER360_SCHEDULER_OUTPUT_PATH') ?: storage_path('logs/builder360-scheduler.log'),
        'sqlite_backup_at' => env('BUILDER360_SQLITE_BACKUP_SCHEDULE_AT', '01:00'),
        'sqlite_backup_verify_at' => env('BUILDER360_SQLITE_BACKUP_VERIFY_SCHEDULE_AT', '01:30'),
        'required_commands' => [
            'collaboration:release-scheduled-messages',
        ],
        'sqlite_required_commands' => [
            'builder360:sqlite-backup',
            'builder360:sqlite-backup-verify',
        ],
    ],

    'assets' => [
        'public_root' => public_path(),
        'required_public_files' => array_values(array_filter(
            array_map('trim', explode(',', (string) env('BUILDER360_REQUIRED_PUBLIC_ASSETS', 'css/builder360-classic.css,js/builder360-classic.js'))),
            fn (string $entry): bool => $entry !== '',
        )),
    ],

    'integrations' => [
        'payment_gateway' => [
            'provider' => env('BUILDER360_PAYMENT_GATEWAY_PROVIDER', 'prototype'),
            'webhook_secret' => env('BUILDER360_PAYMENT_GATEWAY_WEBHOOK_SECRET'),
        ],
    ],

    'after_sales' => [
        'sla_hours' => [
            'low' => 72,
            'medium' => 48,
            'high' => 24,
            'critical' => 8,
        ],
    ],
];
