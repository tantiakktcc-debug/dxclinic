# DxClinic — Multi-Tenant Clinical Practice Management & OPD Platform

[![Framework](https://img.shields.io/badge/Laravel-13.x-red.svg)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue.svg)](https://php.net)
[![Livewire](https://img.shields.io/badge/Livewire-4.4-pink.svg)](https://livewire.laravel.com)
[![Frontend](https://img.shields.io/badge/TailwindCSS-v4-38bdf8.svg)](https://tailwindcss.com)
[![Database](https://img.shields.io/badge/MySQL-8.0%2F8.4-orange.svg)](https://mysql.com)
[![WhatsApp Gateway](https://img.shields.io/badge/Baileys%20Gateway-Node.js-green.svg)](https://github.com/WhiskeySockets/Baileys)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)

**DxClinic** is a multi-tenant Outpatient Department (OPD), Clinical Practice Management, and Diagnostic Center platform designed for independent medical clinics, polyclinics, and diagnostic facilities. It delivers an end-to-end operational pipeline spanning patient self-booking, reception desk arrival queues, clinical vital signs capture, prescription pad vector printing, consultation and diagnostic billing, shift-based cash drawer reconciliation, and automated WhatsApp confirmations.

---

## Architecture & Core Subsystems

```
                           ┌──────────────────────────────────────────────┐
                           │            Superadmin / Developer            │
                           │   Guard: developer (developer_users table)   │
                           │       Routes: /superadmin/* (Global)         │
                           └──────────────────────┬───────────────────────┘
                                                  │ Fleet Management & Licensing
                                                  ▼
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                 SHARED DATABASE (`dxclinic`)                                     │
│  Composite Unique Keys: (clinic_id, id), (clinic_id, doctor_id, booking_type_id)                │
└─────────────────────────────────────────────────────────────────────────────────────────────────┘
                                                  ▲
                   ┌──────────────────────────────┴──────────────────────────────┐
                   │                                                             │
┌──────────────────┴──────────────────┐                       ┌──────────────────┴──────────────────┐
│        Authenticated Tenant App     │                       │       Public Unauthenticated PWA    │
│  Guard: web (users table)           │                       │  Routes: /book/{public_slug}        │
│  Roles: ADMIN, OPERATOR, DOCTOR     │                       │          /b/{public_slug}           │
│  Middleware: auth, tenant, license  │                       │  Middleware: tenant (route-scoped)  │
└─────────────────────────────────────┘                       └─────────────────────────────────────┘
```

### 1. Multi-Tenancy & Database-Level Defense-in-Depth
- **Strict Tenant Context Scoping:** Authenticated requests (`ADMIN`, `OPERATOR`, `DOCTOR`) are locked to the user's `clinic_id`. Tampering via query strings (`?clinic_id=`), custom headers (`X-Clinic-Code`, `X-Clinic-ID`), or POST body attributes is rejected with `403 Forbidden`.
- **Public Slug Locator:** Patient booking uses canonical URL slugs (`/book/{public_slug}` and `/b/{public_slug}`). Internal database IDs (`NX0001`, `1`) are blocked (`404 Not Found`).
- **Composite Foreign Key Constraints:** Relational mappings in MySQL (`clinic_id, doctor_id`, `clinic_id, booking_type_id`, `clinic_id, id`) prevent cross-tenant data leakage even at raw SQL level.

### 2. Single Authoritative Server-Side IST Booking Engine
- **Server Clock Standard (`ClinicClock`):** All booking decisions are strictly bound to India Standard Time (`Asia/Kolkata` `+05:30`). Client and browser clocks are ignored.
- **Booking Eligibility Service:**
  - Evaluates advance horizons: `MIN(clinic_max_advance_days, doctor_booking_policy_max_days)`.
  - Same-day session cutoffs: **Morning closes at 11:00 AM IST**; **Evening closes at 7:00 PM IST**.
  - Enforces doctor leaves (`doctor_leaves`), session-specific leaves, and weekly schedules.
- **Monotonic Concurrency-Safe Tokens (`TokenAllocationService`):**
  - Allocates sequential daily tokens (`#001`, `#002`, `#003`) scoped by `clinic_id`, `token_date`, and `session_type`.
  - Row-level database locking (`lockForUpdate()`) eliminates race conditions under concurrent bookings.
  - Queue broadcast events are dispatched strictly inside `DB::afterCommit(...)`.

### 3. Consultation & Diagnostic Billing with Financial Invariants
- **Mathematical Invariant Validation:**
  $$\text{Net Amount} = \text{Gross Amount} - \text{Discount Amount} - \text{Waiver Amount}$$
  Any deviation raises an `InvalidArgumentException` before records are saved.
- **Diagnostic Center Workflow:** Catalog management for blood tests and imaging packages, item recency and frequency tracking, and specimen collection slips.

### 4. Cash Drawer Sessions & Daily Financial Reconciliation
- **Shift-Based Cash Control:** Tracks morning and evening cash drawer sessions.
- **Closing Cash Equation:**
  $$\text{Expected Closing Cash} = \text{Opening Cash} + \text{Cash Collections} - \text{Cash Expenses} - \text{Bank Cash Deposits} - \text{Cash Refunds}$$
- **Discrepancy Justification:** Any variance between physical count and expected balance requires mandatory written operator justification.
- **Double-Entry Accounting:** Posts contra journals for cash-to-bank transfers, refunds, and expenses.

### 5. Prescription Pad Studio & Millimeter Vector Engine
- **Millimeter Coordinate Positioning:** Visual drag-and-drop designer mapping patient vitals, clinical notes, and doctor info in millimeters ($1\text{ mm} = 72/25.4\text{ pt}$).
- **Dual Mode Output:**
  - **Proof Mode:** Displays the background scanned doctor letterhead for visual verification.
  - **Production Mode:** Suppresses the background, rendering only text, lines, and boxes directly onto pre-printed letterhead pads on hardware printers (e.g. Canon LBP2900).
- **Typography:** Registers TrueType Unicode font families (`Noto Sans Devanagari`) into DomPDF for regional script support.

### 6. WhatsApp Omnichannel Hub & Baileys Gateway
- **Dual Transport Providers:**
  - **`BaileysWhatsAppProvider`:** Local development transport using a Node.js TypeScript gateway on `127.0.0.1:9090` authenticated via HMAC-SHA256 signatures (`X-Gateway-Signature`), nonces, and 5-minute replay prevention.
  - **`MetaWhatsAppProvider`:** Preserves Meta Cloud API v21.0 configuration and Embedded Signup v4.
- **Token Slip Delivery:** Generates 15-minute temporary cryptographically signed download links (`URL::temporarySignedRoute('token.slip.private-download', ...)`).
- **Operator Desk (`WhatsAppDesk`):** 3-pane Livewire interface with canned responses, conversation status filters, and human handoff takeover (`HUMAN_HANDOFF`) to suppress automated AI bot interference.

### 7. RAG Knowledge Assistant & Safe Action Redirects
- **Knowledge Base:** Indexes user manuals, quick reference cards, and FAQs from `knowledge/`.
- **Query Classification:**
  - **Class A:** Informational inquiries answered directly from documentation.
  - **Class B:** Read-only operational inquiries (queue status, doctor presence, fee structure).
  - **Class C:** Prohibited mutating financial/security operations via chat (refunds, bill modifications, password changes, database restores) generate safe, guided UI navigation redirects.

---

## Prerequisites

- **PHP:** `^8.3`
  - Required Extensions: `pdo_mysql`, `mbstring`, `openssl`, `gd`, `zip`, `xml`, `curl`, `bcmath`, `fileinfo`
- **Dependency Manager:** [Composer](https://getcomposer.org/) 2.x
- **Database:** MySQL 8.0+ or MySQL 8.4 LTS
- **Node.js & npm:** Node.js v18+ and npm v9+
- **Git:** Git 2.40+

---

## Installation & Local Development Setup

### 1. Clone Repository & Install Dependencies
```bash
# Clone the repository
git clone <repository-url> dxclinic
cd dxclinic

# Install PHP dependencies
composer install

# Install frontend dependencies
npm install
```

### 2. Environment Configuration
```bash
# Create local environment file from sanitized template
copy .env.example .env

# Generate application encryption key
php artisan key:generate
```

Open `.env` and configure your database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dxclinic
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

### 3. Database Migration & Seeders
```bash
# Create the database schema and seed baseline catalogs
php artisan migrate --seed
```

The seeders configure:
- Double-entry system ledger accounts (`SystemLedgerSeeder`)
- Standard diagnostic test and package masters (`DiagnosticMasterSeeder`)
- Standard clinic expense categories (`ExpenseCategorySeeder`)
- Baseline consultation fee masters (`FeeMasterSeeder`)
- Development testing accounts (`DevelopmentUserSeeder`):
  - **Admin:** `admin@dxclinic.local` / `DxClinic@Admin2026`
  - **Operator:** `operator@dxclinic.local` / `DxClinic@Op2026`
  - **Doctor:** `doctor@dxclinic.local` / `DxClinic@Doc2026`

### 4. Build Frontend Assets
```bash
# Build production assets via Vite
npm run build

# Or launch development server with hot-module replacement
npm run dev
```

### 5. Start the Application Server
```bash
php artisan serve --port=8000
```
Visit `http://127.0.0.1:8000/login` to access the application.

---

## WhatsApp Development Gateway (Optional)

For local WhatsApp automation testing via the Baileys multi-device protocol:

```bash
cd whatsapp-gateway

# Install gateway dependencies
npm install

# Configure gateway environment
copy .env.example .env

# Build TypeScript to dist/
npm run build

# Launch the gateway on 127.0.0.1:9090
npm start
```

Ensure `GATEWAY_SECRET` in `whatsapp-gateway/.env` matches `BAILEYS_GATEWAY_SECRET` in the root `.env`.

---

## Automated Testing Suite

DxClinic includes a test suite covering unit, feature, and security scenarios:

```bash
# Run full PHPUnit / Pest test suite
php artisan test

# Run targeted subsystem feature tests
php artisan test tests/Feature/BookingEligibilityServiceTest.php
php artisan test tests/Feature/CashControlTest.php
php artisan test tests/Feature/ConsultationBillingTest.php
php artisan test tests/Feature/Revision47PublicSlugFeatureTest.php
php artisan test tests/Feature/Revision46DatabaseIntegrityFeatureTest.php
```

---

## DevSecOps & Security Controls

- **Zero Plaintext Secrets:** No passwords, tokens, API keys, or private session credentials are committed to version control.
- **Ephemeral QR Codes:** WhatsApp connection QR strings are encrypted with Laravel's `encrypt()` and purged upon successful pairing.
- **AES-256 Encrypted Backups:** Local system backups (`.ncbk`) are encrypted using AES-256-GCM with PBKDF2 key derivation and verified by SHA-256 checksum manifests.
- **Temporary Signed URLs:** Sensitive documents (e.g., WhatsApp token slips) use cryptographically signed 15-minute expiring routes.

---

## Documentation

Detailed architectural and operational documentation is located in:
- `docs/FULL_SYSTEM_ARCHITECTURE.md` — Multi-tenant architecture and middleware pipeline.
- `docs/DATABASE_SCHEMA_AUDIT.md` — Database tables and composite integrity constraints.
- `docs/API_INVENTORY.md` — REST API routes and authentication requirements.
- `docs/FINAL_AUTHORIZATION_MATRIX.md` — Role and permission matrix across guards.
- `knowledge/manuals/` — Markdown manuals and quick cards for Admins, Doctors, and Operators.

---

## Engineering Attribution & License

- **Developer:** Kundan Kumar
- **Engineering Company:** Nexora Solution, Varanasi, Uttar Pradesh, India
- **Canonical Website:** [https://nexorasolutionsind.co.in/](https://nexorasolutionsind.co.in/)
- **Contact:** `+91 98765 43210` | `contact@nexorasolutionsind.co.in`
- **License:** Distributed under the **GNU Affero General Public License v3.0 (GNU AGPL-3.0, SPDX: AGPL-3.0-only)**. See the [`LICENSE`](LICENSE) file for the complete terms and conditions. All modified or network-accessible deployments must provide corresponding source code under the terms of the GNU AGPL-3.0.
