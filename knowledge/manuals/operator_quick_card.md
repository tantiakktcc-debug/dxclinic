# OPERATOR — QUICK OPERATION CARD
**DxClinic Clinical Management System — Revision 35**  
*Manual Type: 1-Page Quick Reference Card | Version: Revision 35 | Developer: Kundan Kumar | Copyright © 2026 DxClinic*

---

### 1. RECEPTION LOGIN & DESK PREPARATION
1. Open browser: Navigate to `http://127.0.0.1:8000/login` (Local) or `https://dxclinic.example.local/login` (Remote HTTPS).
2. Sign in with Operator / Receptionist credentials.
3. Open Cash Session: Ensure morning/evening session is opened with verified physical opening cash.

### 2. OPERATOR BOOKING ENTRY (`/operator/booking`)
*Do NOT confuse with Public Patient PWA (`/book/{public_slug}`, e.g., `/book/sharmacare`).*
1. Click **Operator Booking** in sidebar (`/operator/booking`).
2. **Patient Identification**:
   - **Search Existing**: Enter name, 10-digit mobile, or `DXP-XXXXX`. Click patient card to select.
   - **Or New Patient**: Enter Full Name (Unicode letters only, e.g., `Kundan Kumar` or `राम कुमार`), Age (0 for newborn to 130), Gender, Mobile (`+91 9876543210`), Street Address, and City.
3. **Select Consultation Mode**:
   - `[ ] 🏥 Clinic Consultation — ₹600` (In-person clinic consultation)
   - `[ ] 🌐 Online Consultancy — ₹700` (Virtual consultation via telemedicine)
4. **Select Attending Doctor**: Choose doctor from dropdown (pre-selected if default doctor configured).
5. **Select Session & Date**: Select Morning, Evening, or Sunday.
6. Click **[Allocate Common Queue Token]**. Token is assigned from unified daily sequence (`001`, `002`...).
7. Click **Print Token** to hand patient receipt.

### 3. ARRIVAL QUEUE & VITALS CAPTURE (`/operator/queue`)
1. When patient arrives at clinic desk, locate token row.
2. Click **[Mark Arrived]**. Status updates to `ARRIVED` and Visit is created.
3. If physical consultation, enter Vitals: Weight, BP (Systolic/Diastolic), Pulse, SpO2, Temperature. Click **[Save Vitals]**.
4. Click **Print Doctor Pad** to generate clinical prescription slip.

### 4. HISTORICAL BILLING & CONSULTATION SETTLEMENT (`/operator/consultation`)
1. **Tabs**:
   - **[New Consultation Billing]**: Active queue settlement.
   - **[Consultation Billing History]**: Full historical ledger. Action button sequence: `[View] -> [Edit] -> [View Pad] -> [View Changes] -> [Refund] -> [Receipt]`.
   - **[Doctor Pad History]**: Historical clinical encounters repository with Template/Version tracking.
2. **[Edit] Button Placement**: Located immediately before `[View Pad]` for instant access to controlled modification & refund workflows.
3. **Fee Resolution**: Physical: **₹600** | Online: **₹700** (from Fee Master).
4. **5-Day Free Revisit**: Physical consultation within 5 days applies ₹600 waiver automatically (Status: `WAIVED`, ₹0 collection).
5. **Payment**:
   - **Cash**: Collect cash & click **[Collect Cash & Settle]**.
   - **UPI**: Present QR, verify physical Soundbox audible confirmation, check **[Soundbox Voice Confirmed]**, click **[Settle UPI]**.
   - Print strict **A5 Receipt** (Patient & Clinic copies).

### 5. HISTORICAL DIAGNOSTIC BILLING (`/operator/diagnostics`)
1. **Tabs**: **[New Diagnostic Billing]** and **[Diagnostic Billing History]**.
2. **Column Sequence**: Table displays `Status | Edit | Method` for instant access to bill modifications and refunds.
3. **Historical Invariant**: Diagnostic line items display preserved historical snapshot rates (never recomputed with today's master rates).
4. Reprint Diagnostic Receipts and Lab Slips directly from history with zero accounting side-effects.

### 5b. DIAGNOSTIC LAB WORKFLOW (`/lab`) — REVISION 36
1. Open **Diagnostic Lab** in sidebar (`/lab`, shortcut: `Alt + L`).
2. **Investigation Worklist**: Locate patient order by Order #, Patient Name, or Patient ID.
3. **Specimen Collection**:
   - Click **[Collect Sample]**. Select container/type and enter optional collection notes.
   - Click **[Confirm Collection & Generate Sample ID]**. The system atomically allocates a human-readable specimen ID (`SMP-{CLINIC_CODE}-{YYYYMMDD}-{XXXX}`).
   - Double-clicking is prevented by row-level idempotency locks.
4. **Result Entry**: Once collected, click **[Enter Results]** to enter numeric, text, or qualitative values.
5. **Role Limitation**: Operators can enter draft results, but CANNOT verify results or release final reports (restricted to Doctors/Admins).
### 6. REPRINTING HISTORICAL DOCTOR PADS
1. Locate patient row by Token, Name, Doctor, or Date in Queue or History.
2. Click **[Reprint Doctor Pad]** or **[Pad]**.
3. **Safety Guarantee**: Reprinting generates **zero payments**, **zero journals**, **zero refunds**, and **zero duplicate visits/tokens**.
4. **Historical Template Immutability (Revision 37)**: Reprints reproduce the **exact template version** committed when the encounter was created. Even if the doctor's active template was modified or updated to a new version in the designer, historical encounters reprint with their original layout and millimeter coordinates.
5. Legacy records without a template cleanly use the legacy Doctor Pad renderer.
6. **Physical Print Calibration Standard (Revision 30)**: Always print at **Scale: 100% (Actual Size)** with Margins: None (never "Fit to Page"). Hindi/Devanagari patient text prints cleanly. Report any millimeter stationery shifts to Admin for printer profile compensation.

### 7. CONTROLLED BILL MODIFICATION & REFUNDS
1. **Financial Immutability**: Historical settled bills, payments, receipts, and journals are **NEVER overwritten**.
2. **Pending Bills**: Can be edited with a mandatory reason.
3. **Paid Bills (Doctor-Recommended Discount / Refund)**:
   - Click **[Edit]** on the historical bill.
   - Select Type: `DISCOUNT_AFTER_SETTLEMENT` or `PARTIAL_REFUND`.
   - Enter refund amount and **Mandatory Reason** (minimum 10 characters, non-generic).
   - Select doctor instruction reference.
   - Choose refund settlement method (`CASH` or `UPI`).
   - Settle refund: Original bill remains intact; refund transaction, reversal journal, and receipt revision are generated.
   - Full refund marks bill as `REFUNDED` only when 100% of payment is returned.

### 8. STREAMLINED DAILY SIDEBAR & HELP
1. **Daily Work First**: Dashboard, OPD Booking, OPD Queue, Patients, Consultation Billing, Diagnostic Billing, and Cash Counter.
2. **Collapsible Groups**: Reports and Help & Guide are collapsed by default to maximize daily clinical screen real estate.
3. **Instant Help**: Click **Help & Guide ▾** for FAQ, User Manual, and Quick Reference.

### 9. REAL-TIME LIVE QUEUE (`/queue/live`)
1. **[Open Live Queue]**: Click the green button on the top navbar or Arrival Queue. Opens a secondary display window (`window.open`).
2. **Move to Secondary Monitor**: Drag the Live Queue window to patient-facing or waiting-room TV.
3. **Close & Reopen**: Closing the window does not close your cash session or logout the user. Reopen anytime to reload the live queue.
4. **Source Badges**: Identify booking origin instantly via badges: `CLINIC` (Walk-in), `ONLINE` (Patient PWA), `WHATSAPP` (WhatsApp Assistant).
5. **Unified Token Sequence**: All bookings increment the exact same single daily series. Tokens are unique, monotonic, and never reused after cancellation or absence.
6. **Sound Alert**: Toggle chime sound ON/OFF for new patient booking alerts.
7. **Connection States**: Green = Connected; Amber = Reconnecting; Blue = Fallback refresh active.

### 10. DEVELOPER ATTRIBUTION & SUPPORT
- **Developer**: Kundan Kumar &bull; **Company**: Nexora Solutions, Varanasi, Uttar Pradesh, India
- **Contact**: +91 98765 43210 &bull; contact@nexorasolutionsind.co.in &bull; [https://nexorasolutionsind.co.in/](https://nexorasolutionsind.co.in/)

---
*DxClinic Master Contract Verified &bull; Quick Operator Desk Reference &bull; Copyright © 2026 DxClinic*

