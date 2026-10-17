<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PatientIdentityService
{
    /**
     * Find existing patient by name and mobile (Section 45, 46)
     */
    public function findExisting(int $clinicId, string $name, string $mobile): ?Patient
    {
        $raw10 = PhoneNumberNormalizer::toRaw10($mobile) ?? preg_replace('/[^0-9]/', '', $mobile);
        $cleanName = trim($name);

        if (strlen($raw10) < 10 || $cleanName === '') {
            return null;
        }

        // Match by last 10 digits of mobile and case-insensitive name
        return Patient::where('clinic_id', $clinicId)
            ->where(function ($query) use ($raw10) {
                $query->where('mobile', $raw10)
                    ->orWhere('mobile', '+91 ' . $raw10)
                    ->orWhere('mobile', 'LIKE', '%' . $raw10);
            })
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($cleanName)])
            ->first();
    }

    /**
     * Find patient by exact business patient_id or legacy alias (e.g., DXP-00001 or DXP-GRACE-xxxx)
     */
    public function findByPatientId(int $clinicId, string $patientId): ?Patient
    {
        $id = trim($patientId);
        return Patient::where('clinic_id', $clinicId)
            ->where(function ($query) use ($id) {
                $query->where('patient_id', $id)
                    ->orWhere('legacy_alias', $id);
            })
            ->first();
    }

    /**
     * Find candidate existing patients by mobile number for duplicate verification (Revision 32)
     */
    public function findCandidatesByMobile(int $clinicId, string $mobile): \Illuminate\Database\Eloquent\Collection
    {
        $raw10 = PhoneNumberNormalizer::toRaw10($mobile) ?? preg_replace('/[^0-9]/', '', $mobile);

        if (strlen($raw10) < 10) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return Patient::where('clinic_id', $clinicId)
            ->where(function ($query) use ($raw10) {
                $query->where('mobile', $raw10)
                    ->orWhere('mobile', '+91 ' . $raw10)
                    ->orWhere('mobile', 'LIKE', '%' . $raw10);
            })
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Resolve existing or register new patient (Section 45, 46, 56)
     */
    public function resolvePatient(array $data): Patient
    {
        $clinicId = (int) ($data['clinic_id'] ?? Clinic::first()?->id ?? 1);
        $mobile = (string) ($data['phone'] ?? $data['mobile'] ?? '');
        $name = (string) ($data['name'] ?? '');

        $existing = $this->findExisting($clinicId, $name, $mobile);
        if ($existing) {
            return $existing;
        }

        return $this->registerPatient([
            'clinic_id' => $clinicId,
            'name' => $name,
            'mobile' => $mobile,
            'age' => isset($data['age']) ? (int) $data['age'] : 30,
            'sex' => strtoupper(trim((string) ($data['sex'] ?? 'MALE'))),
            'city' => trim((string) ($data['city'] ?? 'Local')),
            'address' => trim((string) ($data['address'] ?? 'Desk Registration')),
        ]);
    }

    /**
     * Register new patient with dedicated city field (Section 40, 41)
     */
    public function registerPatient(array $data): Patient
    {
        $clinicId = (int) ($data['clinic_id'] ?? Clinic::first()?->id);
        if (!$clinicId) {
            throw new InvalidArgumentException("Clinic ID is required for patient registration.");
        }

        $rawName = trim((string) ($data['name'] ?? ''));
        if (!PersonNameNormalizer::isValid($rawName)) {
            throw new InvalidArgumentException(PersonNameNormalizer::ERROR_MESSAGE);
        }
        $name = PersonNameNormalizer::normalize($rawName);

        $age = isset($data['age']) ? (int) $data['age'] : 0;
        $sex = strtoupper(trim((string) ($data['sex'] ?? '')));
        
        $rawMobile = (string) ($data['mobile'] ?? '');
        if (!PhoneNumberNormalizer::isValid($rawMobile)) {
            throw new InvalidArgumentException(PhoneNumberNormalizer::ERROR_MESSAGE);
        }
        $mobile = PhoneNumberNormalizer::normalize($rawMobile);

        $address = trim((string) ($data['address'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));

        if ($age < 0 || $age > 130) {
            throw new InvalidArgumentException("Valid patient age is required (0 to 130 years).");
        }
        if (!in_array($sex, ['MALE', 'FEMALE', 'OTHER'])) {
            throw new InvalidArgumentException("Sex must be MALE, FEMALE, or OTHER.");
        }
        if ($city === '') {
            throw new InvalidArgumentException("Patient city is required for registration (Section 41).");
        }

        return DB::transaction(function () use ($clinicId, $name, $age, $sex, $mobile, $address, $city) {
            // Check for duplicate in transaction
            $existing = $this->findExisting($clinicId, $name, $mobile);
            if ($existing) {
                return $existing;
            }

            // Mobile patient registration limit enforcement (Revision 34)
            $clinic = Clinic::find($clinicId);
            $maxPerMobile = $clinic?->max_patients_per_mobile ?? 4;
            $countForMobile = Patient::where('clinic_id', $clinicId)->where('mobile', $mobile)->count();
            if ($countForMobile >= $maxPerMobile) {
                throw new InvalidArgumentException("Maximum patient limit of {$maxPerMobile} reached for mobile {$mobile}.");
            }

            $patientId = $this->generateNextPatientId($clinicId);

            return Patient::create([
                'patient_id' => $patientId,
                'clinic_id' => $clinicId,
                'name' => $name,
                'age' => $age,
                'sex' => $sex,
                'mobile' => $mobile,
                'address' => $address,
                'city' => $city,
                'profile_status' => 'ACTIVE',
            ]);
        });
    }

    /**
     * Complete or update missing profile fields at arrival without creating another patient (Section 54)
     */
    public function completeProfileAtArrival(Patient $patient, array $data): Patient
    {
        $updates = [];

        if (empty($patient->sex) && !empty($data['sex']) && in_array(strtoupper($data['sex']), ['MALE', 'FEMALE', 'OTHER'])) {
            $updates['sex'] = strtoupper($data['sex']);
        }

        if (empty($patient->address) && !empty($data['address'])) {
            $updates['address'] = trim($data['address']);
        }

        if (empty($patient->city) && !empty($data['city'])) {
            $updates['city'] = trim($data['city']);
        }

        if (!empty($updates)) {
            $patient->update($updates);
        }

        return $patient->fresh();
    }

    /**
     * Generate sequential unique business patient ID: DXP-00001
     */
    protected function generateNextPatientId(int $clinicId): string
    {
        $patients = Patient::where('patient_id', 'LIKE', 'DXP-%')
            ->lockForUpdate()
            ->get(['patient_id']);

        $maxNum = 0;
        foreach ($patients as $p) {
            if (preg_match('/^DXP-(\d+)$/', $p->patient_id, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        $nextNum = $maxNum + 1;
        $candidate = sprintf('DXP-%05d', $nextNum);

        while (Patient::where('patient_id', $candidate)->exists()) {
            $nextNum++;
            $candidate = sprintf('DXP-%05d', $nextNum);
        }

        return $candidate;
    }
}
