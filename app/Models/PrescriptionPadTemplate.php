<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrescriptionPadTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'doctor_id',
        'name',
        'description',
        'paper_width_mm',
        'paper_height_mm',
        'orientation',
        'background_path',
        'background_width_px',
        'background_height_px',
        'template_type',
        'version',
        'status',
        'is_active',
        'activated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'paper_width_mm' => 'float',
        'paper_height_mm' => 'float',
        'background_width_px' => 'integer',
        'background_height_px' => 'integer',
        'version' => 'integer',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(PrescriptionPadElement::class, 'template_id')->orderBy('sort_order');
    }

    public function visibleElements(): HasMany
    {
        return $this->hasMany(PrescriptionPadElement::class, 'template_id')->where('is_visible', true)->orderBy('sort_order');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'prescription_pad_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', 'ACTIVE');
    }

    public function scopeForDoctor($query, int $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->status === 'ACTIVE';
    }

    public function isArchived(): bool
    {
        return $this->status === 'ARCHIVED';
    }

    /**
     * Get paper dimensions in points (1 mm = 72 / 25.4 pt = ~2.83465 pt).
     * Authoritative for PDF generation.
     */
    public function getPaperDimensionsPt(): array
    {
        $mmToPt = 72 / 25.4;
        $widthPt = round($this->paper_width_mm * $mmToPt, 2);
        $heightPt = round($this->paper_height_mm * $mmToPt, 2);

        if ($this->orientation === 'landscape' && $widthPt < $heightPt) {
            return [$heightPt, $widthPt];
        }

        return [$widthPt, $heightPt];
    }
}
