# Payroll and Statutory Completion Audit — 2026-07-18

## Outcome

The repository-controlled payroll and statutory foundation now supports deterministic annual tax projection without embedding unverified statutory rates or slabs in application code.

Existing payroll safeguards remain authoritative: governed statutory rule packs, maker-checker approval, official-source evidence, employee statutory-state resolution, finalized attendance snapshots, immutable rule-version pinning, integer minor-unit monetary calculations, stale-run protection, tax-document governance, and commission processing.

No current legal rate, threshold, slab, exemption, or state-specific monetary value was invented or copied from prototype material.

Repository code completion is separate from statutory authority. The typed engines and governed workflows are implemented, while numeric packs, legal templates, shadow cycles, provider integration, and client acceptance remain external gates.

## Implemented repository-controlled changes

### Governed employee tax inputs

- Added effective financial-year tax profiles with draft, submitted, verified, and locked lifecycle states.
- Added encrypted declaration/proof inputs with evidence-document ownership checks.
- Added optimistic lock versions, checksums, lifecycle history, maker-checker separation, and audit events.
- Locked profiles and their declarations are immutable.
- Added typed immutable application DTOs so validated tax input does not cross the application boundary as unstructured request arrays.

### Deterministic annual projection

- Added an annual projection context and result contract using integer minor monetary units.
- Added a deterministic annual tax projection engine driven only by an activated statutory rule-pack definition.
- Added an authoritative context factory that requires a locked, checksum-valid employee tax profile and loads approved prior payroll snapshots.
- Integrated annual projection into statutory simulation and payroll generation.
- Payroll generation rejects ambiguous packs containing more than one annual projection definition.

### Official-source validation

- Government source hosts continue to allow `gov.in`, `nic.in`, and their subdomains.
- Maharashtra Labour Welfare Board evidence explicitly allows the exact normalized host `mlwb.in`.
- Lookalike and arbitrary domains remain rejected. The allowlist does not accept subdomains or suffix matches for `mlwb.in`.

## Governed employee tax-input vertical slice

Employee tax inputs are now exposed through a policy-reviewed server-rendered workflow:

1. Employees with the explicit `employee.self_service` capability may save, amend, and submit only their own financial-year profile.
2. Payroll and compliance reviewers use dedicated list/detail screens backed by immutable presentation DTOs and thin application Actions.
3. Verification and locking are separate maker-checker operations with optimistic lock versions; the creator, submitter, verifier, and locker cannot collapse into one actor.
4. Linked proof files remain in private managed-document storage. Tax-aware policies protect direct downloads, approval, listing, storage metadata, and deletion.
5. Locked amendments create a new version that supersedes, but never mutates, the original locked profile.
6. The annual projection factory validates one shared canonical profile payload containing immutable proof snapshots. Relational proof removal cannot erase historical evidence, while snapshot tampering invalidates the profile checksum.

Wildcard technical access, bare `payroll.view`, and generic HR permissions do not grant access to employee tax profiles or linked proof content. Explicit payroll/compliance capability is required.

## External activation gates

The following are not safe to infer and remain outside this repository-controlled correction:

- Current central and state statutory numeric packs, pending official-source verification and compliance/legal approval.
- Claim payout accounting semantics.
- Loan amortization, interest, installment, and recovery accounting.
- Pre-run adjustment rules.
- Post-approved payroll reversal and adjustment-run accounting.
- Historical recalculation ledger and reporting semantics.
- Shadow payroll comparison, operational reconciliation, and production sign-off.

Until approved packs exist, the engine remains configuration-driven and fail-closed rather than applying guessed values.

The official-source catalogue and independent maker-checker preparation record is maintained in `docs/STATUTORY_RULE_PACK_SOURCE_HANDOFF_2026-07-18.md`. Catalogue inclusion is not approval; sources must be revalidated at activation time.

## Code-complete and external-gate matrix

| Capability | Repository status | Authority status |
|---|---|---|
| Deterministic statutory/tax engines | Implemented and regression-tested | Non-authoritative until an approved effective-dated pack exists |
| Employee tax input and proof workflow | Implemented with checksum, maker-checker, immutability, and explicit access | Client operational acceptance pending |
| Attendance/payroll provenance | Version-pinned path implemented | Four legacy generated payroll snapshots with `input_snapshot = NULL` must be classified or regenerated before approval |
| Official-source evidence | Source locations catalogued | Independent Compliance/Legal verification and approval pending |
| Form 16 | Governed snapshot infrastructure present | Production template and issuance approval pending |
| Payroll cutover | Code path available | Two governed shadow payroll cycles and reconciliation pending |
| External providers | No fake adapter introduced | Contracts, credentials, and operational validation pending |

## Focused validation completed

The following commands passed:

- `php artisan test tests/Feature/EmployeeTaxProfileWorkflowTest.php`
  - 3 tests, 75 assertions covering self-service draft/submit, independent verification/locking, governed amendments, canonical proof snapshots, tamper detection, stale versions, explicit permission boundaries (including the domain read boundary), private proof access, maker-checker approval, storage-metadata redaction, and delete protection.
- `php artisan test tests/Unit/AnnualTaxProjectionEngineTest.php tests/Feature/GovernedStatutoryPayrollTest.php`
  - 12 tests, 134 assertions.
- `php artisan test --filter=PayrollRunTest`
  - 14 tests, 145 assertions.
- `php artisan test --filter=GovernedTaxDocumentSnapshotTest`
  - 5 tests, 43 assertions.
- `php artisan test --filter=PayrollApplicationLayerTest`
  - 3 tests, 27 assertions.
- `php artisan test --filter=PayrollTaxDocumentTest`
  - 5 tests, 89 assertions.

Employee tax-input vertical-slice evidence: **3 tests passed with 75 assertions**. Earlier projection/statutory/payroll evidence remains **39 tests with 438 assertions**.

PHP syntax validation also passed for the modified service, validator, immutable DTOs, projection engine tests, and governed statutory payroll tests.

The final integrated Laravel regression gate passed **1,047 tests with 22,310 assertions** and zero failures. All migrations report `Ran`, including the score-snapshot immutability migration. Final Blade view caching and the Vite production build passed.

Authenticated access-boundary evidence used the normal login flow: Payroll Admin and Compliance Officer could access employee tax profiles; Director, HR Manager, System Administrator, and Employee were denied that administrative profile route. The Employee could access only their own tax-input workflow. Payroll Admin could access statutory and authorized simulation views but not performance; Compliance Officer could access statutory and audit views but not performance, roster, or simulation. These checks validate application authorization only and do not activate or legally approve a statutory rule pack.

## Migration and recovery cautions

- Reversing employee-tax-profile supersession after multiple governed versions exist can violate retained version relationships; use a forward-fix migration in production.
- Permission rollback is intentionally conservative, and the original scoring-foundation rollback can revoke scoring permissions broadly.
- Governance/history rollback paths are destructive once approved evidence exists.
- Production recovery must preserve payroll, proof, rule-version, and calculation evidence; destructive rollback is not an acceptable correction path.

## Production interpretation

This delivery completes the safe code-level contracts and deterministic calculation path. It does not claim that India statutory calculations are legally activated or production-approved. That claim requires verified numeric packs, appointed compliance approval, shadow-cycle comparison, and operational sign-off.
