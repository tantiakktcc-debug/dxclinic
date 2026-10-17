# DXCLINIC — PLATFORM FINANCIAL SOURCE-OF-TRUTH MATRIX

> Revision 40 — Addendum B (Mandatory Gate Document)
> Generated from actual MySQL schema inspection 2026-09-29

---

## Purpose

This document maps every commercial transaction type to its **single authoritative record**. No platform reporting layer may create duplicate financial truth.

---

## Commercial Transaction Sources

### 1. INITIAL LICENSE SALE

| Field | Source Table | Column |
|---|---|---|
| Authoritative Record | `licenses` | row where `is_renewal = 0` |
| Clinic | `licenses.clinic_id` | → clinics |
| Plan | `licenses.plan_id` | → subscription_plans |
| Gross Amount | `licenses.amount` | decimal(10,2) |
| Discount | `licenses.discount_amount` | decimal(10,2) |
| Net Amount | `licenses.net_amount` | decimal(10,2) |
| Additional Doctor Seats | `licenses.additional_doctor_seats` | int |
| Additional Doctor Amount | `licenses.additional_doctor_amount` | decimal(10,2) |
| Payment Method | `licenses.payment_method` | varchar(50) |
| Payment Reference | `licenses.payment_reference` | varchar(100) |
| Payment Date | `licenses.payment_date` | date |
| Issued By | `licenses.issued_by` | → developer_users |
| Issued At | `licenses.issued_at` | timestamp |
| **Request Source** | `license_requests` | `fulfilled_license_id` links back |

**Collection Status Derivation:**
- If `payment_method IS NOT NULL AND payment_date IS NOT NULL` → COLLECTED
- Otherwise → OUTSTANDING

---

### 2. LICENSE RENEWAL

| Field | Source Table | Column |
|---|---|---|
| Authoritative Record | `license_renewals` | immutable audit log |
| Clinic | `license_renewals.clinic_id` | → clinics |
| New License | `license_renewals.license_id` | → licenses (new) |
| Previous License | `license_renewals.previous_license_id` | → licenses (old) |
| Plan | `license_renewals.plan_id` | → subscription_plans |
| Base Amount | `license_renewals.base_amount` | decimal(10,2) |
| Doctor Seat Amount | `license_renewals.total_doctor_seat_amount` | decimal(10,2) |
| Discount | `license_renewals.discount_amount` | decimal(10,2) |
| Net Amount | `license_renewals.net_amount` | decimal(10,2) |
| Payment Received | `license_renewals.payment_received` | boolean |
| Payment Method | `license_renewals.payment_method` | varchar(50) |
| Payment Date | `license_renewals.payment_date` | date |
| Payment Reference | `license_renewals.payment_reference` | varchar(100) |
| Renewed By | `license_renewals.renewed_by` | → developer_users |
| Renewal Number | `license_renewals.renewal_number` | int |

**Collection Status Derivation:**
- `payment_received = 1` → COLLECTED
- `payment_received = 0` → OUTSTANDING

---

### 3. MID-TERM DOCTOR ADD-ON

| Field | Source Table | Column |
|---|---|---|
| Authoritative Record | `clinic_doctor_seat_addons` | |
| Clinic | `clinic_doctor_seat_addons.clinic_id` | → clinics |
| License | `clinic_doctor_seat_addons.license_id` | → licenses |
| Doctor (optional) | `clinic_doctor_seat_addons.doctor_id` | → doctors |
| Seats | `clinic_doctor_seat_addons.seats_count` | int |
| Price | `clinic_doctor_seat_addons.price` | decimal(10,2) |
| Payment Method | `clinic_doctor_seat_addons.payment_method` | varchar(50) |
| Payment Reference | `clinic_doctor_seat_addons.payment_reference` | varchar(100) |
| Payment Date | `clinic_doctor_seat_addons.payment_date` | date |
| Created By | `clinic_doctor_seat_addons.created_by_developer_user_id` | → developer_users |

**Collection Status Derivation:**
- `payment_date IS NOT NULL` → COLLECTED
- Otherwise → OUTSTANDING

---

### 4. PLATFORM EXPENSE

| Field | Source |
|---|---|
| Authoritative Record | **DOES NOT EXIST YET** |
| Required: | New `platform_expenses` table |
| Scope: | Platform-level only, NOT tenant clinic expenses |
| The existing `expenses` table is TENANT-OWNED (has `clinic_id`) | Must remain separate |

---

## Revenue Aggregation Formulas

```
Gross Sales = Σ(licenses.amount WHERE is_renewal=0)
            + Σ(license_renewals.base_amount + license_renewals.total_doctor_seat_amount)
            + Σ(clinic_doctor_seat_addons.price)

Discounts = Σ(licenses.discount_amount)
          + Σ(license_renewals.discount_amount)

Net Sales = Gross Sales - Discounts

Collections = Net amounts WHERE payment confirmed
Outstanding = Net amounts WHERE payment NOT confirmed

Operating Profit = Net Sales - Σ(platform_expenses.amount)
```

---

## No-Duplication Rules

1. **Platform invoices** must REFERENCE source transactions, not store independent amounts
2. **`platform_payments`** table: DO NOT CREATE unless the audit proves that the payment fields in `licenses`, `license_renewals`, and `clinic_doctor_seat_addons` are insufficient
3. **Collection totals** must be derived from the authoritative payment fields above
4. **Tenant `payments` table** (94 rows, patient collections) is COMPLETELY SEPARATE from platform commercial transactions

---

## Source Data Availability Summary (Revision 42 Status)

| Transaction Type | Table | Has Amount | Has Payment Method | Has Payment Date | Has Reference | Invoicing Entity |
|---|---|---|---|---|---|---|
| Initial License | `licenses` | ✅ | ✅ | ✅ | ✅ | `platform_sales_invoices` (`INITIAL_LICENSE`) |
| Renewal | `license_renewals` | ✅ | ✅ | ✅ | ✅ | `platform_sales_invoices` (`LICENSE_RENEWAL`) |
| Doctor Add-on | `clinic_doctor_seat_addons` | ✅ | ✅ | ✅ | ✅ | `platform_sales_invoices` (`DOCTOR_ADDON`) |
| Platform Expense | `platform_expenses` | ✅ | ✅ | ✅ | ✅ | N/A (Provider overhead expense) |

---

## Conclusion (Revision 42 Verified)

> [!IMPORTANT]
> All three revenue transaction types derive payment metadata from their authoritative source records.
> A separate `platform_payments` table was **STRICTLY NOT CREATED**.
> Implemented tables:
> 1. `platform_invoice_sequences` — atomic sequence generator (`INV-YYYY-XXXXX`)
> 2. `platform_sales_invoices` — immutable commercial document referencing authoritative source
> 3. `platform_invoice_items` — itemized lines (Base Plan, Doctor Seats, Discount)
> 4. `platform_expenses` — provider operational overhead (cloud hosting, SMS, maintenance; strictly NO `clinic_id`)

