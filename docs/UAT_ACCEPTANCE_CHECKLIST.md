# Builder360 ERP-CRM UAT Acceptance Checklist

Use this checklist to confirm SOW acceptance. A module is accepted only when the responsible client user signs off the relevant workflow and no critical defect remains.

## Environment

| Check | Expected Result | Status |
| --- | --- | --- |
| MySQL setup | `.env` uses `DB_CONNECTION=mysql`; `php artisan migrate --seed` completes against the configured MySQL database. | Pending |
| Build | `npm run build` completes. | Pending |
| Readiness | `/operations/readiness` returns `ok` for authorized operational users. | Pending |
| Login | Seeded authorized users can log in; inactive/unauthorized users cannot access restricted routes. | Pending |
| Storage | Document upload/download works according to permissions. | Pending |
| Scheduler | Required scheduled commands are registered. | Pending |

## Functional UAT

| SOW Area | Scenario | Expected Result | Status |
| --- | --- | --- | --- |
| Management | Director opens dashboard and management reports. | KPIs, reports, notifications and approvals are visible according to role. | Pending |
| Collaboration | User creates task, calendar event and message. | Assignment, status updates, export and audit records work. | Pending |
| CRM | Sales user creates lead, qualifies it and schedules site visit. | Lead stage, qualification, site visit and activity history update. | Pending |
| Sales | Booking is created for an available unit. | Unit/customer/payment schedule are validated and booking is stored. | Pending |
| Collections | Finance records and approves a collection receipt. | Amount is linked to booking and approval/audit is captured. | Pending |
| Projects | Admin creates/updates project and team assignments. | Project data and assigned team are visible to scoped users. | Pending |
| Inventory | Pricing version is created and approved. | Effective pricing and unit availability are controlled. | Pending |
| Construction | Daily progress report is submitted and approved. | Milestone progress, remarks and audit trail update. | Pending |
| BOQ/Contractor | Contractor measurement and bill are approved. | BOQ quantity/rate controls and approval trail work. | Pending |
| Procurement | Requisition, PO and goods receipt are processed. | Vendor, stock and approval records update correctly. | Pending |
| HR Employee | HR creates employee, uploads documents and records movement. | Employee master, documents, expiry and movement history work. | Pending |
| Attendance | Shift, attendance and regularization are processed. | Late/early/missing checkout and approval behavior is correct. | Pending |
| Leave | Employee requests leave and manager/HR approves. | Balance, status, ledger and notifications update. | Pending |
| Payroll | Payroll run is generated and approved. | Salary structure, payroll controls, net pay and audit records work. | Pending |
| Statutory payroll authority | Compliance maker prepares and a different authorized checker approves a current official-source-backed central/state pack. | Source evidence, checksum, applicability, effective dates and maker-checker separation validate; unapproved packs remain fail-closed. | Pending |
| Payroll shadow cutover | Payroll completes two governed shadow cycles against the approved current process. | Every unexplained variance is resolved and signed off before authoritative cutover. | Pending |
| Employee tax proofs | Employee submits declarations/proofs; independent Payroll/Compliance users verify and lock the profile. | Private proof access, checksum, maker-checker, superseding amendment and tamper detection work. | Pending |
| Form 16 authority | Payroll generates the governed tax-document output using the client-approved production template. | No unapproved template is issued; access and audit controls pass. | Pending |
| Bank batch | Bank transfer batch is prepared and released. | Account/IFSC/totals/checksum controls pass. | Pending |
| Recruitment | Candidate, interview and offer are processed. | Stage history, scheduling, release and source reports work. | Pending |
| Performance | Review cycle and employee review are processed. | Self/manager submission and close controls work. | Pending |
| Performance scoring integrity | Authorized users create, validate, independently approve and activate a performance formula, then tamper-check its checksum evidence. | Invalid checksum/effective slot fails closed; identical version-pinned evidence produces the same append-only result. | Pending |
| Performance override | HR requests an evidenced override and a different authorized approver decides it. | Formula result remains preserved, override creates linked evidence, and the closed review is not silently recalculated. | Pending |
| Performance shadow cutover | One complete governed review cycle runs in shadow mode. | Calculated/manual differences are reviewed and signed off before results become authoritative. | Pending |
| Roster simulation | HR simulates roster impact before publication. | Simulation is deterministic, reports precedence/ambiguity, and does not mutate roster, attendance or payroll data. | Pending |
| Roster publication | HR generates and publishes one schedule period with rotations, rest/coverage validation and notifications. | Generation is idempotent; invalid conflicts block publication; published evidence is retained. | Pending |
| Shift swap | Employee requests a swap and authorized manager/HR approves or rejects it. | Scope, lock, overlap, rest, coverage, duplicate and audit controls pass. | Pending |
| Attendance finalization | HR finalizes attendance and Payroll consumes the immutable snapshot. | Finalized evidence is the only payable-day source; locked periods require authorized reasoned reopen. | Pending |
| Lifecycle | Confirmation and F&F settlement are processed. | Approval order and blockers are enforced. | Pending |
| Finance | Voucher and GST return period are approved/locked. | Finance approvals, GST status and exports work. | Pending |
| Legal/RERA | RERA and project approvals are verified. | Validity status, reminders and document reference work. | Pending |
| Documents | Managed document is uploaded, approved and downloaded. | Version, category, expiry and permission controls work. | Pending |
| Possession | Handover checklist, snags and letter issue are processed. | Completion is blocked until required conditions are met. | Pending |
| After-sales | Service ticket is assigned, resolved and closed. | SLA, owner and customer history update. | Pending |
| Maintenance | Society, dues and common-area handover are managed. | Due reminders, payment status and handover sign-off work. | Pending |
| Buyer Portal | Buyer views bookings, receipts, documents and tickets. | Buyer sees only own scoped records. | Pending |
| Partner Portal | Channel Partner/Broker views leads and bookings. | Partner sees only partner-scoped records and no internal modules. | Pending |
| Admin | Admin manages users, roles and settings. | Permissions and setting approval/versioning work. | Pending |
| Audit | Auditor exports audit events. | Read-only access and scoped exports work. | Pending |

## Final Acceptance

- All critical and high defects are closed.
- Role-wise access is signed off.
- Data migration/reconciliation is signed off if client data is imported.
- Reports and exports are signed off.
- Statutory/legal/payroll/GST/RERA settings are validated by the client or appointed expert.
- Client signs final acceptance certificate.
