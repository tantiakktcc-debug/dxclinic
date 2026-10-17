<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Clinic License — DxClinic Superadmin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #0b0f19; color: #f1f5f9; min-height: 100vh; padding: 2rem 1.5rem; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; border-bottom: 1px solid #1e293b; padding-bottom: 1.5rem; }
        .back-link { color: #38bdf8; text-decoration: none; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; }
        .back-link:hover { text-decoration: underline; }
        h1 { font-size: 1.75rem; font-weight: 800; color: #ffffff; }
        .card { background: #131d2e; border: 1px solid #1e293b; border-radius: 1rem; padding: 2rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4); }
        .section-title { font-size: 1.1rem; font-weight: 700; color: #38bdf8; margin-bottom: 1.25rem; border-bottom: 1px solid #1e293b; padding-bottom: 0.5rem; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.825rem; font-weight: 600; color: #94a3b8; margin-bottom: 0.4rem; }
        input[type="text"], input[type="email"], input[type="number"], select, textarea { width: 100%; background: #0b0f19; border: 1px solid #27354a; border-radius: 0.5rem; padding: 0.65rem 0.85rem; color: #f8fafc; font-size: 0.9rem; outline: none; }
        input:focus, select:focus, textarea:focus { border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); }
        .btn-primary { background: linear-gradient(135deg, #0284c7, #2563eb); color: #ffffff; border: none; padding: 0.85rem 1.75rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: opacity 0.2s; }
        .btn-primary:hover { opacity: 0.9; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; }
        .badge-notice { background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.2); color: #38bdf8; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.85rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <a href="{{ route('superadmin.dashboard') }}" class="back-link">← Back to Superadmin Fleet</a>
                <h1 style="margin-top: 0.5rem;">Issue New Clinic License</h1>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert-error">
                <strong>Please fix the errors below:</strong>
                <ul style="margin-left: 1.25rem; margin-top: 0.5rem;">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="badge-notice">
            ⚡ <strong>Automated Provisioning:</strong> This action will atomically allocate the next Clinic ID (e.g. NX0002), generate a cryptographically strong License Key & Activation Code, register the initial Admin user, and prepare the one-time Onboarding Credential Package.
        </div>

        <form action="{{ route('superadmin.licenses.issue.submit') }}" method="POST" class="card">
            @csrf

            <div class="section-title">1. Clinic Information</div>
            <div class="grid-2">
                <div class="form-group">
                    <label for="clinic_name">Clinic Facility Name *</label>
                    <input type="text" id="clinic_name" name="clinic_name" value="{{ old('clinic_name') }}" placeholder="e.g. Apex Health Clinic" required>
                </div>
                <div class="form-group">
                    <label for="clinic_city">City *</label>
                    <input type="text" id="clinic_city" name="clinic_city" value="{{ old('clinic_city') }}" placeholder="e.g. Varanasi" required>
                </div>
                <div class="form-group">
                    <label for="clinic_phone">Clinic Contact Phone</label>
                    <input type="text" id="clinic_phone" name="clinic_phone" value="{{ old('clinic_phone') }}" placeholder="e.g. +91 9876543210">
                </div>
                <div class="form-group">
                    <label for="clinic_email">Clinic Email</label>
                    <input type="email" id="clinic_email" name="clinic_email" value="{{ old('clinic_email') }}" placeholder="clinic@example.com">
                </div>
            </div>

            <div class="form-group">
                <label for="clinic_address">Full Address</label>
                <input type="text" id="clinic_address" name="clinic_address" value="{{ old('clinic_address') }}" placeholder="Street, Locality, Landmark">
            </div>

            <div class="grid-2" style="margin-top: 1rem;">
                <div class="form-group">
                    <label for="max_advance_booking_days">Max Advance Booking Days</label>
                    <input type="number" id="max_advance_booking_days" name="max_advance_booking_days" value="{{ old('max_advance_booking_days', 1) }}" min="1" max="30">
                    <small style="color: #64748b;">Default 1 allows Today & Tomorrow only.</small>
                </div>
                <div class="form-group">
                    <label for="max_patients_per_mobile">Max Patients Per Mobile</label>
                    <input type="number" id="max_patients_per_mobile" name="max_patients_per_mobile" value="{{ old('max_patients_per_mobile', 4) }}" min="1" max="20">
                    <small style="color: #64748b;">Family member threshold per mobile.</small>
                </div>
            </div>

            <div class="section-title" style="margin-top: 1.5rem;">2. Initial Clinic Administrator Account</div>
            <div class="grid-2">
                <div class="form-group">
                    <label for="admin_name">Admin Full Name *</label>
                    <input type="text" id="admin_name" name="admin_name" value="{{ old('admin_name') }}" placeholder="e.g. Dr. Rajesh Sharma" required>
                </div>
                <div class="form-group">
                    <label for="admin_email">Admin Email *</label>
                    <input type="email" id="admin_email" name="admin_email" value="{{ old('admin_email') }}" placeholder="admin@example.com" required>
                </div>
                <div class="form-group">
                    <label for="admin_phone">Admin Mobile</label>
                    <input type="text" id="admin_phone" name="admin_phone" value="{{ old('admin_phone') }}" placeholder="10-digit mobile number">
                </div>
                <div class="form-group">
                    <label>Admin User ID</label>
                    <input type="text" value="Auto-allocated: ADM0001" disabled style="opacity: 0.6;">
                </div>
            </div>

            <div class="section-title" style="margin-top: 1.5rem;">3. Subscription Entitlement & License</div>
            <div class="grid-2">
                <div class="form-group">
                    <label for="plan_id">Subscription Plan *</label>
                    <select id="plan_id" name="plan_id" required onchange="recalculatePricing()">
                        @foreach ($plans as $p)
                            <option value="{{ $p->id }}" 
                                    data-price="{{ $p->price }}" 
                                    data-months="{{ $p->duration_months }}" 
                                    data-included="{{ $p->included_doctor_seats ?? 1 }}" 
                                    data-addon-price="{{ $p->additional_doctor_seat_price ?? 2999.00 }}"
                                    {{ old('plan_id') == $p->id ? 'selected' : '' }}>
                                {{ $p->name }} ({{ $p->duration_months }} Months) — ₹{{ number_format($p->price, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="start_date">License Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="{{ old('start_date', date('Y-m-d')) }}" onchange="recalculatePricing()">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="included_doctor_seats">Included Doctor Seats *</label>
                    <input type="number" id="included_doctor_seats" name="included_doctor_seats" value="{{ old('included_doctor_seats', 1) }}" min="1" max="50" required oninput="recalculatePricing()">
                    <small style="color: #64748b;">Included with base subscription plan.</small>
                </div>
                <div class="form-group">
                    <label for="additional_doctor_seats">Additional Doctor Seats (Paid Add-on)</label>
                    <input type="number" id="additional_doctor_seats" name="additional_doctor_seats" value="{{ old('additional_doctor_seats', 0) }}" min="0" max="50" oninput="recalculatePricing()">
                    <small style="color: #64748b;">Add-on doctor capacity.</small>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="additional_doctor_fee_per_year">Additional Doctor Annual Fee (₹) *</label>
                    <input type="number" step="0.01" id="additional_doctor_fee_per_year" name="additional_doctor_fee_per_year" value="{{ old('additional_doctor_fee_per_year', '2999.00') }}" min="0" required oninput="recalculatePricing()">
                    <small style="color: #64748b;">Configurable per-doctor add-on rate.</small>
                </div>
                <div class="form-group">
                    <label for="discount_amount">Discount Amount (₹)</label>
                    <input type="number" step="0.01" id="discount_amount" name="discount_amount" value="{{ old('discount_amount', '0.00') }}" min="0" oninput="recalculatePricing()">
                </div>
            </div>

            <!-- Dynamic Pricing Calculation Box -->
            <div style="background: #0b1320; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; border-bottom: 1px solid #1e293b; padding-bottom: 0.5rem;">
                    <span style="font-weight: 700; color: #38bdf8; font-size: 0.95rem;">Pricing & Capacity Breakdown</span>
                    <span id="calc_end_date" style="font-size: 0.8rem; color: #94a3b8; font-family: 'JetBrains Mono', monospace;">Valid until: -</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; text-align: center;">
                    <div style="background: #131d2e; padding: 0.75rem; border-radius: 0.5rem;">
                        <div style="font-size: 0.75rem; color: #94a3b8;">Base Plan</div>
                        <div id="calc_base_amount" style="font-size: 1.1rem; font-weight: 700; color: #ffffff; font-family: 'JetBrains Mono', monospace;">₹0.00</div>
                    </div>
                    <div style="background: #131d2e; padding: 0.75rem; border-radius: 0.5rem;">
                        <div style="font-size: 0.75rem; color: #94a3b8;">Addon Doctors</div>
                        <div id="calc_addon_amount" style="font-size: 1.1rem; font-weight: 700; color: #38bdf8; font-family: 'JetBrains Mono', monospace;">₹0.00</div>
                    </div>
                    <div style="background: #131d2e; padding: 0.75rem; border-radius: 0.5rem;">
                        <div style="font-size: 0.75rem; color: #94a3b8;">Subtotal</div>
                        <div id="calc_subtotal" style="font-size: 1.1rem; font-weight: 700; color: #cbd5e1; font-family: 'JetBrains Mono', monospace;">₹0.00</div>
                    </div>
                    <div style="background: #131d2e; padding: 0.75rem; border-radius: 0.5rem;">
                        <div style="font-size: 0.75rem; color: #94a3b8;">Discount</div>
                        <div id="calc_discount" style="font-size: 1.1rem; font-weight: 700; color: #f87171; font-family: 'JetBrains Mono', monospace;">-₹0.00</div>
                    </div>
                    <div style="background: #0f2744; border: 1px solid #0284c7; padding: 0.75rem; border-radius: 0.5rem;">
                        <div style="font-size: 0.75rem; color: #7dd3fc; font-weight: 600;">Net Payable</div>
                        <div id="calc_net" style="font-size: 1.25rem; font-weight: 800; color: #38bdf8; font-family: 'JetBrains Mono', monospace;">₹0.00</div>
                    </div>
                    <div style="background: #052e16; border: 1px solid #16a34a; padding: 0.75rem; border-radius: 0.5rem;">
                        <div style="font-size: 0.75rem; color: #86efac; font-weight: 600;">Doctor Seats</div>
                        <div id="calc_total_seats" style="font-size: 1.25rem; font-weight: 800; color: #34d399; font-family: 'JetBrains Mono', monospace;">1</div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Administrative Notes</label>
                <textarea id="notes" name="notes" rows="2" placeholder="Optional notes for audit logs...">{{ old('notes') }}</textarea>
            </div>

            <div style="text-align: right; margin-top: 1.5rem;">
                <button type="submit" class="btn-primary">Generate Credentials & Issue License →</button>
            </div>
        </form>
    </div>

    <script>
        function recalculatePricing() {
            const planSelect = document.getElementById('plan_id');
            const selectedOpt = planSelect.options[planSelect.selectedIndex];
            if (!selectedOpt) return;

            const basePrice = parseFloat(selectedOpt.getAttribute('data-price') || 0);
            const months = parseInt(selectedOpt.getAttribute('data-months') || 12);
            const planIncluded = parseInt(selectedOpt.getAttribute('data-included') || 1);
            const planAddonPrice = parseFloat(selectedOpt.getAttribute('data-addon-price') || 2999);

            const includedInput = document.getElementById('included_doctor_seats');
            const additionalInput = document.getElementById('additional_doctor_seats');
            const feeInput = document.getElementById('additional_doctor_fee_per_year');
            const discountInput = document.getElementById('discount_amount');
            const startDateInput = document.getElementById('start_date');

            // Default fee if not touched
            if (!feeInput.value || feeInput.value === '0') {
                feeInput.value = planAddonPrice.toFixed(2);
            }

            const incSeats = parseInt(includedInput.value || 1);
            const addSeats = parseInt(additionalInput.value || 0);
            const annualFee = parseFloat(feeInput.value || 0);
            const discount = parseFloat(discountInput.value || 0);

            const addonAmount = addSeats * annualFee;
            const subtotal = basePrice + addonAmount;
            const net = Math.max(0, subtotal - discount);
            const totalSeats = incSeats + addSeats;

            document.getElementById('calc_base_amount').textContent = '₹' + basePrice.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('calc_addon_amount').textContent = '₹' + addonAmount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('calc_subtotal').textContent = '₹' + subtotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('calc_discount').textContent = '-₹' + discount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('calc_net').textContent = '₹' + net.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('calc_total_seats').textContent = totalSeats + ' (' + incSeats + ' + ' + addSeats + ')';

            // Calculate estimated end date
            if (startDateInput.value) {
                const sDate = new Date(startDateInput.value);
                sDate.setMonth(sDate.getMonth() + months);
                const yyyy = sDate.getFullYear();
                const mm = String(sDate.getMonth() + 1).padStart(2, '0');
                const dd = String(sDate.getDate()).padStart(2, '0');
                document.getElementById('calc_end_date').textContent = 'Valid until: ' + dd + '-' + mm + '-' + yyyy + ' (' + months + ' mo)';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', recalculatePricing);
    </script>
</body>
</html>
