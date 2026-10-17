# DxClinic Financial Workflows & Accounting Architecture
**Revision 35 Authoritative Documentation**  
*Developer: Kundan Kumar | Copyright © 2026 DxClinic*

---

## 1. Accounting Foundation & Principles
DxClinic adheres to double-entry accounting standards where every business transaction creates a balanced Journal Entry:
$$\sum \text{Debits} \equiv \sum \text{Credits} \quad (\text{Variance: } ₹0.00)$$

Financial reports are strictly derived from:
- `payments`
- `journal_entries`
- `ledger_accounts`
- `cash_sessions`
- `expenses`
- `fund_transfers`

Never compute financial history from arbitrary UI strings or untrusted client inputs.

---

## 2. Onboarding & Opening Balances

### 2.1 Opening Bank Balance
- **Trigger**: System onboarding or new financial year setup.
- **Nature**: Equity contribution, **NOT revenue**.
- **Double-Entry Journal**:
  - `DR 1002 - Bank Account Ledger` (Asset)
  - `CR 3001 - Opening Balance Equity` (Equity)
- **Example**: Initial bank account balance of ₹50,000.00:
  - Dr HDFC Bank A/c: ₹50,000.00
  - Cr Opening Balance Equity: ₹50,000.00

### 2.2 Opening Cash in Hand
- **Trigger**: Initial clinic onboarding.
- **Nature**: Equity contribution, **NOT revenue**.
- **Double-Entry Journal**:
  - `DR 1001 - Cash in Hand` (Asset)
  - `CR 3001 - Opening Balance Equity` (Equity)
- **Example**: Initial drawer cash of ₹10,000.00:
  - Dr Cash in Hand: ₹10,000.00
  - Cr Opening Balance Equity: ₹10,000.00

---

## 3. Consultation Revenue & Billing

### 3.1 Clinic Consultation (Default ₹600.00)
- **Fee Master**: `CONSULTATION_PHYSICAL` resolves to ₹600.00.
- **Cash Payment**:
  - `DR 1001 - Cash in Hand`: ₹600.00
  - `CR 4001 - Consultation Income`: ₹600.00
- **UPI Soundbox Payment**:
  - `DR 1002 - Selected Bank Ledger`: ₹600.00
  - `CR 4001 - Consultation Income`: ₹600.00
- **Discounted Consultation** (e.g., ₹100 discount applied):
  - `DR 1001 - Cash in Hand` (Amount paid): ₹500.00
  - `DR 4003 - Discount Expense`: ₹100.00
  - `CR 4001 - Consultation Income`: ₹600.00

### 3.2 Online Consultancy (Default ₹700.00)
- **Fee Master**: `CONSULTATION_ONLINE` resolves to ₹700.00.
- **Crucial Rule**: Online booking does NOT use an online payment gateway. Booking generates an appointment, token, and a ₹700.00 **unpaid fee obligation** (`status = 'PENDING'`).
- **Settlement Rule**: No payment record or journal entry is created at booking. Payment occurs when settled via physical Soundbox UPI or Cash counter.
- **When Settled via UPI**:
  - `DR 1002 - Selected Bank Ledger`: ₹700.00
  - `CR 4001 - Consultation Income`: ₹700.00

---

## 4. Free OPD Revisit (5-Day Rule)
- **Eligibility Rule**: A qualifying completed physical consultation (`CLINIC_PHYSICAL`) followed by a revisit within **5 calendar days** incurs **₹0 CONSULTATION FEE**.
- **Billing Representation**:
  - Gross Amount: **₹600.00**
  - Revisit Waiver (`waiver_type = 'OPD_REVISIT'`): **₹600.00**
  - Net Amount: **₹0.00**
  - Bill Status: `'WAIVED'`
- **Accounting & Collection Rule**:
  - **Zero cash collection**.
  - **Zero UPI collection**.
  - **Zero bank movement**.
  - **NO payment record is created**.
  - Revisit is explicitly identifiable and never classified as a discount.

---

## 5. Diagnostic Laboratory Billing
- **Nature**: Fee earned from pathology tests and packages (CBC, Blood Sugar, Lipid Profile).
- **Cash Settlement**:
  - `DR 1001 - Cash in Hand`: Amount
  - `CR 4002 - Diagnostic Income`: Amount
- **UPI Soundbox Settlement**:
  - `DR 1002 - Selected Bank Ledger`: Amount
  - `CR 4002 - Diagnostic Income`: Amount

---

## 6. Clinic Expenses
- **Categories**: Managed via Expense Category Master (Housekeeping, Generator Fuel, Tea & Refreshments, Office Supplies).
- **Cash Expense**:
  - `DR 5001 - Operational Expense`: Amount
  - `CR 1001 - Cash in Hand`: Amount
- **Bank Expense**:
  - `DR 5001 - Operational Expense`: Amount
  - `CR 1002 - Selected Bank Ledger`: Amount

---

## 7. Cash Deposit to Bank (Fund Transfer)
- **Nature**: Internal liquidity movement between cash drawer and clinic bank account. **NOT revenue. NOT expense.**
- **Double-Entry Journal**:
  - `DR 1002 - Selected Bank Ledger` (Asset increases)
  - `CR 1001 - Cash in Hand` (Asset decreases)
- **Example**: Receptionist deposits ₹15,000.00 cash into HDFC Bank:
  - Dr HDFC Bank A/c: ₹15,000.00
  - Cr Cash in Hand: ₹15,000.00

---

## 8. Daily Reconciliation & Session Closing

### 8.1 Physical Cash Balance Equation
$$\text{Expected Closing Cash} = \text{Opening Physical Cash} + \text{Cash Collections} - \text{Cash Expenses} - \text{Cash Deposits}$$

*Note: UPI collections are credited directly to the bank account and are strictly excluded from physical drawer cash.*

### 8.2 Book Bank Balance Equation
$$\text{Book Bank Balance} = \text{Opening Bank} + \text{UPI Collections} + \text{Cash Deposits} - \text{Bank Expenses}$$

*Note: Labeled strictly as "BOOK BANK BALANCE" because statement reconciliation requires manual bank passbook clearance.*

### 8.3 Cash Variance & Session Close
- At session end, operator counts physical cash and inputs `closing_physical_cash`.
- System computes: $\text{Variance} = \text{Physical Cash} - \text{Expected Cash}$.
- If variance is zero, status displays: `BALANCED`.
- If variance exists, status displays: `REQUIRES REVIEW` with exact difference highlighted.

---

## 9. Comprehensive Reporting & Reconciliation
All reports reconcile against ledger entries and settled payments:
1. **OPD Patient Reports** (`/reports/opd`):
   - Sessions: Morning, Evening, Sunday, or All.
   - Doctor Filter: Specific doctor or All Doctors.
   - Strictly excludes vitals.
   - Bottom `TOTAL COLLECTION` equals the exact sum of settled payments. Free revisits show ₹0.00.
2. **Diagnostic Reports** (`/reports/diagnostics`):
   - Itemized test breakdown with billed vs collected comparison.
3. **General Ledger Reconciliation**:
   - Total Debits equals Total Credits across all active accounts.

---

## 10. Platform Commercial Accounting vs. Clinic Tenant Accounting (Revision 42)

### 10.1 Fundamental Accounting Partition
DxClinic enforces a strict partition between the **Software Provider Commercial Layer** and the **Tenant Clinic Operational Layer**.

```
+---------------------------------------------------------------------------------+
|                        PLATFORM COMMERCIAL ACCOUNTING                           |
|  - Managed by Superadmin (developer guard at /superadmin/*)                    |
|  - Primary Sources: licenses, license_renewals, clinic_doctor_seat_addons       |
|  - Provider Invoices: platform_sales_invoices (INV-YYYY-XXXXX)                  |
|  - Platform Overhead Expenses: platform_expenses (Hosting, SMS, Software)      |
|  - Metrics: Gross Sales, Net Sales, Collections, Refunds, Operating Profit      |
|  - NO CLINIC_ID on platform expenses (strictly provider-level)                 |
+---------------------------------------------------------------------------------+
                                      ||
                        [ STRICT ZERO CROSS-POSTING ]
                                      ||
+---------------------------------------------------------------------------------+
|                        CLINIC TENANT ACCOUNTING                                 |
|  - Managed by Clinic Staff (web guard at /operator/*, /admin/*)                 |
|  - Primary Sources: bills, payments, cash_sessions, expenses                    |
|  - Double-Entry Engine: journal_entries, ledger_accounts                        |
|  - Revenue Streams: Patient OPD Consultations, Diagnostics, Pharmacy            |
|  - Clinic Expenses: Counter tea, generator fuel, clinic staff wages             |
|  - MANDATORY clinic_id on all operational records (TenantContext scoped)        |
+---------------------------------------------------------------------------------+
```

### 10.2 Commercial Revenue Sources & Authoritative Truth
1. **Initial License Sale:**
   - Authoritative source: `licenses` table.
   - Fulfilled via `LicenseService::issueLicense()` or `LicenseRequestService::fulfillRequest()`.
   - Invoice Line Items: Base Plan, Included Doctor Seats, Additional Doctor Seats, Trial Waiver (₹0), Discounts, Net Amount.
2. **License Renewal:**
   - Authoritative source: `license_renewals` table.
   - Triggered via `/superadmin/clinics/{clinic}/renew`.
   - Invoice Line Items: Renewal Plan Base, Doctor Seat Renewals, New Doctor Seats, Discounts, Net Amount.
   - Preserves permanent license key continuity (`NXCL-••••-••••-••••-••••`).
3. **Mid-Term Doctor Add-On:**
   - Authoritative source: `clinic_doctor_seat_addons` table.
   - Billed at standard ₹2,999/doctor/year for an active license term.
   - Co-terminates with active license without re-licensing or resetting clinic credentials.

### 10.3 Single Source of Truth Guarantee (No Duplicate Payment Ledger)
No `platform_payments` table is created. All collection reporting and cashflow reconciliations are derived directly from the authoritative commercial source records (`licenses`, `license_renewals`, `clinic_doctor_seat_addons`) which contain payment methods, payment references (UTR/Cheque #), and payment dates.

### 10.4 Invoice Immutability & Idempotency Rules
- **Invoice Numbering:** Formatted as `INV-YYYY-XXXXX` from an atomic sequence counter (`platform_invoice_sequences`).
- **Idempotency Protection:** Enforced by `UNIQUE(source_type, source_id)` in `platform_sales_invoices`. Rapid double-clicks by Superadmin cannot produce duplicate invoices.
- **Immutability:** Once an invoice is issued, its financial amounts and source linkages are permanently locked in `PlatformSalesInvoice::booted()`. Corrections require a formal cancellation note (`status = 'CANCELLED'`).

### 10.5 Operating Profit Calculation Standard
Platform profit on the Superadmin Sales Dashboard (`/superadmin/sales`) is calculated on an accrual-consistent commercial basis:
$$\text{Gross Sales} - \text{Discounts} = \text{Net Sales}$$
$$\text{Net Sales} - \text{Refunds} = \text{Net Revenue}$$
$$\text{Net Revenue} - \text{Platform Expenses} = \text{Operating Profit}$$

Collections vs. Profit distinction:
- **Collections:** Cash and electronic funds actually received across Cash, UPI, Bank Transfer, and Cheque channels.
- **Outstanding:** $\text{Net Amount} - \text{Collected Amount}$.
- **Operating Profit:** True operational profitability of the software provider after deducting platform overhead expenses from net revenue.

---

## 11. Diagnostic Laboratory Financial Invariance (Revision 36)

### 11.1 Diagnostic Billing as Financial Source of Truth
Diagnostic tests and packages are billed and settled at `/operator/diagnostics`. This billing event is the sole financial authority:
- Posts double-entry journals: `DR Cash/Bank (1001/1002) | CR Diagnostic Revenue (4002)`.
- Updates cash drawer sessions or bank ledger balances.
- Generates customer payment receipts.

### 11.2 Strict Post-Billing Clinical Decoupling
Revision 36 extends the laboratory lifecycle AFTER billing without introducing secondary financial events:
1. **Specimen Collection**: Allocates specimen identifier (`SMP-{CLINIC_CODE}-{YYYYMMDD}-{XXXX}`); zero accounting impact.
2. **Result Entry**: Records numeric/text/qualitative clinical values; zero accounting impact.
3. **Clinician Verification**: Certifies findings by attending doctor/admin; zero accounting impact.
4. **Report Release**: Issues diagnostic report PDF and registers public verification QR code; zero accounting impact.
5. **Controlled Result Corrections**: Amends clinical values with versioning and audit trail; strictly prohibited from modifying past bills, revenue amounts, or payment modes.
6. **Report Reprints**: 100% idempotent retrieval with zero financial side effects.

### 11.3 Mobile OTP Explicit Deferral
Mobile OTP verification (SMS OTP / WhatsApp OTP) is intentionally deferred to a future phase. Revision 36 introduces zero OTP costs, zero OTP gateway fees, and zero OTP ledger accounts.


