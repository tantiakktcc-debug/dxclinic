<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'email', 'phone', 'role', 'doctor_id', 'status', 'password', 'last_login_at', 'clinic_id', 'login_id', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return strtoupper($this->status ?? 'ACTIVE') === 'ACTIVE';
    }

    public function isAdmin(): bool
    {
        return strtoupper($this->role ?? '') === 'ADMIN';
    }

    public function isOperator(): bool
    {
        return strtoupper($this->role ?? '') === 'OPERATOR';
    }

    public function isDoctor(): bool
    {
        return strtoupper($this->role ?? '') === 'DOCTOR';
    }

    public function hasRole(string ...$roles): bool
    {
        $currentRole = strtoupper($this->role ?? 'ADMIN');
        $uppercased = array_map('strtoupper', $roles);
        return in_array($currentRole, $uppercased, true);
    }

    public static function nextLoginId(?int $clinicId, string $role): string
    {
        $rolePrefix = match (strtoupper($role)) {
            'ADMIN' => 'ADM',
            'OPERATOR' => 'OPR',
            'DOCTOR' => 'DOC',
            default => 'USR',
        };

        $query = static::query();
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        $count = $query->where('role', strtoupper($role))->count();
        return sprintf('%s%04d', $rolePrefix, $count + 1);
    }
}
