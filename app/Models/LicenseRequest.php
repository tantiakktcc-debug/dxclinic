<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenseRequest extends Model
{
    protected $table = 'license_requests';

    protected $fillable = [
        'request_number',
        'request_type',
        'status',
        // Contact / Owner
        'clinic_name',
        'owner_name',
        'mobile',
        'email',
        'address',
        'city',
        'pincode',
        // Renewal verification
        'clinic_id',
        'clinic_code',
        // Plan selection
        'plan_id',
        'wants_trial',
        'requested_doctor_count',
        // Pricing snapshot
        'plan_name_snapshot',
        'plan_duration_months',
        'trial_months',
        'included_doctor_count',
        'additional_doctor_count',
        'doctor_rate_snapshot',
        'base_amount',
        'additional_doctor_amount',
        'trial_amount',
        'discount_amount',
        'net_amount',
        // Superadmin processing
        'reviewed_by',
        'reviewed_at',
        'payment_confirmed_by',
        'payment_confirmed_at',
        'payment_method',
        'payment_reference',
        'payment_date',
        'fulfilled_license_id',
        'fulfilled_clinic_id',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'admin_notes',
        'account_issuance_metadata',
        'snapshot_data',
        // Metadata
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'wants_trial' => 'boolean',
        'requested_doctor_count' => 'integer',
        'plan_duration_months' => 'integer',
        'trial_months' => 'integer',
        'included_doctor_count' => 'integer',
        'additional_doctor_count' => 'integer',
        'doctor_rate_snapshot' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'additional_doctor_amount' => 'decimal:2',
        'trial_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'payment_confirmed_at' => 'datetime',
        'payment_date' => 'date',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'account_issuance_metadata' => 'array',
        'snapshot_data' => 'array',
    ];

    // ── Relationships ──

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class, 'reviewed_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class, 'rejected_by');
    }

    public function paymentConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class, 'payment_confirmed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function fulfilledLicense(): BelongsTo
    {
        return $this->belongsTo(License::class, 'fulfilled_license_id');
    }

    public function fulfilledClinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class, 'fulfilled_clinic_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LicenseRequestEvent::class)->orderBy('created_at', 'asc');
    }

    // ── Status Helpers ──

    public function isInitial(): bool
    {
        return strtoupper($this->request_type) === 'INITIAL';
    }

    public function isRenewal(): bool
    {
        return strtoupper($this->request_type) === 'RENEWAL';
    }

    public function isDoctorSeatAddon(): bool
    {
        return strtoupper($this->request_type) === 'DOCTOR_SEAT_ADDON';
    }

    public function isPending(): bool
    {
        return in_array(strtoupper($this->status), ['PENDING', 'SUBMITTED', 'UNDER_REVIEW', 'PAYMENT_PENDING']);
    }

    public function isSubmitted(): bool
    {
        return strtoupper($this->status) === 'SUBMITTED';
    }

    public function isUnderReview(): bool
    {
        return strtoupper($this->status) === 'UNDER_REVIEW';
    }

    public function isFulfilled(): bool
    {
        return strtoupper($this->status) === 'FULFILLED';
    }

    public function isRejected(): bool
    {
        return strtoupper($this->status) === 'REJECTED';
    }

    public function isCancelled(): bool
    {
        return strtoupper($this->status) === 'CANCELLED';
    }

    /**
     * Whether Superadmin can perform the one-click fulfill action.
     * Allowed from PENDING, SUBMITTED or UNDER_REVIEW.
     */
    public function canBeFulfilled(): bool
    {
        return in_array(strtoupper($this->status), ['PENDING', 'SUBMITTED', 'UNDER_REVIEW', 'PAYMENT_PENDING']);
    }

    /**
     * Whether Superadmin can reject this request.
     */
    public function canBeRejected(): bool
    {
        return in_array(strtoupper($this->status), ['PENDING', 'SUBMITTED', 'UNDER_REVIEW', 'PAYMENT_PENDING']);
    }

    /**
     * Whether request can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array(strtoupper($this->status), ['PENDING', 'SUBMITTED', 'UNDER_REVIEW', 'PAYMENT_PENDING']);
    }

    /**
     * Whether request is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array(strtoupper($this->status), ['FULFILLED', 'REJECTED', 'CANCELLED']);
    }

    // ── Scopes ──

    public function scopePending($query)
    {
        return $query->whereIn('status', ['PENDING', 'SUBMITTED', 'UNDER_REVIEW', 'PAYMENT_PENDING']);
    }

    public function scopeActionable($query)
    {
        return $query->whereIn('status', ['PENDING', 'SUBMITTED', 'UNDER_REVIEW', 'PAYMENT_PENDING']);
    }

    // ── Display Helpers ──

    public function getStatusBadgeClassAttribute(): string
    {
        return match (strtoupper($this->status)) {
            'PENDING' => 'badge-warning',
            'SUBMITTED' => 'badge-info',
            'UNDER_REVIEW' => 'badge-warning',
            'PAYMENT_PENDING' => 'badge-warning',
            'FULFILLED' => 'badge-success',
            'REJECTED' => 'badge-danger',
            'CANCELLED' => 'badge-secondary',
            default => 'badge-secondary',
        };
    }

    public function getEffectiveDurationAttribute(): int
    {
        return (int) ($this->plan_duration_months ?? 0) + (int) ($this->trial_months ?? 0);
    }
}
