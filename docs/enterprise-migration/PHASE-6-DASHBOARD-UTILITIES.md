# Phase 6 — Role Dashboards and Global Utilities

## Status

Completed for role dashboards, Approval Center, Notifications, report register/export and Profile utilities. Report pin/schedule administration remains intentionally assigned to Phase 15.

## Implemented

- The primary dashboard route now follows a Form Request and Gate, thin controller, one-use-case Action, intent-named domain reader and immutable page DTO.
- Server-rendered dashboard delivery reads only role dashboard and navigation context data instead of assembling every module payload.
- All seeded roles retain normalized role-specific statistics, sections, charts, alerts, tables, quick actions and permission-safe drilldowns.
- Project, role and period context remain server-controlled and session-backed.
- Project Health dashboard values use current immutable scoring snapshots only; missing scores remain explicitly uncalculated.
- Approval Center list assembly moved from the controller into a focused Action and immutable DTO while preserving JSON and export behavior.
- Approval Center no longer loads the unrelated application bootstrap for its Blade page.
- Notifications use a focused inbox Action, immutable DTO, recipient-scoped query/summary services and a domain filter catalogue.
- Notification Blade delivery no longer loads unrelated application bootstrap data.
- Profile context reads only the authorized active role and selected project context; the unused broad bootstrap payload was removed from the profile result.
- Reports & Analytics now opens a native Blade workspace for browser requests with report, project, status and date filters.
- Report JSON and CSV/Excel/PDF contracts remain backward compatible and audited.
- Report records render through responsive desktop and mobile registers with a business empty state.

## Architecture slices

- Dashboard: `DashboardRequest` → `DashboardController` → `ShowRoleDashboard` → `RoleDashboardReader` → `DashboardPageData` → Blade.
- Approvals: `ApprovalCenterIndexRequest` → `ApprovalCenterController` → `ListApprovalCenter` → `ApprovalCenterService` → `ApprovalCenterPageData` → Blade/JSON.
- Notifications: `NotificationIndexRequest` → `NotificationCenterController` → `ListNotificationInbox` → query/summary/catalog services → `NotificationInboxData` → Blade/JSON.
- Profile: profile policy → `ProfileController` → `ShowProfile` → `ProfileContextReader` → `ProfilePageData` → Blade.
- Reports: `ReportRegisterRequest` → `ManagementReportController` → `GenerateReportRegister` → report/catalog/scope services → `ReportRegisterData` → Blade/JSON/export.

## Verification

- Phase 6 focused gate: 53 tests, 6,391 assertions.
- Production Vite build passed.
- Blade view compilation passed.
- Governance route inspection passed.
- Browser verified Dashboard, Approval Center, Notifications, Reports & Analytics and My Profile.
- Mobile viewport verified all five routes with no horizontal overflow, no server error, explicit button types and no console errors.
- All seeded role dashboard architecture and external-route restrictions passed.

## Rollback

No schema migration was introduced. Rollback consists of reverting the focused Actions/DTOs/readers and restoring the prior controller data assembly. Existing records and report exports are unaffected.
