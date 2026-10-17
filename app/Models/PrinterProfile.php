<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrinterProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'paper_width_mm',
        'paper_height_mm',
        'offset_x_mm',
        'offset_y_mm',
        'scale_x',
        'scale_y',
        'is_default',
    ];

    protected $casts = [
        'paper_width_mm' => 'float',
        'paper_height_mm' => 'float',
        'offset_x_mm' => 'float',
        'offset_y_mm' => 'float',
        'scale_x' => 'float',
        'scale_y' => 'float',
        'is_default' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
