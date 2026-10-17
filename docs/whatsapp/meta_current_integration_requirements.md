# Meta WhatsApp Cloud API — Production Integration Requirements & Guidelines

**Application**: DxClinic Clinical Management & OPD System  
**Document**: `docs/whatsapp/meta_current_integration_requirements.md`  
**Date Verified**: 2026-10-04  
**Graph API Target**: Meta Graph API v21.0+  
**Implementation Developer**: Kundan Kumar &bull; Nexora Solutions, Varanasi, India  

---

## 1. Executive Overview & Official References

DxClinic interfaces with Meta's official **WhatsApp Cloud API** hosted on Meta's global infrastructure. WhatsApp serves strictly as an automated booking and communication adapter. It flows into DxClinic's core `AppointmentService`, `TokenAllocationService`, and `PatientIdentityService`.

### Official Meta Documentation Sources
1. **WhatsApp Cloud API Overview & Getting Started**:  
   [https://developers.facebook.com/docs/whatsapp/cloud-api](https://developers.facebook.com/docs/whatsapp/cloud-api)
2. **Meta Embedded Signup for Tech Providers / Solution Partners**:  
   [https://developers.facebook.com/docs/whatsapp/embedded-signup](https://developers.facebook.com/docs/whatsapp/embedded-signup)
3. **Webhook Setup & Event Ingestion Reference**:  
   [https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks](https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks)
4. **Cloud API Messages Reference (Sending Messages & Templates)**:  
   [https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages](https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages)
5. **Business Management API (WABA & Asset Sharing)**:  
   [https://developers.facebook.com/docs/whatsapp/business-management-api](https://developers.facebook.com/docs/whatsapp/business-management-api)

---

## 2. Meta App & Business Asset Configuration

To connect live clinics to WhatsApp Cloud API, the following Meta developer prerequisites are required:

### 2.1 Meta Business Manager
- A verified **Meta Business Account** (Business Manager).
- Completed **Business Verification** (uploading official business registration, e.g., GST certificate, MSME, or Clinic Clinical Establishment Act license).

### 2.2 Meta Developer App
- An App of type **Business**.
- Added Product: **WhatsApp**.
- Required Scopes / Permissions:
  - `whatsapp_business_management`: To manage WABA, fetch phone number IDs, and configure templates.
  - `whatsapp_business_messaging`: To send and receive conversational WhatsApp messages.

### 2.3 System User & Permanent Token Generation
- For production multi-tenant or server-side automation, standard 60-day user tokens are unsuitable.
- **Requirement**: Create a **System User** (Role: Admin or Employee with WhatsApp asset access) in Meta Business Manager.
- Generate a **System User Access Token** with permanent lifetime (`never expires`), storing the encrypted token in `whatsapp_connections.access_token_encrypted`.

---

## 3. Phone Number Onboarding & Coexistence Constraints

### 3.1 Dedicated Phone Number Rule
- A phone number connected to the WhatsApp Cloud API **cannot simultaneously be registered** on the standard consumer WhatsApp app or the WhatsApp Business mobile app on a smartphone, unless using Meta's Cloud API Coexistence beta (limited availability).
- **Standard Onboarding**:
  1. The clinic must delete their existing WhatsApp account from the physical mobile phone:  
     *Settings &rarr; Account &rarr; Delete My Account*.
  2. The number then becomes available to receive a verification OTP (SMS or Voice Call) during Meta Embedded Signup.

### 3.2 Display Name Approval
- Each clinic phone number requires a **Display Name** compliant with Meta's Commerce and WhatsApp Display Name guidelines (e.g. *"Sharma Care Clinic"*).
- The display name must match legal or public signage documentation.

---

## 4. Webhook Configuration & Handshake Protocol

### 4.1 Verification Endpoint (GET)
- **URL**: `https://{domain}/api/v1/webhooks/whatsapp`
- **Query Parameters**:
  - `hub.mode`: Must be string `'subscribe'`
  - `hub.verify_token`: Cryptographic token (configured per connection or globally via `WHATSAPP_VERIFY_TOKEN`)
  - `hub.challenge`: Plain text random challenge string
- **Response**: The endpoint must echo back the exact `hub.challenge` string with HTTP 200 and `Content-Type: text/plain`.

### 4.2 Signature Verification (POST)
- **Header**: `X-Hub-Signature-256`
- **Format**: `sha256={hmac_hash}`
- **Verification Rule**: Compute `hash_hmac('sha256', $rawPayload, $metaAppSecret)`. Compare using `hash_equals()` to prevent timing attacks.
- If signature check fails, reject immediately with **HTTP 401 Unauthorized**.

### 4.3 Webhook Payload Schema
Inbound events arrive with the following structure:
```json
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "{waba_id}",
      "changes": [
        {
          "value": {
            "messaging_product": "whatsapp",
            "metadata": {
              "display_phone_number": "+91 98765 43210",
              "phone_number_id": "109876543210002"
            },
            "contacts": [
              {
                "profile": { "name": "Rahul Kumar" },
                "wa_id": "919876543210"
              }
            ],
            "messages": [
              {
                "from": "919876543210",
                "id": "wamid.HBgLMTEyMjMzNDQ1NQ==",
                "timestamp": "1728045600",
                "type": "text",
                "text": { "body": "Book appointment tomorrow morning" }
              }
            ]
          },
          "field": "messages"
        }
      ]
    }
  ]
}
```

---

## 5. Security & Multi-Tenant Boundary Demarcation

1. **Tenant Resolution via `phone_number_id`**: Incoming webhooks derive `clinic_id` strictly from `whatsapp_connections.phone_number_id`. Message body, patient text, or query parameters are never trusted to establish clinic identity.
2. **Double Idempotency Guard**:
   - `whatsapp_webhook_events` stores external `wamid...` message IDs with a unique constraint. Duplicate deliveries are recorded as `DUPLICATE` and bypassed.
   - The booking command itself is idempotent, returning committed appointment details on repeated invocations without allocating duplicate tokens.
3. **Outbound Asynchronous Decoupling**: Outbound WhatsApp confirmations dispatch asynchronously via queued jobs (`SendWhatsAppMessageJob`) **strictly after** database transaction commit. If WhatsApp delivery fails, the appointment and token remain 100% valid in the database.

---

## 6. Live Demo Clinic Test Runbook

When live Meta production credentials become available, execute the following verification steps:
1. Configure `.env`:
   ```env
   WHATSAPP_APP_SECRET=your_meta_app_secret
   WHATSAPP_VERIFY_TOKEN=your_custom_verify_token
   WHATSAPP_ACCESS_TOKEN=your_permanent_system_user_token
   ```
2. In Meta App Dashboard &rarr; WhatsApp &rarr; Configuration:
   - Callback URL: `https://your-domain.com/api/v1/webhooks/whatsapp`
   - Verify Token: Matches `WHATSAPP_VERIFY_TOKEN`
   - Webhook Fields: Subscribe to `messages`.
3. Send a test message from a real WhatsApp mobile app: `"Hello"`.
4. Follow the interactive state machine prompts to complete an appointment booking.
5. Verify that:
   - Appointment is created in `appointments`.
   - Token is allocated in `token_sequences`.
   - The token appears instantaneously in the **Live Queue Window** (`/queue/live`).
   - A confirmation message arrives on the patient's WhatsApp mobile screen.
