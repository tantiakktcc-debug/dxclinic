<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renew Paid License — {{ $clinic->clinic_code }} — DxClinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #0b0f19; color: #f1f5f9; min-height: 100vh; padding: 2rem 1.5rem; }
        .container { max-width: 900px; margin: 0 auto; }
        .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; border-bottom: 1px solid #1e293b; padding-bottom: 1.25rem; }
        .back-link { color: #38bdf8; text-decoration: none; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; }
        .back-link:hover { text-decoration: underline; }
        .title { font-size: 1.6rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; }
        .subtitle { font-size: 0.85rem; color: #94a3b8; margin-top: 0.25rem; }

        /* Current License Card */
        .card-current { background: #131d2e; border: 1px solid #1e293b; border-radius: 1rem; padding: 1.5rem; margin-bottom: 2rem; }
        .clinic-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem; }
        .clinic-id { font-family: 'JetBrains Mono', monospace; font-size: 1.25rem; font-weight: 800; color: #38bdf8; }
        .clinic-name { font-size: 1.1rem; font-weight: 700; color: #ffffff; }
        
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-ACTIVE { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-EXPIRED { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .status-SUSPENDED { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-REVOKED { background: rgba(100, 116, 139, 0.2); color: #94a3b8; border: 1px solid #475569; }

        .current-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; }
        .c-item { background: #0b0f19; border: 1px solid #1e293b; border-radius: 0.5rem; padding: 0.85rem; }
        .c-label { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .c-val { font-size: 0.95rem; font-weight: 700; color: #f1f5f9; margin-top: 0.25rem; }
        .c-val-mono { font-family: 'JetBrains Mono', monospace; }

        /* Form */
        .form-card { background: #131d2e; border: 1px solid #1e293b; border-radius: 1rem; padding: 2rem; }
        .section-title { font-size: 1.1rem; font-weight: 800; color: #ffffff; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
        .section-desc { font-size: 0.85rem; color: #94a3b8; margin-top: -0.75rem; margin-bottom: 1.25rem; }

        /* Plan Selector Grid */
        .plan-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
        .plan-option { background: #0b0f19; border: 2px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; cursor: pointer; transition: all 0.2s; position: relative; }
        .plan-option:hover { border-color: #38bdf8; }
        .plan-option.selected { border-color: #2563eb; background: rgba(37, 99, 235, 0.08); }
        .plan-option input { position: absolute; opacity: 0; }
        .plan-name { font-size: 1rem; font-weight: 700; color: #ffffff; }
        .plan-dur { font-size: 0.8rem; color: #94a3b8; margin-top: 0.15rem; }
        .plan-price { font-size: 1.35rem; font-weight: 800; color: #38bdf8; margin-top: 0.5rem; font-family: 'JetBrains Mono', monospace; }

        /* Doctor Seats Section */
        .doctor-section { background: #0b0f19; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.75rem; }
        .doc-badge { background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.25); color: #38bdf8; padding: 0.2rem 0.5rem; border-radius: 0.35rem; font-size: 0.75rem; font-weight: 700; }
        .checkbox-label { display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.9rem; font-weight: 600; color: #f1f5f9; }
        .checkbox-label input { width: 1.15rem; height: 1.15rem; accent-color: #2563eb; cursor: pointer; }

        /* Input Controls */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
        .field { display: flex; flex-col; gap: 0.35rem; }
        label { font-size: 0.8rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea {
            background: #0b0f19; border: 1px solid #27354a; border-radius: 0.5rem; padding: 0.65rem 0.9rem; color: #f8fafc; font-size: 0.9rem; outline: none; width: 100%; transition: border-color 0.2s;
        }
        input:focus, select:focus, textarea:focus { border-color: #38bdf8; }

        /* Calculation Summary Box */
        .summary-box { background: linear-gradient(135deg, #0d1627, #131d2e); border: 1px solid #2563eb; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1.75rem; }
        .sum-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: #cbd5e1; padding: 0.35rem 0; }
        .sum-row-total { border-top: 1px solid #27354a; margin-top: 0.75rem; padding-top: 0.75rem; font-size: 1.25rem; font-weight: 800; color: #ffffff; }
        .sum-val { font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #ffffff; }
        .sum-total-val { font-family: 'JetBrains Mono', monospace; font-weight: 900; color: #34d399; font-size: 1.4rem; }

        /* Buttons */
        .btn-submit { background: linear-gradient(135deg, #0284c7, #2563eb); color: #ffffff; padding: 0.85rem 1.75rem; border-radius: 0.5rem; font-weight: 800; font-size: 1rem; border: none; cursor: pointer; transition: opacity 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .btn-submit:hover:not(:disabled) { opacity: 0.95; }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-cancel { background: #1e293b; border: 1px solid #334155; color: #cbd5e1; padding: 0.85rem 1.5rem; border-radius: 0.5rem; font-size: 0.95rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-cancel:hover { background: #334155; color: #ffffff; }

        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #f87171; padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div>
                <a href="{{ route('superadmin.dashboard') }}" class="back-link">← Back to Superadmin Fleet</a>
                <h1 class="title" style="margin-top: 0.5rem;">Superadmin One-Click Paid License Renewal</h1>
                <p class="subtitle">Confirm received payment outside the application and atomically renew tenant subscription.</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $error)
                    <div>• {{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if ($isRevoked)
            <div class="alert-error" style="border-width: 2px;">
                ⚠️ This license cannot be renewed through the normal renewal workflow. Please use the appropriate Superadmin reinstatement process.
            </div>
        @endif

        <!-- Current Subscription Card -->
        <div class="card-current">
            <div class="clinic-header">
                <div>
                    <span class="clinic-id">{{ $clinic->clinic_code }}</span>
                    <span class="clinic-name" style="margin-left: 0.5rem;">{{ $clinic->name }}</span>
                    <span style="font-size: 0.85rem; color: #94a3b8; margin-left: 0.5rem;">({{ $clinic->city }} • {{ $clinic->phone }})</span>
                </div>
                <div>
                    <span class="badge status-{{ $license->status }}">{{ $license->status }}</span>
                </div>
            </div>

            <div class="current-grid">
                <div class="c-item">
                    <div class="c-label">Current Plan</div>
                    <div class="c-val">{{ $license->plan?->name ?? 'Standard' }}</div>
                </div>
                <div class="c-item">
                    <div class="c-label">Valid From</div>
                    <div class="c-val c-val-mono">{{ $license->start_date ? $license->start_date->format('d-m-Y') : '-' }}</div>
                </div>
                <div class="c-item">
                    <div class="c-label">Valid Until</div>
                    <div class="c-val c-val-mono" style="color: {{ $license->isExpired() ? '#f87171' : '#38bdf8' }};">
                        {{ $license->end_date ? $license->end_date->format('d-m-Y') : '-' }}
                    </div>
                </div>
                <div class="c-item">
                    <div class="c-label">Remaining Time</div>
                    <div class="c-val c-val-mono" style="color: {{ $license->remainingDays() > 0 ? '#34d399' : '#f87171' }};">
                        {{ $license->remainingDaysFormatted() }}
                    </div>
                </div>
                <div class="c-item">
                    <div class="c-label">Doctor Seats</div>
                    <div class="c-val">
                        {{ $clinic->activeDoctorCount() }} used / {{ $clinic->totalDoctorCapacity() }} capacity
                    </div>
                </div>
                <div class="c-item">
                    <div class="c-label">Clinic Owner / Admin</div>
                    <div class="c-val" style="font-size: 0.85rem;">
                        {{ $owner?->name ?? 'Admin User' }} ({{ $owner?->login_id ?? '-' }})
                    </div>
                </div>
            </div>
        </div>

        <!-- Renewal Form -->
        <form action="{{ route('superadmin.clinics.renew', $clinic) }}" method="POST" id="renewForm" class="form-card">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

            <!-- Step 1: Select Plan -->
            <div class="section-title">
                <span>1. Select Renewal Plan</span>
            </div>
            <p class="section-desc">Select the subscription period purchased by the clinic. (Trial is not an option).</p>

            <div class="plan-grid">
                @foreach ($plans as $plan)
                    @php
                        $isDefault = $plan->code === 'YEARLY';
                    @endphp
                    <div class="plan-option {{ $isDefault ? 'selected' : '' }}" onclick="selectPlan({{ $plan->id }}, {{ $plan->duration_months }}, {{ (float) $plan->price }}, {{ (float) ($plan->additional_doctor_seat_price ?? 2999) }}, this)">
                        <input type="radio" name="plan_id" value="{{ $plan->id }}" {{ $isDefault ? 'checked' : '' }} required>
                        <div class="plan-name">{{ $plan->name }}</div>
                        <div class="plan-dur">{{ $plan->duration_months }} Months Coverage</div>
                        <div class="plan-price">₹{{ number_format($plan->price, 0) }}</div>
                    </div>
                @endforeach
            </div>

            <!-- Step 2: Additional Doctor Seats (Yearly Only - Phase 6 & 7) -->
            <div id="doctorSeatsSection" class="doctor-section">
                <div class="section-title" style="margin-bottom: 0.75rem; font-size: 1rem;">
                    <span>2. Yearly Additional Doctor Seats</span>
                    <span class="doc-badge">Annual Rate: ₹2,999 / doctor / year</span>
                </div>
                <p class="section-desc">
                    Distinguish clearly between renewing existing paid doctor seats and purchasing brand new seats.
                </p>

                <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid #1e293b; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; font-size: 0.85rem;">
                    <div>Base Included Seats: <strong style="color: #ffffff;">1 Seat</strong></div>
                    <div>Currently Paid Seats: <strong style="color: #38bdf8;">{{ $license->additional_doctor_seats ?? 0 }} Seats</strong></div>
                    <div>Current Doctors Used: <strong style="color: #fbbf24;">{{ $clinic->activeDoctorCount() }} Doctors</strong></div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <!-- Option A & B: Renew Existing Paid Seats -->
                    @if (($license->additional_doctor_seats ?? 0) > 0)
                        <label class="checkbox-label">
                            <input type="checkbox" name="renew_existing_doctor_seats" id="renewExistingCheckbox" value="1" checked onchange="recalculate()">
                            <span>
                                Renew existing <strong>{{ $license->additional_doctor_seats }}</strong> paid doctor seat(s)
                                <span style="color: #38bdf8; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem;">
                                    ({{ $license->additional_doctor_seats }} × ₹2,999 = ₹<span id="existingSeatCostDisplay">{{ number_format($license->additional_doctor_seats * 2999, 0) }}</span>)
                                </span>
                            </span>
                        </label>
                    @else
                        <input type="hidden" name="renew_existing_doctor_seats" value="0">
                        <div style="font-size: 0.85rem; color: #64748b;">No existing additional doctor seats on current license.</div>
                    @endif

                    <!-- Option C: Add NEW Doctor Seats -->
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <label style="font-size: 0.85rem; color: #cbd5e1; text-transform: none;">
                            Purchase <strong>NEW</strong> additional doctor seats:
                        </label>
                        <input type="number" name="new_doctor_seats_count" id="newSeatsInput" min="0" max="50" value="0" style="width: 100px; padding: 0.4rem 0.6rem;" oninput="recalculate()">
                        <span style="font-size: 0.85rem; color: #94a3b8;">
                            Cost: <span style="color: #38bdf8; font-family: 'JetBrains Mono', monospace;">₹<span id="newSeatCostDisplay">0</span></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Step 3: Date Preview (Phase 9 & 10) -->
            <div style="background: #0b0f19; border: 1px solid #1e293b; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.75rem;">
                <div class="section-title" style="margin-bottom: 0.5rem; font-size: 1rem;">
                    <span>3. Subscription Validity Calculation</span>
                </div>
                <div id="datePreviewText" style="font-size: 0.9rem; color: #38bdf8; font-weight: 600;">
                    Calculating dates...
                </div>
                <div id="datePreviewExpl" style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.35rem;">
                    <!-- Explanation -->
                </div>
            </div>

            <!-- Step 4: Summary & Pricing Breakdown -->
            <div class="summary-box">
                <div class="sum-row">
                    <span>Base Renewal Amount:</span>
                    <span class="sum-val">₹<span id="sumBaseAmount">14,999.00</span></span>
                </div>
                <div class="sum-row" id="sumExistingRow">
                    <span>Existing Doctor Seats Renewal:</span>
                    <span class="sum-val">₹<span id="sumExistingAmount">0.00</span></span>
                </div>
                <div class="sum-row" id="sumNewRow">
                    <span>New Doctor Seats:</span>
                    <span class="sum-val">₹<span id="sumNewAmount">0.00</span></span>
                </div>
                <div class="sum-row">
                    <span>Subtotal:</span>
                    <span class="sum-val">₹<span id="sumSubtotal">14,999.00</span></span>
                </div>
                <div class="sum-row" style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed #27354a;">
                    <span>Discount (Configurable):</span>
                    <div style="display: inline-flex; align-items: center; gap: 0.35rem;">
                        <span style="color: #94a3b8;">₹</span>
                        <input type="number" step="0.01" name="discount_amount" id="discountInput" value="0.00" min="0" style="width: 120px; padding: 0.3rem 0.5rem; text-align: right; font-family: 'JetBrains Mono', monospace;" oninput="recalculate()">
                    </div>
                </div>
                <div class="sum-row sum-row-total">
                    <span>NET PAYABLE:</span>
                    <span class="sum-total-val">₹<span id="sumNetAmount">14,999.00</span></span>
                </div>
            </div>

            <!-- Step 5: Payment Confirmation (Phase 13) -->
            <div class="section-title">
                <span>4. Payment Confirmation</span>
            </div>
            <p class="section-desc">Record external payment confirmation details (Authorized Superadmin only).</p>

            <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 0.75rem; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
                <label class="checkbox-label" style="color: #34d399;">
                    <input type="checkbox" name="payment_received" value="1" checked required>
                    <span>PAYMENT RECEIVED: I confirm payment has been received in full from the clinic owner outside the application.</span>
                </label>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label>Payment Method *</label>
                    <select name="payment_method" required>
                        <option value="UPI" selected>UPI (GPay / PhonePe / Paytm)</option>
                        <option value="BANK_TRANSFER">Bank Transfer (NEFT / IMPS / RTGS)</option>
                        <option value="CASH">Cash</option>
                        <option value="OTHER">Other / Cheque</option>
                    </select>
                </div>
                <div class="field">
                    <label>Payment Date *</label>
                    <input type="date" name="payment_date" id="paymentDateInput" value="{{ now()->toDateString() }}" required onchange="recalculate()">
                </div>
                <div class="field">
                    <label>Payment Reference / UTR #</label>
                    <input type="text" name="payment_reference" placeholder="e.g. UPI-9842109823 or NEFT-34981" maxlength="100">
                </div>
                <div class="field">
                    <label>Audit Notes</label>
                    <input type="text" name="notes" placeholder="e.g. Paid by Dr. Ramesh via UPI" maxlength="500">
                </div>
            </div>

            <!-- Actions (Phase 14 & 16) -->
            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 1rem; border-top: 1px solid #1e293b; padding-top: 1.5rem; margin-top: 1.5rem;">
                <a href="{{ route('superadmin.dashboard') }}" class="btn-cancel">Cancel</a>
                <button type="submit" id="btnSubmit" class="btn-submit" {{ $isRevoked ? 'disabled' : '' }}>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 1.2rem; height: 1.2rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span>Confirm Payment & Renew →</span>
                </button>
            </div>
        </form>
    </div>

    <script>
        var currentEndDate = "{{ $license->end_date ? $license->end_date->format('Y-m-d') : '' }}";
        var isCurrentActive = {{ ($license->status === 'ACTIVE' && $license->end_date && $license->end_date >= now()->startOfDay()) ? 'true' : 'false' }};
        var existingPaidSeats = {{ (int) ($license->additional_doctor_seats ?? 0) }};

        var selectedPlanId = {{ $plans->firstWhere('code', 'YEARLY')?->id ?? $plans->first()?->id ?? 1 }};
        var selectedDuration = {{ $plans->firstWhere('code', 'YEARLY')?->duration_months ?? 12 }};
        var selectedBasePrice = {{ (float) ($plans->firstWhere('code', 'YEARLY')?->price ?? 14999) }};
        var selectedDoctorFee = {{ (float) ($plans->firstWhere('code', 'YEARLY')?->additional_doctor_seat_price ?? 2999) }};

        function selectPlan(planId, duration, price, docFee, el) {
            document.querySelectorAll('.plan-option').forEach(function(p) { p.classList.remove('selected'); });
            el.classList.add('selected');
            el.querySelector('input[type="radio"]').checked = true;

            selectedPlanId = planId;
            selectedDuration = duration;
            selectedBasePrice = price;
            selectedDoctorFee = docFee;

            var docSection = document.getElementById('doctorSeatsSection');
            if (duration >= 12) {
                docSection.style.display = 'block';
            } else {
                docSection.style.display = 'none';
            }

            recalculate();
        }

        function recalculate() {
            var isYearly = selectedDuration >= 12;
            var renewExisting = false;
            var existingBox = document.getElementById('renewExistingCheckbox');
            if (existingBox && existingBox.checked) {
                renewExisting = true;
            }

            var newSeatsInput = document.getElementById('newSeatsInput');
            var newSeatsCount = parseInt(newSeatsInput ? newSeatsInput.value : 0) || 0;

            var existingSeatsRenewed = (isYearly && renewExisting) ? existingPaidSeats : 0;
            var existingSeatCost = existingSeatsRenewed * selectedDoctorFee;
            var newSeatCost = (isYearly ? newSeatsCount : 0) * selectedDoctorFee;
            var totalDoctorCost = existingSeatCost + newSeatCost;

            var existingDisp = document.getElementById('existingSeatCostDisplay');
            if (existingDisp) existingDisp.innerText = Math.round(existingPaidSeats * selectedDoctorFee).toLocaleString('en-IN');

            var newDisp = document.getElementById('newSeatCostDisplay');
            if (newDisp) newDisp.innerText = Math.round(newSeatCost).toLocaleString('en-IN');

            var baseAmount = selectedBasePrice;
            var subtotal = baseAmount + (isYearly ? totalDoctorCost : 0);

            var discountInput = document.getElementById('discountInput');
            var discount = parseFloat(discountInput.value) || 0;
            var netAmount = Math.max(0, subtotal - discount);

            document.getElementById('sumBaseAmount').innerText = baseAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 });
            document.getElementById('sumExistingAmount').innerText = existingSeatCost.toLocaleString('en-IN', { minimumFractionDigits: 2 });
            document.getElementById('sumNewAmount').innerText = newSeatCost.toLocaleString('en-IN', { minimumFractionDigits: 2 });
            document.getElementById('sumSubtotal').innerText = subtotal.toLocaleString('en-IN', { minimumFractionDigits: 2 });
            document.getElementById('sumNetAmount').innerText = netAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 });

            // Date calculations (Phase 9 & 10)
            var paymentDateVal = document.getElementById('paymentDateInput').value;
            var pDate = paymentDateVal ? new Date(paymentDateVal) : new Date();

            var newStart, newEnd;
            var expl = '';

            if (isCurrentActive && currentEndDate) {
                var cEnd = new Date(currentEndDate);
                if (cEnd >= pDate) {
                    newStart = new Date(cEnd);
                    newStart.setDate(newStart.getDate() + 1);
                    expl = 'Active license extension: New period starts the day after current expiry (' + formatDate(cEnd) + '), preserving all already-paid days.';
                } else {
                    newStart = new Date(pDate);
                    expl = 'Payment confirmation date: New period begins immediately.';
                }
            } else {
                newStart = new Date(pDate);
                expl = 'Expired/Suspended renewal: New period starts immediately from payment confirmation date (' + formatDate(pDate) + ').';
            }

            newEnd = new Date(newStart);
            newEnd.setMonth(newEnd.getMonth() + selectedDuration);
            newEnd.setDate(newEnd.getDate() - 1);

            document.getElementById('datePreviewText').innerText = formatDate(newStart) + ' → ' + formatDate(newEnd) + ' (' + selectedDuration + ' Months)';
            document.getElementById('datePreviewExpl').innerText = expl;
        }

        function formatDate(d) {
            var day = String(d.getDate()).padStart(2, '0');
            var mon = String(d.getMonth() + 1).padStart(2, '0');
            var yr = d.getFullYear();
            return day + '-' + mon + '-' + yr;
        }

        // Duplicate click protection (Phase 16)
        document.getElementById('renewForm').addEventListener('submit', function(e) {
            var btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = 'Processing Renewal...';
        });

        // Initialize calculation on load
        document.addEventListener('DOMContentLoaded', function() {
            recalculate();
        });
    </script>
</body>
</html>
