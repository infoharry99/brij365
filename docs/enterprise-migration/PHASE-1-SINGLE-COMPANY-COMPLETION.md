# Phase 1 Completion — Single Company / Multiple Projects

## Outcome

Builder360 now operates in an explicit single-company mode while preserving
the existing `company_id` boundary on business records. The configured company
is resolved by stable business code (`B360D` by default); multiple projects
remain available inside that company.

## Delivered controls

- `BUILDER360_SINGLE_COMPANY` and `BUILDER360_COMPANY_CODE` environment settings.
- Fail-closed `ActiveCompanyResolver`.
- Query, policy and wildcard-permission enforcement through `CompanyScopeService`.
- Authenticated write requests automatically receive the configured company
  only when the request omits `company_id`.
- Explicit attempts to submit another company remain validation/authorization
  failures and are never silently overwritten.
- Additional company creation is disabled in single-company mode.
- Company dropdowns are replaced by a reusable Blade company-context component;
  it renders a hidden authoritative value in single-company mode and retains a
  selector only when multi-company mode is deliberately enabled.
- Health/readiness verification includes the configured active company.
- Local application URL is standardized to `http://127.0.0.1:8001`.

## Validation evidence

- Full suite after the single-company implementation: 689 tests passed; one
  obsolete assertion correctly detected the new Vite asset introduced at the
  beginning of Phase 2.
- The obsolete assertion was updated and its focused test passes.
- `SingleCompanyScopeTest`: 7 tests passed.
- Targeted project, collaboration, administration and frontend wiring suite:
  76 tests passed with 1,220 assertions.
- `builder360:verify --json`: single-company readiness reports `ok`.

## Rollback

Set `BUILDER360_SINGLE_COMPANY=false`, clear configuration cache, and the
company-context component and scope service return to their multi-company
behavior. Database columns and historical company relationships were not
removed.
