# DxClinic Multi-Tenant Platform Architecture Specification
**Revision 34 Hardening Pass — Authoritative Reference**

## 1. System Architecture Overview

```
                    SUPERADMIN
                         |
                  Platform Layer
                         |
              Subscription / License
                         |
                  Clinic NX0001 / NX0002 ...
                         |
        +----------------+----------------+
        |                |                |
      Users            Doctors         Patients
        |                |                |
     Admin            Fees/Schedule   Appointments
     Operator         Consult Types  Visits
     Doctor           Templates      Billing
                                       Accounting
```

### Public Access Layer
```
    /b/NX0001 (Short QR URL)
         ↓
    /book/NX0001 (Canonical PWA)
         ↓
    Clinic NX0001 (Branding, Active Doctors, Modes, Fees)

    /b/NX0002
         ↓
    /book/NX0002
         ↓
    Clinic NX0002
```

### Authenticated Tenant Access Layer
```
    authenticated_user.clinic_id
              ↓
        TenantContext
              ↓
     ONLY THAT CLINIC (100% Isolated)
```

### Superadmin Platform Layer
```
    developer guard (/superadmin/login)
              ↓
    explicit platform context & fleet oversight
```

---

## 2. Core Security & Tenant Isolation Rules

### Correction 1: Authoritative Tenant Context Security
- **Normal Authenticated Users (`ADMIN`, `OPERATOR`, `DOCTOR`):**
  `authenticated_user.clinic_id` is the **ONLY** authoritative tenant source.
- Request headers (`X-Clinic-ID`, `X-Clinic-Code`) **MUST NEVER** override the authenticated user's clinic.
- Route parameters (`{clinic}`, `{clinic_code}`) **MUST NEVER** override the authenticated user's clinic.
- Session values **MUST NOT** be trusted if they differ from `authenticated_user.clinic_id`.
- Any mismatch or cross-tenant resource tampering immediately results in **403 Forbidden**.

### Correction 2: Independent Clinic Lifecycle & Onboarding State
Clinic status is governed by three independent lifecycle dimensions:
1. **License State:** `TRIAL`, `ACTIVE`, `EXPIRED`, `SUSPENDED`, `REVOKED`, `RENEWED`.
2. **Activation State:** `PENDING_ACTIVATION`, `ACTIVATED`.
3. **Onboarding State:** `NOT_STARTED`, `IN_PROGRESS`, `COMPLETED`.

Example: A clinic can be `License = ACTIVE`, `Activation = ACTIVATED`, and `Onboarding = NOT_STARTED` (authorized, but pending initial administrative setup).

### Correction 3: Immutable License History
- Subscription periods are **never overwritten**.
- Renewals create a **NEW immutable License period record** (`licenses` table).
- The superseded license is updated to `status = 'RENEWED'` with all historical start/end dates intact.
- Full audit history is logged to `license_events`.

### Correction 4: Doctor Seat Licensing & Capacity Enforcement
- **Base Plan Capacity:** Standard subscription includes **1 Doctor Seat** (configurable per plan).
- **Additional Doctor Add-on:** Additional doctor seats are priced at **₹2,999 / doctor / year** (configurable per plan/license).
- **Capacity Calculation:**
  $$\text{Capacity} = \text{included\_doctor\_seats} + \text{additional\_doctor\_seats}$$
- **Transactional Enforcement:** When creating a doctor or toggling an inactive doctor active:
  $$\text{active\_doctor\_count} < \text{subscribed\_doctor\_capacity}$$
  If false, backend transactionally blocks creation with the required message:
  > *"You have reached the maximum number of doctor seats included in your license. Please contact the application provider to purchase an additional doctor seat."*
- **Doctor Deactivation/Deletion:** Frees up real-time licensed capacity without altering historical billing or subscription records.

### Correction 5 & 6: Clinic-Specific Public Booking & Safety
- **Canonical Booking:** `/book/{clinic_code}` resolves only that clinic's branding, doctors, schedules, consultation types, and fees.
- **Short URL:** `/b/{clinic_code}` redirects internally to `/book/{clinic_code}` for QR codes and SMS.
- **Legacy Fallback:** Unparameterized `/book` redirects to default legacy tenant `NX0001`.
- **Zero Fallback Invariant:** Never use `Clinic::first()` in the public booking resolution path.
- **Safe 404:** Invalid or non-existent clinic codes safely abort with **404 Not Found**.

### Correction 7: Mobile Family Patient Registration Limit
- Configured limit (`max_patients_per_mobile = 4`) is strictly clinic-scoped.
- The same mobile number can independently register up to 4 patients at NX0001, and up to 4 patients at NX0002 without cross-tenant interference.

### Correction 8: Minimal Login Screen Lookup API
- `/api/clinic-lookup/{clinic_code}` returns only minimal confirmation data:
  ```json
  {
    "clinic_code": "NX0001",
    "clinic_name": "Sharma Care Clinic",
    "city": "Varanasi"
  }
  ```
- Strictly rate-limited (`throttle:30,1`).
- Never exposes owner names, contact numbers, addresses, or license information.

### Correction 9: Decoupled Credentials & One-Time Activation
- **License Key $\neq$ Activation Code $\neq$ Password**.
- **Activation Code:**
  - One-time only.
  - Initial Administrator only.
  - Cryptographic hash destroyed upon consumption.
  - Renewals **never** require an activation code.

### Correction 10: Existing NX0001 Legacy Preservation
- Production clinic `NX0001` remains `ACTIVATED` with `onboarding_status = 'COMPLETED'`.
- Existing users are never locked out.
- Initial license and doctor seats (capacity = 5) allocated without simulating a new activation.

### Correction 11: Tenant-Scoped Database Uniqueness
The database schema enforces multi-tenant composite unique constraints:
- `UNIQUE(clinic_id, login_id)` on `users`.
- `UNIQUE(clinic_id, patient_id)` on `patients`.
- `UNIQUE(clinic_id, doctor_id, consultation_mode)` on `doctor_consultation_types`.
- `UNIQUE(clinic_id, token_date, session_type)` on `clinic_token_sequences`.

### Correction 12: Global System Masters
Platform-wide system masters remain globally managed without `clinic_id`:
- `expense_categories` (System expense taxonomy).
- `ledger_accounts` (Double-entry accounting chart of accounts).

---

## 3. Security Test Matrix Status (Correction 14 & 15)

All 22 security matrix tests in `Revision34MultiTenantFeatureTest` pass with 100% success (72 assertions):

| # | Test Case Description | Result |
|---|---|---|
| 1 | NX0001 user + NX0002 header $\implies$ NX0001 remains (Header rejected 403) | **PASSED** |
| 2 | NX0001 user + NX0002 route parameter $\implies$ Denied (403) | **PASSED** |
| 3 | NX0001 user + NX0002 patient ID $\implies$ Denied (403) | **PASSED** |
| 4 | NX0001 user + NX0002 appointment $\implies$ Denied (403) | **PASSED** |
| 5 | NX0001 user + NX0002 template $\implies$ Denied (403) | **PASSED** |
| 6 | NX0001 user + NX0002 fee $\implies$ Denied (403) | **PASSED** |
| 7 | NX0001 user cannot access Superadmin | **PASSED** |
| 8 | Superadmin can inspect NX0002 audit events | **PASSED** |
| 9 | Public `/book/NX0001` cannot expose NX0002 data | **PASSED** |
| 10 | Public `/book/NX0002` cannot expose NX0001 data | **PASSED** |
| 11 | Invalid clinic code returns safe 404 | **PASSED** |
| 12 | License activation code can be consumed only once | **PASSED** |
| 13 | Non-admin cannot consume initial activation code | **PASSED** |
| 14 | Expired license blocks normal tenant access | **PASSED** |
| 15 | Suspended license blocks normal tenant access | **PASSED** |
| 16 | Renewing a license does not require activation code | **PASSED** |
| 17 | Additional doctor cannot exceed purchased capacity | **PASSED** |
| 18 | Additional doctor purchase increases capacity | **PASSED** |
| 19 | Doctor deletion/deactivation does not reduce historical billing | **PASSED** |
| 20 | Onboarding status lifecycle (`NOT_STARTED` $\implies$ `IN_PROGRESS` $\implies$ `COMPLETED`) | **PASSED** |
| 21 | Clinic lookup API returns minimal data safely & rate-limited | **PASSED** |
| 22 | Short public URL `/b/{clinic_code}` redirects to canonical | **PASSED** |

---

## 4. Revision 35 — Superadmin One-Click Paid License Renewal Architecture

### 4.1 Frozen Business Workflow
```
   [Clinic Owner] ──(Pays Outside App: Cash/UPI/Bank)──> [Application Owner/Superadmin]
                                                                    │
                                                            (Verifies Payment)
                                                                    │
                                                                    ▼
                                                    [Superadmin One-Click Renewal]
                                                    (/superadmin/clinics/{id}/renew)
                                                                    │
                                                       (Atomic DB Transaction)
                                                                    │
                                     ┌──────────────────────────────┴──────────────────────────────┐
                                     ▼                                                             ▼
                          [Creates License Period]                                      [Audit & Ledgers]
                          - Status: ACTIVE                                              - license_renewals
                          - Continuous dates                                            - license_events
                          - Doctor seat capacity                                        - Idempotency key
                                     │
                                     ▼
                            [Tenant Experience]
                            - Immediate ACTIVE access
                            - Zero license keys entered
                            - Zero activation codes
                            - Zero reactivation / onboarding
                            - Existing logins unchanged
```

### 4.2 Database Schema Additions
1. **`license_renewals` Table (Immutable Financial & Operational Log):**
   - `id`: Primary key
   - `clinic_id`: Foreign key to `clinics`
   - `license_id`: Foreign key to renewed `licenses`
   - `previous_license_id`: Foreign key to predecessor `licenses` (nullable)
   - `plan_id`: Foreign key to `subscription_plans`
   - `renewal_number`: Sequential ordinal per clinic (`#1`, `#2`, ...)
   - `renewal_date`: Date of transaction
   - `previous_end_date`: Expiry date of previous term
   - `new_start_date`: Effective start date of renewed term
   - `new_end_date`: New expiry date
   - `duration_months`: Covered duration
   - `base_amount`: Base tier fee
   - `renewed_doctor_seats`: Count of renewed existing additional seats
   - `renewed_doctor_seats_fee`: Fee for renewed existing seats
   - `new_doctor_seats`: Count of newly purchased additional seats
   - `new_doctor_seats_fee`: Fee for newly purchased seats
   - `total_doctor_seats`: Total active doctor seats after renewal
   - `discount_amount`: Billing reduction applied
   - `net_amount`: Final settled total
   - `payment_method`: `CASH`, `UPI`, `BANK_TRANSFER`, `CHEQUE`, `OTHER`
   - `payment_reference`: External transaction identifier (UTR, Cheque #, etc.)
   - `payment_date`: Date payment was settled
   - `idempotency_key`: Unique UUID preventing duplicate processing
   - `notes`: Administrative audit memo
   - `created_by_developer_user_id`: Superadmin who authorized transaction

2. **`licenses` Table Modifications:**
   - `previous_license_id`: Self-referencing link to predecessor license
   - `renewal_number`: Sequential ordinal
   - `is_renewal`: Boolean flag (true for renewals)
   - `payment_method`, `payment_reference`, `payment_date`: Settlement metadata

### 4.3 Feature Test & E2E Test Suite Status
- **`Revision35LicenseRenewalFeatureTest`:** **26/26 Tests PASSED** (68 assertions).
- **`Revision34MultiTenantFeatureTest`:** **22/22 Tests PASSED** (72 assertions).
- **Full Project Regression:** **185/185 Tests PASSED** (880 assertions).
- **Playwright E2E (`revision35_license_renewal_e2e.js`):** **25/25 Tests PASSED** (0 failures).

---

## 5. Revision 37 Architecture: Doctor Add-On Licensing, Demo Tenant & Strict PWA Isolation

### 5.1 Mid-Term Doctor Add-On Licensing Architecture
Mid-term doctor seat addition allows an active clinic to purchase additional doctor capacity at any time during an active subscription:
```text
Clinic License
    └── Permanent License Key (UNCHANGED)
          │
          ├── Included Doctor Seat (Base Plan)
          │
          ├── Additional Doctor Seat #1 (Purchased mid-term, yearly billing)
          │      └── Validity: purchase_date → purchase_date + 1 year - 1 day
          │
          └── Additional Doctor Seat #2 (Purchased mid-term, yearly billing)
```
- **Same License Key Rule:** The existing permanent License Key is never altered, regenerated, or replaced.
- **No Activation / Onboarding:** No activation codes or onboarding workflows are generated.
- **Yearly Billing Only:** Doctor add-ons are billed separately on an annual cycle (default: ₹2,999/year per doctor).
- **Capacity Calculation Engine:**
  $$\text{Effective Capacity} = \text{Included Base Seats} + \sum \text{Active Doctor Add-On Entitlements}$$
- **Reconciliation Engine:** At base license renewal, active mid-term entitlements are reconciled so that clinics are never double-billed for already-paid doctor add-ons.

### 5.2 Permanent Demo Tenant Sandbox (`democlinic`)
- **Deterministic Identity:** Clinic Code `democlinic`, Name `Demo Clinic`, City `Varanasi`.
- **Preconfigured Users:** `demoadmin` (Administrator), `demooperator` (Front-Desk Operator), `demodoctor01` (Dr. Amit Verma), `demodoctor02` (Dr. Neha Kapoor), legacy `demodoctor` alias.
- **Doctor Hardcap Rule:** Demo Clinic capacity is strictly fixed to 2 doctors. Attempts to add doctor seats via UI or API are blocked by the service layer.
- **Commercial Exclusion:** Demo is classified with `clinics.is_demo = true` and `demo_status = 'ACTIVE'|'PAUSED'`. It is excluded from billable commercial license expiration workflows.
- **Demo Pause & Resume:**
  - When paused, staff login attempts are rejected with a controlled message: *"The DxClinic demonstration environment is temporarily unavailable. Please try again later."*
  - Public booking at `/book/democlinic` returns HTTP 503 with the controlled unavailable screen.
- **Atomic Deterministic Reset:**
  - Re-seeds standard fictional patients, appointments, visits, vitals, bills, payments, and receipts.
  - Transactions are strictly scoped to `clinic_id = democlinic.id`. Zero effect on real tenant databases.

### 5.3 Strict Public PWA Tenant Isolation Architecture
- **Canonical Routing:** `/book/{clinic_code}` is the canonical entry point.
- **Short Routing:** `/b/{clinic_code}` provides an unambiguous redirect to `/book/{clinic_code}`.
- **Sole Tenant Authority:** The URL parameter `{clinic_code}` is the only accepted tenant selector.
- **Anti-Tampering Guard:** Attempts to override tenant context via query parameter (`?clinic_id=...` or `?clinic_code=...`), HTTP header (`X-Clinic-ID`, `X-Clinic-Code`), or POST body payload trigger an immediate HTTP 403 Forbidden abort.
- **Service Layer Guarding:** `AppointmentService` validates `patient->clinic_id === clinic->id` and `doctor->clinic_id === clinic->id`, preventing cross-tenant appointment hijacking.

---

## 6. Revision 38 Architecture: Data Isolation, Doctor Identity, Consultancy Naming, Plan-Date Correction & Demo 2-Doctor Upgrade

### 6.1 Global Doctor Name Normalization Architecture
- **Problem Statement:** Historical records and views suffered from duplicate prefixes like "Dr. Dr. Rajesh Sharma" caused by double-prepending "Dr." in templates when database records already stored "Dr. ...".
- **Canonical Stored Name:** Doctor names in database are stored without honorific title prefixes (e.g. `Rajesh Sharma`, `Amit Verma`). The `Doctor` model defines a mutator `setNameAttribute` utilizing `DoctorNamePresenter::stripTitle($value)` to strip any incoming "Dr.", "Dr ", or "Doctor " prefixes automatically.
- **Centralized Formatter:** `DoctorNamePresenter::format($doctor)` generates the single canonical display title `Dr. Firstname Lastname`.
- **Global UI Standard:** All UI/PDF/PWA/report/receipt/prescription outputs access `DoctorNamePresenter::format($doctor)` or `$doctor->formatted_name`, guaranteeing "Dr. Dr." can never occur anywhere.

### 6.2 Customer-Facing Consultancy Terminology
- **Standardized Terminology:**
  - `Clinic Consultancy` (In-person consultation at physical clinic)
  - `Online Consultancy` (Telemedicine / virtual consultation)
  - `Visiting Consultancy` (Doctor home/field visit)
- **Enum Preservation:** Internal enum values (`CLINIC_PHYSICAL`, `ONLINE`, `VISITING`) remain unchanged for strict database backward-compatibility.
- **Full Spectrum Coverage:** Customer-facing labels across Public PWA, Operator booking, Arrival Queue, OPD reports, Diagnostic reports, Fee Master manager, Receipts, Prescriptions, Doctor pads, and AI assistants use "Clinic Consultancy".

### 6.3 Authoritative Fee Resolution & Cross-Tenant Data Isolation
- **Multi-Level Scoping:** Fee lookups are scoped strictly to the current clinic:
  $$\text{Doctor Specific Fee (if doctor belongs to clinic)} > \text{Clinic Fee Master Baseline} > \text{System Default}$$
- **Foreign Doctor Fee Bleed Prevention:** In `FeeMaster::getFeeForMode()`, the service explicitly verifies `$doctor->clinic_id === $clinicId`. If a doctor belongs to another clinic, their custom rate is strictly rejected and never applied.
- **Livewire Tenant Context Preservation:** In `EnsureTenantContext` and `TenantContext::resolvePublicClinic()`, Livewire component update requests (`/livewire/update`) parse the `Referer` URL or snapshot state to maintain the exact public clinic context across asynchronous requests.
- **Baseline Fee Seeding:** Every tenant, including `democlinic`, maintains baseline `FeeMaster` records (`doctor_id = null`) for all enabled modes, eliminating fallback ambiguity before a doctor is selected.

### 6.4 5-Year Plan License Date Bug Fix & Real Mathematical Remaining Days
- **Root Cause:** A 10-year (`addYears(10)`) duration had previously been seeded for the 5-Year Plan license, leading to 2036 expirations (~3,653 days).
- **Corrected Rule:** A 5-Year subscription duration is strictly defined as **60 months** (`addMonths(60)`).
- **Authoritative Date Calculation:** Expiration date is calculated as exactly 5 years from issuance in `Asia/Kolkata` (e.g., `2026-09-28` $\to$ `2031-09-28`), producing mathematically correct remaining days (~1,825–1,826 days).
- **Zero Faking Policy:** The remaining days metric is calculated strictly from `now()->diffInDays($validUntil, false)` and is never faked or derived from plan names.

### 6.5 Demo Clinic 2-Doctor Upgrade & Fictional Identity
- **Two Fictional Demo Doctors:**
  - Doctor #1: `Dr. Amit Verma` (Login: `demodoctor01`, Reg: `REG-DEMO01`, Specialization: General Medicine)
    - Consultancies: Clinic Consultancy (₹500), Online Consultancy (₹400)
  - Doctor #2: `Dr. Neha Kapoor` (Login: `demodoctor02`, Reg: `REG-DEMO02`, Specialization: Family Medicine)
    - Consultancies: Clinic Consultancy (₹500), Visiting Consultancy (₹750)
- **Isolated User Credentials:** Securely seeded demo passwords (`DemoDoctor01!`, `DemoDoctor02!`) with `DOCTOR` role bound exclusively to `democlinic`.
- **Hard Limit: 2 Doctor Seats:**
  - Demo clinic total capacity is locked to 2 seats at all layers:
    1. UI display on Superadmin Fleet Dashboard: *"Isolated demonstration sandbox • Hard limit: 2 Doctor seats"*
    2. Controller validation blocking doctor additions
    3. `Clinic::totalDoctorCapacity()` returning 2
    4. `Doctor::creating` model hook rejecting any 3rd doctor
    5. Database transaction rollbacks on violation

---

## 7. Revision 42 Platform Sales, Invoicing, Collections & Commercial Accounting Architecture

### 7.1 Authoritative Commercial Source of Truth (No Duplicate Payment Ledger)
Revision 42 establishes the commercial invoicing and reporting subsystem without creating duplicate financial records. Platform payment data is derived strictly from authoritative commercial source records:
1. **Initial License Purchase:** `licenses` table (`paid_amount`, `payment_method`, `payment_reference`, `payment_date`).
2. **License Renewals:** `license_renewals` table (`net_amount`, `payment_method`, `payment_reference`, `payment_date`).
3. **Mid-Term Doctor Add-Ons:** `clinic_doctor_seat_addons` table (`price_paid`, `payment_method`, `payment_reference`, `payment_date`).
4. **Platform Overhead Expenses:** `platform_expenses` table (`amount`, `expense_date`, `category`, `payment_method`).

**Strict Invariant:** NO `platform_payments` table is created. Authoritative financial truth lives on the primary commercial event tables.

### 7.2 Invoice Architecture & Unique Sequential Generation
- **Document Entities:** `platform_sales_invoices` and `platform_invoice_items`.
- **Atomic Numbering:** `platform_invoice_sequences` guarantees globally unique, gap-free, atomic invoice numbering in the format:
  $$\text{INV-YYYY-XXXXX} \quad (\text{e.g., INV-2026-00001, INV-2026-00002})$$
- **Database Idempotency & Uniqueness:** `UNIQUE(source_type, source_id)` on `platform_sales_invoices` guarantees that a single commercial transaction can NEVER produce duplicate invoices regardless of network retries or repeated Superadmin submissions.
- **Source Linkage Immutability:** Once an invoice is issued, its amounts (`subtotal`, `discount`, `net_amount`, `collected_amount`), clinic association, and source linkage are frozen at the Eloquent lifecycle level (`PlatformSalesInvoice::booted()`). Edits are rejected with an `IllegalStateException`.
- **Correction Protocol:** Historical invoices are immutable. In the event of a billing dispute or cancellation, a controlled cancellation mechanism updates `status = 'CANCELLED'` and records cancellation audit metadata without deleting historical invoice records.

### 7.3 Financial KPI Formulations & Profit Accounting
Platform financial performance is reported on `/superadmin/sales` via the following exact accounting formulas:
$$\text{Gross Sales} - \text{Discounts} = \text{Net Sales}$$
$$\text{Net Sales} - \text{Refunds} = \text{Net Revenue}$$
$$\text{Net Revenue} - \text{Platform Overhead Expenses} = \text{Operating Profit}$$

Key metrics tracked in parallel:
- **Collected Revenue:** $\sum \text{collected\_amount}$ on all non-cancelled invoices.
- **Outstanding Balance:** $\sum \text{outstanding\_amount}$ ($\text{net\_amount} - \text{collected\_amount}$).
- **Collections vs Profit:** Collections represent cash/bank cashflows received, whereas Profit reflects earned net revenue less provider operational overhead.

### 7.4 Multi-Channel Collections Ledger (`/superadmin/collections`)
Tracks all inbound commercial payments aggregated by payment mode:
- **Cash:** In-person cash collections.
- **UPI:** Digital collections via Unified Payments Interface.
- **Bank Transfer:** NEFT, RTGS, IMPS direct bank transfers.
- **Cheque:** Physical bank cheque clearings.
- **Other:** Wire transfers or alternative channels.

Provides four distinct viewing perspectives:
1. **Individual Transactions:** Chronological transaction journal with UTR reference, clinic ID, and invoice number.
2. **Daily Aggregation:** Date-grouped daily collection totals across each payment mode.
3. **Monthly Aggregation:** Month-grouped calendar totals.
4. **Financial Year (FY) Aggregation:** Indian Financial Year (April 1 to March 31) breakdown.

### 7.5 Per-Clinic & Per-License Commercial History
Accessible at `/superadmin/clinics/{clinic}/sales`:
- **Stable License Identity:** Maintains the permanent license key as the clinic's enduring identity (`NXCL-••••-••••-••••-••••`). Renewals extend this identity rather than manufacturing artificial new licenses.
- **Chronological Timeline:** Displays complete commercial evolution from initial license onboarding, through mid-term doctor capacity upgrades, to renewals and credit adjustments.
- **Audit Granularity:** Full visibility into invoice numbers, payment channels, UTRs, and issuance timestamps.

### 7.6 Platform Accounting vs. Clinic Tenant Accounting Isolation
A hard boundary is maintained between software provider accounting and customer clinic accounting:
| Feature | Platform Accounting (`developer` guard) | Clinic Tenant Accounting (`web` guard) |
|---|---|---|
| **Scope** | Provider SaaS sales, renewals, seat add-ons | OPD consultations, diagnostics, pharmacy |
| **Expenses** | Platform hosting, server, domain, SMS costs (`platform_expenses`) | Clinic rent, nurse wages, electricity, consumables (`expenses`) |
| **Invoicing** | `platform_sales_invoices` (INV-YYYY-XXXXX) | OPD Patient Receipts & Diagnostic Bills (`bills`, `payments`) |
| **Ledgers** | Single commercial source tables | Double-entry journals (`journal_entries`, `ledger_accounts`) |
| **Tenant Scoping**| Provider-global (strictly no `clinic_id` on platform expenses) | Multi-tenant scoped (`clinic_id` required on all records) |
| **Cross-Posting** | **ZERO CROSS-POSTING** | **ZERO CROSS-POSTING** |

---
*DxClinic Platform Architecture Specification &bull; Revision 42 Complete &bull; Copyright &copy; 2026 DxClinic*

