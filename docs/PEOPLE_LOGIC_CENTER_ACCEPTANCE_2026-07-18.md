# People Logic Center and HRMS Acceptance Record

## Scope

This record captures repository implementation evidence for the governed People Logic Center, performance scoring, statutory payroll, attendance rosters, and People workspace on 18 July 2026. Repository implementation is code-complete for the stated contracts; production authority remains subject to the external gates in this record. Limited authenticated role/access and responsive browser checks passed only for the routes, roles, and dimensions explicitly recorded below. Native browser zoom, full keyboard and assistive-technology coverage, complete role-by-screen visual acceptance, operational validation, legal approval, and client acceptance remain pending and are not implied by the automated suite.

The application remains a server-rendered Laravel Blade application. Laravel policies, Form Requests, Actions, immutable data objects, domain services, and persisted records remain authoritative. Alpine is used only for local presentation state. One-company mode remains active.

## Implemented governed capabilities

### People Logic Center

The existing **Scoring Logic** navigation item now provides role-filtered access to:

- Overview.
- Business Scoring.
- Employee Performance.
- Statutory & Payroll Rules.
- Attendance & Roster Rules.
- Simulation & Impact.
- Versions & Audit.

Sections and operations are filtered by the same server-side access authority used by direct requests. System administration does not automatically grant access to HR or sensitive payroll records.

### Performance scoring

- Version-pinned scoring rules and checksums.
- Canonical checksum validation across lifecycle transitions, active-rule resolution, calculation, scheduled activation, and audit.
- Deterministic effective-slot ordering, overlap protection, validated cache warming, and fail-closed scheduled activation.
- Criterion normalization, weighted calculation, score bands, trace, and reproducible snapshots.
- Corrected score-range and rating-band mapping with explicit inclusive boundary handling.
- Configured rounding method and precision are applied deterministically and covered by boundary tests.
- A typed performance-source registry is the allow-list for supported evidence sources and their canonical input mapping.
- Server-derived finalized-attendance evidence.
- Governed override request and independent decision workflow.
- Non-mutating performance simulations.
- Historical/manual-review compatibility without fabricating missing evidence.
- Append-only score snapshots with controlled current-to-historical transitions and restrictive foreign keys for pinned review/override evidence.
- Stable per-rule/subject persistence mutexes that serialize generic first snapshot writes and preserve exactly one current snapshot under concurrency.
- Structured, non-sensitive audit and log evidence for each failed scheduled activation while later eligible rules continue processing.

### Statutory payroll

- Typed, effective-dated statutory rule packs.
- Independent verification evidence and maker-checker activation controls.
- Deterministic minor-unit monetary calculations.
- Legacy, hybrid, and governed-required cutover modes.
- Version-pinned payroll calculation provenance and traces.
- Non-mutating statutory simulations.
- Governed tax-document snapshot generation.
- Governed employee tax profiles with draft, submit, independent verify, lock, and superseding-amendment states.
- Canonical profile checksums containing immutable proof snapshots and explicit payroll/compliance authorization at domain and file boundaries.
- Approved payroll runs and their employee items reject ordinary Eloquent update and delete operations; post-approval corrections remain an external adjustment/reversal workflow decision.

No prototype or unverified numeric statutory rate is treated as payroll-authoritative.

### Rosters and attendance

- Dated rosters and entries with draft, published, locked, and cancelled states.
- Effective-dated roster rule packs and pinned rule provenance.
- Deterministic rotation preview/materialization and split-shift support.
- Typed attendance and roster rule-pack editors create normalized, company-scoped governed drafts instead of accepting arbitrary JSON as the primary UI contract.
- Generic System Settings approval revalidates and canonicalizes attendance/roster packs before activation, including legacy aliases; malformed drafts fail closed.
- Rotation-pattern editing supports a dynamic cycle length of 1–31 days while retaining server-side validation as the authority.
- Publication validation for overlap, rest, coverage, active users, and active shifts.
- Swap requests and decisions.
- Immutable attendance source events and server-derived daily attendance materialization.
- Fail-closed attendance-period finalization reconciles every employee/date and blocks unresolved schedule, attendance, leave, holiday, weekly-off, or explicit-absence evidence.
- Finalized attendance is the only governed payable-day evidence path.
- Deterministic non-mutating roster impact simulation with authoritative-resolution and ambiguity evidence.

## Automated evidence

The final integrated regression gate after the attendance/roster editor, performance-contract, finalization, and approved-payroll immutability pass:

- `php artisan test`: **1,067 passed**, **22,442 assertions**, zero failures.
- `npm run build`: final Vite production build passed.
- Blade view compilation: final Blade view cache passed.
- Migration status: all migrations report `Ran`, including `2026_07_18_001250_enforce_score_snapshot_immutability`.
- Changed PHP syntax audit: 147 files inspected, zero syntax failures.

Focused integrity evidence included:

- Scoring, Logic Center, and roster simulation gate: 73 passed, 625 assertions.
- Performance/scoring append-only and governance gate: 46 passed, 503 assertions.
- Payroll, employee tax, and attendance gate: 57 passed, 537 assertions.
- `PeopleWorkspaceCompletionTest`: 9 passed, 128 assertions.
- `ShellInteractionSourceTest`: 5 passed, 90 assertions.
- `ScoringSchedulerAndSnapshotIntegrityTest`: 2 passed, 15 assertions.
- `ScoringRuleIntegrityTest`: 7 passed, 42 assertions.
- `PerformanceScoringGovernanceTest`: 20 passed, 189 assertions.
- Additional final coverage includes the performance scoring rule contract, typed Logic Center attendance/roster drafts and generic approval revalidation, attendance finalization reconciliation, dynamic rotation-pattern editing, and approved-payroll Eloquent immutability.

## Authenticated browser acceptance — exercised evidence and pending manual gates

The following checks used the normal local login form and seeded local demo accounts. No middleware, policy, or authorization bypass was used. `Allowed` means the authenticated route rendered successfully; `403` records an expected policy boundary, not a failure.

### Role and access-boundary matrix

| Role | Route / journey | Expected access | Observed result | Status | Evidence / notes |
|---|---|---|---|---|---|
| Director | `/scoring`, `/hr/employees`; sensitive payroll and self-service boundaries | Logic Center and Employee Master allowed; tax profiles denied | `/scoring` allowed; `/hr/employees` allowed; `/payroll/employee-tax-profiles` returned 403; `/hr/employees/me` returned 404 | Pass with seed-data limitation | The seeded Director has no linked Employee record, so the self-service route cannot render an Employee profile. |
| HR Manager | Employee Performance, Attendance & Roster Rules, Simulation & Impact, statutory view, Employee Master | Operational HR views allowed; tax profiles denied | Performance, roster, simulation, statutory, and `/hr/employees` allowed; tax profiles returned 403 | Pass | Sensitive payroll boundary remained enforced. |
| Payroll Admin | Statutory & Payroll Rules, authorized simulation, employee tax profiles; performance boundary | Payroll/statutory views allowed; performance denied | Statutory, simulation, and tax profiles allowed; performance returned 403 | Pass | — |
| Compliance Officer | Statutory verification, audit, tax profiles; HR performance/roster/simulation boundaries | Compliance evidence allowed; unrelated operational views denied | Statutory, audit, and tax profiles allowed; performance, roster, and simulation returned 403 | Pass | — |
| Auditor | Immutable read-only versions and calculation traces | Audit allowed; operational/scoring mutations and sensitive sections denied | Audit allowed; statutory, performance, roster, and simulation returned 403 | Pass | — |
| Employee | Own self-service and tax inputs; administrative workspaces denied | Own records allowed; scoring and Employee Master denied | `/hr/employees/me` and own tax inputs allowed; `/scoring` and `/hr/employees` returned 403 | Pass | — |
| System Administrator | Technical governance without automatic employee or sensitive-payroll access | Technical scoring governance allowed; Employee Master and tax profiles denied | Performance and statutory allowed; Employee Master and tax profiles returned 403 | Pass | Confirms System Administrator does not inherit sensitive People/payroll access automatically. |

### Responsive and visual matrix

Employee Master was exercised at every listed viewport. Logic Center overview, performance, statutory, roster, simulation, and audit were additionally exercised at 1024×768 and 390×844. A final HR Manager pass exercised the permission-aware Attendance & Roster editor and Employee Master at 1440×900 and 390×844, including a light-to-dark-to-light theme cycle. Global-sidebar variants were not separately recorded for every viewport. These checks do not constitute complete cross-browser, native-zoom, keyboard, or assistive-technology acceptance.

| Viewport | Authenticated role | Route / screen | Theme | Sidebar | Overflow / clipping | Console / required requests | Status | Evidence / notes |
|---|---|---|---|---|---|---|---|---|
| 1920×1080 | Director | Employee Master | Not separately varied | Not separately varied | No page-level horizontal overflow | No Internal Server Error observed | Pass | Authenticated responsive check. |
| 1440×900 | Director | Employee Master | Not separately varied | Not separately varied | No page-level horizontal overflow | No Internal Server Error observed | Pass | Authenticated responsive check. |
| 1280×800 | Director | Employee Master | Not separately varied | Not separately varied | No page-level horizontal overflow | No Internal Server Error observed | Pass | Authenticated responsive check. |
| 1024×768 | Director | Employee Master; Logic Center overview/performance/statutory/roster/simulation/audit | Not separately varied | Not separately varied | No page-level horizontal overflow | No Internal Server Error observed | Pass | People and Logic Center representative routes exercised. |
| 768×1024 | Director | Employee Master | Not separately varied | Not separately varied | No page-level horizontal overflow | No Internal Server Error observed | Pass | Authenticated responsive check. |
| 390×844 | Director | Employee Master; Logic Center overview/performance/statutory/roster/simulation/audit | Not separately varied | Not separately varied | No page-level horizontal overflow | Console errors/warnings empty; no Internal Server Error | Pass | Final Employee Master metrics: body/html client width = scroll width = 390; topbar actions client width = scroll width = 370; People actions client width = scroll width = 344. |
| 1440×900 | HR Manager | Attendance & Roster Rules; Employee Master | Light and dark | Expanded | Body and document client width equalled scroll width; governed editor and employee register remained reachable | Console errors/warnings empty; required routes returned successfully | Pass | Normal seeded login was used. The roster editor rendered only for the authorized HR role; dark mode switched immediately and light mode was restored. |
| 390×844 | HR Manager | Attendance & Roster Rules; Employee Master | Light | Mobile navigation | Body/document client width equalled scroll width at 390 px; People register used employee cards and the Logic Center used its intentional internal section scroller | Console errors/warnings empty; no Internal Server Error | Pass | Normal seeded login was used. No middleware or policy bypass and no data mutation occurred. |

### Manual workstation and client acceptance

Native browser-chrome zoom and screen-reader software remain manual workstation checks unless directly exercised. CSS scaling or viewport emulation must not be reported as native zoom evidence.

| Check | Required evidence | Status | Owner / notes |
|---|---|---|---|
| Native browser zoom at 125% | Authenticated screenshots and interaction/overflow result | Pending | Manual workstation check |
| Native browser zoom at 150% | Authenticated screenshots and interaction/overflow result | Pending | Manual workstation check |
| Keyboard-only navigation | Focus order, visible focus, activation, Escape, and error recovery | Pending | — |
| Screen-reader validation | Labels, landmarks, tables, errors, status announcements, and restricted states | Pending | Manual assistive-technology check |
| Light and dark themes | Immediate switch, reload persistence, contrast, and unthemed-surface review | Partial pass | Auditor light-to-dark switch was immediate and persisted after reload. Full role/screen contrast and unthemed-surface review remains pending. |
| Client role-by-role UAT | Signed workflow acceptance and retained defect references | Pending | Client acceptance gate |

## Security and integrity boundaries

- Creator/approver separation remains mandatory for governed rule activation and performance overrides.
- Active governed versions are immutable.
- Score snapshots are append-only, and restrictive foreign keys retain pinned review and override evidence.
- Generic snapshot writes serialize on a stable company/rule/subject mutex so concurrent first writes cannot create two current snapshots.
- Scheduled activation failures emit safe per-rule operational evidence without exposing governed configuration or private inputs.
- Performance, statutory, and roster simulation never writes payroll, attendance, performance-review, or roster results.
- Payroll calculations use deterministic minor units and pinned rule versions.
- Approved payroll runs and run items are immutable through normal Eloquent mutation paths.
- Sensitive payroll data is not granted solely by System Administrator status.
- Guest, external-provider, and statutory source evidence is never fabricated.
- Historical approved payroll and closed performance reviews are not silently recalculated.
- System Administrator technical governance does not by itself grant access to employee tax profiles or proof documents.

## External activation gates

The local data audit found one populated employee statutory state: `MH`. No active `payroll.statutory_rules` pack exists. Existing `payroll.tax_rules`, attendance, and leave settings are not treated as a substitute for an independently verified Maharashtra/central statutory payroll pack.

The following are operational or legal approval gates rather than missing code:

1. Current India central and employee-state numeric statutory packs must be verified against official Government sources and approved by an independent Compliance/Legal maker-checker before activation.
2. The legal Form 16 document template must be approved independently before production issuance.
3. Governed payroll must complete two shadow payroll cycles with every unexplained variance resolved.
4. Performance scoring must complete one shadow review cycle.
5. Rosters must complete one published schedule period before attendance/payroll dependency is enabled.
6. Biometric, geofence, and external payroll-provider adapters require real provider contracts and credentials.
7. Adjustment/reversal accounting for an already approved payroll requires Payroll and Finance approval of signed credit, recovery, export, and posting semantics before implementation may become authoritative.
8. Client role-by-role UAT and final acceptance remain pending.

These gates fail closed: an unverified pack, unapproved template, or unsupported provider cannot become authoritative through UI state alone.

## Rollback and reproducibility

- Every governed calculation stores or references its rule version and checksum.
- Attendance, performance, and payroll evidence paths retain reproducible input provenance.
- Future-effective activation is scheduler-controlled.
- Existing historic records are preserved.
- Rollback selects an approved prior governed version; it does not mutate historic results in place.
- Four generated legacy payroll employee snapshots currently have `input_snapshot = NULL`; they must be classified as legacy/non-authoritative or regenerated from governed inputs before approval.
- Supersession, permission, scoring, governance, and history migrations are production forward-fix boundaries where rollback could lose authority or evidence; destructive rollback is not a production recovery strategy.
