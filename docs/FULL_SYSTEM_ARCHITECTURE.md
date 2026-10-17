# DXCLINIC — REVISION 40 — FULL SYSTEM ARCHITECTURE AUDIT

> Generated: 2026-09-29 | Method: Forensic Codebase, Migration & MySQL Schema Inspection  
> Database: `dxclinic` (MySQL 8.0) | Multi-Tenancy Architecture: Shared Database, Shared Schema  
> Audit Authority: Read-Only Forensic Baseline (Revision 40 Gate)

---

## 1. High-Level Architectural Model

DxClinic is a multi-tenant Clinical Practice Management and Outpatient Department (OPD) platform designed for independent medical clinics, polyclinics, and diagnostic centers.

```
                           ┌──────────────────────────────────────────────┐
                           │            Superadmin / Developer            │
                           │   Guard: developer (developer_users table)   │
                           │       Routes: /superadmin/* (Global)         │
                           └──────────────────────┬───────────────────────┘
                                                  │ Fleet Management & Licensing
                                                  ▼
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                 SHARED DATABASE (`dxclinic`)                                     │
│                                 51 Tables — Shared Schema                                       │
│                                                                                                 │
│  Tenant 1 (NX0001)               Tenant 2 (NX0002)               Tenant 6 (democlinic)          │
│  Sharma Care Clinic             Arogya Super Specialty           Permanent System Demo          │
│  clinic_id = 1                  clinic_id = 2                    clinic_id = 6 (is_demo=1)      │
└─────────────────────────────────────────────────────────────────────────────────────────────────┘
                                                  ▲
                   ┌──────────────────────────────┴──────────────────────────────┐
                   │                                                             │
┌──────────────────┴──────────────────┐                       ┌──────────────────┴──────────────────┐
│        Authenticated Tenant App     │                       │       Public Unauthenticated PWA    │
│  Guard: web (users table)           │                       │  Routes: /book/{public_slug}        │
│  Roles: ADMIN, OPERATOR, DOCTOR     │                       │          /b/{public_slug}           │
│  Middleware: auth, tenant, license  │                       │  Middleware: tenant (route-scoped)  │
│  Queue: /clinic/{slug}/operator/queue│                      │  Isolation: 404 on internal codes   │
└─────────────────────────────────────┘                       └─────────────────────────────────────┘
```

---

## 2. Authentication & Authorization Boundaries

### 2.1 Guards & Tables

| Guard | Provider Model | Database Table | Tenant Scoped? | Purpose |
|---|---|---|---|---|
| `web` | [User](file:///e:/dxclinic/app/Models/User.php) | `users` | **YES** (`clinic_id` FK) | Clinic Administrators, Frontdesk Operators, Attending Doctors |
| `developer` | [DeveloperUser](file:///e:/dxclinic/app/Models/DeveloperUser.php) | `developer_users` | **NO** (Platform-level) | Superadmin platform fleet operations, license provisioning, tenant monitoring |

### 2.2 User Roles & Dashboards

1. **`ADMIN`**
   - Full clinic-level operational & administrative authority.
   - Settings management (clinic details, doctor capacity, branding, fee master, vitals master, bank accounts, expense categories, prescription pad designer).
   - Onboarding setup wizard, manual database backup/restore.
   - User creation and operator credential management.
2. **`OPERATOR`**
   - Frontdesk operations: Patient Registration, Appointment Booking, Today's Arrival Queue, Consultation Billing, Diagnostic Billing.
   - Cash Session Management (Open session, record cash collection, close session, reconciliation).
   - Strictly forbidden from clinic settings and backup facilities (returns `403 Forbidden`).
3. **`DOCTOR`**
   - Clinical consultation workflows, patient encounter review, prescription issuance.
   - Linked to a record in the `doctors` table via `users.doctor_id`.

---

## 3. Multi-Tenancy Enforcement Mechanism

### 3.1 Tenant Identification Strategy
DxClinic enforces multi-tenancy at the application layer through a **Tenant Context** resolver:

- **Authenticated Web Requests**:
  The tenant is authoritatively derived from `Auth::guard('web')->user()->clinic_id`. Route parameters, headers, and query strings are validated against the authoritative session clinic; any conflicting tenant override (`?clinic_id=`, `X-Clinic-ID`, `X-Tenant-ID`, `X-Clinic-Code`) triggers an immediate `403 Forbidden`.
- **Public Booking PWA (`/book/{public_slug}`)**:
  The tenant is resolved exclusively from the URL route parameter `public_slug` (or short URL `/b/{public_slug}` which 302-redirects). If invalid, omitted, or if an internal identifier (`NX0001`, `1`) is supplied, it aborts with HTTP 404 Not Found.
- **Authenticated Operator Queue (`/clinic/{public_slug}/operator/queue`)**:
  Tenant-scoped operator queue. The route parameter `public_slug` is resolved and strictly verified against `Auth::user()->clinic_id`. If they mismatch, HTTP 403 Forbidden is returned. Unselected `/operator/queue` aborts with HTTP 404.
- **Superadmin Console (`/superadmin/*`)**:
  Operates globally across all clinics. Clinic context is explicitly passed as route parameters (`/superadmin/clinics/{clinic}/*`).

### 3.2 Tenant Isolation Middleware Pipeline

1. **`EnsureValidTenantContext` (`tenant`)**:
   - Validates that a clinic context exists for the incoming request.
   - Binds the resolved [Clinic](file:///e:/dxclinic/app/Models/Clinic.php) into [TenantContext](file:///e:/dxclinic/app/Services/TenantContext.php).
   - Verifies that the clinic is not `SUSPENDED` or `REVOKED`.
2. **`EnforceLicenseState` (`license`)**:
   - Applied to tenant routes (`/dashboard`, `/operator/*`, `/settings/*`, `/reports/*`, `/cash/*`).
   - Checks `$clinic->activeLicense` status and expiration date (`end_date >= today`).
   - If expired, redirects to `/settings/subscription` with a license renewal barrier.
3. **`SuperadminMiddleware` (`superadmin`)**:
   - Guards all `/superadmin/*` routes with `Auth::guard('developer')->check()`.

---

## 4. Core System Subsystems

### Subsystem 1: Superadmin & Fleet Licensing
- **Location**: [SuperadminDashboardController](file:///e:/dxclinic/app/Http/Controllers/Superadmin/SuperadminDashboardController.php), [LicenseService](file:///e:/dxclinic/app/Services/LicenseService.php)
- **Functions**: Issues initial licenses, provisions clinics with atomic credentials, executes paid renewals, adds mid-term doctor seats, suspends/reactivates/revokes licenses, monitors audit events in `license_events`.

### Subsystem 2: Clinic Onboarding & Activation
- **Location**: [ClinicOnboarding](file:///e:/dxclinic/app/Livewire/Onboarding/ClinicOnboarding.php), [ActivationController](file:///e:/dxclinic/app/Http/Controllers/ActivationController.php)
- **Functions**: First-time license key validation, initial administrator password update, clinic operational profile configuration (schedules, branding, consultation fees).

### Subsystem 3: Patient Identity & Medical Records
- **Location**: [PatientIdentityService](file:///e:/dxclinic/app/Services/PatientIdentityService.php), [PatientManager](file:///e:/dxclinic/app/Livewire/PatientManager.php)
- **Functions**: Generates deterministic patient IDs (`DXP-00001`), deduplicates by mobile and Aadhaar/Govt ID, maintains demographic data and visit timeline.

### Subsystem 4: Appointment & Token Queue Engine
- **Location**: [AppointmentService](file:///e:/dxclinic/app/Services/AppointmentService.php), [ArrivalQueue](file:///e:/dxclinic/app/Livewire/Operator/ArrivalQueue.php), [PatientBooking](file:///e:/dxclinic/app/Livewire/Pwa/PatientBooking.php)
- **Functions**: Manages daily token sequencing (`token_sequences` table), session management (Morning/Evening), public self-booking with rate limiting, arrival marking, and queue status displays.

### Subsystem 5: Clinical OPD Consultation & Billing
- **Location**: [ConsultationBilling](file:///e:/dxclinic/app/Livewire/Operator/ConsultationBilling.php), [Visit](file:///e:/dxclinic/app/Models/Visit.php), [Vital](file:///e:/dxclinic/app/Models/Vital.php)
- **Functions**: Snapshots consultation fees, records patient vital signs (BP, Pulse, SpO2, Temp, RR, RBS), tracks doctor encounter status, generates token slips and consultation receipts.

### Subsystem 6: Diagnostic Center & Investigation Billing
- **Location**: [DiagnosticBillingService](file:///e:/dxclinic/app/Services/DiagnosticBillingService.php), [DiagnosticBilling](file:///e:/dxclinic/app/Livewire/Operator/DiagnosticBilling.php)
- **Functions**: Catalogs laboratory and imaging investigations (`diagnostic_items`), tracks item recency/frequency (`scopeFrequent`), generates itemized bills with strict mathematical invariants (`net = gross - discount - waiver`).

### Subsystem 7: Cash Control, Sessions & Reconciliation
- **Location**: [CashReconciliationService](file:///e:/dxclinic/app/Services/CashReconciliationService.php), [CashSessionManager](file:///e:/dxclinic/app/Livewire/Cash/CashSessionManager.php), [DailyReportView](file:///e:/dxclinic/app/Livewire/Cash/DailyReportView.php)
- **Functions**: Enforces shift-based cash drawer sessions (`cash_sessions`), calculates expected cash from consultation + diagnostic receipts minus cash expenses, compares against physical cash counted at closing, documents overage/shortage variances.

### Subsystem 8: Prescription Pad Studio & Vector PDF Engine
- **Location**: [PrescriptionPadDesigner](file:///e:/dxclinic/app/Livewire/Settings/PrescriptionPadDesigner.php), [PrescriptionPdfRenderer](file:///e:/dxclinic/app/Services/PrescriptionPdfRenderer.php), [PrescriptionDataMapper](file:///e:/dxclinic/app/Services/PrescriptionDataMapper.php)
- **Functions**: Millimeter-precision visual designer for pre-printed letterheads; manages dynamic fields, static text, lines, and rectangles; proof vs. production PDF rendering; printer hardware calibration offsets (`printer_profiles`).

### Subsystem 9: Public Self-Booking PWA & Offline Hub
- **Location**: [PatientBooking](file:///e:/dxclinic/app/Livewire/Pwa/PatientBooking.php), [BookingQrController](file:///e:/dxclinic/app/Http/Controllers/BookingQrController.php), [Public Manuals](file:///e:/dxclinic/public/manuals/index.html)
- **Functions**: Mobile-optimized appointment booking, QR code generation (PNG/SVG/printable posters), standalone HTML documentation and FAQ portal.

---

## 5. Architectural Findings & Remediation Summary (Revision 41)

| # | Subsystem | Finding | Original Severity | Remediation in Revision 41 | Post-Remediation Status |
|---|---|---|---|---|---|
| 1 | **API Layer** | `/api/health`, `/api/queue/today`, `/api/reports/daily-summary` used `Clinic::first()` unconditionally. | **CRITICAL / HIGH** | Sanitized `/api/health` to platform uptime; protected queue with auth or cryptographic display signature; required auth tenant session for daily financial summary. | **FIXED & VERIFIED** |
| 2 | **Diagnostic Master API** | `/api/diagnostics/frequent` did not filter by tenant `clinic_id`. | **HIGH** | Scoped queries by tenant `clinic_id` + global master records; prevents cross-tenant pricing leakage. | **FIXED & VERIFIED** |
| 3 | **Livewire Scoping** | 12 Livewire report/management components used `$clinic = Clinic::first()`. | **HIGH** | Replaced all occurrences with authoritative `TenantContext::clinic()` and scoped queries. | **FIXED & VERIFIED** |
| 4 | **Print/PDF Routes** | `/print/*` and `/pdf/*` endpoints lacked authentication and tenant authorization checks (IDOR). | **HIGH** | Moved all clinical prints/PDFs to `['auth', 'tenant', 'license']` group; enforced `authorizeClinicResource()`, returning HTTP 403 on mismatch. | **FIXED & VERIFIED** |
| 5 | **Commercial Sales Layer** | Platform sales invoices, items, and billing ledgers do not exist in the database schema; all commercial data currently resides in licensing tables. | **Expected / Pre-Gate** | Batch 3 implementation kept strictly blocked during Revision 41 until security gate verification. Unlocked for Batch 3. | **READY FOR BATCH 3** |
| 6 | **Revision 45 Doctor & Fee Hardening** | Booking types were doctor-filtered visually but fees relied on generic mode fallback; unconfigured doctor tariffs could silently borrow generic fees. | **HIGH (ARCHITECTURAL INVARIANT)** | Hardened `FeeMaster` to store `booking_type_id`, `effective_from`, `effective_to` with `UNIQUE(clinic_id, doctor_id, booking_type_id)`. Added strict rule: Booking types are bookable/displayed ONLY when mapped to the doctor AND active fee configured for `(clinic, doctor, booking_type)`. Appointments permanently snapshot `fee_amount`. Cross-tenant and cross-doctor IDOR strictly blocked at domain level. | **FIXED & VERIFIED** |

---

## 6. Revision 45: Doctor + Booking Type + Fee Mapping Architecture

### 6.1 Authoritative Business Hierarchy
For all appointment bookings across all channels (Operator Desk, Telephonic, Public Patient PWA):

```
Active Clinic Context (`clinic_id`)
   │
   ▼
Selected Doctor (`doctor_id` where `clinic_id = activeClinic`)
   │
   ▼
Doctor Booking Type Availability (`doctor_booking_type_availability` where `is_enabled = 1`)
   │
   ▼
Active Booking Types (`booking_types` where `clinic_id = activeClinic` and `is_active = 1`)
   │
   ▼
Active Doctor-Specific Fee (`fee_masters` where `doctor_id = selectedDoctor` and `booking_type_id = bookingType` and currently effective)
   │
   ▼
Bookable Consultancy Types & Mapped Fee ──► Immutable Appointment Snapshot (`fee_amount`, `booking_type_id`, `consultation_mode`)
```

### 6.2 Separation of Four Core Concepts
1. **`booking_source`**: Where the appointment originated (`CLINIC`, `ONLINE`, `TELEPHONIC`).
2. **`consultation_mode`**: How the service is delivered (`CLINIC_PHYSICAL`, `ONLINE`, `VISITING_CONSULTANCY`).
3. **`booking_type`**: What clinical service is booked (`CLINIC_CONSULTANCY`, `ONLINE_CONSULTANCY`, `EMERGENCY_CONSULTANCY`, custom clinic-defined types).
4. **`fee_mapping`**: Doctor-specific financial obligation configured in `fee_masters` for `(clinic_id, doctor_id, booking_type_id)`.

### 6.3 Anti-Fallback & Anti-Tampering Protections
- **No Silent Fee Fallback**: If an admin enables a booking type for a doctor but configures no fee, the booking type is **NOT bookable**. System returns HTTP/Domain validation error `Fee is not configured for this doctor and consultancy type.`
- **Explicit Zero Fee Allowed**: Explicitly configured `₹0.00` fee is valid. Missing fee != zero fee.
- **Tenant & Doctor Ownership Verification**: `doctor.clinic_id === booking_type.clinic_id === authenticated clinic_id`. Cross-tenant booking types or unmapped doctor booking types are rejected at server/domain level with `InvalidArgumentException`.
- **Historical Immutability**: Subsequent tariff changes in Fee Master update only new bookings; past appointments and bills preserve their original snapshotted `fee_amount` and financial records permanently.

---

## 7. Revision 46: Database Composite Integrity & Versioned Fee Master Architecture

### 7.1 Database-Level Composite Foreign Key Hardening
Revision 46 elevates tenant isolation from application-only verification to physical MySQL InnoDB composite foreign key constraints. This guarantees that malformed cross-clinic or cross-doctor combinations are physically rejected by the database engine (SQLSTATE 23000) even if application code regresses.

#### Parent Composite Unique Indexes:
- `doctors`: `UNIQUE(id, clinic_id)` (Constraint `doctors_id_clinic_id_unique`)
- `booking_types`: `UNIQUE(id, clinic_id)` (Constraint `booking_types_id_clinic_id_unique`)

#### Child Composite Foreign Key Constraints:
1. **`doctor_booking_type_availability`**:
   - `FOREIGN KEY (doctor_id, clinic_id) REFERENCES doctors(id, clinic_id) ON DELETE CASCADE` (Constraint `fk_dbta_doctor_clinic`)
   - `FOREIGN KEY (booking_type_id, clinic_id) REFERENCES booking_types(id, clinic_id) ON DELETE CASCADE` (Constraint `fk_dbta_booking_type_clinic`)
   - Composite unique index: `UNIQUE(clinic_id, doctor_id, booking_type_id)`
2. **`fee_masters`**:
   - `FOREIGN KEY (doctor_id, clinic_id) REFERENCES doctors(id, clinic_id) ON DELETE CASCADE` (Constraint `fk_fee_doctor_clinic`)
   - `FOREIGN KEY (booking_type_id, clinic_id) REFERENCES booking_types(id, clinic_id) ON DELETE CASCADE` (Constraint `fk_fee_booking_type_clinic`)
   - Composite versioned unique index: `UNIQUE(clinic_id, doctor_id, booking_type_id, effective_from)` (Constraint `fee_masters_clinic_doc_bt_version_unique`)
3. **`appointments`**:
   - `FOREIGN KEY (doctor_id, clinic_id) REFERENCES doctors(id, clinic_id) ON DELETE RESTRICT` (Constraint `fk_appointments_doctor_clinic`)
   - `FOREIGN KEY (booking_type_id, clinic_id) REFERENCES booking_types(id, clinic_id) ON DELETE RESTRICT` (Constraint `fk_appointments_booking_type_clinic`)

### 7.2 Versioned Fee Master Architecture & Non-Overlapping Dates
Revision 46 resolves the architectural contradiction in Revision 45 (`effective_from/to` alongside unversioned 3-column unique constraint):
- **Physical Uniqueness**: `UNIQUE(clinic_id, doctor_id, booking_type_id, effective_from)`
- **Non-Overlapping Ranges**: `BookingTypeService::setDoctorFee()` validates that no two active fee records for the same doctor and booking type have overlapping `[effective_from, effective_to]` ranges. When a new open-ended fee is published, previous open-ended records are automatically capped (`effective_to = new_effective_from - 1 day`).
- **Date-Scoped Resolution**: `FeeMaster::getFeeForBookingType($clinicId, $doctorId, $bookingTypeId, $asOfDate)` queries the record where `effective_from <= $asOfDate` and (`effective_to IS NULL` or `effective_to >= $asOfDate`).

### 7.3 Atomic Server-Side Booking Pipeline
`AppointmentService::bookAppointment()` executes within a single `DB::transaction(...)`:
1. Resolves active tenant clinic.
2. Locks and verifies selected doctor row (`lockForUpdate()`).
3. Enforces `doctor.clinic_id === clinic_id`.
4. Resolves selected booking type and enforces `booking_type.clinic_id === clinic_id`.
5. Verifies doctor booking type availability mapping (`is_enabled = 1`).
6. Resolves exact active fee from `fee_masters` for `(clinic_id, doctor_id, booking_type_id, appointment_date)`.
7. Rejects if fee is missing (no silent fallback).
8. Derives consultation mode from booking type.
9. Allocates token atomically via `TokenEngine`.
10. Persists appointment record with snapshotted `fee_amount`, `booking_type_id`, `consultation_mode`, and `booking_source`.
11. Commits transaction or rolls back entirely on any integrity violation.

---

## 8. Revision 47: Public Slug, Tenant-Safe URL Architecture & Public PWA Isolation

### 8.1 Architectural Purpose & Threat Model
Prior to Revision 47, public self-booking URLs directly exposed internal clinic operational codes (e.g. `/book/NX0001`). This conflated internal tenant identifiers with public addressing, allowed unselected `/book` to default to an arbitrary clinic, and left `/operator/queue` as an un-scoped URL.

Revision 47 establishes a strict separation between **Internal Tenant Identity** (`clinics.id`, `clinics.code`) and **Public Tenant Locator** (`clinics.public_slug`).

### 8.2 Database Architecture: `clinics.public_slug`
- **Column Definition**: `public_slug VARCHAR(100) NOT NULL UNIQUE` on `clinics` table.
- **Unique Index**: `clinics_public_slug_unique` (`UNIQUE(public_slug)`).
- **Format Requirements**:
  - Lowercase alphanumeric characters and single hyphens only (`/^[a-z0-9]+(?:-[a-z0-9]+)*$/`).
  - Minimum 3 characters, maximum 100 characters.
  - Human-friendly: derived from clinic name, city, and branding (e.g., `sharmacare`, `arogya`, `democlinic`).
  - No random opaque UUIDs or arbitrary hexadecimal strings.
  - Internal identifiers (`clinic_id`, `clinic_code`) remain immutable and internal.

### 8.3 Centralized `PublicSlugService`
Located at [PublicSlugService](file:///e:/dxclinic/app/Services/PublicSlugService.php), providing:
1. **Deterministic Normalization**: Strips punctuation, trims, normalizes multi-spaces to hyphens, lowercase conversion.
2. **Candidate Generation Progression**:
   - `sharmacare`
   - `sharmacarevns` / `sharmacare-varanasi` (location-aware fallback)
   - `sharmacareclinic` (semantic fallback)
   - `sharmacare2`, `sharmacare3` (sequential collision resolution)
3. **Double-Checked Concurrency Protection**: Pre-validation check (`isSlugAvailable()`) combined with atomic database uniqueness constraint catching `QueryException` (error code 1062) and retrying up to 5 times.
4. **Resolution by Public Slug**: `findByPublicSlug($slug)` strictly resolves active clinics; prohibits matching on `clinic_code` or `clinic_id`.

### 8.4 Routing & Public PWA Isolation Architecture
1. **Canonical Public PWA Booking**:
   - Route: `/book/{public_slug}` (`name: pwa.book`)
   - Unselected `/book`: **Returns HTTP 404**. No default fallback clinic.
   - Internal Identifier Access (`/book/NX0001`, `/book/1`): **Returns HTTP 404**.
2. **Canonical Short Redirect**:
   - Route: `/b/{public_slug}` (`name: pwa.book.short`)
   - Action: Returns HTTP 302 redirecting to canonical `/book/{public_slug}`.
   - Unselected `/b`: **Returns HTTP 404**.
   - Internal Identifier Access (`/b/NX0001`, `/b/1`): **Returns HTTP 404**.
3. **Tenant-Scoped Operator Queue**:
   - Route: `/clinic/{public_slug}/operator/queue` (`name: operator.queue`)
   - Middleware: `['web', 'auth', 'tenant', 'license']`
   - Unselected `/operator/queue`: **Returns HTTP 404**.
   - Cross-Tenant Enforcement: If an authenticated staff member from Clinic A attempts to access `/clinic/{clinic_b_slug}/operator/queue`, `EnsureTenantContext` middleware returns **HTTP 403 Forbidden**.
4. **Anti-Tampering Protection**:
   - Any attempt to override tenant context via query strings (`?clinic_id=2`), request headers (`X-Clinic-ID`, `X-Tenant-ID`, `X-Clinic-Code`), or POST payloads returns **HTTP 403 Forbidden**.
   - Zero commercial clinic directory or platform-wide listing is exposed.


