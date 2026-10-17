<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'code',
        'name',
        'duration_months',
        'price',
        'included_doctor_seats',
        'additional_doctor_seat_price',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'price' => 'decimal:2',
        'included_doctor_seats' => 'integer',
        'additional_doctor_seat_price' => 'decimal:2',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class, 'plan_id');
    }
}
