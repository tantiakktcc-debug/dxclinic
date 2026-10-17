
# DXCLINIC — FINAL AUTHORIZATION MATRIX
## Complete Role-Based Access Control (RBAC) & Tenant Security Reference
**Revision:** 45 Final Post-Revision-45 Audit  
**Authority:** Multi-Tenant Perimeter Guard & RBAC Engine  
**System Roles Audited:**
1. **Superadmin** (`developer` guard &bull; `developer_users` table &bull; Global Platform Scope)
2. **Admin** (`web` guard &bull; `users` table where `role = 'ADMIN'` &bull; Tenant-Scoped)
3. **Operator** (`web` guard &bull; `users` table where `role = 'OPERATOR'` &bull; Tenant-Scoped)
4. **Doctor** (`web` guard &bull; `users` table where `role = 'DOCTOR'` &bull; Tenant-Scoped)
5. **Public** (Unauthenticated Guest / External Patient)

---

## 1. Master Route Authorization Matrix

| Route URI | HTTP Method | Middleware Stack | Superadmin | Admin | Operator | Doctor | Public | Cross-Tenant Behavior |
|---|---|---|---|---|---|---|---|---|
| **Platform Fleet Governance** | | | | | | | | |
| `/superadmin/login` | `GET/POST` | `web` | Allowed | Allowed | Allowed | Allowed | Allowed | N/A (Global login form) |
| `/superadmin/dashboard` | `GET` | `web, superadmin` | **Allowed** | Denied (302) | Denied (302) | Denied (302) | Denied (302) | Denied (Platform only) |
| `/superadmin/licenses/issue` | `GET/POST` | `web, superadmin` | **Allowed** | Denied (302) | Denied (302) | Denied (302) | Denied (302) | Denied (Platform only) |
| `/superadmin/licenses/{id}/reveal` | `GET` | `web, superadmin` | **Allowed** | Denied (302) | Denied (302) | Denied (302) | Denied (302) | Denied (Platform only) |
| `/superadmin/clinics/{id}/renew` | `GET/POST` | `web, superadmin` | **Allowed** | Denied (302) | Denied (302) | Denied (302) | Denied (302) | Denied (Platform only) |
| `/superadmin/clinics/{id}/add-doctor-seats` | `POST` | `web, superadmin` | **Allowed** | Denied (302) | Denied (302) | Denied (302) | Denied (302) | Denied (Platform only) |
| `/superadmin/clinics/{id}/suspend` | `POST` | `web, superadmin` | **Allowed** | Denied (302) | Denied (302) | Denied (302) | Denied (302) | Denied (Platform only) |
| `/superadmin/demo/reset` | `POST` | `web, superadmin` | **Allowed** | Denied (302) | Denied (302) | Denied (302) | Denied (302) | Denied (Platform only) |
| `/superadmin/demo/pause` | `POST` | `web, superadmin` | **Allowed** | Denied (302) | Denied (302) | Denied (302) | Denied (302) | Denied (Platform only) |
| `/superadmin/demo/resume` | `POST` | `web, superadmin` | **Allowed** | Denied (302) | Denied (302) | Denied (302) | Denied (302) | Denied (Platform only) |
| **Platform Commercial Sales & Accounting (Revision 42)** | | | | | | | | |
| `/superadmin/sales` | `GET` | `web, superadmin` | **Allowed** | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (Platform only) |
| `/superadmin/invoices` | `GET` | `web, superadmin` | **Allowed** | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (Platform only) |
| `/superadmin/invoices/{id}` | `GET` | `web, superadmin` | **Allowed** | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (Platform only) |
| `/superadmin/invoices/{id}/print` | `GET` | `web, superadmin` | **Allowed** | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (Platform only) |
| `/superadmin/invoices/{id}/pdf` | `GET` | `web, superadmin` | **Allowed** | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (Platform only) |
| `/superadmin/invoices/{id}/cancel` | `POST` | `web, superadmin` | **Allowed** | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (Platform only) |
| `/superadmin/collections` | `GET` | `web, superadmin` | **Allowed** | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (Platform only) |
| `/superadmin/clinics/{id}/sales` | `GET` | `web, superadmin` | **Allowed** | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (Platform only) |
| `/superadmin/expenses` | `GET/POST`| `web, superadmin` | **Allowed** | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (302/403) | Denied (Platform only) |
| **Tenant Administrative & Setup** | | | | | | | | |
| `/dashboard` | `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | Redirect (`/operator/queue`) | Redirect (`/operator/queue`) | Denied (302) | Scoped to authenticated clinic |
| `/settings` | `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | **Denied (403)** | **Denied (403)** | Denied (302) | Scoped to authenticated clinic |
| `/settings/backup` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | **Denied (403)** | **Denied (403)** | Denied (302) | Scoped to authenticated clinic |
| `/settings/backup/download/{f}` | `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | **Denied (403)** | **Denied (403)** | Denied (302) | Scoped to authenticated clinic |
| `/settings/fee-master` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | **Denied (403)** | **Denied (403)** | Denied (302) | Doctor-specific tariff configuration (Rev 45) |
| `/settings/booking-types` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | **Denied (403)** | **Denied (403)** | Denied (302) | Doctor booking type matrix & custom types (Rev 44/45) |
| `/admin/doctors` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | **Denied (403)** | **Denied (403)** | Denied (302) | Scoped to authenticated clinic |
| `/admin/users` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | **Denied (403)** | **Denied (403)** | Denied (302) | Scoped to authenticated clinic |
| `/admin/fees` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | **Denied (403)** | **Denied (403)** | Denied (302) | Scoped to authenticated clinic |
| `/admin/pad-designer` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | **Denied (403)** | **Denied (403)** | Denied (302) | Scoped to authenticated clinic |
| **Tenant Frontdesk & OPD Operations** | | | | | | | | |
| `/operator/queue` | `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | **Allowed** | **Allowed** | Denied (302) | Scoped to authenticated clinic |
| `/operator/appointments` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | **Allowed** | Denied (403) | Denied (302) | Scoped to authenticated clinic |
| `/operator/patients` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | **Allowed** | **Allowed** | Denied (302) | Scoped to authenticated clinic |
| `/operator/consultation` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | **Allowed** | Denied (403) | Denied (302) | Scoped to authenticated clinic |
| `/operator/diagnostics` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | **Allowed** | Denied (403) | Denied (302) | Scoped to authenticated clinic |
| `/cash/sessions` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | **Allowed** | Denied (403) | Denied (302) | Scoped to authenticated clinic |
| **Tenant Doctor Clinical Care** | | | | | | | | |
| `/doctor/queue` | `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | Denied (403) | **Allowed** | Denied (302) | Scoped to authenticated clinic |
| `/doctor/consultation/{visit}` | `GET/POST`| `auth, tenant, license` | Denied (302) | **Allowed** | Denied (403) | **Allowed** | Denied (302) | **IDOR: 403 Forbidden** if visit belongs to other clinic |
| `/doctor/history/{patient}` | `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | Denied (403) | **Allowed** | Denied (302) | **IDOR: 403 Forbidden** if patient belongs to other clinic |
| **Tenant Financial & OPD Reports** | | | | | | | | |
| `/reports/opd` | `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | **Allowed** | **Allowed** | Denied (302) | Scoped to authenticated clinic |
| `/reports/diagnostics` | `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | **Allowed** | Denied (403) | Denied (302) | Scoped to authenticated clinic |
| `/reports/financial` | `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | Denied (403) | Denied (403) | Denied (302) | Scoped to authenticated clinic |
| **Object Prints & PDF Downloads (Anti-IDOR Protected)** | | | | | | | | |
| `/receipt/{bill}` | `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | **Allowed** | **Allowed** | Denied (302) | **IDOR: 403 Forbidden** if bill belongs to other clinic |
| `/prescriptions/{visit}/pdf` | `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | **Allowed** | **Allowed** | Denied (302) | **IDOR: 403 Forbidden** if visit belongs to other clinic |
| `/prescriptions/template/{tpl}/pdf`| `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | **Allowed** | **Allowed** | Denied (302) | **IDOR: 403 Forbidden** if template belongs to other clinic |
| `/patients/{patient}/history/pdf` | `GET` | `auth, tenant, license` | Denied (302) | **Allowed** | **Allowed** | **Allowed** | Denied (302) | **IDOR: 403 Forbidden** if patient belongs to other clinic |
| **Tenant & Public APIs** | | | | | | | | |
| `/api/queue/today` | `GET` | `web` | Denied (401) | **Allowed** | **Allowed** | **Allowed** | Allowed only with valid Signed URL | Scoped to authenticated clinic or signed clinic_code |
| `/api/reports/daily-summary` | `GET` | `web` | Denied (401) | **Allowed** | **Allowed** | **Allowed** | Denied (401) | Scoped to authenticated clinic only |
| `/api/daily-summary` | `GET` | `web` | Denied (401) | **Allowed** | **Allowed** | **Allowed** | Denied (401) | Scoped to authenticated clinic only |
| `/api/diagnostics/frequent` | `GET` | `web` | Denied (401) | **Allowed** | **Allowed** | **Allowed** | Denied (401) | Scoped to tenant + global catalog |
| `/api/health` | `GET` | Public | **Allowed** | **Allowed** | **Allowed** | **Allowed** | **Allowed** | Non-sensitive platform health only |
| `/api/clinic-lookup` | `GET` | Public | **Allowed** | **Allowed** | **Allowed** | **Allowed** | **Allowed** | Public clinic facility info only |
| **Public PWA & Knowledge Portal** | | | | | | | | |
| `/book/{clinic_code}` | `GET/POST`| `public.clinic` | **Allowed** | **Allowed** | **Allowed** | **Allowed** | **Allowed** | Scoped to requested clinic (404 on invalid code) |
| `/b/{clinic_code}` | `GET` | `public.clinic` | **Allowed** | **Allowed** | **Allowed** | **Allowed** | **Allowed** | Redirects to `/book/{clinic_code}` |
| `/verify-token/{secureToken}` | `GET` | Public | **Allowed** | **Allowed** | **Allowed** | **Allowed** | **Allowed** | Validates cryptographic secureToken |
| `/token-pdf/{secureToken}` | `GET` | Public | **Allowed** | **Allowed** | **Allowed** | **Allowed** | **Allowed** | Generates slip PDF for valid secureToken |
| `/manuals/*` | `GET` | Public | **Allowed** | **Allowed** | **Allowed** | **Allowed** | **Allowed** | Professional manuals & printable guides |

---

## 2. Cross-Boundary & Negative Security Test Verification

### 2.1 Tenant Staff $\to$ Superadmin Commercial Data
- **Scenario:** Authenticated user from `NX0001` (`ADM0001`, `OPR0001`, `DOC0001`) attempts `GET /superadmin/sales` or `/superadmin/invoices`.
- **Enforcement:** `SuperadminMiddleware` checks `Auth::guard('developer')->check()`.
- **Result:** **302 Redirect to `/superadmin/login`** for browser requests; **403 Forbidden** for API/JSON requests.
- **Security Finding:** Complete isolation. Tenant users cannot access or view provider commercial data.

### 2.2 Operator / Doctor $\to$ Admin Settings & Backups
- **Scenario:** Frontdesk Operator (`OPR0001`) attempts `GET /settings/backup` or `POST /settings/backup`.
- **Enforcement:** `BackupService` and `Settings` controller verify `Auth::user()->role === 'ADMIN'`.
- **Result:** **HTTP 403 Forbidden**.
- **Security Finding:** Privilege escalation from Operator or Doctor to Admin is strictly blocked.

### 2.3 Public $\to$ Tenant Private Workspace
- **Scenario:** Unauthenticated user attempts `GET /dashboard`, `/operator/queue`, `/cash/sessions`.
- **Enforcement:** Laravel `Authenticate` middleware stack redirects unauthenticated requests.
- **Result:** **302 Redirect to `/login`**.
- **Security Finding:** Zero unauthenticated access to tenant clinical data.

### 2.4 Tenant A $\to$ Tenant B (Cross-Tenant Anti-IDOR Defense)
- **Scenario:** Authenticated user of `NX0001` attempts to view Receipt #102 belonging to `NX0002` (`GET /receipt/102`) or Prescription PDF #55 belonging to `NX0002`.
- **Enforcement:** Controller executes `authorizeClinicResource($model)`:
  ```php
  if ((int) $model->clinic_id !== (int) TenantContext::getClinicId()) {
      abort(403, 'Cross-tenant resource access is strictly forbidden.');
  }
  ```
- **Result:** **HTTP 403 Forbidden**.
- **Security Finding:** Insecure Direct Object References (IDOR) are completely blocked.

---
*DxClinic Final Authorization Matrix &bull; Revision 45 Complete &bull; Copyright &copy; 2026 DxClinic*
