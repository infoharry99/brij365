# Builder360 ERP-CRM Local Hosting and Handover Guide

## Local Hosting

Run from the Laravel project folder:

```bash
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open:

```text
http://127.0.0.1:8000
```

## MySQL Delivery Settings

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=builder360
DB_USERNAME=root
DB_PASSWORD=
DB_FOREIGN_KEYS=true
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
MAIL_MAILER=log
```

## Production-like Local Check

For a stricter local readiness run:

```env
APP_DEBUG=false
FILESYSTEM_LOCAL_SERVE=false
```

Then run:

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan builder360:verify --json
php artisan test
```

## Required Operational Commands

| Command | Purpose |
| --- | --- |
| `collaboration:release-scheduled-messages` | Releases scheduled collaboration/mailbox messages. |
| `builder360:sqlite-backup` | Legacy SQLite-only backup utility; not used for the MySQL Phase 1A local delivery. |
| `builder360:sqlite-backup-verify` | Legacy SQLite-only verification utility; not used for the MySQL Phase 1A local delivery. |
| `builder360:verify --json` | Reports application readiness and module artifacts. |

## Handover Pack

| Artifact | Location |
| --- | --- |
| SOW completion matrix | `docs/SOW_COMPLETION_MATRIX.md` |
| Role and permission matrix | `docs/ROLE_PERMISSION_MATRIX.md` |
| Workflow and settings catalogue | `docs/WORKFLOW_AND_SETTINGS_CATALOGUE.md` |
| UAT checklist | `docs/UAT_ACCEPTANCE_CHECKLIST.md` |
| Local hosting guide | `docs/LOCAL_HOSTING_AND_HANDOVER.md` |
| Webuzo Ubuntu deployment and rollback | `docs/WEBUZO_UBUNTU_DEPLOYMENT.md` |
| Production environment template | `deployment/webuzo/.env.production.example` |
| Queue/Reverb/Scheduler templates | `deployment/webuzo/` |

## Final Local Verification

```bash
php artisan migrate:status
php artisan builder360:reconcile --json
php artisan builder360:verify --json
php artisan schedule:list
php artisan test
npm run build
```

`builder360:reconcile` is read-only. It confirms the configured company, active-company count, company/project reference integrity, active scoring-rule uniqueness and failed queue state before handover.

## Client Validation Required

The application contains configurable records for statutory, payroll, tax, GST, RERA, labour-law and legal controls. These values must be reviewed and approved by the client or appointed expert before production reliance.

## Live Integration Readiness

The local delivery uses configurable adapter/readiness records for external systems. Live production integration requires separate vendor choices, credentials, commercial subscriptions and acceptance testing.
