# DXCLINIC — REVISION 40 — RELATIONSHIP ISOLATION GRAPH

> Generated: 2026-09-29 | Method: Forensic Foreign Key & Entity Graph Inspection  
> Scope: 51 Database Tables Classified by Multi-Tenant Boundary  
> Purpose: Identify every direct and indirect path to `clinic_id` and document all potential cross-tenant IDOR vulnerabilities.

---

## 1. Graph Overview

```
                                    ┌──────────────────────┐
                                    │    clinics (Root)    │
                                    └──────────┬───────────┘
                                               │
         ┌─────────────────────────────────────┼─────────────────────────────────────┐
         ▼                                     ▼                                     ▼
┌──────────────────┐                 ┌──────────────────┐                  ┌──────────────────┐
│  Direct Models   │                 │ Indirect Models  │                  │  Global Masters  │
│ 28 Tables        │                 │ 7 Tables         │                  │ 4 Tables         │
│ (Has clinic_id)  │                 │ (via Parent FK)  │                  │ (No clinic_id)   │
└──────────────────┘                 └──────────────────┘                  └──────────────────┘
```

---

## 2. Direct Tenant-Owned Tables (28 Tables)

These tables contain an authoritative `clinic_id` column. Queries MUST always include `WHERE clinic_id = ?`:

| # | Table Name | Eloquent Model | Foreign Key | Parent Relation | Tenant Authorization Boundary |
|---|---|---|---|---|---|
| 1 | `appointments` | [Appointment](file:///e:/dxclinic/app/Models/Appointment.php) | `clinic_id` | `belongsTo(Clinic)` | Validated against `TenantContext::clinicId()` |
| 2 | `audit_logs` | [AuditLog](file:///e:/dxclinic/app/Models/AuditLog.php) | `clinic_id` | `belongsTo(Clinic)` | Admin / Tenant Audit Boundary |
| 3 | `backups` | [Backup](file:///e:/dxclinic/app/Models/Backup.php) | `clinic_id` | `belongsTo(Clinic)` | Strictly Admin role; blocked for Operators |
| 4 | `bank_accounts` | [BankAccount](file:///e:/dxclinic/app/Models/BankAccount.php) | `clinic_id` | `belongsTo(Clinic)` | Clinic Admin Financial Boundary |
| 5 | `cash_sessions` | [CashSession](file:///e:/dxclinic/app/Models/CashSession.php) | `clinic_id` | `belongsTo(Clinic)` | Shift Cash Session Boundary |
| 6 | `clinic_doctor_seat_addons` | [ClinicDoctorSeatAddon](file:///e:/dxclinic/app/Models/ClinicDoctorSeatAddon.php) | `clinic_id` | `belongsTo(Clinic)` | Superadmin Licensing Ledger |
| 7 | `consultation_bills` | [ConsultationBill](file:///e:/dxclinic/app/Models/ConsultationBill.php) | `clinic_id` | `belongsTo(Clinic)` | OPD Billing Boundary |
| 8 | `diagnostic_bills` | [DiagnosticBill](file:///e:/dxclinic/app/Models/DiagnosticBill.php) | `clinic_id` | `belongsTo(Clinic)` | Diagnostic Billing Boundary |
| 9 | `diagnostic_items` | [DiagnosticItem](file:///e:/dxclinic/app/Models/DiagnosticItem.php) | `clinic_id` | `belongsTo(Clinic)` | Investigation Master Catalog |
| 10 | `doctor_consultation_types` | [DoctorConsultationType](file:///e:/dxclinic/app/Models/DoctorConsultationType.php) | `clinic_id` | `belongsTo(Clinic)` | Consultation Fee Master |
| 11 | `doctors` | [Doctor](file:///e:/dxclinic/app/Models/Doctor.php) | `clinic_id` | `belongsTo(Clinic)` | Medical Staff Directory |
| 12 | `expenses` | [Expense](file:///e:/dxclinic/app/Models/Expense.php) | `clinic_id` | `belongsTo(Clinic)` | Cash Drawer Expense Voucher |
| 13 | `fee_masters` | [FeeMaster](file:///e:/dxclinic/app/Models/FeeMaster.php) | `clinic_id` | `belongsTo(Clinic)` | Clinic Standard Fee Schedule |
| 14 | `fund_transfers` | [FundTransfer](file:///e:/dxclinic/app/Models/FundTransfer.php) | `clinic_id` | `belongsTo(Clinic)` | Cash-to-Bank Deposit Ledger |
| 15 | `journals` | [Journal](file:///e:/dxclinic/app/Models/Journal.php) | `clinic_id` | `belongsTo(Clinic)` | Double-entry Journal Header |
| 16 | `license_activations` | [LicenseActivation](file:///e:/dxclinic/app/Models/LicenseActivation.php) | `clinic_id` | `belongsTo(Clinic)` | Activation Audit Record |
| 17 | `license_renewals` | [LicenseRenewal](file:///e:/dxclinic/app/Models/LicenseRenewal.php) | `clinic_id` | `belongsTo(Clinic)` | Immutable Renewal Ledger |
| 18 | `license_requests` | [LicenseRequest](file:///e:/dxclinic/app/Models/LicenseRequest.php) | `clinic_id` | `belongsTo(Clinic)` | Commercial Request Ledger |
| 19 | `licenses` | [License](file:///e:/dxclinic/app/Models/License.php) | `clinic_id` | `belongsTo(Clinic)` | Subscription License Record |
| 20 | `patients` | [Patient](file:///e:/dxclinic/app/Models/Patient.php) | `clinic_id` | `belongsTo(Clinic)` | Patient Medical Record (EMR) |
| 21 | `payments` | [Payment](file:///e:/dxclinic/app/Models/Payment.php) | `clinic_id` | `belongsTo(Clinic)` | Transaction Payment Record |
| 22 | `prescription_pad_templates`| [PrescriptionPadTemplate](file:///e:/dxclinic/app/Models/PrescriptionPadTemplate.php)| `clinic_id` | `belongsTo(Clinic)` | Prescription Studio Template |
| 23 | `printer_profiles` | [PrinterProfile](file:///e:/dxclinic/app/Models/PrinterProfile.php) | `clinic_id` | `belongsTo(Clinic)` | Hardware Calibration Profile |
| 24 | `receipts` | [Receipt](file:///e:/dxclinic/app/Models/Receipt.php) | `clinic_id` | `belongsTo(Clinic)` | Numbered Financial Receipt |
| 25 | `refunds` | [Refund](file:///e:/dxclinic/app/Models/Refund.php) | `clinic_id` | `belongsTo(Clinic)` | Bill Refund Ledger |
| 26 | `token_sequences` | [TokenSequence](file:///e:/dxclinic/app/Models/TokenSequence.php) | `clinic_id` | `belongsTo(Clinic)` | Daily Token Sequence Generator |
| 27 | `users` | [User](file:///e:/dxclinic/app/Models/User.php) | `clinic_id` | `belongsTo(Clinic)` | Clinic User / Staff Accounts |
| 28 | `visits` | [Visit](file:///e:/dxclinic/app/Models/Visit.php) | `clinic_id` | `belongsTo(Clinic)` | Clinical Encounter Record |

---

## 3. Indirect Tenant-Dependent Tables (7 Tables)

These tables **do not possess a direct `clinic_id` column** and depend strictly on their parent table to resolve tenant ownership:

```
vitals ──────────────► visits ─────────────────────────► clinic_id
prescription_pad_elements ► prescription_pad_templates ──► clinic_id
diagnostic_bill_items ► diagnostic_bills ──────────────► clinic_id
journal_entries ─────► journals ───────────────────────► clinic_id
receipt_revisions ───► receipts ───────────────────────► clinic_id
license_events ──────► licenses ───────────────────────► clinic_id
license_request_events ► license_requests ─────────────► clinic_id
```

### Forensic Breakdown of Indirect Isolation & IDOR Vulnerabilities

| # | Table Name | Parent Table & FK | Path to `clinic_id` | Tenant Resolver | Cross-Tenant IDOR Risk & Defense |
|---|---|---|---|---|---|
| 1 | `vitals` | `visits` (`visit_id`) | `vitals.visit_id` &rarr; `visits.clinic_id` | `$vital->visit->clinic_id` | **HIGH RISK**: Direct route model binding `GET /vitals/{vital}` without scoping to `TenantContext::clinicId()` leaks clinical vital signs. **Defense**: Scope via visit parent. |
| 2 | `prescription_pad_elements` | `prescription_pad_templates` (`template_id`) | `elements.template_id` &rarr; `templates.clinic_id` | `$elem->template->clinic_id` | **MEDIUM RISK**: Direct API update of element by ID could overwrite another clinic's template. **Defense**: Scope strictly via `template_id` ownership. |
| 3 | `diagnostic_bill_items` | `diagnostic_bills` (`diagnostic_bill_id`) | `items.diagnostic_bill_id` &rarr; `bills.clinic_id` | `$item->bill->clinic_id` | **MEDIUM RISK**: Item rate snapshotting must not be queryable across tenants. **Defense**: Scope via `diagnostic_bill_id`. |
| 4 | `journal_entries` | `journals` (`journal_id`) | `entries.journal_id` &rarr; `journals.clinic_id` | `$entry->journal->clinic_id` | **HIGH RISK**: Financial ledger queries without joining `journals` would aggregate financial debits/credits across tenants. |
| 5 | `receipt_revisions` | `receipts` (`receipt_id`) | `revisions.receipt_id` &rarr; `receipts.clinic_id` | `$rev->receipt->clinic_id` | **MEDIUM RISK**: Audit trail of edited receipts must be isolated to clinic. |
| 6 | `license_events` | `licenses` (`license_id`) | `events.license_id` &rarr; `licenses.clinic_id` | `$event->license->clinic_id` | **LOW RISK (Superadmin-only)**: Audit log of license state changes. Must not leak to unauthorized users. |
| 7 | `license_request_events` | `license_requests` (`license_request_id`) | `events.license_request_id` &rarr; `requests.clinic_id` | `$event->request->clinic_id` | **LOW RISK (Superadmin-only)**: Audit log of commercial request workflow. |

---

## 4. Shared / Global Platform Tables (No `clinic_id`)

| Table Name | Role | Shared Status | Tenant Hazard Assessment |
|---|---|---|---|
| `expense_categories` | Expense Classification Master | **SHARED GLOBALLY** | **ARCHITECTURAL DEFECT**: Currently shared across all clinics. Editing an expense category name/code affects all clinics. Should be tenant-scoped or split into system defaults + tenant custom. |
| `ledger_accounts` | Standard Chart of Accounts | Shared Standard Chart | Safe: Standard accounting taxonomy (Assets, Liabilities, Equity, Revenue, Expense). |
| `subscription_plans` | SaaS Pricing & Tier Catalog | Shared Platform Plans | Safe: Controlled exclusively by Superadmin. |
| `developer_users` | Platform Fleet Superadmins | Platform Superadmins | Safe: Isolated under `developer` guard. |
| `idempotency_records`| API Replay Prevention | Global Request Hashes | Safe: Keyed by request hash and UUID. |
| `clinic_sequences` | Clinic Code Allocator | Atomic Counter | Safe: Monotonic counter for issuing `NX0001`, `NX0002`. |
