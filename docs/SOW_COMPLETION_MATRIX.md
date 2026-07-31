# Builder360 ERP-CRM SOW Completion Matrix

This matrix maps the approved SOW to the Laravel 12 / PHP 8.2 / Blade + Vite application using MySQL as the accepted local and deployment database for Phase 1A onward.

## Delivery Position

| Area | Delivery Position |
| --- | --- |
| Database | MySQL is the accepted delivery database for local execution and Webuzo/Ubuntu deployment readiness. |
| UI/UX | Approved standalone UI/UX remains the visual reference. Laravel serves the application through Blade and Vite. |
| Integrations | External services are represented through configurable provider/readiness records unless live vendor credentials are supplied. |
| Statutory validation | Payroll, GST, RERA, labour-law, tax and legal correctness require client or appointed-expert validation before production reliance. |
| Acceptance | UAT must be completed using `docs/UAT_ACCEPTANCE_CHECKLIST.md`. |

## Functional SOW Coverage

| SOW Area | Laravel Coverage | Primary UI / Route Areas | Acceptance Gate |
| --- | --- | --- | --- |
| Management & Collaboration | Covered | `/`, `/governance/management-summary`, `/governance/report-register`, `/notifications`, `/collaboration/tasks`, `/collaboration/calendar-events`, `/collaboration/messages`, `/governance/audit-events` | Dashboard, tasks, calendar, messages, notifications, reports and audit views open and enforce roles. |
| Sales & CRM | Covered | `/crm/leads`, `/crm/lead-qualifications`, `/crm/site-visits`, `/crm/campaigns`, `/crm/prospect-inquiries`, `/sales/bookings`, `/finance/collections` | Lead-to-booking-to-collection flow passes UAT. |
| Projects & Inventory | Covered | `/projects`, `/inventory/units`, `/inventory/unit-price-versions`, `/projects/cost-roi/export` | Project, unit availability, pricing approval and cost/ROI export pass UAT. |
| Construction | Covered | `/construction/milestones`, `/construction/daily-progress-reports`, `/construction/boq-items`, `/construction/contractor-measurements`, `/construction/contractor-bills` | Site progress, BOQ, measurement and contractor-bill approvals pass UAT. |
| Procurement & Store | Covered | `/procurement/dashboard`, `/procurement/vendors`, `/procurement/requisitions`, `/procurement/purchase-orders`, `/procurement/goods-receipts`, `/procurement/stock-items` | Requisition-to-PO-to-GRN and stock movements pass UAT. |
| HRMS | Covered | `/hr/employees`, `/hr/employee-documents`, `/hr/attendance-records`, `/hr/attendance-shifts`, `/hr/attendance-regularizations`, `/hr/leave-requests`, `/hr/leave-balances`, `/hr/assets`, `/hr/expense-claims`, `/hr/loans`, `/hr/helpdesk-tickets` | Employee master, attendance, leave, documents, claims, loans and ESS flows pass UAT. |
| Payroll | Covered | `/payroll/runs`, `/payroll/components`, `/payroll/salary-structures`, `/payroll/bank-transfer-batches`, `/payroll/commission-rules`, `/payroll/commission-runs`, `/payroll/tax-documents` | Payroll generation, approval, bank batch, commission and tax-document controls pass UAT. |
| Recruitment & Performance | Covered | `/recruitment/job-openings`, `/recruitment/candidates`, `/recruitment/interviews`, `/recruitment/offers`, `/recruitment/source-summary`, `/hr/performance-cycles`, `/hr/performance-reviews` | Candidate-to-offer and performance review cycles pass UAT. |
| Employee Lifecycle | Covered | `/hr/confirmation-cases`, `/hr/separation-settlements`, `/hr/exit-interviews`, `/hr/employees/{employee}/movements` | Confirmation, movement, separation, F&F and exit workflows pass UAT. |
| Finance | Covered | `/finance/dashboard`, `/finance/collections`, `/finance/payment-requests`, `/finance/vouchers`, `/finance/gst-entries`, `/finance/gst-return-periods` | Voucher, GST, collection and payment-request approvals pass UAT. |
| Legal & RERA | Covered | `/legal/rera-registrations`, `/legal/project-approvals`, `/legal/compliance-obligations` | Registration, approval, validity and compliance-tracking records pass UAT. |
| Documents | Covered | `/documents`, `/documents/categories`, `/hr/employees/{employee}/documents`, `/buyer/documents` | Category, upload, approval, expiry and scoped downloads pass UAT. |
| Possession | Covered | `/possession/handovers`, `/possession/snags` | Eligibility, checklist, snags, letter issue and handover completion pass UAT. |
| After-Sales & Maintenance | Covered | `/after-sales/tickets`, `/after-sales/work-orders`, `/maintenance/societies`, `/maintenance/dues`, `/maintenance/handover-items` | Ticket SLA, work orders, society setup, dues and reminders pass UAT. |
| Customer Channels | Covered | `/buyer/summary`, `/buyer/bookings`, `/buyer/payment-requests`, `/buyer/receipts`, `/buyer/service-tickets`, `/partner/summary`, `/partner/leads`, `/partner/bookings`, `/prospect-inquiries` | Buyer, partner and public inquiry scopes pass UAT. |
| Admin & Governance | Covered | `/admin/users`, `/admin/roles`, `/settings/system-settings`, `/settings/data-imports`, `/operations/readiness`, `/governance/audit-events` | Users, roles, settings, imports, audit and readiness pass UAT. |

## Cross-Functional Capability Coverage

| Capability | Delivery Rule |
| --- | --- |
| Role-based access | Enforced through Laravel auth middleware, role records, policies and request authorization. |
| Record scoping | Company, project, department, employee, buyer and partner scoping must be verified in UAT for screens and exports. |
| Workflows | Approvals, rejection remarks, status changes and role order are managed by services, policies and configurable settings. |
| Configuration-first rules | Approval chains, SLA, HR attendance/leave, payroll, commission, GST, backup/DR and document controls are system settings. |
| Audit trail | Major create, update, approve, reject, complete and export actions must generate audit events. |
| Notifications | Workflow, reminder and operational alerts are represented in `user_notifications` and notification routes. |
| Imports | Data import preview/post/reconciliation is handled through settings data-import routes. |
| Exports | Governed export limits apply through report and module export controls. |
| Readiness | `/operations/readiness` is the operational acceptance view for database, storage, queue, scheduler, assets, backup, integrations and security. |

## Final Acceptance Rule

The SOW is accepted only when the full automated test suite passes, all UAT scenarios are signed, no critical defects remain, and the client confirms statutory/legal/payroll validation through its responsible experts.
