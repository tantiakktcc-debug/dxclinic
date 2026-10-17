<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformSalesInvoice extends Model
{
    use HasFactory;

    protected $table = 'platform_sales_invoices';

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'clinic_id',
        'license_id',
        'sale_type',
        'source_type',
        'source_id',
        'subtotal',
        'discount',
        'net_amount',
        'status',
        'payment_method',
        'payment_reference',
        'payment_date',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'payment_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted()
    {
        // Enforce invoice immutability after issuance
        static::updating(function (PlatformSalesInvoice $invoice) {
            $immutableFields = [
                'invoice_number',
                'clinic_id',
                'license_id',
                'sale_type',
                'source_type',
                'source_id',
                'subtotal',
                'discount',
                'net_amount',
            ];

            foreach ($immutableFields as $field) {
                if ($invoice->isDirty($field) && $invoice->getOriginal($field) !== null) {
                    throw new \DomainException("Commercial invoice {$invoice->invoice_number} is immutable. Field '{$field}' cannot be modified once issued.");
                }
            }

            // Once cancelled, cannot un-cancel
            if ($invoice->getOriginal('status') === 'CANCELLED' && $invoice->isDirty('status')) {
                throw new \DomainException("Cancelled invoice {$invoice->invoice_number} cannot be reactivated.");
            }
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlatformInvoiceItem::class, 'invoice_id')->orderBy('id', 'asc');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(DeveloperUser::class, 'cancelled_by');
    }

    /**
     * Resolve the underlying authoritative commercial record
     */
    public function getSourceRecordAttribute()
    {
        return match ($this->source_type) {
            'INITIAL_LICENSE', License::class => License::find($this->source_id),
            'LICENSE_RENEWAL', LicenseRenewal::class => LicenseRenewal::find($this->source_id),
            'DOCTOR_ADDON', ClinicDoctorSeatAddon::class => ClinicDoctorSeatAddon::find($this->source_id),
            default => null,
        };
    }

    public function isPaid(): bool
    {
        return strtoupper($this->status) === 'PAID';
    }

    public function isCancelled(): bool
    {
        return strtoupper($this->status) === 'CANCELLED';
    }

    public function isIssued(): bool
    {
        return strtoupper($this->status) === 'ISSUED';
    }

    public function formattedSaleType(): string
    {
        return match ($this->sale_type) {
            'INITIAL_LICENSE' => 'Initial License',
            'LICENSE_RENEWAL' => 'License Renewal',
            'DOCTOR_ADDON' => 'Doctor Seat Add-on',
            default => $this->sale_type,
        };
    }

    public function maskedLicenseKey(): string
    {
        if ($this->license) {
            return $this->license->masked_key;
        }

        return 'NXCL-••••-••••-••••-••••';
    }
}
