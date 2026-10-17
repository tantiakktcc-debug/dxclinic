<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Request {{ $licenseRequest->request_number }} — Superadmin — DxClinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing:border-box;margin:0;padding:0;font-family:'Inter',-apple-system,sans-serif; }
        body { background:#0b0f19;color:#f1f5f9;min-height:100vh;padding:1.5rem; }
        .container { max-width:900px;margin:0 auto; }
        .back { display:inline-flex;align-items:center;gap:0.4rem;color:#94a3b8;text-decoration:none;font-size:0.85rem;font-weight:600;margin-bottom:1rem; }
        .back:hover { color:#3b82f6; }

        .header { display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:0.75rem; }
        .header h1 { font-size:1.35rem;font-weight:800; }
        .badge { display:inline-block;padding:0.25rem 0.7rem;border-radius:9999px;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.03em; }
        .badge-info { background:rgba(59,130,246,0.15);color:#60a5fa;border:1px solid rgba(59,130,246,0.3); }
        .badge-warning { background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3); }
        .badge-success { background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3); }
        .badge-danger { background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3); }
        .badge-secondary { background:rgba(100,116,139,0.15);color:#94a3b8;border:1px solid rgba(100,116,139,0.3); }

        .card { background:#131d2e;border:1px solid #1e293b;border-radius:1rem;padding:1.75rem;margin-bottom:1.25rem; }
        .card-title { font-size:0.95rem;font-weight:700;color:#38bdf8;margin-bottom:1rem;text-transform:uppercase;letter-spacing:0.04em;padding-bottom:0.5rem;border-bottom:1px solid #1e293b; }
        .info-grid { display:grid;grid-template-columns:1fr 1fr;gap:0.75rem; }
        .info-row { display:flex;flex-direction:column;gap:0.15rem; }
        .info-row .lbl { font-size:0.78rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:0.03em; }
        .info-row .val { font-size:0.95rem;color:#f1f5f9;font-weight:600; }
        .info-row .val.mono { font-family:'JetBrains Mono',monospace; }
        .info-row .val.highlight { color:#38bdf8; }
        .info-row .val.success { color:#34d399; }
        .info-row .val.warning { color:#fbbf24; }
        .full-row { grid-column:1/-1; }

        /* Pricing Summary */
        .pricing-box { background:#080d16;border:1px solid #1f293d;border-radius:0.75rem;padding:1.25rem;margin-top:0.5rem; }
        .price-row { display:flex;justify-content:space-between;padding:0.4rem 0;font-size:0.9rem; }
        .price-row .label { color:#94a3b8; }
        .price-row .value { font-family:'JetBrains Mono',monospace;font-weight:600; }
        .price-row.total { border-top:1px solid #1e293b;margin-top:0.5rem;padding-top:0.75rem;font-weight:800;font-size:1rem; }
        .price-row.total .value { color:#38bdf8; }

        /* Action Panel */
        .action-panel { background:linear-gradient(135deg,rgba(59,130,246,0.08),rgba(16,185,129,0.05));border:1px solid rgba(59,130,246,0.3);border-radius:1rem;padding:2rem; }
        .action-panel h2 { font-size:1.1rem;font-weight:800;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem; }
        .field { display:flex;flex-direction:column;gap:0.3rem;margin-bottom:1rem; }
        .field label { font-size:0.85rem;font-weight:600;color:#94a3b8; }
        .field label .req { color:#ef4444; }
        .field input,.field select,.field textarea { background:#111b2e;border:1px solid #1e293b;color:#f1f5f9;padding:0.6rem 0.8rem;border-radius:0.5rem;font-size:0.9rem; }
        .field input:focus,.field select:focus,.field textarea:focus { outline:none;border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,0.15); }
        .field textarea { resize:vertical;min-height:60px; }
        .field-grid { display:grid;grid-template-columns:1fr 1fr;gap:1rem; }
        .checkbox-field { display:flex;align-items:center;gap:0.5rem;margin:1rem 0; }
        .checkbox-field input { width:18px;height:18px;accent-color:#10b981; }
        .checkbox-field label { font-size:0.9rem;font-weight:600;color:#10b981; }

        .btn { padding:0.65rem 1.5rem;border-radius:0.5rem;font-weight:700;font-size:0.9rem;cursor:pointer;border:none;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem; }
        .btn-fulfill { background:linear-gradient(135deg,#059669,#10b981);color:#fff;box-shadow:0 4px 12px rgba(16,185,129,0.3); }
        .btn-fulfill:hover { transform:translateY(-1px);box-shadow:0 6px 16px rgba(16,185,129,0.4); }
        .btn-reject { background:transparent;color:#f87171;border:1px solid rgba(239,68,68,0.3); }
        .btn-reject:hover { background:rgba(239,68,68,0.1); }
        .btn-review { background:transparent;color:#fbbf24;border:1px solid rgba(245,158,11,0.3); }
        .btn-review:hover { background:rgba(245,158,11,0.1); }
        .btn-ghost { background:transparent;color:#94a3b8;border:1px solid #334155; }
        .btn-ghost:hover { background:#1e293b;color:#fff; }

        .action-bar { display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:1.5rem; }

        /* Events */
        .event-list { list-style:none; }
        .event-item { padding:0.75rem 0;border-bottom:1px solid #111b2e;display:flex;gap:0.75rem;align-items:flex-start; }
        .event-item:last-child { border-bottom:none; }
        .event-dot { width:10px;height:10px;border-radius:50%;margin-top:5px;flex-shrink:0; }
        .event-dot.submitted { background:#3b82f6; }
        .event-dot.under_review { background:#f59e0b; }
        .event-dot.payment_confirmed { background:#10b981; }
        .event-dot.fulfilled { background:#10b981; }
        .event-dot.rejected { background:#ef4444; }
        .event-text { font-size:0.85rem;color:#94a3b8;line-height:1.4; }
        .event-type { font-weight:700;color:#f1f5f9; }
        .event-time { font-size:0.75rem;color:#64748b; }

        .alert-error { background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;padding:0.75rem 1rem;border-radius:0.5rem;font-size:0.85rem;margin-bottom:1rem; }
        .alert-success { background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:#34d399;padding:0.75rem 1rem;border-radius:0.5rem;font-size:0.85rem;margin-bottom:1rem; }

        .fulfilled-info { background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.25);border-radius:0.75rem;padding:1.25rem;margin-top:0.5rem; }

        @media (max-width:600px) { .info-grid,.field-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>
    <div class="container">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
            <a href="{{ route('superadmin.license-requests') }}" class="back" style="margin-bottom:0;">← Back to License Requests</a>
            <a href="{{ route('superadmin.license-history.show', $licenseRequest) }}" class="btn btn-ghost" style="padding:0.35rem 0.75rem;font-size:0.8rem;" id="linkDetailToAudit">📜 View Full Audit History</a>
        </div>

        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $error)
                    <div>⚠️ {{ $error }}</div>
                @endforeach
            </div>
        @endif
        @if (session('success'))
            <div class="alert-success">✓ {{ session('success') }}</div>
        @endif

        <div class="header">
            <h1>
                {{ $licenseRequest->request_number }}
                @if ($licenseRequest->isInitial())
                    <span class="badge badge-info">Initial License</span>
                @else
                    <span class="badge" style="background:rgba(139,92,246,0.15);color:#a78bfa;border:1px solid rgba(139,92,246,0.3);">Renewal</span>
                @endif
            </h1>
            @php
                $badgeClass = match(strtoupper($licenseRequest->status)) {
                    'SUBMITTED' => 'badge-info',
                    'UNDER_REVIEW' => 'badge-warning',
                    'FULFILLED' => 'badge-success',
                    'REJECTED' => 'badge-danger',
                    'CANCELLED' => 'badge-secondary',
                    default => 'badge-secondary',
                };
            @endphp
            <span class="badge {{ $badgeClass }}">{{ $licenseRequest->status }}</span>
        </div>

        {{-- Request Details --}}
        <div class="card">
            <div class="card-title">Requestor & Clinic Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="lbl">Clinic Name</span>
                    <span class="val">{{ $licenseRequest->clinic_name }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Owner / Contact</span>
                    <span class="val">{{ $licenseRequest->owner_name }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Mobile</span>
                    <span class="val mono">{{ $licenseRequest->mobile }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Email</span>
                    <span class="val">{{ $licenseRequest->email }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">City</span>
                    <span class="val">{{ $licenseRequest->city ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Pincode</span>
                    <span class="val">{{ $licenseRequest->pincode ?? '—' }}</span>
                </div>
                <div class="info-row full-row">
                    <span class="lbl">Address</span>
                    <span class="val">{{ $licenseRequest->address ?? '—' }}</span>
                </div>
                @if ($licenseRequest->isRenewal())
                    <div class="info-row">
                        <span class="lbl">Clinic Code (Existing)</span>
                        <span class="val mono highlight">{{ $licenseRequest->clinic_code }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Plan & Pricing --}}
        <div class="card">
            <div class="card-title">{{ $licenseRequest->isDoctorSeatAddon() ? 'Doctor Seat Add-on Snapshot' : 'Plan & Pricing Snapshot' }}</div>
            <div class="info-grid">
                @if ($licenseRequest->isDoctorSeatAddon())
                    <div class="info-row">
                        <span class="lbl">Request Type</span>
                        <span class="val highlight">Doctor Seat Add-on (+{{ $licenseRequest->additional_doctor_count ?: $licenseRequest->requested_doctor_count }} Seats)</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Current Capacity</span>
                        <span class="val">{{ $licenseRequest->clinic ? $licenseRequest->clinic->totalDoctorCapacity() : ($licenseRequest->included_doctor_count ?? 1) }} Seats</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Requested Additional Seats</span>
                        <span class="val mono highlight">+{{ $licenseRequest->additional_doctor_count ?: $licenseRequest->requested_doctor_count }}</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Resulting Total Capacity</span>
                        <span class="val mono success">{{ ($licenseRequest->clinic ? $licenseRequest->clinic->totalDoctorCapacity() : 0) + ($licenseRequest->additional_doctor_count ?: $licenseRequest->requested_doctor_count) }} Seats</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Rate per Doctor Seat</span>
                        <span class="val mono">₹{{ number_format($licenseRequest->doctor_rate_snapshot, 2) }} / year</span>
                    </div>
                @else
                    <div class="info-row">
                        <span class="lbl">Plan</span>
                        <span class="val highlight">{{ $licenseRequest->plan_name_snapshot ?? '—' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Duration</span>
                        <span class="val">{{ $licenseRequest->plan_duration_months ?? '—' }} months{{ $licenseRequest->trial_months > 0 ? " + {$licenseRequest->trial_months} month FREE trial" : '' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Total Doctors Requested</span>
                        <span class="val mono">{{ $licenseRequest->requested_doctor_count }}</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Included / Additional</span>
                        <span class="val">{{ $licenseRequest->included_doctor_count }} included + {{ $licenseRequest->additional_doctor_count }} additional</span>
                    </div>
                    @if ($licenseRequest->wants_trial)
                        <div class="info-row">
                            <span class="lbl">Free Trial</span>
                            <span class="val success">✓ 1 Month Free Trial</span>
                        </div>
                    @endif
                @endif
            </div>

            <div class="pricing-box">
                @if ($licenseRequest->isDoctorSeatAddon())
                    <div class="price-row">
                        <span class="label">Additional Doctor Seats ({{ $licenseRequest->additional_doctor_count ?: $licenseRequest->requested_doctor_count }} × ₹{{ number_format($licenseRequest->doctor_rate_snapshot, 0) }})</span>
                        <span class="value">₹{{ number_format($licenseRequest->net_amount, 2) }}</span>
                    </div>
                @else
                    <div class="price-row">
                        <span class="label">Base Plan Amount</span>
                        <span class="value">₹{{ number_format($licenseRequest->base_amount, 2) }}</span>
                    </div>
                    @if ($licenseRequest->additional_doctor_count > 0)
                        <div class="price-row">
                            <span class="label">Additional Doctors ({{ $licenseRequest->additional_doctor_count }} × ₹{{ number_format($licenseRequest->doctor_rate_snapshot, 0) }})</span>
                            <span class="value">₹{{ number_format($licenseRequest->additional_doctor_amount, 2) }}</span>
                        </div>
                    @endif
                    @if ($licenseRequest->trial_months > 0)
                        <div class="price-row">
                            <span class="label">Free Trial (+{{ $licenseRequest->trial_months }} month)</span>
                            <span class="value" style="color:#34d399;">₹0.00</span>
                        </div>
                    @endif
                @endif
                @if ($licenseRequest->discount_amount > 0)
                    <div class="price-row">
                        <span class="label">Discount</span>
                        <span class="value" style="color:#f87171;">-₹{{ number_format($licenseRequest->discount_amount, 2) }}</span>
                    </div>
                @endif
                <div class="price-row total">
                    <span class="label">Net Amount</span>
                    <span class="value">₹{{ number_format($licenseRequest->net_amount, 2) }}</span>
                </div>
                @if (!$licenseRequest->isDoctorSeatAddon())
                    <div class="price-row">
                        <span class="label">Effective Duration</span>
                        <span class="value" style="color:#34d399;">{{ $licenseRequest->effective_duration }} months</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Fulfilled Info --}}
        @if ($licenseRequest->isFulfilled())
            <div class="card">
                <div class="card-title" style="color:#34d399;">✓ Fulfilled</div>
                <div class="fulfilled-info">
                    <div class="info-grid">
                        @if ($licenseRequest->fulfilledClinic)
                            <div class="info-row">
                                <span class="lbl">Clinic ID</span>
                                <span class="val mono highlight">{{ $licenseRequest->fulfilledClinic->clinic_code }}</span>
                            </div>
                        @endif
                        @if ($licenseRequest->fulfilledLicense)
                            <div class="info-row">
                                <span class="lbl">License Status</span>
                                <span class="val success">{{ $licenseRequest->fulfilledLicense->status }}</span>
                            </div>
                            <div class="info-row">
                                <span class="lbl">Valid Until</span>
                                <span class="val mono">{{ $licenseRequest->fulfilledLicense->end_date->format('d-m-Y') }}</span>
                            </div>
                        @endif
                        <div class="info-row">
                            <span class="lbl">Payment Method</span>
                            <span class="val">{{ $licenseRequest->payment_method ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Payment Reference</span>
                            <span class="val mono">{{ $licenseRequest->payment_reference ?? '—' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="lbl">Confirmed At</span>
                            <span class="val">{{ $licenseRequest->payment_confirmed_at?->format('d-m-Y H:i') ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Rejection Info --}}
        @if ($licenseRequest->isRejected() && $licenseRequest->rejection_reason)
            <div class="card">
                <div class="card-title" style="color:#f87171;">✗ Rejection Reason</div>
                <p style="color:#fca5a5;font-size:0.9rem;">{{ $licenseRequest->rejection_reason }}</p>
            </div>
        @endif

        {{-- Action Panel (only if request can be processed) --}}
        @if ($licenseRequest->canBeFulfilled())
            <div class="action-panel">
                <h2>
                    @if ($licenseRequest->isInitial())
                        ⚡ Confirm Payment & Generate License
                    @elseif ($licenseRequest->isDoctorSeatAddon())
                        ⚡ Confirm Payment & Activate Doctor Seat Add-on
                    @else
                        ⚡ Confirm Payment & Renew License
                    @endif
                </h2>

                <form method="POST" action="{{ route('superadmin.license-requests.fulfill', $licenseRequest) }}" id="fulfillForm">
                    @csrf
                    <div class="field-grid">
                        <div class="field">
                            <label>Payment Method <span class="req">*</span></label>
                            <select name="payment_method" required>
                                <option value="">— Select —</option>
                                <option value="CASH">Cash</option>
                                <option value="UPI">UPI</option>
                                <option value="BANK_TRANSFER">Bank Transfer / NEFT / RTGS</option>
                                <option value="CHEQUE">Cheque</option>
                                <option value="CARD">Card</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Payment Date</label>
                            <input type="date" name="payment_date" value="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="field">
                            <label>Payment Reference</label>
                            <input type="text" name="payment_reference" placeholder="e.g. UPI-REF-123456">
                        </div>
                        <div class="field">
                            <label>Discount (₹)</label>
                            <input type="number" name="discount_amount" value="0" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="field">
                        <label>Admin Notes</label>
                        <textarea name="admin_notes" placeholder="Optional internal notes..."></textarea>
                    </div>

                    <div class="checkbox-field">
                        <input type="checkbox" name="payment_confirmed" id="paymentConfirmed" value="1" required>
                        <label for="paymentConfirmed">I confirm payment of ₹{{ number_format($licenseRequest->net_amount, 2) }} has been received</label>
                    </div>

                    <div class="action-bar">
                        <button type="submit" class="btn btn-fulfill" onclick="return confirm('Confirm payment received and {{ $licenseRequest->isInitial() ? 'generate new license' : ($licenseRequest->isDoctorSeatAddon() ? 'activate doctor seats' : 'renew license') }}?')">
                            @if ($licenseRequest->isInitial())
                                ✓ Confirm Payment & Generate License
                            @elseif ($licenseRequest->isDoctorSeatAddon())
                                ✓ Confirm Payment & Activate Doctor Seats
                            @else
                                ✓ Confirm Payment & Renew License
                            @endif
                        </button>
                    </div>
                </form>

                <hr style="border:none;border-top:1px solid #1e293b;margin:1.5rem 0;">

                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                    @if ($licenseRequest->isSubmitted())
                        <form method="POST" action="{{ route('superadmin.license-requests.review', $licenseRequest) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-review">📝 Mark Under Review</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('superadmin.license-requests.reject', $licenseRequest) }}" style="display:inline;" onsubmit="return confirm('Are you sure you want to reject this request?')">
                        @csrf
                        <input type="hidden" name="rejection_reason" id="rejectionReasonHidden">
                        <button type="button" class="btn btn-reject" onclick="var r=prompt('Reason for rejection:');if(r!==null){document.getElementById('rejectionReasonHidden').value=r;this.closest('form').submit();}">
                            ✗ Reject Request
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Events Timeline --}}
        <div class="card">
            <div class="card-title">Event Timeline</div>
            @if ($licenseRequest->events->count() > 0)
                <ul class="event-list">
                    @foreach ($licenseRequest->events as $event)
                        <li class="event-item">
                            <span class="event-dot {{ strtolower($event->event_type) }}"></span>
                            <div>
                                <span class="event-type">{{ $event->event_type }}</span>
                                <span class="event-time">— {{ $event->created_at->format('d-m-Y H:i:s') }}</span>
                                @if ($event->notes)
                                    <div class="event-text">{{ $event->notes }}</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p style="color:#64748b;font-size:0.85rem;">No events recorded yet.</p>
            @endif
        </div>
    </div>
</body>
</html>
