# DOCTOR — QUICK REFERENCE CARD
**DxClinic Clinical Management System — Revision 35**  
*Manual Type: 1-Page Quick Reference Card | Version: Revision 35 | Developer: Kundan Kumar | Copyright © 2026 DxClinic*

**Application Developer:** Kundan Kumar | **Development Company:** Nexora Solutions, Varanasi, Uttar Pradesh, India  
**Website:** https://nexorasolutionsind.co.in/ | **Contact:** +91 98765 43210 | **Email:** contact@nexorasolutionsind.co.in  

---

### 1. LOGIN & CLINICAL QUEUE
1. Open browser: Navigate to clinic portal (`http://127.0.0.1:8000/login` locally on server, or `https://dxclinic.example.local/login` over secure HTTPS).
2. Log in with Doctor credentials.
3. Access active patient queue and chronological historical encounters via streamlined sidebar.

### 2. PATIENT CONSULTATION & VITALS REVIEW
1. Select arrived patient by Token Number or Name.
2. Review pre-recorded vital signs (Weight, BP, Pulse, SpO2, Temp, RR, RBS).
3. Inspect longitudinal consultation history, prior prescriptions, and diagnostic reports.

### 3. DOCTOR PAD PRINTING & REPRINTING
1. Click **[Print Doctor Pad]** or **[View PDF]**.
2. **Stationery Overlay Mode (Revision 37)**:
   - Uses your active custom template designed via the visual layout editor, or verified legacy layout.
   - Outputs: Patient Demographics, Date, Token, Selected Vitals, and Doctor Custom Static Texts (e.g. consultation hours, emergency instructions) with exact point typography.
   - Suppresses: Scanned background, clinic logos, headers, QR codes, financial details.
3. **Missing Vitals Guarantee**: If a vital was not taken, it is left blank. No fake defaults or zeros are ever printed.
4. **Reprint Safety**:
   - Zero payments, zero journals, zero refunds, zero duplicate tokens/visits. 100% idempotent.
5. **Historical Template Immutability**: Reprints permanently reproduce the exact template version assigned at the visit date. Changes in the visual editor apply only to new consultations and never alter past patient prescription records.
6. **Physical Print Standard (Revision 30)**: Ensure prints use **Scale: 100% (Actual Size)** with Margins: None. If stationery shifts, report millimeters to Admin for printer profile adjustment.
7. **Unicode Hindi Support (Revision 30)**: Hindi/Devanagari patient names and notes render with full TrueType font fidelity without character loss or question marks.

### 4. RECOMMENDING A DISCOUNT OR REFUND
1. If patient is eligible for a fee reduction post-consultation:
2. Go to Encounter -> **Doctor Recommendation**.
3. Select Type: `DISCOUNT_AFTER_SETTLEMENT` (or `FULL_REFUND`).
4. Enter recommended refund amount (e.g., `₹100.00`).
5. Enter clinical reason (e.g., *"Patient requested discount; recommended ₹100 refund."*).
6. Click **[Submit Recommendation]**.
7. Desk operator/admin will execute the refund and balance the cash/UPI register.

### 4b. DIAGNOSTIC LAB REVIEW, VERIFICATION & RELEASE (`/lab`) — REVISION 36
1. **Access Lab Workspace**: Click **Diagnostic Lab** in sidebar (`/lab`, shortcut: `Alt + L`).
2. **Review Investigation Worklist**: View ordered tests, specimen collection status, and entered draft results.
3. **Clinician Verification**: Review values against reference ranges and abnormal flags. Click **[Verify]** to certify results.
4. **Authorize Final Report**: Click **[Release Report]**, add optional clinical impressions, and confirm release. This issues the finalized diagnostic PDF and generates the public verification QR code.
5. **Controlled Result Corrections**: If an amended result is necessary post-release, use **[Correct]** with a mandatory clinical reason. An audit trail captures the amendment while preserving the original record.
6. **Strict Financial Separation**: Doctors hold clinical release authority, but CANNOT alter diagnostic bills, cash sessions, journals, or payment refunds.
7. **Mobile OTP Deferred**: Patient notification via mobile OTP is intentionally deferred to a future phase.

### 5. BOUNDARY RULES & CLEAN NAVIGATION
- **You Can**: Review history, reprint Doctor Pads, review lab results, verify/release diagnostic reports, submit discount recommendations.
- **You Cannot**: Modify paid amounts directly, process cash/UPI refunds, activate templates, create accounting entries, or delete records.
- **Navigation**: Distraction-free sidebar with Daily Work (Dashboard, Queue, Patients, Consultation, Diagnostic Lab) and Help & Guide.

### 6. DOCTOR LIVE QUEUE MONITOR (`/queue/live`)
1. **[Open Live Queue]**: Click the green button to open your dedicated consultation queue monitor in a separate window.
2. **Own Patient Scope**: Automatically filters to show only patients booked with you.
3. **Arrival & Booking Visibility**: Instantly see when patients arrive (`ARRIVED`) or book via `CLINIC`, `ONLINE` (PWA), or `WHATSAPP`.
4. **Unified Tokens**: Unique, monotonic tokens across all channels; cancellation or absence permanently consumes the token and numbers are never reused.
5. **Zero Financial Clutter**: Read-only display focused purely on patient flow and consultation readiness.

### 7. DEVELOPER ATTRIBUTION & SUPPORT
- **Developer**: Kundan Kumar &bull; **Company**: Nexora Solutions, Varanasi, Uttar Pradesh, India
- **Contact**: +91 98765 43210 &bull; contact@nexorasolutionsind.co.in &bull; [https://nexorasolutionsind.co.in/](https://nexorasolutionsind.co.in/)

---
*DxClinic Master Contract Verified &bull; Quick Doctor Reference &bull; Copyright © 2026 DxClinic*


