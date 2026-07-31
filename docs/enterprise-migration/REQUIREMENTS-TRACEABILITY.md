# Builder360 Target Requirements Traceability

Status vocabulary:

- **Proven** — authoritative current evidence satisfies the requirement.
- **Partial** — working capability exists but target architecture/UI/scope is incomplete.
- **Missing** — required implementation is absent.
- **Unverified** — evidence has not yet been gathered at the required scope.

## Architecture and platform

| Requirement | Current status | Authoritative evidence | Planned phase |
|---|---|---|---|
| Laravel 12 / PHP 8.2 | Proven | `php artisan about` | Maintain |
| MySQL authoritative data source | Proven for current runtime | `php artisan about`, `builder360:verify` | Reconcile every module at its phase |
| Server-rendered Blade pages | Partial | 60 Blade views and browser tests | Phases 3–15 |
| Vite | Proven foundation | production build and manifest are generated | Phase 2 |
| Tailwind CSS | Proven foundation | Vite builds theme/utilities and approved compatibility CSS without Bootstrap or direct asset loading | Phase 2 complete |
| Alpine local UI state | Proven foundation | CSP-compatible Alpine controls shell-local interactions | Phase 2 complete |
| No React production UI | Partial | classic routes do not mount React; legacy JSX remains | Phases 2–4 and retirement gate |
| Reusable Blade components | Proven foundation | approved semantic UI/form/overlay/tab/responsive-register catalogue with feature tests | Phase 3 complete |
| Immutable page DTOs | Missing/partial | broad associative bootstrap arrays | Phases 3–15 |
| Thin controllers and use-case Actions | Partial | services/Form Requests exist; architecture is inconsistent | Each domain phase |
| Reverb only for approved realtime | Proven | Reverb server dependency, Echo client, private channels, broadcast events, polling fallback and Phase 8 runtime verification | Phase 8 complete |

## Core operating model

| Requirement | Current status | Evidence/gap | Planned phase |
|---|---|---|---|
| One configured company | Proven | configured active-company resolver, middleware, policies, views and tests | Phase 1 complete |
| Preserve `company_id` isolation | Proven baseline | broad company-scope tests | Phase 1 regression gate |
| Multiple projects | Proven baseline | project models/routes/tests | Maintain |
| All Projects within active company | Partial | project context exists; single-company resolver absent | Phases 1 and 4 |
| Role-based access | Proven baseline | policy and feature tests | Every phase regression |
| Approved responsive shell | Proven | responsive Blade shell, immutable shell DTOs, global controls and browser evidence | Phase 4 complete |
| Canonical local URL `:8001` | Proven | `.env` and `.env.example` use `http://127.0.0.1:8001` | Phase 1 complete |

## UI and global utilities

| Area | Status | Required completion | Phase |
|---|---|---|---|
| Authentication/Profile | Proven | approved components, immutable DTOs, account/security/activity presentation | 4 complete |
| Sidebar/Topbar | Proven | server navigation, icons, search, contexts, theme, notifications, profile/logout and responsive evidence | 4 complete |
| Role dashboards | Proven | focused server-rendered architecture, role/project/period context, accurate role data and permission-safe drilldowns | 6 complete |
| Notifications | Proven | recipient-scoped Blade inbox, summaries, filters, read/archive actions and responsive evidence | 6 complete |
| Approval Center | Proven | scoped authoritative metrics, filters, source actions, export and restricted-role behavior | 6 complete |
| Reports | Proven for register/export | native Blade register and JSON/CSV/Excel/PDF parity; pin/schedule administration remains Phase 15 | 6 complete / 15 |
| User-facing technical copy removal | Partial | rendered-route audit required | Each phase |

## Scoring Logic

| Requirement | Status | Required completion | Phase |
|---|---|---|---|
| Separate Scoring Logic navigation | Proven | authorized server-rendered module with eleven required destinations | 5 complete |
| Lead rule editing | Proven | active central rule controls condition options, weights, bands, qualification calculation, snapshots and Blade presentation | 5 complete |
| Employee weighted calculation | Proven | deterministic weighted adapter, immediate manager-submit refresh, breakdown and immutable snapshots; HR register presentation follows its module conversion | 5/12 |
| Confirmation scoring | Proven | configurable adapter, immediate recommendation refresh, bands and evidence preserve the independent HR decision | 5/12 |
| Recruitment scorecard | Proven | weighted panel adapter, evidence readiness, immediate snapshot and native interview-register presentation | 5 complete |
| Vendor scoring | Proven | policy-controlled evidence entry, current snapshot, rule history and native Vendor Master presentation | 5 complete |
| Project health scoring | Proven | structured evidence, immediate calculation, snapshot presentation, snapshot-based export and snapshot-only dashboard metric | 5 complete |
| CSAT scoring | Proven | project aggregate adapter, sample enforcement and ticket-close refresh preserve the customer rating | 5/13 |
| Exit feedback scoring | Proven | department aggregate adapter, sample enforcement and submission refresh preserve confidential source responses; HR presentation follows its module conversion | 5/12 |
| Draft/edit/approve/activate/rollback | Proven | structured edit, clone, validate, submit, approve, reject, activate/schedule, rollback-draft, retire, compare, inspection and export proven | 5 |
| Score snapshots/rule version | Proven | resolver, deterministic calculator, immutable snapshot writer, replacement chain, rule evidence and manual override proven across eight adapters | 5 |
| Impact preview/recalculation | Proven | eight source adapters, preview, queued runs, progress/failures, completion notification and scheduled activation proven | 5 |

## Domain execution map

| Domain | Primary current Blade/UI sources | Primary backend families | Target phase |
|---|---|---|---|
| Tasks | Proven — focused Blade read DTO and Action-based mutation boundaries | `/collaboration/tasks*` | 7 complete |
| Calendar | Proven — focused Blade read DTO and Action-based lifecycle boundaries | `/collaboration/calendar-events*` | 7 complete |
| Chat | Proven — focused Blade DTO/Action architecture with private Reverb updates | `/collaboration/chat*` | 8 complete |
| Mailbox | Proven — focused Blade DTO/Action architecture with scoped lifecycle/export | `/collaboration/messages*` | 8 complete |
| CRM | Proven — native lead, qualification, scoring, follow-up, site visit, inquiry, marketing, activity, funnel, conversion, team-performance and booking workspaces with focused Actions/DTOs | `/crm/*`, `/sales/*` | 9 complete |
| Projects/Inventory | Proven — native project master, hierarchy attributes, team, health scoring, unit availability, pricing, cost/ROI and scoped export workspaces with focused Actions/DTOs | `/projects/*`, `/inventory/*` | 10 complete |
| Construction/Procurement | Proven — native planning, progress, materials, stores, transfers, requisitions, comparison, purchasing, receipts, vendor scoring, BOQ, measurements, bills and approval workflows with focused Actions/DTOs | `/construction/*`, `/procurement/*` | 11 complete |
| HR | `hr/*` | `/hr/*` | 12 |
| Payroll | `payroll/workspace/*` | `/payroll/*` | 12 |
| Recruitment | `recruitment/workspace/*` | `/recruitment/*` | 12 |
| Finance | `finance/*` | `/finance/*` | 13 |
| After-sales | `after-sales/*` | `/after-sales/*` | 13 |
| Documents | `documents/*` | `/documents/*` | 13 |
| Legal/RERA | `legal/*` | `/legal/*` | 13 |
| Possession/Maintenance | `possession/*`, `maintenance/*` | respective web routes | 13 |
| Buyer/Partner portals | `buyer/*`, `partner/*` | portal route groups | 14 |
| Administration/Settings | `admin/*`, `settings/*` | admin/settings route groups | 15 |

## Acceptance evidence required for every phase

1. Approved UI reference comparison at desktop, laptop, tablet, and mobile widths.
2. Named route and browser render evidence.
3. Form Request validation evidence.
4. Policy/direct-route denial evidence.
5. MySQL query/source reconciliation for displayed metrics.
6. Relevant targeted tests.
7. Full regression suite at the phase gate.
8. Vite production build after Phase 2.
9. Browser console and keyboard-accessibility checks.
10. Rollback note for the phase.

## Baseline commands

```text
php artisan about
php artisan builder360:verify --json
php artisan test
```

Baseline result: 683 tests passed with 15,871 assertions. Phase 4 gate: 706 tests passed with 16,026 assertions. Phase 7 gate: 737 tests passed with 16,404 assertions. Phase 8 gate: 740 tests passed with 16,441 assertions. Phase 9 gate: 749 tests passed with 16,521 assertions. Phase 10 gate: 751 tests passed with 16,540 assertions. Phase 11 gate: 755 tests passed with 16,570 assertions. These are regression floors, not evidence that the final target has been achieved.
