# DxClinic Administrator Complete Setup & Controls Manual
**DxClinic Clinical Management System — Revision 35**  
*Manual Type: Full Admin Manual | Version: Revision 35 | Last Updated: September 2026 | Developer: Kundan Kumar | Copyright © 2026 DxClinic*

**Application Developer:** Kundan Kumar | **Development Company:** Nexora Solutions, Varanasi, Uttar Pradesh, India  
**Website:** https://nexorasolutionsind.co.in/ | **Contact:** +91 98765 43210 | **Email:** contact@nexorasolutionsind.co.in  

---

## 1. System Architecture & Administrative Scope
DxClinic Revision 35 provides full enterprise clinical control, multi-doctor scheduling, double-entry financial accounting, booking channel separation, historical billing management, and controlled financial modifications with immutable audit trails.

Access URL: `http://127.0.0.1:8000/login` (Local) | `https://dxclinic.example.local/login` (Remote HTTPS)  
Default Admin: Full administrative privileges over clinical, financial, and system settings.

---

## 2. Settings Subsystem (Strict Order)
All administrative settings follow the standard 11-step configuration order:

### 2.1 Clinic & Doctor Management (`/settings/clinic-doctor`)
1. **Clinic Profile**: Define clinic legal entity name, address, city, phone, and official email.
2. **Doctor Registry & Capacity Entitlement (Revision 37)**:
   - Register attending doctors with Name, Medical Qualifications, Registration Number, Specialization, and Contact Number.
   - Doctor capacity is governed by the clinic's entitlement: Base Included Seat (1) plus any active Doctor Seat Add-Ons.
   - If capacity is reached, creating an additional doctor profile is blocked until an additional doctor seat entitlement is purchased via Superadmin (₹2,999/year per doctor).
   - Doctor names follow strict person name validation (Unicode alphabetic characters and single spaces). Professional degrees and registration numbers are stored separately.
3. **Default Booking Doctor**:
   - When multiple active doctors exist, designate the default doctor preselected across Operator and Public booking desks.
   - If only one doctor is active, the system automatically selects that doctor without prompting.

### 2.2 Onboarding Wizard (`/settings/onboarding`)
- Step-by-step initial deployment wizard configuring clinic profile, initial doctor, opening cash, opening bank balances, and operator accounts.

### 2.3 Users & Operator Credentials (`/settings/users`)
- Role-Based Access Control (RBAC):
  - `ADMIN`: Full master authority over financial refunds, adjustments, audit trails, and user accounts.
  - `OPERATOR`: Desk registration, queue, vitals, billing, cash sessions, refund requests, and daily reports.
  - `DOCTOR`: Clinical consultation, historical encounter review, doctor pad reprinting, and discount/refund recommendations.
- Secure password hashing with PBKDF2/Bcrypt and audit logging.

### 2.4 Expense Categories (`/settings/expense-categories`)
- Standardized operational expense codes (Rent, Electricity, Tea/Coffee `EXP-TEA`, Printing/Stationery, Staff, Medical Supplies).

### 2.5 Fee Master (`/settings/fee-master`) (Revision 45 Hardened)
- Authoritative doctor + booking type pricing engine:
  - Select Attending Doctor to load only the booking types explicitly mapped to that physician.
  - Configure individual tariff rates for each mapped type (e.g. Dr. Rajesh Sharma: Clinic = ₹600.00, Online = ₹700.00, Emergency = ₹1,500.00; Dr. Amit Verma: Clinic = ₹800.00, Emergency = ₹2,000.00).
  - **Anti-Fallback Rule**: If a booking type is mapped but lacks a configured active fee, it is strictly excluded from booking. A doctor cannot borrow another physician's fee or silently fallback to generic rates. Missing fee != zero fee (explicit ₹0.00 is supported).
  - Effective dating (`effective_from`, `effective_to`) ensures new rates take effect for future bookings while all past appointment financial snapshots remain immutable.

### 2.5b Extensible Booking Types & Doctor Availability Matrix (`/settings/booking-types`) (Revision 44/45)
- **Clinic-Owned Booking Types**: Create custom clinic consultancy offerings (e.g., *Home Healthcare Visit*, *Pediatric Consultation*, *Diet Counseling*) alongside platform system types (*Clinic Consultancy*, *Online Consultancy*, *Emergency Consultancy*).
- **Per-Doctor Availability Matrix**: Enable or disable each booking type on a doctor-by-doctor basis. Doctor A and Doctor B have completely independent availability matrices.
- **Tenant Isolation**: Booking types belong strictly to `booking_types.clinic_id`. Clinic A's types never appear for Clinic B.
- **Separation of Four Concepts**:
  1. *Booking Source*: Where the booking originated (`CLINIC`, `ONLINE`, `TELEPHONIC`).
  2. *Consultation Mode*: How care is delivered (`CLINIC_PHYSICAL`, `ONLINE`, `VISITING_CONSULTANCY`).
  3. *Booking Type*: Specific clinical service package booked (`CLINIC_CONSULTANCY`, `ONLINE_CONSULTANCY`, `EMERGENCY_CONSULTANCY`, or Custom).
  4. *Fee Mapping*: Exact tariff mapped to `(clinic_id, doctor_id, booking_type_id)`.

### 2.6 Vital Master (`/settings/vital-master`)
- Configure vital observation ranges: Weight (kg), Systolic/Diastolic BP (mmHg), Pulse (bpm), SpO2 (%), Temperature (°F).

### 2.7 Bank Accounts (`/settings/bank-accounts`)
- Configure clinic bank accounts: Bank Name, Account Holder, Account Number, IFSC (standard 4-alpha + 0 + 6-alphanumeric), Branch, and UPI VPA ID.

### 2.8 Print & Branding (`/settings/branding`)
- Upload clinic logo (validated PNG/JPEG image signature, max 2MB).
- Print policies:
  - Financial Receipts are strictly **A5** (`148mm x 210mm`).
  - Doctor prescription pad overlay strictly suppresses clinic logo when printed on pre-printed doctor letterheads.

### 2.9 Online Booking & Counter QR (`/settings/booking-qr`) (Revision 47 Hardened)
- Canonical Per-Clinic Public PWA URL: `https://dxclinic.example.local/book/{public_slug}` (e.g. `/book/sharmacare`, `/book/arogya`, or `/book/democlinic`).
- Short Alias: `https://dxclinic.example.local/b/{public_slug}` (redirects automatically via HTTP 302 to canonical `/book/{public_slug}`).
- Dedicated isolated public booking layout with strict server-side tenant isolation: internal database IDs and clinic codes (e.g. `/book/NX0001`, `/book/1`) and unselected `/book` are strictly forbidden and return HTTP 404.
- Centralized `PublicSlugService` ensures unique, human-friendly public locators without exposing internal clinic codes.
- Download counter QR standee posters (PNG, SVG, or printable A4 PDF) embedding the clinic's dedicated `public_slug` booking URL.
- Operator Arrival Queue is accessed via tenant-scoped `/clinic/{public_slug}/operator/queue` with strict authentication and cross-tenant barrier enforcement.

### 2.10 Backup & Disaster Recovery (`/settings/backup`)
- Automated daily backup triggered after evening closing.
- Encrypted with AES-256-GCM + PBKDF2 key derivation into `.ncbk` format.
- Restore protocol: Pre-restore snapshot, maintenance mode, checksum validation, and schema integrity rollback.

### 2.11 Help Center & Manuals (`/settings/help`)
- Full documentation suite, 1-page quick cards, PDF downloads, and RAG knowledge base.

### 2.12 Prescription Pad Designer & Visual Layout Editor (`/settings/prescription-pad-templates`) (Revision 38)
- **Professional Desktop Multi-Selection & Marquee Tool**:
  - `Ctrl/Cmd + Click` and `Shift + Click` toggle individual elements into or out of active selection sets.
  - Marquee (Lasso) Selection: Dragging across empty canvas draws a rectangular selection marquee, selecting all contained or intersecting objects.
  - Dedicated Multi-Selection Properties Panel displays item count and enables batch alignment, distribution, duplication, deletion, and locking without displaying misleading single-item values.
  - Multi-drag moves all selected objects concurrently at 60fps with zero intermediate network requests.
- **Precision Alignment & Distribution Engine**:
  - Single Element: Aligns relative to physical page boundaries (Left, Center, Right, Top, Middle, Bottom).
  - Multiple Elements: Aligns relative to the shared bounding box enclosing all selected elements.
  - Distribution (3+ Elements): Mathematically spaces items with equal millimeter gaps horizontally or vertically.
- **Smart Alignment Guides & Snapping**:
  - Ephemeral pink dashed guide lines dynamically appear as objects approach page centers, borders, or neighboring element centers/edges.
  - Smart Snap gently locks positions to guides within physical millimeter tolerances without altering saved document models.
- **Z-Order Layer Management Panel**:
  - Dedicated Layers tab in the left sidebar lists all canvas elements in their exact rendering stack order.
  - Controls: Bring to Front, Bring Forward, Send Backward, and Send to Back.
  - Layer sequence is persistently stored in `sort_order` and matches DomPDF print output identically.
- **Element Locking Safeguard**:
  - Elements can be locked to prevent accidental dragging, resizing, or nudging.
  - Locked elements remain printable: locking is strictly an editor interaction safeguard and does not alter Proof or Production PDFs.
- **Atomic Undo / Redo Transaction Model**:
  - Full document history stack accessible via toolbar buttons (`↶ Undo`, `↷ Redo`) and standard shortcuts (`Ctrl+Z`, `Ctrl+Shift+Z` / `Ctrl+Y`).
  - Discrete actions (moves, resizes, duplicates, deletions, alignments, typography, rotation) create single atomic transactions.
  - Viewport zoom and pan operations are strictly isolated and do not pollute document undo history.
- **Free Element Rotation (0°–360°)**:
  - Supports continuous rotation around the physical element center anchor (`transform-origin: center center;`).

### 2.13 WhatsApp Integration, Diagnostics & Automation Controls (`/settings/whatsapp`) (Production Hardened)
- **Meta WhatsApp Cloud API Connection**:
  - Configure Phone Number ID, WhatsApp Business Account ID (WABA ID), and Display Phone Number.
  - **Encrypted Credential Storage**: Permanent System User Access Tokens are encrypted with AES-256-CBC prior to persistence. Secrets are never exposed in logs, serialized models, or Livewire payloads.
  - **Shoulder-Surfing Protection**: Business Account and Phone IDs are securely masked in the staff UI (`+91 ••• 7600`, `1069••••1098`).
- **Connection Health & Diagnostics Telemetry (Phase R)**:
  - **Connection Status**: Real-time indicator (`ACTIVE`, `DISABLED / DISCONNECTED`, `ERROR`, `PENDING CONFIGURATION`).
  - **Webhook Status**: Handshake state and verification timestamp (`VERIFIED` vs. `NOT VERIFIED`).
  - **Event Telemetry**: Live relative timestamps for Last Inbound Message, Last Outbound Send, and Last Webhook Event.
  - **Queue Worker Reliability**: Live queue status and error logs for the latest delivery failures.
- **Unified Daily Token Sequence Rules**:
  - All four booking channels (`CLINIC`, `ONLINE`, `WHATSAPP`, `TELEPHONIC`) consume the exact same date-and-session scoped sequence.
  - Token numbers are **unique, monotonic, and never reused**.
  - If an appointment is cancelled or marked absent, the token number is retired forever. Token sequences are **NOT guaranteed gapless**.
  - Token numbers never contain channel-specific prefixes (e.g. valid: `007`; invalid: `WA-007`).
- **Assistant & Channel Controls (Section 31)**:
  - Granular toggles for AI Natural Language Assistant, WhatsApp Appointment Booking, Human Handoff Support, Timings, and Consultation Fees.
  - Session and advance booking constraint enforcement (Today, Tomorrow, Morning, Evening, Sunday).
- **Safe Disconnect / Reconnect Protocol**:
  - Clicking **Disconnect** safely halts future webhook processing.
  - **Zero Data Loss Invariant**: Disconnection never deletes or alters historical appointments, tokens, patient records, visits, or financial receipts.
  - Stored in document state in degrees and reproduced with physical coordinate fidelity in DomPDF.
- **Professional Desktop Interaction Model**:
  - Selection Tool (`V`): Select and manipulate individual design elements with real-time feedback.
  - Hand / Pan Tool (`H`): Real panning tool to navigate horizontally and vertically across the canvas at any zoom level.
  - Spacebar Temporary Pan Shortcut: Hold `Space` and drag with the pointer to pan the viewport without changing the active tool. Panning changes only the viewport transform and never shifts physical document coordinates.
- **Cursor-Centered Mouse-Wheel Zoom Architecture**:
  - Rolling the mouse wheel over the editor workspace dynamically zooms in (Wheel UP) or out (Wheel DOWN) clamped between 50% and 200%.
  - Zoom centers around the exact pointer location in screen coordinates ($xm, ym$): $panX_{new} = \text{round}(xm - (xm - panX) \times (newZoom / oldZoom))$, preserving the exact document point under the cursor with sub-millimeter precision.
  - Wheel zooming updates local CSS viewport transforms without shifting document physical millimeter coordinates and generates zero Livewire network requests during wheel events.
  - Normal browser scrolling remains uninhibited outside the editor canvas.
- **60fps Real-Time Field Dragging & 8-Handle Resizing**:
  - Smooth DOM updates during pointer movement without server round-trip latency.
  - 8-handle system (NW, N, NE, E, SE, S, SW, W) with proper geometric anchors and cursor feedback.
  - Keyboard movement: Arrow keys move by `0.5 mm`; Shift + Arrow keys move by `0.1 mm`. Snap-to-grid toggleable with `5.0 mm` grid lines.
- **Native Image / Logo Elements & Upload Security**:
  - Direct Image Tool (`[Image]` in toolbox) opens file browser supporting PNG and JPG/JPEG files (up to 5MB) for clinic logos, doctor emblems, and digital signatures.
  - Upload security: Strict server-side MIME type verification, `getimagesize()` binary analysis, dimension validation, and safe randomized filenames (`img_elem_{templateId}_{uniqid}_{time}.{ext}`) stored under clinic-scoped storage paths. Executable files and directory traversal attempts are strictly rejected.
  - Unique persistent element identity (`image_{num}_{hash}`) allows multiple independent images to coexist without overwriting.
  - Real-time 60fps dragging and 8-handle resizing with **Lock Aspect Ratio** enabled by default (`lock_aspect_ratio: true`), preventing visual distortion.
  - Properties panel supports Opacity adjustment (0% to 100%), dimension fine-tuning, and aspect ratio lock toggling.
- **Proof vs. Production Modes & PDF Separation**:
  - Proof Mode: Overlays high-resolution scanned stationery letterhead for visual field alignment; user-inserted `IMAGE` elements render clearly over or alongside the background.
  - Production Mode: Suppresses letterhead scan for printing on pre-printed paper; user-inserted `IMAGE` elements continue to render at exact physical millimeter coordinates.
  - Inserted images are converted to base64 Data URIs in DomPDF, completely decoupled from background scan layers.
  - Hardware printer X/Y offsets and scaling are stored independently per clinic.
- **Strict Individual Typography Isolation**:
  - Every field maintains isolated typography attributes (`font_size`, `font_family`, `font_weight`, `font_style`, `text_align`, `color`, `line_height`).
  - Changing typography on one element strictly affects only the selected item without leaking to other fields.
  - Font sizes are stored and rendered as true typographical points (`1 pt = 1/72 inch`) ensuring 100% fidelity in DomPDF output.
- **Multiple Static Text Elements Coexistence**:
  - Multiple Static Text items (e.g. consultation hours, emergency phone numbers, registration notices) coexist with unique persistent identities.
  - Creating, editing, or reordering Static Text elements never overwrites or reuses prior static text.
- **Historical Template Immutability**:
  - Activating a template version archives the previous active version. Historical clinical consultations retain their permanently committed template version. Referenced templates cannot be deleted (`RESTRICT`).
  - *Note: Mobile OTP verification is deferred to a future phase. OTP remains completely OUT OF SCOPE.*

---

## 3. Historical Billing Architecture & Immutability Rules
*Core Principle: Settled financial facts are strictly immutable.*

1. **Immutable Entities**:
   - Original `payments` records (amounts, methods, dates).
   - Original `receipts` records (receipt numbers, settled totals).
   - Original `journals` and `journal_entries` (settled debit/credit lines).
   - Settled `consultation_bills` and `diagnostic_bills` gross, net, and paid amounts.
2. **Representation of Financial Changes**:
   - **Refunds**: Created in the dedicated `refunds` table with lifecycle status (`REQUESTED` -> `APPROVED` -> `PROCESSED` or `VOIDED`).
   - **Receipt Revisions**: Stored in `receipt_revisions` capturing old values, new values, financial impact, and reason.
   - **Supplementary Bills**: Created for diagnostic additions as separate linked bills (`{bill_number}-SUPP-{n}`) rather than overwriting historical bills.
   - **Audit Logs**: Created in `audit_logs` capturing reason (min 10 chars), change category, financial impact, and linked refund ID.

---

## 4. Refund Processing & Double-Entry Accounting

### 4.1 Refund Calculation
Refundable amount is derived dynamically from:
$$\text{Remaining Refundable Amount} = \text{Original Settled Payment} - \sum \text{PROCESSED Refunds}$$
*Note: No mutable `refunded_amount` column exists on `payments`.*

### 4.2 Refund Settlement Rules
- **Cash Refund**:
  - `refund_method = 'CASH'`
  - Requires active `cash_session_id`.
  - Accounting Entry:
    - **Dr Consultation Revenue (4001) / Diagnostic Revenue (4002)**
    - **Cr Cash on Hand (1001)**
- **UPI Refund**:
  - `refund_method = 'UPI'`
  - Requires clinic `bank_account_id` and bank transaction `reference_number`.
  - Accounting Entry:
    - **Dr Consultation Revenue (4001) / Diagnostic Revenue (4002)**
    - **Cr Bank Ledger (1002)**
- **Actual Settlement Method**: The refund method is the actual method used to return funds. If an original payment was made via UPI but cash is refunded, Cash on Hand is credited.

### 4.3 Full vs Partial Refunds
- **Partial Refund**: Bill status remains `PAID`. Retained revenue is displayed as `Original Paid - Refunds`.
- **Full Refund**: Bill status transitions to `REFUNDED` only when 100% of the settled amount has been processed as a refund.

---

## 5. Controlled Bill Modification & Supplementary Billing

### 5.1 Pending (Unsettled) Bills
- If a bill is still `PENDING`, items or discounts may be adjusted.
- Requires server-side reason validation ($\ge 10$ characters, non-generic) and an audit log entry.
- No accounting entries are generated until actual settlement.

### 5.2 Diagnostic Bill Reductions & Additions
- **Reduction**: Removing a test or reducing quantity triggers a controlled refund of the difference. The original bill remains intact.
- **Addition**: Adding an additional test to a settled diagnostic bill creates a new **Supplementary Diagnostic Bill** linked to the original bill. The supplementary bill has its own payment workflow, receipt, and journal.

### 5.3 Doctor Recommendations
- A doctor may recommend a discount or refund (stored with `doctor_instruction_by`, timestamp, and rationale).
- The recommendation is informational and does NOT settle money directly.
- Final financial settlement requires authorized processing by Admin or authorized desk staff.

---

## 6. Doctor Pad History & Reprint Idempotency
- Doctor Pad History displays all historical clinical encounters.
- Reprinting an old Doctor Pad generates:
  - **Zero payment records**
  - **Zero journal entries**
  - **Zero refunds**
  - **Zero appointment/token increments**
  - **Zero duplicate visits**
- Pre-printed stationery overlay calibration is strictly respected.

---

## 7. Financial Reconciliation Formulas

### 7.1 Physical Cash Reconciliation
$$\text{Expected Closing Cash} = \text{Opening Cash} + \text{Cash Collections} - \text{Cash Expenses} - \text{Cash Deposits} - \text{Cash Refunds Paid in Cash}$$

### 7.2 Book Bank Reconciliation
$$\text{Book Bank Balance} = \text{Opening Bank} + \text{UPI Collections} + \text{Cash Deposits} - \text{Bank Expenses} - \text{Bank Refunds Paid from Bank}$$

### 7.3 General Ledger Equilibrium
$$\sum \text{Debits} == \sum \text{Credits} \quad (\text{Discrepancy} = ₹0.00)$$

---

## 8. Role Permissions & Security Matrix
| Permission / Capability | ADMIN | OPERATOR | DOCTOR |
| :--- | :---: | :---: | :---: |
| View Historical Consultation & Diagnostic Bills | Yes | Yes | Yes (Clinical context) |
| Reprint Receipts, Doctor Pads, Lab Slips | Yes | Yes | Doctor Pad only |
| Submit Refund / Modification Request | Yes | Yes | Recommendation only |
| Approve / Process Financial Refunds | Yes | Desk Cash Limit | No |
| Create Supplementary Diagnostic Bills | Yes | Yes | No |
| View Receipt Revision History & Audit Trails | Yes | Yes (Read-only) | No |
| Edit System Settings & User Roles | Yes | No | No |
| Overwrite Historical Settled Facts | **BLOCKED** | **BLOCKED** | **BLOCKED** |
| Manage Prescription Pad Templates & Calibration | Yes | No | No |
| Activate / Archive Prescription Templates | Yes | No | No |

---

## 9. Prescription Pad Template Designer (Revision 27)

### 9.1 Overview & Architecture
The Prescription Pad Template Designer allows administrators to design, calibrate, and version multi-doctor physical prescription stationery layouts.
- **Physical Millimeter Units**: Field coordinates (`x_mm`, `y_mm`, `width_mm`, `height_mm`) are persisted strictly in physical millimeters, ensuring 100% faithful print reproduction regardless of designer zoom or device DPI.
- **Doctor-Specific Profiles**: Different doctors can have different templates, different paper dimensions, and different selected clinical fields.
- **Scanned Reference Background**: Administrators can upload a high-resolution PNG/JPG scan of pre-printed stationery. It displays as an alignment reference during design and in **Proof Mode**, but is omitted during **Production Mode**.
- **Interactive SVG Vector Canvas**: Features real-time millimeter rulers, customizable 5mm/10mm grids, snap-to-grid alignment, keyboard nudge precision (Arrow = 0.5 mm, Shift + Arrow = 0.1 mm), and numeric X/Y property panels.
- **Safe Template Versioning**: Active templates are immutable. Editing an active template automatically creates a new `DRAFT` version (e.g. v2). Activating a new draft safely marks the previous active version as `ARCHIVED`.
- **Legacy Fallback Guarantee**: Doctors without an active template automatically continue using the legacy Doctor Pad renderer. The legacy configuration and templates are never overwritten or deleted.
- **Printer Calibration**: Global physical offsets (`offset_x_mm`, `offset_y_mm`) and scaling factors (`scale_x`, `scale_y`) can be configured per clinic hardware printer without altering individual template coordinates.

### 9.2 Step-by-Step Template Creation Workflow
1. Navigate to **Settings > Prescription Pad Designer** (`/settings/prescription-pad-templates`).
2. Click **[New Template]** to create a fresh draft, or **[Import From Legacy]** to copy legacy coordinates into a draft.
3. Choose the target doctor, select paper size (**A4**, **A5**, **Letter**, **Half Letter**, or **Custom mm**), and orientation.
4. Click **[Open Designer]** to access the SVG workspace.
5. In the **Pad Scan** card, upload the doctor's scanned pre-printed pad.
6. From the **Field Toolbox**, click fields to add them (e.g. Patient Name, Age/Sex, Blood Pressure, Pulse, Token, Date).
7. Drag fields on canvas or adjust **X (mm)** and **Y (mm)** in the right **Properties Panel**.
8. Click **[Test Print ▾] > Proof PDF** to inspect alignment with the scan.
9. Click **[Save Draft]** to persist elements.
10. Click **[Activate]** to make the template live for the doctor's OPD consultations.

---

## 10. Billing History & Controlled Workflow Updates

### 10.1 Consultation Billing History Edit Placement
In `/operator/consultation`, the **Consultation Billing History** table renders an **[Edit]** button immediately before the **[Pad]** reprint button on every eligible row.
- Clicking **[Edit]** opens the existing `BillModificationService` modal.
- Paid bills trigger the controlled refund/discount workflow with mandatory reason tracking.

### 10.2 Diagnostic Billing History Column Layout
In `/operator/diagnostics`, the **Diagnostic Billing History** table physically arranges columns in the exact sequence:
$$\text{Status} \quad|\quad \textbf{Edit} \quad|\quad \text{Method}$$
The **[Edit]** action button is positioned in its own dedicated column between Status and Payment Method for instant operational access.

## 11. Historical Prescription Template Immutability & Calibration

### 11.1 Immutable Template Version Binding
When a Doctor Pad is first generated or printed for a patient encounter, the system permanently records the exact active template version (`prescription_pad_template_id`) onto the appointment record.
- **Subsequent Reprints:** Any future reprint or PDF download of that historical encounter will retrieve and render the exact template version that was in effect at the time of print.
- **Future Template Activations:** Activating a new template version (e.g. V2 or V3) will ONLY apply to newly created clinical encounters. Past historical records are never retroactively rebound or altered.

### 11.2 Historical Printing of Archived Templates
When Template V1 is superseded by activating Template V2, V1 transitions to `ARCHIVED` status.
- Archived templates cannot be assigned to new appointments.
- Archived templates remain 100% active and printable in read-only mode for historical encounters that reference them.

### 11.3 Legacy Historical Record Fallback
All clinical appointments created prior to the introduction of custom templates (or with `is_legacy_pad = true`) automatically resolve to the legacy Doctor Pad renderer (`print.doctor-pad`). The legacy Blade view and its millimeter layout remain fully operational and preserved.

### 11.4 Hardware Deletion Protection
Database foreign key constraints (`RESTRICT`) prevent the deletion of any template version that is referenced by clinical appointments. If an administrator wishes to retire a template, they must use the **Archive** action.

### 11.5 Physical Printer Operational Calibration Protocol (Revision 30)
1. **Mathematical Verification vs. Physical Verification:**
   - Software verification calculates millimeters to PDF points ($1\text{ mm} = 72/25.4\text{ pt} \approx 2.83465\text{ pt}$) and verifies rendering correctness in software.
   - Physical printer verification accounts for mechanical tractor rollers, laser margin grip boundaries, and paper tray tolerances.
2. **On-Site Physical Sheet Calibration Protocol:**
   - Load pre-printed blank doctor letterhead stationery into the physical printer tray (e.g. Canon LBP2900 on USB001).
   - In the system or PDF viewer print dialog, select:
     - **Scale**: `100%` or `Actual Size` (CRITICAL: Never choose "Fit to Page", "Fit to Printable Area", or "Shrink oversized pages").
     - **Margins**: `None` or `Minimum`.
     - **Paper Size**: Match the exact template dimensions (e.g. A4: $210 \times 297\text{ mm}$ or A5: $148 \times 210\text{ mm}$).
   - Generate a **Production PDF** test print.
   - Take a physical millimeter ruler or digital caliper and measure target fields:
     - E.g. Patient Name: Expected $X = 35.20\text{ mm}$, Actual $X = 36.40\text{ mm} \implies \Delta X = -1.20\text{ mm}$.
     - E.g. Date: Expected $Y = 48.00\text{ mm}$, Actual $Y = 50.00\text{ mm} \implies \Delta Y = -2.00\text{ mm}$.
   - Open **Settings > Prescription Pad Templates > Printer Calibration** (`Printer Profiles`).
   - Enter compensation offsets: `Offset X = -1.20 mm`, `Offset Y = -2.00 mm`.
   - If dimension measurements span over $100\text{ mm}$ (e.g., $100\text{ mm}$ rule measures $101.2\text{ mm}$ on paper), apply **Scale Calibration**: $\text{Scale } X = 100.0 / 101.2 \approx 0.9881$.
   - Save the profile and print a second physical sheet. Re-measure to verify repeatability.

### 11.6 Printer Profile & Template Separation
- **Strict Domain Isolation:** Template element coordinates (`x_mm`, `y_mm`, `width_mm`, `height_mm`) in `prescription_pad_elements` are immutable physical layout properties of the doctor's stationery.
- **Hardware Profile Portability:** Calibration values (`offset_x_mm`, `offset_y_mm`, `scale_x`, `scale_y`) are stored in `printer_profiles` linked to the hardware device name (e.g. `Canon LBP2900`).
- Updating printer calibration **never** creates a new template version and never mutates template coordinates. The same doctor template can be printed across different clinic workstations or printers by attaching respective hardware profiles.

### 11.7 Proof PDF vs. Production PDF Workflows
- **Proof PDF:** Contains the scanned background pad image, test patient values, and a bold diagonal "PROOF MODE - NOT FOR CLINICAL USE" watermark. Used exclusively on plain paper during layout design to verify field alignment against the scanned stationery.
- **Production PDF:** Completely suppresses the scanned background and proof watermark. Outputs strictly dynamic text, vitals, demographics, and custom static labels with exact millimeter placement on physical pre-printed stationery.

### 11.8 Unicode & Devanagari (Hindi) Font Engine (Revision 30)
- **Problem Statement:** Standard DomPDF core Type 1 fonts (`Helvetica`, `Times-Roman`, `Courier`) only support Latin/WinAnsi encoding. Hindi, Bengali, and other Indic scripts produce question marks (`?`), empty boxes (`□`), or fatal font errors.
- **TrueType Font Integration:** DxClinic embeds the open-source, SIL Open Font Licensed **Noto Sans Devanagari** (`NotoSansDevanagari-Regular.ttf` and `NotoSansDevanagari-Bold.ttf`) registered directly in `storage/fonts/installed-fonts.json`.
- **Automatic Fallback Chain:** The print stylesheet declares:
  ```css
  font-family: 'Noto Sans Devanagari', 'DejaVu Sans', sans-serif;
  ```
- **Designer Font Selection:** Administrators can select **Noto Sans Devanagari (Hindi/Unicode)** from the typography dropdown in the SVG designer for any individual or multi-selected element.
- **Mixed-Script Support:** Seamlessly renders combined English and Hindi text (e.g. `Ram कुमार`, `वाराणसी, उत्तर प्रदेश`, `डॉ. सुशील दास`) while keeping numbers, punctuation, and clinical vitals (`134/90 mmHg`, `72 kg`) crisp and uncorrupted.
- **Font Subsetting:** Configured with `isFontSubsettingEnabled = true`, reducing PDF file size from ~880 KB to ~20-50 KB for instant desk printing.

### 11.9 Prescription Pad Troubleshooting Guide
- **Symptom: Printed text is shifted by 3-5 mm downwards or to the right.**
  - *Cause:* The PDF reader print dialog applied "Fit to Page" margins, or printer roller mechanical offset needs compensation.
  - *Fix:* Ensure print dialog is set to **Actual Size (100%)**. If still offset, adjust `Offset X` / `Offset Y` in **Printer Profiles** modal.
- **Symptom: Hindi patient names or addresses appear as question marks ('????').**
  - *Cause:* Legacy font or un-registered font selected.
  - *Fix:* In the Designer, select the text element and choose `Noto Sans Devanagari (Hindi/Unicode)`. DxClinic's renderer auto-registers the font on demand.
- **Symptom: Text is clipped on the right edge of a pre-printed box.**
  - *Cause:* Element `width_mm` in designer is narrower than the dynamic patient name or address string.
  - *Fix:* Open template in Designer, select the element, drag right boundary handle or increase `W (mm)` in properties panel, then click **Save Draft**.
- **Symptom: Reprints of old consultations look different after updating the template.**
  - *Answer:* DxClinic guarantees historical template immutability. Historical consultations permanently reference their original template version. If you updated a template today, it created a new version (e.g. v2) that only applies to new visits; past visits still reprint with v1.

---

## 12. Multi-Tenant Architecture, Licensing & Security (Revision 34 Hardening)

### 12.1 Triple-Identifier Tenant Security Model
DxClinic enforces strict tenant isolation using three decoupled, specialized credentials:
1. **Clinic ID (`clinic_code`):** The facility's unique tenant identifier (e.g., `NX0001`, `NX0002`).
2. **User Login ID (`login_id`):** The user's role-scoped identity within their clinic (e.g., `ADM0001`, `OPR0001`). User IDs are unique *per clinic*, allowing different clinics to have identical internal login IDs without collisions.
3. **Password:** Secret credential controlling authentication.

### 12.2 License Key vs Activation Code Separation
- **License Key (`NXCL-XXXX-XXXX-XXXX-XXXX`):** Cryptographically stored entitlement token representing the clinic's active subscription period, duration, and doctor capacity.
- **Activation Code (`NXA-XXXX-XXXX`):** A **one-time, single-use** cryptographic code issued only to the initial Clinic Administrator. It binds the initial admin, marks the clinic `ACTIVATED`, and initiates onboarding. It is immediately consumed and cannot be reused. Subsequent renewals **never** require an activation code.

### 12.3 Authoritative Tenant Context Rule
For all authenticated tenant staff (Admin, Operator, Doctor):
$$\text{authenticated\_user.clinic\_id} \implies \text{TenantContext}$$
- Request headers (`X-Clinic-ID`, `X-Clinic-Code`), route parameters, and session values can **NEVER** override the authenticated user's clinic.
- Any attempt to access cross-tenant patients, appointments, templates, fees, or settings results in an immediate **403 Forbidden** security block.

### 12.4 Onboarding State Management
Clinic lifecycle tracks three independent states:
1. **License State:** `TRIAL`, `ACTIVE`, `EXPIRED`, `SUSPENDED`, `REVOKED`, `RENEWED`.
2. **Activation State:** `PENDING_ACTIVATION`, `ACTIVATED`.
3. **Onboarding Status:** `NOT_STARTED` $\implies$ `IN_PROGRESS` $\implies$ `COMPLETED`.

A clinic is authorized upon activation, but remains in onboarding configuration until the Administrator finishes the setup wizard at `/settings/onboarding`.

### 12.5 Doctor Seat Capacity & Add-on Licensing
- **Base Plan Capacity:** Standard subscription includes **1 Doctor Seat** (or plan-defined seats).
- **Additional Doctor Add-on:** Additional doctor seats are priced at **₹2,999 / doctor / year** (configurable by Superadmin).
- **Transactional Enforcement:** The backend strictly verifies:
  $$\text{active\_doctor\_count} < \text{subscribed\_doctor\_capacity}$$
  If the limit is reached, doctor creation or activation is blocked with the message:
  > *"You have reached the maximum number of doctor seats included in your license. Please contact the application provider to purchase an additional doctor seat."*
- **Doctor Deactivation/Deletion:** Deactivating or deleting a doctor frees up capacity for another doctor, but never alters historical subscription billing or event audit logs.

### 12.6 Clinic-Specific Public Booking & Short URL
- **Canonical Public Booking:** `https://yourdomain.com/book/{clinic_code}` (e.g., `/book/NX0001`). Resolves strictly that clinic's branding, active doctors, schedules, consultation modes, and fee structure.
- **Short QR URL:** `https://yourdomain.com/b/{clinic_code}` (e.g., `/b/NX0001`). Redirects instantly to the canonical booking page.
- **Legacy Fallback:** Accessing unparameterized `/book` gracefully redirects to default tenant `NX0001`.
- **Invalid Clinic Code:** Requesting an unknown clinic code yields a safe **404 Not Found** and never falls back to arbitrary clinic data.

---

## 13. Clinic Subscription Renewal & Zero-Disruption Workflow (Revision 35)

### 13.1 License & Subscription Status Panel (`/settings/subscription`)
Clinic Administrators have real-time visibility into their subscription health via the **License & Subscription** management screen:
- **Current Plan & Duration:** Displays active plan tier (e.g., Yearly Plan, 12 Months Coverage).
- **Masked License Key:** Displays tenant license key formatted as `NXCL-••••-••••-••••-••••` for security.
- **Authoritative Validity Period:** Start Date and Expiry Date with precise Remaining Days countdown calculated in `Asia/Kolkata` (IST) timezone.
- **Doctor Seat Capacity & Allocation Table:** Shows total licensed doctor seats (Base Included + Paid Add-ons), current utilization, and each registered doctor's assigned seat type.

### 13.2 Zero-Disruption Paid Renewal Policy
DxClinic establishes a zero-disruption renewal model for clinic owners:
1. **External Payment:** Subscription renewals and doctor seat expansions are paid outside the application directly to the software provider (via UPI, Bank Transfer, Cheque, or Cash).
2. **Superadmin Atomic Renewal:** Upon payment verification, the platform Superadmin confirms and activates the renewal inside DxClinic with one click.
3. **Zero Tenant Actions Required:**
   - Clinic Administrators do **NOT** enter any new license key or activation code.
   - The clinic does **NOT** undergo reactivation or re-onboarding.
   - Staff login IDs and passwords remain completely unchanged.
   - Active clinical queues, patient records, bills, and accounting ledgers continue without any pause or reset.
4. **Instant Synchronization:** The moment Superadmin submits the renewal, the clinic's validity date and doctor seats update instantaneously in the database and appear in `/settings/subscription`.

---

## 14. Revision 38 Platform Hardening & Multi-Tenant Data Isolation

### 14.1 Centralized Doctor Name Normalization
- Doctor records in the database store clean canonical names without title prefixes (e.g., `Rajesh Sharma`, `Amit Verma`).
- The system automatically strips any entered prefixes (`Dr.`, `Dr `, `Doctor `) and formats all customer-facing displays via `DoctorNamePresenter::format($doctor)` as `Dr. Firstname Lastname`.
- Eliminates duplicate prefixes such as "Dr. Dr." across all patient portals, reports, receipts, and print templates.

### 14.2 Standardized Customer-Facing Terminology
- All customer-facing screens and receipts use `Clinic Consultancy`, `Online Consultancy`, and `Visiting Consultancy`.
- Internal configuration and database enums (`CLINIC_PHYSICAL`, `ONLINE`, `VISITING`) remain unchanged.

### 14.3 Strict Multi-Tenant Fee Isolation
- Fee resolution strictly validates that doctor-specific fee overrides only apply when the doctor belongs to the target clinic (`$doctor->clinic_id === $clinicId`).
- Baseline clinic-wide rates ensure fallback stability across all booking channels.

---

## 15. Revision 46: Database Integrity Hardening & Versioned Doctor Fee Architecture

### 15.1 The Authoritative Relationship
DxClinic Revision 46 enforces an unyielding architectural relationship from UI down to raw MySQL InnoDB constraints:
```
CLINIC
  │
  └── DOCTOR (clinic_id verified)
        │
        └── MAPPED BOOKING TYPE (is_enabled = true for doctor)
              │
              └── EXACT DOCTOR + CLINIC FEE (active on appointment date)
                    │
                    └── IMMUTABLE APPOINTMENT SNAPSHOT (fee_amount permanently frozen)
```

### 15.2 Database-Level Composite Foreign Keys
Even if an application bug or regressed code attempts an invalid combination, the MySQL InnoDB database physically rejects the transaction:
1. **Parent Tables**:
   - `doctors`: Enforces `UNIQUE(id, clinic_id)`.
   - `booking_types`: Enforces `UNIQUE(id, clinic_id)`.
2. **Child Constraints**:
   - `doctor_booking_type_availability`: Both `(doctor_id, clinic_id)` and `(booking_type_id, clinic_id)` are composite foreign keys.
   - `fee_masters`: Both `(doctor_id, clinic_id)` and `(booking_type_id, clinic_id)` are composite foreign keys.
   - `appointments`: Both `(doctor_id, clinic_id)` and `(booking_type_id, clinic_id)` are composite foreign keys (`ON DELETE RESTRICT` to protect clinical records).

### 15.3 Versioned Fee Master & Non-Overlapping Dates
Fee Master supports scheduling future fee changes without altering past appointments:
- **Composite Version Key**: `UNIQUE(clinic_id, doctor_id, booking_type_id, effective_from)`.
- **Non-Overlapping Ranges**: Active fee versions for the same doctor and booking type must not have overlapping dates. Adding an open-ended fee automatically caps the prior open-ended fee on the preceding day.
- **Date-Specific Snapshot**: New bookings look up the fee active on `appointment_date` and freeze it into `appointments.fee_amount`. Later tariff adjustments never mutate past bills, appointments, or receipts.

### 15.4 Separation of Four Core Booking Dimensions
1. **Booking Source**: Where the booking originated (`CLINIC`, `ONLINE`, `TELEPHONIC`).
2. **Consultation Mode**: Delivery method (`CLINIC_PHYSICAL`, `ONLINE`, `VISITING_CONSULTANCY`).
3. **Booking Type**: Service package (`CLINIC_CONSULTANCY`, `ONLINE_CONSULTANCY`, `EMERGENCY_CONSULTANCY`, or custom).
4. **Fee Master**: Doctor-specific tariff for `(clinic_id, doctor_id, booking_type_id, effective_from)`.

---

## 16. Diagnostic Laboratory Management, Specimen Tracking & Release Governance (Revision 36)

### 16.1 Clinical & Financial Domain Separation
Revision 36 introduces normalized entities separating clinical investigation lifecycles from billing:
```
Diagnostic Bill (Financial Source of Truth — Immutable)
       │
       └── Diagnostic Order (Clinical Investigation Header)
             │
             ├── Diagnostic Order Items (Ordered tests/parameters snapshot)
             ├── Diagnostic Specimen (Physical biological sample & container)
             ├── Diagnostic Result(s) (Clinical values, units, reference intervals)
             └── Diagnostic Report (Authorized release with verification signature & QR)
```
Creating, collecting, entering, verifying, or releasing diagnostic results generates **zero financial transactions**, zero journal entries, and zero alterations to existing diagnostic bills or payment receipts.

### 16.2 Concurrency-Safe Specimen Allocation
Specimen identifiers are generated using an atomic sequence table (`diagnostic_sequences`) with row-level locks and day-boundary reset:
$$\mathbf{SMP}\text{-}\{\text{CLINIC\_CODE}\}\text{-}\{\text{YYYYMMDD}\}\text{-}\{\text{XXXX}\} \quad \text{e.g., } \mathbf{SMP}\text{-}\mathbf{NX0001}\text{-}\mathbf{20260930}\text{-}\mathbf{0001}$$
- Guaranteed monotonic, gapless within each clinic day.
- Completely collision-resistant under high concurrent desk traffic.
- Never reveals raw auto-increment database primary keys to patients.

### 16.3 Laboratory State Machine
The clinical lifecycle follows a deterministic, guarded finite-state machine:
$$\textbf{ORDERED} \longrightarrow \textbf{SAMPLE\_PENDING} \longrightarrow \textbf{COLLECTED} \longrightarrow \textbf{PROCESSING} \longrightarrow \textbf{RESULT\_ENTERED} \longrightarrow \textbf{VERIFIED} \longrightarrow \textbf{RELEASED}$$
- **Exceptional States**: `REJECTED` and `CANCELLED`.
- **State Transition Guards**:
  - Direct transition from `ORDERED` or `SAMPLE_PENDING` to `RELEASED` is physically impossible.
  - Rejection requires a documented reason and moves the specimen to `REJECTED`, resetting order status to `SAMPLE_PENDING` for recollection.
  - Released reports cannot revert to earlier states; changes require controlled versioned correction.

### 16.4 Verification Demarcation & RBAC
- **Operators**: Allowed to locate orders, collect specimens, track specimen status, print collection labels, and enter draft results (`ENTERED`). Operators are **forbidden** from verifying results or releasing reports.
- **Doctors & Admins**: Hold sole authority to review clinical results against biological reference ranges, execute clinician verification (`VERIFIED`), and authorize report release (`RELEASED`).

### 16.5 Controlled Result Correction & Audit Trail
Once a result is VERIFIED or RELEASED:
1. Direct in-place database overwrite is permanently blocked.
2. An authorized doctor or admin must initiate a **Controlled Correction** modal.
3. The user must provide a clinical reason ($\ge 5$ characters).
4. The existing result record is marked `CORRECTED`.
5. A new active version is created, referencing the modifying user and timestamp.
6. A permanent audit log record is written to `audit_logs` capturing `DIAGNOSTIC_RESULT_CORRECTED`, old value, new value, reason, and user ID.

### 16.6 Production Diagnostic Report PDF & QR Verification
- Released reports generate production-grade A4 clinical PDFs via `/pdf/diagnostic-result-report/{reportId}`.
- Includes clinic header, patient demographic block, specimen details, test results table with reference ranges, abnormal flags, verifying clinician signature block, and secure verification QR code.
- QR Code points to public verification URL:
  $$\text{https://dxclinic.example.local/verify-lab-report/}\{\text{report\_number}\}$$
- Public verification page displays authentic clinical details without exposing administrative controls or patient contact details.
- Reprinting is 100% idempotent and generates zero duplicate records.

### 16.7 Diagnostic Billing Integration Demarcation
- Diagnostic orders originate strictly from existing billing infrastructure (`DiagnosticBilling`).
- Result entry and report verification create zero additional accounting entries, maintaining absolute ledger purity.

---

## 17. WhatsApp Automation & Meta Cloud API Integration

### 17.1 Architecture & Single Daily Token Series
DxClinic integrates the official Meta WhatsApp Business Cloud API as an automated booking and communication channel (`WHATSAPP`).
- **Channel Adapter Role**: WhatsApp is strictly an inbound communication adapter. It communicates with the core `AppointmentService`, `TokenAllocationService`, and `PatientIdentityService`.
- **Unified Daily Token Sequence**: Appointments booked via WhatsApp consume the **exact same daily token series** as Clinic Walk-ins (`CLINIC`), Patient PWA (`ONLINE`), and Telephonic bookings (`TELEPHONIC`). No separate `whatsapp_tokens` table exists.
- **Tenant Boundary**: Every WhatsApp connection belongs strictly to a specific clinic (`clinic_id`). Cross-clinic tenant routing is derived solely from the registered `phone_number_id` verified against `whatsapp_connections`.

### 17.2 Meta Embedded Signup & Onboarding
1. Navigate to **Settings &rarr; WhatsApp Automation** (`/settings/whatsapp`).
2. Click **Connect WhatsApp** to trigger Meta Embedded Signup.
3. Authenticate with Facebook Business Manager and select/register the official clinic phone number.
4. Upon return, the connection status shifts to `CONNECTED`.
5. Access tokens are encrypted at rest using AES-256 (`access_token_encrypted`).

### 17.3 Webhook Handshake & Idempotency
- **Endpoint**: `/api/v1/webhooks/whatsapp`
- **GET (Verification)**: Validates `hub.mode = subscribe` and cryptographic `hub.verify_token`.
- **POST (Event Ingestion)**: Verifies `X-Hub-Signature-256` HMAC-SHA256 signature using the configured app secret.
- **Idempotency Guard**: All inbound events are logged in `whatsapp_webhook_events`. Duplicated Meta deliveries are recognized via `provider_event_id` and processed logically once, preventing duplicate patient profiles, duplicate appointments, or duplicate tokens.

### 17.4 State Machine & AI Boundaries
- The conversational workflow is driven by a deterministic finite state machine (`WhatsAppBookingStateMachine`):
  $$\textbf{NEW} \rightarrow \textbf{MENU} \rightarrow \textbf{WAITING\_DATE} \rightarrow \textbf{WAITING\_SESSION} \rightarrow \textbf{WAITING\_DOCTOR} \rightarrow \textbf{CONFIRMATION} \rightarrow \textbf{BOOKED}$$
- **AI Boundary**: AI natural language processing assists solely in conversational parsing (intent identification, date/session extraction). AI **never** decides authoritative tokens, availability, Sunday rules, or financial transactions.

### 17.5 Human Handoff & Reception Takeover
- When a patient selects *"Talk to Receptionist"*, the state transitions to `HUMAN_HANDOFF`.
- Automated AI responses are suspended until the reception desk releases the conversation back to automated mode (`Return to AI`).

### 17.6 Disconnecting WhatsApp
- Clicking **Disconnect** in WhatsApp Settings safely transitions status to `DISCONNECTED`.
- Historical appointments, patients, and conversation transcripts remain permanently preserved.

---

## 18. Real-Time Live Queue Window

### 18.1 Purpose & Architecture
The **Live Queue Window** (`/clinic/{public_slug}/queue/live` or `/queue/live`) is a dedicated, real-time presentation display designed for secondary monitors and waiting room displays.
- **Read-Only**: The Live Queue is strictly a read and presentation channel. It performs zero token allocations, zero appointment modifications, and zero billing side-effects.
- **Authoritative Data Source**: Reads live records from `appointments`, `patients`, `doctors`, and `visits` via `QueueViewService`.
- **No Duplicate Tables**: No `live_queue` table exists. It renders the single authoritative source of truth.

### 18.2 Opening the Separate Browser Window
1. From any screen (Navbar, Dashboard, or Arrival Queue), click **[Open Live Queue]**.
2. A separate independent browser window launches (`window.open`) at 1280x800 resolution without distracting browser toolbars.
3. Closing or minimizing the window has **zero impact** on the operator's active session, cash drawer, or background operations.

### 18.3 Role-Based Scopes & Isolation
- **ADMIN & OPERATOR**: Full visibility of all clinic doctors, sessions, and patients for the current day.
- **DOCTOR**: Authoritative default filter strictly restricted to the logged-in doctor's assigned patients.
- **Cross-Clinic Security**: Accessing another clinic's live queue URL or subscribing to another clinic's broadcast channel is server-enforced and rejected with HTTP 403 Forbidden.

### 18.4 Real-Time Updates & Fallback Recovery
- **WebSocket Broadcast**: Dispatched via `QueueUpdated` event on private channel `private-clinic.{clinicId}.queue` **strictly after** database transaction commit.
- **Event-Driven Refresh**: Receipt of `QueueUpdated` triggers an authenticated reload from the database, eliminating race conditions or stale client-side mutations.
- **Connection Indicators**:
  - `REALTIME CONNECTED` (Green): Active WebSocket connection.
  - `RECONNECTING` (Amber): Socket interrupted; automatic reconnection in progress.
  - `FALLBACK REFRESH` (Blue): Polling fallback (every 6 seconds) ensuring continuous zero-gap display even if local network blocks WebSockets.

---

## 19. Developer Attribution & System Metadata
- **Application Developer**: Kundan Kumar
- **Development Company**: Nexora Solutions
- **Developer Location**: Varanasi, Uttar Pradesh, India
- **Developer Contact**: +91 98765 43210 | contact@nexorasolutionsind.co.in
- **Developer Website**: [https://nexorasolutionsind.co.in/](https://nexorasolutionsind.co.in/)

---
*DxClinic Platform Administration Manual &bull; Master Contract Implementation Verified &bull; Copyright &copy; 2026 DxClinic*


