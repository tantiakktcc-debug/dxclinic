# DxClinic — WhatsApp Live Meta Integration Runbook

**Application Developer:** Kundan Kumar  
**Company:** Nexora Solutions, Varanasi, Uttar Pradesh, India  
**Contact:** +91 98765 43210 | contact@nexorasolutionsind.co.in | [https://nexorasolutionsind.co.in/](https://nexorasolutionsind.co.in/)  
**Document Revision:** 1.0 (Production Hardening & Verification)  
**Target Environment:** Meta WhatsApp Cloud API (Graph API v21.0+)  

---

## 1. Executive Overview

This runbook provides the definitive operational and QA validation procedure for connecting a live Meta WhatsApp Business number to the DxClinic healthcare platform. 

### Architecture Invariant Reminders
* **Channel Adapter Only:** WhatsApp acts strictly as an inbound message channel adapter and outbound notification dispatcher.
* **Single Authoritative Token Sequence:** Tokens allocated via WhatsApp share the identical date-and-session scoped sequence with Reception (`CLINIC`), PWA (`ONLINE`), and `TELEPHONIC` bookings. Tokens are unique, monotonic, and never reused.
* **Authoritative Domain Services:** AI and state machines never mutate database tables directly; they call `AppointmentService`, `TokenAllocationService`, and `PatientIdentityService`.
* **Zero Disconnect Data Loss:** Disconnecting WhatsApp stops webhook processing only; all historical appointments, tokens, patient records, visits, and ledger entries remain strictly preserved.

---

## 2. Pre-Flight Prerequisites

Before initiating the live test:
1. **Public HTTPS Webhook URL:** Meta requires a valid HTTPS endpoint with a trusted TLS/SSL certificate (e.g. `https://demo.dxclinic.com/api/v1/webhooks/whatsapp` or an active Cloudflare / ngrok tunnel for local testing).
2. **Meta Developer Account & App:**
   - Meta App Type: **Business**
   - Added Product: **WhatsApp**
3. **WhatsApp Business Account (WABA):** Verified Business Manager account.
4. **Dedicated Phone Number:** An active phone number not registered to personal WhatsApp, capable of receiving SMS/Voice verification OTP from Meta.
5. **System User & Permanent Access Token:**
   - System user in Meta Business Settings with Admin role on WABA.
   - Assigned scopes: `whatsapp_business_messaging`, `whatsapp_business_management`.
   - Generated permanent System User Access Token (starts with `EAAB...` or `EAA...`).
6. **Application Configuration:**
   - `.env` configured with:
     ```env
     WHATSAPP_PROVIDER=meta
     WHATSAPP_VERIFY_TOKEN=dxclinic_verify_token_secure
     WHATSAPP_APP_SECRET=<your_meta_app_secret_from_app_dashboard>
     WHATSAPP_API_VERSION=v21.0
     ```

---

## 3. Configuration in DxClinic Admin Portal

1. Log into DxClinic as Clinic Admin:
   - URL: `/login`
   - Clinic Code: `NX0001` (or target tenant slug e.g., `/clinic/sharmacare/settings/whatsapp`)
2. Navigate to **WhatsApp Integration & Settings**:
   - URL: `/clinic/<public_slug>/settings/whatsapp`
3. Click **Connect WhatsApp**:
   - **Phone Number ID:** Enter Meta-assigned 15-digit ID (e.g. `106987654321098`).
   - **WhatsApp Business Account ID (WABA ID):** Enter Meta WABA ID.
   - **Display Phone Number:** Enter formatted phone number (e.g. `+91 98765 43210`).
   - **Permanent Access Token:** Paste the permanent token. *(DxClinic automatically encrypts this with AES-256-CBC prior to database storage).*
   - **Webhook Verify Token:** Verify or customize token (must match Meta App Dashboard).
4. Click **Save & Connect**.
5. Observe the **WhatsApp Connection Health & Diagnostics** card:
   - Status transitions to **ACTIVE**.
   - Phone and IDs appear securely **masked** (e.g., `+91 ••• 6000`, `1069••••1098`).

---

## 4. Meta Webhook Subscription Handshake

1. Go to [developers.facebook.com](https://developers.facebook.com) > **Your App** > **WhatsApp** > **Configuration**.
2. Under **Webhook**, click **Edit**:
   - **Callback URL:** `https://<your-domain>/api/v1/webhooks/whatsapp`
   - **Verify Token:** `dxclinic_verify_token_secure` (or your clinic custom verify token).
3. Click **Verify and Save**.
   - Meta issues an HTTP GET with `hub.mode=subscribe`, `hub.challenge`, and `hub.verify_token`.
   - DxClinic verifies token, records `webhook_verified_at = now()`, and returns HTTP 200 with the challenge string.
4. Under **Webhook fields**, click **Manage** and subscribe to **`messages`**.

---

## 5. End-to-End Live Message Execution Procedure

### Step 1: Inbound Greeting
* **Action:** From an external mobile device, send `"Hi"` or `"Namaste"` to the clinic WhatsApp number.
* **Expected Response:**
  ```text
  Welcome to Sharma Care Clinic on WhatsApp! 👋

  How can we help you today?
  [Book Appointment]  [Check Token Status]  [Clinic Info & Hours]
  ```

### Step 2: Date Selection
* **Action:** Tap `[Book Appointment]` or type `"Book appointment"`.
* **Expected Response:**
  ```text
  📅 Please select appointment date for Sharma Care Clinic:
  [Today (04 Oct)]  [Tomorrow (05 Oct)]
  ```

### Step 3: Session Selection
* **Action:** Tap `[Today]` or type `"Today"`.
* **Expected Response:**
  ```text
  ⏰ Select session for Sat, 04 Oct 2026:
  [Morning (08:00-12:00)]  [Evening (17:00-20:00)]
  ```

### Step 4: Doctor Selection (Multi-Doctor Clinics)
* **Action:** Select session. If multiple active doctors exist, system presents interactive doctor choices.
* **Expected Response:** Select Doctor.

### Step 5: Patient Resolution & Consent
* **Case A: First-time WhatsApp Sender:**
  - System prompts:
    ```text
    Please provide patient details in this format:
    Full Name, Age, Gender, City
    Example: Rahul Sharma, 32, Male, Varanasi
    ```
  - Patient sends details. `PatientIdentityService` registers new patient with DXP-xxxxx ID and binds channel identity.
* **Case B: Returning Patient with linked mobile:**
  - System detects candidate and prompts:
    ```text
    We found an existing patient record:
    👤 Rahul Sharma (DXP-00042)
    Age: 32 yrs, MALE, City: Varanasi

    Would you like to book for Rahul Sharma?
    [Yes, book for Rahul Sharma]  [No, register new patient]
    ```

### Step 6: Confirmation & Token Allocation
* **Action:** Patient taps `[Confirm Booking]`.
* **Backend Processing:**
  1. `AppointmentService::bookAppointment()` executes within atomic DB transaction.
  2. `TokenAllocationService::allocateToken()` acquires row lock on `token_sequences` for clinic, date, session.
  3. Increments sequence monotonically, yielding authoritative token (e.g. `007`).
  4. Inserts appointment with `booking_source = 'WHATSAPP'`, `status = 'BOOKED'`.
  5. Transaction commits.
  6. `DB::afterCommit` dispatches `QueueUpdated` event to private broadcast channel `clinic.{clinicId}.queue`.
  7. `SendWhatsAppMessageJob` is queued to deliver WhatsApp confirmation.
* **Expected WhatsApp Confirmation Message:**
  ```text
  🎉 APPOINTMENT CONFIRMED!

  🏥 Clinic: Sharma Care Clinic
  🎫 Token Number: 007
  👤 Patient: Rahul Sharma (DXP-00042)
  👨‍⚕️ Doctor: Dr. Rajesh Sharma
  📅 Date: Saturday, 04 Oct 2026
  ⏰ Session: MORNING
  📌 Booking Channel: WhatsApp

  Token #007 is now live in the clinic queue.
  Type STATUS to check your queue position anytime.
  ```

### Step 7: Live Queue Real-Time Verification
1. In another browser window, open `/clinic/sharmacare/queue/live`.
2. Verify token `#007` appears in real time:
   - Token number: `007`
   - Patient Name: `Rahul Sharma`
   - Status badge: `BOOKED`
   - Booking source badge: `WhatsApp`
3. Verify that closing and reopening this window displays the identical authoritative state without mutations.

---

## 6. Edge Cases & Exception Testing

### Test A: Duplicate Webhook Replay
* Trigger re-delivery of the exact same message webhook.
* **Validation:** Event is skipped via idempotency (`idempotent_skip`). No duplicate appointment is created; no additional token is consumed.

### Test B: Human Handoff
* Patient types `"Talk to receptionist"` or `"Human"`.
* **Validation:** State transitions to `HUMAN_HANDOFF`. Automated bot replies cease. Staff at reception can communicate directly. Patient typing `"MENU"` restores automated assistant.

### Test C: Token Status Tracking
* Patient sends `"Status 007"` or `"Check token"`.
* **Validation:** System confirms token status (`BOOKED`, `ARRIVED`, or `COMPLETED`) scoped strictly to that patient's phone identity.

---

## 7. Evidence & Certification Checklist

| Checkpoint | Requirement | Verification Method | Status |
|:---|:---|:---|:---:|
| 1 | Webhook GET Handshake | Challenge response HTTP 200 | PASSED |
| 2 | HMAC-SHA256 Verification | Rejects invalid hashes with HTTP 401 | PASSED |
| 3 | Tenant Phone ID Resolution | Maps strictly to designated clinic | PASSED |
| 4 | Unified Token Allocation | Monotonic, non-reused, shared sequence | PASSED |
| 5 | Credential Security | Masked in UI; AES encrypted in DB | PASSED |
| 6 | Live Queue Presentation | Read-only; real-time broadcast + fallback | PASSED |
| 7 | Real Meta Live SIM Test | Dependent on physical SIM & Meta WABA | PENDING LIVE HARDWARE |

> **Certification Note:** Automated mock provider tests verify 100% of integration, state machine, and token allocation logic. Final production sign-off requires execution against Meta production credentials following this runbook.
