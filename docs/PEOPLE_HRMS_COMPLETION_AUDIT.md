# People / HRMS Completion Audit

## Status and scope

This report records the People/HRMS work implemented in the current Laravel Blade application and the validation evidence available at handoff. It is deliberately limited to repository-backed behavior. The reference screenshots remain the presentation authority; Laravel policies, Form Requests, Actions, domain services, and persisted records remain the business and authorization authority.

One-company mode remains active. No React, Vue, Inertia, Livewire, SPA, mock HR records, or frontend business authority was introduced.

## Implemented roadmap coverage

| Roadmap area | Implemented evidence |
|---|---|
| People workspace foundation | Shared policy-aware People workspace navigation, responsive presentation states, and consistent HR page structure were added across the HR surfaces. |
| Employee Master and Employee 360 | The employee register, filters, employee presentation, responsive states, Employee 360 navigation, and policy-aware employee information were completed using the existing employee routes and mutations. |
| HR Command Center and employee self service | The HR dashboard and employee self-service experience aggregate existing authorized HR records without introducing a separate frontend data store. |
| Attendance and leave | Existing attendance, shift, regularization, leave request, balance, processing, encashment, and policy workflows were exposed through the People workspace while retaining their existing server authority. |
| Payroll and employee finance operations | Payroll, salary structures, commissions, bank transfers, claims, and loans remain policy-controlled and were integrated into the People information architecture. |
| Recruitment, performance, and lifecycle | Recruitment, performance cycles/reviews, employee confirmations, movements, separation settlements, and exit interview records were integrated using existing routes, Actions, and policies. |
| Documents, assets, helpdesk, and compliance | Private employee documents, assets, helpdesk, policy acknowledgements, and compliance surfaces were integrated without weakening file or record authorization. |
| Reports and settings | A permission-aware HR report catalogue and governed HR settings surfaces were added. Existing System Settings approval/activation remains the mutation authority. |

Representative implementation locations include:

- `app/Application/Hr`
- `app/Domain/Hr`
- `app/Http/Controllers/Hr`
- `app/Http/Requests/Hr`
- `app/Policies`
- `resources/views/hr`
- `resources/css`
- `tests/Feature`

## Security and consistency corrections

### Employee movement compensation visibility

Employee movement history no longer renders raw `new_values` directly in Blade. A normalized movement presenter applies the same `EmployeeFieldVisibility` authority to HTML and JSON output. Users without compensation access receive a restricted presentation while the stored movement data remains unchanged.

Key files:

- `app/Domain/Hr/Services/EmployeeMovementPresenter.php`
- `app/Application/Hr/Data/EmployeeMovementChangeData.php`
- `app/Application/Hr/Data/EmployeeMovementRowData.php`
- `app/Application/Hr/Actions/ListEmployeeMovementWorkspace.php`
- `app/Http/Resources/EmployeeMovementResource.php`
- `resources/views/hr/employees/movements.blade.php`
- `tests/Feature/HrEmployeeCompensationVisibilityTest.php`

### Active internal user eligibility

A shared `ActiveInternalUserEligibility` authority now governs internal-user candidates at presentation, validation, policy, and service boundaries. It requires an active account in the same company and excludes roles that explicitly carry buyer or partner portal permissions. Wildcard internal administrative roles are not incorrectly treated as portal users.

This authority is used by employee user linking, candidate-to-employee conversion, recruitment interviews and feedback, employee-profile services, and HR Helpdesk assignee selection.

Key files:

- `app/Domain/Hr/Services/ActiveInternalUserEligibility.php`
- `app/Domain/Hr/Services/EmployeeRegister.php`
- `app/Domain/Hr/Services/HrHelpdeskAssigneeCandidates.php`
- `app/Domain/Recruitment/Services/RecruitmentWorkspaceRegister.php`
- `app/Http/Requests/Hr/StoreEmployeeRequest.php`
- `app/Http/Requests/Hr/UpdateEmployeeRequest.php`
- `app/Http/Requests/Recruitment/ConvertCandidateToEmployeeRequest.php`
- `app/Http/Requests/Recruitment/ScheduleInterviewRequest.php`
- `app/Policies/InterviewPolicy.php`
- `app/Services/Hr/EmployeeProfileService.php`
- `app/Services/Recruitment/RecruitmentService.php`
- `tests/Feature/PeopleActiveInternalUserEligibilityTest.php`

### Additional enforced safeguards

- Employee hierarchy validation includes indirect reporting-cycle protection.
- Helpdesk assignment excludes inactive, external, cross-company, and otherwise ineligible users.
- Sensitive compensation fields remain governed consistently across supported UI and resource paths.
- Existing private-document authorization and audit behavior remain authoritative.
- Typed attendance and roster rule-pack editors normalize company-scoped governed drafts, while the generic System Settings approval path revalidates and canonicalizes those packs before activation.
- Attendance finalization fails closed when any employee/date lacks authoritative schedule, attendance, approved leave, holiday, weekly-off, or explicit-absence evidence.
- Approved payroll runs and run items reject ordinary Eloquent update/delete operations; correction after approval remains subject to separately approved adjustment/reversal accounting semantics.

## HR report catalogue scope

The report catalogue exposes only export contracts proven to exist in the application:

| Report | Available formats |
|---|---|
| Employee Master Register | CSV, XLS, PDF |
| Payroll Run Register | CSV, Excel, PDF |

Availability is centralized in `app/Domain/Hr/Services/HrReportCatalog.php` and reused by request authorization, People navigation, and the report view. The implementation does not advertise recruitment, statutory, performance-scoring, or other reports whose export contracts have not been proven.

Related files:

- `app/Domain/Hr/Services/HrReportCatalog.php`
- `app/Http/Requests/Hr/HrReportIndexRequest.php`
- `app/Domain/Hr/Services/PeopleWorkspaceNavigation.php`
- `resources/views/hr/reports/index.blade.php`
- `tests/Feature/HrReportsAndSettingsHubTest.php`

## Recorded automated validation

The following are the most recent recorded focused results after the final People security corrections:

| Command/filter | Result |
|---|---|
| `php artisan test --filter=Hr --compact` | 201 passed, 2,185 assertions |
| `php artisan test --filter=Payroll --compact` | 49 passed, 619 assertions |
| `php artisan test --filter=Recruitment --compact` | 29 passed, 403 assertions |
| `php artisan test --filter=HrReportsAndSettingsHubTest --compact` | 11 passed, 66 assertions |
| `php artisan test --filter=HrEmployeeCompensationVisibilityTest --compact` | 5 passed, 54 assertions |
| `php artisan test --filter=PeopleActiveInternalUserEligibilityTest --compact` | 3 passed, 64 assertions |
| `php artisan test --filter=PeopleWorkspaceCompletionTest` | 9 passed, 128 assertions |
| `php artisan test --filter=ShellInteractionSourceTest` | 5 passed, 90 assertions |
| `php artisan test` | 1,067 passed, 22,442 assertions, 0 failures |
| `php artisan test --filter=ScoringSchedulerAndSnapshotIntegrityTest` | 2 passed, 15 assertions |
| `php artisan test --filter=ScoringRuleIntegrityTest` | 7 passed, 42 assertions |
| `php artisan test --filter=PerformanceScoringGovernanceTest` | 20 passed, 189 assertions |

Earlier implementation gates also recorded:

- People accessibility-focused tests: 2 passed, 91 assertions.
- Blade component tests: 4 passed, 21 assertions.
- Blade view clear/cache completed successfully, including the final release view-cache gate.
- The final Vite production build completed successfully after the responsive People corrections.
- Migration inspection/execution completed without a reported failure. All migrations report `Ran`, including `2026_07_18_001250_enforce_score_snapshot_immutability`.
- HR route inspection reported 89 HR routes.

The complete repository regression suite and PHP syntax checks are green in the local implementation environment. Final Blade view caching and the Vite production build also passed. Limited authenticated role/access and responsive browser checks passed only for the exercised routes and viewport matrix recorded in the detailed acceptance record; native browser zoom, screen-reader validation, the full manual keyboard matrix, complete cross-browser coverage, and client UAT remain pending.

## Governed logic completion and external authority

The repository now includes typed, version-pinned performance scoring, statutory payroll, roster resolution, attendance evidence, simulation, and audit services under the People Logic Center. Scoring-rule configuration is protected by canonical checksums throughout draft, lifecycle, resolution, calculation, scheduled activation, and audit paths. Effective-slot ordering is deterministic, scheduled activation fails closed on invalid integrity evidence, and active-rule cache warming validates the resolved rule before use. Performance rating ranges now use explicit inclusive boundary mapping, configured rounding is applied deterministically, and a typed source registry is the authority for supported scoring evidence inputs.

Score snapshots are append-only at the model boundary. Replacing a current snapshot creates new evidence and marks the prior snapshot historical through a controlled lifecycle operation; arbitrary update/delete is rejected. Restrictive foreign keys prevent pinned performance reviews or override chains from losing their snapshot evidence.

Generic snapshot writes additionally lock a stable company/rule/subject mutex before reading or replacing current evidence. This closes the first-write race where concurrent writers previously had no existing snapshot row to lock. Scheduled scoring activation now records safe per-rule failure classification and retry evidence in logs and audit history, while isolating a failed rule so later eligible rules can still activate.

Employee tax inputs now use a governed draft, submit, independent verify, lock, and superseding-amendment workflow. Canonical profile checksums include immutable proof snapshots, while explicit payroll/compliance authorization protects both the domain read boundary and private proof content.

Roster impact simulation is deterministic and non-mutating. It reports the authoritative roster resolution and ambiguity evidence without creating rosters, attendance summaries, payroll inputs, or workflow decisions.

Attendance and roster governance now uses typed Logic Center editors rather than an arbitrary-JSON primary workflow. The same validators also run during generic System Settings approval, canonicalizing supported legacy aliases and blocking malformed drafts. Rotation-pattern editing dynamically supports 1–31 cycle days, while server validation remains authoritative. Attendance-period finalization performs a fail-closed employee/date reconciliation before producing payroll evidence.

Approved payroll runs and employee run items are immutable through normal Eloquent update and delete paths. This protects approved calculation evidence but does not define the accounting behavior for later credits, recoveries, exports, postings, adjustments, or reversals; those semantics remain an external Payroll/Finance approval gate.

The following are not fabricated from prototype screenshots and remain fail-closed until independently authorized:

- current India central and employee-state numeric statutory values;
- the legally approved Form 16 template;
- approved payroll adjustment/reversal accounting semantics;
- biometric, geofence, payroll-provider, background-check, or other external integration contracts;
- disaster-recovery and provider failover contracts;
- unsupported multi-company precedence in the one-company application;
- reports or exports without proven server contracts.

Roster rotation, swaps, performance formulas, and statutory packs are governed through versioned rules. Only verified, approved, effective-dated versions may become authoritative. Historical results are not silently rewritten.

The local data audit also identified four generated payroll employee snapshots whose legacy `input_snapshot` value is `NULL`. They must be explicitly classified as legacy/non-authoritative or regenerated from governed inputs before any related payroll run can be approved; their missing provenance must not be inferred.

## Browser acceptance status

Authenticated role/access boundaries were exercised through the normal local login form for Director, HR Manager, Payroll Admin, Compliance Officer, Auditor, Employee, and System Administrator without middleware or authorization bypass. Employee Master was exercised at 1920×1080, 1440×900, 1280×800, 1024×768, 768×1024, and 390×844 with no page-level horizontal overflow or Internal Server Error in those recorded checks. Logic Center representative views were exercised at 1024×768 and 390×844 without page overflow or server errors. A final HR Manager pass verified the permission-aware Attendance & Roster editor and Employee Master at 1440×900 and 390×844; console errors/warnings were empty, document width remained bounded, and the light-to-dark-to-light theme cycle rendered immediately. These checks do not prove complete cross-browser, native-zoom, keyboard, screen-reader, or client visual acceptance.

The seeded Director has no linked Employee record, so `/hr/employees/me` returned 404; this is recorded as a seed-data limitation rather than an authorization or rendering success. Native browser zoom at 125%/150%, screen-reader validation, the complete manual keyboard matrix, and client UAT are not claimed.

The browser matrix and remaining activation gates are maintained in `docs/PEOPLE_LOGIC_CENTER_ACCEPTANCE_2026-07-18.md`.

## Handoff conclusion

The repository now contains the planned People workspace, principal HR operational surfaces, governed People Logic Center, performance scoring, statutory payroll framework, roster/attendance controls, simulations, and immutable calculation evidence. The final Laravel regression suite records 1,067 passing tests and 22,442 assertions, and the recorded asset gates passed. The limited authenticated route/responsive checks described above passed only for their recorded matrix. Production authority remains conditional on independent legal/compliance approval of official statutory values, an approved production Form 16 template, two payroll shadow cycles, one performance shadow cycle, one published roster/UAT period, real provider contracts where applicable, approved payroll adjustment/reversal accounting semantics, native-zoom and assistive-technology checks, and client UAT.

## Migration and rollback cautions

- The employee-tax-profile supersession migration can be unsafe to reverse after multiple governed versions exist; production correction should be forward-only.
- The People permission rollback is intentionally a no-op so production authorization history is not silently stripped.
- The original scoring-foundation rollback revokes scoring permissions broadly and therefore requires an explicit access-impact review.
- Governance and history-table rollback paths are destructive once authoritative evidence exists. Production remediation must use forward-fix migrations rather than destructive rollback.
