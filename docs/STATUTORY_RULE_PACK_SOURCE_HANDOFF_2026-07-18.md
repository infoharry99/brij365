# Statutory Payroll Rule-Pack Source Handoff

## Purpose and authority

This record is a compliance handoff, not an active payroll rule pack. It catalogues official primary-source locations inspected for handoff on 18 July 2026 and the decisions that an independent Compliance/Legal maker-checker must verify from current source material before a governed `payroll.statutory_rules` version may be activated. Catalogue inclusion is not legal review, approval, or evidence that a numeric value is current.

Builder360 must continue to fail closed while no verified, approved, effective-dated pack exists. Prototype values, search snippets, this document, and other application settings are not payroll authority.

## Official primary sources catalogued for independent review

| Area | Official source | Handoff purpose | Activation status |
|---|---|---|---|
| Employees' Provident Fund / pension allocation | Employees' Provident Fund Organisation, contribution-rate publication: <https://www.epfindia.gov.in/site_docs/PDFs/MiscPDFs/ContributionRate.pdf> | Contribution basis, employee/employer allocation, wage ceiling, rounding, and exceptions | **Not approved** |
| Employees' Provident Fund applicability and operational interpretation | EPFO FAQ: <https://www.epfindia.gov.in/site_en/FAQ.php/Resource/site_docs/PDFs/Downloads_PDFs/Feedback.php> | Applicability, membership, excluded-employee and operational questions | **Not approved** |
| Employees' State Insurance | Employees' State Insurance Corporation standard note: <https://rohp.esic.gov.in/attachments/publicationfile/Standard_Note_on_Employees_State_Insurance_Scheme_as_on_01_01_2025_English_1757940841.pdf> | Coverage threshold, contribution basis, contribution periods, employee/employer rates, and exceptions | **Not approved** |
| Salary TDS and Income-tax Act 2025 transition | Income Tax Department transition FAQ: <https://www.incometax.gov.in/iec/foportal/help/all-topics/e-filing-services/objective-and-scope-new-act-faq> | Effective-date and tax-year transition | **Not approved** |
| Salary TDS compliance | Income Tax Department TDS guidance: <https://www.incometax.gov.in/iec/foportal/help/all-topics/e-filing-services/tds-compliance> | Employer deduction, projection, evidence, adjustment, deposit, and reporting obligations | **Not approved** |
| Assessment Year 2026-27 salaried-individual guidance | Income Tax Department return-applicability guidance: <https://www.incometax.gov.in/iec/foportal/help/individual/return-applicable-1?leadId=68798332> | Independent reconciliation of the applicable regime, slab, rebate, deduction, and return guidance before preparing any tax-year rule pack | **Not approved** |
| Tax Year 2026-27 rate material | Government of India, Union Budget 2026 memorandum: <https://www.indiabudget.gov.in/doc/memo.pdf> | Current enacted/proposed rate-table reconciliation and effective dates | **Not approved** |
| Maharashtra Profession Tax | Maharashtra GST Department schedule page: <https://www.mahagst.gov.in/en/profession-tax-and-other-rate-schedule> and official rate schedule PDF: <https://www.mahagst.gov.in/public/uploads/mvatservices/1761635577Rate%20Schedules%20under%20the%20Professions%20Tax%20Act%2C%201975%2C.pdf> | State applicability, slabs, periodic exception, deduction and remittance treatment | **Not approved** |
| Maharashtra Profession Tax amendments | Maharashtra GST Department notifications index: <https://www.mahagst.gov.in/en/profession-tax-and-allied-acts-notifications> and notification dated 28 February 2026: <https://www.mahagst.gov.in/public/uploads/mvatservices/1772710759_PT%20Notification%2028.2.2026.pdf> | Confirm return/payment due-date amendments independently from rate schedules and preserve their separate legal effective dates | **Not approved** |
| Maharashtra Labour Welfare Fund | Maharashtra Labour Welfare Board: <https://www.mlwb.in/> and FAQ: <https://www.mlwb.in/faq> | Covered establishments/employees, contribution frequency, employee/employer shares, due dates, and exclusions | **Not approved** |
| Gratuity | India Code, Payment of Gratuity Act: <https://www.indiacode.nic.in/handle/123456789/18656?locale=en> and section 4: <https://www.indiacode.nic.in/show-data?actid=AC_CEN_6_6_00029_197239_1517807324619&orderno=5&sectionId=42148&sectionno=4> | Eligibility, continuous service, wage basis, calculation, ceiling, forfeiture, and exceptions | **Not approved** |
| Statutory bonus | Ministry of Labour and Employment, Payment of Bonus Act publication: <https://labour.gov.in/sites/default/files/thepaymentofbonusact1965.pdf> | Applicability, allocable surplus, eligibility, minimum/maximum bonus, set-on/set-off, and exclusions | **Not approved** |

## Required maker-checker decisions

The proposed pack cannot be submitted for approval until the maker has recorded, and a different authorized checker has independently verified, at least the following:

1. Legal instrument and amendment status on the proposed effective date.
2. Employee and establishment applicability, including exclusions and voluntary coverage.
3. Statutory-state resolution and treatment of employee transfers during a payroll period.
4. Wage/component basis for every rule and the precedence of arrears, incentives, commissions, leave encashment, overtime, and reimbursements.
5. Thresholds, ceilings, slabs, periodic exceptions, contribution frequency, and employee/employer allocation.
6. Rounding method, rounding stage, minimum/maximum constraints, and carry/true-up behavior.
7. Joining, exit, unpaid leave, half day, weekly off, holiday, arrear, reversal, and retroactive-change treatment.
8. Tax-regime selection, declarations, proof review, previous-employer income, projection, and year-end true-up.
9. Deposit, filing, reporting, and correction obligations outside the calculation engine.
10. Golden examples for each boundary and exception, signed by Payroll and Compliance/Legal.

## Pack preparation checklist

- [ ] Create a draft effective-dated pack; do not activate it.
- [ ] Attach downloaded official source copies or immutable evidence references and SHA-256 checksums.
- [ ] Record the source publication/retrieval date and legal effective date separately.
- [ ] Complete typed schema validation and overlap detection.
- [ ] Run non-mutating simulation against boundary and golden cases.
- [ ] Compare the draft against the current manual/legacy payroll calculation.
- [ ] Resolve every unexplained variance.
- [ ] Obtain maker submission and independent Compliance/Legal approval.
- [ ] Schedule activation only after all source evidence and approvals are present.
- [ ] Revalidate every source, amendment status, effective date, and official checksum immediately before activation.
- [ ] Complete two governed shadow payroll cycles before authoritative cutover.

## Explicit exclusions

- This record does not authorize any numeric rate, cap, slab, threshold, or formula.
- It does not replace legal advice, payroll review, statutory registration analysis, or state-specific applicability review.
- It does not authorize production Form 16 issuance or any unapproved Form 16 template, remittance, return filing, accounting posting, adjustment, or reversal semantics.
- It does not authorize historical recalculation or mutation of approved payroll.
