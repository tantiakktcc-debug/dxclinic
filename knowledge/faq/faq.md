# DxClinic Knowledge Base & Frequently Asked Questions (FAQ)

**Version:** 26.0.0  
**Updated:** September 2026  
**Audience:** All Users (Admin, Operator, Doctor)  

---

### Q1: How do I register a patient?
**Answer:** Open **Patients** (`/patients`) and click **+ New Patient** or use the **+ Walk-in / Telephonic Booking** modal on the Queue page (`/clinic/{public_slug}/operator/queue`). Enter Patient Full Name (min 3 chars), Age, Sex, 10-digit Mobile Number, Address, and City. The system automatically issues a permanent Patient ID (`DXP-xxxxx`).

### Q2: How do I search a patient?
**Answer:** Use the search bar in **Patients** (`/patients`) or the Arrival Check-in bar on the Queue page. You can search by Patient ID (e.g., `DXP-00001`), Patient Name, Mobile Number, or City.

### Q3: How do I view patient history?
**Answer:** Navigate to **Patients** (`/patients`), locate the patient, and click **Patient History**. This opens their complete timeline including past appointments, tokens, recorded vitals, consultation bills, diagnostic bills, payment receipts, and grace consultation history. You can also click **Download History PDF**.

### Q4: How do I edit patient details?
**Answer:** In **Patients** (`/patients`), click **Edit Profile** on the patient's card. You can update Name, Age, Sex, Mobile, Address, and City, then click **Save Changes**.

### Q5: How do I book an appointment?
**Answer:** On the OPD Queue page, click **+ Walk-in / Telephonic Booking** (or patients use their clinic's dedicated public PWA at `/book/{public_slug}` or short link `/b/{public_slug}`). Select or enter patient details, choose Attending Doctor (or default doctor), Date, and Session (Morning/Evening). Click **Confirm Booking** to allocate a token.

### Q6: How does token numbering work?
**Answer:** DxClinic uses a single continuous daily sequence per clinic date (001, 002, 003...). The token number increments sequentially throughout the date. It does NOT reset between morning and evening sessions, nor does it reset per doctor.

### Q7: Why is a token not reused?
**Answer:** Tokens are immutable audit identifiers for queue transparency and patient safety. If an appointment is cancelled or marked absent, that token number is retired and never re-allocated to another patient on that day.

### Q8: How do I check in?
**Answer:** When the patient arrives at the clinic counter, enter their Token Number, Patient ID, or Mobile in the **Arrival Check-in** bar on the Queue screen and click **Mark Arrived**. This creates an active Visit record.

### Q9: How do I enter vitals?
**Answer:** On the patient's queue card, click **Vitals**. Fill in Weight (kg), Height (cm), BP Systolic and Diastolic (mmHg), Pulse Rate, SpO2 (%), Temperature (°F), Fever status, Respiratory Rate, and Random Blood Sugar (RBS). Click **Save Vitals**.

### Q10: How do I collect consultation fee?
**Answer:** Go to **Consultation Billing** (`/operator/consultation`), select the patient, review the fee breakdown (gross, discount, net), choose payment mode (**CASH** or **UPI**), enter the payment received, and click **Settle Bill & Print Receipt**.

### Q11: How do I record UPI?
**Answer:** In the billing screen, select **UPI** as the payment method, select the destination **Bank Account**, have the patient scan the clinic QR code, verify the transaction via physical Soundbox audible voice announcement, enter the UPI Reference/UTR number, and click **Confirm UPI Payment**.

### Q12: Why is Soundbox confirmation required?
**Answer:** DxClinic operates with a strict offline/clinic-first policy without an automatic online payment gateway. To eliminate fraud, fake payment screenshots, or network dropouts, operators must receive audible Soundbox voice confirmation or direct bank SMS before marking a UPI bill settled.

### Q13: How do I create diagnostic bill?
**Answer:** Open **Diagnostic Billing** (`/operator/diagnostics`), select the patient, select the required tests or packages from the diagnostic tariff master, review itemized charges, select CASH or UPI, and click **Settle & Print Receipt**.

### Q14: How do I record expense?
**Answer:** Open **Cash Counter** (`/cash/sessions`), switch to the **Expenses** tab, and click **Record Expense**. Select the Expense Category, enter Amount, select Payment Method (`CASH` or `BANK`), enter Payee name and notes, and click **Save Expense**. The system creates a balanced double-entry accounting journal.

### Q15: How do I deposit cash into bank?
**Answer:** In **Cash Counter** (`/cash/sessions`), click **Bank Deposit**. Select the destination Bank Account, enter Deposit Amount and bank reference/challan number, and click **Record Bank Deposit**. This posts a fund transfer entry: `Dr Bank`, `Cr Cash`. It decreases physical cash in hand without altering revenue.

### Q16: How is today's cash calculated?
**Answer:** **Today's Cash Collection** is strictly the SUM of all settled payments where `payment_method = 'CASH'` for the selected clinic date. It never includes UPI, pending, cancelled, or grace zero-payment visits.

### Q17: How is expected closing cash calculated?
**Answer:** Formula: `Expected Closing Cash = Opening Physical Cash + Cash Collections - Cash Expenses - Cash Deposits - Cash Refunds Paid in Cash`. UPI collections and bank movements do NOT enter this physical drawer cash formula.

### Q18: How do I close morning session?
**Answer:** In Cash Counter during an open Morning Session, perform a physical drawer count. Enter the counted amount in **Physical Closing Cash**. The system computes variance against Expected Closing Cash. If variance is not zero, enter a mandatory **Variance Reason**, then click **Close Session**.

### Q19: How do I close evening session?
**Answer:** Follow the same process at the end of the evening clinic. Count physical cash, enter the figure into **Physical Closing Cash**, review calculated variance, provide variance reason if required, and click **Close Session**.

### Q20: How does Sunday session work?
**Answer:** Sunday has a single configured operational session. It operates under the exact same financial rules: starting physical cash, collections, expenses, bank deposits, and closing reconciliation count.

### Q21: How do I print patient receipt?
**Answer:** From the billing confirmation screen or receipt history, click **[Print Patient Copy]**. This renders a single A5 page clearly marked with the *PATIENT COPY* badge.

### Q22: How do I print clinic receipt?
**Answer:** Click **[Print Clinic Copy]**. This renders a single A5 page clearly marked with the *CLINIC COPY* badge for clinic auditing and records.

### Q23: How do I print both?
**Answer:** Click **[Print Both Copies]**. This renders an A5 document with Page 1 as Patient Copy and Page 2 as Clinic Copy separated by an automatic page break.

### Q24: How do I upload clinic logo?
**Answer:** As an Administrator, open **Settings -> Print & Branding** (`/settings/branding`). Click **Choose File / Browse**, select a valid PNG, JPG, or JPEG image (max 2MB), configure the alignment (Left, Center, Right), and click **Upload Logo**. The system locks the aspect ratio to prevent stretching.

### Q25: How do I add doctor?
**Answer:** Go to **Settings -> Clinic & Doctor** (`/settings/clinic-doctor`). Under the Doctor Profiles section, click **Add Doctor**. Enter Full Name, Qualification, Registration Number, Specialization, and Contact Number, then click **Add Doctor**.

### Q26: How do I change default doctor?
**Answer:** Go to **Settings -> Clinic & Doctor** (`/settings/clinic-doctor`). In the Default Booking Doctor card, select the preferred active doctor from the dropdown and click **Save Default Doctor**.

### Q27: How do I add operator?
**Answer:** Go to **Settings -> Users & Operators** (`/settings/users`). Click **+ Add User / Operator**. Enter Name, Email (login ID), Mobile Number, Role (`OPERATOR`), and initial password. Click **Create User**.

### Q28: How do I reset operator password?
**Answer:** In **Settings -> Users & Operators** (`/settings/users`), locate the operator account, click **Reset Password**, enter the new password, and click **Confirm Password Reset**. An audit log entry is recorded.

### Q29: How do I change my password?
**Answer:** In the top navigation bar, click on your user profile icon/name, select **Change Password**, enter your current password, your new password, and confirm. Click **Update Password**.

### Q30: How do I download booking QR?
**Answer:** Open **Settings -> Online Booking / QR** (`/settings/booking-qr`). Click **Download PNG** or **Download SVG** for either the Local Development URL (`http://127.0.0.1:8000/book/{public_slug}`) or Production URL (`https://dxclinic.example.local/book/{public_slug}`). You can also click **Print Clinic Poster** to generate an A4 counter standee with the embedded QR and short URL (`/b/{public_slug}`).

### Q31: How does online booking work?
**Answer:** Patients scan the clinic QR code or open their clinic's dedicated public slug link (`/book/{public_slug}` or `/b/{public_slug}`) on their mobile phone. Unselected `/book` or internal codes (`/book/NX0001`) return 404 to ensure complete tenant safety. Patients enter their name, age, mobile, choose an attending doctor, consultancy type, and session, and receive an instant digital token. They present this token at the clinic counter upon arrival and pay their fee in person.

### Q32: Is online payment enabled?
**Answer:** **No.** DxClinic intentionally does NOT support online payment gateways during booking. All fee settlements occur physically at the clinic counter via Cash or verified Soundbox UPI.

### Q33: How do I backup?
**Answer:** Go to **Settings -> Backup & Restore** (`/settings/backup`). Click **Create Backup Now**. Enter a strong administrative encryption password. The system compiles an encrypted `.ncbk` container containing the database dump, files, logo, and metadata with SHA-256 checksum.

### Q34: How do I restore?
**Answer:** In **Settings -> Backup & Restore**, click **Restore Backup**. Select the backup file or choose from backup history, enter the decryption password, and click **Confirm Restore**. The system automatically creates a pre-restore safety snapshot, verifies checksum integrity, restores data atomically, and rolls back if an error occurs.

### Q35: What happens if backup fails?
**Answer:** If an automatic or manual backup encounters an error, the failure is logged, a warning alert is displayed on the admin dashboard, the existing database remains untouched, and previous valid backups are preserved.

### Q36: How do I generate reports?
**Answer:** Open **Reports -> OPD Reports** (`/reports/opd`), **Diagnostic Reports** (`/reports/diagnostics`), or **Reconciliation** (`/reports/reconciliation`). Select the required period filter: Daily, Weekly, Monthly, Quarterly, Half-Yearly, Financial Year (Apr 1 - Mar 31), or Custom Date Range. Filter by doctor or session, and export or print.

### Q37: How do I book online consultancy?
**Answer:** In either **Operator Booking** (`/operator/booking`) or the Public PWA (`/book/{public_slug}`, e.g. `/book/sharmacare`), select the consultation mode radio button: **Online Consultancy — ₹700**. The booking generates an appointment and token with an unpaid fee obligation. Payment is settled when the patient pays via Soundbox UPI or Cash counter.

### Q38: How does the Free OPD Revisit rule work?
**Answer:** If a patient who completed a qualifying physical OPD consultation revisits within **5 calendar days**, the consultation fee is **₹0.00**. The system automatically applies a **₹600.00 Revisit Waiver** (`OPD REVISIT FREE CONSULTANCY`), resulting in Net ₹0.00. No payment or collection record is created, and drawer cash is unaffected.

### Q39: What is the difference between Operator Booking and Public Booking?
**Answer:** Operator Booking (`/operator/booking`) is an authenticated desk portal used by receptionists to search existing patients or register walk-ins (`booking_source = CLINIC`). Public Booking (`/book/{public_slug}`, e.g. `/book/sharmacare`) is an unauthenticated PWA portal used by patients on their mobile devices (`booking_source = ONLINE`).

### Q40: How does mobile number normalization work?
**Answer:** DxClinic accepts 10 digits (`9876543210`), +91 with 10 digits (`+919876543210` or `+91 9876543210`), and 11 digits with leading zero (`09876543210`). All valid inputs are normalized automatically to canonical `+91 9876543210`. Invalid lengths or non-digit characters are rejected with clear error messages.

### Q41: Why are vitals excluded from OPD Patient Reports?
**Answer:** Per DxClinic Revision 25 architectural standards, OPD patient operational registers (`/reports/opd`) focus strictly on patient identity, token allocation, attendance, consultation mode, fee settlement, and attending doctor. Clinical vitals (BP, pulse, weight) are restricted to the confidential Doctor Pad and Patient History.

### Q42: How is the Total Collection footer calculated in reports?
**Answer:** In both OPD and Diagnostic reports, the **TOTAL COLLECTION** footer is computed strictly by summing actual settled payment records (`payments` table with `status = 'SETTLED'`). Unpaid bookings and ₹0 free revisits contribute ₹0.00 to collection totals, ensuring exact financial reconciliation with the cash drawer and bank ledger.

---

### Q43: How do I view old consultation bills?
**Answer:** Navigate to **Consultation Billing** (`/operator/consultation`) and click on the **[Consultation Billing History]** tab. This displays all historical consultation bills regardless of how long ago they were created.

### Q44: How do I view old diagnostic bills?
**Answer:** Open **Diagnostic Billing** (`/operator/diagnostics`) and click on the **[Diagnostic Billing History]** tab. This presents all historical diagnostic bills, line items, and payment statuses.

### Q45: How do I find a bill by patient or bill number?
**Answer:** In either Consultation Billing History or Diagnostic Billing History, use the filter inputs at the top of the table. You can search by Patient Name, Patient ID (`DXP-...`), 10-digit Mobile, Bill Number (e.g., `CBL-2026-...` or `DBL-2026-...`), Attending Doctor, or Date Range.

### Q46: Can I edit a paid consultation bill?
**Answer:** **No.** The original paid bill cannot be overwritten. Settled financial records (payments, receipts, journals, bill amounts) are strictly immutable. A controlled amendment, discount, or refund workflow is used, generating new audit and transactional records while the original financial history remains preserved.

### Q47: How do I give a discount after the patient has already paid?
**Answer:** Locate the bill in Consultation Billing History and click **[Modify / Refund]**. Select type **DISCOUNT_AFTER_SETTLEMENT**, specify the refund amount, enter the mandatory reason (e.g., *"Refund for discount given as per doctor recommendation."*), link the doctor, and select the refund settlement method (Cash or UPI). The original bill remains intact; a refund transaction is issued, returning the discount to the patient and balancing the accounting ledger.

### Q48: How do I process a partial refund?
**Answer:** Open the bill's **[Modify / Refund]** modal, select **PARTIAL_REFUND**, enter any refund amount up to the remaining refundable amount (`Settled Amount - Prior Refunds`), enter the mandatory reason, and process. The bill status remains `PAID`, with updated retained revenue displayed clearly.

### Q49: How do I process a full refund?
**Answer:** In the **[Modify / Refund]** modal, enter the full remaining refundable amount. Once 100% of the settled payment is refunded, the bill status updates to **REFUNDED**.

### Q50: What reason is required when modifying a bill?
**Answer:** A meaningful, mandatory reason of **at least 10 characters** is strictly required by the server. Generic or trivial inputs such as `"edit"`, `"change"`, `"mistake"`, or `"ok"` are rejected. Example valid reasons: *"Patient requested discount as per doctor instruction"* or *"Wrong test package selected by operator"*.

### Q51: What happens when a doctor recommends a discount?
**Answer:** The attending physician submits a Doctor Recommendation with the recommended amount and rationale. This recommendation is attached to the encounter record but does NOT move money directly. The desk operator or administrator executes the financial refund through authorized billing controls.

### Q52: Can an operator directly modify a settled financial record?
**Answer:** **No.** Neither operators nor administrators can overwrite settled database rows in-place. Operators can initiate modification and refund requests with mandatory reasons, and execute refunds within configured desk cash limits.

### Q53: Can a doctor process a refund?
**Answer:** **No.** Doctors have medical authority to recommend fee reductions or waivers, but cannot directly process refunds, disburse drawer cash, alter payment records, or post accounting journals.

### Q54: How do I view refund history?
**Answer:** In the Billing History table, click **[View]** on any amended or refunded bill. The ledger detail modal displays the original payment, list of processed refunds with timestamps, operator names, and the remaining net retained amount.

### Q55: How do I reprint an old consultation receipt?
**Answer:** In Consultation Billing History, click **[Print Receipt]** (Patient Copy, Clinic Copy, or Both). Reprinting is completely idempotent and creates zero payments, zero journals, and zero duplicate receipts.

### Q56: How do I reprint an old Doctor Pad?
**Answer:** Go to **Consultation Billing** (`/operator/consultation`) and switch to the **[Doctor Pad History]** tab. Locate the patient encounter and click **[Reprint Doctor Pad]**. Output is calibrated for pre-printed letterheads and creates zero financial or queue records.

### Q57: How do I view old diagnostic bill details?
**Answer:** In Diagnostic Billing History, click **[View Details]**. A modal opens displaying itemized investigations, historical rates, historical quantities, gross, discount, refunds, and retained amounts.

### Q58: Can I change old diagnostic test/package rates?
**Answer:** **No.** Historical rates remain strictly preserved as snapshotted at billing time. Old bills never re-query today's master rates. Any financial adjustment is represented via an authorized refund or supplementary bill.

### Q59: What happens if the corrected diagnostic bill becomes lower?
**Answer:** If tests are removed or quantities reduced, the financial difference is disbursed as a controlled **Refund**, reducing revenue and crediting Cash or Bank.

### Q60: What happens if the corrected diagnostic bill becomes higher?
**Answer:** The original bill is NOT modified in-place. The additional tests are billed as a new **Supplementary Diagnostic Bill** (`{bill_number}-SUPP-{n}`) linked to the original bill, with its own payment settlement and receipt.

### Q61: Does printing/reprinting create another payment?
**Answer:** **Never.** All printing and PDF generation endpoints in DxClinic are strictly idempotent and read-only.

### Q62: Does refunding a bill change the original receipt?
**Answer:** **No.** The original receipt remains immutable. The system generates a **Receipt Revision** record in `receipt_revisions` capturing the adjustment, refund link, and reason.

### Q63: Where can I see who modified a bill and why?
**Answer:** Click **[Audit Log]** on any historical bill. The modal displays who requested or processed the change, exact timestamp, change category, old values, new values, financial impact, and mandatory reason.

### Q64: How does refund affect cash reconciliation?
**Answer:** Cash refunds disbursed from the desk drawer are subtracted in the reconciliation formula:  
$$\text{Expected Closing Cash} = \text{Opening Cash} + \text{Cash Collections} - \text{Cash Expenses} - \text{Cash Deposits} - \text{Cash Refunds Paid in Cash}$$

### Q65: How does UPI refund affect bank reconciliation?
**Answer:** UPI refunds transferred back to the patient from clinic bank accounts reduce the book bank balance:  
$$\text{Book Bank Balance} = \text{Opening Bank} + \text{UPI Collections} + \text{Cash Deposits} - \text{Bank Expenses} - \text{Bank Refunds Paid from Bank}$$

### Q66: Can the RAG assistant modify or refund a bill?
**Answer:** **No.** The AI Operator Assistant is strictly informational. It can explain how to request a refund or interpret billing policies, but cannot execute financial changes, post journals, or modify records.

### Q67: What is the difference between an amendment, refund, discount, and supplementary bill?
**Answer:**
- **Amendment / Modification**: Adjusting an unsettled `PENDING` bill before payment.
- **Refund**: Returning money to a patient from an already settled bill via Cash or Bank transfer.
- **Discount After Settlement**: A partial refund granted post-payment (e.g. per doctor's recommendation).
- **Supplementary Bill**: A newly created, linked bill used when additional diagnostic tests are required after original settlement.

### Q68: What is Prescription Pad Template Designer?
**Answer:** It is a vector-based (SVG) design and calibration module that enables clinics to configure custom, doctor-specific prescription stationery layouts with physical millimeter-level field coordinates, dynamic data mapping, scanned reference backgrounds, and live PDF proofing.

### Q69: Can each doctor have a different prescription pad?
**Answer:** **Yes.** Every doctor in DxClinic can have independent prescription pad templates with distinct paper dimensions, field selections, typography, and coordinate positioning.

### Q70: How do I create a prescription pad template?
**Answer:** Navigate to **Settings > Prescription Pad Designer**, click **[New Template]**, select the doctor, choose the paper size (A4, A5, Letter, Half Letter, or Custom mm), specify orientation, and click **[Create & Open Designer]**. You can also import existing legacy settings by clicking **[Import From Legacy]**.

### Q71: How do I upload a pad scan?
**Answer:** In the Prescription Pad Designer, navigate to the left sidebar card **Pad Scan (Reference)**, select a PNG, JPG, or JPEG scan of the physical pre-printed doctor pad, and click **[Upload Scan]**. The scan displays as a transparent background overlay in Proof Mode.

### Q72: Which paper sizes are supported?
**Answer:** Standard paper sizes include **A4 (210 × 297 mm)**, **A5 (148 × 210 mm)**, **Letter (215.9 × 279.4 mm)**, **Half Letter (139.7 × 215.9 mm)**, and arbitrary **Custom Sizes** specified in millimeters.

### Q73: Why are positions stored in millimeters?
**Answer:** Millimeters correspond directly to real-world physical paper dimensions. Browser pixels change across screens, devices, and zoom levels; physical millimeter coordinates ensure 100% faithful print reproduction on physical printers regardless of screen resolution or designer zoom.

### Q74: How do I drag a field?
**Answer:** Click and hold any placed field on the canvas to drag it. Release the mouse button to set its new location. The designer converts canvas coordinates directly into physical millimeters.

### Q75: How do I set X/Y manually?
**Answer:** Click any field on the canvas to open the right-hand **Properties Panel**. Under **Position (Millimeters)**, enter numeric values for **X (mm)** and **Y (mm)**. You can also use arrow keys for 0.5 mm steps or Shift + Arrow keys for 0.1 mm precision.

### Q76: How do I choose which vitals print?
**Answer:** In the Designer's **Field Toolbox** under **VITALS**, click only the vitals you wish to place (e.g. BP, Pulse, Weight, SpO2, Temp, RBS, RR). If a doctor doesn't need certain vitals on their pad, simply do not add those fields or delete them from the canvas.

### Q77: How do I create another template version?
**Answer:** To modify an active or archived template, click **[Duplicate]** or **[Edit (New Draft)]**. This creates a new version in `DRAFT` status (e.g. v2, v3) without altering the existing active template.

### Q78: What is Proof PDF?
**Answer:** **Proof PDF** renders both the scanned pre-printed stationery background and the dynamic patient data. It is used on-screen and for calibration to verify alignment against physical stationery.

### Q79: What is Production PDF?
**Answer:** **Production PDF** renders strictly the dynamic data overlay (patient details, vitals, token, date) with a transparent background. It is sent to physical printers loaded with pre-printed clinic stationery so the scan background is never printed twice.

### Q80: Why is the scan not printed in Production PDF?
**Answer:** Physical prescription pads are already printed by commercial printing presses with clinic logos, doctor headers, watermarks, and ruled sections. Printing the scan image would waste toner, cause double-printing, and blur the physical stationery.

### Q81: How do I test print?
**Answer:** In the top toolbar of the Designer, click **[Test Print ▾]** and select either **Production PDF (Data Only)** or **Proof PDF (With Scan)**. The system compiles a real Dompdf document using realistic sample clinical data.

### Q82: What is printer calibration?
**Answer:** Hardware printers often have mechanical paper feed offsets (e.g. shifting print 1.5mm to the left). In **Settings > Prescription Pad Designer > [Printer Calibration]**, admins can set physical X/Y offsets and scaling factors. These offsets adjust the final print output globally without mutating individual template coordinates.

### Q83: What happens when a doctor has no new template?
**Answer:** DxClinic automatically falls back to the **legacy Doctor Pad renderer**. No doctor is ever left without working pad printing.

### Q84: What happens to my old Doctor Pad settings?
**Answer:** Legacy Doctor Pad coordinates, Blade layouts, and uploaded files are 100% preserved. They remain the active fallback until a new designer template is explicitly activated for that doctor.

### Q85: How do I view old Consultation Bills?
**Answer:** Navigate to **Consultation Billing** (`/operator/consultation`) and scroll down to the **Consultation Billing History** table. You can search by bill number, patient name, date range, or doctor.

### Q86: How do I edit a paid Consultation Bill?
**Answer:** In the Consultation Billing History table, click **[Edit]** (located immediately before the **[Pad]** button). A modal will open allowing controlled modifications or refund requests with a mandatory reason.

### Q87: How do I edit a Diagnostic Bill?
**Answer:** In **Diagnostic Billing** (`/operator/diagnostics`), locate the bill in the history table. The **[Edit]** button is located in its own dedicated column physically positioned between **Status** and **Method**.

### Q88: Why is a paid bill not overwritten?
**Answer:** Settled financial transactions are legally and audit-wise immutable. Modifying settled numbers in-place breaks journal ledgers, destroys audit trails, and violates accounting principles. Adjustments are handled via approved refunds, supplementary bills, and receipt revisions.

### Q89: How do I request a refund?
**Answer:** In the Edit/Modify modal of a paid bill, select **Refund**, enter the refund amount, specify the payment mode (Cash from drawer or Bank/UPI with bank account selection), and provide a mandatory reason.

### Q90: How does doctor-recommended discount work?
**Answer:** When a doctor recommends a post-settlement discount, the original paid bill is not reduced. Instead, a refund is issued for the discounted amount with the doctor recommendation audit trail attached.

### Q91: How do I view changes/audit history?
**Answer:** Click the **[Audit]** button on any billing row. A full timeline modal will show the user, timestamp, modification category, old values, new values, and reason.

### Q92: Can a Doctor process a refund?
**Answer:** **No.** Doctors may recommend discounts or refunds, but only authorized Admins and Operators can disburse cash or bank refunds and post accounting entries.

### Q93: Can an Operator activate a prescription template?
**Answer:** **No.** Template creation, design, and activation require administrative credentials (`ADMIN` role).

### Q94: Which template does a historical Doctor Pad reprint use?
**Answer:** It uses the **exact template version** that was committed when that Doctor Pad was generated. If an encounter was printed under Template V1, reprinting that historical encounter will ALWAYS use Template V1, even if Template V2 or V3 is currently active.

### Q95: Does reprinting create another payment?
**Answer:** **Never.** All pad and receipt printing endpoints in DxClinic are read-only and strictly idempotent.

### Q96: Why might a vital field be blank?
**Answer:** DxClinic strictly follows clinical truth: if a patient encounter has no vital record recorded (e.g. weight or BP was not measured), the field is left blank. The system never fabricates placeholder values or fake zeros.

### Q97: How does the sidebar work?
**Answer:** The navigation is grouped into:
1. **Daily Work** (Dashboard, Booking, Queue, Patients, Consultation, Diagnostics, Cash) — directly visible.
2. **Reports** — collapsible section for OPD, Diagnostic, and Financial reports.
3. **Help & Guide** — collapsible section near the bottom for FAQ, User Manuals, and Quick Guides.
4. **Settings** — collapsible section at the bottom (Admin only) for clinic configuration, masters, and the Prescription Pad Designer.

### Q98: Where are FAQ and manuals?
**Answer:** Click **Help & Guide ▾** in the left sidebar to access the FAQ, User Manual, and Quick Guide tabs.

### Q99: Where are Settings?
**Answer:** Settings are located at the bottom of the left sidebar under the collapsible **Settings ▾** group (accessible to Admins).

### Q100: Does activating a new template version change old Doctor Pad reprints?
**Answer:** **No.** Historical appointments retain an immutable reference to the template version in effect when printed. Activating a future version (e.g. V2 or V3) only applies to new appointments; historical reprints remain completely immutable.

### Q101: What happens if an older template version is ARCHIVED?
**Answer:** Archived template versions remain fully printable for historical records that were originally committed to them. Archival prevents assigning the template to new encounters, but preserves 100% reproducibility for historical reprints.

### Q102: How are historical legacy records printed?
**Answer:** Existing historical encounters recorded before template creation (or with no template assigned) continue to use the legacy Doctor Pad Blade renderer (`print.doctor-pad`). Legacy fallback is fully preserved and never overwritten.

### Q103: Can a template version be deleted if it was used for past patient pads?
**Answer:** **No.** The database enforces a foreign key `RESTRICT` constraint. Any template version referenced by historical clinical encounters cannot be deleted, protecting historical medical records.

### Q104: How is physical printer calibration validated?
**Answer:** Printer calibration (offsets in mm and scale factors) is mathematically calculated and verified during PDF generation. Physical alignment requires an on-site operational check on the clinic's physical printer hardware, ensuring "Margins: None" or "Minimum" is selected in the browser print dialog.

### Q105: What is the difference between Clinic ID, User ID, License Key, and Activation Code?
**Answer:**
- **Clinic ID (`clinic_code`):** The facility's unique tenant identifier (e.g., `NX0001`, `NX0002`).
- **User ID (`login_id`):** The staff member's unique role-scoped login handle within their clinic (e.g., `ADM0001`, `OPR0001`).
- **License Key (`NXCL-...`):** The cryptographically secured subscription entitlement token representing duration and doctor capacity.
- **Activation Code (`NXA-...`):** A **one-time, single-use** security token consumed exclusively by the initial Clinic Administrator during first activation.

### Q106: Can two different clinics have staff with the same User ID?
**Answer:** **Yes.** User IDs are scoped per clinic (`UNIQUE(clinic_id, login_id)`). Different clinics can independently have an `OPR0001` or `ADM0001` without collision or cross-tenant interference.

### Q107: Can request headers or URL parameters override the authenticated user's clinic?
**Answer:** **Never.** Under the authoritative tenant security model, for all authenticated users (`ADMIN`, `OPERATOR`, `DOCTOR`), `authenticated_user.clinic_id` is the **ONLY** authoritative tenant context source. Request headers (`X-Clinic-ID`, `X-Clinic-Code`), query parameters, route parameters, and session values cannot override it; any mismatched cross-tenant tampering attempt is rejected with **403 Forbidden**.

### Q108: How does the one-time Activation Code work?
**Answer:** When a clinic license is first provisioned by the Superadmin, an Activation Code is generated. The initial Clinic Administrator uses this code once to activate the clinic facility and set their permanent password. Once consumed, the code hash is permanently purged and cannot be reused.

### Q109: Does renewing a license require an Activation Code?
**Answer:** **No.** Renewals create a new active subscription period extending the clinic's validity without requiring an activation code or interrupting normal tenant access.

### Q110: How many doctors can a clinic add to their subscription?
**Answer:** By default, standard subscriptions include **1 Doctor Seat**. Additional doctor seats can be purchased as add-ons for ₹2,999 / doctor / year (or the plan-configured rate). Effective capacity equals `included_doctor_seats + additional_doctor_seats`.

### Q111: What happens if a clinic attempts to create a doctor beyond their licensed capacity?
**Answer:** Creation is blocked transactionally at both the Livewire and Eloquent model layers with the exact message: *"You have reached the maximum number of doctor seats included in your license. Please contact the application provider to purchase an additional doctor seat."*

### Q112: How can a clinic add more doctor seats?
**Answer:** The Superadmin opens the Fleet Dashboard, clicks **[+ Seats]** for the clinic, enters the number of seats to purchase, and confirms. Billing is calculated on a pro-rata basis based on remaining subscription days, and the clinic can immediately register new doctor profiles.

### Q113: If a doctor is deactivated or deleted, does that reduce historical subscription charges?
**Answer:** **No.** Deactivating or deleting a doctor frees up real-time licensed capacity for a replacement doctor, but historical subscription records and billing ledger entries remain completely immutable.

### Q114: How do public patients book appointments for a specific clinic?
**Answer:** Each clinic has a dedicated public booking link using its human-friendly public slug:
$$\text{https://dxclinic.example.local/b/}\mathbf{sharmacare} \quad\implies\quad \text{https://dxclinic.example.local/book/}\mathbf{sharmacare}$$
Visiting this link exposes only that clinic's branding, active doctors, consulting modes, and fees.

### Q115: What happens if an invalid clinic slug or internal code is entered in the public booking URL?
**Answer:** Requesting an unknown slug (e.g. `/book/unknownclinic` or `/b/unknownclinic`), an unselected `/book`, or an internal clinic identifier (e.g. `/book/NX0001` or `/book/1`) safely aborts with **404 Not Found**. The system never falls back to arbitrary clinic data (`Clinic::first()`) or leaks internal tenant IDs.

### Q116: How does a clinic renew their subscription in DxClinic?
**Answer:** The clinic owner pays the application owner/software provider outside the application (via UPI, Bank Transfer, Cheque, or Cash). Once payment is received, the Superadmin performs a one-click renewal inside the Superadmin Fleet Dashboard. The clinic's subscription becomes active immediately with zero disruption.

### Q117: Does the clinic administrator have to enter a new license key or activation code when renewing?
**Answer:** **No.** The clinic administrator and staff require zero actions. They do not enter a new license key, do not enter an activation code, do not undergo reactivation or onboarding, and keep their existing login credentials.

### Q118: How does continuous active service extension work for active licenses?
**Answer:** If the clinic's current license is still active with remaining days, the new renewal term begins strictly on `current_end_date + 1 day`. All remaining active days are preserved and added continuously to the new subscription period.

### Q119: What happens if an expired or suspended license is renewed?
**Answer:** If the clinic's license has lapsed into `EXPIRED` or `SUSPENDED` status, the new renewal coverage period begins on the **Payment Date**, immediately restoring the clinic's license status to `ACTIVE` and reopening staff access.

### Q120: How are additional doctor seats billed during yearly renewal?
**Answer:** During a Yearly renewal, the base plan includes 1 doctor seat. Any existing paid add-on seats can be renewed at the annual renewal rate (₹2,999/seat/year), and new additional doctor seats can be purchased simultaneously at the same rate.

### Q121: How is accidental double-billing of doctor seats prevented?
**Answer:** The renewal interface clearly separates:
- **Option A:** Toggling the renewal of already-existing paid doctor seats.
- **Option B:** Specifying the quantity of brand-new additional doctor seats.
This visual and functional separation ensures existing seats are not mistakenly billed as new additions.

### Q122: Can a revoked or cancelled license be renewed using the standard renewal form?
**Answer:** **No.** A license flagged as `REVOKED` cannot be renewed through the normal one-click renewal form. The system displays an explicit warning instructing the superadmin to use the proper reinstatement process.

### Q123: How does the system prevent duplicate renewals caused by accidental double-clicks?
**Answer:** Every renewal form generates a unique cryptographic `idempotency_key`. If the Superadmin double-clicks or the browser sends duplicate requests, the system returns the existing renewal transaction safely without creating duplicate database records or double-charging.

### Q124: Where can a clinic administrator view their subscription status and remaining days?
**Answer:** Clinic Administrators can navigate to **Settings ▾ &rarr; License & Subscription** (`/settings/subscription`). The panel displays their current plan, masked license key, start/end dates, remaining days calculated in `Asia/Kolkata` (IST) timezone, and doctor seat allocation breakdown.

### Q125: Where does the Superadmin review historical renewals and payment audit trails?
**Answer:** Fleet-wide renewal records are available at `/superadmin/license-history`, and clinic-specific historical timelines are located at `/superadmin/clinics/{clinic_id}/history`. Every entry records the exact payment method, external transaction reference (UTR/cheque), amounts, and authorizing user.

### Q126: Can a clinic add another doctor during an already-active subscription period?
**Answer:** **Yes.** Clinics can purchase additional doctor seats mid-term via the Superadmin Fleet Dashboard (**[+ Doctor Seat]**). Mid-term additions are billed separately on an annual cycle (default: ₹2,999/year per doctor). The clinic's existing permanent License Key remains 100% unchanged, no activation codes are generated, and clinic staff experience zero downtime.

### Q127: How is doctor seat entitlement validity calculated?
**Answer:** Mid-term doctor seat add-ons are valid for exactly one year (`valid_from = payment_date`, `valid_until = valid_from + 1 year - 1 day`). The parent clinic license dictates overall access: if the parent license expires or is suspended, clinic access is locked regardless of active doctor add-ons.

### Q128: What is the Demo Clinic sandbox and what credentials does it use?
**Answer:** DxClinic includes a permanent, isolated demonstration tenant (`democlinic`) with full fictional clinical data. Default credentials are `demoadmin` / `DemoAdmin123!` (Administrator), `demooperator` / `DemoOperator123!` (Desk Operator), `demodoctor01` / `DemoDoctor01!` (Dr. Amit Verma — General Medicine), and `demodoctor02` / `DemoDoctor02!` (Dr. Neha Kapoor — Family Medicine). The legacy alias `demodoctor` / `DemoDoctor123!` is preserved for backward compatibility. The Demo Clinic operates with a strict hard cap of 2 doctor seats.

### Q129: What happens when the Superadmin pauses the Demo Clinic?
**Answer:** When paused, demo staff login attempts are blocked, and the public booking PWA at `/book/democlinic` returns a clean HTTP 503 screen stating: *"The DxClinic demonstration environment is temporarily unavailable. Please try again later."* Resuming demo restores full operational capability immediately.

### Q130: What does [Reset Demo Data] do?
**Answer:** Clicking **[Reset Demo Data]** in the Superadmin Fleet Dashboard atomically wipes transactional records (patients, visits, vitals, bills, payments, receipts) specifically for `democlinic` and re-seeds deterministic fictional clinical records. Real customer clinics are 100% untouched.

### Q131: How is strict tenant isolation enforced on the public PWA?
**Answer:** The canonical URL structure `/book/{public_slug}` (and short alias `/b/{public_slug}`) serves as the sole tenant authority. The backend strictly resolves all queries and writes to the identified clinic. Any attempt to override tenant context via query strings (`?clinic_id=...`), HTTP headers (`X-Clinic-ID`), or form payloads triggers an immediate **403 Forbidden** abort.

### Q132: Why did "Dr. Dr." previously appear and how is doctor naming normalized in Revision 38?
**Answer:** "Dr. Dr." appeared when database records already stored "Dr. Rajesh Sharma" and presentation templates prepended another "Dr. ". In Revision 38, doctor names are canonically stored without honorific prefixes (`Rajesh Sharma`, `Amit Verma`), and the centralized service `DoctorNamePresenter::format($doctor)` generates the single formatted string `Dr. Firstname Lastname` across all UI screens, PWA, PDFs, reports, and receipts.

### Q133: What is the customer-facing terminology for clinic consultations?
**Answer:** The customer-facing terminology is strictly `Clinic Consultation` (alongside `Online Consultancy` and `Visiting Consultancy`). Internal enums (`CLINIC_PHYSICAL`, `ONLINE`, `VISITING`) remain unchanged to preserve database backward compatibility.

### Q134: How does DxClinic guarantee consultation fee isolation between clinics?
**Answer:** Consultation fee resolution is hierarchically scoped to the active clinic. In `FeeMaster::getFeeForMode()`, doctor-specific custom fees are validated with `$doctor->clinic_id === $clinicId`; if a doctor ID does not belong to the active clinic, their rate is rejected. Furthermore, asynchronous Livewire updates resolve public clinic context from request referrers/snapshots to prevent tenant fallback, and each clinic seeds baseline fee records (`doctor_id = null`).

### Q135: How is 5-Year Plan license duration and remaining days calculated?
**Answer:** A 5-Year subscription duration is strictly defined as 60 months (`duration_months = 60`). The license validity end date is calculated exactly 5 years from issuance in `Asia/Kolkata` (e.g. `2026-09-28` to `2031-09-28`). The remaining days counter is mathematically computed from the real `valid_until` date (~1,825–1,826 days remaining) and is never faked or derived from plan titles.

### Q136: Can Demo Clinic ever exceed 2 doctor seats?
**Answer:** No. Demo Clinic has a strict hard cap of 2 doctor seats enforced at all levels: UI, controller, service layer (`DemoService`), Eloquent model hooks (`Doctor::creating`), and database transactions. Any attempt to register a 3rd doctor is immediately rejected.

### Q137: What keyboard shortcuts are available in DxClinic for fast front-desk and clinical operations?
**Answer:** DxClinic provides a comprehensive keyboard-first operating engine across all portals:
- **`F1` / `?`**: Open the interactive Keyboard Shortcuts Cheatsheet modal with live instant search.
- **`F2`**: Quick New Patient / Walk-in Registration.
- **`F3` / `F4`**: Instant focus on Patient Search or Existing Patient lookup.
- **`F7`**: Print Token or Slip immediately.
- **`F8`**: Allocate Queue Token or Confirm Appointment Booking.
- **`F9`**: Complete Consultation & Advance Queue.
- **`Ctrl + Enter`**: Submit active form or modal dialogue.
- **`Esc`**: Dismiss any open modal or cheatsheet dialog.
- **Navigation Shortcuts (`Alt + Key`)**:
  - `Alt + D`: Dashboard
  - `Alt + Q`: OPD Arrival Queue
  - `Alt + B`: New Token / Booking
  - `Alt + P`: Patient Registry
  - `Alt + C`: Consultation Billing
  - `Alt + O`: Diagnostic Billing
  - `Alt + T`: Diagnostic Tariff Master
  - `Alt + S`: Cash Sessions Drawer
  - `Alt + H`: Reports & Analytics
  - `Alt + M`: User Manuals & Documentation Hub

### Q138: How does the public booking PWA handle session selection when today's sessions have expired?
**Answer:** When a patient accesses `/book/{public_slug}` after the clinic's final daily session has concluded, the PWA intelligently auto-advances the date picker to the next available working date (tomorrow). This ensures the session selector dropdown is never rendered empty or broken. If a selected date has no active sessions, a clear informational notice is displayed, and the booking submission button is disabled defensively.

### Q139: Where can operators and administrators download or print offline user manuals and quick reference cards?
**Answer:** All operational manuals and quick reference cards are accessible via the central Documentation Hub at `/manuals`. Individual styled guides (Administrator Manual, Doctor Manual, Operator Manual, Superadmin Manual, Role Quick Cards, and Full FAQ) are formatted with print stylesheets (`@media print` and `@page { size: A4; margin: 15mm; }`) and feature a one-click **[Print / Save PDF]** button that produces clean, professional offline documentation without web navigation headers.

### Q140: How does the local hard-drive backup facility work, and who is authorized to run it?
**Answer:** Backups can only be generated and restored by authorized users with the **`ADMIN`** or **`SUPERADMIN`** role. Operators or unprivileged staff attempting access are strictly blocked with an HTTP 403 Forbidden error. Administrators can configure a custom destination folder on the local machine or external drive (e.g. `D:\Backups\DxClinic`), and download the resulting encrypted `.ncbk` archive directly from the browser.

### Q141: What is the companion SHA-256 metadata manifest (.meta.json) generated with backups?
**Answer:** Alongside every `.ncbk` backup archive, DxClinic atomically generates a companion JSON metadata manifest file (`<backup_name>.meta.json`). This manifest contains the database schema version, clinic tenant identifier, timestamp, backup file size, total table row counts, and an authoritative cryptographic SHA-256 checksum of the archive. Administrators can download this manifest independently to verify data integrity and compliance before performing cold-storage archiving or restoration.

### Q142: How does DxClinic manage commercial invoicing and sales reporting without creating duplicate financial records?
**Answer:** DxClinic operates on a strict single-source-of-truth architecture. Instead of introducing a separate, duplicate `platform_payments` table, the commercial invoicing layer derives its financial truth directly from the primary transaction records: `licenses` for initial license sales, `license_renewals` for subscription extensions, and `clinic_doctor_seat_addons` for mid-term doctor additions. The invoicing layer (`platform_sales_invoices` and `platform_invoice_items`) generates customer-facing documents referencing these primary events.

### Q143: What is the authoritative commercial source of truth for each commercial transaction type?
**Answer:**
- **Initial License:** `licenses` table (`paid_amount`, `payment_method`, `payment_reference`, `payment_date`).
- **License Renewal:** `license_renewals` table (`net_amount`, `payment_method`, `payment_reference`, `payment_date`).
- **Mid-Term Doctor Add-On:** `clinic_doctor_seat_addons` table (`price_paid`, `payment_method`, `payment_reference`, `payment_date`).
- **Platform Overhead Expenses:** `platform_expenses` table (`amount`, `category`, `expense_date`, `payment_method`).

### Q144: How are platform invoice numbers formatted and how is duplicate generation prevented?
**Answer:** Platform invoices use globally unique, gap-free numbers formatted as `INV-YYYY-XXXXX` (e.g., `INV-2026-00001`), generated atomically via `platform_invoice_sequences`. Duplicate creation is blocked by a database uniqueness constraint on `[source_type, source_id]`. Rapid double-clicks by administrators or repeated reconciliations safely return the existing invoice without duplicate generation.

### Q145: Can an issued platform sales invoice be edited or modified?
**Answer:** No. Platform sales invoices are strictly immutable once issued. The system's Eloquent model hook blocks any updates to financial amounts (`subtotal`, `discount`, `net_amount`, `collected_amount`), clinic association, or source linkages. If a billing error or commercial dispute occurs, the invoice must be handled through a controlled cancellation mechanism (`status = 'CANCELLED'`), creating an auditable cancellation trail while preserving historical records intact.

### Q146: What is the difference between Platform Commercial Accounting and Clinic Tenant Accounting?
**Answer:** DxClinic enforces complete separation between the software provider's commercial accounting and customer clinics' clinical accounting:
- **Platform Commercial Accounting:** Managed solely by the Superadmin under the `developer` guard (`/superadmin/*`). It tracks SaaS subscriptions, doctor seat additions, collections, and provider overhead expenses (`platform_expenses`).
- **Clinic Tenant Accounting:** Managed by clinic staff under the `web` guard (`/operator/*`, `/admin/*`). It tracks patient consultation fees, pathology tests, medicine bills, drawer cash sessions, clinic expenses (tea, generator fuel), and double-entry journals.
- **Zero Cross-Posting:** Platform expenses have NO `clinic_id`. Provider revenues and expenses never mix with clinic financial books.

### Q147: How is Operating Profit calculated on the Superadmin Sales Dashboard?
**Answer:** Platform operating profit is calculated using the standard accrual commercial accounting formula:
1. `Gross Sales - Discounts = Net Sales`
2. `Net Sales - Refunds = Net Revenue`
3. `Net Revenue - Platform Overhead Expenses = Operating Profit`
All metrics can be filtered by time range (Today, Yesterday, This Week, This Month, Quarter, Half-Year, FY, Custom Range) or by clinic, sale type, and payment channel.

### Q148: What is the difference between Collected Revenue and Operating Profit?
**Answer:** Collected Revenue is the actual cashflow received across Cash, UPI, Bank Transfer, and Cheque channels. Operating Profit is an accounting measure of profitability: Net Revenue earned less Platform Expenses incurred during the period. A sale may be fully collected without being 100% profit (due to server hosting and operational costs), or an invoiced amount may be pending collection (outstanding).

### Q149: How does the Collections Ledger work and what aggregation views are available?
**Answer:** Accessible at `/superadmin/collections`, the Collections Ledger provides a consolidated view of all commercial receipts broken down by payment channel (Cash, UPI, Bank Transfer, Cheque). It provides four distinct viewing modes:
1. **Individual Transactions:** Detailed chronological list with payment date, clinic code, UTR reference, invoice number, and amount.
2. **Daily Aggregation:** Daily summary totals for each payment mode.
3. **Monthly Aggregation:** Calendar monthly collection totals.
4. **Financial Year Totals:** Indian Fiscal Year (April 1 – March 31) collection totals.

### Q150: What is a Mid-Term Doctor Add-On invoice and does it change the clinic's permanent license key?
**Answer:** A Mid-Term Doctor Add-On invoice is issued when a clinic purchases additional doctor capacity during an active subscription (standard default: ₹2,999/doctor/year). It references `clinic_doctor_seat_addons.id`. It does NOT renew the clinic, does NOT generate a new license key, does NOT issue activation codes, and does NOT interrupt clinic operations. The clinic's permanent license key remains 100% unchanged.

### Q151: Are tenant clinic users allowed to view platform sales or invoices?
**Answer:** No. Platform sales, invoices, collections, and platform expenses are strictly restricted to Superadmins (`developer` guard). Tenant users (whether Admin, Doctor, or Operator) attempting to access `/superadmin/sales`, `/superadmin/invoices`, `/superadmin/collections`, or `/superadmin/expenses` are immediately denied access (redirected to Superadmin login for web requests, or returned HTTP 403 Forbidden for API/JSON requests).

### Q152: What is a Booking Type in DxClinic Revision 45, and how does it differ from Consultation Mode and Booking Source?
**Answer:** DxClinic strictly separates four orthogonal operational concepts:
1. **Booking Source:** Where the appointment originated (`CLINIC` for walk-in desk, `ONLINE` for patient PWA, `TELEPHONIC` for phone bookings).
2. **Consultation Mode:** How the consultation is delivered (`CLINIC_PHYSICAL` in-person, `ONLINE` video/tele-consult, `VISITING_CONSULTANCY` home/field visit).
3. **Booking Type:** What clinical service or package the patient is booking (`CLINIC_CONSULTANCY`, `ONLINE_CONSULTANCY`, `EMERGENCY_CONSULTANCY`, or custom types like `Home Healthcare Visit`). Each booking type belongs strictly to one clinic (`booking_types.clinic_id`).
4. **Fee Mapping:** The explicit doctor tariff configured in Fee Master for `(clinic_id + doctor_id + booking_type_id)`.

### Q153: How does doctor-specific booking type availability work?
**Answer:** In **Settings -> Booking Types** (`/settings/booking-types`), administrators can configure an availability matrix for each doctor independently. Enabling *Emergency Consultancy* or a custom type like *Home Healthcare Visit* for Dr. Rajesh Sharma does NOT enable it for Dr. Amit Verma. Each doctor maintains an independent availability roster.

### Q154: Why does a booking type not appear on the Operator Desk or Public PWA for a selected doctor?
**Answer:** Under the Revision 45 **Anti-Fallback Rule**, a booking type is displayed and bookable ONLY when BOTH conditions are met:
1. The booking type is explicitly enabled for that doctor in `doctor_booking_type_availability`.
2. An active tariff rate is explicitly configured for that exact doctor and booking type in `fee_masters`.
If an administrator enables a type for a doctor but forgets to set a fee, the type is excluded from booking to prevent silent fee fallbacks or unexpected charges.

### Q155: Can a doctor borrow another doctor's emergency fee or fallback to a generic clinic fee?
**Answer:** No. Doctor tariffs are completely isolated. Selecting Dr. Amit Verma will never resolve Dr. Rajesh Sharma's emergency fee or a generic system fallback fee. If Dr. Amit does not have a configured fee for *Emergency Consultancy*, emergency bookings are blocked for him until configured. Explicit ₹0.00 fees are supported, but a missing fee record is never treated as zero.

### Q156: What happens to historical appointment fees when an administrator modifies the Fee Master rate?
**Answer:** Modifying a doctor's fee in Fee Master updates only new future bookings created after the change. Every appointment record permanently snapshots `fee_amount` at creation time. Historical appointments, receipts, bills, and PDF reprints remain completely unchanged.

### Q157: How does Revision 46 protect clinic and doctor data integrity at the database layer?
**Answer:** Revision 46 enforces composite unique keys (`id, clinic_id`) on parent tables `doctors` and `booking_types`, and composite foreign keys on child tables `doctor_booking_type_availability`, `fee_masters`, and `appointments`. This ensures MySQL InnoDB physically blocks any cross-clinic doctor, booking type, or fee association with SQLSTATE 23000 integrity violations, preventing corruption even if application code regresses.

### Q158: How does Fee Versioning work with effective dates in Revision 46?
**Answer:** FeeMaster uniqueness is defined as `UNIQUE(clinic_id, doctor_id, booking_type_id, effective_from)`. Different fee rates can be scheduled for different time periods (e.g. ₹600 from 2026-04-01 to 2026-09-30, and ₹700 from 2026-10-01 onwards). Bookings dynamically resolve the fee rate effective on the appointment date, while past appointments remain anchored to their historical snapshot.

### Q159: What is the atomic booking transaction in Revision 46?
**Answer:** All bookings in `AppointmentService::bookAppointment()` execute within a single authoritative `DB::transaction(...)`. The transaction locks the doctor record via `lockForUpdate()`, validates tenant ownership, checks doctor availability mapping, resolves the effective fee, derives consultation mode, allocates the queue token, and writes the appointment snapshot. If any step fails, the entire transaction rolls back completely.

### Q160: Why does the system reject overlapping fee date ranges?
**Answer:** To guarantee unambiguous fee resolution, no two active fee records for the same clinic, doctor, and booking type can have overlapping date intervals. Publishing a new open-ended fee automatically caps the previous open-ended record to end on the day before the new fee starts, preventing ambiguity in tariff calculations.

### Q161: What is a clinic's public slug and why is it used instead of clinic code?
**Answer:** In Revision 47, clinics are addressed publicly by a human-friendly `public_slug` (e.g. `sharmacare`, `arogya`, `democlinic`) stored in `clinics.public_slug` with a database unique index. Internal clinic identifiers (`id` and `clinic_code` like `NX0001`) are strictly isolated to internal administration and database keys, preventing exposure to external patients and eliminating enumeration attacks.

### Q162: What happens if a patient visits `/book` without a slug, or enters an internal clinic code like `/book/NX0001`?
**Answer:** Both requests return **HTTP 404 Not Found**. Unselected `/book` does not default to any clinic to prevent accidental booking into the wrong tenant. Similarly, internal clinic codes (`NX0001`, `1`) are strictly forbidden in public URLs. The booking PWA resolves only valid, registered `public_slug` values.

### Q163: How does the short booking URL `/b/{public_slug}` work?
**Answer:** The short URL `/b/{public_slug}` is designed for QR code density optimization and printed counter posters. When accessed, it immediately issues an HTTP 302 redirect to the canonical `/book/{public_slug}` URL. Unselected `/b` returns **HTTP 404**.

### Q164: How is the Operator Arrival Queue protected against cross-clinic access in Revision 47?
**Answer:** The operator queue is tenant-scoped at `/clinic/{public_slug}/operator/queue`. The route middleware verifies that the requested `public_slug` matches the authenticated staff member's clinic ID. If an operator from Clinic A attempts to access Clinic B's queue URL, the request is immediately rejected with **HTTP 403 Forbidden**. Unselected `/operator/queue` returns **HTTP 404**.

### Q165: How does DxClinic prevent cross-tenant parameter tampering in requests?
**Answer:** `EnsureTenantContext` middleware inspects all incoming requests for tenant override attempts. If an attacker injects query parameters (`?clinic_id=`), HTTP headers (`X-Clinic-ID`, `X-Tenant-ID`, `X-Clinic-Code`), or POST body attributes attempting to override or mismatch the authoritative session or route clinic, the request is immediately aborted with **HTTP 403 Forbidden**.

### Q166: What is the Diagnostic Laboratory Workflow in Revision 36?
**Answer:** Revision 36 introduces a dedicated clinical laboratory workflow accessible at `/lab` that extends the diagnostic lifecycle after billing:
`Diagnostic Order -> Diagnostic Bill/Payment -> Specimen Collection -> Specimen Tracking -> Result Entry -> Clinician Verification -> Report Release -> Patient Diagnostic Report PDF`.
Existing diagnostic billing remains the authoritative, financially immutable source of truth.

### Q167: How are human-readable Specimen Identifiers allocated?
**Answer:** Specimen identifiers are generated via a concurrency-safe, monotonic, clinic-scoped sequence in the format `SMP-{CLINIC_CODE}-{YYYYMMDD}-{XXXX}` (e.g., `SMP-NX0001-20260930-0001`). Specimen IDs are non-guessable, unique per clinic day, never reused, and printed on all specimen labels, tracking views, and diagnostic reports.

### Q168: How is duplicate specimen collection prevented?
**Answer:** Specimen collection is strictly idempotent. When an operator collects a specimen, row-level locking and status validation ensure that double-clicking or re-submitting cannot create duplicate specimens. Once collected, the "Collect Sample" button is replaced by the active specimen identifier badge and result entry actions.

### Q169: What types of test results are supported in Structured Result Entry?
**Answer:** Result entry supports multiple clinical data types defined in the diagnostic item master:
1. **Numeric:** Continuous values with units, biological reference ranges, and critical panic thresholds (e.g., Blood Glucose 140 mg/dL).
2. **Text / Narrative:** Descriptive findings, microscopical examinations, and morphological comments.
3. **Qualitative / Semi-quantitative:** Positive/Negative, Reactive/Non-reactive, Nil, Traces, or graded values.
Values falling outside biological reference ranges are flagged with high-visibility abnormal tags (`[ABNORMAL]`).

### Q170: Who is authorized to enter, verify, and release diagnostic results?
**Answer:** DxClinic enforces strict role-based access control (RBAC):
- **Operators:** Can view the worklist, collect specimens, track sample status, and enter draft results. Operators are strictly forbidden from verifying results or releasing final reports.
- **Doctors & Admins:** Have full clinical authority to review entered results, verify findings, authorize releases, and perform controlled corrections.

### Q171: Can a verified or released result be edited silently?
**Answer:** Absolutely not. Once a result is VERIFIED or RELEASED, direct inline editing is blocked. If an error is detected post-release, the system requires a **Controlled Result Correction**:
1. The user must provide a documented, non-trivial clinical correction reason.
2. The original result is marked `CORRECTED` and preserved in the database.
3. A new versioned result record is created with the updated value and linked to the editor.
4. An immutable entry is written to `audit_logs` capturing the previous value, new value, reason, timestamp, and user ID.

### Q172: Does the clinical laboratory workflow affect bills, cash drawers, or accounting ledgers?
**Answer:** No. Clinical workflows and financial accounting are completely decoupled. Collecting specimens, entering results, verifying findings, releasing reports, or correcting values produces zero financial transactions, zero ledger entries, zero cash session movements, and zero modifications to existing diagnostic bills or payment receipts.

### Q173: How does the public QR report verification endpoint work?
**Answer:** Every released diagnostic report features a secure QR code resolving to `https://dxclinic.example.local/verify-lab-report/{report_number}`. Scanning the code opens a secure public verification landing page confirming the report's authenticity, patient details, specimen identifier, release timestamp, and verifying clinician without exposing administrative controls or sensitive internal database IDs.

### Q174: Is reprinting a diagnostic report safe and idempotent?
**Answer:** Yes. Accessing or printing `/pdf/diagnostic-result-report/{reportId}` is 100% idempotent. It regenerates the exact diagnostic report from the immutable clinical snapshot without generating new orders, specimens, results, or accounting entries.

### Q175: Where do released diagnostic reports appear in Patient History?
**Answer:** In the Patient Management screen (`/patients`), opening **Patient History** displays an integrated timeline showing diagnostic orders, specimen collection status, and released diagnostic reports. Clinicians and operators can download or view released reports directly from the patient's longitudinal record, while unverified draft results remain hidden from final history.

### Q176: Is mobile OTP implemented in Revision 36 or Revision 37?
**Answer:** No. Mobile OTP verification (SMS OTP / WhatsApp OTP) is explicitly deferred to a future phase. Revision 37 introduces zero OTP database tables, zero OTP services, zero OTP challenge logic, and zero OTP UI screens.

### Q177: What are the key enhancements introduced in the Revision 37 Prescription Pad Builder?
**Answer:** Revision 37 delivers a professional visual layout editor for prescription pads with desktop design-tool interaction standards (inspired by CorelDRAW and Photoshop):
1. Dedicated Hand/Pan tool and temporary Space + Drag panning.
2. Smooth zooming (50% to 200%) with pannable canvas at any zoom level.
3. 60fps real-time field dragging and resizing with an 8-handle system.
4. Complete typography isolation per element (font size, family, weight, style, color, alignment).
5. Independent coexistence of multiple Static Text elements with unique persistent identities.
6. Millimeter coordinate stability and exact DomPDF physical point reproduction.

### Q178: How do I navigate and pan the prescription pad workspace after zooming?
**Answer:** You can pan the canvas horizontally and vertically using two convenient methods:
- **Hand Tool:** Click the **Hand (H)** button in the designer toolbar or press `H`. Then click and drag anywhere on the workspace to shift the viewport.
- **Space + Drag Shortcut:** While in the default **Select (V)** tool, press and hold the `Spacebar` to temporarily activate the Hand cursor, click and drag to pan, and release the `Spacebar` to resume selection mode immediately.
Panning changes only the viewport transform and never alters stored document coordinates in millimeters.

### Q179: How does real-time field movement and resizing work?
**Answer:** When moving an element or dragging any of the 8 resize handles (NW, N, NE, E, SE, S, SW, W), visual DOM updates occur instantaneously at 60fps directly within the browser without waiting for server round-trips. Group translations, dimensions, bounding outlines, coordinate badges, and handles update smoothly. Final coordinates in millimeters are committed and persisted upon mouse/pointer release (`pointerup`).

### Q180: Can I move elements using the keyboard for high precision?
**Answer:** Yes. When any element is selected:
- **Arrow Keys:** Nudge position by `0.5 mm` in any direction.
- **Shift + Arrow Keys:** Fine micro-adjust position by `0.1 mm`.
This allows pixel-perfect alignment against printed stationery guidelines.

### Q181: How is typography isolated between different prescription fields?
**Answer:** Every element maintains its own independent typography configuration (`font_family`, `font_size`, `font_weight`, `font_style`, `text_align`, `color`, `line_height`). Changing the font size or family of one element (e.g., Patient Name to 16 pt) strictly updates only that selected element. No shared global font state exists.

### Q182: How do multiple Static Text elements coexist without overwriting each other?
**Answer:** Each Static Text element is created as an independent record with a unique identifier (`static_text_{num}_{hash}` and unique `uid`). Creating Static Text B never overwrites or reuses Static Text A. Multiple static notices (e.g., consultation timings, emergency contact numbers, clinic registration notices) coexist with distinct contents, positions, and typography.

### Q183: What is the difference between Proof View and Production View?
**Answer:**
- **Proof View:** Displays the uploaded high-resolution scan of the pre-printed clinic letterhead behind the canvas elements, allowing precise visual alignment of fields against physical boxes and lines.
- **Production View:** Hides the background scan, displaying only the dynamic data and static text elements that will be printed onto the physical pre-printed stationery.

### Q184: How are font sizes mapped between the editor and the final printed PDF?
**Answer:** Font sizes represent true typographical point sizes (`1 pt = 1/72 inch = 0.352778 mm`). Browser zoom and display scaling do not alter the saved point size. When DomPDF compiles the prescription PDF, a 12 pt font renders at exactly 12 points, ensuring absolute fidelity between on-screen design and physical paper output.

### Q185: How do I test print or download a sample prescription PDF?
**Answer:** In the designer header, click the **Test Print ▾** dropdown and select:
- **Production PDF (Data Only):** Downloads the clean PDF containing only text, lines, and barcodes for printing on pre-printed stationery.
- **Proof PDF (With Scan):** Downloads the combined PDF with the reference scan for proofreading and counter validation.

### Q186: Does saving or duplicating a template affect historical patient prescriptions?
**Answer:** No. Historical consultations reference their specific immutable template ID and version snapshot. Activating a new template version safely archives the prior active template. Archived templates used by past appointments remain read-only and are never modified by the editor.

### Q187: How does cursor-centered mouse-wheel zoom work in the Prescription Pad Designer?
**Answer:** Rolling the mouse wheel over the designer workspace adjusts zoom smoothly (Wheel UP = Zoom IN, Wheel DOWN = Zoom OUT). Crucially, zooming occurs around the exact pointer location: the point on the document under your cursor remains visually stationary while the canvas expands or contracts around it. This is computed mathematically via an inverse viewport transform ($panX_{new} = xm - (xm - panX) \times (newZoom / oldZoom)$). Cursor-centered wheel zooming modifies only local CSS viewport transformations, never shifts stored document coordinates in millimeters, and triggers zero Livewire network requests during wheel events. When the pointer moves outside the editor canvas, standard browser window scrolling remains unaffected.

### Q188: How do I upload, place, and resize custom images or logos on a prescription pad?
**Answer:** Click the **[Image]** button in the designer toolbox to open the file selection dialog. Select any valid PNG or JPG/JPEG image (up to 5MB, e.g. clinic logo, accreditation seal, doctor emblem, or digital signature). The system creates a new `IMAGE` element with a unique persistent identity (`image_{num}_{hash}`), places it centered on the current visible canvas preserving its native aspect ratio, and selects it immediately. You can drag the image in real-time at 60fps or resize it using the 8 boundary handles (NW, N, NE, E, SE, S, SW, W). Multiple image elements can coexist independently without overwriting each other.

### Q189: How does aspect ratio locking work for image elements during resizing?
**Answer:** By default, every image element has **Lock Aspect Ratio** enabled (`lock_aspect_ratio: true`). When resizing from any corner handle (NW, NE, SE, SW), the width and height scale proportionally based on the original image dimensions ($w/h = \text{aspect\_ratio}$), preventing accidental stretching or distortion. If non-uniform scaling is desired, you can uncheck **Lock Aspect Ratio** in the Image Properties panel. You can also adjust image opacity (0% to 100%) and fine-tune physical millimeter dimensions directly.

### Q190: How are inserted image elements rendered in Proof vs. Production PDFs, and how is upload security enforced?
**Answer:** User-inserted `IMAGE` elements represent true printable design objects and render in both **Proof PDF** and **Production PDF** at exact physical millimeter coordinates. They are completely separate from the background scan layer (which is visible only in Proof mode and strictly suppressed in Production mode). On the server side, image uploads undergo rigorous security validation: file extension, genuine MIME type, `getimagesize()` image integrity verification, 5MB file size limit, and randomized server-side storage names (`img_elem_{templateId}_{uniqid}_{time}.{ext}`) stored under clinic-scoped storage paths. Executable files, scripts, or disguised binaries are strictly rejected.

### Q191: How does multi-selection and marquee rectangular selection work in the Prescription Pad Designer?
**Answer:** The designer supports professional multi-selection via:
- **Ctrl/Cmd + Click:** Toggles individual elements into or out of the active selection set without deselecting other items.
- **Shift + Click:** Adds or removes elements from selection adhering to desktop design software conventions.
- **Marquee (Lasso) Selection:** Clicking empty canvas area and dragging draws a blue translucent marquee rectangle. Any element enclosed by or intersecting the marquee rectangle is immediately selected upon pointer release.
When multiple elements are selected, the right sidebar transforms into a dedicated Multi-Selection Panel displaying the total count (e.g. `3 elements selected`) and batch operations (alignment relative to bounds, equal spacing distribution, duplicate, delete, and lock) while safely hiding misleading single-element inputs. Continuous dragging of multi-selected objects moves all items together at 60fps with zero intermediate network requests.

### Q192: How do alignment and distribution tools operate on single vs. multiple elements?
**Answer:** The alignment engine respects physical millimeter document coordinates:
- **Single Selected Element:** Align commands (Left, Center, Right, Top, Middle, Bottom) align the element directly relative to the physical page boundaries ($X=0$, $X=\text{paperW}/2$, $X=\text{paperW}-w$, etc.).
- **Multiple Selected Elements:** Align commands compute the collective bounding box enclosing all selected elements ($\min X, \max X, \min Y, \max Y$) and align all items relative to that common boundary.
- **Distribution (3+ Elements):** When 3 or more elements are selected, **Distribute Horizontally** or **Distribute Vertically** calculates the total available span between the outermost elements, subtracts item widths/heights, and spaces all intermediate elements with mathematically equal margins in physical millimeters.

### Q193: What are Smart Guides and Smart Snapping, and do they affect the saved template?
**Answer:** Smart Guides are lightweight, temporary visual aids inspired by professional CAD and graphic design software. While dragging an element near key document anchors (page vertical/horizontal center, page borders, or neighboring element edges/centers), dynamic pink dashed guide lines appear on screen. If **Snap** is toggled ON, the dragged element gently snaps to the guide coordinate within a small physical tolerance (e.g. 2 mm). Smart guides are 100% ephemeral: they exist purely in the browser's SVG viewport layer and are never saved to the database or rendered in printed PDFs.

### Q194: How does the Layers panel manage object stacking order (Z-Order)?
**Answer:** The **Layers** sidebar tab displays all canvas elements in their exact rendering stack order from front (top) to back (bottom). Each row displays the element type icon, label, and visibility toggle. Admins can click **Bring to Front**, **Bring Forward**, **Send Backward**, or **Send to Back** to rearrange stack priority. This order is persistently stored in the database `sort_order` column. Most importantly, the DomPDF print engine iterates through elements in this exact persisted order, guaranteeing that SVG canvas visual overlap perfectly matches final printed PDF stacking.

### Q195: How does element locking protect fixed clinic branding and prevent accidental edits?
**Answer:** Any canvas element (clinic logo, header bar, doctor registration number, Rx symbol) can be locked by clicking the **Lock** button in its properties panel. When locked:
- The element cannot be dragged or repositioned.
- Resize handles are hidden, preventing accidental dimensional changes.
- Nudge keystrokes are ignored for the locked element.
- The element displays a distinct `[LOCKED]` badge and cannot be accidentally modified during bulk operations.
Unlocking requires a deliberate click on **Unlock** in the properties panel or Layers list. Locking is an editor interaction safeguard and does not affect PDF output: locked elements render normally in both Proof and Production PDFs.

### Q196: How does the Undo/Redo transaction model and element rotation function?
**Answer:** 
- **Undo / Redo History:** The designer maintains a robust document history stack accessible via toolbar buttons (`↶ Undo`, `↷ Redo`) and standard keyboard shortcuts (`Ctrl+Z`, `Ctrl+Shift+Z` or `Ctrl+Y`). Every user action (dragging an object, resizing, nudging, aligning, distributing, changing typography, duplicating, deleting, locking, or rotating) creates a single atomic history transaction. Viewport zoom and pan operations are strictly isolated and do not pollute document history.
- **Element Rotation:** Elements support free rotation from $0^\circ$ to $360^\circ$ around their physical center anchor point (`transform-origin: center center;`). Rotation is persisted in the element's `rotation` column and reproduced with physical coordinate fidelity in DomPDF using CSS transformation matrices.

### Q197: What is Prescription Pad Template Designer?
**Answer:** The Prescription Pad Template Designer (`/settings/prescription-pad-templates` and `/settings/prescription-pad-designer/{templateId}`) is a configuration-driven visual report layout engine for outpatient prescription stationery. It behaves like a modern report designer where administrators can define doctor-specific prescription layouts, upload physical pad reference scans, place print-safe dynamic data controls (patient demographics, visit tokens, vitals, clinic details) and static annotations, configure typography per element, save coordinates in physical millimeters, calibrate printer hardware, and generate high-fidelity Proof and Production PDFs.

### Q198: Can every doctor have a different prescription pad?
**Answer:** Yes. Every prescription pad template is strictly scoped to a specific attending doctor (`doctor_id`) and clinic (`clinic_id`). Doctor A can use an A4 pad with blood pressure and RBS, Doctor B can use an A5 pad with pediatric weight and height, and Doctor C can use custom stationery dimensions. The system resolves the template based on the actual attending physician of the patient's encounter.

### Q199: How do I create a prescription pad template?
**Answer:** Navigate to **Settings -> Prescription Pad Templates** (`/settings/prescription-pad-templates`) as an Administrator. Click **New Template**. Select the Attending Doctor, enter a Template Name (e.g., `Dr. Sharma Standard Pad`), choose Paper Size (A4, A5, Letter, Half Letter, or Custom in mm) and Orientation (Portrait or Landscape), and click **Create & Open Designer**. The system creates a new template in `DRAFT` status and opens the visual SVG designer.

### Q200: How do I upload a prescription pad scan?
**Answer:** In the designer workspace, switch the left panel to the **Settings** tab. Under **Reference Background Scan**, click **Choose File** and select a high-resolution scan or photo of the doctor's pre-printed letterhead (PNG, JPG, or JPEG, up to 10MB). Click **Upload Reference Scan**. The image is loaded behind the design canvas at exact physical paper scale to guide visual field alignment.

### Q201: What paper sizes are supported?
**Answer:** DxClinic natively supports **A4** (210 × 297 mm), **A5** (148 × 210 mm), **Letter** (215.9 × 279.4 mm), **Half Letter** (139.7 × 215.9 mm), and **Custom** dimensions. For custom stationery, admins can specify exact physical width and height in millimeters (e.g. 175 × 240 mm). Orientation can be set to Portrait or Landscape.

### Q202: Why are coordinates stored in millimeters?
**Answer:** Storing screen pixels (e.g., `x = 420px, y = 620px`) would cause physical print misalignments across different monitor screen resolutions, device pixel ratios (DPR), viewport zooms, and operating systems. By persisting physical coordinates strictly as `x_mm`, `y_mm`, `width_mm`, and `height_mm`, dimensions are hardware-independent. DomPDF converts millimeters directly into standard typographical points (`1 mm = 72 / 25.4 pt = 2.8346 pt`), guaranteeing 100% actual-size physical printing accuracy on any printer.

### Q203: How do I drag a field?
**Answer:** In the designer canvas, click on any element to select it. With the **Select Tool (V)** active, click and drag the element across the canvas. Dynamic coordinate badges update in real-time at 60fps. Upon mouse/pointer release (`pointerup`), the new position is saved in physical millimeters. Multiple selected elements can also be dragged simultaneously.

### Q204: How do I edit X/Y manually?
**Answer:** Select the element on canvas. In the right **Properties Panel**, locate the **Position & Dimensions** section. Type the exact values in the **X (mm)** and **Y (mm)** input fields (e.g., `X = 35.20`, `Y = 48.50`). Changing the numeric value immediately updates the on-screen visual position and persists to the template definition.

### Q205: How do I select which vitals appear?
**Answer:** In the designer's **Field Toolbox**, expand the **Vital Signs** section. Click on any vital button (e.g., *Blood Pressure*, *Pulse Rate*, *SpO2*, *Temperature*, *Weight*, *Height*, *Respiratory Rate*, *RBS*) to drop it onto the canvas. Move the vital box to its designated print zone on the stationery. If a vital should not be printed, either delete the element from the canvas or toggle **Visible = Off** in its Properties panel.

### Q206: Can different doctors have different fields?
**Answer:** Absolutely. Field selection, layout placement, and typography are 100% doctor-specific. Doctor A's template may include BP, Weight, SpO2, and Token Number, while Doctor B's template includes Pediatric Age in Months, Weight, Height, and Doctor Registration Number. Each template maintains its own independent set of `prescription_pad_elements`.

### Q207: How do I create a new template version?
**Answer:** To preserve historical reproducibility, ACTIVE templates cannot be destructively overwritten. To make changes: in the template list (`/settings/prescription-pad-templates`), click **Duplicate** on the current active template (or click **Duplicate as New Version** inside the designer). The system creates a new template in `DRAFT` status with an incremented version number (e.g., V1 -> V2). Modify, test, and preview the draft. When ready, click **Activate**.

### Q208: What is Proof PDF?
**Answer:** **Proof PDF** is a verification document that renders the scanned prescription pad artwork in the background with the dynamic patient data overlaid on top. It is clearly marked with a *PROOF COPY* badge and is used by clinic administrators and doctors to verify on-screen visual alignment against physical boxes, pre-printed headers, and printed margin boundaries.

### Q209: What is Production PDF?
**Answer:** **Production PDF** is the final clinical document printed at the doctor's desk. It renders **dynamic data fields only** on a clean, blank background with zero scanned letterhead artwork. It is fed into the clinic printer preloaded with the doctor's physical pre-printed stationery, ensuring crisp, fast, toner-efficient printing without duplicating printed letterheads or logos.

### Q210: Why is the scanned pad not printed in production?
**Answer:** Physical prescription pads already contain the doctor's printed header, clinic logo, registration details, decorative borders, and clinical stationery artwork. Printing the scanned background would waste printer toner, produce muddy double-printed text, and risk legal/branding distortions. Production PDF prints strictly variable data onto pre-existing physical stationery.

### Q211: How do I test print?
**Answer:** In the designer header, open the **Test Print ▾** menu and select **Production PDF (Data Only)** or **Proof PDF (With Scan)**. Alternatively, from the template management table, click the **Test Print** or **Proof** button. The system generates a PDF populated with standardized sample patient data (`TEST PATIENT`, `Age: 40/M`, `BP: 120/80`, `Weight: 70 kg`, etc.) rendered at 100% actual physical size for test printing on counter paper.

### Q212: What is printer calibration?
**Answer:** Physical office printers often introduce mechanical feed margins or systematic paper offsets (e.g., feeding 2mm to the right or 1.5mm down). Rather than altering coordinates on every individual template field, Administrator can open **Printer Calibration** (`/settings/prescription-pad-templates` -> Printer Calibration) and configure global hardware offsets: `X Offset (mm)` (shifts right/left), `Y Offset (mm)` (shifts down/up), and Scale multipliers (`Scale X`, `Scale Y`). These offsets are applied globally at PDF render time.

### Q213: How do I activate a template?
**Answer:** On the Template Management table (`/settings/prescription-pad-templates`) or in the designer header, click **Activate**. The system checks that the template has elements, marks the selected template status as `ACTIVE` (`is_active = true`, `activated_at = now()`), and safely transitions any previously active template for that doctor to `ARCHIVED`. From that moment, new OPD appointments for that doctor automatically use the newly activated template.

### Q214: What happens to the old template?
**Answer:** The old template is automatically transitioned to `ARCHIVED` (`status = 'ARCHIVED'`, `is_active = false`). It is NEVER deleted. All historical patient encounters that were printed using the old version permanently retain their link to that exact archived version, guaranteeing 100% historical reprint reproducibility.

### Q215: What happens to the previous legacy Doctor Pad setting?
**Answer:** The legacy single-doctor Doctor Pad configuration (`print.doctor-pad` Blade template and legacy coordinates) is permanently preserved in the codebase as a fallback. If an attending doctor does NOT have an active template in the new designer system, the template resolver automatically falls back to the legacy renderer. No legacy settings or coordinates are destroyed.

### Q216: How are historical Doctor Pads handled?
**Answer:** In **Doctor Pad History** (`/operator/consultation` -> Doctor Pad History tab), every past encounter displays its Attending Doctor, Patient, Token, Date, and the exact Template and Version used. Clicking **Reprint** resolves the specific template version recorded at encounter time. If the encounter was generated under legacy settings, it reprints using the legacy engine; if generated under V1, it reprints using V1 even if V2 is currently active.

### Q217: How do I view old consultation bills?
**Answer:** Open **Consultation Billing** (`/operator/consultation`) and switch to the **Consultation Billing History** tab. Operators and Admins can view all past consultation bills with multi-parameter filtering: Date From, Date To, Patient ID (`DXP-...`), Patient Name, Mobile Number, Bill Number, Token Number, Doctor, Consultation Mode, Payment Method, and Status, with responsive pagination.

### Q218: How do I edit a consultation bill?
**Answer:** In the Consultation Billing History table, every eligible row displays an **[Edit]** button located strictly in the order: `[View] -> [Edit] -> [View Pad] -> [View Changes] -> [Refund]`. Clicking **[Edit]** opens the controlled modification workflow modal. Direct overwriting of settled financial records is blocked; adjustments require a mandatory reason and are processed via audited refund or non-financial correction.

### Q219: Why can't a paid bill be directly overwritten?
**Answer:** Settled financial bills, payment records, customer receipts, and double-entry accounting journals are legally and commercially immutable. Direct database overwriting would cause cash drawer reconciliation discrepancies, invalidate customer receipts, violate audit trail integrity, and enable financial fraud. All financial modifications must be executed through controlled refund or supplementary billing workflows.

### Q220: How do I give a discount after payment?
**Answer:** If an attending doctor recommends a courtesy discount after the patient has paid (e.g., fee was ₹600, doctor recommends ₹100 discount): do NOT edit the gross fee to ₹500. Instead, click **[Edit]** or **[Refund]** on the settled bill, select refund type, enter the refund amount of ₹100, select the payout method (`CASH` from drawer session or `UPI` from bank), provide a mandatory audit reason (min 10 chars), and process the refund. The original bill remains ₹600, a ₹100 refund record is issued, and ₹500 is net retained.

### Q221: How do I process a partial refund?
**Answer:** On the paid consultation bill, click **[Refund]** or **[Edit]**. Enter the specific refund amount (must be <= settled payment minus prior processed refunds). Select the settlement method (`CASH` requires active cash session; `UPI` requires destination bank account and UTR reference). Enter a mandatory descriptive reason (>= 10 characters). Upon processing, the system updates the bill refund status, creates a receipt revision, logs an audit entry, and posts balanced debit/credit accounting entries.

### Q222: How do I process a full refund?
**Answer:** On the paid bill, initiate the refund workflow and select **Full Refund**. The system automatically sets the refund amount to the full remaining refundable balance. Select the physical payout method (Cash/UPI), enter the mandatory reason, and confirm. The consultation bill status updates to `REFUNDED`, the appointment status reflects the cancellation/refund, and an immutable audit log is created.

### Q223: How do I edit a diagnostic bill?
**Answer:** In **Diagnostic Billing** (`/operator/diagnostics`), switch to the **Diagnostic Billing History** tab. Locate the bill row where the action buttons are arranged in strict sequence: `Status | Edit | Method`. Click **[Edit]**. For unsettled bills, tests and rates can be updated with server-side recalculation. For settled bills, original line items and historic rates remain immutable: fee reductions are handled via refunds, while additional tests are handled via supplementary diagnostic billing.

### Q224: What happens when a diagnostic bill needs to increase?
**Answer:** If a patient requires additional investigations after their diagnostic bill has been paid and settled (e.g., initial bill was ₹1,100, doctor adds a ₹300 thyroid test), the historical paid bill is NEVER rewritten to ₹1,400. Instead, authorized staff creates a **Supplementary Diagnostic Bill** linked to the parent bill for ₹300, which is independently paid, receipted, and accounted.

### Q225: What is supplementary diagnostic billing?
**Answer:** Supplementary diagnostic billing is an accounting-safe workflow where additional medical laboratory tests requested after initial payment settlement are billed as an independent child invoice linked via `parent_diagnostic_bill_id`. Both bills maintain distinct receipts and payments, preserving the mathematical integrity of historical transactions and drawer sessions.

### Q226: What reason is required for a modification?
**Answer:** Any financial or clinical modification requires a descriptive, meaningful reason of at least **10 characters**. Generic placeholders such as `"edit"`, `"change"`, `"ok"`, `"mistake"`, or whitespace-padded entries are strictly rejected by server-side validation. Accepted examples: *"Refund for discount given as per doctor recommendation"*, or *"Incorrect diagnostic package selected during counter registration"*.

### Q227: Can a doctor directly process a refund?
**Answer:** No. Attending physicians have complete clinical authority to submit discount or refund *recommendations* (`doctor_instruction_by`, `recommendation_amount`, `recommendation_reason`). However, doctors are strictly prohibited from settling cash, transferring bank funds, or posting journal entries. Actual financial execution must be performed by authorized billing staff or administrators.

### Q228: Can an operator directly modify a settled bill?
**Answer:** Operators can initiate refund requests, record doctor recommendations, and make permitted non-financial corrections (e.g. spelling of patient name). However, financial refunds exceeding established policy limits require Administrator approval. Operators cannot bypass transaction locking or alter accounting ledgers directly.

### Q229: Where can I see the audit history?
**Answer:** In billing history rows, clicking **[View Changes]** opens the complete audit trail modal. Administrators can also inspect system-wide audit records at `/admin/audit-logs`. The audit trail details: who made the change (User ID, Name, Role), what category (`EDIT`, `REFUND`, `DOCTOR_RECOMMENDATION`, `SUPPLEMENTARY_BILL`), exact timestamp, reason provided, previous values, new values, and financial impact.

### Q230: Does reprinting create another payment?
**Answer:** Never. Reprinting any patient document — whether a Consultation Receipt (`/print/consultation-receipt/{id}`), Diagnostic Receipt, Lab Collection Slip, or Doctor Pad (`/pdf/doctor-pad/{id}`) — is strictly **idempotent**. Reprinting generates zero new payments, zero accounting journals, zero refunds, zero tokens, and zero duplicate visits.

### Q231: Why are some vitals blank?
**Answer:** In DxClinic, clinical truth is paramount. If a vital sign (e.g. RBS or Temperature) was not physically measured or recorded by the nurse/operator during patient check-in, its field is rendered completely blank on the Doctor Pad. The system never fabricates fake default values (such as `0` or `N/A`) for missing clinical observations.

### Q232: What happens when a vital is not recorded?
**Answer:** When an encounter does not record a specific vital sign, the Prescription Data DTO passes `null` for that key. The template renderer suppresses output for that vital box or leaves the designated zone empty so the physician can manually write observations onto the physical stationery during examination.

### Q233: Why does the Doctor Pad use the doctor's template?
**Answer:** Every medical specialist has distinct clinical requirements: a cardiologist requires prominent BP and Pulse zones, a pediatrician needs Height and Weight percentile spaces, while a general physician requires general demographic coordinates. By resolving the active template belonging to the specific attending doctor of the encounter, each physician gets their preferred stationery layout automatically.

### Q234: How do I calibrate a real printer?
**Answer:** In **Settings -> Prescription Pad Templates**, click **Printer Calibration**. Enter fine-tuned X Offset (mm), Y Offset (mm), Scale X, and Scale Y. Print a Test Production PDF on physical paper at Actual Size (100%), measure the shift between the printed dynamic text and the pre-printed stationery boxes using a physical ruler/caliper, adjust the offsets accordingly (positive X shifts right, negative shifts left; positive Y shifts down, negative shifts up), and click **Save Calibration**.

### Q235: Why does software-perfect PDF still need physical calibration?
**Answer:** Every physical printer hardware mechanism (feed rollers, tray guides, paper pick-up margins, drum mechanics) introduces minor mechanical physical shifts of 1 to 4 millimeters. While the software PDF is mathematically exact, physical calibration compensates for these printer-specific mechanical tolerances so that printed ink lands precisely inside pre-printed paper borders.

### Q236: What is a Printer Profile?
**Answer:** A **Printer Profile** (stored in `printer_profiles`) defines hardware-specific physical calibration offsets (`offset_x_mm`, `offset_y_mm`, `scale_x`, `scale_y`) for a particular printer workstation. It applies translation transformations at print time without modifying the underlying template coordinates.

### Q237: Why should printer offsets not be stored in template coordinates?
**Answer:** Storing printer offsets directly in template field coordinates breaks portability. If an offset were saved into the template coordinates, that template would be misaligned when printed on a different printer or workstation in the clinic. Decoupling the printer profile from template coordinates ensures the same template design works seamlessly across multiple printers by simply selecting each printer's calibration profile.

### Q238: How do I perform a Test Print?
**Answer:** In the Prescription Pad Designer, click the **Test Print ▾** dropdown on the top right toolbar. Select **Production PDF (Data Only)** to test physical overlay printing onto pre-printed stationery, or select **Proof PDF (With Scan)** to test alignment against the stationery background scan on blank A4 paper.

### Q239: How do I measure print position?
**Answer:** Take a printed physical sheet and use a transparent millimeter ruler or digital caliper. Measure the distance from the top and left paper edge to a prominent field (such as Patient Name or BP). Compare the measured physical position against the template's designed coordinates: `Difference = Actual Position - Expected Position`. Set the printer calibration offset to `-Difference` to correct the shift.

### Q240: What is Actual Size printing?
**Answer:** Actual Size printing means the PDF viewer or printer driver prints the document at exactly **100% scale** (1:1 scale) without performing software scaling. 1 millimeter in the PDF corresponds to exactly 1 physical millimeter on the printed paper sheet.

### Q241: Why should "Fit to Page" be disabled?
**Answer:** Operating system print dialogs often enable "Fit to Printable Area" or "Shrink oversized pages" by default, reducing the document to ~94-96% to fit inside non-printable hardware margins. This scaling shrinks all millimeter distances, causing pre-printed boxes and printed values to drift out of alignment across the page. Always select **Actual Size (100%)** and disable "Fit to Page".

### Q242: How do I verify PDF vs physical paper?
**Answer:** Hold the printed test sheet directly over an original blank pre-printed prescription pad sheet against a bright window or lightbox. Verify that every patient field, date, token, and vital observation sits comfortably within its corresponding pre-printed border without overlapping pre-printed headers or rules.

### Q243: Why can Hindi appear incorrectly?
**Answer:** Traditional PDF Type 1 standard PostScript fonts (such as Helvetica, Courier, and Times-Roman) only include the 128-character Latin ASCII / Latin-1 glyph sets. When non-Latin characters (such as Hindi or Bengali Devanagari glyphs) are rendered using these legacy fonts, the PDF engine cannot resolve the glyphs and substitutes question marks (`?`) or unprintable box symbols (`□`).

### Q244: How do I enable Unicode fonts?
**Answer:** DxClinic integrates official TrueType Unicode fonts (such as `Noto Sans Devanagari` and `DejaVu Sans`). When rendering templates or patient records containing Hindi or regional languages, ensure the element font family is set to **Noto Sans Devanagari** or relies on DxClinic's automatic Unicode font fallback chain.

### Q245: Which fonts are supported?
**Answer:** The production PDF renderer officially supports:
1. **Noto Sans Devanagari** (Regular & Bold) - Complete support for Hindi, Sanskrit, Marathi, Nepali, and mixed English/Devanagari text with TrueType subsetting.
2. **DejaVu Sans** - Full extended Latin and European Unicode character sets.
3. **Helvetica** - Fast, lightweight standard Latin font for English stationery.
4. **Arial** - Standard sans-serif Latin font.
5. **Times-Roman** - Traditional serif font for clinical stationery.
6. **Courier** - Monospaced font for tabular or code alignment.

### Q246: How do I print Hindi/Devanagari?
**Answer:** Enter the patient's name, address, or clinical instructions in Hindi (e.g., `राम कुमार`, `वाराणसी, उत्तर प्रदेश`). In the Prescription Pad Designer, select **Noto Sans Devanagari (Hindi/Unicode)** as the font family, or leave default font fallback active. The system embeds the required TrueType glyph subsets into the production PDF stream for flawless physical print output.

### Q247: What should I do when Hindi displays as '?'?
**Answer:** If Hindi text displays as question marks, the document is rendering using legacy Helvetica without TrueType font subsetting. In the Prescription Pad Designer, select the element, open the **Properties** panel, change the **Font Family** dropdown to **Noto Sans Devanagari (Hindi/Unicode)**, and save the template draft.

### Q248: How do multiple printers use the same template?
**Answer:** In DxClinic, each workstation or printer has its own independent record in `printer_profiles`. Workstation A (Canon LBP2900 on USB001) might use X: +1.5mm, Y: -1.0mm, while Workstation B (HP LaserJet on Network) might use X: -0.5mm, Y: +2.0mm. The attending doctor's template coordinates remain identical; each workstation applies its specific runtime calibration profile at print dispatch time.

### Q249: How does legacy Doctor Pad fallback work?
**Answer:** For doctors who have not yet configured an active template designer layout, or for historical appointments generated before Revision 27, DxClinic automatically routes the print request to the legacy Doctor Pad Blade view (`print.doctor-pad`) with `class="preprinted-mode"` intact. This guarantees 100% backward compatibility and zero disruption to daily clinic operations.

### Q250: How are historical templates preserved?
**Answer:** Every appointment record stores an immutable `prescription_pad_template_id` referencing the exact template version active at the time of the consultation. Even if an administrator subsequently modifies, duplicates, or archives the doctor's template, historical reprints resolve the exact version and element layout stored at that encounter.

### Q251: What is the Live Queue?
**Answer:** The **Live Queue** is a dedicated, real-time presentation display window (`/queue/live`) engineered for clinic waiting room monitors, reception desks, and doctors' examination screens. It continuously reflects the authoritative daily patient token sequence, booking source, arrival status, and consultation progress without requiring manual page reloads. It is strictly a read-only presentation layer that queries authoritative database state via `QueueViewService`.

### Q252: How do I open Live Queue in a separate window?
**Answer:** Click the green **[Open Live Queue]** button located on the top navigation bar or the Operator Arrival Queue. The application executes a controlled `window.open` call launching a dedicated browser window (1280x800 resolution) with minimal operational chrome, dark-mode visual hierarchy, and independent navigation.

### Q253: Can I close the Live Queue?
**Answer:** Yes. The Live Queue window is completely decoupled from your primary workstation session. Staff can close or minimize the window at any time without terminating background processes.

### Q254: Can I reopen the Live Queue?
**Answer:** Yes. Simply click **[Open Live Queue]** again. Upon reopening, the window immediately connects, evaluates role authorization, queries the database, and renders the current authoritative queue state.

### Q255: Does closing the Live Queue affect appointments?
**Answer:** No. Closing the Live Queue has **zero impact** on appointments, patient visits, token sequences, cash drawers, or active user sessions. The Live Queue has no state-mutating authority.

### Q256: Does WhatsApp booking appear in Live Queue?
**Answer:** Yes. When a patient books an appointment via the clinic's official WhatsApp automated assistant, the transaction commits in `AppointmentService`, broadcasts a `QueueUpdated` event, and the token appears immediately in the Live Queue with the `WHATSAPP` booking source badge.

### Q257: Does PWA booking appear in Live Queue?
**Answer:** Yes. Patient self-service bookings completed via the public booking PWA (`/book/{public_slug}`) commit to the same authoritative sequence and immediately appear in the Live Queue with the `ONLINE` booking source badge.

### Q258: Does clinic booking appear in Live Queue?
**Answer:** Yes. Walk-in bookings entered by the front desk operator appear instantly with the `CLINIC` booking source badge.

### Q259: Does Telephony booking appear in Live Queue?
**Answer:** Yes. Appointments booked via telephonic reception appear with the `TELEPHONIC` booking source badge.

### Q260: Do all booking channels share the same token series?
**Answer:** Yes. All four booking channels (`CLINIC`, `ONLINE`, `WHATSAPP`, `TELEPHONIC`) share **exactly one authoritative daily token sequence** per clinic and date. If Reception books Token 001, PWA books Token 002, WhatsApp books Token 003, and Telephonic books Token 004. There are no separate "WhatsApp token counters" or "PWA token counters".

### Q261: Can WhatsApp generate a separate token?
**Answer:** No. WhatsApp is strictly an inbound communication and booking channel adapter. It is architecturally prohibited from generating separate tokens or maintaining a distinct counter. All tokens originate solely from `TokenAllocationService`.

### Q262: What happens if two patients book simultaneously?
**Answer:** Concurrency safety is guaranteed at the database engine level. `TokenAllocationService` executes inside a single database transaction acquiring an atomic row-level lock (`lockForUpdate()`) on the clinic's `token_sequences` record. Bookings execute sequentially under isolation: each request acquires the next integer token atomically, guaranteeing zero duplicate token allocations.

### Q263: What happens if the realtime connection fails?
**Answer:** If the WebSocket connection drops, the Live Queue window automatically detects the interruption, updates its status badge to amber `RECONNECTING`, and attempts exponential reconnection. Simultaneously, an automatic polling fallback (executing every 6 seconds) ensures continuous queue updates so staff never lose visibility.

### Q264: What does Reconnecting mean?
**Answer:** `RECONNECTING` indicates that the browser has temporarily lost its WebSocket socket connection to the broadcast server (e.g. network blip or Wi-Fi roaming) and is actively negotiating reconnection.

### Q265: What does Fallback Refresh mean?
**Answer:** `FALLBACK REFRESH` indicates that the Live Queue is operating in safety recovery mode, periodically polling the backend server over HTTP to refresh queue state whenever real-time WebSockets are unavailable or blocked by local firewalls.

### Q266: Can a doctor see another doctor's queue?
**Answer:** No. By default, doctor logins are strictly restricted by server-side authorization to view only appointments assigned to their own authenticated doctor ID. Cross-doctor visibility is blocked unless explicit administrative permissions are granted.

### Q267: Can operators see all doctors?
**Answer:** Yes. Clinic Administrators and Frontdesk Operators have full clinic-wide visibility across all active doctors and sessions for the current day.

### Q268: Does Live Queue create any financial transaction?
**Answer:** Absolutely not. Opening, viewing, minimizing, closing, or refreshing the Live Queue generates **zero payments, zero receipts, zero journal entries, zero refunds, zero invoices, and zero cash movements**.

### Q269: Does refreshing Live Queue create a new token?
**Answer:** No. Refreshing the Live Queue window (manually or automatically via real-time triggers) is strictly an idempotent read operation. It simply re-queries the current database state and will never allocate a token.

### Q270: What happens when a WhatsApp webhook is delivered twice?
**Answer:** Webhook deliveries are protected by double idempotency. First, `WhatsAppWebhookEvent` tracks external Meta event IDs; duplicated messages are recorded as `DUPLICATE` and ignored. Second, the booking command is idempotent, ensuring that repeated webhook deliveries return the existing committed appointment and token without creating duplicates.

### Q271: Why is the token shown before WhatsApp confirmation?
**Answer:** The token appears in the Live Queue and database immediately upon transaction commit (`DB::commit()`). WhatsApp message delivery is an asynchronous background notification dispatched after commit. This guarantees that staff and patients have an authoritative booking even if outbound WhatsApp cellular delivery encounters transient network delays.

### Q272: Why is the token allocated before the WhatsApp confirmation message?
**Answer:** To guarantee data integrity. A token is only valid once it has been authoritatively reserved and committed in the database. Allocating the token before dispatching the outbound confirmation ensures that the confirmation message contains the real, committed token number.

### Q273: What happens if WhatsApp confirmation fails after booking?
**Answer:** Under DxClinic's failure isolation architecture, communication failures **never invalidate or roll back** a committed appointment. The appointment and token remain 100% valid in the clinic queue. The outbound notification job is placed in retry status (`whatsapp_message_deliveries`) to re-attempt delivery automatically.

### Q274: Is the token sequence guaranteed to be gapless?
**Answer:** No. Tokens are **unique, monotonic, and never reused**. Once a token has been allocated, that number is permanently consumed. If an appointment is subsequently marked `CANCELLED` or `ABSENT`, that token is retired forever to maintain absolute audit integrity and prevent duplicate/counterfeit tokens. Consequently, expected numeric gaps (e.g. `001, 002, 004`) appear in the daily sequence. The sequence is strictly **NOT guaranteed gapless**.

### Q275: How does DxClinic secure WhatsApp credentials from staff and shoulder-surfing?
**Answer:** All permanent System User Access Tokens are encrypted with AES-256-CBC prior to database storage and explicitly declared `$hidden` in Eloquent models to prevent leakage in JSON responses, logs, or Livewire component payloads. In the Admin settings view (`/settings/whatsapp`), sensitive identifiers are securely masked (e.g., `+91 ••• 7600`, `1069••••1098`).

### Q276: What happens if a clinic disconnects its WhatsApp connection?
**Answer:** Disconnecting WhatsApp is completely safe. It sets the connection status to `DISCONNECTED`, halting future inbound webhook processing and outbound message dispatches. Under DxClinic's Data Preservation Invariant, disconnecting **NEVER deletes or alters** historical WhatsApp chat records, patient profiles, committed appointments, tokens, visits, or financial receipts.

---

## 21. Developer Information
- **Application Developer**: Kundan Kumar
- **Development Company**: Nexora Solutions
- **Developer Location**: Varanasi, Uttar Pradesh, India
- **Developer Contact**: +91 98765 43210 &bull; contact@nexorasolutionsind.co.in
- **Developer Website**: [https://nexorasolutionsind.co.in/](https://nexorasolutionsind.co.in/)

---
*DxClinic Platform Architecture & FAQ &bull; Master Contract Implementation Verified &bull; Copyright &copy; 2026 DxClinic*
