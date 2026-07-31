# Builder360 Webuzo Ubuntu Deployment and Rollback

This runbook deploys the accepted single-company, multi-project Builder360 release after local UAT. It does not authorize changing a live VPS; deployment requires a separate approved maintenance window.

## 1. Release contents

Package the Laravel project with:

- `app`, `bootstrap`, `config`, `database`, `public`, `resources`, `routes`, `storage`, `vendor` (or install it on the server)
- `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `artisan`, `.env.example`
- generated `public/build` Vite assets
- `deployment/webuzo/.env.production.example`
- queue, scheduler and Reverb templates under `deployment/webuzo`

Never package `.env`, logs, sessions, caches, private documents, database dumps, credentials, or local test artifacts.

## 2. Server prerequisites

- Ubuntu supported by the installed Webuzo release
- PHP 8.2 or newer with PDO MySQL, mbstring, OpenSSL, tokenizer, XML, cURL, fileinfo and ZIP
- MySQL/MariaDB using `utf8mb4`
- Composer 2
- Node.js compatible with `package-lock.json` only when building assets on the server
- HTTPS certificate for the production hostname
- A process manager capable of keeping the queue worker and Reverb alive
- Cron access for Laravel Scheduler

## 3. Document root and permissions

The website document root must point to Laravel's `public` directory, for example:

```text
/home/WEBUZO_USER/public_html/builder360/public
```

The web server must not expose the project root, `.env`, `storage`, `vendor`, database dumps, or deployment templates.

```bash
cd /home/WEBUZO_USER/public_html/builder360
chown -R WEBUZO_USER:WEBUZO_USER storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

Do not use global `777` permissions.

## 4. Database and backup gate

Before deploying:

1. Create a transactionally consistent MySQL backup using Webuzo backup tooling or the approved database provider.
2. Record backup filename, SHA-256 checksum, creation time, database name, release version and operator.
3. Confirm the backup is stored outside the web root.
4. Restore the backup into an isolated database and run application smoke checks.
5. Set the `BUILDER360_EXTERNAL_DB_BACKUP_*` values only after the restore test is evidenced.

Example manual backup when the hosting policy permits `mysqldump`:

```bash
umask 077
mysqldump --single-transaction --routines --triggers --events builder360 | gzip > /secure-backups/builder360-predeploy-YYYYMMDD-HHMM.sql.gz
sha256sum /secure-backups/builder360-predeploy-YYYYMMDD-HHMM.sql.gz > /secure-backups/builder360-predeploy-YYYYMMDD-HHMM.sha256
```

Never place the backup under `public` or commit it to source control.

## 5. Environment setup

Copy `deployment/webuzo/.env.production.example` to `.env`, replace every placeholder, and generate a unique key only for a new installation:

```bash
cp deployment/webuzo/.env.production.example .env
php artisan key:generate
```

For an existing installation, preserve the existing `APP_KEY`; changing it invalidates encrypted sessions and encrypted application data.

Required production conditions:

- `APP_ENV=production`
- `APP_DEBUG=false`
- HTTPS `APP_URL`
- `DB_CONNECTION=mysql`
- database cache, queue and sessions
- encrypted, secure, HttpOnly session cookies
- approved SMTP credentials and from-address
- non-prototype payment adapter before live payment processing
- unique Reverb credentials and exact allowed origin
- no demo password unless approved demo accounts are intentionally required

## 6. Install and build

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan storage:link
```

If `public/build` is produced by CI and included in the package, Node.js is not required on the VPS. Verify the manifest and assets before continuing.

## 7. Maintenance-window deployment

```bash
php artisan down --render="errors::503"
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan builder360:reconcile --json
php artisan builder360:verify --json
php artisan queue:restart
php artisan up
```

Do not run `db:seed` in production unless the client-approved data plan explicitly requires it and `BUILDER360_DEMO_PASSWORD` is intentionally set.

## 8. Queue, scheduler and Reverb

- Install `deployment/webuzo/builder360-queue.service.example` as a systemd service or translate it into the Webuzo process manager.
- Install `deployment/webuzo/builder360-reverb.service.example` when Chat Connect live updates are enabled.
- Install `deployment/webuzo/builder360-scheduler.cron.example` for the Webuzo account.
- Replace `WEBUZO_USER` and paths before installation.
- Proxy the public WebSocket connection through HTTPS to `127.0.0.1:8080`; never expose the internal Reverb port without firewall and origin controls.

Validate:

```bash
systemctl status builder360-queue
systemctl status builder360-reverb
php artisan schedule:list
php artisan queue:monitor database:default --max=1000
```

## 9. Post-deployment verification

1. `GET /health` returns a safe `ok` response.
2. Authorized Director/System Administrator/Auditor opens `/operations/readiness` and all production checks pass.
3. `php artisan builder360:reconcile --json` reports `status: ok`.
4. Login, logout, password recovery and profile work over HTTPS.
5. Director, Employee, Buyer and Partner navigation remains role-correct.
6. Selected project changes dashboard data without exposing another project.
7. A controlled create/approve workflow records activity history and notification evidence.
8. A permitted private document downloads; an unauthorized user receives 403/404.
9. Report export matches its on-screen filters.
10. Chat message delivery works through Reverb and continues through polling if Reverb is stopped.
11. Browser console has no critical errors at desktop and mobile widths.

## 10. Rollback

Rollback is release-based and database-safe:

1. Put the application in maintenance mode.
2. Stop queue and Reverb workers so they cannot write during rollback.
3. Point the website/release symlink to the last accepted application release.
4. Restore the pre-deployment MySQL backup if the new migration or application wrote incompatible data.
5. Restore the prior `.env` without changing its `APP_KEY`.
6. Run `php artisan optimize:clear`, then rebuild config, route and view caches.
7. Restart queue and Reverb processes.
8. Run health, readiness and reconciliation checks before reopening traffic.

Do not use `migrate:rollback` as the only data rollback mechanism for a production incident. Some security/data migrations intentionally avoid unsafe automatic reactivation; the verified database backup is the authoritative rollback path.

## 11. Final sign-off

Production processing may start only after:

- local UAT is signed
- role and project access is signed
- imported client totals reconcile
- statutory/legal/payroll/GST/RERA settings are approved by the client's appointed expert
- backup restore evidence is accepted
- no critical defect remains
- the deployment checklist is signed by the client and service provider
