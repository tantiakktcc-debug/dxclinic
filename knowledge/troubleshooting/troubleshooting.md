# DxClinic Troubleshooting & System Diagnostics (Revision 35)

**Audience:** System Administrators, Clinic Operators, Support Technicians  
**Version:** 35.0.0  
**Updated:** 2026-09-30  

---

## 1. Network & Mobile Device Access
- **Local Application Server:** `http://127.0.0.1:8000` (Strict Loopback Only)
- **Public Application Hostname:** `https://dxclinic.example.local` (Cloudflare Tunnel)
- **Public Patient Booking PWA:** `https://dxclinic.example.local/book/{public_slug}` (e.g. `/book/sharmacare`)
- **Public Short QR Redirect:** `https://dxclinic.example.local/b/{public_slug}` (e.g. `/b/sharmacare`)
- **Reception / Staff Portal:** `http://127.0.0.1:8000/login` (Local) | `https://dxclinic.example.local/login` (Remote HTTPS)
- **Issue:** Mobile phone or remote tablet cannot connect to DxClinic.
  - Note: DxClinic binds strictly to loopback `127.0.0.1:8000`. Direct LAN binding (`0.0.0.0:8000` or `192.168.1.2:8000`) is intentionally disabled for security.
  - Remote and mobile devices connect exclusively via the secure Cloudflare Tunnel: `https://dxclinic.example.local`.
  - Verify that the `Cloudflared` Windows Service is running: run `check_dxclinic_server.bat` on the host machine.
  - Verify that Laravel is listening on `127.0.0.1:8000`: run `start_dxclinic.bat`.
  - Ensure the client device has active internet access and opens the canonical HTTPS URL (`https://dxclinic.example.local/book/{public_slug}`). Generic `/book` returns HTTP 404 by design for multi-tenant safety.

## 2. Printer & Document Formatting
- **Issue:** Receipt prints on A4 instead of A5.
  - DxClinic receipts are calibrated strictly for A5 paper (`148mm × 210mm`).
  - In printer dialog, ensure paper size is set to **A5**. Set scale to "100%" or "Fit to paper".
- **Issue:** Doctor prescription pad text does not align with pre-printed lines.
  - The Doctor Pad is calibrated for `8.25in × 11.671in`.
  - Margins must be set to "None" in browser print preview.
  - Use the PDF download option (`/pdf/doctor-pad/{appointmentId}`) for millimeter-precise rendering.

## 3. Double-Entry Accounting Inconsistencies
- **Issue:** Trial balance shows difference $\neq 0.00$.
  - Run the diagnostic script: `php scratch/verify_db_accounting.php`.
  - Identify unbalanced journals with: `SELECT * FROM journals WHERE id NOT IN (SELECT journal_id FROM journal_entries GROUP BY journal_id HAVING SUM(debit) = SUM(credit));`.

## 4. Cash Drawer Variances
- **Issue:** Drawer cash does not balance at closing.
  - Check whether a staff member took cash out for expenses without recording it in the Expenses register.
  - Check whether a cash deposit to the bank was recorded in the system but the cash remained in the drawer.
  - Check whether a UPI payment was accidentally settled as CASH.

## 5. Backup & Recovery Failures
- **Issue:** Restore rejected with "Decryption failed or invalid password".
  - Backup files (`.ncbk`) are encrypted using AES-256-GCM with a user-supplied password. If the password is lost, the backup container cannot be decrypted.
- **Issue:** "Checksum mismatch detected".
  - Indicates file corruption or unauthorized tampering of the `.ncbk` file. Use an earlier valid snapshot.
