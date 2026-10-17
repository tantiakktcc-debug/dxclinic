# DXCLINIC — REVISION 40 — NETWORK CALL INVENTORY

> Addendum F | Generated from codebase inspection 2026-09-29

---

## Purpose
Catalog every network call originating from the frontend or backend that is NOT a standard Laravel route. Includes fetch(), axios, Livewire wire: directives, signed URLs, and external API calls.

---

## 1. JavaScript fetch() Calls (3 found)

| # | Source View | Target URL | Auth | Tenant Scoped |
|---|---|---|---|---|
| 1 | `superadmin/dashboard.blade.php:509` | `/superadmin/licenses/${licenseId}/reveal` | Superadmin guard | N/A (global) |
| 2 | `superadmin/dashboard.blade.php:556` | `/superadmin/clinics/${clinicId}/events` | Superadmin guard | N/A (fleet) |
| 3 | `auth/login.blade.php:167` | `/api/clinic-lookup?code=${code}` | None (public) | By design — public lookup |

## 2. Axios Calls (0 found)
No axios calls detected in any view template.

## 3. Livewire Wire Calls (~28 components)
All Livewire components use `wire:click`, `wire:model`, `wire:submit` which route through the Livewire update endpoint (`/livewire-*/update`). These are POST-only and pass through `EnsureTenantContext` middleware.

## 4. API Routes (5 endpoints — all in `routes/api.php`)

| # | Method | URI | Auth | Tenant Scoped | Risk |
|---|---|---|---|---|---|
| 1 | GET | `/api/clinic-lookup` | None | Public by design | LOW |
| 2 | GET | `/api/health` | None | ⚠️ `Clinic::first()` | **HIGH** |
| 3 | GET | `/api/queue/today` | None | ⚠️ `Clinic::first()` | **HIGH** |
| 4 | GET | `/api/diagnostics/frequent` | None | No clinic scoping visible | **HIGH** |
| 5 | GET | `/api/reports/daily-summary` | None | ⚠️ `Clinic::first()` | **HIGH** |

### Risk Assessment for API Routes:
- **Endpoints 2, 3, 5** use `Clinic::first()` which returns whatever clinic has the lowest ID. In a multi-tenant production deployment, this leaks Clinic 1's data to any unauthenticated caller.
- **Endpoint 4** calls `DiagnosticBillingService::getFrequentlyUsed()` without passing a clinic — needs audit of that service method to confirm scoping.
- **None of these have auth middleware** — they are completely open.

## 5. External API Calls (Backend — 0 found)
No external HTTP calls detected (no payment gateways, no SMS APIs, no third-party integrations).

## 6. Signed URLs (0 found)
No Laravel signed URL usage detected.

## 7. Print/PDF Routes (No Auth)

| # | URI Pattern | Auth | Risk |
|---|---|---|---|
| 1 | `/print/consultation-receipt/{billId}` | None | MEDIUM — receipt content via bill ID |
| 2 | `/print/diagnostic-receipt/{billId}` | None | MEDIUM — receipt content via bill ID |
| 3 | `/print/doctor-pad/{appointmentId}` | None | MEDIUM — prescription via appointment ID |
| 4 | `/print/lab-collection-slip/{billId}` | None | MEDIUM — lab slip via bill ID |
| 5 | `/print/token/{secureToken}` | None | LOW — uses secure random token |
| 6 | `/pdf/*` routes (8 total) | None | MEDIUM — PDF downloads without auth |

### Mitigation Note:
Print routes use `-dash` suffixed variants for in-app access and non-dash for external. The `print/token/{secureToken}` uses a 64-char random token which provides adequate security. The bill/appointment ID-based routes are incrementally guessable.

---

## Summary of Findings

| Category | Count | Risk |
|---|---|---|
| fetch() calls | 3 | LOW (2 superadmin, 1 public lookup) |
| axios calls | 0 | N/A |
| API endpoints (open) | 5 | **HIGH** (3 use `Clinic::first()`) |
| Print/PDF (no auth) | ~14 | MEDIUM (incremental IDs) |
| Livewire wire: | ~28 | LOW (framework-managed) |
| External APIs | 0 | N/A |
| Signed URLs | 0 | N/A |
