# DxClinic Attending Physician & Doctor Operations Manual
**DxClinic Clinical Management System — Revision 35**  
*Manual Type: Full Doctor Operation Manual | Version: Revision 35 | Last Updated: September 2026 | Developer: Kundan Kumar | Copyright © 2026 DxClinic*

**Application Developer:** Kundan Kumar | **Development Company:** Nexora Solutions, Varanasi, Uttar Pradesh, India  
**Website:** https://nexorasolutionsind.co.in/ | **Contact:** +91 98765 43210 | **Email:** contact@nexorasolutionsind.co.in  

---

## 1. System Access & Doctor Portal
1. Open web browser (Google Chrome or modern Chromium browser).
2. Navigate to: `http://127.0.0.1:8000/login` (Local) | `https://dxclinic.example.local/login` (Remote HTTPS).
3. Enter your authorized Doctor email and password. Click **Sign In**.
4. The doctor interface provides access to patient clinical queues, encounter history, vitals, and doctor pad reprinting.

---

## 2. Reviewing Historical Clinical Encounters
1. Navigate to the consultation encounters view or historical queue.
2. Search patients by Name, Mobile Number, or Patient ID (`DXP-...`).
3. Review chronological past visits, previous diagnoses, recorded vitals, and prescribed medications.
4. Billing context is viewable in read-only mode to understand whether an encounter was Physical, Online, Waived (Revisit), or Paid.

---

## 3. Doctor Pad Viewing & Idempotent Reprinting (Revision 37 Enhanced)
1. Open any active or historical patient encounter.
2. Click **[Doctor Pad]** or **[View PDF]**.
3. **Pre-Printed Stationery Overlay Calibration**:
   - The printed output is rendered with millimeter coordinate fidelity and exact typographical points (`1 pt = 1/72 in`) as configured in the visual designer.
   - Outputs: Patient Demographics, Date, Token, Selected Vitals, and Doctor Custom Static Texts (e.g. consultation hours, emergency instructions) with independent element typography.
   - Suppresses: Letterhead background scans, clinic logos, headers, QR codes, financial details, and unconfigured fields.
4. **Reprint Safety Invariant**:
   - Reprinting a past Doctor Pad is strictly **idempotent**.
   - It creates **zero payment records**, **zero accounting journals**, **zero refunds**, **zero tokens**, and **zero duplicate visits**.
5. **Historical Template Immutability**: Historical visits permanently reproduce the exact template version assigned at the encounter time. Current changes in the designer never alter past patient prescription records.

---

## 4. Clinical Data Correction Rules
1. If vital signs or clinical notes require correction post-consultation:
   - Corrections must be entered through the audited clinical correction workflow.
   - The system preserves original values and records timestamp, reason, and physician attribution in `audit_logs`.
   - Never attempt to delete historical encounters.

---

## 5. Doctor Recommendation for Discount or Refund
Physicians frequently encounter situations where a patient should receive a courtesy discount or refund (e.g., poor financial situation, simplified second opinion, or test package adjustment).

### 5.1 The Doctor Recommendation Principle
- **Doctors Recommend; Billing Staff Settles**: Doctors have full medical authority to recommend financial adjustments, but **MUST NOT directly manipulate accounting ledgers, cash sessions, or bank accounts**.
- All financial settlement remains with authorized desk operators and administrators.

### 5.2 Submitting a Recommendation
1. Within the patient encounter, locate the **Doctor Recommendation** section.
2. Select Recommendation Type:
   - `DISCOUNT_AFTER_SETTLEMENT`: Partial discount on fee already paid.
   - `FULL_REFUND`: Complete fee waiver/refund.
   - `PACKAGE_ADJUSTMENT`: Diagnostic investigation adjustment.
3. Enter Recommended Amount (e.g., `₹100.00`).
4. Enter Clinical / Operational Reason (e.g., *"Patient requested simplified consultation; recommended ₹100 discount."*).
5. Submit recommendation.

### 5.3 What Happens Next
- The recommendation is immediately attached to the bill record with your doctor ID and timestamp.
- The desk operator or administrator inspects the recommendation in their billing history.
- Authorized billing staff executes the actual refund via Cash or UPI and balances the accounting journal.

---

## 6. Doctor Role Boundary Matrix
| Action / Capability | Doctor Allowed? | Rationale / Workflow |
| :--- | :---: | :--- |
| View patient clinical history & vitals | **YES** | Core clinical duty |
| View historical consultation encounters | **YES** | Essential for longitudinal patient care |
| Reprint historical Doctor Pads | **YES** | Idempotent PDF reprint without financial side effects |
| Submit discount / refund recommendation | **YES** | Authoritative medical recommendation |
| Directly modify settled bill financial values | **NO** | Settled bills are immutable; avoids financial fraud |
| Directly settle cash or UPI refunds | **NO** | Financial movement strictly managed by cash counter |
| Post double-entry accounting journals | **NO** | Restricted to authorized accounting subsystem |
| Delete past patient or encounter records | **NO** | Complete preservation of clinical and audit history |

---

## 7. Identifying Amended & Refunded Records
- In the patient encounter history, billing records display clear badges:
  - `PAID`: Original payment settled, no adjustments.
  - `PARTIALLY REFUNDED`: Doctor-recommended discount processed; shows original paid fee, refunded amount, and net retained.
  - `REFUNDED`: Full consultation fee returned to patient.
  - `WAIVED`: 5-day free OPD revisit applied automatically.

---

## 8. Doctor Pad Custom Stationery & Reprints (Revision 27)

### 8.1 Tailored Prescription Stationery
DxClinic supports personalized prescription pad layouts per doctor:
- **Configured Fields**: Each doctor can have different printed fields (e.g. some doctors print Height/Weight/BMI, while others print BP, Pulse, SpO2, and RBS).
- **Physical Millimeter Precision**: Placed elements print exactly where configured for your clinic's stationery.
- **Active Template**: When you or the front desk print your Doctor Pad, the system uses your currently active custom template. If no custom template has been activated, it seamlessly uses the verified legacy layout.

### 8.2 Vital Rendering Guarantee
- **Clinical Authenticity**: If vitals were recorded during nurse triage or intake, they render accurately on your pad.
- **No False Data**: If a vital (such as Weight or RBS) was not measured, it is left blank. DxClinic never outputs fake defaults.

### 8.3 Historical Template Immutability (Revision 28)
- **Exact Historical Appearance**: When an old consultation is reprinted or reviewed, DxClinic guarantees that the prescription pad appears exactly as it was originally printed on that visit date.
- **Future Changes Protected**: Updating or activating a new prescription pad template design today will never alter or distort the layout of past historical patient prescriptions. Archived older templates remain permanently reproducible.

### 8.4 Physical Stationery Printing & Alignment (Revision 30)
- **Stationery Overlay Mode:** When your prescription pad is printed, DxClinic outputs only the patient's demographics, vitals, and date directly onto your clinic's pre-printed letterhead. The pre-printed header, footer, logo, and borders are omitted to avoid double-printing.
- **Actual Size (100%) Requirement:** Ensure your desk printer or assistant prints at **Scale: 100% (Actual Size)** with margins set to **None**. Never use "Fit to Page", which shrinks text out of alignment with your stationery's printed rule lines.
- **Requesting Layout / Offset Adjustments:** If you change physical stationery batches or notice text shifting, submit an adjustment request to the Clinic Administrator. The Administrator will adjust the **Printer Profile** without modifying your template layout or past patient records.

### 8.5 Multilingual (Devanagari / Hindi) Clinical Records (Revision 30)
- **High-Fidelity Unicode Rendering:** Patient names, addresses, or clinical remarks recorded in Hindi (e.g., `राम कुमार`, `वाराणसी, उत्तर प्रदेश`) are fully supported using embedded TrueType fonts (`Noto Sans Devanagari`).
- **Zero Character Corruption:** Vowels, matras, and conjunct consonants render accurately with zero question marks (`?`) or missing glyphs, while preserving Latin medical abbreviations (`BP`, `Pulse`, `SpO2`) and numeric clinical readings.

---

## 9. Doctor Navigation & Role Boundaries
- **Daily Work Sidebar**: Doctors have a focused, distraction-free sidebar displaying **Dashboard**, **OPD Queue**, **Patients**, and **Consultation Billing**.
- **Help & Guide**: Direct access to clinical FAQ and manuals near the bottom.
- **Role & Authorization Boundaries:**
  - **Permitted**: View OPD queue, record clinical observations, view/reprint Doctor Pads, review diagnostic results, verify/release diagnostic reports, submit discount recommendations.
  - **Restricted**: Doctors **cannot** modify hardware printer calibration profiles, activate/archive prescription pad templates, execute financial refunds, modify bill items, or post accounting journals. These operations are restricted to Administrators and Cash Desk operators.

---

## 10. Multi-Tenant Practice & Doctor Seat Entitlement (Revision 34 Hardening)

### 10.1 Doctor Sign-In & Practice Isolation
- Doctors log in using **Clinic ID** (e.g., `NX0001`), **User ID** (e.g., `DOC0001`), and **Password**.
- The OPD Queue, Patient History, and Consultation Desk strictly reflect your clinic's patient roster.
- Cross-clinic access is physically prohibited at the platform level.

### 10.2 Doctor Seat Licensing Model
- Each clinic facility operates with a licensed doctor seat capacity.
- The base clinic subscription includes **1 Doctor Seat**. Additional doctor seats can be added by the Clinic Administrator through the application provider.
- Deactivating a doctor frees up licensed capacity for another doctor without affecting past patient records.

### 10.3 Direct Patient Booking
- Patients can book appointments with you through your clinic's dedicated public link:
  $$\text{https://yourdomain.com/b/} \mathbf{NX0001}$$
- Patients selecting your name will have their tokens automatically queued for your physical OPD or visiting session.

---

## 11. Doctor Name Presentation & Consultancy Modes (Revision 38)

### 11.1 Standardized Doctor Name Presentation
- Doctor profiles store clean canonical names in the system (`Rajesh Sharma`, `Amit Verma`).
- The system automatically applies the professional title via `DoctorNamePresenter::format()` across all clinical pads, patient receipts, reports, and public booking screens, rendering cleanly as `Dr. Firstname Lastname` without duplicate prefixes (no "Dr. Dr.").

### 11.2 Standardized Consultancy Terminology
- Clinical appointments are categorized as:
  - `Clinic Consultation` (In-person outpatient consultation)
  - `Online Consultancy` (Virtual telemedicine consultation)
  - `Visiting Consultancy` (Home / field consultation)

---

## 12. Doctor-Specific Booking Types & Tariff Mapping (Revision 45)

### 12.1 Independent Doctor Availability Matrix
- Your clinical practice has its own distinct booking type configuration.
- The administrator maps specific booking types (e.g. *Clinic Consultation*, *Online Consultancy*, *Emergency Consultancy*, *Home Healthcare Visit*) specifically to your doctor profile.
- Another doctor in the same clinic (or another clinic) maintains a completely separate booking type matrix.
- When an operator or patient selects your name, only the services explicitly enabled for you appear on the booking screen.

### 12.2 Doctor-Specific Tariffs & Anti-Fallback Protection
- Your fees are configured independently in Fee Master for each enabled booking type (e.g., Dr. Rajesh Sharma: Clinic = ₹600, Online = ₹700, Emergency = ₹1500).
- The system enforces a strict **Anti-Fallback Rule**: If an administrator enables a booking type for your profile but does not set an active fee, that booking type is automatically excluded from booking. The system never silently borrows another doctor's rate or uses a generic fallback price.
- Explicit ₹0.00 fees are supported where intended. Missing fee != zero fee.
- When fees are updated, all past consultation bills and appointment records retain their original historical financial snapshots permanently.

---

## 13. Diagnostic Laboratory Clinical Review, Verification & Release Governance (Revision 36)

### 13.1 Accessing the Laboratory Workspace (`/lab`)
Attending physicians and consulting pathologists access the laboratory subsystem via the **Diagnostic Lab** sidebar item or keyboard shortcut `Alt + L`. The workspace presents three specialized views:
- **Investigation Worklist**: Active orders tracking specimen status and draft results.
- **Sample Tracking**: Real-time biological specimen directory with collection timestamps and container types.
- **Released Reports**: Archive of authorized clinical reports ready for review, reprint, or patient consultation.

### 13.2 Result Evaluation & Abnormal Flags
1. From the worklist, click on any order with status `RESULT_ENTERED`.
2. Inspect the structured result entries:
   - **Numeric Parameters**: Verified against defined biological reference ranges (e.g., Hemoglobin `14.2` g/dL, Reference: `13.0 - 17.0`).
   - **Abnormal Flags**: Parameters outside standard physiological intervals are highlighted with prominent abnormal badges (`[ABNORMAL]`).
   - **Qualitative & Morphological Findings**: Text observations, peripheral smear narratives, and urine microscopy.

### 13.3 Clinician Verification Protocol
- Only Doctors and Admins possess the clinical authority to certify diagnostic investigations.
- After reviewing values, click **[Verify]**.
- The order and associated results transition from `RESULT_ENTERED` to `VERIFIED`. An audit entry is registered recording the verifying physician's ID and timestamp.

### 13.4 Authorizing & Releasing Final Diagnostic Reports
1. For verified orders, click **[Release Report]**.
2. An authorization dialog allows recording clinical impressions, interpretive comments, or follow-up recommendations (e.g., *"Clinical correlation advised. Repeat in 2 weeks."*).
3. Click **[Confirm & Release Report]**.
4. The system:
   - Transitions the order to `RELEASED`.
   - Generates the immutable final report record (`REP-D-YYYYMMDD-XXXX`).
   - Activates the public verification QR endpoint.
   - Makes the PDF available for instant viewing and printing.

### 13.5 Controlled Result Correction Post-Release
Once a report is released, results cannot be silently modified or deleted:
1. If an amendment is required, the doctor opens the **[Controlled Correction]** modal.
2. A detailed clinical rationale must be entered (minimum 5 characters).
3. The previous result is archived as `CORRECTED`.
4. A new versioned result is saved and linked to the correcting clinician.
5. An immutable audit record records previous value, updated value, reason, and user ID.

### 13.6 Separation of Clinical Authority from Financial Settlement
- Doctors hold complete clinical authority over result evaluation, verification, and report release.
- However, doctors **CANNOT**:
  - Alter diagnostic bills or tariff rates.
  - Process cash or UPI payments.
  - Modify cash sessions or bank records.
  - Post accounting journal adjustments.
- Financial settlement remains the exclusive responsibility of reception operators and financial administrators.

### 13.7 Clinical & Financial Purity
- Clinical diagnostic evaluation and prescription management remain completely uncoupled from financial accounting.

---

## 14. Doctor Live Queue & WhatsApp Booking Visibility

### 14.1 Doctor-Scoped Live Queue Display
- When logged in as a Doctor, clicking **[Open Live Queue]** opens a dedicated, real-time consultation monitor (`/queue/live`).
- **Strict Role Isolation**: By default, the Doctor Live Queue filters and displays **only appointments assigned to you**. You do not see other doctors' queues unless explicitly granted multi-doctor visibility.
- **Optimized for Doctor's Desk**: Dark-themed, high-contrast, designed to remain open on a secondary screen or tablet alongside Doctor Pad.

### 14.2 Real-Time Booking & Arrival Visibility
- As patients arrive at reception or book appointments, your screen updates instantly without refreshing.
- Clearly displays:
  - **Token Number**: Formatted token (e.g. `001`, `002`).
  - **Patient Details**: Full patient name and permanent Patient ID (e.g. `DXP-00146`).
  - **Booking Source**: Distinct visual indicator showing where the patient booked (`CLINIC`, `ONLINE`, or `WHATSAPP`).
  - **Status & Arrival**: `BOOKED` (scheduled), `ARRIVED` (patient checked in at reception), `IN_PROGRESS` (in consultation), or `COMPLETED`.

### 14.3 WhatsApp Bookings Visibility
- Patients booking via the clinic's official WhatsApp automated assistant appear directly in your queue with the `WHATSAPP` source badge.
- Follows the exact same authoritative daily token series as clinic walk-ins.
- Zero financial or billing fields are displayed on the Live Queue, keeping the consultation flow purely clinical.

---

## 15. Developer Attribution & System Support
- **Application Developer**: Kundan Kumar
- **Development Company**: Nexora Solutions, Varanasi, Uttar Pradesh, India
- **Contact**: +91 98765 43210 | contact@nexorasolutionsind.co.in | [https://nexorasolutionsind.co.in/](https://nexorasolutionsind.co.in/)

---
*DxClinic Platform Doctor Manual &bull; Master Contract Implementation Verified &bull; Copyright &copy; 2026 DxClinic*


