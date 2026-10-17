# DxClinic Superadmin Fleet Control Manual

## 1. Overview & Platform Architecture
The Superadmin subsystem operates on a dedicated authentication guard (`developer`) completely separated from tenant clinics. Superadmins have fleet-wide administrative oversight to provision clinics, issue licenses, expand doctor capacities, configure subscription pricing, manage lifecycle events, and audit operations across all tenant clinics.

```
                    SUPERADMIN
                         |
                  Platform Layer
                         |
              Subscription / License
                         |
                  Clinic NX0001 / NX0002 ...
                         |
        +----------------+----------------+
        |                |                |
      Users            Doctors         Patients
        |                |                |
     Admin            Fees/Schedule   Appointments
     Operator         Consult Types  Visits
     Doctor           Templates      Billing
```

---

## 2. Superadmin Authentication & Guard Isolation
- **URL:** `/superadmin/login`
- **Guard:** `developer` (maps to `developer_users` table).
- **Isolation:** Normal tenant staff (`web` guard) attempting to access `/superadmin/*` are strictly blocked and redirected to the login gate. Superadmins are NOT bound to tenant context.

---

## 3. Fleet Dashboard Overview (`/superadmin/dashboard`)
The fleet dashboard provides real-time operational telemetry across all clinics:
- **Total Clinics:** Active count of registered facilities.
- **Active Licenses:** Clinics with valid, active entitlements.
- **Pending Activation:** Newly provisioned clinics that have not yet consumed their activation code.
- **Expired Licenses:** Clinics whose subscription term has elapsed.
- **Suspended Licenses:** Clinics temporarily disabled by administrative action.
- **Platform Net Billed:** Cumulative subscription revenue.
- **Doctor Capacity Metrics:** Displaying used vs total allowed doctor seats, included seats, and paid add-on seats.

---

## 4. Issuing a New Clinic License (`/superadmin/licenses/issue`)
When provisioning a new clinic:
1. **Clinic Facility Information:** Name, city, phone, email, address, advance booking window, family mobile limit (default 4).
2. **Initial Administrator Account:** Full name, email, mobile. Login ID is automatically assigned (`ADM0001`).
3. **Subscription Entitlement & Seats:**
   - **Plan:** Select term (e.g. Monthly, Quarterly, Half-Yearly, Yearly, 3 Years, 5 Years).
   - **Included Doctor Seats:** Default = 1 seat.
   - **Additional Doctor Seats:** Paid add-on seats.
   - **Additional Doctor Fee:** Default = ₹2,999.00 / doctor / year (server-authoritative, configurable).
   - **Discount Amount:** Optional billing reduction.
   - **Start Date:** Automatically defaults to today or custom date.
4. **Automated Provisioning:**
   - Next sequential Clinic ID is assigned atomically (e.g., `NX0002`).
   - Secure License Key (`NXCL-XXXX-XXXX-XXXX-XXXX`) and one-time Activation Code (`NXA-XXXX-XXXX`) are generated.
   - Initial Admin account is created with `must_change_password = true`.
   - Onboarding Credential Package is produced for printing or secure handover.

---

## 5. Mid-Term Doctor Seat Add-On Licensing (Revision 37)
Each clinic operates under an entitlement-based doctor capacity:
$$\text{Total Doctor Capacity} = \text{Base Included Seats (1)} + \sum \text{Active Doctor Seat Add-On Entitlements}$$

### 5.1 Purchasing Additional Doctor Seats Mid-Term
Clinics with an active license may purchase additional doctor seats during an ongoing subscription period:
- **This is NOT a license renewal:** No new `licenses` record is created, and parent license expiration dates remain unchanged.
- **Permanent License Key Unchanged:** The clinic's existing permanent license key remains 100% unchanged.
- **No Activation / Onboarding:** No activation codes are generated, and clinic staff experience zero operational disruption.
- **Yearly Billing Only:** Doctor add-ons are billed separately on an annual cycle (default: ₹2,999/year per doctor).
- **Entitlement Validity:** Exactly one year (`valid_from = payment_date`, `valid_until = valid_from + 1 year - 1 day`).
- **Parent License Dependency:** When the parent clinic license expires or is suspended, the clinic is locked regardless of active doctor add-ons. Doctor add-ons do not grant standalone clinic access.

### 5.2 Superadmin Workflow
1. Click **[+ Doctor Seat]** on the clinic's row in the Fleet Dashboard.
2. The modal displays:
   - Clinic ID & Clinic Name
   - Current Plan & License Valid Until
   - Base Included Seats & Active Paid Add-on Seats
   - Current Effective Doctor Capacity
3. Enter **Number of Additional Doctors** (price updates dynamically: $N \times ₹2,999/\text{year}$).
4. Select **Payment Method** (`UPI`, `BANK_TRANSFER`, `CASH`, `CHEQUE`, `OTHER`), provide Payment Reference (UTR), Payment Date, and optional audit notes.
5. Click **[Confirm Payment & Add Doctor Seat]**.
6. The action is atomic and idempotent. Capacity expands immediately, and a durable entitlement is saved in `clinic_doctor_seat_addons`.

---

## 6. Superadmin One-Click Paid License Renewal (`/superadmin/clinics/{id}/renew`)
Revision 35 introduces the frozen business workflow for paid license renewals:

### 6.1 Business Workflow
1. The clinic owner pays the software provider directly outside DxClinic (Cash, UPI, NEFT/RTGS Bank Transfer, Cheque, or Other).
2. After payment is received and confirmed, **ONLY the Superadmin** performs the renewal inside DxClinic.
3. The clinic administrator and staff require **ZERO ACTIONS**:
   - No new license key
   - No activation code
   - No reactivation wizard
   - No re-onboarding
   - No credential changes
4. Subscription status becomes `ACTIVE` immediately upon Superadmin confirmation.

### 6.2 Search & Fleet Discovery
Superadmins can quickly find any clinic using the normalized search bar:
- By **Clinic ID** (e.g. `NX0001`, `NX0002`)
- By **Clinic Facility Name** (e.g. `Sharma Care Clinic`)
- By **Registered Mobile / Phone** (e.g. `9876543210`)
- By **Clinic Owner / Administrator Name** (e.g. `Clinic Admin`, `Dr. Rajesh Sharma`)

### 6.3 Dedicated Renewal Screen (`/superadmin/clinics/{id}/renew`)
Clicking **[Renew License]** on any clinic row opens the dedicated renewal screen displaying:
- Current Plan, Valid From, Valid Until, and remaining days calculated in authoritative `Asia/Kolkata` (IST) timezone.
- Current doctor capacity: base included seat (1), currently paid additional seats, and actively assigned doctors.

### 6.4 Renewal Plan & Term Coverage
Superadmins select the purchased renewal term:
- **Quarterly Plan:** 3 Months coverage
- **Half-Yearly Plan:** 6 Months coverage
- **Yearly Plan:** 12 Months coverage
- **3-Year Plan:** 36 Months coverage
- **5-Year Plan:** 60 Months coverage
*(Trial plans are strictly excluded from renewals).*

### 6.5 Continuous Active Service vs. Lapsed Renewal Date Logic
The system enforces strict business date calculations:
- **Active License Extension:** If the current license is active with remaining days, the new period begins strictly on `existing_end_date + 1 day` (`start_date = current_end_date + 1`), preserving all unused days.
- **Expired or Suspended License Renewal:** If the license has expired or is currently suspended, the new period begins on the **Payment Date** (`start_date = payment_date`), restoring the clinic immediately to `ACTIVE` status.

### 6.6 Reconciliation with Active Mid-Term Add-Ons (Revision 37)
To prevent accidental double-billing of mid-term doctor seats during renewal:
- The renewal engine automatically detects active doctor add-ons whose validity extends into the new license period.
- Active mid-term entitlements are credited toward total capacity without incurring double renewal charges.

### 6.7 Idempotency & Payment Confirmation
- **Double-Click Protection:** Every renewal form generates a cryptographically random `idempotency_key` stored in a hidden field. Submitting twice or clicking rapidly returns the existing transaction safely without double-charging or creating duplicate license periods.
- **Payment Verification:** Superadmin records Payment Method (`CASH`, `UPI`, `BANK_TRANSFER`, `CHEQUE`, `OTHER`), External Payment Reference (UTR / Cheque #), Payment Date, and optional administrative notes.
- **Revoked License Protection:** Revoked or cancelled licenses cannot be renewed through the standard workflow and present an explicit warning banner directing superadmins to reinstatement procedures.

---

## 7. Fleet Auditable Renewal History (`/superadmin/license-history`)
Every paid renewal generates an immutable record in the `license_renewals` table and an entry in `license_events`:
- Full financial breakdown: Base fee, Doctor seat renewal fee, New doctor seat fee, Discount, and Net Paid.
- Complete coverage period: Previous expiry date, New expiry date, and Total duration months.
- Payment audit trail: Payment method, Reference UTR, Payment date, and Superadmin user ID who authorized the renewal.
- Clinic-specific historical timeline view is accessible at `/superadmin/clinics/{id}/history`.

---

## 8. Permanent Demo Tenant Management (Revision 37 & 38)
DxClinic provides a permanent, isolated demonstration clinic (`democlinic`) designed for prospect evaluations, walk-throughs, and public testing without polluting customer data.

### 8.1 Demo Clinic Specifications
- **Clinic ID:** `democlinic`
- **Clinic Name:** `Demo Clinic`
- **Doctor Hardcap:** Strictly 2 Doctor seats. Additional seats cannot be purchased via UI or API.
- **Public Booking PWA:** `https://dxclinic.example.local/book/democlinic` (or `/b/democlinic`).
- **Initial Credentials:**
  - **Admin:** `demoadmin` / `DemoAdmin123!`
  - **Operator:** `demooperator` / `DemoOperator123!`
  - **Doctor 1:** `demodoctor01` / `DemoDoctor01!` (Dr. Amit Verma — General Medicine)
  - **Doctor 2:** `demodoctor02` / `DemoDoctor02!` (Dr. Neha Kapoor — Family Medicine)
  - *Note: `demodoctor` / `DemoDoctor123!` is preserved as a backward-compatible alias for Doctor 1.*

### 8.2 Demo Controls on Fleet Dashboard
- **[Pause Demo]:** Temporarily halts the demonstration environment.
  - Staff logins are blocked.
  - Public PWA returns a friendly HTTP 503 screen (*"The DxClinic demonstration environment is temporarily unavailable. Please try again later."*).
- **[Resume Demo]:** Restores full access to staff logins and public booking.
- **[Reset Demo Data]:** Atomically wipes transactional data in `democlinic` and re-seeds the deterministic fictional clinical dataset (patients, appointments, vitals, bills, payments). Real customer clinics are 100% untouched.

---

## 9. Revision 38 Platform Hardening & Multi-Tenant Data Isolation
- **Global Doctor Name Normalization:** Centralized presenter `DoctorNamePresenter::format()` normalizes doctor names without duplicate prefixes like "Dr. Dr.". Database stores clean canonical names (`Rajesh Sharma`, `Amit Verma`).
- **Standardized Consultancy Terminology:** Customer-facing terminology is strictly `Clinic Consultancy`, `Online Consultancy`, and `Visiting Consultancy`. Internal enums (`CLINIC_PHYSICAL`, `ONLINE`, `VISITING`) remain unchanged for database stability.
- **Strict Clinic-Scoped Fee Resolution:** Fee queries are verified against the active clinic. Doctor-specific overrides only apply if the doctor belongs to the target clinic (`$doctor->clinic_id === $clinicId`).
- **5-Year Plan License Date Rule:** 5-Year plans are strictly defined as 60 months (`duration_months = 60`). Expiration and remaining days are calculated mathematically against the real `valid_until` date in `Asia/Kolkata` (producing ~1,825–1,826 days remaining from issuance, zero faking).

---

## 10. Platform Sales, Invoicing & Commercial Accounting (Revision 42)

### 10.1 Commercial Architecture & Single Source of Truth
Revision 42 establishes a comprehensive commercial invoicing and financial analytics engine. The platform strictly enforces a **single source of truth** architecture to prevent duplicate financial records:
- **Initial License Sales:** Authoritative truth in `licenses` (`paid_amount`, `payment_method`, `payment_reference`, `payment_date`).
- **License Renewals:** Authoritative truth in `license_renewals` (`net_amount`, `payment_method`, `payment_reference`, `payment_date`).
- **Mid-Term Doctor Add-Ons:** Authoritative truth in `clinic_doctor_seat_addons` (`price_paid`, `payment_method`, `payment_reference`, `payment_date`).
- **Platform Overhead Expenses:** Authoritative truth in `platform_expenses` (`amount`, `category`, `expense_date`, `payment_method`).

**Strict Rule:** No duplicate `platform_payments` table exists. All reporting derives directly from these primary commercial event tables.

### 10.2 Sales Analytics Dashboard (`/superadmin/sales`)
The executive sales dashboard tracks commercial performance across all clinics:
- **Financial KPIs:**
  - **Total Sales (Gross):** Cumulative gross billed value of base licenses and doctor capacity expansions.
  - **Net Sales:** Gross sales minus commercial discounts.
  - **Collected Revenue:** Total funds received and verified via Cash, UPI, Bank Transfer, or Cheque.
  - **Outstanding Balance:** Billed net amounts pending payment settlement.
  - **Refunds:** Total issued credit adjustments.
  - **Platform Expenses:** Operational overhead costs (cloud servers, SMS gateways, maintenance).
  - **Operating Profit:** Mathematically computed as $\text{Operating Profit} = (\text{Net Sales} - \text{Refunds}) - \text{Platform Expenses}$.
- **Time Window Filters:** `Today`, `Yesterday`, `This Week`, `This Month`, `Quarter`, `Half-Year`, `Financial Year` (FY April 1 - March 31), and `Custom Range`.
- **Faceted Filtering:** Real-time filtering by Clinic, License ID, Sale Type (`INITIAL_LICENSE`, `LICENSE_RENEWAL`, `DOCTOR_ADDON`), Payment Method (`CASH`, `UPI`, `BANK_TRANSFER`, `CHEQUE`), and Payment Status (`PAID`, `PARTIAL`, `UNPAID`).

### 10.3 Invoicing Subsystem (`/superadmin/invoices`)
- **Sequential Invoicing:** Every commercial event is assigned an atomic, gap-free invoice number: `INV-YYYY-XXXXX` (e.g., `INV-2026-00001`).
- **Idempotency Protection:** Enforced by database constraint `UNIQUE(source_type, source_id)`. Rapid Superadmin clicks or automated reconciliations will never produce duplicate invoice records.
- **Invoice Immutability:** Once issued, invoice financials, clinic ownership, and source linkages are permanently locked. Changes require a formal cancellation note (`status = 'CANCELLED'`).
- **Document Actions:**
  - **View Detail (`/superadmin/invoices/{id}`):** Displays itemized line items (Base Plan, Additional Doctor Seats, Discounts, Net Amount) and masked license key (`NXCL-••••-••••-••••-••••`).
  - **Print Invoice (`/superadmin/invoices/{id}/print`):** High-resolution clean printer template with hospital/clinic details and QR verification.
  - **Download PDF (`/superadmin/invoices/{id}/pdf`):** Streamed vector PDF attachment for accounting archive.

### 10.4 Collections Ledger (`/superadmin/collections`)
Provides visibility into commercial cash inflows across four viewing modes:
1. **Individual Transactions:** Complete journal showing UTR/Cheque references, clinic codes, payment modes, and dates.
2. **Daily Aggregation:** Grouped daily totals across Cash, UPI, Bank Transfers, and Cheques.
3. **Monthly Aggregation:** Calendar monthly breakdowns.
4. **Financial Year Totals:** Indian Fiscal Year aggregate reporting.

### 10.5 Per-Clinic Commercial History (`/superadmin/clinics/{clinic}/sales`)
Provides a permanent, chronological billing ledger for each clinic:
- **Stable License Identity:** Anchored to the clinic's permanent license key (`NXCL-••••-••••-••••-••••`).
- **Full Historical Ledger:** Shows initial license purchase, subsequent renewals, mid-term doctor seat expansions, invoice numbers, collected payments, discounts, and outstanding balances.

### 10.6 Platform Expenses Management (`/superadmin/expenses`)
Allows Superadmins to record provider operational overhead:
- **Categories:** `INFRASTRUCTURE`, `SOFTWARE_LICENSE`, `OFFICE_RENT`, `SALARIES`, `MARKETING`, `HARDWARE`, `UTILITIES`, `MISCELLANEOUS`.
- **Provider-Level Isolation:** Platform expenses contain **strictly NO clinic_id**. They represent provider overhead and are completely separated from clinic operational expenses.

### 10.7 Separation Between Platform and Clinic Accounting
| Dimension | Platform Commercial Accounting | Clinic Tenant Operational Accounting |
|---|---|---|
| **Actor** | Software Provider (Superadmin) | Clinic Staff (Doctor, Operator, Clinic Admin) |
| **Authentication** | `developer` guard (`/superadmin/*`) | `web` guard (`/operator/*`, `/admin/*`) |
| **Invoicing** | Platform Sales Invoices (`INV-YYYY-XXXXX`) | OPD Patient Bills & Diagnostic Receipts |
| **Revenue Stream** | SaaS License Subscriptions & Doctor Seats | Patient Consultation Fees & Pathology Tests |
| **Expenses** | Platform Cloud Hosting & Dev Costs (`platform_expenses`) | Clinic Counter Expenses (`expenses` table) |
| **Ledgers** | Single-source commercial reconciliation | Double-entry journal accounting (`journal_entries`) |
| **Cross-Posting** | **STRICT ZERO CROSS-POSTING** | **STRICT ZERO CROSS-POSTING** |

---
*DxClinic Platform Administration Manual &bull; Revision 42 Complete &bull; Copyright &copy; 2026 DxClinic*

