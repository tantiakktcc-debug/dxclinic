<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionPadElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'field_key',
        'element_type',
        'x_mm',
        'y_mm',
        'width_mm',
        'height_mm',
        'font_family',
        'font_size',
        'font_weight',
        'font_style',
        'text_align',
        'line_height',
        'color',
        'rotation',
        'sort_order',
        'is_locked',
        'is_visible',
        'config_json',
    ];

    protected $casts = [
        'x_mm' => 'float',
        'y_mm' => 'float',
        'width_mm' => 'float',
        'height_mm' => 'float',
        'font_size' => 'float',
        'line_height' => 'float',
        'rotation' => 'integer',
        'sort_order' => 'integer',
        'is_locked' => 'boolean',
        'is_visible' => 'boolean',
        'config_json' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PrescriptionPadTemplate::class, 'template_id');
    }

    public function getShowLabelAttribute(): bool
    {
        return (bool)($this->config_json['show_label'] ?? false);
    }

    public function getLabelTextAttribute(): ?string
    {
        return $this->config_json['label_text'] ?? null;
    }

    public function getEffectiveLabelAttribute(): string
    {
        if (!empty($this->config_json['label_text'])) {
            return $this->config_json['label_text'];
        }
        return \App\Services\PrescriptionDataMapper::getDefaultLabelForField($this->field_key);
    }
}
