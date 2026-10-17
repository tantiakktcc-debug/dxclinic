# DXCLINIC — REVISION 40 — TENANT OWNERSHIP MATRIX

> Generated from actual MySQL schema inspection 2026-09-29
> 51 tables classified

---

## Classification Key

| Category | Meaning |
|---|---|
| **TENANT OWNED** | Has authoritative `clinic_id` column. Data belongs to a specific clinic. |
| **GLOBAL PLATFORM** | No `clinic_id`. Shared across all tenants. Read-only from tenant perspective. |
| **MIXED / REFERENCE** | Has optional `clinic_id` or polymorphic ownership. |
| **INHERITED** | No direct `clinic_id` but ownership derived through parent foreign key. |
| **INFRASTRUCTURE** | Laravel framework tables. Not business data. |

---

## TENANT OWNED (28 tables)

| Table | Ownership Column | Authority Path |
|---|---|---|
| `appointments` | `clinic_id` → clinics(id) | Direct |
| `audit_logs` | `clinic_id` → clinics(id) | Direct |
| `backups` | `clinic_id` → clinics(id) | Direct |
| `bank_accounts` | `clinic_id` → clinics(id) | Direct |
| `cash_sessions` | `clinic_id` → clinics(id) | Direct |
| `clinic_doctor_seat_addons` | `clinic_id` → clinics(id) | Direct |
| `consultation_bills` | `clinic_id` → clinics(id) | Direct |
| `diagnostic_bills` | `clinic_id` → clinics(id) | Direct |
| `diagnostic_items` | `clinic_id` → clinics(id) | Direct (nullable — seeded items are global) |
| `doctor_consultation_types` | `clinic_id` → clinics(id) | Direct |
| `doctors` | `clinic_id` → clinics(id) | Direct |
| `expenses` | `clinic_id` → clinics(id) | Direct |
| `fee_masters` | `clinic_id` → clinics(id) | Direct (+ optional doctor_id) |
| `fund_transfers` | `clinic_id` → clinics(id) | Direct |
| `journals` | `clinic_id` → clinics(id) | Direct (nullable) |
| `licenses` | `clinic_id` → clinics(id) | Direct |
| `license_activations` | `clinic_id` → clinics(id) | Direct |
| `license_renewals` | `clinic_id` → clinics(id) | Direct |
| `patients` | `clinic_id` → clinics(id) | Direct |
| `payments` | `clinic_id` → clinics(id) | Direct |
| `prescription_pad_templates` | `clinic_id` → clinics(id) | Direct |
| `printer_profiles` | `clinic_id` → clinics(id) | Direct |
| `receipts` | `clinic_id` → clinics(id) | Direct |
| `refunds` | `clinic_id` → clinics(id) | Direct |
| `token_sequences` | `clinic_id` → clinics(id) | Direct |
| `users` | `clinic_id` → clinics(id) | Direct (nullable for superadmin) |
| `visits` | `clinic_id` → clinics(id) | Direct |
| `vitals` | via `visit_id` → visits → clinic_id | Inherited |

---

## INHERITED OWNERSHIP (5 tables)

| Table | Parent FK | Ultimate Clinic Path |
|---|---|---|
| `diagnostic_bill_items` | `diagnostic_bill_id` → diagnostic_bills | → clinic_id |
| `journal_entries` | `journal_id` → journals | → clinic_id |
| `license_events` | `license_id` → licenses | → clinic_id |
| `prescription_pad_elements` | `template_id` → prescription_pad_templates | → clinic_id |
| `receipt_revisions` | `receipt_id` → receipts | → clinic_id |

---

## GLOBAL PLATFORM (8 tables)

| Table | Purpose |
|---|---|
| `clinics` | Tenant registry (the tenants themselves) |
| `clinic_sequences` | Global clinic code sequence counter |
| `developer_users` | Superadmin/developer accounts |
| `expense_categories` | Shared expense category catalog |
| `idempotency_records` | Duplicate request prevention |
| `ledger_accounts` | Chart of accounts |
| `license_request_sequences` | Request number sequence |
| `subscription_plans` | Plan catalog |

---

## MIXED / REFERENCE (2 tables)

| Table | Notes |
|---|---|
| `license_requests` | Has optional `clinic_id` (null for new requests, filled on fulfillment). `clinic_code` for renewal requests. Cross-tenant by design — Superadmin reviews all. |
| `license_request_events` | Inherits via `license_request_id`. Cross-tenant audit trail. |

---

## INFRASTRUCTURE (8 tables)

| Table | Framework |
|---|---|
| `cache` | Laravel cache |
| `cache_locks` | Laravel cache locks |
| `failed_jobs` | Laravel queue |
| `job_batches` | Laravel queue |
| `jobs` | Laravel queue |
| `migrations` | Laravel schema tracking |
| `password_reset_tokens` | Laravel auth |
| `sessions` | Laravel sessions |

---

## Cross-Tenant Risk Assessment

### Tables where `clinic_id` is NULLABLE but contains tenant data:

| Table | Column | Risk |
|---|---|---|
| `diagnostic_items` | `clinic_id` NULLABLE | LOW — seeded items shared, clinic items scoped |
| `journals` | `clinic_id` NULLABLE | MEDIUM — opening balance journals may not be scoped |
| `users` | `clinic_id` NULLABLE | LOW — only developer users have NULL clinic_id |

### Tables where indirect cross-tenant access is possible:

| Table | Risk Path | Mitigation |
|---|---|---|
| `prescription_pad_elements` | Template ID in URL → wrong clinic's template | `EnsureTenantContext` validates template ownership |
| `diagnostic_bill_items` | Bill ID → wrong clinic's bill | Parent bill has clinic_id check |
| `vitals` | Visit ID → wrong clinic's vitals | Visit has clinic_id, middleware checks appointment |

---

## CRITICAL SEPARATION: Platform vs Tenant Accounting

```
PLATFORM ACCOUNTING (Superadmin)          TENANT ACCOUNTING (Clinic)
─────────────────────────────────         ────────────────────────────
licenses.amount                           payments.amount
license_renewals.net_amount               consultation_bills.net_amount
clinic_doctor_seat_addons.price           diagnostic_bills.net_amount
platform_expenses (NEW)                   expenses.amount
                                          fund_transfers.amount
                                          journals / journal_entries
                                          receipts / receipt_revisions
                                          cash_sessions
                                          refunds
```

These must NEVER be mixed in queries or reports.
