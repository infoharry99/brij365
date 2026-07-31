# Builder360 ERP-CRM Enterprise Blade

This is the traditional Laravel MVC implementation of Builder360 ERP-CRM, aligned to the approved Builder360 UI and SOW.

All browser pages are rendered by controllers and Blade views. The approved interface uses the shared enterprise Blade layout with Vite-built Tailwind CSS and Alpine.js assets. No SPA runtime or client-side business-data layer is used.

## Stack

- Laravel 12
- PHP 8.2+
- Thin controllers, one-use-case Actions, immutable DTOs, intent-named domain services, policies and Form Requests
- Server-rendered Blade layouts and reusable Blade components
- Vite, Tailwind CSS and CSP-safe Alpine.js for local presentation behavior
- MySQL or MariaDB

## Important Architecture Notes

- Entry route: `/`
- Named route: `builder360.dashboard`
- Controller: `App\Http\Controllers\Builder360\DashboardController`
- Bootstrap service: `App\Services\Builder360\Builder360Bootstrap`
- Shared Blade shell: `resources/views/layouts/builder360-classic.blade.php`
- Vite stylesheet entry: `resources/css/enterprise.css`
- Vite interaction entry: `resources/js/app.js`
- Module pages: dedicated Blade views under `resources/views/`

The approved standalone UI/UX remains the visual reference. Laravel provides authenticated browser routes, policies, validation, MySQL records, readiness checks and SOW governance artifacts through a conventional route-to-controller-to-Blade request flow.

## SOW Completion Artifacts

| Artifact | Purpose |
| --- | --- |
| `docs/SOW_COMPLETION_MATRIX.md` | Maps every SOW business area to implemented Laravel route areas and acceptance gates. |
| `docs/ROLE_PERMISSION_MATRIX.md` | Defines role-wise scope, access and restrictions for Director, internal users, buyer and partner roles. |
| `docs/WORKFLOW_AND_SETTINGS_CATALOGUE.md` | Documents approval flows, configurable setting keys and external integration adapter metadata. |
| `docs/UAT_ACCEPTANCE_CHECKLIST.md` | Provides module-wise UAT scenarios and final acceptance checklist. |
| `docs/LOCAL_HOSTING_AND_HANDOVER.md` | Provides MySQL local hosting, readiness and handover instructions. |

## Setup

```bash
composer install
php artisan key:generate
php artisan builder360:database-prepare
php artisan migrate
```

Configure a local MySQL or MariaDB database before running migrations.

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=builder360
DB_USERNAME=root
DB_PASSWORD=
```

## Verification and SOW Acceptance

```bash
php artisan builder360:database-prepare
php artisan builder360:verify --json
php artisan route:list
php artisan test
composer validate --strict
```

SOW acceptance also requires:

- every SOW area in `docs/SOW_COMPLETION_MATRIX.md` to have a working UI location
- every UAT scenario in `docs/UAT_ACCEPTANCE_CHECKLIST.md` to be signed off
- role-wise access from `docs/ROLE_PERMISSION_MATRIX.md` to pass screen, action and export testing
- workflow and configuration controls from `docs/WORKFLOW_AND_SETTINGS_CATALOGUE.md` to be validated
- statutory/legal/payroll/GST/RERA settings to be approved by the client or appointed expert before production reliance

## Operational Health and Readiness

The Laravel app includes operational endpoints for MySQL local hosting and deployment checks. SQLite compatibility remains available for isolated automated tests and legacy backup verification only:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/health` | `health` | Public liveness probe with safe service status only | Public |
| GET | `/operations/readiness` | `operations.readiness` | Authenticated readiness checks for database, migrations, session, auth, authorization, configuration, audit, notifications, report limits, payroll controls, pagination, money input limits, operational input limits, cache, queue, storage, backup, scheduler, assets, integrations, optimization, mail, logging, rate limiting, CSRF, exception handling and security | Director, System Administrator or Auditor |

Implemented controls:

- `/health` does not expose database paths, migration details, cache configuration details or storage paths
- `/operations/readiness` is protected by existing wildcard, `settings.view` or `audit.view` permissions
- Database readiness validates the configured connection and performs additional foreign-key checks when SQLite is used by automated tests
- Migration readiness compares migration files with the migrated records
- Session readiness validates the configured driver, database sessions table or file path, lifetime and SameSite policy
- Auth readiness validates the web guard/provider model, required Builder360 user/account-status columns, email verification support, remember-token support, password-reset token table/columns, password broker expiry/throttle/confirmation timeout settings and the centralized strong-password policy
- Authorization readiness validates the roles table/columns, configured required role slugs, active wildcard role coverage, active operational users for director/system-admin/auditor access, allowed scope levels and valid permission arrays
- Configuration readiness validates the versioned `system_settings` table, configured required active business setting keys, approved/effective setting records, supported value types, non-empty setting values and duplicate active scope/key conflicts
- Audit readiness validates the `audit_events` table/columns, required audit query indexes, request-context audit columns, central sensitive-metadata redaction self-test and production audit-activity freshness
- Notification readiness validates the `user_notifications` table/columns, required inbox query indexes, allowed notification statuses, recipient-user scope integrity, unread backlog thresholds and production notification-activity freshness
- Report-limit readiness validates governance report date-range caps and export row caps so MIS exports stay bounded and configurable
- Payroll-control readiness validates payroll period-year and working-day bounds so payroll generation inputs remain configurable and calendar-safe
- Pagination readiness validates default, large and absolute per-page caps so list endpoints stay bounded and configurable
- Money-input readiness validates configured finance, HR, payroll and recruitment amount ceilings so transactional numeric limits are bounded and configurable
- Operational-input readiness validates configured procurement and construction quantity, rate and equipment-hour ceilings
- Database cache and database queue readiness work with the configured database connection
- Queue readiness reports pending, reserved and failed job counts against configurable thresholds
- Storage readiness reports writable framework and log directories without writing probe files
- Backup readiness reports the configured backup controls, retention and verification status
- Scheduler readiness confirms required operational commands are registered, including SQLite backup and backup verification
- Asset readiness validates the compatibility assets and generated Vite manifest required by the enterprise Blade shell
- Integration readiness allows the prototype payment gateway locally but degrades production readiness unless a non-prototype payment provider and webhook secret are configured
- Optimization readiness reports Laravel config-cache and route-cache status
- Mail readiness reports the configured mailer and from-address safety without sending probe messages
- Logging readiness reports the configured channel and level without writing extra probe logs
- Rate-limiting readiness validates configured ERP read/write limits, confirms the named `erp-read` limiter is registered, checks authenticated ERP routes include `throttle:erp-read` and `erp.write_limit`, and verifies sensitive auth/integration routes keep their throttles
- CSRF readiness verifies every state-changing web route remains CSRF-protected unless it is an explicitly approved external integration endpoint; currently only the signed payment-gateway webhook may bypass CSRF
- Exception-handling readiness validates the central JSON exception renderer, request-ID correlation, safe generic server-error messaging and production blocking of debug exception details
- Security readiness validates APP_KEY, SameSite, safe browser security-header configuration, restrictive Permissions-Policy and CSP baseline directives in all environments
- Authenticated ERP responses are marked `private, no-store` to reduce browser/proxy caching of sensitive CRM, HR, payroll and finance data
- In `APP_ENV=production`, readiness also requires debug mode off, encrypted sessions, secure session cookies, HSTS enabled, authenticated response no-store enabled and a tightened CSP without `unsafe-inline`
- In `APP_ENV=production`, readiness also degrades unsafe infrastructure choices such as `array`/`null` cache stores, `array`/`cookie` session drivers, `sync`/`null`/`deferred`/`background` queue connections, disabled failed-job recording, prototype payment gateway configuration, public default filesystem disks, raw private local storage serving, missing Laravel config/route caches, `log`/`array` mailers, placeholder from-addresses, `null`/`emergency` log channels and `debug` log level
- Every HTTP response includes an `X-Request-Id`; incoming `X-Request-Id`, `X-Correlation-Id` or `Traceparent` values are preserved, otherwise a UUID is generated and carried into audit events
- JSON error responses include the same `request_id` in the response body for client support and log correlation without exposing stack traces or request payloads

## SQLite Backup Command

For local SQLite deployments, Builder360 includes a native private backup command without adding third-party packages:

```bash
php artisan builder360:sqlite-backup
php artisan builder360:sqlite-backup --json
php artisan builder360:sqlite-backup --output-dir=backups/sqlite --retention-days=30
php artisan builder360:sqlite-backup-verify --json
php artisan builder360:sqlite-backup-verify backups/sqlite/example.sqlite.json --json
```

The command:

- supports only file-backed SQLite databases, not `:memory:`
- stores backup files under `storage/app/private`
- rejects absolute paths, traversal and unsafe output directories
- creates a `.sqlite` backup plus a JSON manifest
- records backup file size, SHA-256 checksum, creation time, method and Laravel/PHP versions
- verifies the latest or selected manifest without restoring it by checking file existence, size, SHA-256 and SQLite `pragma integrity_check`
- prunes expired `builder360-sqlite-*.sqlite*` backup files only inside the selected private backup directory

Readiness uses the latest manifest to report backup freshness, checksum validity and SQLite integrity-check validity. In production with SQLite, stale, missing, checksum-invalid or integrity-invalid backups degrade readiness.

The Laravel scheduler registers daily SQLite backup and verification jobs when `BUILDER360_SCHEDULER_ENABLED=true`:

```bash
php artisan schedule:list
php artisan schedule:run
```

Default schedule:

| Task | Command | Default Time |
| --- | --- | --- |
| SQLite backup | `builder360:sqlite-backup --json` | `01:00` |
| SQLite backup verification | `builder360:sqlite-backup-verify --json` | `01:30` |

Run `php artisan schedule:run` every minute through the deployment process manager or system cron. Scheduler output is appended to `storage/logs/builder360-scheduler.log` unless `BUILDER360_SCHEDULER_OUTPUT_PATH` is changed.

## Browser Security Headers

All HTTP responses pass through a Laravel security-header middleware:

| Header | Purpose |
| --- | --- |
| `X-Frame-Options: SAMEORIGIN` | Reduces clickjacking risk while allowing same-origin app embedding if needed |
| `X-Content-Type-Options: nosniff` | Prevents browser MIME sniffing |
| `Referrer-Policy: strict-origin-when-cross-origin` | Limits cross-origin referrer leakage |
| `Cross-Origin-Opener-Policy: same-origin` | Reduces cross-origin window interaction risk |
| `Permissions-Policy` | Disables unnecessary browser capabilities and limits geolocation to same-origin use |
| `Content-Security-Policy` | Sets a compatible baseline policy for the server-rendered Classic MVC shell |
| `Strict-Transport-Security` | Added only on secure HTTPS requests |
| `Cache-Control` / `Pragma` / `Expires` | Marks authenticated ERP responses as private no-store |

The default CSP blocks executable inline scripts. Enterprise Blade pages load versioned Vite assets from the shared layout. Production readiness reports `degraded` while a configured CSP contains `unsafe-inline`; production deployments should use nonce/hash-based exceptions only where an approved integration requires them.

## ERP Route Rate Limits

Authenticated ERP routes use central Laravel rate limiting in addition to CSRF, authorization policies and form-request validation:

| Limit | Default | Applies To |
| --- | ---: | --- |
| `SECURITY_RATE_LIMIT_ERP_READ_PER_MINUTE` | `1200` | Authenticated, verified ERP read requests |
| `SECURITY_RATE_LIMIT_ERP_WRITE_PER_MINUTE` | `600` | Authenticated, verified POST/PATCH/PUT/DELETE requests |
| `SECURITY_RATE_LIMIT_ERP_READ_MIN_PER_MINUTE` | `1` | Minimum acceptable ERP read limit for readiness validation |
| `SECURITY_RATE_LIMIT_ERP_READ_MAX_PER_MINUTE` | `5000` | Maximum acceptable ERP read limit for readiness validation |
| `SECURITY_RATE_LIMIT_ERP_WRITE_MIN_PER_MINUTE` | `1` | Minimum acceptable ERP write limit for readiness validation |
| `SECURITY_RATE_LIMIT_ERP_WRITE_MAX_PER_MINUTE` | `2500` | Maximum acceptable ERP write limit for readiness validation |
| `SECURITY_EXCEPTION_JSON_REQUEST_ID_ENABLED` | `true` | Adds `request_id` to JSON exception responses for support correlation and readiness validation |
| `SECURITY_EXCEPTION_INCLUDE_DEBUG_DETAILS` | `false` | Allows local-only JSON exception debug details when explicitly enabled; production readiness degrades if enabled |
| `SECURITY_EXCEPTION_GENERIC_SERVER_MESSAGE` | safe generic text | Message returned for unhandled 5xx JSON errors when debug details are disabled |
| `SECURITY_PASSWORD_MIN_LENGTH` | `10` | Minimum password length enforced by reset and admin user-creation flows |
| `SECURITY_PASSWORD_MAX_LENGTH` | `255` | Maximum password length accepted by the central password policy |
| `SECURITY_PASSWORD_REQUIRE_MIXED_CASE` | `true` | Requires upper and lower case characters in passwords |
| `SECURITY_PASSWORD_REQUIRE_NUMBERS` | `true` | Requires at least one numeric character in passwords |
| `SECURITY_PASSWORD_REQUIRE_SYMBOLS` | `true` | Requires at least one symbol in passwords |
| `SECURITY_PASSWORD_UNCOMPROMISED` | `false` | Optional Laravel compromised-password check; enable only when outbound validation service access is approved |
| `SECURITY_PASSWORD_MAX_COMPROMISED_THRESHOLD` | `0` | Threshold used when uncompromised password checking is enabled |

The write limiter is enforced by middleware and applies across sales, finance, HR, payroll, procurement, legal, possession, after-sales, settings, admin, buyer and partner workflows. Tune these values per deployment based on expected concurrency, reverse-proxy protection and operational monitoring.

## Managed Document Storage Guardrails

Managed document records validate storage metadata before creating document register entries:

| Setting | Default | Purpose |
| --- | --- | --- |
| `BUILDER360_DOCUMENT_ALLOWED_DISKS` | `local,s3` | Limits which Laravel disks may be referenced by managed documents |
| `BUILDER360_DOCUMENT_STORAGE_PATH_PREFIX` | `documents/` | Requires document paths to stay inside the managed document namespace |
| `BUILDER360_REPORT_MAX_DATE_RANGE_DAYS` | `366` | Caps governance report date ranges to prevent unbounded report/export queries |
| `BUILDER360_REPORT_MAX_EXPORT_ROWS` | `500` | Caps governance report register JSON/CSV/Excel/PDF rows per request |
| `BUILDER360_REPORT_MAX_EXPORT_ROWS_CEILING` | `5000` | Safety ceiling used by readiness so overly large export row limits are detected |
| `BUILDER360_PAYROLL_PERIOD_YEAR_MIN` | `2020` | Minimum payroll period year accepted by payroll generation |
| `BUILDER360_PAYROLL_PERIOD_YEAR_MAX` | `2100` | Maximum payroll period year accepted by payroll generation |
| `BUILDER360_PAYROLL_WORKING_DAYS_MIN` | `1` | Minimum working days accepted for a payroll run |
| `BUILDER360_PAYROLL_WORKING_DAYS_MAX` | `31` | Maximum calendar-safe working days accepted for a payroll run |
| `BUILDER360_PAGINATION_DEFAULT_PER_PAGE` | `15` | Default page size for standard list endpoints when the request omits `per_page` |
| `BUILDER360_PAGINATION_WORKSPACE_PER_PAGE` | `25` | Default page size for operational workspace lists when the request omits `per_page` |
| `BUILDER360_PAGINATION_LARGE_PER_PAGE` | `50` | Default page size for larger reference lists when the request omits `per_page` |
| `BUILDER360_PAGINATION_DEFAULT_MAX_PER_PAGE` | `50` | Default per-page cap for standard list endpoints |
| `BUILDER360_PAGINATION_LARGE_MAX_PER_PAGE` | `100` | Per-page cap for larger administrative list endpoints |
| `BUILDER360_PAGINATION_ABSOLUTE_MAX_PER_PAGE` | `100` | Highest per-page cap allowed by configured application pagination policy |
| `BUILDER360_PAGINATION_ABSOLUTE_CEILING` | `250` | Operational safety ceiling used by readiness to reject excessive pagination limits |
| `BUILDER360_MONEY_ENTERPRISE_AMOUNT_MAX` | `999999999999.99` | Maximum enterprise-scale amount accepted by finance and CRM monetary fields |
| `BUILDER360_MONEY_PAYMENT_AMOUNT_MAX` | `999999999999` | Maximum payment-request amount accepted by finance workflows |
| `BUILDER360_MONEY_HR_AMOUNT_MAX` | `999999999.99` | Maximum HR operational amount for claims, loans, assets and settlements |
| `BUILDER360_MONEY_CTC_AMOUNT_MAX` | `9999999999` | Maximum CTC amount accepted by HR and recruitment fields |
| `BUILDER360_MONEY_MAINTENANCE_COST_MAX` | `9999999999.99` | Maximum maintenance work-order cost amount |
| `BUILDER360_MONEY_COMMISSION_FIXED_AMOUNT_MAX` | `99999999` | Maximum fixed commission rule amount |
| `BUILDER360_MONEY_COMMISSION_TARGET_AMOUNT_MAX` | `9999999999` | Maximum target-based commission amount |
| `BUILDER360_MONEY_ENTERPRISE_AMOUNT_CEILING` | `999999999999.99` | Readiness ceiling for enterprise-scale configured amount limits |
| `BUILDER360_MONEY_HR_AMOUNT_CEILING` | `999999999.99` | Readiness ceiling for HR-scale configured amount limits |
| `BUILDER360_MONEY_CTC_AMOUNT_CEILING` | `9999999999` | Readiness ceiling for configured CTC limits |
| `BUILDER360_OPERATIONAL_PROCUREMENT_QUANTITY_MAX` | `9999999` | Maximum procurement item quantity accepted in requisitions, purchase orders and goods receipts |
| `BUILDER360_OPERATIONAL_CONSTRUCTION_QUANTITY_MAX` | `999999999` | Maximum construction/BOQ/measurement quantity accepted |
| `BUILDER360_OPERATIONAL_RATE_MAX` | `999999999` | Maximum procurement and construction rate accepted |
| `BUILDER360_OPERATIONAL_EQUIPMENT_HOURS_MAX` | `24` | Maximum equipment hours accepted per daily report line |
| `BUILDER360_OPERATIONAL_QUANTITY_CEILING` | `999999999` | Readiness ceiling for configured operational quantity limits |
| `BUILDER360_OPERATIONAL_RATE_CEILING` | `999999999` | Readiness ceiling for configured operational rate limits |
| `BUILDER360_OPERATIONAL_EQUIPMENT_HOURS_CEILING` | `24` | Readiness ceiling for configured equipment-hour limits |
| `BUILDER360_REQUIRED_PUBLIC_ASSETS` | `css/builder360-classic.css,js/builder360-classic.js` | Direct public assets required before the app is considered ready |`r`n| `BUILDER360_PAYMENT_GATEWAY_PROVIDER` | `prototype` | Local/test payment link provider; production readiness degrades for prototype/demo/mock/sandbox/simulated values |
| `BUILDER360_PAYMENT_GATEWAY_WEBHOOK_SECRET` | blank | Required in production readiness when a real payment provider is configured, so inbound payment callbacks can be verified |
| `BUILDER360_QUEUE_MAX_PENDING_JOBS` | `1000` | Maximum unreserved database queue backlog before readiness degrades |
| `BUILDER360_QUEUE_MAX_RESERVED_JOBS` | `250` | Maximum reserved/in-progress database jobs before readiness degrades |
| `BUILDER360_QUEUE_MAX_FAILED_JOBS` | `0` | Maximum failed jobs allowed before readiness degrades |
| `BUILDER360_REQUIRED_ROLE_SLUGS` | seeded Builder360 role list | Comma-separated active role slugs required by authorization readiness |
| `BUILDER360_REQUIRED_OPERATIONAL_ROLE_SLUGS` | `director,system_admin,auditor` | Comma-separated role slugs that must have at least one active user for operational access and recovery |
| `BUILDER360_REQUIRED_ACTIVE_SETTING_KEYS` | seeded Builder360 settings list | Comma-separated active configuration keys required by configuration readiness |
| `BUILDER360_REQUIRED_AUDIT_INDEXES` | seeded audit index list | Comma-separated audit indexes required by audit readiness |
| `BUILDER360_AUDIT_MAX_ACTIVITY_AGE_HOURS` | `24` | Maximum age of latest audit event before production readiness degrades |
| `BUILDER360_REQUIRED_NOTIFICATION_INDEXES` | seeded notification index list | Comma-separated notification indexes required by notification readiness |
| `BUILDER360_NOTIFICATIONS_MAX_UNREAD_PER_USER` | `250` | Maximum unread in-app notification backlog per user before readiness degrades |
| `BUILDER360_NOTIFICATIONS_MAX_ACTIVITY_AGE_HOURS` | `24` | Maximum age of latest notification before production readiness degrades |
| `SECURITY_HEADER_FRAME_OPTIONS` | `SAMEORIGIN` | X-Frame-Options policy; readiness accepts `SAMEORIGIN` or `DENY` |
| `SECURITY_HEADER_REFERRER_POLICY` | `strict-origin-when-cross-origin` | Referrer-Policy value; readiness requires a non-leaky policy |
| `SECURITY_HEADER_COOP` | `same-origin` | Cross-Origin-Opener-Policy value required by security-header readiness |
| `SECURITY_HEADER_PERMISSIONS_POLICY` | restrictive browser-feature policy | Permissions-Policy value; readiness requires camera, microphone, payment and USB to be disabled |
| `SECURITY_HEADER_CSP` | Builder360 baseline CSP | Content-Security-Policy value; readiness requires core directives and blocks inline scripts |
| `SECURITY_HEADER_HSTS_ENABLED` | `true` | Enables Strict-Transport-Security on secure requests; production readiness requires this |
| `SECURITY_HEADER_HSTS_MAX_AGE` | `31536000` | HSTS max-age in seconds; production readiness requires at least one year |
| `SECURITY_HEADER_HSTS_INCLUDE_SUBDOMAINS` | `true` | Adds `includeSubDomains` to HSTS and is required when preload is enabled |
| `SECURITY_HEADER_HSTS_PRELOAD` | `false` | Adds HSTS preload directive when explicitly enabled |
| `BUILDER360_DOCUMENT_ALLOWED_EXTENSIONS` | `pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv` | Comma-separated managed-document extensions accepted by the centralized document file policy |
| `BUILDER360_DOCUMENT_ALLOWED_MIME_TYPES` | safe office/image/PDF/CSV list | Comma-separated MIME types accepted by the centralized document file policy; wildcards and `application/octet-stream` degrade readiness |
| `BUILDER360_DOCUMENT_MAX_FILE_SIZE_KB` | `10240` | Maximum managed-document file size in KB |
| `BUILDER360_DOCUMENT_MAX_FILE_SIZE_CEILING_KB` | `51200` | Safety ceiling used by readiness so oversized upload limits are detected before production |
| `BUILDER360_DOCUMENT_ALLOWED_CHECKSUM_ALGORITHMS` | `sha256` | Checksum algorithms supported by managed-document metadata; readiness currently requires SHA-256 |
| `SECURITY_AUTHENTICATED_NO_STORE_ENABLED` | `true` | Adds no-store cache headers to authenticated ERP responses and is required by production readiness |
| `SECURITY_AUTHENTICATED_CACHE_CONTROL` | `private, no-store, max-age=0, must-revalidate` | Cache-Control value used for authenticated ERP responses |
| `BUILDER360_SQLITE_BACKUP_DIR` | `backups/sqlite` | Private storage directory used by `builder360:sqlite-backup` and backup readiness |
| `BUILDER360_SQLITE_BACKUP_RETENTION_DAYS` | `30` | Retention window for SQLite backup pruning |
| `BUILDER360_SQLITE_BACKUP_MAX_AGE_HOURS` | `24` | Maximum acceptable age for the latest SQLite backup in production readiness |
| `BUILDER360_SCHEDULER_ENABLED` | `true` | Registers required Laravel scheduler jobs; production readiness degrades if required jobs are missing |
| `BUILDER360_SCHEDULER_TIMEZONE` | `APP_TIMEZONE` | Timezone used by Builder360 scheduled operational jobs |
| `BUILDER360_SCHEDULER_OUTPUT_PATH` | `storage/logs/builder360-scheduler.log` | Scheduler output log path |
| `BUILDER360_SQLITE_BACKUP_SCHEDULE_AT` | `01:00` | Daily scheduler time for SQLite backup |
| `BUILDER360_SQLITE_BACKUP_VERIFY_SCHEDULE_AT` | `01:30` | Daily scheduler time for SQLite backup verification |
| `FILESYSTEM_LOCAL_SERVE` | `false` | Keeps Laravel's raw private local storage helper routes disabled unless a local-only development workflow explicitly opts in |

The validation rejects public/unknown disks, absolute paths, URLs, Windows drive paths, path traversal, backslash separators, blank path segments and filenames containing path data. Managed-document metadata is also checked by a centralized file policy for configured extension, MIME type, size limit, matching storage/original extension and SHA-256 checksum format. Operational readiness degrades if the configured file policy allows dangerous extensions, wildcard MIME types, unsupported checksum algorithms or an upload limit above the configured safety ceiling.

Document file access is exposed through the authenticated `documents.download` route only. The endpoint applies the existing `ManagedDocumentPolicy::view` checks, verifies the file exists on the configured Laravel disk, streams with private/no-store response headers and records a `documents.document.downloaded` audit event. Raw public storage links are not used for managed documents, and raw private local storage serving is disabled by default through `FILESYSTEM_LOCAL_SERVE=false`. API responses expose `download_url`, while raw storage disk/path/checksum metadata is limited to document managers, document approvers and wildcard administrators. Buyer users cannot access the internal `/documents` register; they can only list documents through the buyer portal and download customer/booking documents that belong to their own customer record.

## Current Functional Scope

The converted UI includes the existing Builder360 prototype modules:

- Executive dashboard
- Sales and CRM
- Lead qualification
- Site visits
- Sales and booking
- Collections
- Project and inventory screens
- Construction operations screens
- HRMS and employee self-service
- Partner portal roles
- Finance, legal, documents, possession, after-sales and maintenance prototype screens
- Admin, workflow, audit, authentication and settings prototype screens

## Backend Foundation Added

The Laravel application now includes database-backed foundation tables and Eloquent models for:

- Roles and permission gates
- ERP modules
- Companies
- Branches
- Projects
- Employees
- Partners
- Customers
- Leads
- Project units
- Bookings and booking payment schedules
- Collection receipts, buyer payment requests, GST entries and GST return periods
- Document categories and managed documents
- Leave types, leave balances, leave requests, leave processing runs and leave encashments
- Attendance shifts, shift assignments, attendance records and regularization requests
- Employee confirmation cases for probation review, manager recommendation and HR decision
- Employee separation settlements with Full & Final calculations, clearance blockers and approval workflow
- Employee exit interviews with confidential responses, HR review and aggregate reporting
- Performance cycles and employee performance reviews with KPI/KRA, ratings and PIP metadata
- Payroll components, salary structures, assignments, payroll runs, payroll run items, commission rules/runs/items and bank-transfer batches
- Employee tax documents for Form 16 generation, issue and acknowledgement
- Job openings, candidates, interviews and job offers
- Vendors, purchase requisitions, purchase orders and goods receipts
- Construction milestones, BOQ items, contractor measurements and daily progress reports
- RERA registrations, project approvals and compliance obligations
- Possession handovers and handover snags
- After-sales service tickets and maintenance work orders
- User notifications and workflow alerts
- Versioned system settings
- Audit events

The authenticated root dashboard is server-rendered Blade. It uses the shared application service for active roles, modules, companies, projects, partner pipeline summaries, partner portal dashboard data, buyer portal dashboard data, recipient-specific notification summaries and executive dashboard metrics.

## Server-Rendered Dashboard Bootstrap Slice

The main Builder360 dashboard receives a company-scoped dashboard payload from Laravel and renders it through Blade:

| Payload Area | Purpose |
| --- | --- |
| `dashboard.kpis` | Project count, active sites, unit inventory, lead count, qualified leads, site visits, bookings, sales value, outstanding value, expenses, ROI and pending approvals |
| `dashboard.projects` | Project scorecard rows calculated from units, bookings, collections, milestones, purchase orders and contractor measurements |
| `dashboard.funnel` | Lead funnel counts calculated from leads, qualifications, site visits and bookings |
| `dashboard.approvals` | Pending approval queue from requisitions, purchase orders, vouchers and leave requests |
| `dashboard.alerts` | Server-sourced dashboard status and risk alerts |
| `partner_portal` | Partner-role-only summary for leads, site visits, bookings, collection follow-up, commissions and documents; non-partner users receive `null` |
| `buyer_portal` | Buyer-role-only summary for customer, booking, payment schedule, receipts, outstanding, documents and service tickets; non-buyer users receive `null` |
| `notifications` | Authenticated current-user notification counts, category/severity summaries and recent non-archived alerts for the topbar bell and Notifications page |

Implemented controls:

- Dashboard payload source is clearly marked as `laravel-sqlite`
- Director/global users receive group-wide metrics
- Company users receive company-scoped metrics only
- Dashboard renders without `id="root"` or browser-side fallback records
- Dashboard displays server-backed access scope, authorized modules, project, funnel, approval and notification metrics
- Partner portal UI prefers the server-scoped `partner_portal` payload and falls back to converted demo rows only when no server payload is available
- Buyer portal UI prefers the server-scoped `buyer_portal` payload for unit, consideration, paid/outstanding amounts, next due and payment schedule
- Notifications UI prefers the server-scoped `notifications` payload for unread counts, recent alerts and category filters
- Feature tests verify payload presence, global counts, company scoping, partner portal scoping, buyer portal scoping and notification recipient scoping

## CRM Lead Backend Slice

Authenticated Laravel endpoints now exist for the first production-backed CRM domain slice:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/crm/leads` | `crm.leads.index` | Paginated company-scoped CRM leads with project/source/campaign filters | `crm.view` or `crm.manage` |
| POST | `/crm/leads` | `crm.leads.store` | Create a CRM lead with customer, project, partner and optional campaign attribution | `crm.manage` |
| PATCH | `/crm/leads/{lead}/stage` | `crm.leads.stage.update` | Update lead stage/status/follow-up | `crm.manage` |
| GET | `/partner/summary` | `partner.summary` | Partner-scoped dashboard summary for leads, site visits, bookings, collection follow-up, commissions and documents | Partner-role `partner.portal` |
| GET | `/partner/leads` | `partner.leads.index` | Partner-scoped lead list | `partner.portal` |

Implemented controls:

- Form request validation
- Project/company consistency validation
- Laravel policies and gates
- Partner portal routes require both partner role scope and `partner.portal`; wildcard internal roles are not treated as partner users
- Partner lead, dashboard, collection-follow-up, commission and document scoping by authenticated active partner email
- Internal CRM lead register rejects undocumented query filters while allowing only stage, status, project, source, campaign and bounded pagination filters
- Partner portal endpoints reject undocumented query filters while explicitly allowing bounded pagination through `page` and `per_page`
- Audit events for lead creation and stage updates
- Lead creation and stage updates create persistent lead activity timeline records
- JSON resources for stable frontend consumption

## CRM Prospect Inquiry Backend Slice

Public customer-channel inquiry intake and authenticated CRM inquiry management are now production-backed:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| POST | `/prospect-inquiries` | `prospect-inquiries.store` | Capture public website/mobile/channel prospect inquiry for an active project | Public, rate-limited |
| GET | `/crm/prospect-inquiries` | `crm.prospect-inquiries.index` | Company-scoped prospect inquiry register with status/project/assignee/source/channel/date/search filters | `crm.view` or `crm.manage` |
| PATCH | `/crm/prospect-inquiries/{prospectInquiry}/assign` | `crm.prospect-inquiries.assign` | Assign an open inquiry to an active CRM user in the same company | `crm.manage` |
| PATCH | `/crm/prospect-inquiries/{prospectInquiry}/convert` | `crm.prospect-inquiries.convert` | Convert an assigned/open inquiry into a normal CRM lead and customer record | `crm.manage` |
| PATCH | `/crm/prospect-inquiries/{prospectInquiry}/close` | `crm.prospect-inquiries.close` | Close an inquiry as duplicate or unqualified with reason | `crm.manage` |

Implemented controls:

- Public capture derives `company_id` from the selected active project; client-supplied company/status fields are ignored
- Email/phone duplicate detection for open inquiries on the same project, with duplicate linkage and warning notification
- Internal registers enforce company scope and reject undocumented query filters
- Assignment validates active same-company CRM users only
- Conversion reuses the CRM lead service so customers, leads, campaign activity, audit events and lead timeline behavior stay consistent
- Closed, converted and unresolved duplicate inquiries cannot be converted again
- Audit events for capture, assignment, conversion and closure
- Notification center alerts for new/duplicate inquiries and assigned inquiry ownership
- Feature tests cover public capture, duplicate detection, company-scoped listing, assignment, conversion, duplicate conversion blocking and partner denial

## CRM Marketing Campaign and Lead Activity Backend Slice

Authenticated Laravel endpoints now exist for marketing campaign tracking, campaign response attribution and lead activity history:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/crm/campaigns` | `crm.campaigns.index` | Company-scoped campaign register with channel/status/project/date/search filters and response metrics | `crm.view` or `crm.manage` |
| POST | `/crm/campaigns` | `crm.campaigns.store` | Create draft or active marketing campaign records | `crm.manage` |
| PATCH | `/crm/campaigns/{marketingCampaign}/status` | `crm.campaigns.status.update` | Activate, pause, complete or archive a campaign with workflow history | `crm.manage` |
| GET | `/crm/lead-activities` | `crm.lead-activities.index` | Company-scoped lead activity timeline with lead/project/campaign/type/date/search filters | `crm.view` or `crm.manage` |
| POST | `/crm/lead-activities` | `crm.lead-activities.store` | Record manual call, email, note, follow-up or campaign-response activity | `crm.manage` |

Implemented controls:

- Normalized `marketing_campaigns` and `lead_activities` records linked to companies, projects, leads, actors and campaigns
- Lead records support optional `marketing_campaign_id` attribution
- Campaign metrics calculate from real lead and booking records: total leads, open/won/lost leads, bookings, expected value, conversion rate and target attainment
- Campaign and activity registers reject undocumented query filters and enforce company/project scope before returning data
- Lead creation rejects campaign attribution from another company or unrelated campaign project
- Manual lead activities update next follow-up where supplied
- Lead creation, campaign response capture, stage changes, qualification and site-visit actions write timeline activity records
- Partner portal users cannot access internal campaign or lead-activity endpoints
- Audit events for campaign creation, campaign status update and manual lead activity creation
- Feature tests covering campaign metrics, campaign lifecycle, lead attribution, activity history, scope validation and partner restrictions

## CRM Qualification and Site Visit Backend Slice

Authenticated Laravel endpoints now exist for qualification scoring and site-visit execution:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/crm/lead-qualifications` | `crm.lead-qualifications.index` | Company-scoped qualification register with lead/status/score/date filters | `crm.view` or `crm.manage` |
| POST | `/crm/lead-qualifications` | `crm.lead-qualifications.store` | Record BANT-style lead qualification and update lead stage/budget | `crm.manage` |
| GET | `/crm/site-visits` | `crm.site-visits.index` | Company-scoped site-visit register with lead/project/assignee/status/date filters | `crm.view` or `crm.manage` |
| POST | `/crm/site-visits` | `crm.site-visits.store` | Schedule site, office or virtual visit for an active lead | `crm.manage` |
| PATCH | `/crm/site-visits/{siteVisit}/complete` | `crm.site-visits.complete` | Complete visit with outcome, notes and next follow-up | `crm.manage` |
| PATCH | `/crm/site-visits/{siteVisit}/cancel` | `crm.site-visits.cancel` | Cancel scheduled visit with reason | `crm.manage` |

Implemented controls:

- Normalized qualification and site-visit records linked to leads, projects, customers and users
- Qualification scoring across budget, authority, need and timeline
- Qualification statuses: qualified, nurture and disqualified
- Lead stage/status/budget updates from qualification decision
- Qualification and site-visit actions append persistent lead activity timeline records
- Site visit lifecycle: scheduled, completed, no-show and cancelled
- Site, office and virtual visit modes
- Same-company validation for leads and assigned users
- Assigned-user schedule conflict validation for overlapping visits
- Qualification and site-visit registers reject undocumented query filters while allowing only their documented lead, project, assignee, status, score, date, visit-mode and pagination filters
- Assigned-user notifications through notification center
- Audit events for qualification, scheduling, completion and cancellation
- Feature tests covering listing, qualification updates, scheduling, notifications, completion, conflict validation, cancellation and partner restrictions

## Unit Inventory and Sales Booking Backend Slice

Authenticated Laravel endpoints now exist for inventory availability and sales booking workflows:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/inventory/units` | `inventory.units.index` | Paginated company-scoped unit inventory with status filters | `inventory.view`, `booking.view` or `booking.manage` |
| GET | `/inventory/unit-price-versions` | `inventory.unit-price-versions.index` | Company-scoped effective-dated unit pricing register | `inventory.view`, `booking.view` or `booking.manage` |
| POST | `/inventory/unit-price-versions` | `inventory.unit-price-versions.store` | Draft a new unit price version with rate, premium, charge and tax breakup | `booking.manage` |
| PATCH | `/inventory/unit-price-versions/{unitPriceVersion}/approve` | `inventory.unit-price-versions.approve` | Approve a draft price version and retire overlapping active versions | `booking.manage` or `finance.approve` |
| POST | `/sales/booking-quotes` | `sales.booking-quotes.store` | Preview unit pricing, discount impact, tax and payable amount from the active effective price | `booking.view` or `booking.manage` |
| GET | `/sales/bookings` | `sales.bookings.index` | Paginated company-scoped booking list | `booking.view` or `booking.manage` |
| POST | `/sales/bookings` | `sales.bookings.store` | Create a confirmed booking for an available unit | `booking.manage` |
| GET | `/partner/bookings` | `partner.bookings.index` | Partner-scoped booking list | `partner.portal` |

Implemented controls:

- SQLite-compatible normalized migrations for units, bookings and payment schedules
- Effective-dated unit price versions with approval history, charge breakup, tax calculation and audit trail
- Booking quote previews from approved price versions, with legacy unit snapshot fallback where no active price version exists
- Booking creation snapshots the effective pricing version into booking commercials to prevent later pricing changes from corrupting historical bookings
- Direct-discount authority is evaluated through configurable `sales.pricing.rules` when present, with safe default limits if no active setting exists
- Unit availability and company-scope validation
- Lead/customer/project/partner consistency validation
- Transactional booking creation with row locking
- Automatic unit status update to booked
- Automatic lead stage update to Booked/won
- Default or supplied payment schedule creation
- Partner read-only scoping by authenticated partner email
- Internal inventory and booking registers reject undocumented query filters while allowing only their documented project, unit, status, customer and bounded pagination filters
- Audit events for booking creation
- Feature tests covering access control, validation and workflow side effects

## Finance Dashboard and Cash-Flow Backend Slice

Authenticated Laravel endpoint now exists for the SOW finance dashboard, cash position and short-term cash-flow forecast:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/finance/dashboard` | `finance.dashboard` | Company/project-scoped finance dashboard with cash position, period inflow/outflow, receivables ageing, payables, GST summary, approval counts and recent activity | Finance, collections or reports users |

Implemented controls:

- Uses existing persisted finance records; no static dashboard counters
- Cash position calculates approved collection cash-in plus approved receipt/contra voucher cash-in less approved payment voucher cash-out as of the selected date
- Period summary calculates approved collections, receipt vouchers, payment vouchers, paid claims and disbursed employee loans for the selected date range
- Receivables calculate booking payment schedule outstanding after approved receipts, overdue value, due-next-30-days value, requested payment links and forecast inflow
- Payables calculate submitted payment vouchers, approved unpaid claims, approved undisbursed loans and forecast outflow
- GST summary aggregates approved GST entries by transaction type for the selected period
- Approval counts are sourced from submitted collection receipts, submitted finance/payment vouchers, submitted GST entries and requested payment links
- Recent activity lists latest scoped collections, vouchers and payment requests
- Company and project scoping is enforced before aggregation; non-global users without company assignment fail closed
- Dashboard query rejects undocumented filters and limits forecast horizon to 180 days
- Partner users cannot access the internal finance dashboard
- Feature tests cover real dashboard calculations, unsupported filters, cross-company scope denial, fail-closed scope and partner restrictions

## Finance Collection Backend Slice

Authenticated Laravel endpoints now exist for customer collection capture and finance approval:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/finance/collections` | `finance.collections.index` | Paginated company-scoped collection receipt list | `collections.view`, `collections.manage` or `collections.approve` |
| POST | `/finance/collections` | `finance.collections.store` | Submit a collection receipt against a booking or payment schedule | `collections.manage` |
| PATCH | `/finance/collections/{collectionReceipt}/approve` | `finance.collections.approve` | Finance approval of a submitted receipt | `collections.approve` |

Implemented controls:

- Normalized receipt table with booking, schedule, customer, collector and approver references
- Submitted-vs-approved receipt workflow
- Segregation of duties: collector cannot approve the same receipt
- Booking and schedule outstanding validation
- Payment mode and instrument validation
- Company-scoped policies and gates
- Collection register rejects undocumented query filters while allowing only booking, project, customer, status, payment mode and bounded pagination filters
- Schedule status update after approval: pending, partially paid or paid
- Audit events for submission and approval
- Feature tests covering listing, submission, approval, overcollection rejection and partner restrictions

## Buyer Payment Request Backend Slice

Authenticated Laravel endpoints now exist for finance-created buyer payment links and buyer-side simulated online payment. This is gateway-agnostic prototype logic only; no external payment provider, banking API or live money movement is invoked.

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/finance/payment-requests` | `finance.payment-requests.index` | Company-scoped payment request register with status, booking, project, customer and search filters | `collections.view`, `collections.manage` or `collections.approve` |
| POST | `/finance/payment-requests` | `finance.payment-requests.store` | Create a buyer-visible payment request against a booking or payment schedule | `collections.manage` |
| PATCH | `/finance/payment-requests/{paymentRequest}/cancel` | `finance.payment-requests.cancel` | Cancel a requested payment link with reason | `collections.manage` |
| POST | `/finance/payment-gateway/webhook` | `finance.payment-gateway.webhook` | Verify signed real-gateway callback and reconcile a successful payment request | HMAC signature, throttled external callback |
| GET | `/buyer/payment-requests` | `buyer.payment-requests.index` | Buyer-scoped list of own payment requests | `buyer.view` |
| PATCH | `/buyer/payment-requests/{paymentRequest}/pay` | `buyer.payment-requests.pay` | Simulate successful buyer payment and auto-create an approved collection receipt only for prototype/demo/mock/sandbox payment providers | Own buyer only |

Implemented controls:

- Normalized `payment_requests` table with booking, payment schedule, customer, creator, payer and receipt references
- Gateway reference, checksum, payment URL payload and workflow history metadata
- Gateway provider is read from `BUILDER360_PAYMENT_GATEWAY_PROVIDER`; client requests cannot override it
- Direct buyer-side simulated payment is blocked when the configured or stored payment provider is a real provider
- Real-provider payment reconciliation is available through the signed webhook route using `X-Builder360-Signature = HMAC-SHA256(raw_body, BUILDER360_PAYMENT_GATEWAY_WEBHOOK_SECRET)`
- Gateway webhook reconciliation verifies the raw-body HMAC signature before payload validation, rejects undocumented payload fields, applies configured payment amount limits, and validates provider, amount, currency, transaction reference and idempotent paid callbacks before creating an approved receipt
- Active booking validation and company scoping
- Booking and schedule outstanding validation before request creation and again before payment
- Duplicate active payment request prevention for the same schedule
- Internal payment request register rejects undocumented query filters while allowing only status, booking, customer, project, search and bounded pagination filters
- Buyer-only payment authorization using the customer portal user link
- Buyer portal index filters are route-specific: booking/payment/ticket/document/receipt endpoints reject unknown or unrelated status, category, priority, owner-type or booking filters instead of silently ignoring them
- Cancelled and expired links cannot be paid
- Prototype-mode simulated successful payment creates an approved collection receipt and refreshes schedule status
- Verified real-gateway webhook payment creates an approved collection receipt without requiring a browser-authenticated user
- Partner users cannot access internal finance or buyer payment request routes
- Audit events for payment request creation, cancellation, simulated payment, verified gateway webhook payment and gateway-auto-approved collection receipt
- Feature tests covering creation, configured-provider enforcement, signed webhook reconciliation, webhook idempotency, real-provider simulation blocking, duplicate prevention, buyer scope, cancellation, collection creation and partner restrictions

## Finance Voucher and Journal Backend Slice

Authenticated Laravel endpoints now exist for balanced accounting vouchers and finance journal control:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/finance/vouchers` | `finance.vouchers.index` | Company-scoped voucher register with status, type, project, date and search filters | `finance.view`, `finance.manage` or `finance.approve` |
| POST | `/finance/vouchers` | `finance.vouchers.store` | Submit receipt, payment, journal, contra, debit-note or credit-note voucher with balanced lines | `finance.manage` |
| PATCH | `/finance/vouchers/{financialVoucher}/approve` | `finance.vouchers.approve` | Approve submitted voucher | `finance.approve` |
| PATCH | `/finance/vouchers/{financialVoucher}/reject` | `finance.vouchers.reject` | Reject submitted voucher with reason | `finance.approve` |

Implemented controls:

- Header and line-level voucher tables for accounting entries
- Supported voucher types: receipt, payment, journal, contra, debit note and credit note
- Debit/credit balancing validation before submission
- Line-level account code, account name, project, party, cost center, tax and description metadata
- Company and project scoping for finance users
- Global users must provide either explicit `company_id` for company-level vouchers or a company-scoped project; mixed-company project lines require explicit, matching company context
- Segregation of duties: voucher creator cannot approve or reject the same voucher
- Voucher register rejects undocumented query filters while allowing only status, voucher type, project, date range, search and pagination page filters
- Submitted, approved and rejected lifecycle with workflow history
- Audit events for voucher submission, approval and rejection
- Seeded approved journal voucher linked to the demo Skyline collection
- Feature tests covering listing, balanced posting, self-approval denial, approval, rejection, unbalanced validation and partner restrictions

## Finance GST Register and Return Backend Slice

Authenticated Laravel endpoints now exist for GST register tracking and monthly return-period controls:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/finance/gst-entries` | `finance.gst-entries.index` | Company-scoped GST entry register with status, type, period, project and search filters | Finance or Compliance users |
| POST | `/finance/gst-entries` | `finance.gst-entries.store` | Submit GST output/input/reverse-charge/adjustment entry | `finance.manage` or `compliance.manage` |
| PATCH | `/finance/gst-entries/{gstEntry}/approve` | `finance.gst-entries.approve` | Approve submitted GST entry | `finance.approve` or `compliance.manage` |
| GET | `/finance/gst-return-periods` | `finance.gst-return-periods.index` | GST return-period register with status and period filters | Finance or Compliance users |
| POST | `/finance/gst-return-periods` | `finance.gst-return-periods.store` | Prepare monthly GST return summary from approved GST entries | `finance.manage` or `compliance.manage` |
| PATCH | `/finance/gst-return-periods/{gstReturnPeriod}/approve` | `finance.gst-return-periods.approve` | Approve prepared GST return period | `finance.approve` or `compliance.manage` |
| PATCH | `/finance/gst-return-periods/{gstReturnPeriod}/lock` | `finance.gst-return-periods.lock` | Lock approved GST period and freeze included entries | `finance.approve` or `compliance.manage` |

Implemented controls:

- Configurable GST rules through `finance.gst_rules`
- Output, input, reverse-charge and adjustment entry types
- GSTIN format validation where GSTIN is supplied
- Tax component total validation against taxable value and configured rate
- Duplicate company/document/transaction-type prevention
- Locked return periods block new entries for the same company/month
- Entry creator cannot approve the same GST entry
- Return preparer cannot approve the same return period
- Return preparation uses approved GST entries only
- Return summary calculates output tax, input tax credit and net payable
- Return lock marks approved entries as locked for audit readiness
- GST entry and return-period registers reject undocumented query filters while allowing only their documented status, type, period, project, search and bounded pagination filters
- Statutory filing correctness remains subject to client/tax-expert validation
- Audit events for GST entry submission/approval and return preparation/approval/lock
- Feature tests covering entry creation, approval, return preparation, approval, locking, duplicate validation, locked-period blocking and partner restrictions

## Document Management Backend Slice

Authenticated Laravel endpoints now exist for controlled document metadata, expiry tracking and approval:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/documents/categories` | `documents.categories.index` | Active document categories scoped by company and owner type | `documents.view`, `documents.manage` or `documents.approve` |
| GET | `/documents` | `documents.index` | Paginated managed document list with owner/status/expiry filters | `documents.view`, `documents.manage` or `documents.approve` |
| POST | `/documents` | `documents.store` | Submit document metadata against project, booking, customer or employee owner | `documents.manage` |
| PATCH | `/documents/{managedDocument}/approve` | `documents.approve` | Approve a submitted managed document | `documents.approve` |

Implemented controls:

- Document category rules by owner type
- Company-scoped document records
- Supported owners: project, booking, customer and employee
- Customer-owned document company scope is derived from the customer's related bookings, leads, tickets or existing documents; submissions fail closed when no unique company context exists
- Expiry-required category validation
- Expiring document filter through `expires_within_days`
- Category and managed-document registers reject undocumented query filters while allowing only their documented owner, category, status, expiry and bounded pagination filters
- Allowed file metadata validation for PDF/JPEG/PNG, file size and SHA-256 checksum
- Versioning with one current document per owner/category/title
- Segregation of duties: uploader cannot approve the same document
- Audit events for document submission and approval
- Feature tests covering category visibility, expiry filters, submission, approval, versioning and partner restrictions

## HR Employee Master and Operations Backend Slice

Authenticated Laravel endpoints now exist for employee master records, employee assets, claims, loans and HR helpdesk operations:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/hr/employees/me` | `hr.employees.me` | Current employee self-service profile | Own employee scope |
| GET | `/hr/employees` | `hr.employees.index` | Company-scoped employee master register with company, branch, project, department, designation, status and search filters | `hr.view` or `hr.manage` |
| POST | `/hr/employees` | `hr.employees.store` | Create employee master record with statutory and sensitive-profile controls | `hr.manage` |
| GET | `/hr/employees/{employee}` | `hr.employees.show` | View employee profile with salary and sensitive-field masking based on permission | HR scope or own employee scope |
| PATCH | `/hr/employees/{employee}` | `hr.employees.update` | Update employee master profile | `hr.manage` |
| GET | `/hr/assets` | `hr.assets.index` | Employee asset register with employee, category, status and search filters | Asset, HR or own employee scope |
| POST | `/hr/assets` | `hr.assets.store` | Register a company asset for employee assignment | `assets.manage` or `hr.manage` |
| PATCH | `/hr/assets/{employeeAsset}/assign` | `hr.assets.assign` | Assign available asset to an employee | `assets.manage` or `hr.manage` |
| PATCH | `/hr/assets/{employeeAsset}/recover` | `hr.assets.recover` | Recover, retire or mark asset as lost | `assets.manage` or `hr.manage` |
| GET | `/hr/expense-claims` | `hr.expense-claims.index` | Expense claim register with employee, type, status and date filters | Claim, Finance, HR or own employee scope |
| POST | `/hr/expense-claims` | `hr.expense-claims.store` | Submit employee reimbursement claim | `claims.manage`, `claims.view` or employee self-service |
| PATCH | `/hr/expense-claims/{expenseClaim}/approve` | `hr.expense-claims.approve` | HR/Finance approval of submitted claim | `claims.approve`, `claims.manage` or `finance.approve` |
| PATCH | `/hr/expense-claims/{expenseClaim}/reject` | `hr.expense-claims.reject` | Reject submitted claim | `claims.approve`, `claims.manage` or `finance.approve` |
| PATCH | `/hr/expense-claims/{expenseClaim}/pay` | `hr.expense-claims.pay` | Mark approved claim as paid | `finance.approve` |
| GET | `/hr/loans` | `hr.loans.index` | Employee loan register with employee, type and status filters | Loan, Finance, HR or own employee scope |
| POST | `/hr/loans` | `hr.loans.store` | Submit employee loan/advance request | `loans.manage` or employee self-service |
| PATCH | `/hr/loans/{employeeLoan}/approve` | `hr.loans.approve` | HR approval of loan request and repayment schedule | `loans.approve` or `loans.manage` |
| PATCH | `/hr/loans/{employeeLoan}/reject` | `hr.loans.reject` | Reject submitted loan request | `loans.approve` or `loans.manage` |
| PATCH | `/hr/loans/{employeeLoan}/disburse` | `hr.loans.disburse` | Finance disbursement of approved loan | `finance.approve` |
| GET | `/hr/helpdesk-tickets` | `hr.helpdesk-tickets.index` | HR helpdesk register with employee, category, priority and status filters | Helpdesk, HR or own employee scope |
| POST | `/hr/helpdesk-tickets` | `hr.helpdesk-tickets.store` | Create HR helpdesk ticket | `helpdesk.manage` or employee self-service |
| PATCH | `/hr/helpdesk-tickets/{hrHelpdeskTicket}/assign` | `hr.helpdesk-tickets.assign` | Assign HR helpdesk ticket to a user | `helpdesk.manage` or `hr.manage` |
| PATCH | `/hr/helpdesk-tickets/{hrHelpdeskTicket}/resolve` | `hr.helpdesk-tickets.resolve` | Record helpdesk resolution | `helpdesk.manage` or `hr.manage` |
| PATCH | `/hr/helpdesk-tickets/{hrHelpdeskTicket}/close` | `hr.helpdesk-tickets.close` | Close resolved HR helpdesk ticket | Ticket owner or HR/helpdesk manager |

Implemented controls:

- Company-scoped employee master access with branch/project/company scope validation
- Salary and sensitive employee data hidden or masked according to permission
- Employee self-service limited to own profile, own assigned assets, own claims, own loans and own HR helpdesk tickets
- Asset assignment/recovery workflow with audit events
- Global users must provide explicit `company_id` when registering company assets
- Expense claim submission, approval/rejection and finance payment workflow
- Loan submission, HR approval/rejection and finance disbursement workflow
- HR helpdesk open, assignment, resolution and closure workflow
- Partner roles blocked from internal HR employee and operations registers
- Employee, asset, claim, loan and helpdesk registers reject undocumented query filters while allowing only their documented employee, scope, type/category, status, date, search and bounded pagination filters
- Feature tests covering employee master visibility, sensitive-field masking, operational workflows, self-service scope, partner restrictions, fail-closed company scoping and strict filter contracts

## HR Leave Management Backend Slice

Authenticated Laravel endpoints now exist for configurable leave types, employee balances and leave approval workflows:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/hr/leave-types` | `hr.leave-types.index` | Active company-scoped leave types and policy metadata | `leave.view`, `leave.request`, `leave.manage` or `leave.approve` |
| GET | `/hr/leave-balances` | `hr.leave-balances.index` | Employee leave balances; self-scoped for requesters, company-scoped for HR | `leave.view`, `leave.request`, `leave.manage` or `leave.approve` |
| GET | `/hr/leave-requests` | `hr.leave-requests.index` | Leave request list; self-scoped for employees, company-scoped for HR | `leave.view`, `leave.request`, `leave.manage` or `leave.approve` |
| POST | `/hr/leave-requests` | `hr.leave-requests.store` | Submit a leave request with balance reservation | `leave.request` or `leave.manage` |
| PATCH | `/hr/leave-requests/{leaveRequest}/approve` | `hr.leave-requests.approve` | Approve a submitted leave request | `leave.approve` |
| PATCH | `/hr/leave-requests/{leaveRequest}/reject` | `hr.leave-requests.reject` | Reject a submitted leave request and release reserved balance | `leave.approve` |
| GET | `/hr/leave-processing-runs` | `hr.leave-processing-runs.index` | Leave accrual/year-end processing run register | `leave.view`, `leave.manage` or `leave.approve` |
| POST | `/hr/leave-processing-runs` | `hr.leave-processing-runs.store` | Preview monthly accrual or year-end carry-forward/lapse processing | `leave.manage` |
| PATCH | `/hr/leave-processing-runs/{leaveProcessingRun}/post` | `hr.leave-processing-runs.post` | Post a previewed leave processing run | `leave.approve` |
| GET | `/hr/leave-encashments` | `hr.leave-encashments.index` | Leave encashment register; self-scoped for employees and company-scoped for HR/payroll | `leave.view`, `leave.manage`, `leave.approve`, `leave.request`, `payroll.view` or `payroll.manage` |
| POST | `/hr/leave-encashments` | `hr.leave-encashments.store` | Submit an eligible leave encashment request | `leave.request` or `leave.manage` |
| PATCH | `/hr/leave-encashments/{leaveEncashment}/approve` | `hr.leave-encashments.approve` | Approve leave encashment and reduce available balance | `leave.approve` or `leave.manage` |
| PATCH | `/hr/leave-encashments/{leaveEncashment}/reject` | `hr.leave-encashments.reject` | Reject submitted leave encashment | `leave.approve` or `leave.manage` |
| PATCH | `/hr/leave-encashments/{leaveEncashment}/mark-payroll` | `hr.leave-encashments.mark-payroll` | Mark approved encashment for payroll inclusion | `payroll.manage` |

Implemented controls:

- Configurable leave type policy metadata: entitlement, paid/unpaid, half-day, negative balance, carry-forward, encashment and approval-chain settings
- Employee/year-specific leave balances
- Self-service scope for employees and company scope for HR approvers
- Global users must provide explicit `company_id` when previewing company-level leave processing runs
- Overlapping leave request prevention
- Supporting document requirement for document-controlled leave types
- Transactional balance reservation on submit
- Balance deduction on approval and release on rejection
- Monthly accrual and year-end processing preview before posting
- Idempotent posted-run guard so the same company/year/type cannot be posted twice
- Carry-forward and lapse calculations based on leave type policy metadata
- Next-year opening balance creation for year-end carry-forward
- Encashment eligibility and tax formula sourced from versioned `hr.leave.rules`
- Encashment approval reduces leave balance and payroll marking notifies payroll users
- Segregation of duties: requester cannot approve own request
- Leave type, balance, request, processing-run and encashment registers reject undocumented query filters while allowing only their documented employee, year, type, status, date and bounded pagination filters
- Audit events for submission, approval and rejection
- Audit events for leave processing preview/posting and encashment submission/approval/payroll marking
- Feature tests covering self-service listing, submission, approval, rejection, balance updates, insufficient balance, accrual posting, year-end carry-forward/lapse, encashment payroll inclusion and partner restrictions

## HR Attendance Management Backend Slice

Authenticated Laravel endpoints now exist for shift-based attendance records and regularization approval:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/hr/attendance-shifts` | `hr.attendance-shifts.index` | Active company-scoped attendance shifts and rule metadata | `attendance.view`, `attendance.request`, `attendance.manage` or `attendance.approve` |
| GET | `/hr/attendance-records` | `hr.attendance-records.index` | Attendance records with employee/status/date filters; self-scoped for employees, company-scoped for HR | `attendance.view`, `attendance.request`, `attendance.manage` or `attendance.approve` |
| GET | `/hr/attendance-regularizations` | `hr.attendance-regularizations.index` | Attendance regularization request list | `attendance.view`, `attendance.request`, `attendance.manage` or `attendance.approve` |
| POST | `/hr/attendance-regularizations` | `hr.attendance-regularizations.store` | Submit check-in/check-out correction request | `attendance.request` or `attendance.manage` |
| PATCH | `/hr/attendance-regularizations/{regularization}/approve` | `hr.attendance-regularizations.approve` | Approve regularization and update attendance record | `attendance.approve` |
| PATCH | `/hr/attendance-regularizations/{regularization}/reject` | `hr.attendance-regularizations.reject` | Reject regularization without changing attendance | `attendance.approve` |

Implemented controls:

- Configurable shift timing, grace, half-day and full-day thresholds
- Employee shift assignments with effective dates
- Late-coming, early-leaving and worked-minute calculations
- Self-service scope for employees and company scope for HR approvers
- One pending regularization per employee/date
- Transactional approval updates or creates the attendance record
- Rejection leaves original attendance unchanged
- Segregation of duties: requester cannot approve own request
- Attendance shift, record and regularization registers reject undocumented query filters while allowing only their documented employee, status, date and bounded pagination filters
- Audit events for submission, approval and rejection
- Feature tests covering listing, calculations, approval update, rejection, employee scope and partner restrictions

## HR Employee Confirmation Backend Slice

Authenticated Laravel endpoints now exist for probation confirmation review, manager recommendation and HR confirmation decision:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/hr/confirmation-cases` | `hr.confirmation-cases.index` | Company-scoped confirmation due queue with employee, manager, department, status and due-date filters | HR, reporting manager or own employee scope |
| POST | `/hr/confirmation-cases` | `hr.confirmation-cases.store` | Create employee probation confirmation case | `hr.manage` |
| PATCH | `/hr/confirmation-cases/{employeeConfirmationCase}/recommend` | `hr.confirmation-cases.recommend` | Reporting manager submits confirm/extend/reject recommendation with review scores | Reporting manager with `performance.manage` |
| PATCH | `/hr/confirmation-cases/{employeeConfirmationCase}/decide` | `hr.confirmation-cases.decide` | HR records final confirm/extend/reject decision and confirmation-letter reference | `hr.manage` |

Implemented controls:

- Normalized employee confirmation case records with probation dates, review due date, manager recommendation, HR decision and letter reference
- Due queue filtering by status, employee, manager, department and review date range
- Manager scoping through employee reporting line
- Employee self-service read scope for own confirmation case
- Workflow order: due → manager recommended → confirmed, extended or rejected
- Confirmation requires effective date and confirmation-letter reference
- Extension requires extended probation end date
- Employee master profile is updated with confirmation decision metadata
- Notifications for manager review due, HR decision due and employee final decision
- Audit events for case creation, manager recommendation and HR decision
- Confirmation due queue rejects undocumented query filters while allowing only employee, manager, department, status, due-date and bounded pagination filters
- Partner roles blocked from internal confirmation workflow
- Feature tests covering due queue, creation, duplicate prevention, manager recommendation, HR decision, extension validation, employee scope and partner restrictions

## HR Employee Separation and Full & Final Backend Slice

Authenticated Laravel endpoints now exist for employee separation initiation, Full & Final calculation, HR approval, Finance approval and completion control:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/hr/separation-settlements` | `hr.separation-settlements.index` | Company-scoped separation and F&F register with employee, status, type and date filters; self-scoped for employees | HR, Finance or own employee scope |
| POST | `/hr/separation-settlements` | `hr.separation-settlements.store` | Initiate employee separation and calculate draft F&F settlement | `hr.manage` |
| PATCH | `/hr/separation-settlements/{employeeSeparationSettlement}/hr-approve` | `hr.separation-settlements.hr-approve` | HR verifies and approves F&F calculation before finance review | `hr.manage` |
| PATCH | `/hr/separation-settlements/{employeeSeparationSettlement}/finance-approve` | `hr.separation-settlements.finance-approve` | Finance approves HR-approved F&F settlement with segregation of duties | `finance.approve` |
| PATCH | `/hr/separation-settlements/{employeeSeparationSettlement}/complete` | `hr.separation-settlements.complete` | Complete F&F after all clearances are resolved and payment reference is recorded | `finance.approve` |

Implemented controls:

- Normalized separation settlement records with resignation date, last working date, settlement date, separation type and workflow history
- Full & Final calculation breakdown for last salary, leave encashment, bonus, gratuity, approved claims, notice recovery, loan recovery, tax recovery, gross payable, recoveries and net payable
- Clearance blocker detection for assigned assets, open loans, open expense claims and pending attendance regularizations
- Workflow order: initiated -> HR approved -> Finance approved -> completed
- Employee master status changes to `on_notice` on initiation and `separated` on completion
- Duplicate active settlement prevention for the same employee
- Segregation of duties preventing the HR approver from acting as the finance approver
- Employee self-service read scope for own settlement record
- Notifications for finance approval and employee completion
- Audit events for initiation, HR approval, finance approval and completion
- Separation/F&F register rejects undocumented query filters while allowing only employee, status, separation type, last-working-date and bounded pagination filters
- Partner roles blocked from internal separation/F&F workflow
- Feature tests covering calculation, blockers, duplicate prevention, approval order, segregation, blocked completion, successful completion, employee scope and partner restrictions

## HR Exit Interview Backend Slice

Authenticated Laravel endpoints now exist for confidential employee exit questionnaires, HR review and aggregate exit analytics:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/hr/exit-interviews` | `hr.exit-interviews.index` | Company-scoped exit interview register with employee, status, reason, rehire and due-date filters; self-scoped for employees | HR or own employee scope |
| GET | `/hr/exit-interviews/summary` | `hr.exit-interviews.summary` | Aggregate exit analytics by status, separation reason, rehire recommendation, department, ratings and risk flags | `hr.view` or `hr.manage` |
| POST | `/hr/exit-interviews` | `hr.exit-interviews.store` | Schedule employee exit interview with configurable questionnaire template | `hr.manage` |
| PATCH | `/hr/exit-interviews/{employeeExitInterview}/submit` | `hr.exit-interviews.submit` | Employee or HR submits exit questionnaire, reason taxonomy, ratings, rehire recommendation and confidential responses | Own employee or `hr.manage` |
| PATCH | `/hr/exit-interviews/{employeeExitInterview}/review` | `hr.exit-interviews.review` | HR reviews confidential responses and records action items | `hr.manage` |

Implemented controls:

- Normalized exit interview records with due date, workflow status, reason taxonomy, ratings, rehire recommendation, risk flags and action items
- Configurable questionnaire template stored per interview record
- Confidential responses encrypted through Laravel encrypted casts and hidden from employee/self-service responses after submission
- HR-only confidential response and HR review-note visibility
- Employee self-service scope limited to own exit interview
- Aggregate summary endpoint uses shared company scoping, supports global users, fails closed for non-global users without company assignment and intentionally excludes confidential narrative answers
- Workflow order: scheduled -> submitted -> reviewed
- Duplicate active exit interview prevention for the same employee
- Notifications for employee questionnaire completion and HR review queue
- Audit events for scheduling, submission and review
- Exit interview register rejects undocumented query filters while allowing only employee, status, reason, rehire recommendation, due-date and bounded pagination filters
- Partner roles blocked from internal exit interview workflow and reports
- Feature tests covering scheduling, confidential submission, HR review, masking, summary analytics, employee scope and partner restrictions

## HR Performance Management Backend Slice

Authenticated Laravel endpoints now exist for KPI/KRA review cycles, employee self-reviews, manager review submission, HR closure and PIP tracking:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/hr/performance-cycles` | `hr.performance-cycles.index` | Company-scoped performance cycle list with frequency/status/department/project filters | `performance.view`, `performance.manage`, `performance.approve` or employee self-service |
| POST | `/hr/performance-cycles` | `hr.performance-cycles.store` | Create monthly, quarterly or annual performance review cycle | `performance.manage` or `hr.manage` |
| GET | `/hr/performance-reviews` | `hr.performance-reviews.index` | Employee review register with self, manager and HR scoping | `performance.view`, `performance.manage`, `performance.approve` or employee self-service |
| POST | `/hr/performance-reviews` | `hr.performance-reviews.store` | Create KPI/KRA review for an employee in an active cycle | `performance.manage` or `hr.manage` |
| PATCH | `/hr/performance-reviews/{performanceReview}/self-submit` | `hr.performance-reviews.self-submit` | Employee submits self-review and self score | Own employee self-service |
| PATCH | `/hr/performance-reviews/{performanceReview}/manager-submit` | `hr.performance-reviews.manager-submit` | Reporting manager submits score, KPI actuals and comments | Reporting manager or `performance.approve` |
| PATCH | `/hr/performance-reviews/{performanceReview}/close` | `hr.performance-reviews.close` | HR closes final rating and optional PIP plan | `performance.approve` |

Implemented controls:

- Monthly, quarterly and annual cycle frequencies
- Cycle overlap prevention for the same company, frequency, department and project population
- Department/project population scoping for cycle-linked reviews
- Global users must provide an explicit company for company-level performance cycles; project-linked cycles derive scope from the selected project
- KPI weight validation requiring 100% total weight
- Duplicate review prevention per employee and cycle
- Employee self-review, manager submission and HR closure workflow order
- Reporting-manager scoping through employee master reporting lines
- PIP flag, status and structured PIP plan on HR closure
- Employee, manager and HR notification hooks
- Audit events for cycle creation, review creation, self submission, manager submission and HR closure
- Performance cycle and review registers reject undocumented query filters while allowing only their documented cycle, employee, department, project, status, frequency, current, PIP, date and bounded pagination filters
- Feature tests covering cycle/review registers, overlap prevention, duplicate prevention, full workflow, PIP, audit, notifications and partner restrictions

## Payroll Backend Slice

Authenticated Laravel endpoints now exist for salary configuration, monthly payroll processing and bank-transfer batch control:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/payroll/components` | `payroll.components.index` | Active company-scoped payroll earning/deduction components | `payroll.view`, `payroll.manage` or `payroll.approve` |
| GET | `/payroll/salary-structures` | `payroll.salary-structures.index` | Active salary structures with component breakup | `payroll.view`, `payroll.manage` or `payroll.approve` |
| GET | `/payroll/runs` | `payroll.runs.index` | Payroll runs with employee run items | `payroll.view`, `payroll.manage` or `payroll.approve` |
| POST | `/payroll/runs` | `payroll.runs.generate` | Generate monthly payroll from effective salary assignments | `payroll.manage` |
| PATCH | `/payroll/runs/{payrollRun}/approve` | `payroll.runs.approve` | Approve generated payroll run | `payroll.approve` |
| GET | `/payroll/bank-transfer-batches` | `payroll.bank-transfer-batches.index` | Bank-transfer batch register with optional CSV payload | `payroll.view`, `payroll.manage` or `payroll.approve` |
| POST | `/payroll/runs/{payrollRun}/bank-transfer-batches` | `payroll.runs.bank-transfer-batches.store` | Prepare bank-transfer CSV batch from an approved payroll run | `payroll.manage` |
| PATCH | `/payroll/bank-transfer-batches/{payrollBankTransferBatch}/release` | `payroll.bank-transfer-batches.release` | Finance release of prepared bank-transfer batch | `payroll.approve` |
| GET | `/payroll/tax-documents` | `payroll.tax-documents.index` | Company-scoped Form 16/tax document register; self-scoped for employees | Payroll, Compliance or own employee scope |
| POST | `/payroll/tax-documents` | `payroll.tax-documents.store` | Generate Form 16 from approved payroll runs and verified locked tax configuration | `payroll.manage` or `compliance.manage` |
| PATCH | `/payroll/tax-documents/{employeeTaxDocument}/issue` | `payroll.tax-documents.issue` | Issue generated Form 16 to employee after segregation-of-duties review | `payroll.approve` or `compliance.manage` |
| PATCH | `/payroll/tax-documents/{employeeTaxDocument}/acknowledge` | `payroll.tax-documents.acknowledge` | Employee acknowledges issued Form 16 | Own employee self-service |
| GET | `/payroll/commission-rules` | `payroll.commission-rules.index` | Company-scoped commission rule master with type, basis, project and status filters | `payroll.view`, `payroll.manage`, `payroll.approve` or `reports.view` |
| POST | `/payroll/commission-rules` | `payroll.commission-rules.store` | Configure a fixed, percentage, slab or target-based commission rule | `payroll.manage` |
| GET | `/payroll/commission-runs` | `payroll.commission-runs.index` | Commission run register with rule, period and status filters | `payroll.view`, `payroll.manage`, `payroll.approve` or `reports.view` |
| POST | `/payroll/commission-runs` | `payroll.commission-runs.store` | Calculate commission from approved CRM/sales source records | `payroll.manage` |
| PATCH | `/payroll/commission-runs/{commissionRun}/approve` | `payroll.commission-runs.approve` | Finance approval of generated commission for payroll inclusion | `payroll.approve` |
| PATCH | `/payroll/commission-runs/{commissionRun}/reject` | `payroll.commission-runs.reject` | Reject generated commission run with reason | `payroll.approve` |

Implemented controls:

- Configurable earning and deduction components
- Versioned salary structures and structure components
- Effective-dated employee salary assignments
- Transactional payroll generation
- Duplicate payroll-period prevention
- Configurable commission rules supporting fixed, percentage, slab and target-based calculations
- Commission basis can be booking value or approved collections received
- Commission runs calculate from confirmed CRM bookings and linked sales employees
- Duplicate commission rule/period generation prevention
- Commission generator cannot approve or reject the same run
- Approved commission items are included once as `COMM` payroll earnings in the matching payroll period
- Commission items are marked `payroll_included` with payroll run item linkage to prevent duplicate payout
- Gross earnings, deductions and net payable totals
- Payroll generator cannot approve the same run
- Payroll run item status changes after approval
- Bank-transfer batches available only after finance-approved payroll
- Employee bank details validated from encrypted employee profile metadata
- IFSC, bank account, duplicate employee/account, negative net pay and control-total validation
- CSV prototype output with batch number, checksum, control total and item-level transfer records
- Batch preparer cannot release the same bank-transfer batch
- Form 16 generation requires active approved `payroll.tax_rules` setting for the financial year
- Tax configuration must be verified and payroll year must be locked before generation
- Form 16 uses approved payroll runs only, snapshots component totals, tax settings and payroll periods
- Versioned Form 16 documents support controlled regeneration with `force_new_version`
- Generated Form 16 issue requires segregation of duties
- Employee acknowledgement is restricted to the linked employee user
- Payroll component, salary-structure, payroll-run, bank-batch, commission and tax-document registers reject undocumented query filters while allowing only their documented type, period, status, scope, bank, date, payload and bounded pagination filters
- Audit events for generation, approval, commission rule creation, commission run approval/rejection, bank-batch preparation, release, Form 16 generation, issue and acknowledgement
- Feature tests covering configuration listing, run generation, approval, totals, duplicate prevention, commission calculation, commission payroll inclusion, bank validation, CSV payload, release controls, tax config prerequisites, Form 16 versioning, issue/acknowledgement and partner restrictions

## Recruitment Backend Slice

Authenticated Laravel endpoints now exist for applicant tracking, interview scheduling and offer release workflows:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/recruitment/source-summary` | `recruitment.source-summary` | Source-wise recruitment analytics with candidate, interview, offer, conversion and rejection metrics | `recruitment.view`, `recruitment.manage` or `recruitment.approve` |
| GET | `/recruitment/job-openings` | `recruitment.job-openings.index` | Open and historical company-scoped job openings/requisitions | `recruitment.view`, `recruitment.manage` or `recruitment.approve` |
| POST | `/recruitment/job-openings` | `recruitment.job-openings.store` | Submit a job requisition for approval | `recruitment.manage` |
| PATCH | `/recruitment/job-openings/{jobOpening}/approve` | `recruitment.job-openings.approve` | Approve a pending requisition and open it for candidate sourcing | `recruitment.approve` |
| PATCH | `/recruitment/job-openings/{jobOpening}/reject` | `recruitment.job-openings.reject` | Reject a pending requisition with review note | `recruitment.approve` |
| GET | `/recruitment/candidates` | `recruitment.candidates.index` | Candidate master with source, stage and search filters | `recruitment.view`, `recruitment.manage` or `recruitment.approve` |
| POST | `/recruitment/candidates` | `recruitment.candidates.store` | Create candidate profile under an open job opening | `recruitment.manage` |
| POST | `/recruitment/candidates/{candidate}/convert-to-employee` | `recruitment.candidates.convert-to-employee` | Convert a released-offer candidate into the HR employee master | `recruitment.approve` |
| GET | `/recruitment/interviews` | `recruitment.interviews.index` | Scheduled interview list with status/date filters | `recruitment.view`, `recruitment.manage` or `recruitment.approve` |
| POST | `/recruitment/interviews` | `recruitment.interviews.store` | Schedule candidate interview with panel members | `recruitment.manage` |
| PATCH | `/recruitment/interviews/{interview}/feedback` | `recruitment.interviews.feedback` | Submit panel feedback, rating and recommendation for a scheduled interview | Assigned interview panel member |
| GET | `/recruitment/offers` | `recruitment.offers.index` | Offer draft/release register | `recruitment.view`, `recruitment.manage` or `recruitment.approve` |
| POST | `/recruitment/offers` | `recruitment.offers.store` | Create offer draft from configured placeholders | `recruitment.manage` |
| PATCH | `/recruitment/offers/{jobOffer}/release` | `recruitment.offers.release` | HR approval/release of an offer | `recruitment.approve` |

Implemented controls:

- Company-scoped job openings/requisitions, candidates, interviews and offers
- Recruitment source summary report calculates real database-backed candidate counts, interview progression, offer counts, accepted offers, conversion rates and rejection rates by source
- Source summary rejects undocumented filters and supports company, source, department and created-date range filters with fail-closed company scoping
- Job requisition submission stores pending approval status, business justification, workflow history, creator, review metadata and audit trail
- Job requisition review enforces approver permission, company scope and creator/approver segregation before opening or rejecting the requisition
- Candidate creation remains blocked until the selected requisition is approved and opened
- Recruitment job-opening, candidate, interview and offer registers reject undocumented query filters while allowing only their documented status, department, stage, source, search, date and bounded pagination filters
- Candidate duplicate detection by company email and phone
- Candidate source, skills, documents, stage history and notes
- Interview future-date validation
- Interview candidate and panel conflict validation
- Interview feedback submission is limited to assigned panel members, stores reviewer rating/recommendation/comments in the interview feedback JSON, blocks duplicate panel feedback, computes summary metrics and completes the interview when all assigned panel members submit
- Offer generation requires mandatory placeholders
- Offer creator cannot release the same offer
- Candidate-to-employee conversion requires a released offer, blocks duplicate conversion, creates the HR employee record, links the candidate to the employee, marks the offer accepted and records acceptance metadata
- Candidate stage updates for interview scheduling, completed interview feedback, offer draft, offer release and employee creation
- Audit events for candidate creation, interview scheduling, interview feedback, offer creation, offer release and employee conversion
- Feature tests covering listing, source reporting, requisition submission/review, candidate gating, candidate creation, duplicates, interview conflicts, panel feedback, offer approval separation, employee conversion, duplicate conversion prevention and partner restrictions

## Procurement Backend Slice

Authenticated Laravel endpoints now exist for vendor registers, purchase requisitions, purchase orders, goods receipts and stock balances:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/procurement/dashboard` | `procurement.dashboard` | Real procurement dashboard summary with requisition, PO, GRN, stock, pending-delivery and low-stock metrics | `procurement.view`, `procurement.manage` or `procurement.approve` |
| GET | `/procurement/vendors` | `procurement.vendors.index` | Company-scoped vendor master list with type/status/search filters | `procurement.view`, `procurement.manage` or `procurement.approve` |
| POST | `/procurement/vendors` | `procurement.vendors.store` | Create vendor or contractor master with statutory, contact, address, bank and compliance metadata | `procurement.manage` |
| GET | `/procurement/vendors/{vendor}/performance` | `procurement.vendors.performance` | Vendor purchase history, GRN acceptance, payable position and computed performance rating | `procurement.view`, `procurement.manage` or `procurement.approve` |
| PATCH | `/procurement/vendors/{vendor}` | `procurement.vendors.update` | Update vendor or contractor master operational, statutory, contact, address, bank and compliance metadata | `procurement.manage` |
| PATCH | `/procurement/vendors/{vendor}/status` | `procurement.vendors.status.update` | Activate, deactivate or block vendor master with reason history | `procurement.manage` |
| GET | `/procurement/requisitions` | `procurement.requisitions.index` | Purchase requisition register with project/status filters | `procurement.view`, `procurement.manage` or `procurement.approve` |
| POST | `/procurement/requisitions` | `procurement.requisitions.store` | Submit purchase requisition with item-level estimated values | `procurement.manage` |
| PATCH | `/procurement/requisitions/{purchaseRequisition}/approve` | `procurement.requisitions.approve` | Approve submitted purchase requisition | `procurement.approve` |
| GET | `/procurement/purchase-orders` | `procurement.purchase-orders.index` | Purchase order register with vendor/project/status filters | `procurement.view`, `procurement.manage` or `procurement.approve` |
| POST | `/procurement/purchase-orders` | `procurement.purchase-orders.store` | Create purchase order draft from approved requisition or direct project purchase | `procurement.manage` |
| PATCH | `/procurement/purchase-orders/{purchaseOrder}/approve` | `procurement.purchase-orders.approve` | Approve draft purchase order | `procurement.approve` |
| GET | `/procurement/goods-receipts` | `procurement.goods-receipts.index` | Goods receipt register by project or purchase order | `procurement.view`, `procurement.manage` or `procurement.approve` |
| POST | `/procurement/goods-receipts` | `procurement.goods-receipts.store` | Record accepted/rejected material receipt against approved PO | `procurement.manage` |
| GET | `/procurement/stock-items` | `procurement.stock-items.index` | Company/project-scoped site stock balance register with item, store, status and low-stock filters | `procurement.view`, `procurement.manage` or `procurement.approve` |
| POST | `/procurement/stock-issues` | `procurement.stock-issues.store` | Record controlled stock issue, consumption or wastage movement without allowing negative stock | `procurement.manage` |
| POST | `/procurement/stock-returns` | `procurement.stock-returns.store` | Record returned material back into the project/store ledger at current weighted average rate | `procurement.manage` |
| POST | `/procurement/stock-transfers` | `procurement.stock-transfers.store` | Transfer material between project/store locations with paired transfer-out and transfer-in ledger rows | `procurement.manage` |

Implemented controls:

- Company-scoped vendor, requisition, PO, GRN, stock balance and stock movement records
- Procurement dashboard metrics are calculated from current database records, including pending-delivery quantities and values from PO line quantities less accepted GRN quantities
- Vendor performance view calculates purchase history, accepted GRN value, approved vendor-payment voucher deductions, payable balance, acceptance rate, fulfillment rate, on-time delivery rate and a 1-5 operational rating from current records
- Vendor and contractor master creation/update/status lifecycle is database-backed, audited and company-scoped
- Vendor code and GSTIN duplicates are blocked per company before database write
- Global users must provide an active company for vendor creation; non-global users are restricted to their assigned company
- Vendor compliance metadata for GST, PAN, bank and document status
- Vendor PAN and bank account identifiers are encrypted at rest and masked in procurement API responses
- Active project/vendor validation
- Vendor, requisition, purchase-order and goods-receipt registers reject undocumented query filters while allowing only their documented scope, status, search and bounded pagination filters
- Purchase requisition estimated total calculation
- Purchase order subtotal, tax and total calculation
- Purchase orders from requisitions require approved requisition status
- Requester/creator cannot approve their own requisition or purchase order
- Goods receipts allowed only against approved or partially received purchase orders
- Accepted receipt quantity cannot exceed pending purchase order quantity
- Duplicate item codes are rejected in a single goods receipt to prevent over-receipt and stock corruption
- Accepted GRN quantities post inward stock movements and update site stock quantity, value and weighted average rate
- Stock issue, consumption and wastage movements reduce site stock using current weighted average rate and reject negative-stock requests
- Stock returns increase project/store stock with audited return reference, return source, reason and remarks metadata
- Stock transfers create paired transfer-out and transfer-in movements, create the destination stock item when needed, preserve weighted-average valuation and reject same-location, cross-company and negative-stock transfers
- Purchase order status moves to partially received or received based on accepted quantities
- Stock register exposes low-stock filtering based on configurable per-item minimum stock quantity records
- Low-stock notifications are generated from real post-movement balances when on-hand quantity is at or below the configured item minimum; duplicate unread alerts for the same stock item are suppressed until existing alerts are read or archived
- Audit events for vendor master creation/update/status change, requisition submission/approval, PO creation/approval, goods receipt creation, stock issue, stock return and stock transfer
- Feature tests covering vendor master lifecycle, registers, inward/outward/return/transfer stock movements, low-stock alerts, workflows, totals, validation, over-receipt/negative-stock blocking and partner restrictions

## Construction Progress Backend Slice

Authenticated Laravel endpoints now exist for construction milestone planning, BOQ control, contractor measurement certification, contractor bill verification/payment status and daily site progress reporting:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/construction/milestones` | `construction.milestones.index` | Company-scoped construction milestone list with project/status/phase filters | `construction.view`, `construction.manage` or `construction.approve` |
| POST | `/construction/milestones` | `construction.milestones.store` | Create construction milestone plan for an active project | `construction.manage` |
| GET | `/construction/boq-items` | `construction.boq-items.index` | Project BOQ register with project/vendor/trade/status filters | `construction.view`, `construction.manage`, `construction.approve` or `procurement.view` |
| POST | `/construction/boq-items` | `construction.boq-items.store` | Create BOQ line with planned quantity, rate, budget and optional contractor mapping | `construction.manage` |
| GET | `/construction/daily-progress-reports` | `construction.daily-progress-reports.index` | Daily progress report register with project/status/date filters | `construction.view`, `construction.manage` or `construction.approve` |
| POST | `/construction/daily-progress-reports` | `construction.daily-progress-reports.store` | Submit DPR with manpower, progress, material, equipment, safety and quality details | `construction.manage` |
| PATCH | `/construction/daily-progress-reports/{dailyProgressReport}/approve` | `construction.daily-progress-reports.approve` | Approve submitted DPR and update milestone progress | `construction.approve` |
| PATCH | `/construction/daily-progress-reports/{dailyProgressReport}/reject` | `construction.daily-progress-reports.reject` | Reject submitted DPR with reason | `construction.approve` |
| GET | `/construction/contractor-measurements` | `construction.contractor-measurements.index` | Contractor measurement register with project/vendor/status/date filters | `construction.view`, `construction.manage`, `construction.approve` or `finance.view` |
| POST | `/construction/contractor-measurements` | `construction.contractor-measurements.store` | Submit contractor measurement certificate lines against BOQ items | `construction.manage` |
| PATCH | `/construction/contractor-measurements/{contractorMeasurement}/approve` | `construction.contractor-measurements.approve` | Certify submitted measurement and update BOQ measured/certified quantities | `construction.approve` |
| PATCH | `/construction/contractor-measurements/{contractorMeasurement}/reject` | `construction.contractor-measurements.reject` | Reject submitted measurement with reason | `construction.approve` |
| GET | `/construction/contractor-bills` | `construction.contractor-bills.index` | Contractor bill register with project/vendor/status/date filters | `construction.view`, `construction.manage`, `construction.approve`, `finance.view`, `finance.manage` or `finance.approve` |
| POST | `/construction/contractor-bills` | `construction.contractor-bills.store` | Submit contractor bill from an approved measurement with retention, deductions, tax and payable calculation | `construction.manage` |
| PATCH | `/construction/contractor-bills/{contractorBill}/approve` | `construction.contractor-bills.approve` | Approve submitted contractor bill after verification | `construction.approve` |
| PATCH | `/construction/contractor-bills/{contractorBill}/mark-paid` | `construction.contractor-bills.mark-paid` | Record contractor payment and update partial/paid status | `finance.manage` or `finance.approve` |

Implemented controls:

- Company and active-project scoping
- Construction milestone, BOQ, DPR and contractor-measurement registers reject undocumented query filters while allowing only their documented project, vendor, trade, status, phase, date and bounded pagination filters
- Unique milestone code per project
- Unique BOQ code per project
- BOQ budget calculation from planned quantity and rate
- BOQ mapping to milestone and contractor/vendor
- Contractor measurement lines normalized against active project BOQ items
- Measured and certified amount calculation from BOQ rates
- Certified quantity cannot exceed measured quantity on submission
- Cumulative approved certified quantity cannot exceed planned BOQ quantity
- BOQ measured/certified quantity and certified amount update only after measurement approval
- Measurement submitter cannot approve or reject the same certificate
- Rejected measurements do not update BOQ totals
- Contractor bill can be created only once per approved contractor measurement
- Contractor billing rules come from active `construction.contractor_billing` system settings with safe fallback defaults
- Contractor bill gross amount is based on approved certified measurement value
- Retention percent, deduction total, tax amount, payable amount, paid amount and balance amount are calculated and persisted
- Configured retention and deduction limits are enforced before bill creation
- Bill preparer cannot approve the same contractor bill
- Payments can be recorded only after bill approval and cannot exceed the current balance
- Contractor bill payment status moves from approved to partially paid to paid based on cumulative payment amount
- One daily progress report per project/date
- Manpower total validation against manpower breakup
- Progress milestone ownership validation
- DPR preparer cannot approve or reject the same report
- Milestone progress updates only after DPR approval
- Approved DPR can move milestones to in progress or completed
- Materials, equipment, safety, quality and blocker details stored as structured site-report data
- Audit events for milestone creation, BOQ creation, measurement submission/certification/rejection, contractor bill submission/approval/payment, DPR submission, approval and rejection
- Feature tests covering BOQ listing/creation, measurement submission/certification/rejection, over-certification blocking, contractor bill calculation/approval/payment, configured bill limits, duplicate bill blocking, DPR approval/rejection, duplicate report prevention, milestone progress update and partner restrictions

## Legal, RERA and Compliance Backend Slice

Authenticated Laravel endpoints now exist for RERA registration tracking, project approval/NOC tracking and compliance obligation management:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/legal/rera-registrations` | `legal.rera-registrations.index` | Company-scoped RERA register with project/status/expiry filters | `legal.view`, `legal.manage` or `legal.approve` |
| POST | `/legal/rera-registrations` | `legal.rera-registrations.store` | Submit RERA registration record for an active project | `legal.manage` |
| PATCH | `/legal/rera-registrations/{reraRegistration}/verify` | `legal.rera-registrations.verify` | Verify submitted RERA registration | `legal.approve` |
| GET | `/legal/project-approvals` | `legal.project-approvals.index` | Project approvals/NOCs with type/status/expiry filters | `legal.view`, `legal.manage` or `legal.approve` |
| POST | `/legal/project-approvals` | `legal.project-approvals.store` | Record project approval, NOC or authority permission | `legal.manage` |
| PATCH | `/legal/project-approvals/{projectApproval}/verify` | `legal.project-approvals.verify` | Verify project approval record | `legal.approve` |
| GET | `/legal/compliance-obligations` | `legal.compliance-obligations.index` | Compliance calendar with project/type/status/due filters | `legal.view`, `legal.manage` or `legal.approve` |
| POST | `/legal/compliance-obligations` | `legal.compliance-obligations.store` | Create compliance obligation or filing task | `legal.manage` |
| PATCH | `/legal/compliance-obligations/{complianceObligation}/complete` | `legal.compliance-obligations.complete` | Complete obligation with evidence reference | `legal.manage` or `legal.approve` |

Implemented controls:

- Company and active-project scoping
- Unique RERA registration number per company
- Unique approval code per project
- Registration, approval, expiry and due-date filters
- Legal/RERA registers reject undocumented query filters while allowing only their documented project, status, type, expiry/due and bounded pagination filters
- Submitter/responsible user cannot verify their own legal record
- Compliance obligations require evidence reference before completion
- Workflow histories for submitted, verified, open and completed states
- Audit events for RERA submission/verification, approval creation/verification and obligation creation/completion
- Feature tests covering registers, creation, verification, duplicate prevention, obligation completion and partner restrictions

## Possession and Handover Backend Slice

Authenticated Laravel endpoints now exist for possession readiness, final handover and snag closure:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/possession/handovers` | `possession.handovers.index` | Company-scoped possession handover register with project/status filters | `possession.view`, `possession.manage` or `possession.approve` |
| POST | `/possession/handovers` | `possession.handovers.store` | Initiate possession handover for confirmed booking | `possession.manage` |
| PATCH | `/possession/handovers/{possessionHandover}/checklist` | `possession.handovers.checklist.update` | Update handover checklist and readiness | `possession.manage` |
| PATCH | `/possession/handovers/{possessionHandover}/complete` | `possession.handovers.complete` | Complete possession after blockers are cleared | `possession.approve` |
| GET | `/possession/snags` | `possession.snags.index` | Handover snag register with status/severity filters | `possession.view`, `possession.manage` or `possession.approve` |
| POST | `/possession/snags` | `possession.snags.store` | Report handover snag against an open handover | `possession.manage` |
| PATCH | `/possession/snags/{handoverSnag}/resolve` | `possession.snags.resolve` | Resolve open handover snag | `possession.manage` |

Implemented controls:

- Company, booking, project, unit and customer scoping
- Possession handover and snag registers reject undocumented query filters while allowing only their documented project, handover, status, severity and bounded pagination filters
- One handover record per confirmed booking
- Financial outstanding calculated from approved collections
- Handover completion blocked by outstanding balance, pending required checklist or open snags
- Checklist updates refresh handover readiness
- Snag reporting blocks handover readiness
- Snag resolution refreshes handover readiness
- Final handover requires possession letter reference
- Completed handover updates unit status to handed_over
- Audit events for handover initiation, checklist update, snag reporting, snag resolution and final completion
- Feature tests covering readiness, blockers, snag lifecycle, completion and partner restrictions

## After-Sales and Maintenance Backend Slice

Authenticated Laravel endpoints now exist for customer service tickets, SLA handling and maintenance work-order execution:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/after-sales/tickets` | `after-sales.tickets.index` | Service ticket register with booking/customer/project/status filters | `after_sales.view`, `after_sales.manage`, `after_sales.approve` or own buyer scope |
| POST | `/after-sales/tickets` | `after-sales.tickets.store` | Raise a service ticket against a confirmed booking | `after_sales.manage` or own buyer scope |
| PATCH | `/after-sales/tickets/{serviceTicket}/assign` | `after-sales.tickets.assign` | Assign ticket to an internal user and record first response | `after_sales.manage` |
| PATCH | `/after-sales/tickets/{serviceTicket}/resolve` | `after-sales.tickets.resolve` | Resolve ticket after active work orders are completed | `after_sales.manage` |
| PATCH | `/after-sales/tickets/{serviceTicket}/close` | `after-sales.tickets.close` | Close resolved ticket with optional customer rating | `after_sales.approve` or own buyer scope |
| GET | `/after-sales/work-orders` | `after-sales.work-orders.index` | Maintenance work-order register | `after_sales.view`, `after_sales.manage` or `after_sales.approve` |
| POST | `/after-sales/work-orders` | `after-sales.work-orders.store` | Create maintenance work order from an active ticket | `after_sales.manage` |
| PATCH | `/after-sales/work-orders/{maintenanceWorkOrder}/complete` | `after-sales.work-orders.complete` | Complete maintenance work order with cost and notes | `after_sales.manage` |

Implemented controls:

- Customer portal linkage through `customers.portal_user_id`
- Buyer users can see and raise tickets only for their own bookings
- Internal users remain company-scoped
- Service ticket and maintenance work-order registers reject undocumented query filters while allowing only their documented project, booking, customer, assignee, status, priority, category, ticket and bounded pagination filters
- Configurable SLA hours from `config/builder360.php`
- Ticket lifecycle: open, assigned, in progress, resolved and closed
- Maintenance work-order lifecycle: planned, scheduled and completed
- Ticket resolution blocked until active work orders are completed
- Customer closure with optional rating
- Audit events for ticket creation, assignment, resolution, closure and work-order creation/completion
- Feature tests covering buyer scope, partner restrictions, SLA configuration, assignment, work orders, resolution blockers and closure

## Governance Reports and Audit Backend Slice

Authenticated Laravel endpoints now exist for management reporting and audit review:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/governance/audit-events` | `governance.audit-events.index` | Paginated audit event trail with event/user/auditable/request/date/search filters | `audit.view` |
| GET | `/governance/management-summary` | `governance.management-summary.show` | Management KPI summary across CRM, sales, collections, inventory, construction, payroll, after-sales and audit | `reports.view` |
| GET | `/governance/report-register` | `governance.report-register.index` | Report rows for bookings, collections, payroll or service tickets with JSON, CSV, Excel-compatible `.xls` and PDF output | `reports.view` |

Implemented controls:

- Company-scoped audit and reporting for non-global users
- Global summary access for users with wildcard permission
- Audit events retain safe request context: method, path, request/correlation ID, user agent and IP address
- Audit filters by event type, user, auditable model/id, request method, request ID, date range and text search
- Audit-event register rejects undocumented query filters while allowing only its documented event, actor, auditable record, request, date, search and pagination filters
- Management KPIs for pipeline, bookings, receivables, collections, inventory status, construction milestones, payroll totals, after-sales SLA state and audit activity
- Report register supports `bookings`, `collections`, `payroll` and `service_tickets`
- Report register date filters are capped by `BUILDER360_REPORT_MAX_DATE_RANGE_DAYS` to prevent unbounded historical exports
- Report register validates status filters against the selected report type and applies payroll date filters to payroll period start dates
- Project filtering is rejected for payroll reports because payroll runs are company-period records, not project records
- CSV, Excel-compatible XML Spreadsheet and text PDF outputs use server-side generated files without adding dependencies
- Spreadsheet exports escape formula-like values to reduce CSV/Excel injection risk
- Partner roles are blocked from governance routes
- Feature tests covering auditor access, scoped summary, JSON report rows, CSV/Excel/PDF export, partner restrictions and validation

## Notification Center Backend Slice

Authenticated Laravel endpoints now exist for user-specific workflow notifications:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/notifications` | `notifications.index` | Paginated current-user notification inbox with status/category/severity/date filters | Authenticated own inbox |
| GET | `/notifications/summary` | `notifications.summary` | Current-user unread/read/archive/category counts | Authenticated own inbox |
| PATCH | `/notifications/read-all` | `notifications.read-all` | Mark all unread notifications for current user as read | Authenticated own inbox |
| PATCH | `/notifications/{userNotification}/read` | `notifications.read` | Mark one owned notification as read | Notification recipient only |
| PATCH | `/notifications/{userNotification}/archive` | `notifications.archive` | Archive one owned notification | Notification recipient only |

Implemented controls:

- Recipient-scoped notification access
- Notification metadata: channel, category, severity, action URL, payload and optional linked workflow record
- Read and archive state tracking
- Unread and critical unread summaries
- Server bootstrap payload for the topbar bell and Notifications page, scoped to the authenticated recipient
- Notification inbox rejects undocumented query filters while allowing only status, category, severity, date and pagination filters
- Seeded workflow notifications for sales, construction and finance demo users
- After-sales ticket assignment and maintenance work-order creation can create actionable notifications
- Feature tests covering inbox filters, summary counts, read/archive actions, mark-all-read, ownership protection and workflow-triggered notification creation

## Collaboration Tasks and Calendar Backend Slice

Authenticated Laravel endpoints now exist for internal task management and calendar scheduling:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/collaboration/tasks` | `collaboration.tasks.index` | Company-scoped task register with status, priority, assignee, project, module and due-date filters | `collaboration.view`, `collaboration.manage` or own self-service scope |
| POST | `/collaboration/tasks` | `collaboration.tasks.store` | Create a task with assignment, checklist, due date and module context | `collaboration.manage` or self-service own task |
| PATCH | `/collaboration/tasks/{workTask}/assign` | `collaboration.tasks.assign` | Reassign an active task to another company user | `collaboration.manage` |
| PATCH | `/collaboration/tasks/{workTask}/status` | `collaboration.tasks.status.update` | Update task lifecycle status with workflow note | Assignee, creator or `collaboration.manage` |
| GET | `/collaboration/calendar-events` | `collaboration.calendar-events.index` | Company-scoped calendar register with type, status, project and date filters | `collaboration.view`, `collaboration.manage` or own self-service scope |
| POST | `/collaboration/calendar-events` | `collaboration.calendar-events.store` | Schedule internal meeting/site visit/interview/follow-up event with attendees and reminders | `collaboration.manage` or private self-service event |
| PATCH | `/collaboration/calendar-events/{calendarEvent}/cancel` | `collaboration.calendar-events.cancel` | Cancel calendar event with reason | Organizer or `collaboration.manage` |
| GET | `/collaboration/messages` | `collaboration.messages.index` | Participant-scoped internal mailbox with inbox/sent/all folders, status, priority, project, thread and search filters | `collaboration.view`, `collaboration.manage` or own self-service scope |
| POST | `/collaboration/messages` | `collaboration.messages.store` | Send an internal mailbox message to one or more active same-company internal users | `collaboration.manage` or self-service sender |
| PATCH | `/collaboration/messages/{collaborationMessage}/read` | `collaboration.messages.read` | Mark a received mailbox message as read | Message recipient only |
| PATCH | `/collaboration/messages/{collaborationMessage}/archive` | `collaboration.messages.archive` | Archive a received mailbox message | Message recipient only |

Implemented controls:

- Normalized task and calendar-event records with company, project, creator/organizer, assignee/attendee and related-record metadata
- Normalized mailbox message records with company, optional project, sender, recipient, thread key, read/archive state and metadata
- Company-scoped access for internal users and self-scoped access for employee self-service users
- Global users must provide explicit `company_id` for private/unassigned task and calendar records when no project, assignee or attendee supplies company context
- Partner and buyer roles blocked from internal collaboration routes, including mailbox
- Task lifecycle: open, in progress, blocked, completed and cancelled
- Task assignment requires same-company assignee
- Self-service users can create only own tasks
- Calendar lifecycle: scheduled and cancelled, with support for future rescheduling/status extension
- Calendar attendee conflict validation across overlapping scheduled/rescheduled events
- Self-service users can create only private self events
- Mailbox messages are private to sender and recipient; read/archive actions are recipient-only
- Mailbox sends reject self-recipient, inactive users, external buyer/partner users and cross-company recipients
- Task, calendar and mailbox registers reject undocumented query filters while allowing only their documented status, priority, assignee, project, module, event-type, folder, thread, date, search and pagination filters
- Assignment, attendee and mailbox notifications through the notification center
- Audit events for task creation, assignment, status updates, calendar scheduling/cancellation and mailbox send/read/archive actions
- Feature tests covering listing, creation, assignment, status updates, notifications, audit events, self-service scope, calendar conflicts, mailbox threading, recipient-only state transitions and partner restrictions

## System Settings Backend Slice

Authenticated Laravel endpoints now exist for configuration-first ERP governance:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/settings/system-settings` | `settings.system-settings.index` | List versioned system settings with group/key/status/scope filters | `settings.view`, `settings.manage` or `settings.approve` |
| POST | `/settings/system-settings` | `settings.system-settings.store` | Create a draft setting version | `settings.manage` |
| PATCH | `/settings/system-settings/{systemSetting}/approve` | `settings.system-settings.approve` | Approve and activate a draft setting version | `settings.approve` |

Implemented controls:

- Versioned settings by scope and key
- Company-scoped and global setting support
- Draft, active and archived lifecycle
- New approved version archives the previous active version for the same scope/key
- Segregation of duties: creator cannot approve the same setting
- Company administrators cannot create settings for another company
- Seeded settings for HR attendance rules, after-sales SLA hours, workflow approval chains and backup/DR metadata
- System-setting register rejects undocumented query filters while allowing only group, key, status, scope and pagination filters
- Audit events for draft creation and approval
- Feature tests covering listing, draft creation, approval/versioning, scope validation, access restrictions and validation errors

## Data Import and Reconciliation Backend Slice

Authenticated Laravel endpoints now exist for controlled CSV import preview and posting:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/settings/data-imports` | `settings.data-imports.index` | List company-scoped import batches with company/type/status filters | `settings.view` or `settings.manage` |
| POST | `/settings/data-imports/preview` | `settings.data-imports.preview` | Upload and preview a CSV import file before posting | `settings.manage` |
| POST | `/settings/data-imports/{dataImportBatch}/post` | `settings.data-imports.post` | Post a clean preview batch to business records | `settings.manage` |

Implemented controls:

- First supported import template: `crm_prospect_inquiries`
- Required CSV header: `project_code,name,email,phone,source,channel,preferred_contact_method,budget_min,budget_max,message,consent_to_contact`
- Preview stores source rows, preview rows, validation errors, checksum, row counts and reconciliation summary before business records are created
- Posting is blocked when preview has invalid rows
- Posted file checksums cannot be posted again for the same company/import type
- Global users must explicitly choose the target company; scoped users import only for their assigned company
- Prospect inquiry posting reuses the CRM prospect inquiry service, so duplicate detection, audit logging and notifications remain consistent
- Import batch lifecycle is auditable through workflow history and audit events
- Partner/buyer users cannot access import routes
- Feature tests cover preview, row-level errors, posting, duplicate checksum protection, company selection and partner restrictions

## Authentication Hardening Slice

The Laravel auth surface now includes native password reset and account-status enforcement:

| Method | Route | Name | Purpose | Access |
| --- | --- | --- | --- | --- |
| GET | `/forgot-password` | `password.request` | Show password reset request form | Guest |
| POST | `/forgot-password` | `password.email` | Send reset link for an active user account | Guest |
| GET | `/reset-password/{token}` | `password.reset` | Show new-password form for reset token | Guest |
| POST | `/reset-password` | `password.store` | Validate token and set new password | Guest |

Implemented controls:

- Uses Laravel's native password broker and `password_reset_tokens` table
- Works with the configured MySQL database and the isolated SQLite test database
- Does not disclose whether an email address is registered
- Sends reset links only for active users
- Applies request throttling to reset-link requests
- Uses the centralized password policy for reset and admin user creation; default policy requires confirmed passwords with at least 10 characters, mixed case, number and symbol
- Rotates the remember token after reset
- Feature tests cover account-enumeration protection, successful reset/login, weak password rejection and invalid token rejection
- Login is restricted to `active` user accounts only
- Existing authenticated sessions are invalidated on the next protected request if the account is changed to `inactive` or `suspended`
- Inactive and suspended users do not receive password reset links
- Protected ERP and CRM routes require verified email addresses
- Seeded demo users are pre-verified for local access
- Admin-created active users receive Laravel email verification notifications
- Unverified users can access only logout and verification routes until the signed verification link is completed
- Security audit events are recorded for successful and failed login attempts, logout, inactive-session revocation, password reset requests/completions and email verification actions
- Auth audit metadata intentionally excludes passwords, reset tokens, signed links and raw unknown-account email addresses
- Central audit logging recursively redacts sensitive metadata keys such as passwords, tokens, API secrets, PAN/Aadhaar identifiers and bank-account identifiers before persistence

## Roles Preserved

The current role switcher behavior is preserved, including:

- Director
- Sales Head
- Construction Head
- Finance Head
- HR Manager
- Buyer
- Employee
- Payroll Admin
- Recruiter
- Auditor
- Compliance Officer
- System Administrator
- Channel Partner
- Executive Partner (Broker)

Server-side seeded users are linked to roles and permission gates. Authorized role preview, project context and period context use server forms and session-backed Laravel context. All active business workspaces are server-rendered Blade pages protected by policies, Form Requests and company/project scopes.

Admin user and role registers are server-authorized and reject undocumented query filters while allowing only their documented company, role, status, scope, active flag, search and pagination filters.

## Production Readiness Boundary

The application includes Laravel authentication, password reset, email verification, account-status enforcement, role/access gates, form-request authorization, policies, activity events with central sensitive-metadata redaction, security headers, rate limits, role-aware dashboard data, managed-document download controls, operational readiness checks and MySQL local setup.

Before a live client deployment, the following environment-specific work must still be completed and signed off:

- Configure the approved production MySQL or MariaDB database, then run `php artisan migrate --force` against that environment.
- Configure production mail, queue, cache, session, filesystem, logging and backup infrastructure.
- Set `APP_ENV=production`, `APP_DEBUG=false`, a production `APP_KEY`, HTTPS `APP_URL`, encrypted sessions, secure and HttpOnly session cookies, and HSTS-capable TLS termination.
- Set a deployment-specific `BUILDER360_DEMO_PASSWORD` only if demo users are intentionally seeded; otherwise remove, rotate or disable demo accounts before go-live.
- Configure real external adapters for email, SMS, WhatsApp, banking, biometric devices, statutory portals and backup/DR tooling where those integrations are in scope.
- Validate GST, payroll, HR, RERA, labour-law and tax configurations with the client-appointed statutory/legal expert before operational use.
- Run production smoke checks: `/health`, authenticated `/operations/readiness`, login, role-scoped dashboards, critical workflow creation/approval and document download.
- Run queue workers, scheduler and log monitoring under the chosen process manager.
- Execute client UAT, data reconciliation and final acceptance before live transaction processing.

All active browser pages use server-rendered Blade workspaces and Vite production assets. Dormant historical frontend sources are not referenced by routes, views or deployment commands.

## Deployment Notes

For production deployment:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan builder360:reconcile --json
php artisan builder360:verify --json
```

Set production `.env` values for:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL`
- database connection
- cache driver
- queue connection
- mail settings
- filesystem settings
- session encryption, secure-cookie and HttpOnly-cookie settings
- security-header and rate-limit settings
- `BUILDER360_DEMO_PASSWORD` only when intentional demo seeding is approved

Operational commands after deployment:

```bash
php artisan queue:work --tries=3
php artisan schedule:run
```

Use the deployment process manager to keep queue workers alive and run the scheduler every minute. Run the authenticated readiness route after deployment and after any configuration change:

```text
GET /operations/readiness
```

No credentials or environment-specific secrets are hardcoded in this conversion.
# brij365
