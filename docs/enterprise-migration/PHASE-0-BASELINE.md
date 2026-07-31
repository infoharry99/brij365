# Builder360 Enterprise Blade Migration — Phase 0 Baseline

Baseline date: 2026-07-11  
Target: single-company, multi-project Builder360 ERP–CRM  
Architecture authority: `laravel-blade-enterprise-architect-context-aware-deterministic`

## Authoritative inputs

- Laravel application: `C:\Users\Mataji\Desktop\Codex1\Builder360\laravel-builder360`
- Approved UI-only reference: `C:\Users\Mataji\Desktop\Codex1\Builder360\Builder360 ERP (Standalone) (1).html`
- Approved UI SHA-256: `E6A3AD4B5B501C4626BEAAB21CD98C85C6FED62B79326BA555A1A62E91EFA1B4`
- Persistent goal source: `C:\Users\Mataji\.codex\attachments\dd229b5c-903a-4ddd-a082-6dcde3b125d2\pasted-text-1.txt`

The standalone HTML is a visual and interaction reference only. Its React code, hash router, browser-side data, mock records, localStorage behavior, and client authorization are not approved production architecture.

## Proven runtime baseline

| Item | Evidence | Result |
|---|---|---|
| Framework | `php artisan about` | Laravel 12.62.0 |
| PHP | `php artisan about` | PHP 8.2.12 |
| Database | `php artisan about`, `builder360:verify` | MySQL, healthy |
| Cache | `php artisan about` | Database |
| Queue | `php artisan about` | Database |
| Session | `php artisan about` | Database |
| Broadcasting | `php artisan about` | Reverb |
| Routes | `builder360:verify --json` | 355 |
| Application PHP files | filesystem inventory | 729 |
| Blade views | filesystem inventory | 60 |
| Migrations | filesystem inventory | 62 |
| Test files | filesystem inventory | 75 |
| Regression suite | `php artisan test` | 683 passed, 15,871 assertions |
| Regression duration | `php artisan test` | 281.94 seconds |
| Readiness verifier | `php artisan builder360:verify --json` | `status: ok` |

## Current architecture

The current application is a substantial Laravel/MySQL application with broad domain coverage, policy-aware routes, Form Requests, services, audit logging, notifications, exports, Reverb support, and server-rendered Blade workspaces. Its backend regression baseline is strong.

The current UI shell does **not** yet satisfy the target architecture:

1. `package.json`, `vite.config.*`, and `tailwind.config.*` are absent.
2. `resources/js/app.js` imports only `bootstrap.js`.
3. `resources/views/layouts/builder360-classic.blade.php` loads Bootstrap and Font Awesome from CDNs.
4. The same layout loads direct public assets: `public/css/builder360-classic.css` and `public/js/builder360-classic.js`.
5. The layout resolves role, project, and dashboard context by calling `Builder360Bootstrap` directly inside Blade.
6. The layout passes a broad global associative-array payload instead of an immutable application-shell DTO.
7. Reusable Blade components are not yet the primary UI composition model.
8. Dormant React sources remain at `resources/js/app.jsx` and `resources/js/legacy/*`.
9. Existing `ClassicMvcDashboardTest` assertions explicitly require the current non-Vite classic shell and must be migrated when the replacement shell is introduced.
10. The application verifier currently reports `classic_assets: present`; it does not prove Vite/Tailwind/Alpine readiness.

## Operational discrepancies

| Requirement | Current evidence | Gap |
|---|---|---|
| Local canonical URL `127.0.0.1:8001` | `php artisan about` reports `127.0.0.1:8000` | Local environment and documentation are inconsistent with the goal |
| Storage link | `php artisan about` reports `public/storage NOT LINKED` | Must be reconciled before upload/download browser acceptance |
| Single-company operation | Schema, seeder, policies, and company selectors are multi-company capable | A non-destructive active-company resolver and UI suppression are required |
| Vite/Tailwind/Alpine | No frontend manifest/configuration exists | Foundation is missing |
| Approved UI parity | Current classic layout and direct assets differ from target reference | Component and shell migration required |
| No React production dependency | Classic routes do not mount React, but legacy source remains | Production exclusion is partly true; source retirement remains |
| Immutable page DTOs | Global `Builder360Bootstrap` arrays dominate shell/page contracts | Domain and page DTO migration required |
| Business copy only | Tests cover many Blade pages, but technical strings still exist in legacy source/bootstrap | User-visible copy must be audited by rendered route |
| Scoring Logic module | Lead rules exist in system settings; other scores are distributed | Dedicated versioned scoring module is absent |

## Functional baseline by domain

The regression suite proves substantial existing behavior in the following areas. “Proven” means covered by current tests; it does not imply approved-UI parity or full final-scope completion.

| Domain | Current proven baseline | Target delta |
|---|---|---|
| Authentication | login, logout, reset, verification, account status, audit | approved shell/profile integration and target copy |
| Administration | users, roles, companies, settings, imports | single-company UX, reusable components, scoring permissions |
| Dashboards | normalized role dashboard contracts | server-rendered target UI, accurate drilldowns, period/project reconciliation |
| Approvals | scoped data, filters, export | approved target UI and complete source coverage audit |
| Notifications | recipient scope and actions | target UI, global badge synchronization audit |
| Tasks/Calendar | CRUD/workflows, notifications, audit, exports | target UI, collapsible/full-screen behaviors, DTO migration |
| Chat Connect | role access, files, voice, polls, reply/forward integrity | target UI, realtime browser verification, final copy and accessibility |
| Mailbox | inbox/sent/scheduled/read/archive/export/thread behavior | target UI and internal-mailbox boundary confirmation |
| CRM | leads, qualification, scoring rules, inquiries, activities, site visits | approved UI, removal of heuristic score ambiguity, score history |
| Projects/Inventory | projects, unit pricing, cost/ROI exports | target UI and project-health calculator extraction |
| Construction/Procurement | core workflows, vendor performance data | target UI, scorecard/versioned scoring integration |
| HR | employee master, attendance, operations, confirmation, compliance | performance Blade workspace and deterministic scoring engine |
| Payroll/Recruitment | existing workflows and policies | target UI, interview scorecard and performance integration |
| Finance | collections, reports, exports and policies | target UI and full end-to-end reconciliation |
| Documents/After-sales | secure documents, tickets/work orders, buyer scope | target UI, CSAT scoring and analytics |
| Portals | buyer and partner scope tests | approved target UI and full direct-route audit |

## Scoring baseline and verified gaps

### Lead quality

- Existing service: `App\Services\Crm\LeadQualityScoreService`
- Existing setting key: `crm.lead_quality_score.rules`
- Existing formula: normalized configured criterion points to a 0–100 score.
- Existing default: Budget, Authority, Need, Timeline, each 25 points.
- Existing bands: Hot 75+, Warm 50+, Cold 25+, Disqualified Fit 0+.
- Gap: dashboard bootstrap contains a separate stage/status heuristic fallback score, which can be mistaken for an authoritative qualification score.

### Employee performance

- Existing cycles, self score, manager score, final score, rating scale, passing score, KPI weight validation, and PIP threshold.
- Existing default rule metadata includes KPI 70%, KRA 30%, PIP threshold 2.5.
- Gap: the configured KPI/KRA percentages do not currently calculate the final score. Manager and HR scores are manually submitted.
- Gap: the performance GET routes return resources rather than a complete discoverable Blade workspace.

### Other scoring

- Confirmation stores four manual 1–5 components without an aggregate formula.
- Recruitment averages panel ratings but lacks a configurable competency scorecard.
- Vendor rating averages available acceptance and on-time rates and converts to a 0–5 score, but lacks a complete Blade scorecard and versioned rules.
- Project health is calculated in `ProjectController` export logic instead of a dedicated calculator.
- Customer tickets capture a 1–5 rating but lack governed CSAT aggregation.
- Exit interview summaries average category ratings but are not a versioned scoring model.

## Risks requiring explicit control

1. **No Git metadata in the supplied workspace.** Structural changes lack repository-native rollback evidence. Before each migration phase, maintain file-level backups or an equivalent user-approved rollback mechanism.
2. **Large regression surface.** The full suite takes about 4.7 minutes; targeted tests should run during development and the full suite at phase gates.
3. **Tests encode old architecture.** Passing tests that require “classic/no Vite” contradict the target and must be intentionally replaced with equivalent target-architecture assertions.
4. **Global bootstrap coupling.** Replacing it all at once is high risk. Migrate shell/page contracts incrementally through immutable DTOs.
5. **Visual reference is a bundled React artifact.** Extract design tokens and behavior specifications only; do not reuse client business logic.
6. **Single-company transition.** Do not delete company columns or historical companies. Introduce an explicit resolver, fail-closed scope, and migration/operational policy.
7. **Scoring changes affect decisions.** Rules require versioning, approval, impact preview, immutable finalized snapshots, and recalculation policy.

## Phase 0 conclusion

The backend is sufficiently healthy to support an incremental enterprise Blade migration. The target is not achieved: frontend foundation, component architecture, single-company operating layer, approved shell, scoring module, and final module parity remain incomplete.

Phase 1 may begin only after the companion traceability map is accepted as the implementation baseline.
