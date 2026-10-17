<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels Authorization (Sections 65, 66, 78)
|--------------------------------------------------------------------------
*/

// Clinic-wide Queue Channel: Authorized for Admin, Operator, or Doctor within the clinic
Broadcast::channel('clinic.{clinicId}.queue', function (User $user, int|string $clinicId) {
    $clinicId = (int) $clinicId;

    if ($user->role === 'SUPERADMIN') {
        return true;
    }

    if ((int) $user->clinic_id !== $clinicId) {
        return false;
    }

    return in_array($user->role, ['ADMIN', 'OPERATOR', 'DOCTOR', 'SUPERADMIN'], true);
});

// Doctor-specific Queue Channel: Authorized for Admin, Operator, or the specific Doctor
Broadcast::channel('clinic.{clinicId}.doctor.{doctorId}.queue', function (User $user, int|string $clinicId, int|string $doctorId) {
    $clinicId = (int) $clinicId;
    $doctorId = (int) $doctorId;

    if ($user->role === 'SUPERADMIN') {
        return true;
    }

    if ((int) $user->clinic_id !== $clinicId) {
        return false;
    }

    if ($user->role === 'DOCTOR') {
        return (int) $user->doctor_id === $doctorId;
    }

    return in_array($user->role, ['ADMIN', 'OPERATOR', 'SUPERADMIN'], true);
});
