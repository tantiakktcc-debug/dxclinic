<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicDoctorSeatAddon extends Model
{
    use HasFactory;

    protected $table = 'clinic_doctor_seat_addons';

    protected $fillable = [
        'clinic_id',
        'license_id',
        'doctor_id',
        'seats_count',
        'purchase_date',
        'valid_from',
        'valid_until',
        'price',
        'currency',
        'payment_method',
        'payment_reference',
        'payment_date',
        'status',
        'created_by_developer_user_id',
        'notes',
        'idempotency_key',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'payment_date' => 'date',
        'price' => 'decimal:2',
        'seats_count' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class, 'created_by_developer_user_id');
    }

    public function platformInvoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlatformSalesInvoice::class, 'source_id')
            ->where('source_type', 'DOCTOR_ADDON');
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();
        return $query->where('status', 'ACTIVE')
            ->whereDate('valid_from', '<=', $today)
            ->whereDate('valid_until', '>=', $today);
    }

    public function isActive(): bool
    {
        if ($this->status !== 'ACTIVE') {
            return false;
        }

        $today = Carbon::today();
        return $this->valid_from <= $today && $this->valid_until >= $today;
    }

    public function isExpired(): bool
    {
        return $this->status === 'EXPIRED' || ($this->valid_until && $this->valid_until < Carbon::today());
    }

    public function remainingDays(): int
    {
        if (!$this->valid_until) {
            return 0;
        }
        $today = Carbon::today('Asia/Kolkata');
        $validUntil = Carbon::parse($this->valid_until, 'Asia/Kolkata')->startOfDay();
        return (int) $today->diffInDays($validUntil, false);
    }
}
