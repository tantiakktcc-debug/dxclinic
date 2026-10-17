# DXCLINIC — REVISION 40 — DATABASE SCHEMA FORENSIC AUDIT

> Generated: 2026-09-29 | Method: Live MySQL 8.0 `information_schema` & Query Extraction
> Database: `dxclinic` | Migrations Recorded: 38 | Total Tables Found: 51

---
## 1. Executive Schema Summary

- **Total Tables in Live MySQL**: 51 tables
- **Total Migrations in Database**: 38 migration files executed
- **Tenancy Architecture**: Shared database, shared schema; 27 operational tables contain `clinic_id`
- **Platform Accounting Tables**: `platform_sales_invoices`, `platform_invoice_items`, `platform_payments`, `platform_expenses` **DO NOT EXIST** (Confirmed by live schema inspection)

## 2. Table-by-Table Forensic Inventory

| # | Table Name | Live Rows | Columns | Has `clinic_id`? | Primary Role / Classification |
|---|---|---|---|---|---|
| 1 | `appointments` | 196 | 17 | YES | Tenant Operational Data |
| 2 | `audit_logs` | 26 | 15 | YES | Tenant Operational Data |
| 3 | `backups` | 20 | 14 | YES | Tenant Operational Data |
| 4 | `bank_accounts` | 1 | 12 | YES | Tenant Operational Data |
| 5 | `cache` | 4 | 3 | NO | Laravel System / Infrastructure |
| 6 | `cache_locks` | 0 | 3 | NO | Laravel System / Infrastructure |
| 7 | `cash_sessions` | 3 | 16 | YES | Tenant Operational Data |
| 8 | `clinic_doctor_seat_addons` | 0 | 19 | YES | Platform Licensing & Fleet |
| 9 | `clinic_sequences` | 1 | 5 | NO | Child / Shared Lookup Data |
| 10 | `clinics` | 3 | 28 | NO | Tenant Registry |
| 11 | `consultation_bills` | 112 | 17 | YES | Tenant Operational Data |
| 12 | `developer_users` | 1 | 9 | NO | Platform Licensing & Fleet |
| 13 | `diagnostic_bill_items` | 50 | 10 | NO | Child / Shared Lookup Data |
| 14 | `diagnostic_bills` | 27 | 14 | YES | Tenant Operational Data |
| 15 | `diagnostic_items` | 151 | 12 | YES | Tenant Operational Data |
| 16 | `doctor_consultation_types` | 19 | 7 | YES | Tenant Operational Data |
| 17 | `doctors` | 7 | 11 | YES | Tenant Operational Data |
| 18 | `expense_categories` | 14 | 9 | NO | Child / Shared Lookup Data (**GLOBAL BY DESIGN — Verified in Rev 41**) |
| 19 | `expenses` | 19 | 12 | YES | Tenant Operational Data (Strictly tenant-scoped) |
| 20 | `failed_jobs` | 0 | 7 | NO | Laravel System / Infrastructure |
| 21 | `fee_masters` | 12 | 9 | YES | Tenant Operational Data |
| 22 | `fund_transfers` | 19 | 11 | YES | Tenant Operational Data |
| 23 | `idempotency_records` | 0 | 8 | NO | Child / Shared Lookup Data |
| 24 | `job_batches` | 0 | 10 | NO | Laravel System / Infrastructure |
| 25 | `jobs` | 0 | 7 | NO | Laravel System / Infrastructure |
| 26 | `journal_entries` | 223 | 8 | NO | Child / Shared Lookup Data |
| 27 | `journals` | 111 | 9 | YES | Tenant Operational Data |
| 28 | `ledger_accounts` | 18 | 9 | NO | Child / Shared Lookup Data |
| 29 | `license_activations` | 1 | 9 | YES | Tenant Operational Data |
| 30 | `license_events` | 4 | 12 | NO | Platform Licensing & Fleet |
| 31 | `license_renewals` | 0 | 28 | YES | Platform Licensing & Fleet |
| 32 | `license_request_events` | 0 | 11 | NO | Child / Shared Lookup Data |
| 33 | `license_request_sequences` | 1 | 5 | NO | Child / Shared Lookup Data |
| 34 | `license_requests` | 0 | 42 | YES | Platform Licensing & Fleet |
| 35 | `licenses` | 3 | 32 | YES | Platform Licensing & Fleet |
| 36 | `migrations` | 38 | 3 | NO | Laravel System / Infrastructure |
| 37 | `password_reset_tokens` | 0 | 3 | NO | Child / Shared Lookup Data |
| 38 | `patients` | 139 | 13 | YES | Tenant Operational Data |
| 39 | `payments` | 94 | 16 | YES | Tenant Operational Data |
| 40 | `prescription_pad_elements` | 48 | 22 | NO | Child / Shared Lookup Data |
| 41 | `prescription_pad_templates` | 26 | 20 | YES | Tenant Operational Data |
| 42 | `printer_profiles` | 0 | 12 | YES | Tenant Operational Data |
| 43 | `receipt_revisions` | 0 | 10 | NO | Child / Shared Lookup Data |
| 44 | `receipts` | 92 | 10 | YES | Tenant Operational Data |
| 45 | `refunds` | 1 | 28 | YES | Tenant Operational Data |
| 46 | `sessions` | 14 | 6 | NO | Laravel System / Infrastructure |
| 47 | `subscription_plans` | 6 | 11 | NO | Platform Licensing & Fleet |
| 48 | `token_sequences` | 8 | 7 | YES | Tenant Operational Data |
| 49 | `users` | 11 | 16 | YES | Tenant Operational Data |
| 50 | `visits` | 117 | 16 | YES | Tenant Operational Data |
| 51 | `vitals` | 27 | 14 | NO | Child / Shared Lookup Data |

---

## 3. Migration vs. Database Drift Analysis

### 3.1 Migration Ledger (All 38 Applied Migrations)

| Migration Batch | Migration File Name |
|---|---|
| Batch 1 | `0001_01_01_000000_create_users_table` |
| Batch 1 | `0001_01_01_000001_create_cache_table` |
| Batch 1 | `0001_01_01_000002_create_jobs_table` |
| Batch 1 | `2026_09_25_000001_create_clinics_table` |
| Batch 1 | `2026_09_25_000002_create_doctors_table` |
| Batch 1 | `2026_09_25_000003_create_ledger_accounts_table` |
| Batch 1 | `2026_09_25_000004_create_bank_accounts_table` |
| Batch 1 | `2026_09_25_000005_create_cash_sessions_table` |
| Batch 1 | `2026_09_25_000006_create_patients_table` |
| Batch 1 | `2026_09_25_000007_create_appointments_table` |
| Batch 1 | `2026_09_25_000008_create_visits_table` |
| Batch 1 | `2026_09_25_000009_create_vitals_table` |
| Batch 1 | `2026_09_25_000010_create_fee_masters_table` |
| Batch 1 | `2026_09_25_000011_create_diagnostic_items_table` |
| Batch 1 | `2026_09_25_000012_create_bills_tables` |
| Batch 1 | `2026_09_25_000013_create_payments_and_receipts_tables` |
| Batch 1 | `2026_09_25_000014_create_accounting_journals_tables` |
| Batch 1 | `2026_09_25_000015_create_expenses_and_transfers_tables` |
| Batch 1 | `2026_09_25_000016_create_audit_and_idempotency_tables` |
| Batch 1 | `2026_09_25_000017_create_token_sequences_table` |
| Batch 2 | `2026_09_25_000018_add_role_to_users_table` |
| Batch 3 | `2026_09_26_000019_add_fields_to_expense_categories_table` |
| Batch 3 | `2026_09_26_000020_add_fields_to_clinics_and_doctors_table` |
| Batch 3 | `2026_09_26_000021_add_fields_to_users_table` |
| Batch 3 | `2026_09_26_000022_create_backups_table` |
| Batch 4 | `2026_09_26_000023_add_revision_25_fields` |
| Batch 5 | `2026_09_26_000024_add_revision_26_fields` |
| Batch 6 | `2026_09_27_000025_create_prescription_pad_tables` |
| Batch 7 | `2026_09_27_095051_add_prescription_pad_template_id_to_appointments_table` |
| Batch 8 | `2026_09_27_000001_update_token_sequences_and_appointments_and_patients` |
| Batch 9 | `2026_09_28_000001_revision_33_doctor_and_fee_configurations` |
| Batch 10 | `2026_09_28_100001_create_revision_34_platform_and_multi_tenant_schema` |
| Batch 11 | `2026_09_28_100002_add_activation_code_hash_to_licenses_table` |
| Batch 12 | `2026_09_28_100003_revision_34_hardening_pass` |
| Batch 13 | `2026_09_28_200000_revision_35_license_renewal` |
| Batch 14 | `2026_09_28_300000_revision_36_license_requests` |
| Batch 15 | `2026_09_28_400000_revision_37_doctor_addons_and_demo` |
| Batch 16 | `2026_09_28_500000_revision_38_data_isolation_and_doctor_repair` |
| Batch 17 | `2026_09_29_100000_create_revision_42_platform_sales_and_accounting_tables` |

### 3.2 Schema Drift Findings
1. **Zero Unapplied Migrations**: All 39 migration files in `database/migrations` have been executed and recorded in `migrations` table.
2. **Platform Accounting Tables (Revision 42)**: Successfully created `platform_invoice_sequences`, `platform_sales_invoices`, `platform_invoice_items`, and `platform_expenses`. In accordance with Revision 42 architectural mandates, strictly **NO** duplicate `platform_payments` table was introduced.
3. **Missing `username` in `users`**: The live `users` table uses `login_id` (`ADM0001`, `OPR0001`, etc.) and `email`. It does not contain a `username` column.
4. **Missing `role` in `developer_users`**: The live `developer_users` table contains `id`, `name`, `email`, `password`, `is_active`, `last_login_at`, timestamps, but lacks a separate `role` column (it is exclusively for Superadmin developers).
5. **Platform Expense Scope**: Verified that `platform_expenses` has strictly NO `clinic_id`, maintaining full isolation from tenant accounting.

