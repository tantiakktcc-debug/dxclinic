<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Clinic extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_code',
        'public_slug',
        'name',
        'address',
        'city',
        'pincode',
        'phone',
        'email',
        'logo_path',
        'schedule_config',
        'is_onboarded',
        'default_doctor_id',
        'logo_position',
        'print_branding_config',
        'booking_qr_local_url',
        'booking_qr_prod_url',
        'backup_schedule_time',
        'backup_auto_enabled',
        'max_advance_booking_days',
        'max_patients_per_mobile',
        'activation_status',
        'activated_at',
        'activated_by_user_id',
        'onboarding_status',
        'is_demo',
        'demo_status',
    ];

    protected $casts = [
        'schedule_config' => 'array',
        'print_branding_config' => 'array',
        'is_onboarded' => 'boolean',
        'is_demo' => 'boolean',
        'backup_auto_enabled' => 'boolean',
        'max_advance_booking_days' => 'integer',
        'max_patients_per_mobile' => 'integer',
        'activated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Clinic $clinic) {
            if (empty($clinic->clinic_code)) {
                $count = static::count();
                if ($count === 0) {
                    $clinic->clinic_code = 'NX0001';
                    $clinic->activation_status = 'ACTIVATED';
                    $clinic->onboarding_status = 'COMPLETED';
                    $clinic->activated_at = now();
                }
            }
            if (empty($clinic->onboarding_status)) {
                $clinic->onboarding_status = $clinic->is_onboarded ? 'COMPLETED' : 'NOT_STARTED';
            }
            if (empty($clinic->public_slug)) {
                $clinic->public_slug = \App\Services\PublicSlugService::generateUniqueSlug(
                    $clinic->name ?? 'clinic',
                    $clinic->city ?? null
                );
            }
            if (empty($clinic->booking_qr_prod_url) && !empty($clinic->public_slug)) {
                $clinic->booking_qr_prod_url = config('app.url') . '/book/' . $clinic->public_slug;
            }
            if (empty($clinic->booking_qr_local_url) && !empty($clinic->public_slug)) {
                $clinic->booking_qr_local_url = 'http://127.0.0.1:8000/book/' . $clinic->public_slug;
            }
        });

        static::created(function (Clinic $clinic) {
            if ($clinic->clinic_code === 'NX0001' && $clinic->licenses()->count() === 0) {
                License::create([
                    'clinic_id' => $clinic->id,
                    'plan_id' => 1,
                    'license_key_hash' => hash('sha256', 'NXCL-NX0001-DEFAULT-KEY'),
                    'license_key_encrypted' => \Illuminate\Support\Facades\Crypt::encryptString('NXCL-NX0001-DEFAULT-KEY'),
                    'license_key_prefix' => 'NXCL-NX00',
                    'status' => 'ACTIVE',
                    'start_date' => now()->subDay()->toDateString(),
                    'end_date' => now()->addYear()->toDateString(),
                    'net_amount' => 0.00,
                    'included_doctor_seats' => 5,
                    'additional_doctor_seats' => 0,
                    'additional_doctor_fee_per_year' => 2999.00,
                    'additional_doctor_amount' => 0.00,
                ]);
            }

            // Seed system booking types for this clinic (Revision 44)
            \App\Models\BookingType::insertOrIgnore([
                [
                    'clinic_id'          => $clinic->id,
                    'code'               => \App\Models\BookingType::CLINIC_CONSULTANCY,
                    'name'               => 'Clinic Consultancy',
                    'consultation_mode'  => 'CLINIC_PHYSICAL',
                    'is_system'          => true,
                    'is_active'          => true,
                    'is_public_bookable' => true,
                    'display_order'      => 10,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ],
                [
                    'clinic_id'          => $clinic->id,
                    'code'               => \App\Models\BookingType::ONLINE_CONSULTANCY,
                    'name'               => 'Online Consultancy',
                    'consultation_mode'  => 'ONLINE',
                    'is_system'          => true,
                    'is_active'          => true,
                    'is_public_bookable' => true,
                    'display_order'      => 20,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ],
                [
                    'clinic_id'          => $clinic->id,
                    'code'               => \App\Models\BookingType::EMERGENCY_CONSULTANCY,
                    'name'               => 'Emergency Consultancy',
                    'consultation_mode'  => 'CLINIC_PHYSICAL',
                    'is_system'          => true,
                    'is_active'          => true,
                    'is_public_bookable' => false,
                    'display_order'      => 30,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ],
            ]);
        });
    }

    public function bookingTypes(): HasMany
    {
        return $this->hasMany(BookingType::class);
    }

    public function defaultDoctor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'default_doctor_id');
    }

    public function getClinicNameAttribute(): string
    {
        return $this->name ?? '';
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function activeDoctors(): HasMany
    {
        return $this->hasMany(Doctor::class)->where('is_active', true);
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class)->latestOfMany();
    }

    public function doctorBookingPolicies(): HasMany
    {
        return $this->hasMany(DoctorBookingPolicy::class);
    }

    public function doctorLeaves(): HasMany
    {
        return $this->hasMany(DoctorLeave::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function fundTransfers(): HasMany
    {
        return $this->hasMany(FundTransfer::class);
    }

    public function prescriptionPadTemplates(): HasMany
    {
        return $this->hasMany(PrescriptionPadTemplate::class);
    }

    public function printerProfiles(): HasMany
    {
        return $this->hasMany(PrinterProfile::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class)->orderBy('id', 'desc');
    }

    public function latestLicense(): HasOne
    {
        return $this->hasOne(License::class)->latestOfMany();
    }

    public function activeLicense(): HasOne
    {
        return $this->hasOne(License::class)->ofMany([
            'id' => 'max',
        ], function ($query) {
            $query->whereIn('status', ['TRIAL', 'ACTIVE']);
        });
    }

    public function licenseRenewals(): HasMany
    {
        return $this->hasMany(LicenseRenewal::class)->orderBy('id', 'desc');
    }

    public function doctorSeatAddons(): HasMany
    {
        return $this->hasMany(ClinicDoctorSeatAddon::class)->orderBy('valid_until', 'desc');
    }

    public function platformInvoices(): HasMany
    {
        return $this->hasMany(PlatformSalesInvoice::class)->orderBy('invoice_date', 'desc');
    }

    public function activeDoctorSeatAddons(): HasMany
    {
        $today = Carbon::today()->toDateString();
        return $this->hasMany(ClinicDoctorSeatAddon::class)
            ->where('status', 'ACTIVE')
            ->whereDate('valid_from', '<=', $today)
            ->whereDate('valid_until', '>=', $today);
    }

    public function consultationTypes(): HasMany
    {
        return $this->hasMany(DoctorConsultationType::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    public function isActivated(): bool
    {
        return strtoupper($this->activation_status ?? '') === 'ACTIVATED';
    }

    public function isOnboarded(): bool
    {
        return $this->onboarding_status === 'COMPLETED' || (bool) $this->is_onboarded;
    }

    public function isOnboardingCompleted(): bool
    {
        return $this->onboarding_status === 'COMPLETED';
    }

    public function totalDoctorCapacity(): int
    {
        $activeAddonsCount = (int) $this->activeDoctorSeatAddons()->sum('seats_count');

        // Hard rule: DEMOCLINIC doctor capacity base is strictly 2 (Revision 38 Upgrade) plus any purchased add-ons
        if ($this->isDemo()) {
            return 2 + $activeAddonsCount;
        }

        $license = $this->activeLicense ?? $this->latestLicense;
        if (!$license) {
            return 1 + $activeAddonsCount;
        }

        $baseSeats = (int) ($license->included_doctor_seats ?? 1);

        // Active doctor add-on entitlements (Revision 37)
        $activeAddonsCount = (int) $this->activeDoctorSeatAddons()->sum('seats_count');

        if ($activeAddonsCount > 0) {
            return $baseSeats + $activeAddonsCount;
        }

        // Backward-compatibility: if no add-on records exist, check license stored additional seats
        return $baseSeats + (int) ($license->additional_doctor_seats ?? 0);
    }

    public function isDemo(): bool
    {
        return (bool) ($this->is_demo || $this->clinic_code === 'democlinic');
    }

    public function isDemoPaused(): bool
    {
        return $this->isDemo() && strtoupper((string) ($this->demo_status ?? '')) === 'PAUSED';
    }

    public function isDemoActive(): bool
    {
        return $this->isDemo() && strtoupper((string) ($this->demo_status ?? 'ACTIVE')) === 'ACTIVE';
    }

    public function activeDoctorCount(): int
    {
        return $this->doctors()->where('is_active', true)->count();
    }

    public function canAddDoctor(): bool
    {
        return $this->activeDoctorCount() < $this->totalDoctorCapacity();
    }

    public function availableDoctorSeats(): int
    {
        return max(0, $this->totalDoctorCapacity() - $this->activeDoctorCount());
    }

    public function hasValidLicense(): bool
    {
        $license = $this->activeLicense;
        return $license ? $license->isValid() : false;
    }

    public function defaultSchedule(): array
    {
        return [
            'monday' => ['morning' => ['09:00', '11:00'], 'evening' => ['17:00', '19:00']],
            'tuesday' => ['morning' => ['09:00', '11:00'], 'evening' => ['17:00', '19:00']],
            'wednesday' => ['morning' => ['09:00', '11:00'], 'evening' => ['17:00', '19:00']],
            'thursday' => ['morning' => ['09:00', '11:00'], 'evening' => ['17:00', '19:00']],
            'friday' => ['morning' => ['09:00', '11:00'], 'evening' => ['17:00', '19:00']],
            'saturday' => ['morning' => ['09:00', '11:00'], 'evening' => ['17:00', '19:00']],
            'sunday' => ['single' => ['09:00', '11:00']],
        ];
    }

    public function publicBookingUrl(): string
    {
        return url('/book/' . ($this->public_slug ?? $this->clinic_code));
    }

    public function shortBookingUrl(): string
    {
        return url('/b/' . ($this->public_slug ?? $this->clinic_code));
    }

    public function operatorQueueUrl(): string
    {
        return url('/clinic/' . ($this->public_slug ?? $this->clinic_code) . '/operator/queue');
    }

    public function liveQueueUrl(): string
    {
        return url('/clinic/' . ($this->public_slug ?? $this->clinic_code) . '/queue/live');
    }

    public function whatsappConnection(): HasOne
    {
        return $this->hasOne(WhatsAppConnection::class);
    }

    public function whatsappConversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class);
    }

    public function whatsappDeliveries(): HasMany
    {
        return $this->hasMany(WhatsAppMessageDelivery::class);
    }
}

