# DxClinic Operator Complete Desk Operation Manual
**DxClinic Clinical Management System — Revision 35**  
*Manual Type: Full Operator Operation Manual | Version: Revision 35 | Last Updated: September 2026 | Developer: Kundan Kumar | Copyright © 2026 DxClinic*

**Application Developer:** Kundan Kumar | **Development Company:** Nexora Solutions, Varanasi, Uttar Pradesh, India  
**Website:** https://nexorasolutionsind.co.in/ | **Contact:** +91 98765 43210 | **Email:** contact@nexorasolutionsind.co.in  

---

## 1. System Access & Reception Login
1. Open web browser (Google Chrome or modern Chromium browser).
2. Navigate to the clinic reception portal: `http://127.0.0.1:8000/login` (Local) or `https://dxclinic.example.local/login` (Remote HTTPS).
3. Enter your authorized Operator email and password. Click **Sign In**.
4. Open Morning or Evening Cash Session with physical drawer cash before processing patients.

---

## 2. Dedicated Operator Booking (`/operator/booking`)
*CRITICAL SEPARATION: Reception staff MUST use `/operator/booking`. Public patient self-booking is strictly separated at `/book/{public_slug}` (e.g. `/book/sharmacare`).*

1. Click **Operator Booking** in the main navigation menu.
2. Verify visual badge: **Booking Source: Clinic Operator**.
3. **Patient Identification**:
   - **Search Existing Patient**: Type patient name, 10-digit mobile number, or `DXP-XXXXX` in the search box. Click the matching patient record to auto-populate.
   - **Register New Patient**: If the patient is not found, enter:
     - **Full Name**: Letters and single spaces only (e.g., `Kundan Kumar` or `राम कुमार`). Digits and symbols are rejected.
     - **Age**: Integer from 0 (for newborns) to 130 years.
     - **Gender**: Select Male, Female, or Other.
     - **Mobile Number**: Enter 10 digits (`9876543210`), `+91 9876543210`, or `09876543210`. The system automatically normalizes it to canonical `+91 9876543210`.
     - **Address & City**: Enter street address and dedicated city field (defaults to Varanasi).
4. **Select Attending Doctor (Primary Filter)**:
   - Single active doctor: Auto-selected.
   - Multiple active doctors: Pre-selects configured Default Booking Doctor. Operator can choose another physician.
   - **Crucial Rule**: Selecting a doctor dynamically queries the server and loads ONLY the booking types explicitly mapped to that doctor with active fee configurations.
5. **Select Mapped Booking Type & Resolved Doctor Fee**:
   - `[ ] 🏥 Clinic Consultation — ₹600` (or doctor-specific rate)
   - `[ ] 🌐 Online Consultancy — ₹700` (or doctor-specific rate)
   - `[ ] 🚨 Emergency Consultancy — ₹1500` (requires active doctor emergency fee)
   - Custom clinic offerings (e.g. `Home Healthcare Visit`) if enabled for this doctor.
   - **Anti-Fallback Guard**: If a booking type lacks a configured fee for this doctor, it does not appear and cannot be booked. Zero silent fallback.
6. **Select Date & Session**: Choose appointment date and Morning / Evening / Sunday session.
7. Click **[Allocate Common Queue Token]**.
8. The system assigns a unique daily queue token (`001`, `002`...) from the unified sequence. Print the token slip for the patient.

---

## 3. Public Patient Booking PWA Difference (`/book/{public_slug}`)
- Public patients access their clinic's dedicated slug URL: `http://127.0.0.1:8000/book/{public_slug}` (or production `https://dxclinic.example.local/book/{public_slug}`, or short redirect `/b/{public_slug}`).
- Unselected `/book` and internal codes (`/book/NX0001`, `/book/1`) return HTTP 404 to ensure complete tenant safety and isolation.
- Public booking creates `booking_source = ONLINE`.
- Visual badge displays: **Booking Source: Online Patient**.
- No online payment gateway is used. Booking creates an appointment, token, and an **UNPAID / PENDING fee obligation**.
- Payment settlement occurs at the clinic counter or via physical UPI Soundbox.

---

## 4. Arrival Queue & Vitals Recording (`/clinic/{public_slug}/operator/queue`)
- Reception staff access the queue at `/clinic/{public_slug}/operator/queue` (or through the navbar link).
- The route enforces authenticated tenant scoping: accessing another clinic's queue returns HTTP 403 Forbidden, and unselected `/operator/queue` returns HTTP 404.
1. When a patient arrives at the clinic, locate their row by token number or name.
2. Click **[Mark Arrived]**. The status updates to `ARRIVED`, and a clinical Visit record is created.
3. For physical consultations, record vital signs:
   - Weight (kg)
   - Blood Pressure (Systolic / Diastolic mmHg)
   - Pulse (bpm)
   - SpO2 (%)
   - Temperature (°F)
4. Click **[Save Vitals]** and **Print Doctor Pad** for the attending physician.

---

## 4b. Dedicated Real-Time Live Queue Window (`/clinic/{public_slug}/queue/live`)
1. Click **[Open Live Queue]** button on the Arrival Queue toolbar or navigation bar.
2. The Live Queue launches in an **independent pop-out browser window** optimized for reception displays and clinic TV monitors.
3. **Operational Guarantees**:
   - **Zero Business Side Effects**: Opening, closing, or reloading the Live Queue window NEVER mutates appointments, cancels tokens, or affects financial sessions.
   - **Unified Sequence Across Channels**: Displays tokens allocated from Reception (`CLINIC`), Online PWA (`ONLINE`), WhatsApp (`WHATSAPP`), and Telephonic (`TELEPHONIC`) in strict ascending numeric order.
   - **Token Invariant**: Tokens are **unique, monotonic, and never reused**.
     - If a token is marked `CANCELLED` or `ABSENT`, that token number is permanently retired and will NEVER be assigned to another patient.
     - Expected gaps (e.g. `001, 002, 004`) occur when a token has been retired. The sequence is strictly **NOT guaranteed gapless**.
   - **Audible Alerts & Toast Notifications**: Features a sound toggle button that chimes when new tokens are booked in real time from WhatsApp or the Online PWA.
   - **Automatic Polling Fallback**: If WebSockets or network drop temporarily, the window automatically falls back to 5-second polling without disrupting staff.

---

## 5. Consultation Fee Billing & Revisit Rules (`/operator/consultation/{id?}`)

### 5.1 Fee Master Rates
- Clinic Consultancy: **₹600.00** (or doctor-specific rate if configured)
- Online Consultancy: **₹700.00** (or doctor-specific rate if configured)
Fees resolve dynamically from Fee Master hierarchy (Doctor -> Clinic default -> System fallback). Never hardcode prices.

### 5.2 Free OPD Revisit (5-Day Rule)
- **Automatic Rule**: If a patient completed a qualifying physical consultation within the past **5 calendar days**:
  - Gross Amount: **₹600.00**
  - Revisit Waiver (`waiver_type = 'OPD_REVISIT'`): **₹600.00**
  - Net Amount: **₹0.00**
  - Status: `'WAIVED'`
- **Accounting Rule**: Zero cash collection, zero UPI collection, zero bank movement. **No payment record is created.**

### 5.3 Payment Settlement (When Net > 0)
- **Cash**: Enter cash received, click **[Collect Cash & Settle]**.
- **UPI Soundbox**: Present QR, verify audible voice confirmation from physical Soundbox device, check **[Soundbox Voice Confirmed]**, and click **[Settle UPI]**.
- Print strict **A5 Receipt** (`148mm x 210mm`) with Patient Copy and Clinic Copy.

---

## 6. Historical Billing & Management (`/operator/consultation` & `/operator/diagnostics`)
*Revision 26 introduces full historical billing management workspaces.*

### 6.1 Consultation Billing Navigation Tabs
Navigate to `/operator/consultation`. Three tabs are available:
1. **[New Consultation Billing]**: Standard desk workflow for pending token settlements.
2. **[Consultation Billing History]**: Full historical ledger of all consultation bills.
3. **[Doctor Pad History]**: Historical clinical encounter repository for reprinting pre-printed doctor pads with active or archived template references.

### 6.2 Searching & Filtering Historical Consultation Bills
The **Consultation Billing History** tab provides multi-parameter filtering:
- **Date Range**: Filter by Date From and Date To (defaults to today).
- **Search Patient**: Filter by Patient Name, ID (`DXP-...`), or 10-digit Mobile.
- **Search Bill Number**: Exact or partial match (e.g., `CBL-2026-...`).
- **Search Token**: Instant lookup by daily queue token number.
- **Attending Doctor**: Filter by specific physician.
- **Consultation Mode**: Filter by `CLINIC_PHYSICAL` or `ONLINE_CONSULTANCY`.
- **Payment Method**: Filter by `CASH` or `UPI`.
- **Status**: Filter by `PAID`, `PENDING`, `WAIVED`, `CANCELLED`, or `REFUNDED`.

### 6.3 Consultation Row Actions — Strict Sequence Order
Every eligible row in Consultation Billing History features action buttons arranged in the exact order:
$$\mathbf{[View] \rightarrow [Edit] \rightarrow [View\ Pad] \rightarrow [View\ Changes] \rightarrow [Refund] \rightarrow [Receipt]}$$
- **[Edit] Placement**: The **[Edit]** button appears **immediately BEFORE [View Pad]**.
- Clicking **[View]** opens the read-only historical ledger summary.
- Clicking **[Edit]** opens the controlled modification workflow. For paid bills, direct overwriting is blocked; corrections require a mandatory reason ($\ge 10$ chars) and are executed via audited refund or receipt revision.
- Clicking **[View Pad]** generates the pre-printed Doctor Pad according to the attending doctor's resolved template.
- Clicking **[View Changes]** opens the complete audit trail modal.
- Clicking **[Refund]** initiates full or partial refund processing.
- Clicking **[Receipt]** reprints the A5 consultation receipt idempotently.

---

## 7. Doctor Pad History & Reprinting

### 7.1 Accessing Historical Doctor Pads
1. Open `/operator/consultation` and click the **[Doctor Pad History]** tab.
2. Search by Patient Name/ID, Attending Doctor, or Date Range.
3. Columns displayed:
   - Appointment Date & Token Number
   - Patient Name, Gender, Age, City
   - Attending Doctor & Specialization
   - Template / Version (e.g. `Dr. Sharma Pad v2` or `Legacy Configuration`)
   - Vitals Capture Status (`Recorded` vs `Pending`)
   - Actions: `[View]`, `[Preview]`, `[Reprint]`, `[View Template]`, `[View Changes]`

### 7.2 Idempotent Reprinting
- Click **[Reprint]** or **[View]**.
- The resolver checks the historical template assigned to the encounter:
  - If generated under a specific template version (e.g. V1), it renders using that exact archived version, never mutating to today's active template.
  - If generated under legacy settings, it falls back safely to the legacy renderer.
- Output strictly adheres to pre-printed stationery overlay calibration:
  - Prints: Patient Name, Age/Sex, City/Address, Date, Token Number, and recorded Vitals.
  - Omitted: Scanned background, clinic logos, headers, QR codes, financial details.
- **Strict Safety Rule**: Reprinting generates **zero payment records**, **zero accounting journals**, **zero refunds**, and **zero duplicate visits/tokens**.

### 7.3 Physical Prescription Pad Printing & Alignment Standards (Revision 30)
1. **Selecting Attending Doctor & Template:**
   - Always confirm the attending doctor during token allocation and arrival. DxClinic automatically loads the doctor's active template (or legacy layout if no custom template exists).
2. **Strict Actual Size (100%) Printing:**
   - In the print dialog, always choose **Scale: 100%** (or **Actual Size**).
   - **CRITICAL**: Never select *"Fit to Page"*, *"Fit to Printable Area"*, or *"Shrink oversized pages"*. These options rescale the coordinates and throw field values out of alignment with the pre-printed paper lines.
   - Choose **Margins: None** (or Minimum).
3. **Multilingual (Hindi / Devanagari) Support:**
   - Patient names, addresses, or clinical remarks recorded in Hindi (e.g., `राम कुमार`, `वाराणसी, उत्तर प्रदेश`) render cleanly with full TrueType font fidelity.
   - No special configuration is needed at the desk; Unicode text prints seamlessly alongside English numbers and vitals.
4. **Detecting & Reporting Misalignment:**
   - If physical text prints slightly off-center relative to pre-printed stationery boxes, measure the shift with a ruler (e.g., "text is 2 mm too low").
   - Report this to the Clinic Administrator. The Administrator updates the **Printer Profile** (Offsets $X, Y$), which corrects printing across the clinic without mutating the doctor's template coordinates.
5. **Historical Doctor Pad Reprints:**
   - Any historical consultation can be reprinted from `/operator/consultation > [Doctor Pad History]`. DxClinic guarantees reproduction using the exact template version active on the consultation date.

---

## 8. Diagnostic Billing History (`/operator/diagnostics`)

### 8.1 Diagnostic Workspace Tabs & Row Sequence
Navigate to `/operator/diagnostics`. Two tabs are available:
1. **[New Diagnostic Billing]**: Standard desk workflow for test booking, discounting, and collection.
2. **[Diagnostic Billing History]**: Full historical ledger of all diagnostic bills.

**Strict Column Ordering:**
In Diagnostic Billing History, table headers and row action buttons strictly follow the sequence:
$$\mathbf{Status\ \vert\ Edit\ \vert\ Method}$$
The **[Edit]** button MUST appear between **Status** and **Method**.

### 8.2 Historical Line-Item Rate Preservation & Supplementary Billing
- Click **[Edit]** or **[View]** on any historical diagnostic bill.
- **Historic Rate Invariant**: Historical bills permanently preserve original test/package names, types, quantities, rates, and amounts. They are NEVER recalculated using today's master tariff rates.
- **Modifications**:
  - For unsettled bills: in-place corrections with mandatory reason.
  - For settled bills needing a price reduction: processed via audited partial or full refund.
  - For settled bills needing an increase (e.g. patient requires an additional test): the original settled bill remains immutable. Staff creates a **Supplementary Diagnostic Bill** linked via `parent_diagnostic_bill_id`, which is independently billed, paid, receipted, and accounted.

---

## 9. Controlled Bill Modification & Refund Workflow

### 9.1 Financial Safety & Immutability Rules
- **NEVER OVERWRITE SETTLED RECORDS**: Settled payments, receipts, journals, and bill values cannot be edited directly.
- **Transactional Audit Trail**: All financial corrections generate new auditable records (`refunds`, `receipt_revisions`, `audit_logs`, or supplementary bills).

### 9.2 Modifying a PENDING (Unpaid) Bill
- If a bill is still `PENDING` and has no settled payment, the operator can update line items or discounts.
- **Mandatory Reason Requirement**: The operator must enter an explanation of at least **10 characters**. Short or generic reasons (e.g., `"edit"`, `"change"`, `"ok"`) are rejected server-side.
- Financial figures are re-calculated server-side. An audit log is recorded.

### 9.3 Doctor-Recommended Discount After Payment
- If a physician recommends a discount after the patient has paid (e.g., ₹100 discount on a ₹600 consultation):
  1. Locate the bill in **Consultation Billing History** and click **[Modify / Refund]**.
  2. Select Modification Type: `DISCOUNT_AFTER_SETTLEMENT` (or `PARTIAL_REFUND`).
  3. Enter the refund amount: `100.00`.
  4. Enter the **Mandatory Reason** (e.g., `"Refund for discount given as per doctor recommendation."`).
  5. Select the Attending Doctor who gave the instruction.
  6. Select Refund Settlement Method (`CASH` or `UPI`).
  7. **Financial Result**:
     - Original Bill remains ₹600.00 (immutable).
     - Original Payment remains ₹600.00 (immutable).
     - Refund of ₹100.00 is settled and recorded.
     - Net Retained Revenue becomes ₹500.00.
     - A balanced double-entry accounting reversal journal is created.
     - A **Receipt Revision** record is created without mutating the original receipt.

### 9.4 Full Refund Workflow
- A full refund refunds the total settled payment (`refund_amount = remaining refundable amount`).
- The bill status transitions to `REFUNDED` only when 100% of the settled payment is refunded.

### 9.5 Operator Role Boundaries
- **Operator Can**:
  - View all historical consultation and diagnostic bills.
  - Reprint receipts, doctor pads, and lab collection slips.
  - Submit refund / modification requests with mandatory reasons and doctor references.
  - Perform authorized direct refunds when operating within active cash counter permissions.
- **Operator Cannot**:
  - Overwrite historical settled amounts directly.
  - Bypass server-side reason validation.
  - Over-refund beyond the original settled payment minus prior refunds.
  - Delete audit logs or historical billing entries.

---

## 10. Audit Trail Inspection
- Click **[Audit Log]** on any historical bill.
- The drawer displays the chronological timeline of all changes:
  - Timestamp & Operator / Admin Name
  - Change Category (`BILL_CORRECTION`, `REFUND`, `DISCOUNT_AFTER_SETTLEMENT`, `SUPPLEMENTARY_BILL`)
  - Mandatory Reason Recorded
  - Old Values vs New Values
  - Financial Impact & Linked Refund ID

---

## 11. Reports Subsystem (`/reports/opd` and `/reports/diagnostics`)
1. **Session-wise Patient Report**: Filter by Date, Doctor, and Session (Morning, Evening, Sunday).
2. **Day-wise Patient Report**: View chronological daily patient list.
3. **Doctor-wise & Combined Reports**: View specific doctor's caseload or All Doctors combined.
4. **Strict Format**: `Patient ID | Patient Name | Age | Address | City | Mobile | Token | Session | Booking Source | Consultation Type | Paid Fee | Payment Mode | Doctor`.
5. **INVARIANT**: Patient reports strictly omit vitals.
6. **TOTAL COLLECTION Footer**: Calculates strictly from settled payments. Free revisits show ₹0.00.
7. Export reports via View, Print, Download real PDF, or Export CSV.

---

## 12. AI Operator Assistant (RAG)
1. Click the floating chat icon in the bottom-right corner.
2. Ask operational guidance questions:
   - *"How do I view old consultation bills?"*
   - *"How do I process a refund recommended by a doctor?"*
   - *"Why can't I edit a paid bill directly?"*
   - *"What is today's cash collection?"*
3. **Safety Guard**: The AI Assistant is strictly informational. It **CANNOT execute financial modifications, process refunds, or post accounting journals**. All financial operations must occur within the authenticated billing screens.

## 13. Billing History & Navigation Quick Reference (Revision 27)

### 13.1 Consultation Billing History Actions
In `/operator/consultation`:
- Locate the historical bill in **Consultation Billing History**.
- Action buttons appear in the sequence:
  `[View]  [Receipt]  [Edit]  [Pad]  [Audit]  [Refund / Adjust]`
- **[Edit] Button**: Appears immediately before **[Pad]**. Clicking it opens the controlled modification or refund modal.
- **[Pad] Button**: Instantly reprints the patient's Doctor Pad. If the doctor has an active custom template, it renders with the new template; otherwise it uses the legacy layout.

### 13.2 Diagnostic Billing History Actions
In `/operator/diagnostics`:
- The history table features a dedicated **Edit** column located directly between **Status** and **Method**:
  $$\textbf{Status} \quad|\quad \textbf{Edit} \quad|\quad \textbf{Method}$$
- Click **[Edit]** to adjust pending test items or initiate an authorized refund for settled diagnostic bills.

### 13.3 Clean Left Navigation Hierarchy
The left sidebar is streamlined for daily clinical workflows:
1. **Daily Work** (Top): Dashboard, OPD Booking, OPD Queue, Patients, Consultation Billing, Diagnostic Billing, and Cash Counter.
2. **Reports** (Collapsible): OPD Reports, Diagnostic Reports, Financial Reconciliation, and Daily Cash Report.
3. **Help & Guide** (Near Bottom): Collapsible group with direct links to FAQ, User Manuals, Quick Guides, and About Developer.

---

## 14. Doctor Pad Printing: Proof vs Production
- **Production Mode (Standard Print)**: When an operator prints a Doctor Pad via **[Pad]**, only dynamic clinical facts (Patient Name, Age/Sex, Vitals, Token, Date) are printed onto transparent background. This prevents double-printing when feeding physical pre-printed stationery.
- **Missing Vitals**: If vitals appear blank, it signifies no vitals were measured for that encounter. The system never fabricates placeholder values.

---

## 15. Historical Doctor Pad Reprints & Immutability (Revision 28)
- **Exact Historical Version Reproduction**: When clicking **[Pad]** to reprint an old consultation, DxClinic renders the document using the **exact template version** that was active when that encounter was committed.
- **No Impact from New Templates**: Even if the doctor's active template was updated to a newer version (e.g. V2 or V3), old consultations remain completely unaffected and reprint using their original layout.
- **Archived Templates Remain Printable**: Older templates that were archived remain active in read-only mode for historical reprints.
- **Legacy Records**: Older visits recorded before custom templates were configured continue to print smoothly using the legacy pre-printed pad renderer.
- **Idempotency**: Reprints can be performed as many times as needed with zero financial side effects.

---

## 16. Multi-Tenant Operation & Public Booking Links (Revision 34 Hardening)

### 16.1 Operator Authentication & Clinic Context
- Operators sign in using their **Clinic ID** (e.g. `NX0001`), **User ID** (e.g. `OPR0001`), and **Password**.
- Once signed in, all patient records, queues, appointments, doctors, and financial sessions are strictly isolated to your clinic.
- System security prevents any cross-clinic data leakage.

### 16.2 Clinic-Specific Public Booking & QR Codes
- Share your clinic's unique short booking URL with walk-in and online patients:
  $$\text{https://yourdomain.com/b/} \mathbf{NX0001}$$
- Patients scanning the clinic's QR code or visiting this link see exclusively your clinic's name, active doctors, consulting hours, and fees.
- Tokens issued through this link automatically appear on your **OPD Queue** (`/operator/queue`) in real time.

### 16.3 Mobile Number Registration Limits
- The configured family patient limit (default 4 patients per mobile number) is strictly clinic-scoped.
- A family registered at another facility can independently register family members at your clinic without hitting cross-facility caps.

---

## 17. Doctor-First Booking & Exact Mapped Tariff Protocols (Revision 46)

### 17.1 Doctor-First Operator Workflow
When booking appointments at the Frontdesk (`/operator/booking` or modal):
1. **Select Attending Doctor First**: The operator must first select the doctor who will attend the patient.
2. **Server-Side Dynamic Type Scoping**: Selecting the doctor initiates a live server query that returns **ONLY** the booking types enabled for that doctor in `doctor_booking_type_availability`.
3. **No Cross-Doctor Leakage**: If Doctor A offers *Online Consultancy* but Doctor B does not, selecting Doctor B immediately removes *Online Consultancy* from the screen.
4. **Exact Tariff Display**: Each booking type card displays the exact fee configured in Fee Master for that doctor and type. If no fee is configured, the type is unbookable.

### 17.2 Emergency Consultancy Protocol
- Emergency Consultancy is an extensible booking type governed by the exact same integrity rules as regular consultancy.
- It requires an explicit doctor-specific fee.
- If Doctor A charges ₹1,500 and Doctor B charges ₹1,800, selecting Doctor A will **NEVER** display or charge Doctor B's rate.
- If an emergency fee has not been configured for a doctor, emergency booking for that doctor is rejected.

### 17.3 Token Numbering & Financial Immutability
- All booking types share the unified clinic daily token sequence (e.g. 001 Clinic, 002 Emergency, 003 Online).
- The resolved fee is snapshotted permanently into `appointments.fee_amount` at the exact moment of booking.
- Subsequent changes made by administrators in Fee Master will only apply to future bookings, preserving historical bills, receipts, and accounting journals intact.

---

## 18. Diagnostic Laboratory Workflow & Specimen Management (Revision 36)

### 18.1 Overview & Architecture
Revision 36 introduces an end-to-end clinical laboratory workflow at `/lab` that seamlessly extends the diagnostic process AFTER billing and settlement:
$$\text{Diagnostic Order} \longrightarrow \text{Diagnostic Bill/Settlement} \longrightarrow \text{Specimen Collection} \longrightarrow \text{Tracking} \longrightarrow \text{Result Entry} \longrightarrow \text{Clinician Verification} \longrightarrow \text{Report Release}$$

The workflow provides three operational views:
1. **Investigation Worklist**: Active queue of diagnostic orders requiring sample collection, result entry, or clinical authorization.
2. **Sample Tracking**: Comprehensive register of collected, in-process, rejected, and recollected biological specimens.
3. **Released Reports**: Repository of authorized diagnostic reports ready for printing, PDF download, and QR verification.

### 18.2 Specimen Collection Workflow
1. From the **Investigation Worklist**, locate the patient order by Order #, Patient Name, or Patient ID (`DXP-XXXXX`).
2. Verify financial status: The bill amount and settlement status (e.g., `PAID`) are displayed as read-only clinical context.
3. Click **[Collect Sample]**. The collection modal displays ordered investigations, container requirements, and collector identity.
4. Select the specimen container/type (e.g. `Blood (Whole / EDTA)`, `Serum`, `Urine`, `Plasma`) and enter optional collection notes.
5. Click **[Confirm Collection & Generate Sample ID]**.
6. The system atomically allocates a permanent, human-readable specimen identifier:
   $$\mathbf{SMP}\text{-}\{\text{CLINIC\_CODE}\}\text{-}\{\text{YYYYMMDD}\}\text{-}\{\text{XXXX}\} \quad \text{e.g., } \mathbf{SMP}\text{-}\mathbf{NX0001}\text{-}\mathbf{20260930}\text{-}\mathbf{0001}$$
7. **Idempotency Guarantee**: Row-level database locks ensure double-clicking the collection button never creates duplicate specimen records. Once collected, the button is permanently replaced by the active specimen identifier badge.

### 18.3 Specimen Rejection & Recollection Protocol
If a specimen is compromised (e.g. hemolyzed, insufficient volume, clotted):
1. In **Sample Tracking**, click **[Reject Sample]**.
2. Enter a mandatory clinical rejection reason (minimum 5 characters).
3. The specimen status is set to `REJECTED`, and the parent diagnostic order reverts to `SAMPLE_PENDING`. The rejected record is permanently retained for audit compliance.
4. When a new sample is drawn, click **[Initiate Recollection]**. A new specimen identifier is generated and linked to the parent order while preserving the historical rejection trail.

### 18.4 Structured Result Entry
1. Once a specimen is collected, click **[Enter Results]** from the worklist or sample tracking.
2. The modal loads test parameters with configured reference ranges, units, and result types:
   - **Numeric**: Values with units and reference intervals (e.g. Hemoglobin `14.2` g/dL).
   - **Qualitative**: Positive/Negative, Reactive/Non-Reactive.
   - **Text / Narrative**: Descriptive morphological observations.
3. Enter test values and optional clinical remarks.
4. Click **[Save Results]**. The results are saved with status `ENTERED (Draft)`.

### 18.5 Operator Role Boundaries & Verification Demarcation
- **Operator Authority**: Operators are authorized to collect specimens, track laboratory status, and input draft results.
- **Verification Demarcation**: Operators are **strictly forbidden** from verifying results or releasing final reports. The **[Verify]** and **[Release Report]** buttons are automatically hidden from Operator view, and server-side authorization blocks any unauthorized API/Livewire calls.
- Verification and final report release are exclusive responsibilities of Doctors and Admins.

### 18.6 Financial Separation & Immutability
- Laboratory operations (collection, rejection, result entry, correction) generate **zero financial transactions**, zero journal entries, and zero cash drawer alterations.
- Diagnostic billing remains the immutable financial source of truth.

### 18.7 Diagnostic Workflow Pure Demarcation
- Laboratory operations remain strictly operational and clinical. Diagnostic billing remains the immutable financial source of truth.

---

## 19. Real-Time Live Queue & WhatsApp Booking Operations

### 19.1 Opening, Closing & Reopening the Live Queue Window
- **Opening the Window**: Click the green **[Open Live Queue]** button in the top navigation bar or the Arrival Queue screen. This opens a dedicated, dark-themed secondary display window at 1280x800 resolution.
- **Window Independence**: The Live Queue runs in its own window. You can minimize, move it to a patient-facing secondary monitor, or close it at any time.
- **Zero Impact on Reception**: Closing the Live Queue **does not logout** your user session, close your cash drawer, or cancel any appointments.
- **Reopening**: You can click **[Open Live Queue]** at any time. It instantly re-syncs the authoritative current daily queue from the database.

### 19.2 Monitoring New Tokens & Audio Chime
- When a new patient books via Reception (`CLINIC`), Patient PWA (`ONLINE`), or WhatsApp (`WHATSAPP`), the Live Queue updates in real time.
- A toast notification briefly alerts staff: *"New Token #037 — Rahul Kumar (Source: WHATSAPP)"*.
- **Sound Toggle**: An optional chime sound can be toggled ON or OFF via the **[Sound: ON / OFF]** button.

### 19.3 Identifying Booking Sources
The Live Queue displays authoritative booking source badges for each queue entry:
- `CLINIC` (Walk-in reception booking)
- `ONLINE` (Patient self-booking via PWA `/book/{public_slug}`)
- `WHATSAPP` (Automated booking via Meta WhatsApp bot)
- `TELEPHONIC` (Booked via phone reception call)

### 19.4 Single Unified Daily Token Series
All booking sources share **one single continuous token sequence** (e.g. 001 Reception &rarr; 002 PWA &rarr; 003 WhatsApp &rarr; 004 Walk-in). There are no separate "WhatsApp token counters" or "PWA token counters".

### 19.5 Realtime States & Automatic Recovery
- `REALTIME CONNECTED` (Green badge): Operating via live WebSockets.
- `RECONNECTING` (Amber badge): Temporary network drop; system is actively reconnecting.
- `FALLBACK REFRESH` (Blue badge): Polling fallback ensures continuous queue updates even without WebSockets.

### 19.6 Human Handoff Support
- If a WhatsApp patient requests *"Talk to Receptionist"*, the AI stops responding and places the chat in human handoff state.
- Receptionists can review the patient's enquiry and toggle the conversation back to automated assistant mode when finished.

---

## 20. Application Developer Attribution
- **Application Developer**: Kundan Kumar
- **Development Company**: Nexora Solutions, Varanasi, Uttar Pradesh, India
- **Contact**: +91 98765 43210 | contact@nexorasolutionsind.co.in | [https://nexorasolutionsind.co.in/](https://nexorasolutionsind.co.in/)

---
*DxClinic Platform Frontdesk & Operator Manual &bull; Master Contract Implementation Verified &bull; Copyright &copy; 2026 DxClinic*


