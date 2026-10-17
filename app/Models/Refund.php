<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'refund_number',
        'original_payment_id',
        'consultation_bill_id',
        'diagnostic_bill_id',
        'patient_id',
        'refund_type', // FULL, PARTIAL, REFUND_AS_DISCOUNT
        'refund_amount',
        'refund_method', // CASH, UPI
        'bank_account_id',
        'cash_session_id',
        'reference_number',
        'reason',
        'requested_by',
        'requested_at',
        'doctor_instruction_by',
        'doctor_instruction_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'processed_by',
        'processed_at',
        'status', // REQUESTED, APPROVED, REJECTED, PROCESSED, VOIDED
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'doctor_instruction_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'original_payment_id');
    }

    public function consultationBill(): BelongsTo
    {
        return $this->belongsTo(ConsultationBill::class);
    }

    public function diagnosticBill(): BelongsTo
    {
        return $this->belongsTo(DiagnosticBill::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function doctorInstructionBy(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_instruction_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function receiptRevisions(): HasMany
    {
        return $this->hasMany(ReceiptRevision::class, 'linked_refund_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'linked_refund_id');
    }

    public function journal(): HasOne
    {
        return $this->hasOne(Journal::class, 'reference_id')
            ->where('reference_type', self::class);
    }

    public function getBillAttribute()
    {
        return $this->consultationBill ?? $this->diagnosticBill;
    }
}
