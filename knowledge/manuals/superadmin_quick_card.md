# DxClinic Superadmin Quick Card

## Core Platform Rules
- **Guard Isolation:** Superadmin uses `developer` guard (`/superadmin/login`). Tenant staff uses `web` guard.
- **Tenant Context Rule:** Authenticated tenant staff is strictly bound to `user.clinic_id`. Superadmin explicitly selects context for platform inspection.

## Quick Actions
| Action | Endpoint / Flow | Description |
|---|---|---|
| **Fleet Overview** | `/superadmin/dashboard` | View metrics, clinics, doctor capacities, and status filters. |
| **Search Fleet** | Dashboard search bar | Search by Clinic ID (`NX0001`), Name, Mobile, or Owner. |
| **Issue License** | `/superadmin/licenses/issue` | Provision clinic, generate License Key & one-time Activation Code. |
| **Renew License** | Dashboard $\implies$ `Renew License` | One-click paid renewal; external payment recorded, zero tenant action. |
| **Add Doctor Seats** | Dashboard $\implies$ `+ Doctor Seat` | Mid-term doctor add-on: ₹2,999/yr, unchanged permanent license key. |
| **Demo Pause/Resume**| Dashboard $\implies$ `Pause / Resume` | Toggles demo tenant access; 503 unavailable for public PWA when paused. |
| **Reset Demo Data**  | Dashboard $\implies$ `Reset Demo Data`| Wipes & deterministically re-seeds `democlinic` transactional data. |
| **Suspend Clinic**   | Dashboard $\implies$ `Suspend` | Blocks tenant access with 403 Subscription Inactive. |
| **Reveal Key**       | Dashboard $\implies$ `Reveal` | Securely decrypts and displays masked license key. |
| **Renewal History**  | `/superadmin/license-history` | Fleet-wide immutable audit trail of all renewals and payments. |

## Doctor Licensing Model (Revision 37 & 38)
- **Base Plan:** 1 Doctor seat included by default.
- **Mid-Term Add-on Seat:** ₹2,999 / doctor / year (strictly yearly billing).
- **Same Permanent Key:** License key is never modified or regenerated upon seat purchase.
- **Capacity:** $\text{Effective Capacity} = 1 + \sum \text{Active Doctor Seat Add-on Entitlements}$.
- **Enforcement:** Doctor creation is blocked transactionally when active doctors reach effective capacity.
- **Demo Hardcap:** `democlinic` has a strict hardcap of 2 doctor seats (add-ons blocked at API & UI).

## Demo Tenant Sandbox (`democlinic`)
- **Credentials:** `demoadmin` / `DemoAdmin123!`, `demooperator` / `DemoOperator123!`, `demodoctor01` / `DemoDoctor01!`, `demodoctor02` / `DemoDoctor02!`.
- **Public Booking PWA:** `https://dxclinic.example.local/book/democlinic` (or `/b/democlinic`).
- **Isolation:** Transactional data is strictly isolated; resetting data never affects production clinics.
- **Doctor Formatter:** All doctor names formatted via `DoctorNamePresenter::format()` (never "Dr. Dr.").
- **Consultancy Labels:** `Clinic Consultancy`, `Online Consultancy`, `Visiting Consultancy`.

## One-Click Paid Renewal Workflow (Revision 35 & 37)
1. Clinic owner pays outside the application (Cash, UPI, Bank Transfer).
2. Superadmin enters `/superadmin/clinics/{id}/renew`, selects plan, confirms payment details, and clicks **[Confirm Payment & Renew License]**.
3. Extension date is automatic: `current_end_date + 1 day` for active licenses, or payment date for expired/suspended licenses.
4. Active mid-term doctor entitlements are reconciled to prevent double-billing.
5. Tenant administrator and staff require **zero actions**: no license key, no activation code, no onboarding, credentials unchanged. Immediate `ACTIVE` access.

## Commercial Accounting & Invoicing (Revision 42)
| Action | Endpoint | Description |
|---|---|---|
| **Sales Dashboard** | `/superadmin/sales` | 7 Financial KPIs (Gross, Net, Collected, Outstanding, Refunds, Expenses, Operating Profit). |
| **Invoice Ledger** | `/superadmin/invoices` | List and search invoices (`INV-YYYY-XXXXX`) with masked license keys. |
| **Invoice Detail** | `/superadmin/invoices/{id}` | Line items breakdown, cancellation audit, and print/PDF links. |
| **Print / PDF** | `/superadmin/invoices/{id}/print` & `/pdf` | Print-ready HTML layout and vector PDF download. |
| **Collections Ledger** | `/superadmin/collections` | Cashflows by payment mode (Cash, UPI, Bank Transfer, Cheque) across Individual, Daily, Monthly, and FY views. |
| **Clinic Sales History** | `/superadmin/clinics/{id}/sales` | Permanent license key commercial timeline (initial, renewals, seat add-ons). |
| **Platform Expenses** | `/superadmin/expenses` | Provider overhead tracking (strictly NO `clinic_id`). |

### Core Commercial Invariants:
1. **No Duplicate Payment Table:** Commercial truth lives on `licenses`, `license_renewals`, and `clinic_doctor_seat_addons`. No `platform_payments` table.
2. **Invoice Idempotency:** Guaranteed by `UNIQUE(source_type, source_id)`.
3. **Invoice Immutability:** Historical invoices cannot be edited in place. Corrections require cancellation.
4. **Profit Formula:** $\text{Operating Profit} = (\text{Net Sales} - \text{Refunds}) - \text{Platform Expenses}$.
5. **Zero Cross-Posting:** Platform SaaS accounting is 100% separate from tenant clinic accounting.

