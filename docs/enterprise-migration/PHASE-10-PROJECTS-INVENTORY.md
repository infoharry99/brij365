# Phase 10 — Projects, Inventory, Pricing and Project Health

## Status

Completed for the single-company project master, project team, unit inventory, availability, pricing, cost/ROI and project-health workspaces.

## Implemented

- Project Master now enters through focused list/create/update/team-assignment Actions and immutable command/page DTOs while preserving the approved Blade workspace.
- Project hierarchy uses the existing authoritative records: towers/blocks are represented by unit tower attributes and phases by construction milestone phase records. No duplicate hierarchy schema was introduced.
- Project team assignment and revocation use named Form Requests, policies, focused Actions and audited existing workflows.
- Project health remains driven by the active scoring rule and verified project inputs; calculated score, status and evidence remain persisted and visible.
- Unit Inventory and availability use a focused current-company register with project, tower, status and search controls.
- Unit Availability export now enters through a focused Action and immutable export DTO while preserving scoped CSV output.
- Unit Pricing reads, version drafting and approval use focused Actions and immutable DTOs without changing approval, pricing-breakdown or audit behavior.
- Project cost, ROI, booking and collection summaries continue to reconcile the existing project records and are linked from the native workspace.
- Browser workspaces preserve the approved visual system and remain usable at desktop and 390×844 mobile widths.
- No user-facing implementation terminology is present in the completed Phase 10 workspaces.

## Architecture slices

- Project reads: `ProjectIndexRequest` → `ProjectController` → `ListProjectWorkspace` → `ProjectWorkspaceRegister` → immutable `ProjectWorkspaceData` → Blade.
- Project writes: named Form Request → thin `ProjectController` → focused Action → immutable `ProjectCommandData` → existing domain workflow → redirect.
- Team membership: named assignment/revocation request → policy → focused Action → scoped project team workflow → audit/redirect.
- Unit inventory: `ProjectUnitIndexRequest` → `ProjectUnitController` → `ListUnitInventoryWorkspace` → `InventoryWorkspaceRegister` → immutable `UnitInventoryWorkspaceData` → Blade.
- Availability export: validated request → `ExportUnitAvailability` → `UnitAvailabilityReport` → immutable `UnitAvailabilityExportData` → streamed CSV.
- Unit pricing: named request → thin `UnitPricingController` → list/create/approve Action → immutable inventory DTO → existing pricing workflow → Blade/redirect.

## Data model decisions

- The approved existing project hierarchy remains authoritative.
- Unit tower/block information is persisted on `project_units.tower` and grouped in the Unit Inventory workspace.
- Construction phases are persisted on `construction_milestones.phase` and remain managed by the construction workspace.
- Separate tower, block or phase tables were not invented because the current schema already carries these relationships and the requirement does not authorize a duplicate master.

## Verification

- `ProjectInventoryApplicationLayerTest`: 2 tests, 18 assertions.
- `ProjectWorkflowTest`: 12 tests, 113 assertions.
- `UnitPricingWorkflowTest`: 9 tests, 85 assertions.
- Production Vite build passed.
- Live MySQL browser verified Project Master, Unit Inventory and Unit Pricing at desktop and 390×844 mobile widths with no horizontal page overflow or browser console errors.
- Project and inventory UI-copy audit found no visible framework, database or developer-facing terminology.
- SQLite backup regression was rerun independently after two overlapping suite processes caused a temporary shared test-file collision: 6 tests, 41 assertions passed.
- Full Phase 10 regression gate: 751 tests, 16,540 assertions.

## Rollback

No schema migration was introduced. Rollback consists of reverting the focused Project/Inventory Actions, DTOs, registers, Form Request and controller wiring. Existing projects, units, hierarchy attributes, team assignments, scoring evidence, price versions, bookings, collections and audit history remain unchanged.
