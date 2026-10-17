<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License History Audit — #{{ $licenseRequest->request_number }} — DxClinic Fleet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, sans-serif; }
        body { background: #0b0f19; color: #f1f5f9; min-height: 100vh; padding: 1.5rem; }
        .container { max-width: 960px; margin: 0 auto; }
        .back-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem; }
        .back-link { display: inline-flex; align-items: center; gap: 0.4rem; color: #38bdf8; text-decoration: none; font-size: 0.85rem; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }

        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid #1e293b; padding-bottom: 1.25rem; }
        .header h1 { font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
        .badge-initial { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); }
        .badge-renewal { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); }
        .badge-addon { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }

        .badge-status-fulfilled { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-status-pending { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-status-submitted { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-status-rejected { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-status-cancelled { background: rgba(100, 116, 139, 0.15); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3); }

        .card { background: #131d2e; border: 1px solid #1e293b; border-radius: 1rem; padding: 1.5rem; margin-bottom: 1.25rem; }
        .card-title { font-size: 0.95rem; font-weight: 700; color: #38bdf8; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.04em; padding-bottom: 0.5rem; border-bottom: 1px solid #1e293b; display: flex; align-items: center; justify-content: space-between; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }
        .info-row { display: flex; flex-direction: column; gap: 0.2rem; }
        .info-row .lbl { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
        .info-row .val { font-size: 0.95rem; color: #f1f5f9; font-weight: 600; word-break: break-word; }
        .info-row .val.mono { font-family: 'JetBrains Mono', monospace; }
        .info-row .val.highlight { color: #38bdf8; }
        .info-row .val.success { color: #34d399; }
        .info-row .val.warning { color: #fbbf24; }
        .info-row .val.danger { color: #f87171; }
        .full-row { grid-column: 1 / -1; }

        /* Pricing Table */
        .pricing-box { background: #080d16; border: 1px solid #1f293d; border-radius: 0.75rem; padding: 1.25rem; margin-top: 0.75rem; }
        .price-row { display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.9rem; }
        .price-row .label { color: #94a3b8; }
        .price-row .value { font-family: 'JetBrains Mono', monospace; font-weight: 600; }
        .price-row.total { border-top: 1px solid #1e293b; margin-top: 0.5rem; padding-top: 0.75rem; font-weight: 800; font-size: 1.05rem; }
        .price-row.total .value { color: #34d399; }

        /* Security Notice */
        .security-notice { background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 0.5rem; padding: 0.85rem 1rem; font-size: 0.82rem; color: #7dd3fc; margin-bottom: 1rem; line-height: 1.4; }

        /* Event Timeline */
        .event-list { list-style: none; }
        .event-item { padding: 0.85rem 0; border-bottom: 1px solid #1c273a; display: flex; gap: 0.85rem; align-items: flex-start; }
        .event-item:last-child { border-bottom: none; }
        .event-dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; }
        .event-dot.submitted { background: #3b82f6; }
        .event-dot.under_review { background: #f59e0b; }
        .event-dot.payment_confirmed { background: #10b981; }
        .event-dot.fulfilled { background: #10b981; }
        .event-dot.rejected { background: #ef4444; }
        .event-dot.cancelled { background: #94a3b8; }
        .event-text { font-size: 0.85rem; color: #94a3b8; line-height: 1.4; margin-top: 0.2rem; }
        .event-type { font-weight: 700; color: #f1f5f9; }
        .event-time { font-size: 0.75rem; color: #64748b; font-family: 'JetBrains Mono', monospace; }
        .event-meta { background: #080d16; border: 1px solid #1f293d; border-radius: 0.4rem; padding: 0.4rem 0.6rem; font-family: 'JetBrains Mono', monospace; font-size: 0.72rem; color: #94a3b8; margin-top: 0.4rem; }

        .btn-link { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); padding: 0.4rem 0.85rem; border-radius: 0.4rem; font-size: 0.8rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; }
        .btn-link:hover { background: rgba(56, 189, 248, 0.25); }

        @media (max-width: 600px) { .info-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <!-- Back Navigation -->
        <div class="back-nav">
            <a href="{{ route('superadmin.license-history') }}" class="back-link" id="linkBackToHistory">
                ← Back to License Request & Issuance History
            </a>
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                @if ($licenseRequest->canBeFulfilled())
                    <a href="{{ route('superadmin.license-requests.show', $licenseRequest) }}" class="btn-link" id="linkManageInQueue" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; border-color: rgba(245, 158, 11, 0.3);">
                        ⚡ Manage & Fulfill in Request Queue
                    </a>
                @endif
                @if ($licenseRequest->fulfilled_clinic_id)
                    <a href="{{ route('superadmin.dashboard', ['q' => $licenseRequest->fulfilledClinic?->clinic_code ?? $licenseRequest->clinic_code]) }}" class="btn-link" id="linkViewClinic">
                        🏥 View Clinic in Dashboard
                    </a>
                @endif
                @if ($invoice)
                    <a href="{{ route('superadmin.invoices.show', $invoice) }}" class="btn-link" id="linkViewInvoice">
                        📄 View Invoice #{{ $invoice->invoice_number }}
                    </a>
                @endif
            </div>
        </div>

        @php
            $badgeType = match (strtoupper($licenseRequest->request_type)) {
                'INITIAL' => 'badge-initial',
                'RENEWAL' => 'badge-renewal',
                'DOCTOR_SEAT_ADDON' => 'badge-addon',
                default => 'badge-initial',
            };
            $typeLabel = match (strtoupper($licenseRequest->request_type)) {
                'INITIAL' => 'Initial License',
                'RENEWAL' => 'Renewal',
                'DOCTOR_SEAT_ADDON' => 'Doctor Seat Add-on',
                default => $licenseRequest->request_type,
            };
            $statusClass = match (strtoupper($licenseRequest->status)) {
                'FULFILLED' => 'badge-status-fulfilled',
                'PENDING' => 'badge-status-pending',
                'SUBMITTED' => 'badge-status-submitted',
                'UNDER_REVIEW' => 'badge-status-pending',
                'PAYMENT_PENDING' => 'badge-status-pending',
                'REJECTED' => 'badge-status-rejected',
                'CANCELLED' => 'badge-status-cancelled',
                default => 'badge-status-cancelled',
            };
            $snapshot = $licenseRequest->snapshot_data ?? [];
        @endphp

        <!-- Header -->
        <div class="header">
            <div>
                <h1>
                    <span class="val-mono" style="color: #ffffff;">#{{ $licenseRequest->request_number }}</span>
                    <span class="badge {{ $badgeType }}">{{ $typeLabel }}</span>
                    <span class="badge {{ $statusClass }}">{{ $licenseRequest->status }}</span>
                </h1>
                <div style="font-size: 0.85rem; color: #94a3b8; margin-top: 0.35rem;">
                    Historical Audit Record • Submitted on {{ $licenseRequest->created_at->format('d-m-Y \a\t H:i:s') }}
                </div>
            </div>
            <div class="val-mono" style="font-size: 1.4rem; font-weight: 800; color: #34d399;">
                ₹{{ number_format($licenseRequest->net_amount, 2) }}
            </div>
        </div>

        <!-- 1. Request Lifecycle Information -->
        <div class="card" id="cardRequestLifecycle">
            <div class="card-title">1. Request Lifecycle Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="lbl">Request Number</span>
                    <span class="val mono highlight" id="audit_request_number">{{ $licenseRequest->request_number }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Request Type</span>
                    <span class="val highlight" id="audit_request_type">{{ $typeLabel }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Lifecycle Status</span>
                    <span class="val {{ $licenseRequest->isFulfilled() ? 'success' : ($licenseRequest->isRejected() ? 'danger' : 'warning') }}" id="audit_status">
                        {{ $licenseRequest->status }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="lbl">Submitted Date & Time</span>
                    <span class="val mono" id="audit_submitted_at">{{ $licenseRequest->created_at->format('d-m-Y H:i:s') }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Requestor Name</span>
                    <span class="val" id="audit_requestor_name">{{ $licenseRequest->owner_name }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Requestor Role</span>
                    <span class="val" id="audit_requestor_role">{{ $licenseRequest->isDoctorSeatAddon() ? 'Clinic Admin' : 'Clinic Owner' }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Reviewed By & Date</span>
                    <span class="val" id="audit_reviewed_by">
                        {{ $licenseRequest->reviewedBy?->username ?? '—' }}
                        @if ($licenseRequest->reviewed_at)
                            <span class="mono" style="color: #94a3b8; font-size: 0.85rem;">({{ $licenseRequest->reviewed_at->format('d-m-Y H:i') }})</span>
                        @endif
                    </span>
                </div>
                <div class="info-row">
                    <span class="lbl">Fulfilled / Completed Date</span>
                    <span class="val mono {{ $licenseRequest->isFulfilled() ? 'success' : '' }}" id="audit_fulfilled_at">
                        {{ $licenseRequest->payment_confirmed_at?->format('d-m-Y H:i:s') ?? ($licenseRequest->isFulfilled() ? $licenseRequest->updated_at->format('d-m-Y H:i:s') : 'Not Fulfilled') }}
                    </span>
                </div>

                @if ($licenseRequest->isRejected())
                    <div class="info-row full-row">
                        <span class="lbl">Rejection Reason</span>
                        <span class="val danger" id="audit_rejection_reason">{{ $licenseRequest->rejection_reason ?? 'Rejected by Superadmin' }}</span>
                        @if ($licenseRequest->rejected_at)
                            <span class="mono" style="font-size: 0.75rem; color: #f87171;">Rejected at: {{ $licenseRequest->rejected_at->format('d-m-Y H:i:s') }}</span>
                        @endif
                    </div>
                @endif

                @if ($licenseRequest->isCancelled())
                    <div class="info-row full-row">
                        <span class="lbl">Cancellation Reason</span>
                        <span class="val danger" id="audit_cancellation_reason">{{ $licenseRequest->cancellation_reason ?? 'Cancelled by Requestor' }}</span>
                        @if ($licenseRequest->cancelled_at)
                            <span class="mono" style="font-size: 0.75rem; color: #94a3b8;">Cancelled at: {{ $licenseRequest->cancelled_at->format('d-m-Y H:i:s') }}</span>
                        @endif
                    </div>
                @endif

                <div class="info-row full-row">
                    <span class="lbl">Origin Metadata (Audit)</span>
                    <span class="val mono" style="font-size: 0.8rem; color: #94a3b8;">
                        IP: {{ $licenseRequest->ip_address ?? '127.0.0.1' }} | Agent: {{ Str::limit($licenseRequest->user_agent ?? 'Browser', 60) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- 2. Submitted Clinic Snapshot (Preserved Immutable) -->
        <div class="card" id="cardClinicSnapshot">
            <div class="card-title">2. Submitted Clinic Snapshot (Immutable)</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="lbl">Clinic ID / Code</span>
                    <span class="val mono highlight" id="audit_clinic_code">
                        {{ $snapshot['clinic_code'] ?? $licenseRequest->clinic_code ?? $licenseRequest->fulfilledClinic?->clinic_code ?? 'Pending' }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="lbl">Clinic Name</span>
                    <span class="val" id="audit_clinic_name">{{ $snapshot['clinic_name'] ?? $licenseRequest->clinic_name }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Public Booking Slug</span>
                    <span class="val mono" id="audit_public_slug" style="color: #60a5fa;">
                        {{ $snapshot['public_slug'] ?? $licenseRequest->fulfilledClinic?->public_slug ?? $licenseRequest->clinic?->public_slug ?? '—' }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="lbl">Clinic Owner</span>
                    <span class="val" id="audit_owner_name">{{ $snapshot['owner_name'] ?? $licenseRequest->owner_name }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Contact Mobile</span>
                    <span class="val mono" id="audit_mobile">{{ $snapshot['mobile'] ?? $licenseRequest->mobile }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Registered Email</span>
                    <span class="val" id="audit_email">{{ $snapshot['email'] ?? $licenseRequest->email }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">City</span>
                    <span class="val" id="audit_city">{{ $snapshot['city'] ?? $licenseRequest->city ?? '—' }}</span>
                </div>
                <div class="info-row">
                    <span class="lbl">Pincode</span>
                    <span class="val mono" id="audit_pincode">{{ $snapshot['pincode'] ?? $licenseRequest->pincode ?? '—' }}</span>
                </div>
                <div class="info-row full-row">
                    <span class="lbl">Physical Address</span>
                    <span class="val" id="audit_address">{{ $snapshot['address'] ?? $licenseRequest->address ?? '—' }}</span>
                </div>
            </div>
        </div>

        <!-- 3. Plan & Pricing Snapshot -->
        <div class="card" id="cardPlanPricingSnapshot">
            <div class="card-title">3. Plan & Pricing Snapshot (Immutable)</div>
            <div class="info-grid">
                @if ($licenseRequest->isDoctorSeatAddon())
                    <div class="info-row">
                        <span class="lbl">Add-on Type</span>
                        <span class="val highlight" id="audit_addon_type">Doctor Seat Expansion (+{{ $snapshot['seats_added'] ?? ($licenseRequest->additional_doctor_count ?: $licenseRequest->requested_doctor_count) }} Seats)</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Current / Previous Capacity</span>
                        <span class="val mono" id="audit_addon_prev_capacity">{{ $snapshot['previous_capacity'] ?? ($licenseRequest->clinic ? $licenseRequest->clinic->totalDoctorCapacity() : ($licenseRequest->included_doctor_count ?? 1)) }} Seats</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Additional Doctor Seats Requested</span>
                        <span class="val mono highlight" id="audit_addon_requested_seats">+{{ $snapshot['seats_added'] ?? ($licenseRequest->additional_doctor_count ?: $licenseRequest->requested_doctor_count) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Resulting Total Capacity</span>
                        <span class="val mono success" id="audit_addon_resulting_capacity">{{ $snapshot['total_capacity'] ?? (($licenseRequest->clinic ? $licenseRequest->clinic->totalDoctorCapacity() : 0) + ($licenseRequest->additional_doctor_count ?: $licenseRequest->requested_doctor_count)) }} Seats</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Annual Rate Per Doctor Seat</span>
                        <span class="val mono" id="audit_addon_seat_rate">₹{{ number_format($licenseRequest->doctor_rate_snapshot, 2) }} / year</span>
                    </div>
                @else
                    <div class="info-row">
                        <span class="lbl">Subscription Plan</span>
                        <span class="val highlight" id="audit_plan_name">{{ $snapshot['plan_name'] ?? $licenseRequest->plan_name_snapshot ?? $licenseRequest->plan?->name ?? 'Standard' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Plan Duration</span>
                        <span class="val" id="audit_plan_duration">{{ $snapshot['plan_duration_months'] ?? $licenseRequest->plan_duration_months }} Months</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Total Doctors Requested</span>
                        <span class="val mono" id="audit_total_doctors">{{ $licenseRequest->requested_doctor_count }}</span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Included / Additional Seats</span>
                        <span class="val" id="audit_seats_breakdown">
                            {{ $snapshot['included_doctor_count'] ?? $licenseRequest->included_doctor_count }} included + {{ $snapshot['additional_doctor_count'] ?? $licenseRequest->additional_doctor_count }} additional
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Free Trial Enabled</span>
                        <span class="val {{ $licenseRequest->wants_trial ? 'success' : '' }}" id="audit_free_trial">
                            {{ $licenseRequest->wants_trial ? 'YES (' . $licenseRequest->trial_months . ' month FREE trial)' : 'NO' }}
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Effective Commercial Duration</span>
                        <span class="val success mono" id="audit_effective_duration">{{ $licenseRequest->effective_duration }} Months</span>
                    </div>
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
                        <span class="label">Base Plan Fee ({{ $snapshot['plan_name'] ?? $licenseRequest->plan_name_snapshot }})</span>
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
                            <span class="label">Free Trial Period (+{{ $licenseRequest->trial_months }} month)</span>
                            <span class="value" style="color: #34d399;">₹0.00 (FREE)</span>
                        </div>
                    @endif
                @endif
                @if ($licenseRequest->discount_amount > 0)
                    <div class="price-row">
                        <span class="label">Commercial Discount</span>
                        <span class="value" style="color: #f87171;">-₹{{ number_format($licenseRequest->discount_amount, 2) }}</span>
                    </div>
                @endif
                <div class="price-row total">
                    <span class="label">Net Payable Amount</span>
                    <span class="value" id="audit_net_amount">₹{{ number_format($licenseRequest->net_amount, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- 4. Payment Snapshot -->
        <div class="card" id="cardPaymentSnapshot">
            <div class="card-title">4. Payment Snapshot</div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="lbl">Payment Status</span>
                    <span class="val {{ $licenseRequest->isFulfilled() ? 'success' : 'warning' }}" id="audit_payment_status">
                        {{ $licenseRequest->isFulfilled() ? 'PAID / CONFIRMED' : 'NOT PAID' }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="lbl">Payment Method</span>
                    <span class="val mono highlight" id="audit_payment_method">
                        {{ $licenseRequest->payment_method ?? 'NOT PAID' }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="lbl">Payment Date</span>
                    <span class="val mono" id="audit_payment_date">
                        {{ $licenseRequest->payment_date ? $licenseRequest->payment_date->format('d-m-Y') : '—' }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="lbl">Payment Reference</span>
                    <span class="val mono" id="audit_payment_reference">
                        {{ $licenseRequest->payment_reference ?? '—' }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="lbl">Gross Amount</span>
                    <span class="val mono" id="audit_gross_amount">
                        ₹{{ number_format($licenseRequest->base_amount + $licenseRequest->additional_doctor_amount, 2) }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="lbl">Net Paid Amount</span>
                    <span class="val mono success" id="audit_net_paid">
                        ₹{{ number_format($licenseRequest->net_amount, 2) }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="lbl">Confirmed By Superadmin</span>
                    <span class="val" id="audit_payment_confirmed_by">
                        {{ $licenseRequest->paymentConfirmedBy?->username ?? ($licenseRequest->isFulfilled() ? 'Superadmin' : '—') }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="lbl">Confirmed At</span>
                    <span class="val mono" id="audit_payment_confirmed_at">
                        {{ $licenseRequest->payment_confirmed_at ? $licenseRequest->payment_confirmed_at->format('d-m-Y H:i:s') : '—' }}
                    </span>
                </div>
                @if ($licenseRequest->admin_notes)
                    <div class="info-row full-row">
                        <span class="lbl">Superadmin Processing Notes</span>
                        <span class="val" id="audit_admin_notes">{{ $licenseRequest->admin_notes }}</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- 5. Initial License Issuance Data (Fulfilled) -->
        @if ($licenseRequest->isFulfilled())
            <div class="card" id="cardLicenseIssuance">
                <div class="card-title">5. License Issuance Snapshot</div>
                <div class="info-grid">
                    <div class="info-row">
                        <span class="lbl">Clinic ID / Code</span>
                        <span class="val mono highlight" id="audit_issued_clinic_code">
                            {{ $licenseRequest->fulfilledClinic?->clinic_code ?? $licenseRequest->clinic_code ?? '—' }}
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Clinic Name</span>
                        <span class="val" id="audit_issued_clinic_name">
                            {{ $snapshot['clinic_name'] ?? $licenseRequest->clinic_name }}
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Public Booking Slug</span>
                        <span class="val mono" id="audit_issued_slug">
                            {{ $licenseRequest->fulfilledClinic?->public_slug ?? '—' }}
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">License Status</span>
                        <span class="val success" id="audit_license_status">
                            {{ $licenseRequest->fulfilledLicense?->status ?? 'ACTIVE' }}
                        </span>
                    </div>
                    <div class="info-row full-row">
                        <span class="lbl">Permanent License Key</span>
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.2rem;">
                            <span class="val mono highlight" id="audit_license_key" style="font-size: 1.05rem;">
                                {{ $snapshot['license_key'] ?? $licenseRequest->fulfilledLicense?->decryptKey() ?? $licenseRequest->fulfilledLicense?->getMaskedKeyAttribute() ?? 'NXCL-••••-••••-••••-••••' }}
                            </span>
                        </div>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Subscription Validity Period</span>
                        <span class="val mono" id="audit_validity_period">
                            {{ $licenseRequest->fulfilledLicense?->start_date ? $licenseRequest->fulfilledLicense->start_date->format('d-m-Y') : '—' }} to {{ $licenseRequest->fulfilledLicense?->end_date ? $licenseRequest->fulfilledLicense->end_date->format('d-m-Y') : '—' }}
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="lbl">Effective Duration</span>
                        <span class="val mono success" id="audit_issued_duration">
                            {{ $licenseRequest->effective_duration }} Months
                        </span>
                    </div>
                    @if ($licenseRequest->fulfilledClinic)
                        <div class="info-row full-row">
                            <span class="lbl">Direct Patient Booking Link</span>
                            <span class="val mono" id="audit_booking_url">
                                <a href="{{ url('/book/' . $licenseRequest->fulfilledClinic->public_slug) }}" target="_blank" class="link">
                                    {{ url('/book/' . $licenseRequest->fulfilledClinic->public_slug) }}
                                </a>
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- 6. Initial User & Credential Issuance Audit -->
            <div class="card" id="cardAccountIssuance">
                <div class="card-title">6. Initial User & Credential Issuance Audit</div>
                
                <div class="security-notice">
                    🔒 <strong>Strict Credential Security Policy:</strong> Plaintext temporary passwords and one-time activation secrets are never permanently stored or displayed in historical audit logs. Only auditable non-secret account identifiers and issuance verification flags are maintained.
                </div>

                <div class="info-grid">
                    <!-- Administrator -->
                    <div class="info-row">
                        <span class="lbl">Administrator Account</span>
                        <span class="val" id="audit_admin_account_title">
                            {{ $accountMetadata['admin']['name'] ?? $licenseRequest->owner_name }}
                        </span>
                        <div style="font-size: 0.8rem; color: #94a3b8;" class="val-mono">
                            Login ID: <strong style="color: #38bdf8;" id="audit_admin_login_id">{{ $accountMetadata['admin']['login_id'] ?? 'ADM0001' }}</strong> | {{ $accountMetadata['admin']['email'] ?? $licenseRequest->email }}
                        </div>
                        <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;" class="val-mono">
                            Password: <span style="color: #64748b;">[Protected — Displayed Once in Onboarding Package]</span>
                        </div>
                        <div style="font-size: 0.75rem; color: #34d399; margin-top: 0.2rem;">
                            ✓ Credential Package Issued & Secured
                        </div>
                    </div>

                    <!-- Operator -->
                    <div class="info-row">
                        <span class="lbl">Operator Account</span>
                        <span class="val" id="audit_operator_account_title">
                            {{ $accountMetadata['operator']['name'] ?? 'Operator' }}
                        </span>
                        <div style="font-size: 0.8rem; color: #94a3b8;" class="val-mono">
                            Login ID: <strong style="color: #38bdf8;" id="audit_operator_login_id">{{ $accountMetadata['operator']['login_id'] ?? 'OPR0001' }}</strong>
                        </div>
                        <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;" class="val-mono">
                            Password: <span style="color: #64748b;">[Protected — Displayed Once in Onboarding Package]</span>
                        </div>
                        <div style="font-size: 0.75rem; color: #34d399; margin-top: 0.2rem;">
                            ✓ Credential Package Issued & Secured
                        </div>
                    </div>

                    <!-- Doctor -->
                    <div class="info-row">
                        <span class="lbl">Doctor Account</span>
                        <span class="val" id="audit_doctor_account_title">
                            {{ $accountMetadata['doctor']['doctor_name'] ?? $accountMetadata['doctor']['name'] ?? 'Doctor' }}
                        </span>
                        <div style="font-size: 0.8rem; color: #94a3b8;" class="val-mono">
                            Login ID: <strong style="color: #38bdf8;" id="audit_doctor_login_id">{{ $accountMetadata['doctor']['login_id'] ?? 'DOC0001' }}</strong>
                        </div>
                        <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;" class="val-mono">
                            Password: <span style="color: #64748b;">[Protected — Displayed Once in Onboarding Package]</span>
                        </div>
                        <div style="font-size: 0.75rem; color: #34d399; margin-top: 0.2rem;">
                            ✓ Credential Package Issued & Secured
                        </div>
                    </div>

                    <!-- One-Time Activation Code Status -->
                    <div class="info-row">
                        <span class="lbl">One-Time Activation Code</span>
                        <span class="val mono highlight" id="audit_activation_code_status">
                            ISSUED (Masked: ********)
                        </span>
                        <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;">
                            Single-view initial package closed
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- 7. Chronological Audit Event Timeline -->
        <div class="card" id="cardEventTimeline">
            <div class="card-title">7. Event Timeline &amp; Audit Trail</div>
            @if ($licenseRequest->events->count() > 0)
                <ul class="event-list" id="audit_event_list">
                    @foreach ($licenseRequest->events as $event)
                        @php
                            $dotClass = match (strtolower($event->event_type)) {
                                'submitted' => 'submitted',
                                'under_review' => 'under_review',
                                'payment_confirmed' => 'payment_confirmed',
                                'fulfilled' => 'fulfilled',
                                'rejected' => 'rejected',
                                'cancelled' => 'cancelled',
                                default => 'submitted',
                            };
                        @endphp
                        <li class="event-item">
                            <span class="event-dot {{ $dotClass }}"></span>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap;">
                                    <span class="event-type">{{ $event->event_type }}</span>
                                    <span class="event-time">{{ $event->created_at->format('d-m-Y H:i:s') }}</span>
                                </div>
                                @if ($event->notes)
                                    <div class="event-text">{{ $event->notes }}</div>
                                @endif
                                @if (!empty($event->metadata))
                                    <div class="event-meta">
                                        {{ json_encode($event->metadata) }}
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p style="color: #64748b; font-size: 0.85rem;" id="empty_events_message">No historical events recorded for this request.</p>
            @endif
        </div>
    </div>
</body>
</html>
