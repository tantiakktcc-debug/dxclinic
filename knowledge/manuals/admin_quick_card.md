# ADMIN — QUICK ADMINISTRATION CARD
**DxClinic Clinical Management System — Revision 35**  
*Manual Type: 1-Page Quick Reference Card | Version: Revision 35 | Developer: Kundan Kumar | Copyright © 2026 DxClinic*

**Application Developer:** Kundan Kumar | **Development Company:** Nexora Solutions, Varanasi, Uttar Pradesh, India  
**Website:** https://nexorasolutionsind.co.in/ | **Contact:** +91 98765 43210 | **Email:** contact@nexorasolutionsind.co.in  

---

### 1. CLINIC & DOCTOR MANAGEMENT (`/settings/clinic-doctor`)
1. **Clinic Profile**: Configure legal Clinic Name, Address, City, Phone, and Email.
2. **Doctor Registry**: Register attending doctors with Name, Qualification, Registration Number, Specialization, and Contact.
3. **Default Booking Doctor**: Designate which active doctor is auto-preselected across Operator and Public booking desks.
4. **Single Doctor Auto-Selection**: If only one doctor is active, system auto-selects without prompting.

### 2. FEE MASTER & CONSULTATION RATES (`/settings/fee-master`)
1. Authoritative rate source for all billing operations (no hardcoded fees).
2. Default Configuration:
   - `CONSULTATION_PHYSICAL`: **₹600.00** (Clinic Consultation).
   - `CONSULTATION_ONLINE`: **₹700.00** (Virtual Online Consultancy).
3. Changes immediately reflect in Operator Desk, Public PWA, and Consultation Invoices.

### 3. HISTORICAL BILLING & FINANCIAL IMMUTABILITY
1. **Financial Invariant**: Settled payments, receipts, journals, and bills are **never overwritten or altered**.
2. **Consultation & Diagnostic History**: Full historical records accessible across all past dates with multi-parameter filtering.
3. **Historical Line Items**: Diagnostic rates display preserved historical snapshot prices, never recalculating from today's master.
4. **Doctor Pad History**: Idempotent reprinting of past clinical encounters creates zero payments, zero journals, zero refunds, and zero duplicate visits.

### 4. CONTROLLED MODIFICATIONS, REFUNDS & SUPPLEMENTS
1. **Pending Bills**: In-place edits permitted with mandatory reason ($\ge 10$ chars) and audit logging.
2. **Settled Bills**: Corrections occur strictly via new transactional records (`refunds`, `receipt_revisions`, or supplementary bills).
3. **Doctor-Recommended Discounts**: Handled as post-settlement partial refunds. Original bill remains intact; net retained amount is adjusted.
4. **Diagnostic Increases**: Additions generate a linked **Supplementary Diagnostic Bill** (`{bill_number}-SUPP-{n}`) with separate payment and receipt.
5. **Refund Reversals**:
   - Cash Refund: **Dr Revenue (4001/4002) | Cr Cash (1001)**
   - UPI Refund: **Dr Revenue (4001/4002) | Cr Bank Ledger (1002)**

### 5. USER ACCOUNTS & RBAC PERMISSIONS (`/settings/users`)
1. **Roles**:
   - `ADMIN`: Unrestricted master access across clinical, financial, backup, refund approval, and settings modules.
   - `OPERATOR`: Desk registration, queue, vitals, billing, cash sessions, refund requests, and daily reports.
   - `DOCTOR`: Patient clinical encounters, doctor pad reprinting, and discount/refund recommendations.

### 6. COMPREHENSIVE CLINICAL & FINANCIAL REPORTING
1. **OPD Reports** (`/reports/opd`): Session-wise, Day-wise, Doctor-wise. Format strictly omits vitals.
2. **Diagnostic Reports** (`/reports/diagnostics`): Itemized tests, snapshot rates, collections, and doctor breakdown.
3. **Exports**: View on screen, Print, Download real PDF, or Export CSV.

### 7. COMPLETE FINANCIAL RECONCILIATION (`/reports/reconciliation`)
1. Mathematical Verification:
   $$\text{Expected Cash} = \text{Opening Cash} + \text{Cash Collections} - \text{Cash Expenses} - \text{Cash Deposits} - \text{Cash Refunds Paid in Cash}$$
   $$\text{Book Bank Balance} = \text{Opening Bank} + \text{UPI Collections} + \text{Cash Deposits} - \text{Bank Expenses} - \text{Bank Refunds Paid from Bank}$$
2. Strict double-entry equilibrium: $\sum \text{Debits} == \sum \text{Credits}$ with **₹0.00 difference**.

### 8. DAILY BACKUP & DISASTER RECOVERY (`/settings/backup`)
1. **Automatic Backup**: Triggered daily upon evening closing.
2. **Encryption**: AES-256-GCM + PBKDF2 key derivation. Proprietary `.ncbk` container.
3. **Restore Safety**: Pre-restore snapshot, maintenance mode, schema checksum verification, and rollback protection.

### 9. PRESCRIPTION PAD DESIGNER (`/settings/prescription-pad-templates`) — REVISION 38
1. **Desktop Multi-Selection & Marquee**: Select multiple objects via `Ctrl/Cmd + Click`, `Shift + Click`, or click-drag marquee (lasso) selection. Moving multi-selection translates all elements synchronously at 60fps with zero network requests.
2. **Alignment & Distribution**: Align single element to page boundaries; align multiple elements relative to common bounding box (Left, Center, Right, Top, Middle, Bottom). Evenly distribute 3+ elements horizontally or vertically using true millimeter spacing.
3. **Smart Guides & Snapping**: Ephemeral pink dashed guide lines display when objects approach page centers, borders, or neighboring element centers/edges; optional smart snapping gently locks positions.
4. **Z-Order Layer Management**: Dedicated Layers sidebar panel allows full stack reordering (Bring to Front, Forward, Backward, Send to Back). Stacking order is persistently stored in `sort_order` and matches DomPDF print order identically.
5. **Element Locking**: Lock sensitive clinic logos, symbols, or headers to prevent accidental movement or resize. Deliberate unlock restores editing. Locked state does not suppress PDF rendering.
6. **Atomic Undo / Redo Transactions**: Reversible history (`Ctrl+Z`, `Ctrl+Shift+Z` / `Ctrl+Y`) captures complete transactions (moves, resizes, duplicates, deletions, alignments, typography, rotation). Zoom and pan never pollute document history.
7. **Free Element Rotation**: 0° to 360° rotation around physical element center anchor (`transform-origin: center center;`) reproduced with absolute fidelity in DomPDF.
8. **Real-Time Drag, Resize & Aspect Ratio**: 60fps SVG translations, 8-point boundary handles, and proportional aspect ratio locking for uploaded images.
9. **Native Image Upload Security**: PNG/JPG validation (MIME, `getimagesize()`, 5MB limit, sanitized storage) with persistent identities (`image_{num}_{hash}`).
10. **Physical Millimeter Units**: Millimeter coordinates (`x_mm`, `y_mm`, `w_mm`, `h_mm`) prevent device/zoom scaling distortion. 1 pt font size corresponds exactly to 1/72 inch in DomPDF.
11. **Safe Versioning & Dirty State**: Visual dirty indicator (`● Unsaved changes` / `✓ Saved`). Editing active templates creates new `DRAFT` (v2, v3); activating draft archives old version.
12. **Historical Template Immutability**: Historical consultations permanently reference their committed template version. Referenced templates cannot be deleted (`RESTRICT`). Historical appointments are never modified by the editor.
13. **Printer Profile Calibration (Revision 30)**: Global physical offsets (`offset_x_mm`, `offset_y_mm`) and scale (`scale_x`, `scale_y`) are isolated in `printer_profiles`. Adjusting calibration never modifies template coordinates. Always print at **Actual Size (100%)** with margins None.
14. **TrueType Unicode Font Engine (Revision 30)**: Embedded SIL-licensed `Noto Sans Devanagari` registered in DomPDF. Selectable in designer; renders Hindi/Devanagari text (`राम कुमार`, `वाराणसी`) without question marks or character corruption.

### 10. MULTI-TENANT LICENSING & DOCTOR SEATS (REVISION 37)
1. **Clinic ID & User ID**: Clinic ID (e.g. `NX0001`) identifies the tenant; User ID (`ADM0001`) is unique per clinic.
2. **License Key Permanence**: License Key represents ongoing entitlement and is 100% permanent across renewals and doctor add-ons.
3. **Mid-Term Doctor Add-Ons**: Additional doctor seats (₹2,999/year per doctor) can be purchased mid-term via Superadmin. Existing license key remains unchanged; no activation codes or onboarding required.
4. **Effective Capacity**: Derived dynamically as Included Base (1) + Active Add-On Entitlements. Doctor profile creation is blocked when limit is reached.
5. **Authoritative Tenant Rule**: Authenticated `user.clinic_id` is the ONLY tenant context source. Headers/routes cannot override it.
6. **Dedicated Public Link**: Each clinic has canonical `/book/{public_slug}` and short `/b/{public_slug}` (e.g. `/book/sharmacare`) with absolute tenant isolation.

### 12. DIAGNOSTIC LAB OVERSIGHT & CONTROLLED RELEASE (`/lab`) — REVISION 36
1. **Clinical Worklist Oversight**: Monitor orders across `SAMPLE_PENDING`, `COLLECTED`, `RESULT_ENTERED`, `VERIFIED`, and `RELEASED`.
2. **Clinical Verification**: Admins and Doctors possess full authority to review structured findings (numeric, qualitative, text) and click **[Verify]** to certify results.
3. **Report Release & Public QR**: Click **[Release Report]** to generate the final diagnostic PDF and register the public QR verification record (`/verify-lab-report/{report_number}`).
4. **Controlled Result Corrections**: Post-release edits are strictly controlled. Modifying a released result requires a documented reason, archives the old version as `CORRECTED`, creates a new result version, and writes an immutable audit record.
5. **Zero Accounting Impact**: Specimen collection, result entry, verification, and report release create **zero journal entries**, zero ledger transactions, and zero alterations to existing diagnostic bills.
### 13. REAL-TIME LIVE QUEUE WINDOW (`/queue/live`)
1. **[Open Live Queue]**: Click the button from top navigation or Arrival Queue. Launches a dedicated, minimal, dark-themed secondary display window (`window.open`).
2. **Window Independence**: Closing or minimizing the Live Queue window has zero side effects on the operator session, cash drawer, or bookings. Reopen at any time to reload authoritative state.
3. **Queue Filtering**: Filter by Session (All, Morning, Evening) or Doctor. Doctor logins default strictly to their assigned queue.
4. **Realtime States**: Green `REALTIME CONNECTED` = WebSocket active; Amber `RECONNECTING` = reconnecting; Blue `FALLBACK REFRESH` = 6-second polling fallback.
5. **No Queue Mutation**: The window is 100% read-only. Creates zero tokens, zero appointments, zero financial transactions.

### 14. WHATSAPP AUTOMATION & UNIFIED TOKENS (`/settings/whatsapp`)
1. **Meta Cloud API**: Embedded Signup connects official Meta WhatsApp Business number. Credentials encrypted at rest (AES-256).
2. **Single Daily Token Series**: WhatsApp bookings share the exact same continuous token counter as walk-in (`CLINIC`) and PWA (`ONLINE`) bookings. No duplicate `whatsapp_tokens` table.
3. **Deterministic State Machine**: AI assists intent recognition and date extraction; backend rules strictly enforce clinic schedule, bookable sessions, and doctor availability.
4. **Idempotent Webhook**: Meta webhooks (`/api/v1/webhooks/whatsapp`) prevent duplicate processing via `whatsapp_webhook_events`.

### 15. DEVELOPER ATTRIBUTION & METADATA
- **Developer**: Kundan Kumar &bull; **Company**: Nexora Solutions, Varanasi, India
- **Contact**: +91 98765 43210 &bull; contact@nexorasolutionsind.co.in &bull; [https://nexorasolutionsind.co.in/](https://nexorasolutionsind.co.in/)

---
*DxClinic Platform Architecture Verified &bull; Master Contract Implementation Verified &bull; Copyright © 2026 DxClinic*


