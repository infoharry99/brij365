# Phase 7 — Task and Calendar Workspaces

## Status

Completed for the native Task Management and Calendar Management workspaces and their mutation boundaries.

## Implemented

- Task and Calendar browser routes render focused Blade workspaces without the broad application bootstrap payload.
- List delivery follows focused workspace Actions, domain option/register services and immutable page DTOs.
- All Task and Calendar writes now enter through intent-named application Actions and an immutable validated command DTO.
- Existing Form Requests remain the validation boundary and existing policies/domain services remain authoritative for authorization, transactions, notifications and audit history.
- Task create, assign, transfer, update, status, watcher, dependencies, archive, bulk actions, comments, checklist, subtasks and time logs preserve their existing contracts.
- Calendar create, update/reschedule, cancel, complete and archive preserve their existing contracts.
- Company, project and internal-user options are current-company scoped.
- The active-company company-register query now correctly scopes `companies.id`; a live MySQL-only failure caused by filtering a nonexistent `companies.company_id` column is fixed.
- A source architecture guard prevents primary Task/Calendar write paths from regressing to direct controller-to-service calls.

## Architecture slices

- Tasks read: `WorkTaskIndexRequest` → `CollaborationController` → `ListTaskWorkspace` → collaboration query/options services → `TaskWorkspaceData` → Blade/JSON.
- Calendar read: `CalendarEventIndexRequest` → `CollaborationController` → `ListCalendarWorkspace` → calendar register/options services → `CalendarWorkspaceData` → Blade/JSON.
- Writes: named collaboration Form Request → thin controller → one-use-case Action → immutable `CollaborationCommandData` → existing transactional domain workflow → Resource/redirect.

## Verification

- `CollaborationWorkflowTest`: 38 tests, 518 assertions.
- `SingleCompanyScopeTest`: 8 tests, 22 assertions, including native Task and Calendar page loading in active single-company mode.
- `CollaborationApplicationLayerTest`: 2 tests, 12 assertions.
- All new/changed PHP files passed syntax validation.
- Blade view compilation passed.
- Production Vite build passed.
- Browser verified Task Management and Calendar Management on the live MySQL runtime with no server error or horizontal page overflow.
- Full Phase 7 regression gate: 737 tests, 16,404 assertions.

## Rollback

No schema change was introduced. Rollback consists of reverting the focused Actions/DTOs/register/options services and restoring direct controller orchestration. Existing Task, Calendar, notification and audit records are unaffected.
