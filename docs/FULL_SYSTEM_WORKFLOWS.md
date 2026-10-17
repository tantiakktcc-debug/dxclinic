# DXCLINIC — REVISION 40 — FULL SYSTEM WORKFLOWS AUDIT

> Generated: 2026-09-29 | Method: Codebase & Database Transaction Analysis  
> Scope: Complete End-to-End Operational and Clinical Workflows

---

## 1. Clinic Provisioning, Onboarding & Activation Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Superadmin as Superadmin Developer
    participant Sys as DxClinic Platform
    participant DB as MySQL Database
    actor Admin as Clinic Administrator
    actor Operator as Reception Operator

    Superadmin->>Sys: POST /superadmin/licenses/issue (Clinic & Admin Data, Plan, Doctor Seats)
    Sys->>DB: Begin Transaction
    Sys->>DB: Insert clinics record (e.g. NX0002)
    Sys->>DB: Insert subscription license & hash cryptographic keys
    Sys->>DB: Create initial ADMIN User with must_change_password=1
    Sys->>DB: Create initial DOCTOR record & default doctor assignment
    Sys->>DB: Insert license_events (ISSUED)
    Sys->>DB: Commit Transaction
    Sys-->>Superadmin: Display One-Time Onboarding Credential Package
    Superadmin->>Admin: Hands over credentials securely
    Admin->>Sys: GET /login (enters Clinic Code, Login ID, Temp Password)
    Sys-->>Admin: Redirects to /force-password-change
    Admin->>Sys: POST /force-password-change (sets permanent password)
    Sys-->>Admin: Redirects to /onboarding Setup Wizard
    Admin->>Sys: Completes Onboarding (Schedules, Branding, Consultation Fees)
    Sys->>DB: Updates clinics (is_onboarded=1, onboarding_status=COMPLETED)
    Sys-->>Admin: Unlocks /dashboard
```

---

## 2. License Renewal & Doctor Seat Add-On Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Superadmin as Superadmin Developer
    participant Sys as LicenseService
    participant DB as MySQL Database

    Superadmin->>Sys: POST /superadmin/clinics/{clinic}/renew
    Note over Sys: Validates plan, payment received, idempotency key
    Sys->>DB: Lock current license & clinic row (SELECT FOR UPDATE)
    alt Current License ACTIVE and end_date >= payment_date
        Sys->>Sys: new_start_date = old_end_date + 1 day (Continuity preserved)
    else Expired or Suspended
        Sys->>Sys: new_start_date = payment_date
    end
    Sys->>Sys: new_end_date = new_start_date + plan_duration - 1 day
    Sys->>Sys: Calculate fee: base price + (billable doctor seats * annual seat fee) - discount
    Sys->>DB: Insert new licenses record (is_renewal=1, renewal_number=old+1)
    Sys->>DB: Update old license status = 'RENEWED'
    Sys->>DB: Insert license_renewals audit record
    Sys->>DB: Insert license_events (SUPERSEDED_BY_RENEWAL)
    Sys-->>Superadmin: Renewal confirmed with new expiration date
```

---

## 3. Patient Appointment & Queue Management Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Patient as Patient (Mobile)
    actor Operator as Reception Operator
    participant PWA as PatientBooking PWA
    participant Queue as ArrivalQueue Component
    participant DB as MySQL Database

    alt Online Booking (PWA)
        Patient->>PWA: Visits /book/{public_slug} (or /b/{public_slug} -> 302 redirect)
        PWA->>DB: Resolve clinic context strictly by public_slug
        Note over PWA,DB: Internal codes (/book/NX0001) or unselected (/book) abort 404
        Patient->>PWA: Selects Doctor, Session (Morning/Evening), Enters Mobile & Name
        PWA->>DB: Atomically increments token_sequences for date + session
        PWA->>DB: Inserts appointments record (status=BOOKED, source=ONLINE)
        PWA-->>Patient: Returns Token Confirmation Screen
    else Walk-in Reception Booking
        Operator->>Queue: Enters patient phone / registers walk-in
        Queue->>DB: Inserts appointments record (status=BOOKED, source=WALK_IN)
    end

    Patient->>Operator: Arrives at clinic physical reception
    Operator->>Queue: Accesses /clinic/{public_slug}/operator/queue
    Note over Queue,DB: Strict auth + tenant check (403 on tenant mismatch)
    Operator->>Queue: Clicks "Mark Arrived" on token
    Queue->>DB: Updates appointment status = 'ARRIVED', creates visits record
    Queue-->>Operator: Token moves to Attending Doctor Queue
```

---

## 4. OPD Clinical Consultation & Prescription Generation Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Operator as Frontdesk / Vitals Nurse
    actor Doctor as Attending Doctor
    participant Pad as PrescriptionPadRenderer
    participant DB as MySQL Database

    Operator->>DB: Records patient vitals (BP, Pulse, SpO2, Temp, RR, RBS)
    Doctor->>DB: Conducts consultation, updates visit status = 'COMPLETED'
    Doctor->>Pad: Requests Prescription Print / PDF Download
    Pad->>DB: Loads active prescription_pad_template for doctor/clinic
    Pad->>DB: Loads printer_profiles for hardware calibration offsets
    Pad->>Sys: PrescriptionDataMapper resolves patient, vitals, clinic, doctor tokens
    Pad->>Pad: Renders DomPDF vector document matching physical pad dimensions
    Pad-->>Doctor: Streams production PDF (prints on pre-printed letterhead)
```

---

## 5. Diagnostic Billing & Investigation Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Operator as Diagnostic Billing Operator
    participant Service as DiagnosticBillingService
    participant DB as MySQL Database

    Operator->>Service: Selects Diagnostic Tests / Packages, enters discount/waiver
    Service->>DB: Fetches diagnostic_items (snapshots current rates)
    Service->>Service: Verifies invariant: net = gross - discount - waiver
    Service->>DB: Inserts diagnostic_bills record (status=PENDING)
    Service->>DB: Inserts diagnostic_bill_items (snapshotted item details)
    Service->>DB: Increments usage_count and updates last_used_at on items
    Service-->>Operator: Generates Bill Receipt & Lab Collection Slip
```

---

## 6. Cash Drawer Session & Shift Reconciliation Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Operator as Frontdesk Operator
    participant Cash as CashSessionManager
    participant Recon as CashReconciliationService
    participant DB as MySQL Database

    Operator->>Cash: Opens morning shift session with Opening Cash in Hand
    Cash->>DB: Inserts cash_sessions (status=OPEN, session_type=MORNING)
    loop During Shift
        Operator->>DB: Records consultation bills, diagnostic bills (CASH / UPI)
        Operator->>DB: Records petty cash expenses
    end
    Operator->>Cash: Initiates Session Close, enters Physical Cash Counted
    Cash->>Recon: Computes expected cash = opening + cash collections - cash expenses - bank deposits
    Cash->>Cash: Calculates variance = physical cash - expected cash
    Cash->>DB: Updates cash_sessions (status=CLOSED, variance documented)
    Cash-->>Operator: Displays balanced status or variance alert
```

---

## 7. Hard-Drive Local Backup & Restore Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Clinic Administrator
    participant Backup as BackupService
    participant FS as Local Filesystem

    Admin->>Backup: POST /settings/backup (confirms administrator password)
    Backup->>Backup: Verifies admin password hash against authenticated user
    Backup->>FS: Generates raw SQL dump with table definitions and row inserts
    Backup->>FS: Creates companion manifest `.meta.json` with SHA-256 checksum, schema version
    Backup->>DB: Logs backup creation in audit_logs
    Backup-->>Admin: Returns downloadable backup archive with verified checksum
```

---

## 8. Platform Commercial Sales, Invoicing & Reconciliation Workflow (Revision 42)

```mermaid
sequenceDiagram
    autonumber
    actor Superadmin as Platform Superadmin
    participant Controller as SuperadminSalesController
    participant Service as PlatformInvoiceService
    participant Sequence as PlatformInvoiceSequence
    participant DB as Authoritative Commercial Tables
    participant Inv as Platform Invoices & Items

    Note over Superadmin, DB: 1. Commercial Event Trigger (Initial License / Renewal / Add-on)
    Superadmin->>Service: fulfillRequest() / renewLicense() / addDoctorSeats()
    Service->>DB: Writes authoritative commercial record (licenses / license_renewals / clinic_doctor_seat_addons)
    Note over DB: No duplicate platform_payments record created!

    Note over Service, Inv: 2. Idempotent Invoicing & Sequence Generation
    Service->>Inv: Checks if invoice exists for [source_type, source_id]
    alt Invoice Already Exists
        Inv-->>Service: Returns existing invoice (idempotent, no duplicates)
    else New Invoice
        Service->>Sequence: Atomically increment year sequence (e.g. 2026 -> 00001)
        Sequence-->>Service: Formatted number "INV-2026-00001"
        Service->>Inv: Inserts platform_sales_invoices (status=PAID/PARTIAL, amounts, masked key)
        Service->>Inv: Inserts platform_invoice_items (Base Plan, Doctor Seats, Discounts)
    end

    Note over Superadmin, Inv: 3. Commercial Reporting & Oversight
    Superadmin->>Controller: GET /superadmin/sales (with date filters)
    Controller->>Inv: Queries gross sales, discounts, net sales, collections, outstanding
    Controller->>DB: Queries platform_expenses (hosting, SMS, maintenance)
    Controller-->>Superadmin: Displays 7 Financial KPIs and Operating Profit = Net Revenue - Platform Expenses

    Superadmin->>Controller: GET /superadmin/collections (individual, daily, monthly, FY)
    Controller->>DB: Reconciles collections across Cash, UPI, Bank Transfer, Cheque channels
    Controller-->>Superadmin: Renders multi-channel collection aggregations
```

---

## 9. Doctor + Booking Type + Fee Master Configuration Workflow (Revision 45)

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Clinic Administrator
    participant Livewire as FeeMasterManager / BookingTypeManager
    participant BTService as BookingTypeService
    participant FeeDB as fee_masters Table
    participant MapDB as doctor_booking_type_availability Table

    Note over Admin, Livewire: Step 1: Doctor-Centric Mapping & Tariff Definition
    Admin->>Livewire: Accesses Settings -> Fee Master (/settings/fee-master)
    Admin->>Livewire: Selects Doctor (e.g., Dr. Rajesh Sharma, ID 1)
    Livewire->>BTService: getEnabledForDoctor(doctorId=1, clinicId=1, requireActiveFee=false)
    BTService->>MapDB: SELECT mapped booking types WHERE doctor_id=1 AND clinic_id=1
    BTService-->>Livewire: Returns mapped types with current tariff rates
    Livewire-->>Admin: Displays doctor-specific tariff matrix table

    Note over Admin, FeeDB: Step 2: Exact Fee Rate Configuration
    Admin->>Livewire: Enters Tariff: Clinic Consultancy = ₹600, Online = ₹700, Emergency = ₹1500
    Admin->>Livewire: Clicks "Save Doctor Tariff"
    Livewire->>BTService: setDoctorFee(clinicId=1, doctorId=1, bookingTypeId, amount)
    BTService->>FeeDB: Upsert fee_masters [clinic_id=1, doctor_id=1, booking_type_id] (amount, is_active=1)
    Note over FeeDB: Enforced by UNIQUE(clinic_id, doctor_id, booking_type_id)
    FeeDB-->>Livewire: Fee records active & effective
    Livewire-->>Admin: Displays success flash: "Tariff updated successfully for Dr. Rajesh Sharma"
```

---

## 10. Unified Consultation Booking & Fee Resolution Workflow (Revision 45)

```mermaid
sequenceDiagram
    autonumber
    actor Patient as External Patient / Reception Operator
    participant UI as OperatorBooking / PatientBooking PWA
    participant BTService as BookingTypeService
    participant ApptService as AppointmentService
    participant FeeDB as fee_masters Table
    participant ApptDB as appointments Table
    participant TokenSeq as token_sequences Table

    Note over Patient, UI: Step 1: Select Attending Doctor (Primary Filter)
    Patient->>UI: Selects Doctor (e.g. Dr. Rajesh Sharma)
    UI->>BTService: getEnabledForDoctor(doctorId, clinicId, requireActiveFee=true)
    BTService->>FeeDB: Checks active fee for (clinic_id, doctor_id, booking_type_id)
    Note over BTService: ANTI-FALLBACK RULE: Missing fee != zero fee. Types without fees are excluded!
    BTService-->>UI: Returns ONLY mapped booking types with active fees (attaches resolved_fee)
    UI-->>Patient: Displays dynamic booking type pills with exact doctor fees (e.g. ₹600)

    Note over Patient, ApptDB: Step 2: Book Appointment & Snapshot Tariff
    Patient->>UI: Selects "Clinic Consultancy (₹600)", Enters Patient Info, Clicks Book
    UI->>ApptService: bookAppointment(clinicId, doctorId, bookingTypeId, patientData)
    ApptService->>ApptService: Forensic Verification:
    Note over ApptService: 1. doctor.clinic_id === authenticated clinic_id
    Note over ApptService: 2. booking_type.clinic_id === authenticated clinic_id
    Note over ApptService: 3. doctor_booking_type_availability.is_enabled === 1
    Note over ApptService: 4. FeeMaster::getFeeForBookingType(clinic, doctor, type) != null
    ApptService->>TokenSeq: Atomically increment unified session token (e.g. Token #005)
    ApptService->>ApptDB: Insert appointments (clinic_id, doctor_id, booking_type_id, consultation_mode, fee_amount=600.00, token_number)
    Note over ApptDB: fee_amount snapshotted permanently on appointment record
    ApptDB-->>UI: Appointment created successfully
    UI-->>Patient: Renders token receipt with doctor name, booking type, and fee (₹600.00)
```

---

## 11. Conceptual Demarcation: The Four Independent Booking Dimensions

Revision 45 enforces strict separation of four orthogonal operational concepts:

```
+---------------------------------------------------------------------------------------------------+
| 1. BOOKING SOURCE                                                                                 |
| Where the booking originated.                                                                     |
| Values: 'CLINIC' (Walk-in Desk), 'ONLINE' (Patient PWA), 'TELEPHONIC' (Phone Inbound)              |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| 2. CONSULTATION MODE                                                                              |
| How the consultation/service is delivered to the patient.                                         |
| Values: 'CLINIC_PHYSICAL' (In-person), 'ONLINE' (Video/Tele-consult), 'VISITING_CONSULTANCY'       |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| 3. BOOKING TYPE                                                                                   |
| What clinical or service package the patient is booking.                                         |
| Values: 'CLINIC_CONSULTANCY', 'ONLINE_CONSULTANCY', 'EMERGENCY_CONSULTANCY', or Custom Clinic Type |
| Tenant Ownership: Exactly one clinic (booking_types.clinic_id).                                   |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| 4. DOCTOR TARIFF / FEE MAPPING                                                                    |
| Financial rate explicitly configured for (clinic_id + doctor_id + booking_type_id + effective_from)|
| Authoritative Lookup: FeeMaster::getFeeForBookingType($clinicId, $doctorId, $bookingTypeId, $date)|
| Anti-Fallback Guard: If unconfigured, booking is BLOCKED. Explicit ₹0.00 allowed; missing != 0.00 |
+---------------------------------------------------------------------------------------------------+
```

---

## 12. Revision 46: Database-Level Enforced Atomic Booking Transaction Workflow

```mermaid
sequenceDiagram
    autonumber
    actor User as Patient (PWA) / Operator Desk
    participant ApptService as AppointmentService
    participant BTService as BookingTypeService
    participant DB as MySQL InnoDB Database

    User->>ApptService: bookAppointment(payload)
    ApptService->>DB: Begin DB::transaction()
    
    rect rgb(240, 248, 255)
    Note over ApptService,DB: 1. Tenant & Doctor Verification
    ApptService->>DB: SELECT * FROM clinics WHERE id = clinic_id
    ApptService->>DB: SELECT * FROM doctors WHERE id = doctor_id FOR UPDATE
    ApptService->>ApptService: Enforce doctor.clinic_id == active_clinic_id
    end

    rect rgb(255, 250, 240)
    Note over ApptService,DB: 2. Booking Type & Doctor Availability
    ApptService->>DB: SELECT * FROM booking_types WHERE id = booking_type_id
    ApptService->>ApptService: Enforce booking_type.clinic_id == active_clinic_id
    ApptService->>DB: SELECT * FROM doctor_booking_type_availability WHERE clinic_id = ? AND doctor_id = ? AND booking_type_id = ? AND is_enabled = 1
    ApptService->>ApptService: Reject if not enabled for doctor
    end

    rect rgb(240, 255, 240)
    Note over ApptService,DB: 3. Exact Fee Resolution & Snapshoting
    ApptService->>DB: SELECT amount FROM fee_masters WHERE clinic_id = ? AND doctor_id = ? AND booking_type_id = ? AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)
    ApptService->>ApptService: Reject if fee missing (No silent fallback)
    end

    rect rgb(255, 240, 245)
    Note over ApptService,DB: 4. Token Allocation & Atomic Insert
    ApptService->>DB: TokenEngine::allocateToken(clinic_id, appointment_date, session_type)
    ApptService->>DB: INSERT INTO appointments (clinic_id, patient_id, doctor_id, booking_type_id, consultation_mode, booking_source, fee_amount, token_number)
    Note over DB: InnoDB verifies Composite FKs: (doctor_id, clinic_id) & (booking_type_id, clinic_id)
    end

    alt Any Validation or DB Constraint Failure
        ApptService->>DB: Rollback Transaction
        ApptService-->>User: Throw InvalidArgumentException / 422 Error
    else Success
        ApptService->>DB: Commit Transaction
        ApptService-->>User: Return Appointment Model with Snapshot Fee
    end
```

---

## 12. Revision 47: Public Slug Resolution & Tenant Routing Security Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Patient as Public Patient
    actor Operator as Reception Operator
    participant Router as Laravel Router
    participant TenantCtx as EnsureTenantContext
    participant SlugSvc as PublicSlugService
    participant DB as MySQL Database

    rect rgb(240, 248, 255)
    Note over Patient,Router: 1. Public Self-Booking PWA Flow
    Patient->>Router: GET /book/{slug} or /b/{slug}
    alt Route is /b/{slug}
        Router-->>Patient: HTTP 302 Redirect to /book/{slug}
    end
    Router->>TenantCtx: Invoke EnsureTenantContext middleware
    TenantCtx->>SlugSvc: findByPublicSlug(slug)
    SlugSvc->>DB: SELECT * FROM clinics WHERE public_slug = ? AND status != 'REVOKED'
    alt Slug Not Found OR Request contains internal code (e.g. NX0001)
        SlugSvc-->>TenantCtx: Null
        TenantCtx-->>Patient: HTTP 404 Not Found (Zero cross-clinic leaking)
    else Active Clinic Found
        SlugSvc-->>TenantCtx: Clinic Instance
        TenantCtx->>TenantCtx: Bind authoritative Clinic into TenantContext
        TenantCtx-->>Patient: Render PatientBooking PWA View (Scoped to Clinic)
    end
    end

    rect rgb(255, 245, 238)
    Note over Operator,Router: 2. Authenticated Operator Queue Flow
    Operator->>Router: GET /clinic/{slug}/operator/queue
    Router->>TenantCtx: Verify Web Auth & Tenant Context
    alt Unauthenticated
        TenantCtx-->>Operator: HTTP 302 Redirect to /login
    else Authenticated
        TenantCtx->>DB: Load auth_user->clinic_id
        TenantCtx->>SlugSvc: findByPublicSlug(slug)
        alt Slug Mismatch with auth_user->clinic_id
            TenantCtx-->>Operator: HTTP 403 Forbidden (Cross-Tenant Tampering Blocked)
        else Match Verified
            TenantCtx-->>Operator: Render ArrivalQueue Component (Tenant Scoped)
        end
    end
    end

    rect rgb(255, 235, 235)
    Note over Patient,Router: 3. Anti-Tampering Perimeter Defense
    Patient->>Router: GET /book/{slug}?clinic_id=2 or with Header X-Clinic-ID: 2
    Router->>TenantCtx: Detect query/header/payload override attempt
    TenantCtx-->>Patient: HTTP 403 Forbidden (Tampering Rejected)
    end
```




