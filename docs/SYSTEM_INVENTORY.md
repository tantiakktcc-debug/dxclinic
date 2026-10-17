# DXCLINIC — REVISION 40 — PHASE 0: SYSTEM INVENTORY

> Generated: 2026-09-29 | Source: Actual codebase inspection
> Database: `dxclinic` (MySQL) — 51 tables, 38 migrations

---

## 1. Models (`app/Models/` — 43 files)

| # | Model | Table | Rows | Tenant-Owned |
|---|---|---|---|---|
| 1 | Appointment | appointments | 196 | clinic_id |
| 2 | AuditLog | audit_logs | 26 | clinic_id |
| 3 | Backup | backups | 20 | clinic_id |
| 4 | BankAccount | bank_accounts | 1 | clinic_id |
| 5 | CashSession | cash_sessions | 3 | clinic_id |
| 6 | Clinic | clinics | 3 | SELF |
| 7 | ClinicDoctorSeatAddon | clinic_doctor_seat_addons | 0 | clinic_id |
| 8 | ClinicSequence | clinic_sequences | 1 | GLOBAL |
| 9 | ConsultationBill | consultation_bills | 112 | clinic_id |
| 10 | DeveloperUser | developer_users | 1 | GLOBAL |
| 11 | DiagnosticBill | diagnostic_bills | 27 | clinic_id |
| 12 | DiagnosticBillItem | diagnostic_bill_items | 50 | Inherits via bill |
| 13 | DiagnosticItem | diagnostic_items | 151 | clinic_id |
| 14 | Doctor | doctors | 7 | clinic_id |
| 15 | DoctorConsultationType | doctor_consultation_types | 19 | clinic_id |
| 16 | Expense | expenses | 19 | clinic_id |
| 17 | ExpenseCategory | expense_categories | 14 | GLOBAL |
| 18 | FeeMaster | fee_masters | 12 | clinic_id+doctor_id |
| 19 | FundTransfer | fund_transfers | 19 | clinic_id |
| 20 | IdempotencyRecord | idempotency_records | 0 | GLOBAL |
| 21 | Journal | journals | 111 | clinic_id |
| 22 | JournalEntry | journal_entries | 223 | Inherits via journal |
| 23 | LedgerAccount | ledger_accounts | 18 | GLOBAL |
| 24 | License | licenses | 3 | clinic_id |
| 25 | LicenseActivation | license_activations | 1 | clinic_id |
| 26 | LicenseEvent | license_events | 4 | Inherits via license |
| 27 | LicenseRenewal | license_renewals | 0 | clinic_id |
| 28 | LicenseRequest | license_requests | 0 | MIXED |
| 29 | LicenseRequestEvent | license_request_events | 0 | Inherits via request |
| 30 | LicenseRequestSequence | license_request_sequences | 1 | GLOBAL |
| 31 | Patient | patients | 139 | clinic_id |
| 32 | Payment | payments | 94 | clinic_id |
| 33 | PrescriptionPadElement | prescription_pad_elements | 48 | Inherits via template |
| 34 | PrescriptionPadTemplate | prescription_pad_templates | 26 | clinic_id |
| 35 | PrinterProfile | printer_profiles | 0 | clinic_id |
| 36 | Receipt | receipts | — | clinic_id |
| 37 | ReceiptRevision | receipt_revisions | 0 | Inherits via receipt |
| 38 | Refund | refunds | 1 | clinic_id |
| 39 | SubscriptionPlan | subscription_plans | 6 | GLOBAL |
| 40 | TokenSequence | token_sequences | 8 | clinic_id |
| 41 | User | users | 11 | clinic_id |
| 42 | Visit | visits | 117 | clinic_id |
| 43 | Vital | vitals | 27 | Inherits via visit |

---

## 2. Services (`app/Services/` — 37 files)

| # | Service | Size | Purpose |
|---|---|---|---|
| 1 | AppointmentService | 6.5KB | Appointment creation, token allocation |
| 2 | BackupService | 19.8KB | SQL backup/restore, encryption |
| 3 | BankLedgerService | 2.3KB | Bank account ledger operations |
| 4 | BillModificationService | 9.4KB | Bill editing with audit trail |
| 5 | CashReconciliationService | 8.5KB | Daily cash reconciliation |
| 6 | CashSessionService | 9.6KB | Cash session open/close/variance |
| 7 | ClinicClock | 2.5KB | IST timezone enforcement |
| 8 | ClinicScheduleService | 5.4KB | Doctor schedule config |
| 9 | ConsultationBillingService | 3.1KB | Consultation fee calculation |
| 10 | DemoService | 27.8KB | Demo tenant data seeding/reset |
| 11 | DiagnosticBillingService | 3.8KB | Diagnostic billing logic |
| 12 | DoctorNamePresenter | 1.5KB | Doctor display name formatting |
| 13 | IdempotencyService | 0.9KB | Duplicate request prevention |
| 14 | LabCollectionSlipService | 1.3KB | Lab slip generation |
| 15 | LicenseRequestService | 26.1KB | Public license request workflow |
| 16 | LicenseService | 36.3KB | License issuance, renewal, lifecycle |
| 17 | OpeningBalanceService | 4.5KB | Accounting opening balance |
| 18 | PatientAddressFormatter | 1.1KB | Address formatting |
| 19 | PatientIdentityService | 7.8KB | Patient ID generation |
| 20 | PaymentService | 10.3KB | Payment processing + journals |
| 21 | PdfGeneratorService | 4.5KB | DomPDF wrapper |
| 22 | PersonNameNormalizer | 2.0KB | Name normalization |
| 23 | PhoneNumberNormalizer | 3.0KB | Phone normalization |
| 24 | PrescriptionDataMapper | 16.7KB | Field catalog + encounter mapping |
| 25 | PrescriptionPadTemplateService | 16.0KB | Template CRUD, cloning |
| 26 | PrescriptionPdfRenderer | 5.8KB | Prescription PDF (proof/prod) |
| 27 | PrescriptionTemplateResolver | 5.0KB | Template+printer resolution |
| 28 | QrCodeService | 1.7KB | Booking QR generation |
| 29 | RagAssistantService | 23.4KB | RAG chatbot knowledge |
| 30 | ReceiptService | 1.3KB | Receipt number generation |
| 31 | RefundService | 20.1KB | Refund workflow with approval |
| 32 | ReportService | 12.5KB | OPD/diagnostic/financial reports |
| 33 | TenantContext | 6.5KB | Tenant resolution singleton |
| 34 | TokenAllocationService | 1.9KB | Sequential token allocation |
| 35 | TokenPdfService | 1.4KB | Token PDF generation |
| 36 | VisitService | 3.4KB | Visit creation + status |
| 37 | VitalService | 1.3KB | Vital signs recording |

---

## 3. Controllers — 13 files

SuperadminDashboardController (25KB, ~20 routes), AuthController, PdfDownloadController (9 PDF routes), ReceiptPrintController (4 routes), LicenseRequestController (5 routes), LegalController (3 routes), BookingQrController, ActivationController, PasswordChangeController, ClinicLookupController, TokenPrintController, SuperadminAuthController.

## 4. Livewire Components — 28 components

Dashboard, HelpCenter, PatientManager, ClinicDoctorManager, OperatorBooking, ArrivalQueue, ConsultationBilling, DiagnosticBilling, OperatorAssistant, CashSessionManager, DailyReportView, OpdReportView, DiagnosticReportView, FinancialReconciliationView, BackupManager, BankAccountManager, BookingQrManager, BrandingManager, ExpenseCategoryManager, FeeMasterManager, LicenseSubscriptionManager, PrescriptionPadDesigner, PrescriptionPadManager, UserManager, VitalMasterManager, ClinicOnboarding, PatientBooking, ChatbotWidget.

## 5. Middleware — 3 files

- **EnsureTenantContext** (`tenant`): Resolves clinic from auth user, validates IDOR on patients/appointments/templates/fees, blocks cross-tenant headers
- **LicenseMiddleware** (`license`): Validates active license, checks demo pause, forces password change
- **SuperadminMiddleware** (`superadmin`): Developer guard authentication

## 6. Routes — 119 registered

Public (~15), Superadmin (~23), Tenant-protected (~50), Print/PDF (~22), API (~5), Livewire internal (~6).

## 7. Database — 51 MySQL tables, 38 migrations

Full schema audit saved to `scratch/rev40_schema_output.txt` (70KB, 1329 lines).

## 8. Tests

- PHPUnit Feature: 27 test files (Rev 23–39 + core workflows)
- PHPUnit Unit: 2 test files
- Playwright E2E: 10 suites

## 9. Knowledge — 12 files

8 manuals (admin/operator/doctor/superadmin × manual+quick_card), 1 FAQ (47KB), 2 workflow docs, 1 troubleshooting doc.

## 10. Known Gaps

| # | Finding | Severity |
|---|---|---|
| 1 | `patient.full_address_with_city_name` NOT in field catalog | MEDIUM |
| 2 | `patient.age_sex` format `42 Y / F` vs required `35 Yrs / F` | LOW |
| 3 | API `/health`, `/queue/today`, `/reports/daily-summary` use `Clinic::first()` | HIGH |
| 4 | Print/PDF routes have NO auth middleware | HIGH |
| 5 | Platform sales/invoice/accounting tables DO NOT EXIST | Expected |
| 6 | `printer_profiles`: 0 rows, no calibration created | MEDIUM |
| 7 | `license_renewals`: 0 rows, no real renewals executed | INFO |
