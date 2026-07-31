# Phase 11 — Construction and Procurement

## Status

Completed for construction planning, progress, materials, stores, procurement, vendors, contractors, BOQ, measurements, bills and their approval workflows.

## Implemented

- Construction milestones, phases and daily site progress use focused read Actions, immutable DTOs and a current-company construction register.
- Daily progress submission, approval and rejection preserve milestone progress updates, evidence, audit and notification behavior.
- BOQ, contractor measurements, certification controls, contractor bills, retention, deductions, approval and payment recording enter through one-use-case Actions.
- Procurement dashboard, vendor master, requisitions, stock register, purchase orders and goods receipts use focused read Actions and an immutable workspace DTO.
- Vendor create/update/status, requisition submission/approval, quote comparison, purchase-order create/approval, goods receipt, stock issue/return/transfer enter focused Actions through named Form Requests.
- Existing domain services remain transaction authorities for stock ledgers, approval transitions, notifications, audit evidence, low-stock alerts and finance linkage.
- Vendor scorecards remain driven by active vendor-performance scoring rules and current persisted evidence.
- The full current-record journey remains functional: requirement/requisition → linked quotations and comparison → purchase order → goods receipt and stock posting → vendor score → finance payable/settlement evidence.
- Buyer, partner and unscoped users remain denied internal construction/procurement data by policies and company scopes.
- No schema or approved UI change was introduced.

## Architecture slices

- Construction reads: named index Request → thin `ConstructionController` → focused list/workspace Action → `ConstructionWorkspaceRegister` → immutable DTO → Blade/Resource.
- Construction writes: named Form Request → policy → focused Action → immutable `ConstructionCommandData` → transactional construction service → Resource/redirect.
- Procurement reads: named index Request → thin `ProcurementController` → focused list/workspace Action → `ProcurementWorkspaceRegister` → immutable DTO → Blade/Resource.
- Procurement writes: named Form Request → policy → focused Action → immutable `ProcurementCommandData` → transactional procurement service → Resource/redirect.
- Scoring: validated vendor evidence → scoring Action/service → active rule snapshot → vendor workspace and performance response.

## Verification

- `ConstructionApplicationLayerTest`: 2 tests, 11 assertions.
- `ConstructionProgressTest`: 11 tests, 109 assertions.
- `ConstructionBoqMeasurementTest`: 8 tests, 141 assertions.
- `ProcurementApplicationLayerTest`: 2 tests, 19 assertions.
- `ProcurementWorkflowTest`: 21 tests, 389 assertions.
- Live MySQL browser verified Procurement Workspace and Construction Progress at desktop and 390×844 mobile widths with no page overflow or console errors.
- User-facing copy audit found only Blade syntax markers, not visible implementation terminology.
- Production Vite build and compiled Blade view cache passed.
- `builder360:verify --json` reported MySQL and every readiness check healthy.
- Full Phase 11 regression gate: 755 tests, 16,570 assertions.

## Rollback

No migration was introduced. Rollback consists of reverting the focused Construction/Procurement Actions, immutable DTOs, registers and thin-controller wiring. Existing milestones, site reports, stock, requisitions, orders, receipts, vendor scores, BOQ, measurements, bills, approvals, finance references, notifications and audit history remain unchanged.
