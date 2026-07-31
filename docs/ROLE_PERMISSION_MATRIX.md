# Builder360 ERP-CRM Role and Permission Matrix

This matrix is the SOW delivery baseline. The application must enforce these permissions through Laravel authentication, policies, request validation and scoped queries.

| Role | Scope | Main Access | Restricted Areas |
| --- | --- | --- | --- |
| Director | Global | Executive dashboard, all business modules, reports, approvals, audit and configuration oversight. | None except technical secrets and external credentials. |
| Sales Head | Department / Sales | CRM, leads, qualification, site visits, bookings, sales analytics, partner follow-up and collections visibility. | HR payroll, admin configuration, confidential HR records and finance final approvals. |
| Construction Head | Department / Construction | Construction milestones, daily progress, BOQ, contractor measurement, contractor bills, procurement visibility and site reports. | Payroll, HR confidential data, finance final approvals and system settings. |
| Finance Head | Department / Finance | Finance dashboard, collections, vouchers, GST, payment requests, payroll approval visibility, bank batches and reports. | HR confidential records except payroll-linked summaries. |
| HR Manager | Department / HR | Employee master, attendance, leave, recruitment, performance, lifecycle, HR reports, HR settings and confidential HR workflows. | Finance final release, system administrator settings and unrelated department data unless assigned. |
| Employee | Self | Own profile, documents, leave, attendance, claims, loans, helpdesk, policy acknowledgements, tax documents and settlement visibility. | Other employee records, payroll processing, admin, finance approval and audit. |
| Payroll | Department / Payroll | Payroll runs, salary structures, payroll components, commissions, bank batch preparation and tax documents. | User administration, unrelated operational approvals and confidential exit interviews unless payroll-linked. |
| Recruiter | Department / Recruitment | Job openings, candidates, interviews, offers, source reports and candidate conversion. | Payroll, finance, confidential HR lifecycle records and admin settings. |
| Compliance | Read / Verify | Legal/RERA compliance, statutory configuration verification, compliance reports and tax-document verification status. | Payroll processing, employee confidential content beyond compliance need and system administration. |
| Auditor | Read-only | Approved records, audit events, readiness, configuration versions, reports and exports permitted for audit. | Create/update/approve actions and confidential content not authorized for audit scope. |
| System Administrator | Global configuration | Users, roles, permissions, settings, imports, readiness, backup/DR metadata and operational governance. | Business approvals where segregation of duties applies. |
| Buyer | Buyer scope | Buyer portal, own bookings, receipts, payment requests, documents and service tickets. | Internal CRM, HR, finance, construction, admin and other buyers' records. |
| Channel Partner | Partner scope | Partner dashboard, assigned leads, bookings and partner-visible records. | Internal CRM sidebar, HR, payroll, admin, finance approval, audit and system settings. |
| Executive Partner (Broker) | Partner scope | Same as Channel Partner: partner dashboard, assigned leads, bookings and partner-visible records. | Same restrictions as Channel Partner. |

## Enforcement Requirements

- Every controller action must authorize the authenticated user.
- Every export must apply the same scope as the screen list.
- Sensitive HR, payroll, tax and exit-interview fields must be masked or hidden unless explicitly permitted; this is the baseline sensitive-field visibility rule.
- Approval workflows must prevent users from approving their own restricted transactions where segregation of duties applies.
- Buyer and partner users must never receive internal-only navigation or records.
