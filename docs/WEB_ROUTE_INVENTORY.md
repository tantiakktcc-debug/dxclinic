# DXCLINIC — REVISION 47 — WEB ROUTE INVENTORY
> Generated: 2026-09-29 | Method: Automated Route Table Forensic Extraction
> Total Web Routes: 114 | Extracted via `php artisan route:list --json`

---
## Public & Commercial Requests (5 Routes)
| Method | URI | Route Name | Action / Controller | Middleware |
|---|---|---|---|---|
| `GET|HEAD` | `/license-renewal-request` | `license.renewal-request` | `LicenseRequestController@showRenewalForm` | `web, Illuminate\Routing\Middleware\ThrottleRequests:10,1` |
| `POST` | `/license-renewal-request` | `license.renewal-request.submit` | `LicenseRequestController@submitRenewal` | `web, Illuminate\Routing\Middleware\ThrottleRequests:10,1` |
| `GET|HEAD` | `/license-request` | `license.request` | `LicenseRequestController@showRequestForm` | `web, Illuminate\Routing\Middleware\ThrottleRequests:10,1` |
| `POST` | `/license-request` | `license.request.submit` | `LicenseRequestController@submitRequest` | `web, Illuminate\Routing\Middleware\ThrottleRequests:10,1` |
| `GET|HEAD` | `/pricing` | `license.pricing` | `LicenseRequestController@pricing` | `web, Illuminate\Routing\Middleware\ThrottleRequests:10,1` |

## Superadmin Fleet Management (25 Routes)
| Method | URI | Route Name | Action / Controller | Middleware |
|---|---|---|---|---|
| `POST` | `/superadmin/clinics/{clinic}/add-doctor-seats` | `superadmin.clinics.add-doctor-seats` | `Superadmin\SuperadminDashboardController@addDoctorSeats` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/clinics/{clinic}/events` | `superadmin.clinics.events` | `Superadmin\SuperadminDashboardController@events` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/clinics/{clinic}/history` | `superadmin.clinics.history` | `Superadmin\SuperadminDashboardController@clinicHistory` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/clinics/{clinic}/mark-activated` | `superadmin.clinics.mark-activated` | `Superadmin\SuperadminDashboardController@markActivated` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/clinics/{clinic}/reactivate` | `superadmin.clinics.reactivate` | `Superadmin\SuperadminDashboardController@reactivate` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/clinics/{clinic}/renew` | `superadmin.clinics.renew-form` | `Superadmin\SuperadminDashboardController@showRenewForm` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/clinics/{clinic}/renew` | `superadmin.clinics.renew` | `Superadmin\SuperadminDashboardController@renew` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/clinics/{clinic}/revoke` | `superadmin.clinics.revoke` | `Superadmin\SuperadminDashboardController@revoke` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/clinics/{clinic}/suspend` | `superadmin.clinics.suspend` | `Superadmin\SuperadminDashboardController@suspend` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/dashboard` | `superadmin.dashboard` | `Superadmin\SuperadminDashboardController@index` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/demo/pause` | `superadmin.demo.pause` | `Superadmin\SuperadminDashboardController@pauseDemo` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/demo/reset` | `superadmin.demo.reset` | `Superadmin\SuperadminDashboardController@resetDemoData` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/demo/resume` | `superadmin.demo.resume` | `Superadmin\SuperadminDashboardController@resumeDemo` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/license-history` | `superadmin.license-history` | `Superadmin\SuperadminDashboardController@history` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/license-requests` | `superadmin.license-requests` | `Superadmin\SuperadminDashboardController@licenseRequests` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/license-requests/{licenseRequest}` | `superadmin.license-requests.show` | `Superadmin\SuperadminDashboardController@showLicenseRequest` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/license-requests/{licenseRequest}/fulfill` | `superadmin.license-requests.fulfill` | `Superadmin\SuperadminDashboardController@fulfillLicenseRequest` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/license-requests/{licenseRequest}/reject` | `superadmin.license-requests.reject` | `Superadmin\SuperadminDashboardController@rejectLicenseRequest` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/license-requests/{licenseRequest}/review` | `superadmin.license-requests.review` | `Superadmin\SuperadminDashboardController@reviewLicenseRequest` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/licenses/issue` | `superadmin.licenses.issue` | `Superadmin\SuperadminDashboardController@showIssueForm` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/licenses/issue` | `superadmin.licenses.issue.submit` | `Superadmin\SuperadminDashboardController@issueLicense` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/licenses/{license}/reveal` | `superadmin.licenses.reveal-key` | `Superadmin\SuperadminDashboardController@revealKey` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/login` | `superadmin.login` | `Superadmin\SuperadminAuthController@showLogin` | `web` |
| `POST` | `/superadmin/login` | `superadmin.login.submit` | `Superadmin\SuperadminAuthController@login` | `web` |
| `POST` | `/superadmin/logout` | `superadmin.logout` | `Superadmin\SuperadminAuthController@logout` | `web` |
| `GET|HEAD` | `/superadmin/sales` | `superadmin.sales` | `Superadmin\SuperadminSalesController@salesDashboard` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/invoices` | `superadmin.invoices` | `Superadmin\SuperadminSalesController@invoicesList` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/invoices/{invoice}` | `superadmin.invoices.show` | `Superadmin\SuperadminSalesController@invoiceDetail` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/invoices/{invoice}/print` | `superadmin.invoices.print` | `Superadmin\SuperadminSalesController@invoicePrint` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/invoices/{invoice}/pdf` | `superadmin.invoices.pdf` | `Superadmin\SuperadminSalesController@invoicePdf` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/invoices/{invoice}/cancel` | `superadmin.invoices.cancel` | `Superadmin\SuperadminSalesController@cancelInvoice` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/collections` | `superadmin.collections` | `Superadmin\SuperadminSalesController@collections` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/clinics/{clinic}/sales` | `superadmin.clinics.sales` | `Superadmin\SuperadminSalesController@clinicSales` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `GET|HEAD` | `/superadmin/expenses` | `superadmin.expenses` | `Superadmin\SuperadminSalesController@expenses` | `web, App\Http\Middleware\SuperadminMiddleware` |
| `POST` | `/superadmin/expenses` | `superadmin.expenses.store` | `Superadmin\SuperadminSalesController@storeExpense` | `web, App\Http\Middleware\SuperadminMiddleware` |

## Authentication & Onboarding (7 Routes)
| Method | URI | Route Name | Action / Controller | Middleware |
|---|---|---|---|---|
| `GET|HEAD` | `/activate-clinic` | `activation.show` | `ActivationController@show` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext` |
| `POST` | `/activate-clinic` | `activation.submit` | `ActivationController@activate` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext` |
| `GET|HEAD` | `/force-password-change` | `force-password-change` | `PasswordChangeController@show` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext` |
| `POST` | `/force-password-change` | `force-password-change.submit` | `PasswordChangeController@update` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext` |
| `GET|HEAD` | `/login` | `login` | `AuthController@showLoginForm` | `web` |
| `POST` | `/login` | `—` | `AuthController@login` | `web` |
| `GET|POST|HEAD` | `/logout` | `logout` | `AuthController@logout` | `web` |

## Patient Booking PWA (3 Routes)
| Method | URI | Route Name | Action / Controller | Middleware |
|---|---|---|---|---|
| `GET|HEAD` | `/b/{public_slug}` | `pwa.book.short` | `Closure` (302 redirect) | `web` |
| `GET|POST|HEAD` | `/book/{public_slug}` | `pwa.book` | `Livewire:Pwa\PatientBooking` | `web, App\Http\Middleware\EnsureTenantContext` |
| `GET|HEAD` | `/verify-token/{secureToken}` | `token.verify` | `TokenPrintController@show` | `web` |

## Tenant Core & Operator Workflows (6 Routes)
| Method | URI | Route Name | Action / Controller | Middleware |
|---|---|---|---|---|
| `GET|HEAD` | `/dashboard` | `dashboard` | `Livewire:Dashboard` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/operator/booking` | `operator.booking` | `Livewire:Operator\OperatorBooking` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/operator/consultation/{appointmentId?}` | `operator.consultation` | `Livewire:Operator\ConsultationBilling` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/operator/diagnostics/{appointmentId?}` | `operator.diagnostics` | `Livewire:Operator\DiagnosticBilling` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/clinic/{public_slug}/operator/queue` | `operator.queue` | `Livewire:Operator\ArrivalQueue` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/patients` | `patients` | `Livewire:PatientManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |

## Cash & Financial Control (2 Routes)
| Method | URI | Route Name | Action / Controller | Middleware |
|---|---|---|---|---|
| `GET|HEAD` | `/cash/daily-report` | `cash.daily-report` | `Livewire:Cash\DailyReportView` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/cash/sessions` | `cash.sessions` | `Livewire:Cash\CashSessionManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |

## Reports Subsystem (4 Routes)
| Method | URI | Route Name | Action / Controller | Middleware |
|---|---|---|---|---|
| `GET|HEAD` | `/reports/daily-cash` | `reports.daily-cash` | `Livewire:Cash\DailyReportView` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/reports/diagnostics` | `reports.diagnostics` | `Livewire:Reports\DiagnosticReportView` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/reports/opd` | `reports.opd` | `Livewire:Reports\OpdReportView` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/reports/reconciliation` | `reports.reconciliation` | `Livewire:Reports\FinancialReconciliationView` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |

## Settings & Administration (18 Routes)
| Method | URI | Route Name | Action / Controller | Middleware |
|---|---|---|---|---|
| `GET|HEAD` | `/settings/backup` | `settings.backup` | `Livewire:Settings\BackupManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/bank-accounts` | `settings.bank-accounts` | `Livewire:Settings\BankAccountManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/booking-qr` | `settings.booking-qr` | `Livewire:Settings\BookingQrManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/booking-qr/download-png/{type}` | `settings.booking-qr.download-png` | `BookingQrController@downloadPng` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/booking-qr/download-svg/{type}` | `settings.booking-qr.download-svg` | `BookingQrController@downloadSvg` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/booking-qr/poster/{type}` | `settings.booking-qr.poster` | `BookingQrController@printPoster` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/booking-types` | `settings.booking-types` | `Livewire:Settings\BookingTypeManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/branding` | `settings.branding` | `Livewire:Settings\BrandingManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/clinic-doctor` | `settings.clinic-doctor` | `Livewire:ClinicDoctorManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/expense-categories` | `settings.expense-categories` | `Livewire:Settings\ExpenseCategoryManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/fee-master` | `settings.fee-master` | `Livewire:Settings\FeeMasterManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/help/{tab?}` | `settings.help` | `Livewire:HelpCenter` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/onboarding` | `settings.onboarding` | `Livewire:Onboarding\ClinicOnboarding` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/prescription-pad-designer/{templateId}` | `settings.prescription-pad-designer` | `Livewire:Settings\PrescriptionPadDesigner` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/prescription-pad-templates` | `settings.prescription-pad-templates` | `Livewire:Settings\PrescriptionPadManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/subscription` | `settings.subscription` | `Livewire:Settings\LicenseSubscriptionManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/users` | `settings.users` | `Livewire:Settings\UserManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/settings/vital-master` | `settings.vital-master` | `Livewire:Settings\VitalMasterManager` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |

## Print & PDF Generation (19 Routes)
| Method | URI | Route Name | Action / Controller | Middleware |
|---|---|---|---|---|
| `GET|HEAD` | `/pdf/consultation-receipt/{billId}` | `pdf.consultation-receipt` | `PdfDownloadController@consultationReceiptPdf` | `web` |
| `GET|HEAD` | `/pdf/diagnostic-receipt/{billId}` | `pdf.diagnostic-receipt` | `PdfDownloadController@diagnosticReceiptPdf` | `web` |
| `GET|HEAD` | `/pdf/diagnostic-report` | `pdf.diagnostic-report` | `PdfDownloadController@diagnosticReportPdf` | `web` |
| `GET|HEAD` | `/pdf/doctor-pad/{appointmentId}` | `pdf.doctor-pad` | `PdfDownloadController@doctorPadPdf` | `web` |
| `GET|HEAD` | `/pdf/lab-slip/{billId}` | `pdf.lab-slip` | `PdfDownloadController@labSlipPdf` | `web` |
| `GET|HEAD` | `/pdf/manual/{type}` | `pdf.manual` | `PdfDownloadController@manualPdf` | `web` |
| `GET|HEAD` | `/pdf/opd-report` | `pdf.opd-report` | `PdfDownloadController@opdReportPdf` | `web` |
| `GET|HEAD` | `/pdf/patient-history/{patientId}` | `pdf.patient-history` | `PdfDownloadController@patientHistoryPdf` | `web` |
| `GET|HEAD` | `/pdf/prescription-template/{templateId}` | `pdf.prescription-template` | `Closure` | `web` |
| `GET|HEAD` | `/print/consultation-receipt-dash/{billId}` | `print.consultation-receipt` | `ReceiptPrintController@consultationReceipt` | `web` |
| `GET|HEAD` | `/print/consultation-receipt/{billId}` | `print.consultation.receipt` | `ReceiptPrintController@consultationReceipt` | `web` |
| `GET|HEAD` | `/print/diagnostic-receipt-dash/{billId}` | `print.diagnostic-receipt` | `ReceiptPrintController@diagnosticReceipt` | `web` |
| `GET|HEAD` | `/print/diagnostic-receipt/{billId}` | `print.diagnostic.receipt` | `ReceiptPrintController@diagnosticReceipt` | `web` |
| `GET|HEAD` | `/print/doctor-pad-dash/{appointmentId}` | `print.doctor-pad` | `ReceiptPrintController@doctorPad` | `web` |
| `GET|HEAD` | `/print/doctor-pad/{appointmentId}` | `print.doctor.pad` | `ReceiptPrintController@doctorPad` | `web` |
| `GET|HEAD` | `/print/lab-collection-slip-dash/{billId}` | `print.lab-collection-slip` | `ReceiptPrintController@labCollectionSlip` | `web` |
| `GET|HEAD` | `/print/lab-collection-slip/{billId}` | `print.lab.slip` | `ReceiptPrintController@labCollectionSlip` | `web` |
| `GET|HEAD` | `/print/token/{secureToken}` | `print.token` | `TokenPrintController@show` | `web` |
| `GET|HEAD` | `/token-pdf/{secureToken}` | `token.pdf` | `TokenPrintController@show` | `web` |

## Manuals & Documentation (5 Routes)
| Method | URI | Route Name | Action / Controller | Middleware |
|---|---|---|---|---|
| `GET|HEAD` | `/about` | `about` | `LegalController@about` | `web` |
| `GET|HEAD` | `/manuals` | `manuals.hub` | `Closure` | `web` |
| `GET|HEAD` | `/manuals/{slug}` | `manuals.view` | `Closure` | `web` |
| `GET|HEAD` | `/privacy-policy` | `privacy.policy` | `LegalController@privacyPolicy` | `web` |
| `GET|HEAD` | `/terms-and-conditions` | `terms.conditions` | `LegalController@termsConditions` | `web` |

## Other / System (20 Routes)
| Method | URI | Route Name | Action / Controller | Middleware |
|---|---|---|---|---|
| `GET|HEAD` | `//` | `—` | `Closure` | `web` |
| `GET|HEAD` | `/appointments/{appointment}` | `tenant.appointment.show` | `Closure` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/fees/{fee}` | `tenant.fee.show` | `Closure` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/help/{tab?}` | `help` | `Livewire:HelpCenter` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/livewire-5e56d310/css/{component}.css` | `—` | `Closure` | `` |
| `GET|HEAD` | `/livewire-5e56d310/css/{component}.global.css` | `—` | `Closure` | `` |
| `GET|HEAD` | `/livewire-5e56d310/js/{component}.js` | `—` | `Closure` | `` |
| `GET|HEAD` | `/livewire-5e56d310/livewire.csp.min.js.map` | `—` | `Livewire\Mechanisms\FrontendAssets\FrontendAssets@cspMaps` | `` |
| `GET|HEAD` | `/livewire-5e56d310/livewire.min.js` | `—` | `Livewire\Mechanisms\FrontendAssets\FrontendAssets@returnJavaScriptAsFile` | `` |
| `GET|HEAD` | `/livewire-5e56d310/livewire.min.js.map` | `—` | `Livewire\Mechanisms\FrontendAssets\FrontendAssets@maps` | `` |
| `GET|HEAD` | `/livewire-5e56d310/preview-file/{filename}` | `livewire.preview-file` | `Livewire\Features\SupportFileUploads\FilePreviewController@handle` | `web` |
| `POST` | `/livewire-5e56d310/update` | `default-livewire.update` | `Livewire\Mechanisms\HandleRequests\HandleRequests@handleUpdate` | `web, Livewire\Mechanisms\HandleRequests\RequireLivewireHeaders` |
| `POST` | `/livewire-5e56d310/upload-file` | `livewire.upload-file` | `Livewire\Features\SupportFileUploads\FileUploadController@handle` | `web, Illuminate\Routing\Middleware\ThrottleRequests:60,1` |
| `GET|HEAD` | `/onboarding` | `onboarding` | `Livewire:Onboarding\ClinicOnboarding` | `web` |
| `GET|HEAD` | `/patients/{patient}` | `tenant.patient.show` | `Closure` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/sanctum/csrf-cookie` | `sanctum.csrf-cookie` | `Laravel\Sanctum\Http\Controllers\CsrfCookieController@show` | `web` |
| `GET|HEAD` | `/storage/{path}` | `storage.local` | `Closure` | `` |
| `PUT` | `/storage/{path}` | `storage.local.upload` | `Closure` | `` |
| `GET|HEAD` | `/templates/{template}` | `tenant.template.show` | `Closure` | `web, Illuminate\Auth\Middleware\Authenticate, App\Http\Middleware\EnsureTenantContext, App\Http\Middleware\LicenseMiddleware` |
| `GET|HEAD` | `/up` | `—` | `Closure` | `` |

