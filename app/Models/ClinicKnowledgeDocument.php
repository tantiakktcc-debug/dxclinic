<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicKnowledgeDocument extends Model
{
    use HasFactory;

    protected $table = 'clinic_knowledge_documents';

    protected $fillable = [
        'clinic_id',
        'document_key',
        'title',
        'category',
        'content',
        'keywords',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'keywords' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->where('clinic_id', $clinicId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
