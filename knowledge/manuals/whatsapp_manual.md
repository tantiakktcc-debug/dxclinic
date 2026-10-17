# WHATSAPP OPERATOR DESK & AI BOOKING MANUAL
**DxClinic Clinical Management System — Revision 49+**  
*Manual Type: Standard Operating Procedure & Integration Architecture | Version: Revision 49+ | Developer: Kundan Kumar, Nexora Solutions (https://nexorasolutionsind.co.in/) | Copyright © 2026 DxClinic*

---

## 1. EXECUTIVE SUMMARY & SYSTEM ROLE
The DxClinic WhatsApp integration provides a unified dual-channel conversational platform designed to operate alongside the visual Patient PWA (`/book/{public_slug}`) and the in-clinic Operator Reception Desk (`/operator/booking`).

- **Active Development Provider**: Baileys Gateway (Self-hosted on loopback `127.0.0.1:9090` via Cloudflare Tunnel `gateway.example.local`).
- **Future Production Provider**: Meta Cloud API (Embedded Signup v4 preserved).
- **Core Principle**: WhatsApp is purely a transport provider. Laravel remains the sole business authority for patient identity, booking eligibility, token allocation, doctor schedules, and audit trails.

---

## 2. OPERATOR DESK WORKSPACE (`/operator/whatsapp-desk`)
Authorized clinic staff (Admins and Operators) manage all incoming patient conversations via a native 3-pane interface located under:
**Left Sidebar &rarr; Daily Work &rarr; WhatsApp Desk**

### Workspace Layout & Header Identity Separation:
1. **Chat Conversation Header (Center Pane Top)**:
   The workspace explicitly displays transport and patient identities to avoid confusion:
   - **Clinic**: Current authenticated clinic name (e.g. `Demo Clinic` or `City Healthcare Clinic`).
   - **Provider**: Transport provider indicator (`BAILEYS` or `META`).
   - **Business WhatsApp**: The connected clinic phone line (e.g. `+91 99999 99999`).
   - **Patient Name**: Verified patient name or `Unregistered Contact`.
   - **Patient WhatsApp**: The patient customer sender number (e.g. `+91 9876543299`).
   - **Conversation State**: Current state machine node (`MENU`, `AWAITING_DOCTOR`, `HUMAN_HANDOFF`, `BOOKED`, `CLOSED`).

2. **Left Pane (Conversation Drawer)**:
   - Real-time conversation list strictly scoped to the authenticated operator's clinic (`clinic_id`).
   - Filters: **All**, **Needs Human**, **AI Active**, **Booked**, **Unread**, **Closed**.
   - Instant search by patient phone number, name, or message text.
   - Conversation timestamp displayed as relative time calculated from the authoritative clinic clock (`ClinicClock`).
   - Full date/time tooltip in India Standard Time (`Asia/Kolkata`, UTC+05:30).

3. **Center Pane (Chat Thread & Timeline)**:
   - Chronological message history with distinct visual bubbles:
     - 📥 **Inbound (Patient)**: Left-aligned white cards showing sender `👤 Patient (+91...)`.
     - 🤖 **AI Assistant**: Slate-dark cards indicating automated responses.
     - 👨‍💼 **Operator (Manual)**: Emerald-green cards displaying the staff member's name.
   - **Message Bubble Header**: Time formatted strictly in 12-hour IST format (e.g. `02:24 PM`) via `AppTimestampFormatter`.
   - **Message Bubble Footer**:
     - IST Date: e.g. `06 Oct`.
     - Honest Delivery Status: `Sent at 02:24 PM`, `Delivered at 02:24 PM`, `Delivered (time unavailable)`, `Read at 02:25 PM`, or `Pending`.
     - Hover Tooltip: Full sent, delivered, and read timestamps in `Asia/Kolkata` IST.
   - **Message Composer**: Direct manual text entry with Quick Canned Responses and Enter-to-send support.

4. **Right Pane (Patient Profile & Context Drawer)**:
   - Automatically matched patient profile (`DXP-XXXXX`, age, sex, mobile, address).
   - Recent clinic appointments and token numbers (`Token #001`), doctor, session, and status.
   - Quick jump buttons to OPD Arrival Queue and Live Queue Display.
   - Medical Safety Disclaimer regarding administrative use only.

---

## 3. AUTHORITATIVE TIMESTAMP ARCHITECTURE & TIMEZONE POLICY

### Authoritative Clock & Display Timezone
- **Authoritative Application Clock**: `App\Services\ClinicClock`
- **Application Timezone**: `Asia/Kolkata` (India Standard Time, UTC+05:30)
- **Database Storage Policy**:
  - MySQL database stores canonical UTC for all datetime and timestamp attributes (`created_at`, `updated_at`, `sent_at`, `delivered_at`, `read_at`).
  - No database server timezones or browser local Date objects are trusted as display authorities.
  - All clinic-facing display rendering is normalized strictly through `App\Services\AppTimestampFormatter`.

### Normalization Service (`WhatsAppTimestampNormalizer`)
Timestamps arriving from Baileys, Meta webhooks, or Node.js transports pass through `WhatsAppTimestampNormalizer::normalize()`:
- **Unit Detection**:
  - Values $\ge 10^{11}$ are classified as milliseconds (epoch ms) &rarr; divided by 1000 &rarr; UTC Carbon.
  - Values between $10^8$ and $10^{11}$ are classified as seconds (epoch sec) &rarr; converted directly to UTC Carbon.
  - ISO-8601 / RFC-3339 strings are parsed with timezone awareness and converted to UTC.
  - Protobuf Long structures (`toNumber()`, `{low, high}`) are cast safely without truncation.
- **Strict Anti-Heuristic Rule**: Never apply arbitrary `+5.5 hours` string math. All conversions use Carbon timezone transformation (`->setTimezone('Asia/Kolkata')`).

### Delivery Time Semantics & Honest Reporting
The system strictly distinguishes message lifecycle states:
1. **MESSAGE CREATED**: Instant application database persistence.
2. **MESSAGE SENT**: Provider transport dispatched (`sent_at`).
3. **MESSAGE DELIVERED**: Verified receipt from recipient (`delivered_at`).
4. **MESSAGE READ**: Read receipt confirmed by recipient (`read_at`).

**Honesty Invariant**: The application NEVER uses `created_at` to fabricate a delivery timestamp. If Baileys or Meta does not provide an explicit receipt timestamp, the UI honestly renders:
`Delivered (time unavailable)`

---

## 4. OPERATOR MANUAL MESSAGING & AI HANDOFF

### A. Taking Over from AI
When a patient query requires personalized attention or clinical discretion:
1. Operator clicks **[Take Over from AI]** or types a manual message.
2. The conversation transitions atomically to `HUMAN_HANDOFF`.
3. The AI Assistant is immediately suppressed to prevent duplicate or conflicting automated replies.
4. An immutable audit log (`WHATSAPP_OPERATOR_TAKEOVER`) is recorded with the operator's user ID and timestamp.

### B. Sending Manual Messages
1. Operator types text in the composer and clicks **[Send]** (or presses `Enter`).
2. Request passes strict tenant verification (Operator clinic ID == Conversation clinic ID).
3. Dispatched through `WhatsAppProviderInterface` &rarr; active provider (`BaileysProvider` or `MetaProvider`).
4. Message is persisted as `OUTBOUND` with sender type `OPERATOR` and audited in `AuditLog`.
5. Outbound messages start in state `SENT` with `delivered_at = null` until a real delivery receipt arrives.

### C. Resuming Automated AI
1. When operator assistance is concluded, click **[Resume AI Assistant]**.
2. Conversation state returns to `MENU`. Automated date/session tools and RAG replies are re-enabled.
3. Audited as `WHATSAPP_AI_RESUMED`.

---

## 5. AUTHORITATIVE BOOKING ELIGIBILITY & IST RULES
DxClinic enforces **identical domain rules across all channels** (Operator Desk, Patient PWA, WhatsApp Bot, and Telephony) using the central `BookingEligibilityService`:

| Rule | Enforcement Specification | Real-World Boundary |
| :--- | :--- | :--- |
| **Authoritative Timezone** | Server-side **Asia/Kolkata (IST)** | Client browser clocks are completely ignored. |
| **Morning Session Cutoff** | **11:00:00 IST** sharp | 10:59:59 &rarr; Potentially allowed; 11:00:00 &rarr; Strictly REJECTED. |
| **Evening Session Cutoff** | **19:00:00 IST** sharp | 18:59:59 &rarr; Potentially allowed; 19:00:00 &rarr; Strictly REJECTED. |
| **Same-Day Fallback** | Automatic fallback suggestion | If Morning is closed at 12:30 IST, bot automatically offers Evening. |
| **Clinic Advance Horizon** | `max_advance_booking_days` | Today + N days. Dates beyond horizon return `CLINIC_BOOKING_WINDOW_CLOSED`. |
| **Doctor Specific Horizon**| Effective: `MIN(clinic_max, doctor_max)`| Evaluated within doctor's `effective_from` &rarr; `effective_to` window. |
| **Doctor Availability** | Clinic Day + Session + Schedule + Leaves | Inactive doctors or doctors on approved leave are excluded. |
| **Shared Token Counter** | Unified daily sequence (`token_sequences`) | Reception, PWA, WhatsApp share `#001`, `#002`, `#003` monotonically. |
| **Token Retirement** | Non-reuse on cancellation / no-show | Cancelled tokens are never reassigned to subsequent patients. |

---

## 6. BAILEYS GATEWAY & DEVELOPMENT ENVIRONMENT
- **Network Binding**: Node.js Baileys Gateway listens strictly on loopback `127.0.0.1:9090` (never `0.0.0.0`).
- **Cloudflare Ingress**: Routed via dedicated tunnel hostname (e.g. `https://gateway.example.local`).
- **Developer Test Line**: Configurable via `WHATSAPP_DEVELOPMENT_TEST_PHONE` in `.env`.
- **HMAC Authentication**: Inbound webhooks and outbound commands are authenticated with HMAC-SHA256 signatures (`X-Gateway-Signature`), millisecond timestamps, nonces, and replay protection.
- **Status Updates**: Gateway listens to `messages.update` events for `DELIVERY_ACK` and `READ` receipts and relays them to Laravel's webhook endpoint for real-time status synchronization.
- **Ephemeral QR Codes**: QR pairing codes are generated in memory only. Never written to web-accessible directories (`public/` or `storage/app/public/`). Never leaked in logs.

---

## 7. META CLOUD API V4 PRESERVATION
The application preserves complete compatibility with Meta's official Embedded Signup:
- **Meta App ID**: Configured via `META_APP_ID` in `.env`
- **Embedded Signup Config ID**: Configured via `META_CONFIG_ID` in `.env`
- **Version**: `v4` (sessionInfoVersion 3, featureType `whatsapp_business_app_onboarding`)
- **Status**: Honestly reported as `CONFIGURED — LIVE META VERIFICATION PENDING` until live business verification is finalized.

---

## 9. REVISION 50 ENHANCEMENTS: SUPER ADMIN AUTH & TOKEN SLIP ARCHITECTURE

### A. Super Admin Authentication Boundary & Guard Isolation
- **Authentication Routes**:
  - `GET /superadmin/login` & `POST /superadmin/login`
  - `GET /superadmin/dashboard`
  - `POST /superadmin/logout`
- **Guard Isolation**:
  - Superadmin uses dedicated `developer` guard backed by `developer_users` table (`superadmin@dxclinic.local`).
  - Clinic staff use `web` guard backed by `users` table.
  - Zero cross-boundary redirect leaks: unauthenticated `/superadmin/*` requests redirect strictly to `/superadmin/login`; clinic requests redirect strictly to `/login`.
  - Intended redirect (`url.intended`) never cross-contaminates guards.

### B. WhatsApp Identity Normalization (`WhatsAppIdentityNormalizer`)
- **Canonical Identity Format**: Digits-only international E.164 number (e.g. `919123456789`).
- **Transport Separation**:
  - **Business Transport Account**: `919999999999` (`DEFAULT_BUSINESS_NUMBER`).
  - **Patient Sender Account**: e.g. `919123456789`.
  - Normalizes `@s.whatsapp.net`, `@c.us`, `@lid`, device suffixes (`:1`), and 10-digit Indian numbers (`9123456789` &rarr; `919123456789`).

### C. Unified Outbound Architecture & Token Slip Delivery
- **Pipeline**:
  `TokenSlipService` &rarr; `NotificationService` &rarr; `WhatsAppChannel` &rarr; `WhatsAppProviderInterface` (`BaileysWhatsAppProvider` / `MetaCloudWhatsAppProvider`) &rarr; Gateway / API.
- **Private Short-Lived Documents**:
  Token Slips are generated via cryptographically signed temporary routes (`URL::temporarySignedRoute('token.slip.private-download', ...)`), expiring in 15 minutes. Documents are NEVER permanently public.
- **Strict Multi-Role Authorization**:
  - **Superadmin / Clinic Admin**: Own clinic only.
  - **Operator**: Own clinic only.
  - **Doctor**: Own clinic AND assigned appointments only.
  - **Patient PWA**: Verified booking patient identity only.
- **Controlled Idempotency**:
  `TOKEN_SLIP:{appointment_id}:{recipient_phone}:{version}` prevents accidental double clicks while allowing explicit operator resends.
- **Non-blocking Transaction Invariant**:
  WhatsApp notification failure (`FAILED` / `RETRYABLE`) NEVER rolls back or alters the committed appointment or token state.

---

## 10. DEVELOPER & CANONICAL ATTRIBUTION
- **System**: DxClinic Clinical Suite
- **Lead Developer**: Kundan Kumar
- **Engineering Company**: Nexora Solutions
- **Location**: Varanasi, Uttar Pradesh, India
- **Canonical Website**: [https://nexorasolutionsind.co.in/](https://nexorasolutionsind.co.in/)
- **Support Contact**: +91 98765 43210 | contact@nexorasolutionsind.co.in

