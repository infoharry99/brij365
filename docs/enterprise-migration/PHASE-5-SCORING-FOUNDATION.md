# Phase 5 — Scoring Logic Foundation

## Current status

Completed. The rule lifecycle, deterministic calculation engine, recalculation workflow, all eight source-domain adapters, approved evidence-entry surfaces, source-page score presentation and regression gate are implemented.

## Implemented

- Separate authorized `Scoring Logic` main navigation module.
- Eleven required server-rendered destinations.
- Versioned `scoring_rules` with company, key, version, lifecycle status, structured configuration, checksum, effective date, creators/approvers/activators, change reason and previous version.
- Immutable `score_snapshots` schema with components, weights, band, input evidence, rule version and override evidence.
- Recalculation run and failure schemas.
- Dedicated permissions and policy.
- Safe allow-listed rule catalogue for eight scoring areas.
- Versioned draft creation with mandatory reason, deterministic checksum, company scope and audit event.
- Draft validation that rejects executable keys, invalid criteria, non-100% weights, invalid bands, unsupported rounding and invalid sample size.
- Submit, approve and activate/schedule transitions.
- Creator/approver segregation.
- Atomic superseding of the prior active rule.
- Active-rule cache invalidation.
- Canonical checksum verification across draft updates, lifecycle transitions, active resolution, calculation, scheduled activation, and audit presentation.
- Deterministic effective-slot ordering and overlap protection.
- Validated active-rule cache warming and fail-closed scheduled activation when integrity evidence is invalid.
- Responsive Blade rule register, history and recalculation empty states.
- Structured rule editor for controlled criteria, conditions, score bands, rating ranges, thresholds, rounding, evidence size and overrides.
- Form Request and policy-protected edit/update routes with immutable DTOs, configuration normalization, deterministic checksum regeneration and update audit evidence.
- Draft updates reject invalid weights, unsafe identifiers, unsupported operators, invalid thresholds and executable configuration keys.
- Clone creates a new versioned draft without mutating its source version.
- Rejection requires an authorized independent approver and a documented reason.
- Rollback safely prepares a new draft from a historical configuration and still requires validation, approval and activation.
- Active-rule retirement is atomic, reasoned, audited and invalidates the active-rule cache.
- Rule inspection shows stored version evidence, structured criteria, score bands and rule-specific activity history.
- Version comparison is restricted to the same company and rule key.
- Impact preview reconciles eligible and preserved records from the mapped source domain without mutating them.
- Rule export produces an audited JSON evidence package containing configuration, lifecycle timestamps and checksum.
- Active-rule resolution is cached by company and rule key and is invalidated during activation or retirement.
- Deterministic calculation supports allow-listed conditions, normalized component scores, exact applied weights, configured rounding, score bands, mandatory-condition failures and canonical input hashing.
- Append-only score snapshots retain source inputs, component breakdown, weights, band, rule version and calculation timestamp. Arbitrary update/delete is rejected, controlled replacement marks prior evidence historical, and restrictive foreign keys protect pinned reviews and override chains.
- Manual override creates a linked replacement snapshot, preserves the original, derives the configured band, requires permission and reason, and records activity evidence.
- Activation automatically creates a reconciled recalculation run; authorized users can also start a new run manually after completion.
- Recalculation processes complete Lead Qualification evidence, records safe per-record failures for incomplete or unsupported source evidence, and notifies authorized users on completion.
- Scheduled activation command runs every minute with overlap protection, activates due independently approved rules and starts recalculation.
- The Scoring workspace displays run progress and recent failure evidence.
- Eight source-domain adapters calculate Lead Qualification, Employee Performance, Employee Confirmation, Recruitment, Vendor, Project Health, Customer Satisfaction and Exit Feedback scores from explicit business evidence.
- Source records retain structured scoring evidence without allowing scoring to overwrite human decisions, workflow statuses or source ratings.
- Recruitment calculates a weighted panel score, Customer Satisfaction calculates a project aggregate, and Exit Feedback calculates a department aggregate with configured minimum sample enforcement.
- Aggregate scoring subjects provide stable, auditable identities for project and department summary snapshots.
- Project Master now provides a policy-protected health-evidence form, recalculates immediately against the active Project Health rule, and displays the current score, band, rule version and calculation time.
- Project Cost/ROI export reads the active immutable Project Health snapshot; the contradictory controller heuristic has been removed and unavailable scores remain blank.
- Lead Qualification now refreshes the active Lead Scoring snapshot immediately after qualification and presents the current score, band and rule version without changing the authorized CRM routing decision.
- Lead Qualification condition options, points, weights, bands and outcomes now come from the active versioned `lead_quality` rule. The older System Settings record is retained only as a transition fallback when no central rule is active.
- Employee Confirmation and Employee Performance submissions refresh their current score snapshots immediately without making the final HR decision.
- Recruitment panel feedback refreshes the interview snapshot when minimum evidence is complete, and the native interview register presents score, band, rule version and calculation time.
- Vendor Master provides a policy-protected performance-evidence form and displays the current score, band, rule version and calculation time.
- Customer ticket closure refreshes the project Customer Satisfaction aggregate without rewriting the submitted customer rating.
- Exit Interview submission refreshes the department aggregate when the configured minimum sample is available without rewriting confidential responses.
- Management dashboard Project Health values now come only from current immutable snapshots. Construction progress is no longer presented as a fallback health score.

## Verification

- Additive migration applied successfully on local MySQL.
- `ScoringLogicTest`: 26 tests, 311 assertions.
- `ProjectMasterWorkflowTest`: 12 tests, 113 assertions.
- `CrmQualificationAndSiteVisitTest`: 15 tests, 169 assertions.
- Scoring source workflow gate: 107 tests, 1,445 assertions.
- Dashboard/bootstrap regression tests passed, including exact Project Health snapshot value, band and rule version.
- Production Vite build passed.
- Blade view compilation passed.
- Final integrated repository gate: 1,047 tests, 22,310 assertions, zero failures.
- Authenticated route/access boundaries passed for Director, HR Manager, Payroll Admin, Compliance Officer, Auditor, Employee, and System Administrator on the exercised Logic Center and People routes. Logic Center representative views passed responsive overflow/server-error checks at 1024×768 and 390×844; Employee Master passed the recorded six-viewport matrix. Auditor light-to-dark switching was immediate and persisted after reload.
- Native browser zoom at 125%/150%, screen-reader validation, the full manual keyboard matrix, and client UAT remain pending release gates and are not claimed by this phase record.

## Remaining Phase 5 work

- No remaining repository-foundation implementation is identified for Phase 5. Operational authority still requires applicable statutory maker-checker approval, shadow cycles, published roster evidence, provider contracts, and client UAT.

## Rollback

Before production adoption and before governed evidence exists, a clean environment may reverse the additive foundation with explicit access review. Once governed rules or snapshots exist, production recovery is forward-only: append-only score evidence and restrictive foreign keys must be preserved. The original foundation rollback can revoke scoring permissions broadly, and governance/history rollback paths are destructive; neither is a safe production incident-recovery mechanism.
