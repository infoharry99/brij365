# Phase 9 — Sales and CRM

## Status

Completed for the native internal Sales and CRM workspaces, their application boundaries and current-company reporting.

## Implemented

- Lead Management uses a focused register, options service, immutable page/command DTOs and intent-named Actions for create, stage and disposition workflows.
- Lead Qualification and Site Visits use focused read DTOs and Actions while preserving deterministic active scoring rules, qualification snapshots, follow-ups, notifications and audited lifecycle actions.
- Prospect Inquiry capture, assignment, lead conversion and closure now enter through intent-named Actions; the browser workspace reads from a focused scoped register.
- Marketing Campaigns and Lead Activities now have native Blade workspaces rather than JSON-only delivery.
- Campaign create/status and activity recording preserve existing validation, policy, audit and JSON contracts while browser requests receive redirects and business feedback.
- Marketing metrics reconcile real campaign-attributed leads, won leads, bookings, expected value, target attainment and conversion.
- Sales Booking and quote workflows now use immutable Sales DTOs, focused Actions and a current-company booking register without changing pricing, discount, payment schedule or audit controls.
- A native Sales Funnel & Performance workspace provides date/project-filtered funnel, source conversion, owner performance, project conversion and campaign conversion from current records.
- Lead Funnel Analytics and Performance Analytics navigation now open the native CRM analytics workspace instead of the generic report register.
- Partner and portal users remain denied internal CRM, marketing, analytics and booking management routes through existing policies and Form Requests.
- User-facing CRM/Sales pages no longer expose implementation or request-class terminology.

## Architecture slices

- Lead register: `LeadIndexRequest` → `LeadController` → `ListLeadWorkspace` → `LeadRegister` / `CrmWorkspaceOptions` → `LeadWorkspaceData` → Blade/Resource.
- CRM engagement: named Form Request → thin `LeadEngagementController` → one-use-case Action → immutable CRM command → transactional engagement/scoring service → Resource/redirect.
- Prospect inquiries: named Form Request → `ProspectInquiryController` → focused Action → immutable CRM command → inquiry service → Resource/redirect.
- Marketing reads: campaign/activity index request → `MarketingCampaignController` → focused list Action → `MarketingWorkspaceRegister` → immutable page DTO → Blade/Resource.
- Marketing writes: named Form Request → focused Action → immutable CRM command → campaign/activity workflow → Resource/redirect.
- Booking reads: `BookingIndexRequest` → `BookingController` → `ListBookingWorkspace` → `BookingRegister` → immutable `BookingWorkspaceData` → Blade/Resource.
- Booking writes/quotes: named Form Request → focused Action → immutable `SalesCommandData` → pricing/booking workflow → Resource/redirect.
- Sales analytics: `SalesAnalyticsRequest` → invokable controller → `ViewSalesAnalytics` → `SalesAnalyticsReport` → immutable page DTO → Blade.

## Verification

- `CrmApplicationLayerTest`: 3 tests, 28 assertions.
- `CrmLeadTest`: 16 tests, 139 assertions.
- `CrmQualificationAndSiteVisitTest`: 15 tests, 169 assertions.
- `CrmProspectInquiryTest`: 10 tests, 76 assertions.
- `CrmMarketingCampaignTest`: 8 tests, 52 assertions.
- `CrmSalesAnalyticsTest`: 4 tests, 13 assertions.
- `SalesBookingTest`: 15 tests, 156 assertions.
- Production Vite build passed.
- Live MySQL browser verified Marketing Campaigns, Lead Activities, Sales Funnel & Performance and Sales Booking at desktop and 390×844 mobile widths with no horizontal page overflow or browser console errors.
- Browser verification found and corrected a live MySQL-only invalid `leads.customer_name` selection; lead labels now use the existing customer relationship.
- Full Phase 9 regression gate: 749 tests, 16,521 assertions.

## Phase boundary

Collection receipt processing and Customer Service remain operational and linked from the completed journey, but their architecture completion belongs to Phase 13 with Finance and After-sales.

## Rollback

No schema migration was introduced. Rollback consists of reverting the focused CRM/Sales Actions, DTOs, read services, analytics route/view and navigation mapping. Existing leads, qualifications, visits, inquiries, campaigns, activities, bookings, pricing snapshots, collections, notifications and audit history remain unchanged.
