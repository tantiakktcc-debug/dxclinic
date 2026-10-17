<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseRenewal extends Model
{
    use HasFactory;

    protected $table = 'license_renewals';

    protected $fillable = [
        'clinic_id',
        'license_id',
        'previous_license_id',
        'renewal_number',
        'plan_id',
        'previous_end_date',
        'new_start_date',
        'new_end_date',
        'duration_months',
        'base_amount',
        'renew_existing_doctor_seats',
        'existing_doctor_seats_renewed',
        'existing_doctor_seat_amount',
        'new_doctor_seats_purchased',
        'new_doctor_seat_amount',
        'total_doctor_seat_amount',
        'discount_amount',
        'net_amount',
        'payment_received',
        'payment_method',
        'payment_date',
        'payment_reference',
        'idempotency_key',
        'renewed_by',
        'notes',
    ];

    protected $casts = [
        'previous_end_date' => 'date',
        'new_start_date' => 'date',
        'new_end_date' => 'date',
        'payment_date' => 'date',
        'base_amount' => 'decimal:2',
        'existing_doctor_seat_amount' => 'decimal:2',
        'new_doctor_seat_amount' => 'decimal:2',
        'total_doctor_seat_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'renew_existing_doctor_seats' => 'boolean',
        'payment_received' => 'boolean',
        'existing_doctor_seats_renewed' => 'integer',
        'new_doctor_seats_purchased' => 'integer',
        'duration_months' => 'integer',
        'renewal_number' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function previousLicense(): BelongsTo
    {
        return $this->belongsTo(License::class, 'previous_license_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function renewedBy(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class, 'renewed_by');
    }

    public function platformInvoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlatformSalesInvoice::class, 'source_id')
            ->where('source_type', 'LICENSE_RENEWAL');
    }
}
