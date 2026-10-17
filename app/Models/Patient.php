<?php

namespace App\Models;

use App\Services\PatientAddressFormatter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'legacy_alias',
        'clinic_id',
        'name',
        'age',
        'sex',
        'mobile',
        'address',
        'city',
        'profile_status',
    ];

    protected $casts = [
        'age' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function consultationBills(): HasMany
    {
        return $this->hasMany(ConsultationBill::class);
    }

    public function diagnosticBills(): HasMany
    {
        return $this->hasMany(DiagnosticBill::class);
    }

    public function diagnosticOrders(): HasMany
    {
        return $this->hasMany(DiagnosticOrder::class);
    }

    public function diagnosticReports(): HasMany
    {
        return $this->hasMany(DiagnosticReport::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = \App\Services\PersonNameNormalizer::isValid($value)
            ? \App\Services\PersonNameNormalizer::normalize($value)
            : trim((string) $value);
    }

    public function setMobileAttribute($value): void
    {
        $this->attributes['mobile'] = \App\Services\PhoneNumberNormalizer::isValid($value)
            ? \App\Services\PhoneNumberNormalizer::normalize($value)
            : trim((string) $value);
    }

    /**
     * Get the standardized printed address: "Address, City" (Section 62)
     */
    public function getFormattedAddressAttribute(): string
    {
        return PatientAddressFormatter::format($this->address, $this->city);
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->mobile;
    }

    public function setPhoneAttribute(?string $val): void
    {
        $this->setMobileAttribute($val);
    }

    public function channelIdentities(): HasMany
    {
        return $this->hasMany(PatientChannelIdentity::class);
    }
}

