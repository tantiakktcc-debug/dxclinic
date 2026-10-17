# DXCLINIC — REVISION 47 — AUTHORITATIVE FEATURE STATUS MATRIX

> Updated: 2026-09-29 | Method: Post-Remediation Codebase, MySQL Database & Test Suite Verification  
> Rule: **No status is inherited from historical reports without current verification.**  
> Allowed Statuses: `VERIFIED` | `PARTIAL` | `BROKEN` | `NOT VERIFIED` | `PHYSICAL TEST REQUIRED` | `KNOWN LIMITATION`

---

## 1. Comprehensive System Feature Matrix (Revisions 1 – 47)

| Rev | Feature Name | Code Check | Database Check | PHPUnit Test | Playwright Test | Docs | Physical Test | Current Status | Forensic Notes |
|---|---|---|---|---|---|---|---|---|---|
| **1-18** | Patient Unique Identity & EMR | Verified | Verified (`patients`) | Pass | Pass | Verified | N/A | **VERIFIED** | Generates `DXP-XXXXX`, deduplicates phone/Aadhaar. |
| **1-18** | Appointment Booking & Daily Queue | Verified | Verified (`appointments`) | Pass | Pass | Verified | N/A | **VERIFIED** | Daily token sequences per session. |
| **1-18** | OPD Vitals Recording | Verified | Verified (`vitals`) | Pass | Pass | Verified | N/A | **VERIFIED** | Captures BP, Pulse, SpO2, Temp, RR, RBS without fabrication. |
| **18/41**| API Queue Today (`/api/queue/today`) | Verified | Verified | Pass | Pass | Verified | N/A | **VERIFIED** | Authenticated tenant or cryptographic signed display URL. No PII leak. |
| **18/41**| API Daily Summary (`/api/reports/...`) | Verified | Verified | Pass | Pass | Verified | N/A | **VERIFIED** | Authenticated tenant session required. No financial leak. |
| **18/41**| API Health (`/api/health`) | Verified | N/A | Pass | Pass | Verified | N/A | **VERIFIED** | Public platform health sanitized. No tenant data leaked. |
| **19-21**| Legal Pages (`/about`, `/privacy-policy`)| Verified | N/A | Pass | Pass | Verified | N/A | **VERIFIED** | Developer info, terms, privacy routes intact. |
| **22-25**| Consultation Billing & Invariants | Verified | Verified (`consultation_bills`) | Pass | Pass | Verified | N/A | **VERIFIED** | Enforces `net = gross - discount - waiver`. |
| **22-25**| Diagnostic Center Billing | Verified | Verified (`diagnostic_bills`) | Pass | Pass | Verified | N/A | **VERIFIED** | Snapshots rate at bill creation time. Scoped in Rev 41. |
| **22-25**| Shift Cash Drawer Sessions | Verified | Verified (`cash_sessions`) | Pass | Pass | Verified | N/A | **VERIFIED** | Tracks opening cash, collections, expenses, physical count. |
| **23-25**| DomPDF Receipt & Slip Generation | Verified | N/A | Pass | Pass | Verified | N/A | **VERIFIED** | Barryvdh/laravel-dompdf generates vector receipts. |
| **26-28**| Prescription Pad Studio Foundation | Verified | Verified (`prescription_pad_templates`)| Pass | Pass | Verified | N/A | **VERIFIED** | Millimeter coordinate engine and background scan upload. |
| **29-33**| Prescription Static Text Tool | Verified | Verified (`prescription_pad_elements`) | Pass | Pass | Verified | N/A | **VERIFIED** | `addStaticText()` persists static text boxes on canvas. |
| **29-33**| Prescription Line & Rectangle Tools | Verified | Verified (`prescription_pad_elements`) | Pass | Pass | Verified | N/A | **VERIFIED** | Visual vector shapes with configurable borders/colors. |
| **29-33**| Per-Element Typography (Family/Size)| Verified | Verified (`font_size`, `font_family`) | Pass | Pass | Verified | N/A | **VERIFIED** | Isolated styling per field on canvas and PDF. |
| **29-33**| Font Scaling (4pt–12pt) | Verified | Verified | Pass | Pass | Verified | N/A | **VERIFIED** | `DB pt = Designer mm = PDF pt` verified mathematically. |
| **29-33**| Fullscreen Designer & Millimeter Rulers | Verified | N/A | Pass | Pass | Verified | N/A | **VERIFIED** | SVG rulers with 1mm/10mm tick marks, toggleable grid. |
| **29-33**| Canon Pre-Printed Pad Alignment | Verified | N/A | N/A | N/A | Verified | Pending | **PHYSICAL TEST REQUIRED** | Mechanical paper feed calibration requires on-site Canon LBP2900 printer. |
| **33/41**| Prescription Field: `patient.full_address_with_city_name` | Verified | Verified | Pass | Pass | Verified | N/A | **VERIFIED** | Added to mapper, catalog, and renderer. Clean formatting without stray commas. |
| **33/41**| Prescription Field: `patient.age_sex` | Verified | Verified | Pass | Pass | Verified | N/A | **VERIFIED** | Formatted as `"35 Yrs / F"`. Handles infant age 0, null sex safely. |
| **34** | Multi-Tenancy Engine (`TenantContext`)| Verified | Verified (`clinics`) | Pass | Pass | Verified | N/A | **VERIFIED** | Authenticated user `clinic_id` scopes operational data. |
| **34** | Cryptographic Licensing & Superadmin | Verified | Verified (`licenses`, `developer_users`)| Pass | Pass | Verified | N/A | **VERIFIED** | SHA-256 key hashing, AES encryption, developer guard. |
| **35** | Paid License Renewals | Verified | Verified (`license_renewals`) | Pass | Pass | Verified | N/A | **VERIFIED** | Immutable renewal ledger, payment audit, continuity. |
| **36** | Public SaaS Commercial Requests | Verified | Verified (`license_requests`) | Pass | Pass | Verified | N/A | **VERIFIED** | Rate-limited public form and review/fulfill dashboard. |
| **37** | Mid-Term Doctor Seat Add-Ons | Verified | Verified (`clinic_doctor_seat_addons`) | Pass | Pass | Verified | N/A | **VERIFIED** | Mid-term seats co-terminate with active license. |
| **37** | Public PWA Isolation (`/book/{code}`)| Verified | Verified | Pass | Pass | Verified | N/A | **VERIFIED** | URL-based routing strictly isolated; overrides blocked. |
| **38** | Permanent Demo Tenant (`democlinic`) | Verified | Verified (`is_demo=1`) | Pass | Pass | Verified | N/A | **VERIFIED** | Dedicated sandbox tenant, reset/pause facilities. |
| **38** | Demo Doctor Seat Cap (2 Seats) | Verified | Verified (`doctors`) | Pass | Pass | Verified | N/A | **VERIFIED** | Hard limit 2 doctors (`REG-DEMO01`, `REG-DEMO02`). |
| **38** | Legacy `demodoctor` Alias | Verified | Verified (`users`) | Pass | Pass | Verified | N/A | **VERIFIED (ALIAS)** | Points to Doctor 8 (Dr. Amit Verma); does not create 3rd doctor. |
| **39** | Local Hard-Drive Backup Facility | Verified | Verified (`backups`) | Pass | Pass | Verified | N/A | **VERIFIED** | SQL dump + companion `.meta.json` with SHA-256 manifest. |
| **39** | Documentation & Manuals Hub (`/manuals`)| Verified | Verified | Pass | Pass | Verified | N/A | **VERIFIED** | Standalone HTML portal with printable CSS stylesheets. |
| **39** | FAQ Chatbot Widget | Verified | N/A | Pass | Pass | Verified | N/A | **VERIFIED** | Natural language clinic assistant with doctor inquiry. |
| **40** | NX0001 License Date Invariant | Verified | Verified (`licenses.id = 1`) | Pass | Pass | Verified | N/A | **VERIFIED** | Yearly plan, 12 months, start: 2026-08-29, end: 2027-08-29. |
| **41** | Print & PDF IDOR Protection | Verified | Verified | Pass | Pass | Verified | N/A | **VERIFIED** | Auth required, tenant ownership enforced, 403 on mismatch. |
| **41** | Diagnostic Query Tenant Scoping | Verified | Verified | Pass | Pass | Verified | N/A | **VERIFIED** | Scoped to tenant + global catalog; cross-tenant items blocked. |
| **41** | Livewire Tenant Scoping | Verified | Verified | Pass | Pass | Verified | N/A | **VERIFIED** | All 12 `Clinic::first()` eradicated; strict tenant context. |
| **42** | Platform Sales Subsystem | Verified | Verified (`platform_sales_invoices`, `platform_invoice_items`, `platform_expenses`) | Pass | Pass | Verified | Verified | **VERIFIED** | Authoritative commercial source reconciliation, unique invoice sequence, collections reporting, platform expenses, profit formula, immutable invoices. |
| **43** | Consolidated Master Audit & Canon Calibration | Verified | Verified (55 tables, 39 migrations) | Pass | Pass | Verified | Pending | **VERIFIED (PHYSICAL PENDING)** | Forensic audit of 13 tenant entities, 1:1 invoice reconciliation, AES-256 backup verify, zero code alteration, Canon LBP2900 on-site calibration ready. |
| **44** | Extensible Booking Types & Pad Templates | Verified | Verified (`booking_types`, `doctor_booking_type_availability`, `appointments`) | Pass | Pass | Verified | N/A | **VERIFIED** | Extensible clinic-scoped booking types, per-doctor availability matrix, immutable historical template snapshot, non-destructive coexistence with legacy schema, public PWA isolation. |
| **45** | Strict Clinic + Doctor + Booking Type + Fee Mapping Hardening | Verified | Verified (`fee_masters`, `doctor_booking_type_availability`, `appointments.fee_amount`) | Pass | Pass | Verified | N/A | **VERIFIED** | Enforces strict Clinic -> Doctor -> Mapped Booking Type -> Mapped Fee mapping. FeeMaster extended with `booking_type_id`, `effective_from`, `effective_to` and unique index. Appointments snapshot `fee_amount`. Operator & Public PWA primary doctor filter, no silent fee fallback, cross-tenant/cross-doctor tampering blocked. |
| **46** | Database-Level Clinic + Doctor + Booking Type + Fee Integrity Hardening | Verified | Verified (`doctors`, `booking_types`, `doctor_booking_type_availability`, `fee_masters`, `appointments`) | Pass | Pass | Verified | N/A | **VERIFIED** | Enforces database-level composite unique keys (`id, clinic_id`) and composite foreign keys on `doctor_booking_type_availability`, `fee_masters`, and `appointments`. Resolves fee versioning with `UNIQUE(clinic_id, doctor_id, booking_type_id, effective_from)` and non-overlapping date logic. Atomic transactional booking in `AppointmentService`. Raw SQL rejects cross-tenant/cross-doctor tampering even if application layer regresses. |
| **47** | Public Slug Locator, Tenant-Safe URL Architecture & Public PWA Isolation | Verified | Verified (`clinics.public_slug` UNIQUE) | Pass (24/24) | Pass (14/14) | Verified | N/A | **VERIFIED** | Replaces internal IDs (`NX0001`, `1`) in public URLs with human-readable, database-backed `clinics.public_slug`. Canonical `/book/{public_slug}`, short redirect `/b/{public_slug}`, tenant-scoped operator queue `/clinic/{public_slug}/operator/queue`. Unselected `/book`, `/b`, `/operator/queue` return 404. Centralized `PublicSlugService` with candidate progression, pre-save check + database UNIQUE constraint. Tampering via query (?clinic_id=), headers, body, or cross-tenant route rejected with 403. Validated on Cloudflare hostname. |esses. |
