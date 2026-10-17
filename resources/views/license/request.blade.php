<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request a License — DxClinic</title>
    <meta name="description" content="Request a DxClinic license for your clinic. Choose your plan, number of doctors, and get started with India's most advanced clinic management system.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #070b14;
            --bg-card: #0d1525;
            --bg-input: #111b2e;
            --border: #1a2744;
            --border-focus: #3b82f6;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent: #3b82f6;
            --accent-glow: rgba(59, 130, 246, 0.15);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; }

        /* Hero */
        .hero { padding: 3rem 1.5rem 1rem; text-align: center; background: linear-gradient(180deg, rgba(59,130,246,0.08) 0%, transparent 100%); }
        .hero-badge { display: inline-flex; align-items: center; gap: 0.5rem; background: var(--accent-glow); border: 1px solid rgba(59,130,246,0.3); color: var(--accent); padding: 0.35rem 1rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; }
        .hero h1 { font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 900; letter-spacing: -0.03em; margin-bottom: 0.5rem; background: linear-gradient(135deg, #ffffff 40%, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { color: var(--text-secondary); font-size: 1.05rem; max-width: 540px; margin: 0 auto; line-height: 1.6; }

        /* Form Container */
        .form-container { max-width: 680px; margin: 1.5rem auto 3rem; padding: 0 1.5rem; }
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 1.25rem; padding: 2rem; }
        .card + .card { margin-top: 1.5rem; }
        .card-title { font-size: 1.05rem; font-weight: 700; color: var(--accent); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
        .card-title .num { width: 28px; height: 28px; background: var(--accent-glow); border: 1px solid rgba(59,130,246,0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; }

        /* Fields */
        .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .field-grid .full { grid-column: 1 / -1; }
        .field { display: flex; flex-direction: column; gap: 0.35rem; }
        .field label { font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); }
        .field label .req { color: var(--danger); margin-left: 2px; }
        .field input, .field select, .field textarea { background: var(--bg-input); border: 1px solid var(--border); color: var(--text-primary); padding: 0.65rem 0.85rem; border-radius: 0.5rem; font-size: 0.95rem; transition: border-color 0.2s, box-shadow 0.2s; }
        .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--border-focus); box-shadow: 0 0 0 3px var(--accent-glow); }
        .field textarea { resize: vertical; min-height: 70px; }

        /* Plans */
        .plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
        .plan-card { background: var(--bg-input); border: 2px solid var(--border); border-radius: 0.75rem; padding: 1rem; text-align: center; cursor: pointer; transition: all 0.2s; position: relative; }
        .plan-card:hover { border-color: rgba(59,130,246,0.4); }
        .plan-card.selected { border-color: var(--accent); background: var(--accent-glow); }
        .plan-card input[type="radio"] { position: absolute; opacity: 0; }
        .plan-name { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.25rem; }
        .plan-price { font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 1.15rem; color: var(--accent); }
        .plan-duration { font-size: 0.8rem; color: var(--text-muted); }

        /* Doctor Selector */
        .doctor-selector { display: flex; align-items: center; gap: 1rem; }
        .doc-btn { width: 36px; height: 36px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-primary); border-radius: 0.5rem; font-size: 1.2rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .doc-btn:hover { border-color: var(--accent); background: var(--accent-glow); }
        .doc-count { font-family: 'JetBrains Mono', monospace; font-size: 1.3rem; font-weight: 800; min-width: 40px; text-align: center; }

        /* Trial Toggle */
        .trial-toggle { display: flex; align-items: center; gap: 0.75rem; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); border-radius: 0.5rem; padding: 0.75rem 1rem; cursor: pointer; }
        .trial-toggle input { width: 18px; height: 18px; accent-color: var(--success); cursor: pointer; }
        .trial-toggle .trial-text { font-size: 0.9rem; font-weight: 600; color: var(--success); }
        .trial-toggle .trial-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 2px; }

        /* Pricing Summary */
        .pricing-summary { background: var(--bg-input); border: 1px solid var(--border); border-radius: 0.75rem; padding: 1.25rem; margin-top: 1rem; }
        .price-row { display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.9rem; }
        .price-row .label { color: var(--text-secondary); }
        .price-row .value { font-family: 'JetBrains Mono', monospace; font-weight: 600; }
        .price-row.total { border-top: 1px solid var(--border); margin-top: 0.5rem; padding-top: 0.75rem; font-size: 1.05rem; font-weight: 800; }
        .price-row.total .value { color: var(--accent); }

        /* Submit */
        .submit-bar { margin-top: 1.5rem; text-align: center; }
        .btn-submit { background: linear-gradient(135deg, #2563eb, #3b82f6); color: #ffffff; border: none; padding: 0.85rem 2.5rem; border-radius: 0.75rem; font-size: 1.05rem; font-weight: 700; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 15px rgba(59,130,246,0.3); }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(59,130,246,0.4); }
        .btn-submit:active { transform: translateY(0); }

        /* Error */
        .alert-error { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; padding: 0.85rem 1rem; border-radius: 0.5rem; font-size: 0.9rem; margin-bottom: 1.25rem; }

        /* Footer Link */
        .footer-links { text-align: center; margin-top: 1.5rem; padding-bottom: 2rem; }
        .footer-links a { color: var(--text-muted); font-size: 0.85rem; text-decoration: none; margin: 0 0.75rem; }
        .footer-links a:hover { color: var(--accent); }

        /* Honeypot */
        .hp-field { position: absolute; left: -9999px; opacity: 0; height: 0; width: 0; overflow: hidden; }

        @media (max-width: 600px) {
            .field-grid { grid-template-columns: 1fr; }
            .plans-grid { grid-template-columns: 1fr 1fr; }
            .card { padding: 1.25rem; }
        }
    </style>
</head>
<body>
    <div class="hero">
        <div class="hero-badge">🏥 DxClinic License Request</div>
        <h1>Request Your DxClinic License</h1>
        <p>Fill out the form below to request a new clinic license. Our team will contact you after payment confirmation.</p>
    </div>

    <div class="form-container">
        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $error)
                    <div>⚠️ {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('license.request.submit') }}" id="licenseRequestForm">
            @csrf

            {{-- Honeypot --}}
            <div class="hp-field">
                <label for="website_url">Website</label>
                <input type="text" name="website_url" id="website_url" tabindex="-1" autocomplete="off">
            </div>

            {{-- 1. Clinic Information --}}
            <div class="card">
                <div class="card-title"><span class="num">1</span> Clinic Information</div>
                <div class="field-grid">
                    <div class="field full">
                        <label>Clinic Name <span class="req">*</span></label>
                        <input type="text" name="clinic_name" value="{{ old('clinic_name') }}" required placeholder="e.g. Sharma Care Clinic">
                    </div>
                    <div class="field">
                        <label>Clinic Owner Name <span class="req">*</span></label>
                        <input type="text" name="owner_name" value="{{ old('owner_name') }}" required placeholder="e.g. Dr. Raj Kumar">
                    </div>
                    <div class="field">
                        <label>Mobile Number <span class="req">*</span></label>
                        <input type="tel" name="mobile" value="{{ old('mobile') }}" required placeholder="e.g. 9876543210">
                    </div>
                    <div class="field">
                        <label>Email Address <span class="req">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required placeholder="e.g. clinic@example.com">
                    </div>
                    <div class="field">
                        <label>City</label>
                        <input type="text" name="city" value="{{ old('city') }}" placeholder="e.g. Varanasi">
                    </div>
                    <div class="field full">
                        <label>Address</label>
                        <textarea name="address" placeholder="Full clinic address">{{ old('address') }}</textarea>
                    </div>
                    <div class="field">
                        <label>Pincode</label>
                        <input type="text" name="pincode" value="{{ old('pincode') }}" placeholder="e.g. 221001" maxlength="10">
                    </div>
                </div>
            </div>

            {{-- 2. Subscription Plan --}}
            <div class="card">
                <div class="card-title"><span class="num">2</span> Choose Subscription Plan</div>
                <div class="plans-grid" id="plansGrid">
                    @foreach ($plans as $plan)
                        <label class="plan-card {{ old('plan_id') == $plan->id ? 'selected' : '' }}" data-plan-id="{{ $plan->id }}" data-price="{{ $plan->price }}" data-months="{{ $plan->duration_months }}" data-included-seats="{{ $plan->included_doctor_seats }}" data-seat-price="{{ $plan->additional_doctor_seat_price }}">
                            <input type="radio" name="plan_id" value="{{ $plan->id }}" {{ old('plan_id') == $plan->id ? 'checked' : '' }} required>
                            <div class="plan-name">{{ $plan->name }}</div>
                            <div class="plan-price">₹{{ number_format($plan->price, 0) }}</div>
                            <div class="plan-duration">{{ $plan->duration_months }} months</div>
                        </label>
                    @endforeach
                </div>

                {{-- Trial Toggle --}}
                <label class="trial-toggle">
                    <input type="checkbox" name="wants_trial" value="1" {{ old('wants_trial') ? 'checked' : '' }} id="trialToggle">
                    <div>
                        <div class="trial-text">🎁 Add 1 Month FREE Trial</div>
                        <div class="trial-sub">Get an additional month at no extra cost with your paid subscription</div>
                    </div>
                </label>
            </div>

            {{-- 3. Doctor Configuration --}}
            <div class="card">
                <div class="card-title"><span class="num">3</span> How Many Doctors?</div>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;">Select the total number of doctors that will use DxClinic at your facility.</p>
                <div class="doctor-selector">
                    <button type="button" class="doc-btn" onclick="changeDocCount(-1)">−</button>
                    <span class="doc-count" id="docCountDisplay">{{ old('requested_doctor_count', 1) }}</span>
                    <button type="button" class="doc-btn" onclick="changeDocCount(1)">+</button>
                    <input type="hidden" name="requested_doctor_count" id="requestedDoctorCount" value="{{ old('requested_doctor_count', 1) }}">
                </div>

                {{-- Pricing Summary --}}
                <div class="pricing-summary" id="pricingSummary">
                    <div class="price-row">
                        <span class="label">Base Plan</span>
                        <span class="value" id="priceBase">₹0</span>
                    </div>
                    <div class="price-row" id="priceDocRow" style="display: none;">
                        <span class="label" id="priceDocLabel">Additional Doctors (0 × ₹2,999)</span>
                        <span class="value" id="priceDoc">₹0</span>
                    </div>
                    <div class="price-row" id="priceTrialRow" style="display: none;">
                        <span class="label">FREE Trial (+1 month)</span>
                        <span class="value" style="color: var(--success);">₹0</span>
                    </div>
                    <div class="price-row total">
                        <span class="label">Total Amount</span>
                        <span class="value" id="priceTotal">₹0</span>
                    </div>
                    <div class="price-row" id="priceDurationRow">
                        <span class="label">Effective Duration</span>
                        <span class="value" id="priceDuration" style="color: var(--success);">—</span>
                    </div>
                </div>
            </div>

            <div class="submit-bar">
                <button type="submit" class="btn-submit">Submit License Request →</button>
            </div>
        </form>

        <div class="footer-links">
            <a href="{{ route('license.pricing') }}">View Pricing Plans</a>
            <a href="{{ route('license.renewal-request') }}">Renew Existing License</a>
            <a href="{{ route('login') }}">Clinic Login</a>
        </div>
    </div>

    <script>
        // Plan selection
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
                updatePricing();
            });
        });

        // Doctor count
        function changeDocCount(delta) {
            const input = document.getElementById('requestedDoctorCount');
            const display = document.getElementById('docCountDisplay');
            let val = parseInt(input.value) + delta;
            if (val < 1) val = 1;
            if (val > 50) val = 50;
            input.value = val;
            display.textContent = val;
            updatePricing();
        }

        // Trial toggle
        document.getElementById('trialToggle').addEventListener('change', updatePricing);

        // Pricing calculator
        function updatePricing() {
            const selectedPlan = document.querySelector('.plan-card.selected');
            if (!selectedPlan) return;

            const basePrice = parseFloat(selectedPlan.dataset.price);
            const months = parseInt(selectedPlan.dataset.months);
            const includedSeats = parseInt(selectedPlan.dataset.includedSeats) || 1;
            const seatPrice = parseFloat(selectedPlan.dataset.seatPrice) || 2999;
            const totalDocs = parseInt(document.getElementById('requestedDoctorCount').value);
            const wantsTrial = document.getElementById('trialToggle').checked;

            const additionalDocs = Math.max(0, totalDocs - includedSeats);
            const additionalAmount = additionalDocs * seatPrice;
            const totalAmount = basePrice + additionalAmount;
            const effectiveMonths = months + (wantsTrial ? 1 : 0);

            document.getElementById('priceBase').textContent = '₹' + basePrice.toLocaleString('en-IN');

            const docRow = document.getElementById('priceDocRow');
            if (additionalDocs > 0) {
                docRow.style.display = 'flex';
                document.getElementById('priceDocLabel').textContent = `Additional Doctors (${additionalDocs} × ₹${seatPrice.toLocaleString('en-IN')})`;
                document.getElementById('priceDoc').textContent = '₹' + additionalAmount.toLocaleString('en-IN');
            } else {
                docRow.style.display = 'none';
            }

            document.getElementById('priceTrialRow').style.display = wantsTrial ? 'flex' : 'none';
            document.getElementById('priceTotal').textContent = '₹' + totalAmount.toLocaleString('en-IN');
            document.getElementById('priceDuration').textContent = effectiveMonths + ' months' + (wantsTrial ? ' (incl. 1 month FREE)' : '');
        }

        // Init pricing on load
        updatePricing();
    </script>
</body>
</html>
