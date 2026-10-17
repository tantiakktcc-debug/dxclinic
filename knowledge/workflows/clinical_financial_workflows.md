# DxClinic Clinical & Financial Workflows Specification (Revision 35)

**Audience:** System Integrators, Administrators, Accounting Auditors  
**Version:** 35.0.0  
**Status:** Authoritative Architectural Standard  

---

## 1. Core Financial Principles
1. **Double-Entry Equilibrium:** Every financial transaction must be recorded with strictly balanced journal entries:
   $$\sum \text{Debits} = \sum \text{Credits}$$
   Difference must strictly equal ₹0.00 at all times.
2. **Event-Driven Accounting:** Financial journals must ONLY be created from actual business events (Bill Settlement, Expense Incurrence, Bank Cash Deposit, Setup Equity). Never maintain synthetic or manually typed summary totals.
3. **No Online Payment Gateway:** Online booking (`/book/{public_slug}`, e.g. `/book/sharmacare`) registers patients and allocates queue tokens only. Payment happens exclusively at the clinic counter via physical Cash or verified Soundbox UPI.

## 2. Fundamental Business Distinctions
- **Appointment != Visit:** An appointment is a scheduled booking; a visit is created only when the patient physically arrives at the clinic.
- **Token != Payment:** A token represents queue sequence; it has zero monetary value.
- **Payment != Receipt:** A payment is a cash/bank settlement event; a receipt is an immutable A5 document acknowledging the payment.
- **Receipt != Accounting Entry:** Printing, viewing, or reprinting a receipt does not create or modify ledger entries (Idempotency).
- **Discount != Grace Waiver:** A discount is an operational tariff reduction recorded as an expense (`Dr Discount Expense`, `Cr Revenue`); a grace waiver is a 100% zero-payment policy for re-consultations within 5 calendar days.
- **Transfer != Income:** A cash deposit into the bank moves funds between asset accounts (`Dr Bank`, `Cr Cash`); it is never recognized as revenue.
- **Opening Balance != Revenue:** Initial capital/cash entered during setup is credited to `Opening Balance Equity (3001)`, not income.
- **Book Bank Balance != Statement Balance:** Book balance is derived strictly from ledger journals; physical statement balance is confirmed via reconciliation.
- **Cash Collection != UPI Collection:** Cash is physical currency in the drawer; UPI is electronic credit deposited directly into the bank.

## 3. Double-Entry Accounting Matrix

| Business Event | Debit Ledger | Credit Ledger | Financial Effect |
| :--- | :--- | :--- | :--- |
| **Opening Bank Setup** | Dr Bank Asset (1002) | Cr Opening Equity (3001) | Increases bank asset; establishes equity. Zero revenue. |
| **Opening Cash Setup** | Dr Cash in Hand (1001) | Cr Opening Equity (3001) | Establishes drawer cash; zero revenue. |
| **Consultation Cash Payment** | Dr Cash in Hand (1001) | Cr Consultation Revenue (4001) | Increases physical cash and revenue. |
| **Consultation UPI Payment** | Dr Linked Bank Ledger (1002) | Cr Consultation Revenue (4001) | Increases book bank balance and revenue. |
| **Consultation with Discount** | Dr Cash (1001)<br>Dr Discount Expense (5001) | Cr Consultation Revenue (4001) | Net cash received + discount expense = Gross revenue. |
| **Diagnostic Cash Payment** | Dr Cash in Hand (1001) | Cr Diagnostic Revenue (4002) | Increases cash drawer and diagnostic earnings. |
| **Diagnostic UPI Payment** | Dr Linked Bank Ledger (1002) | Cr Diagnostic Revenue (4002) | Increases bank ledger and diagnostic earnings. |
| **Cash Expense Payment** | Dr Category Expense Ledger (5xxx) | Cr Cash in Hand (1001) | Reduces physical cash drawer balance. |
| **Bank Expense Payment** | Dr Category Expense Ledger (5xxx) | Cr Linked Bank Ledger (1002) | Reduces book bank balance. |
| **Cash Deposit to Bank** | Dr Linked Bank Ledger (1002) | Cr Cash in Hand (1001) | Intra-asset transfer. Zero revenue, zero expense. |
| **Grace Consultation** | *No financial journal posted* | *No financial journal posted* | ₹600 gross, ₹600 waiver, ₹0 net paid. Zero collection. |

## 4. Cash Drawer Reconciliation Formula
At any point in an operational session:
$$\text{Expected Closing Cash} = \text{Opening Physical Cash} + \text{Settled Cash Collections} - \text{Cash Expenses} - \text{Cash Deposits}$$
$$\text{Variance} = \text{Actual Physical Cash Count} - \text{Expected Closing Cash}$$
- If $\text{Variance} = 0.00$: Session Status is **BALANCED**.
- If $\text{Variance} \neq 0.00$: Session Status is **VARIANCE**; requires mandatory administrative justification.

## 5. Bank Account Reconciliation Formula
$$\text{Closing Book Bank Balance} = \text{Opening Book Balance} + \text{Settled UPI Collections} + \text{Cash Deposits} - \text{Bank Expenses}$$

## 6. Diagnostic Laboratory Clinical vs Financial Separation (Revision 36)
1. **Financial Source of Truth**: Diagnostic bills and payments settled in `/operator/diagnostics` remain the authoritative, immutable financial record.
2. **Post-Billing Clinical Lifecycle**: Revision 36 extends the workflow after billing without duplicating financial entities:
   $$\text{Diagnostic Bill (Settled)} \longrightarrow \text{Diagnostic Order} \longrightarrow \text{Specimen Collection} \longrightarrow \text{Result Entry} \longrightarrow \text{Clinician Verification} \longrightarrow \text{Final Report Release}$$
3. **Zero Financial Impact Rule**:
   - Collecting specimens generates **zero accounting journals**.
   - Entering, editing, or correcting clinical results generates **zero ledger entries**.
   - Verifying findings or releasing final reports generates **zero cash movements**.
   - Viewing or reprinting diagnostic PDF reports is **100% idempotent** with zero financial side effects.
4. **Mobile OTP Deferred**: Patient notification via mobile OTP is intentionally deferred to a future phase.

