# DxClinic — WhatsApp Integration & Live Queue Troubleshooting Guide

**Application Developer:** Kundan Kumar  
**Company:** Nexora Solutions, Varanasi, Uttar Pradesh, India  
**Contact:** +91 98765 43210 | contact@nexorasolutionsind.co.in | [https://nexorasolutionsind.co.in/](https://nexorasolutionsind.co.in/)  
**Target Environment:** Meta WhatsApp Cloud API (Graph API v21.0+)  

---

## 1. Webhook Handshake & Verification Issues (GET /api/v1/webhooks/whatsapp)

### Symptom 1: Meta displays "The URL couldn't be validated" (HTTP 403 Forbidden)
* **Root Cause 1: Token Mismatch.** The verify token entered in the Meta App Dashboard does not match `config('dxclinic.whatsapp.verify_token')` or the tenant's `webhook_verify_token`.
  - **Resolution:** Check `WHATSAPP_VERIFY_TOKEN` in `.env`. By default in DxClinic it is `dxclinic_verify_token_secure`. Ensure identical string in Meta dashboard.
* **Root Cause 2: Missing Query Parameters.** Meta requires `hub.mode=subscribe`, `hub.challenge=<string>`, and `hub.verify_token=<string>`.
  - **Resolution:** Verify URL routing accepts both dot (`hub.mode`) and underscore (`hub_mode`) notation. DxClinic's `WhatsAppWebhookController::verify` handles both automatically.
* **Root Cause 3: Non-SSL or Self-Signed Certificate.** Meta strictly rejects HTTP URLs and untrusted SSL certificates.
  - **Resolution:** Ensure the domain is served via valid trusted HTTPS (Let's Encrypt, Cloudflare, etc.).

---

## 2. Inbound Webhook Ingestion & Signature Verification (POST /api/v1/webhooks/whatsapp)

### Symptom 2: Webhook returns HTTP 401 {"error": "Invalid signature"}
* **Root Cause 1: App Secret Mismatch.** Meta signs every inbound POST using HMAC-SHA256 of the raw body keyed by the App Secret of the owning Meta App.
  - **Resolution:** Copy the exact App Secret from Meta App Dashboard > App Settings > Basic > App Secret. Set `WHATSAPP_APP_SECRET=<secret>` in `.env`.
* **Root Cause 2: Payload Tampering or Middleware Re-encoding.** If reverse proxies (Nginx/Apache) or Laravel middleware modify whitespace or parse/re-encode the raw JSON payload before signature verification, the hash will fail.
  - **Resolution:** `WhatsAppWebhookController` retrieves the raw unparsed body via `$request->getContent()` before any JSON decoding or parameter trimming occurs.

### Symptom 3: Webhook returns HTTP 413 {"error": "Payload too large"}
* **Root Cause:** Inbound payload exceeds the 512 KB security threshold.
  - **Resolution:** Standard WhatsApp messaging events are < 10 KB. Payloads > 512 KB are blocked to prevent Denial of Service memory exhaustion.

### Symptom 4: Webhook returns HTTP 200 but message rejected with "Unregistered WhatsApp connection"
* **Root Cause:** The `phone_number_id` inside `entry[].changes[].value.metadata.phone_number_id` does not match any row in `whatsapp_connections`.
  - **Resolution:** In DxClinic Admin (`/settings/whatsapp`), verify that **Phone Number ID** matches the exact 15-digit ID in Meta WhatsApp Business Account Phone Numbers list.

### Symptom 5: Message rejected with "WhatsApp connection is disabled or disconnected"
* **Root Cause:** The clinic's WhatsApp connection is in `DISCONNECTED` or `DISABLED` status.
  - **Resolution:** In `/settings/whatsapp`, click **Connect WhatsApp** to re-activate the connection. Disconnection halts new webhook automation but preserves all historical data.

---

## 3. Token Allocation & Concurrency Invariants

### Symptom 6: "Token sequence gap" observed (e.g. 003, 004, 006)
* **Root Cause: Expected Business Invariant.**
  - **Crucial Rule:** Tokens are **unique, monotonic, and never reused**.
  - If token `005` was booked and then marked `CANCELLED` or `ABSENT`, that token is permanently retired to ensure audit integrity and prevent counterfeit tokens.
  - **Resolution:** This is correct platform behavior. Do NOT attempt to recycle or reuse cancelled tokens.

### Symptom 7: Multiple simultaneous bookings hit unique constraint
* **Root Cause:** Simultaneous requests attempting initial creation of `token_sequences` row for a new date/session.
  - **Resolution:** `TokenAllocationService::allocateToken()` implements exclusive row-level locking (`lockForUpdate()`) and catches `QueryException` on initial insertion collisions to seamlessly retry with the established row lock.

---

## 4. Outbound Delivery Failures & Queue Workers

### Symptom 8: Appointments are created but patient receives no WhatsApp confirmation
* **Root Cause 1: Queue Worker Inactive.** `SendWhatsAppMessageJob` is asynchronous and requires the queue worker to process jobs.
  - **Resolution:** Ensure `php artisan queue:work` or the supervisor daemon is running. Check `failed_jobs` table.
* **Root Cause 2: Expired Access Token.** If using a temporary 24-hour Graph API explorer token, it expires automatically.
  - **Resolution:** Generate a **permanent System User Access Token** in Meta Business Manager.
* **Root Cause 3: 24-Hour Customer Care Window.** Meta allows free-form session messages only within 24 hours of the patient's last inbound message.
  - **Resolution:** Outside the 24-hour window, outbound messages must use an approved Meta Message Template.

---

## 5. Live Queue Window Issues

### Symptom 9: Live Queue shows "OFFLINE" or doesn't update in real time
* **Root Cause 1: WebSockets / Reverb / Pusher Disconnected.**
  - **Resolution:** DxClinic automatically falls back to polling via Livewire `wire:poll.5s` when WebSockets are unavailable. Live Queue remains 100% operational in polling mode.
* **Root Cause 2: Cross-Clinic 403 Forbidden Error.**
  - **Resolution:** Live Queue enforces strict server-side tenant isolation. Users can only view queues belonging to their authenticated clinic ID.

### Symptom 10: Doctor cannot see other doctors' patients
* **Root Cause: Intentional Role Isolation.**
  - **Resolution:** Users with the `DOCTOR` role are strictly restricted to their own appointments (`$user->doctor_id`). Only `ADMIN` and `OPERATOR` roles may view clinic-wide multi-doctor queues.

---

## 6. Emergency Safe Disconnect Procedure

If an operator or admin needs to immediately stop automated WhatsApp responses (e.g., during emergency clinic closure or maintenance):

1. Go to `/settings/whatsapp`.
2. Click **Disconnect**.
3. **What Happens:**
   - Future incoming webhooks return `rejected` status.
   - Outbound jobs halt.
   - **Zero Data Loss:** All patient records, appointments, tokens, receipts, and clinical entries are 100% preserved.
4. To resume service, click **Connect WhatsApp** and confirm credentials.
