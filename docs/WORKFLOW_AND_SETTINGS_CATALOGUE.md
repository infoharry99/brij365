# Builder360 ERP-CRM Workflow and Settings Catalogue

All business rules must remain configurable through system settings or module policy records. No statutory rate, payroll formula, SLA, approval chain or vendor credential should be hardcoded.

## Workflow Catalogue

| Workflow | Trigger | Approval / Status Path | Required Controls |
| --- | --- | --- | --- |
| Lead qualification | Lead capture or manual qualification | New -> Qualified / Disposed | Source, project, owner, score and remarks required. |
| Site visit | Visit scheduled | Scheduled -> Completed / Cancelled | Visit date, project, prospect, sales owner and outcome required. |
| Booking | Booking request | Draft -> Confirmed | Customer, unit, quote, payment schedule and availability validation required. |
| Collection receipt | Payment recorded | Draft -> Approved | Booking/customer match, amount, instrument details and finance approval required. |
| Unit price version | Price proposal | Draft -> Approved | Effective date, project/unit scope, price fields and no invalid overlap. |
| Purchase requisition | Material request | Requested -> Approved | Project/site, items, quantity and approver required. |
| Purchase order | PO preparation | Draft -> Approved | Vendor, requisition/reference, items, totals and approval required. |
| Goods receipt | Material receipt | Received | PO/vendor reference, quantities and accepted/rejected counts required. |
| Daily progress report | Site report entry | Submitted -> Approved / Rejected | Project, milestone, progress, labour/equipment and reviewer remarks required. |
| Contractor measurement | Measurement submission | Submitted -> Approved / Rejected | BOQ reference, quantity, rate and approval required. |
| Leave request | Employee request | Requested -> Approved / Rejected | Leave type, dates, balance and manager/HR approval required. |
| Attendance regularization | Employee correction request | Requested -> Approved / Rejected | Attendance date, reason, original/corrected times and approval required. |
| Payroll run | Payroll generation | Generated -> Approved | Period, employees, salary assignments, statutory/tax settings and finance approval required. |
| Bank transfer batch | Approved payroll | Prepared -> Released | Payroll approval, bank details, IFSC/account validation, totals and checksum required. |
| Commission run | Sales result import/calculation | Draft -> Approved / Rejected | Rule, period, sales records and payroll inclusion flag required. |
| Recruitment offer | Candidate offer | Draft -> Released | Candidate, opening, compensation/template placeholders and approval required. |
| Confirmation | Probation due | Due -> Recommended -> Confirmed / Extended / Rejected | Manager recommendation and HR decision required. |
| Separation / F&F | Employee exit | Draft -> HR Approved -> Finance Approved -> Completed | Asset, loan, attendance, leave, notice and settlement blockers must be clear. |
| Possession handover | Customer handover | Initiated -> Letter Issued -> Completed | Payment eligibility, checklist and snags must be resolved or accepted. |
| Service ticket | Complaint raised | Open -> Assigned -> Resolved -> Closed | SLA, owner, customer confirmation and resolution notes required. |
| System setting approval | Config change | Draft -> Active | Version, effective date, owner and approver required. |

## Required Configurable Settings

| Setting Key | Purpose |
| --- | --- |
| `workflow.approval_chains` | Approval levels, role order, escalation and rejection rules. |
| `hr.attendance.rules` | Shift grace, late/early/half-day rules, source controls and regularization limits. |
| `hr.leave.rules` | Leave types, accrual, carry-forward, lapse, encashment and approval hierarchy. |
| `payroll.tax_rules` | Payroll statutory/tax controls and Form 16 readiness metadata. |
| `payroll.commission_rules` | Fixed, percentage, slab and target-based commission configuration. |
| `finance.gst_rules` | GST treatment, return-period locking and finance compliance controls. |
| `after_sales.sla_hours` | SLA hours by priority and escalation readiness. |
| `construction.contractor_billing` | BOQ, measurement, retention and contractor bill control rules. |
| `governance.backup_dr` | Backup schedule, retention, RPO, RTO, restore-test and owner metadata. |

## Integration Adapter Records

| Adapter | Required Metadata |
| --- | --- |
| Biometric attendance | Provider, status, last sync, failure reason, mapped employee identifier and import policy. |
| GPS/geofencing | Provider, status, location rules, accuracy threshold and exception workflow. |
| Payment gateway | Provider, webhook status, secret configured flag, last event and failure reason. |
| Bank transfer | Bank format, batch number, checksum, control total, release status and approver. |
| Email/SMS/WhatsApp | Provider, template, opt-in/allowed channel, last send status and failure reason. |
| Statutory/tax | Rule version, locked period, generator version, issue status and acknowledgement. |
| Backup/DR | Provider, schedule, retention, RPO, RTO, restore-test date, owner and runbook reference. |
