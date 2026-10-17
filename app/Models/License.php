<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class License extends Model
{
    use HasFactory;

    protected $table = 'licenses';

    protected $fillable = [
        'clinic_id',
        'previous_license_id',
        'renewal_number',
        'is_renewal',
        'plan_id',
        'license_key_hash',
        'license_key_encrypted',
        'license_key_prefix',
        'activation_code_hash',
        'start_date',
        'end_date',
        'amount',
        'discount_amount',
        'net_amount',
        'payment_method',
        'payment_reference',
        'payment_date',
        'included_doctor_seats',
        'additional_doctor_seats',
        'additional_doctor_fee_per_year',
        'additional_doctor_amount',
        'status',
        'issued_by',
        'issued_at',
        'activated_at',
        'suspended_at',
        'cancelled_at',
        'revoked_at',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'included_doctor_seats' => 'integer',
        'additional_doctor_seats' => 'integer',
        'additional_doctor_fee_per_year' => 'decimal:2',
        'additional_doctor_amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class, 'issued_by');
    }

    public function activations(): HasMany
    {
        return $this->hasMany(LicenseActivation::class);
    }

    public function previousLicense(): BelongsTo
    {
        return $this->belongsTo(License::class, 'previous_license_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(LicenseRenewal::class, 'license_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LicenseEvent::class)->orderBy('created_at', 'desc');
    }

    public function doctorSeatAddons(): HasMany
    {
        return $this->hasMany(ClinicDoctorSeatAddon::class, 'license_id')->orderBy('valid_until', 'desc');
    }

    public function platformInvoices(): HasMany
    {
        return $this->hasMany(PlatformSalesInvoice::class, 'license_id')->orderBy('invoice_date', 'desc');
    }

    public function totalDoctorCapacity(): int
    {
        return (int) (($this->included_doctor_seats ?? 1) + ($this->additional_doctor_seats ?? 0));
    }

    public function isValid(): bool
    {
        if (!in_array($this->status, ['TRIAL', 'ACTIVE'])) {
            return false;
        }

        $today = Carbon::today();
        return $this->end_date >= $today && ($this->start_date <= $today || $this->is_renewal || $this->previous_license_id !== null);
    }

    public function remainingDays(): int
    {
        if (!$this->end_date) {
            return 0;
        }
        $today = Carbon::today('Asia/Kolkata');
        $endDate = Carbon::parse($this->end_date, 'Asia/Kolkata')->startOfDay();
        return (int) $today->diffInDays($endDate, false);
    }

    public function remainingDaysFormatted(): string
    {
        $days = $this->remainingDays();
        if ($days < 0) {
            return 'EXPIRED';
        }
        if ($days === 0) {
            return 'Expires today';
        }
        if ($days === 1) {
            return '1 day remaining';
        }
        return "{$days} days remaining";
    }

    public function isExpired(): bool
    {
        return $this->status === 'EXPIRED' || ($this->end_date && $this->end_date < Carbon::today());
    }

    public function isSuspended(): bool
    {
        return $this->status === 'SUSPENDED';
    }

    public function isRevoked(): bool
    {
        return $this->status === 'REVOKED';
    }

    public function getMaskedKeyAttribute(): string
    {
        $prefix = $this->license_key_prefix ?: 'NXCL';
        return "{$prefix}-••••-••••-••••-••••";
    }

    public function decryptKey(): ?string
    {
        if (empty($this->license_key_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->license_key_encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
