<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'doctor_id',
        'appointment_id',
        'cash_session_id',
        'visit_date',
        'session_type',
        'consultation_mode',
        'visit_type',
        'revisit_parent_visit_id',
        'status',
        'arrived_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'arrived_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function vital(): HasOne
    {
        return $this->hasOne(Vital::class);
    }

    public function consultationBill(): HasOne
    {
        return $this->hasOne(ConsultationBill::class);
    }

    public function diagnosticBills(): HasMany
    {
        return $this->hasMany(DiagnosticBill::class);
    }

    public function revisitChildVisits(): HasMany
    {
        return $this->hasMany(Visit::class, 'revisit_parent_visit_id');
    }

    public function revisitParentVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'revisit_parent_visit_id');
    }
}
