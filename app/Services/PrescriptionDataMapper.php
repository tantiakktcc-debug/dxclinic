<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Vital;
use Carbon\Carbon;

class PrescriptionDataMapper
{
    /**
     * Get the authoritative catalog of selectable template fields.
     */
    public static function getFieldCatalog(?Clinic $clinic = null): array
    {
        $vitalsConfig = $clinic?->print_branding_config['vitals'] ?? [];

        return [
            'PATIENT' => [
                'patient.name' => [
                    'label' => 'Patient Name',
                    'sample' => 'Mrs. Sunita Sharma',
                    'default_w' => 60,
                    'default_h' => 6,
                    'font_weight' => 'bold',
                ],
                'patient.patient_id' => [
                    'label' => 'Patient ID',
                    'sample' => 'DXP-00069',
                    'default_w' => 35,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'patient.age' => [
                    'label' => 'Age',
                    'sample' => '42 Y',
                    'default_w' => 20,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'patient.sex' => [
                    'label' => 'Sex',
                    'sample' => 'Female',
                    'default_w' => 20,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'patient.age_sex' => [
                    'label' => 'Age / Sex',
                    'sample' => '42 Yrs / F',
                    'default_w' => 30,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'patient.mobile' => [
                    'label' => 'Mobile',
                    'sample' => '9876543210',
                    'default_w' => 35,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'patient.address' => [
                    'label' => 'Address',
                    'sample' => 'Flat 402, Sunshine Heights, M.G. Road',
                    'default_w' => 70,
                    'default_h' => 10,
                    'font_weight' => 'normal',
                ],
                'patient.city' => [
                    'label' => 'City',
                    'sample' => 'Siliguri',
                    'default_w' => 30,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'patient.full_address_with_city_name' => [
                    'label' => 'Full Address with City',
                    'sample' => 'Flat 402, Sunshine Heights, M.G. Road, Siliguri',
                    'default_w' => 80,
                    'default_h' => 10,
                    'font_weight' => 'normal',
                ],
            ],
            'VISIT' => [
                'visit.token_no' => [
                    'label' => 'Token No',
                    'sample' => '023',
                    'default_w' => 25,
                    'default_h' => 8,
                    'font_weight' => 'bold',
                    'font_size' => 14,
                ],
                'visit.date' => [
                    'label' => 'Visit Date',
                    'sample' => Carbon::now()->format('d/m/Y'),
                    'default_w' => 30,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'visit.time' => [
                    'label' => 'Visit Time',
                    'sample' => Carbon::now()->format('h:i A'),
                    'default_w' => 25,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'visit.appointment_date' => [
                    'label' => 'Appointment Date',
                    'sample' => Carbon::now()->format('d/m/Y'),
                    'default_w' => 30,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'visit.session' => [
                    'label' => 'Session',
                    'sample' => 'Morning',
                    'default_w' => 25,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
            ],
            'VITALS' => [
                'vitals.bp' => [
                    'label' => 'Blood Pressure (BP)',
                    'sample' => '134/90 mmHg',
                    'default_w' => 35,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'vitals.pulse' => [
                    'label' => 'Pulse Rate',
                    'sample' => '56 bpm',
                    'default_w' => 25,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'vitals.spo2' => [
                    'label' => 'SpO2',
                    'sample' => '96%',
                    'default_w' => 22,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'vitals.temperature' => [
                    'label' => 'Temperature',
                    'sample' => '98.5 °F',
                    'default_w' => 25,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'vitals.fever' => [
                    'label' => 'Fever',
                    'sample' => 'No',
                    'default_w' => 20,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'vitals.respiratory_rate' => [
                    'label' => 'Respiratory Rate (RR)',
                    'sample' => '55 /min',
                    'default_w' => 30,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'vitals.height' => [
                    'label' => 'Height',
                    'sample' => '165 cm',
                    'default_w' => 25,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'vitals.weight' => [
                    'label' => 'Weight',
                    'sample' => '68.0 kg',
                    'default_w' => 25,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'vitals.rbs' => [
                    'label' => 'Random Blood Sugar (RBS)',
                    'sample' => '130 mg/dL',
                    'default_w' => 32,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
            ],
            'DOCTOR' => [
                'doctor.name' => [
                    'label' => 'Doctor Name',
                    'sample' => 'Dr. Rajesh Sharma',
                    'default_w' => 60,
                    'default_h' => 7,
                    'font_weight' => 'bold',
                    'font_size' => 12,
                ],
                'doctor.qualification' => [
                    'label' => 'Doctor Qualification',
                    'sample' => 'MBBS, MD (Medicine)',
                    'default_w' => 60,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'doctor.registration_no' => [
                    'label' => 'Registration No',
                    'sample' => 'Reg No: WB-12345/2018',
                    'default_w' => 50,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
                'doctor.specialization' => [
                    'label' => 'Specialization',
                    'sample' => 'General Physician',
                    'default_w' => 50,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
            ],
            'CLINIC' => [
                'clinic.name' => [
                    'label' => 'Clinic Name',
                    'sample' => 'DxClinic Health Centre',
                    'default_w' => 70,
                    'default_h' => 8,
                    'font_weight' => 'bold',
                    'font_size' => 14,
                ],
                'clinic.address' => [
                    'label' => 'Clinic Address',
                    'sample' => '12/4 Hill Cart Road, Siliguri, WB 734001',
                    'default_w' => 80,
                    'default_h' => 10,
                    'font_weight' => 'normal',
                ],
                'clinic.phone' => [
                    'label' => 'Clinic Phone',
                    'sample' => 'Phone: +91 98765 43210',
                    'default_w' => 50,
                    'default_h' => 6,
                    'font_weight' => 'normal',
                ],
            ],
            'OTHER' => [
                'static_text' => [
                    'label' => 'Static Text',
                    'sample' => 'Rx',
                    'default_w' => 20,
                    'default_h' => 8,
                    'font_weight' => 'bold',
                    'font_size' => 16,
                ],
                'line' => [
                    'label' => 'Horizontal Line',
                    'sample' => '',
                    'default_w' => 180,
                    'default_h' => 1,
                ],
                'rectangle' => [
                    'label' => 'Box / Rectangle',
                    'sample' => '',
                    'default_w' => 180,
                    'default_h' => 40,
                ],
            ],
        ];
    }

    /**
     * Map encounter models to an associative array of safe field values.
     * Returns empty string or null for absent data, never fabricating values.
     */
    public static function mapEncounter(Appointment $appointment, ?Visit $visit = null, ?Vital $vital = null): array
    {
        $patient = $appointment->patient;
        $doctor = $appointment->doctor;
        $clinic = $appointment->clinic;

        if (!$visit) {
            $visit = $appointment->visit ?? Visit::where('appointment_id', $appointment->id)->latest()->first();
        }

        if (!$vital) {
            if ($visit) {
                $vital = $visit->vital ?? Vital::where('visit_id', $visit->id)->latest()->first();
            }
            if (!$vital) {
                $vital = Vital::whereHas('visit', fn($q) => $q->where('appointment_id', $appointment->id))->latest()->first();
            }
        }

        // Format Age and Sex
        $ageStr = '';
        if ($patient?->age !== null && $patient?->age !== '') {
            $ageVal = (int) $patient->age;
            $ageStr = "{$ageVal} Yrs";
        }
        $rawSex = trim((string) ($patient?->gender ?? $patient?->sex ?? ''));
        $sexStr = $rawSex;
        $sexInitial = '';
        if ($rawSex !== '') {
            $upperSex = strtoupper($rawSex);
            if (str_starts_with($upperSex, 'M')) {
                $sexInitial = 'M';
            } elseif (str_starts_with($upperSex, 'F')) {
                $sexInitial = 'F';
            } else {
                $sexInitial = substr($upperSex, 0, 1);
            }
        }
        $ageSexStr = '';
        if ($ageStr !== '' && $sexInitial !== '') {
            $ageSexStr = "{$ageStr} / {$sexInitial}";
        } elseif ($ageStr !== '') {
            $ageSexStr = $ageStr;
        } elseif ($sexInitial !== '') {
            $ageSexStr = $sexInitial;
        }

        // Format Full Address with City (Section 8 - M1)
        $addr = trim((string) ($patient?->address ?? ''));
        $city = trim((string) ($patient?->city ?? ''));
        if ($addr !== '' && $city !== '') {
            $fullAddressWithCity = "{$addr}, {$city}";
        } elseif ($addr !== '') {
            $fullAddressWithCity = $addr;
        } elseif ($city !== '') {
            $fullAddressWithCity = $city;
        } else {
            $fullAddressWithCity = '';
        }

        // Format Visit Date & Time
        $visitDate = '';
        $visitTime = '';
        if ($visit) {
            $visitDate = $visit->created_at ? Carbon::parse($visit->created_at)->format('d/m/Y') : '';
            $visitTime = $visit->created_at ? Carbon::parse($visit->created_at)->format('h:i A') : '';
        } elseif ($appointment->appointment_date) {
            $visitDate = Carbon::parse($appointment->appointment_date)->format('d/m/Y');
        }

        // Format Appointment Date
        $appDate = $appointment->appointment_date ? Carbon::parse($appointment->appointment_date)->format('d/m/Y') : '';

        // Format Token Number
        $tokenNo = '';
        if ($appointment->token_number !== null) {
            $tokenNo = str_pad((string)$appointment->token_number, 3, '0', STR_PAD_LEFT);
        }

        // Format Vitals strictly from source without fabrication
        $bpStr = '';
        if ($vital) {
            $systolic = $vital->bp_systolic ?? $vital->systolic_bp ?? null;
            $diastolic = $vital->bp_diastolic ?? $vital->diastolic_bp ?? null;
            if ($systolic !== null && $diastolic !== null) {
                $bpStr = "{$systolic}/{$diastolic} mmHg";
            } elseif ($systolic !== null) {
                $bpStr = "{$systolic} mmHg";
            }
        }

        $pulseStr = ($vital && $vital->pulse !== null) ? "{$vital->pulse} bpm" : '';
        $spo2Str = ($vital && $vital->spo2 !== null) ? "{$vital->spo2}%" : '';
        $tempStr = ($vital && $vital->temperature !== null) ? "{$vital->temperature} °F" : '';
        
        $feverStr = '';
        if ($vital && $vital->fever !== null) {
            $feverStr = $vital->fever ? 'Yes' : 'No';
        }

        $rrStr = ($vital && $vital->respiratory_rate !== null) ? "{$vital->respiratory_rate} /min" : '';
        $heightStr = ($vital && $vital->height !== null) ? "{$vital->height} cm" : '';
        $weightStr = ($vital && $vital->weight !== null) ? "{$vital->weight} kg" : '';
        $rbsStr = ($vital && $vital->rbs !== null) ? "{$vital->rbs} mg/dL" : '';

        return [
            // Patient
            'patient.name' => $patient?->name ?? '',
            'patient.patient_id' => $patient?->patient_id ?? $patient?->uhid ?? '',
            'patient.age' => $ageStr,
            'patient.sex' => $sexStr,
            'patient.age_sex' => $ageSexStr,
            'patient.mobile' => $patient?->phone ?? $patient?->mobile ?? '',
            'patient.address' => $patient?->address ?? '',
            'patient.city' => $patient?->city ?? '',
            'patient.full_address_with_city_name' => $fullAddressWithCity,

            // Visit
            'visit.token_no' => $tokenNo,
            'visit.date' => $visitDate,
            'visit.time' => $visitTime,
            'visit.appointment_date' => $appDate,
            'visit.session' => $appointment->session ?? 'Regular',

            // Vitals
            'vitals.bp' => $bpStr,
            'vitals.pulse' => $pulseStr,
            'vitals.spo2' => $spo2Str,
            'vitals.temperature' => $tempStr,
            'vitals.fever' => $feverStr,
            'vitals.respiratory_rate' => $rrStr,
            'vitals.height' => $heightStr,
            'vitals.weight' => $weightStr,
            'vitals.rbs' => $rbsStr,

            // Vital Aliases
            'bp' => $bpStr,
            'pulse' => $pulseStr,
            'spo2' => $spo2Str,
            'temperature' => $tempStr,
            'fever' => $feverStr,
            'respiratory_rate' => $rrStr,
            'height' => $heightStr,
            'weight' => $weightStr,
            'rbs' => $rbsStr,

            // Doctor
            'doctor.name' => $doctor?->display_name ?? ($doctor?->name ? 'Dr. ' . $doctor->name : ''),
            'doctor.qualification' => $doctor?->qualification ?? '',
            'doctor.registration_no' => $doctor?->registration_number ? 'Reg: ' . $doctor->registration_number : '',
            'doctor.specialization' => $doctor?->specialization ?? '',

            // Clinic
            'clinic.name' => $clinic?->name ?? '',
            'clinic.address' => $clinic?->address ?? '',
            'clinic.phone' => $clinic?->phone ?? '',
        ];
    }

    /**
     * Get sample data mapped dictionary for test preview and calibration prints.
     */
    public static function getSampleData(?Doctor $doctor = null, ?Clinic $clinic = null): array
    {
        $today = Carbon::now();

        return [
            // Patient
            'patient.name' => 'Mrs. Sunita Sharma',
            'patient.patient_id' => 'DXP-00069',
            'patient.age' => '42 Yrs',
            'patient.sex' => 'Female',
            'patient.age_sex' => '42 Yrs / F',
            'patient.mobile' => '9876543210',
            'patient.address' => 'Flat 402, Sunshine Heights, M.G. Road',
            'patient.city' => 'Siliguri',
            'patient.full_address_with_city_name' => 'Flat 402, Sunshine Heights, M.G. Road, Siliguri',

            // Visit
            'visit.token_no' => '025',
            'visit.date' => $today->format('d/m/Y'),
            'visit.time' => $today->format('h:i A'),
            'visit.appointment_date' => $today->format('d/m/Y'),
            'visit.session' => 'Morning',

            // Vitals
            'vitals.bp' => '120/80 mmHg',
            'vitals.pulse' => '72 bpm',
            'vitals.spo2' => '98%',
            'vitals.temperature' => '98.4 °F',
            'vitals.fever' => 'No',
            'vitals.respiratory_rate' => '18 /min',
            'vitals.height' => '165 cm',
            'vitals.weight' => '70.0 kg',
            'vitals.rbs' => '110 mg/dL',

            // Doctor
            'doctor.name' => $doctor?->display_name ?? 'Dr. Rajesh Sharma',
            'doctor.qualification' => $doctor?->qualification ?? 'MBBS, MD (Medicine)',
            'doctor.registration_no' => $doctor?->registration_number ? 'Reg: ' . $doctor->registration_number : 'Reg: WB-12345/2018',
            'doctor.specialization' => $doctor?->specialization ?? 'General Physician',

            // Clinic
            'clinic.name' => $clinic?->name ?? 'DxClinic Health Centre',
            'clinic.address' => $clinic?->address ?? '12/4 Hill Cart Road, Siliguri, WB 734001',
            'clinic.phone' => $clinic?->phone ?? 'Phone: +91 98765 43210',
        ];
    }

    /**
     * Get a flat array of all registered dynamic field keys.
     */
    public static function getRegisteredFieldKeys(): array
    {
        $keys = [
            'patient.name',
            'patient.patient_id',
            'patient.age',
            'patient.sex',
            'patient.age_sex',
            'patient.mobile',
            'patient.address',
            'patient.city',
            'patient.full_address_with_city_name',
            'visit.token_no',
            'visit.date',
            'visit.time',
            'visit.appointment_date',
            'visit.session',
            'vitals.bp',
            'vitals.pulse',
            'vitals.spo2',
            'vitals.temperature',
            'vitals.fever',
            'vitals.respiratory_rate',
            'vitals.height',
            'vitals.weight',
            'vitals.rbs',
            'bp',
            'pulse',
            'spo2',
            'temperature',
            'fever',
            'respiratory_rate',
            'height',
            'weight',
            'rbs',
            'doctor.name',
            'doctor.qualification',
            'doctor.registration_no',
            'doctor.specialization',
            'clinic.name',
            'clinic.address',
            'clinic.phone',
            'static_text',
            'line',
            'rectangle',
            'image',
            'qr_code',
            'barcode',
        ];

        return $keys;
    }

    /**
     * Validate whether a field key and element type are safe and resolvable.
     */
    public static function isFieldKeySafeAndResolvable(string $fieldKey, ?string $elementType = null): bool
    {
        $keyLower = strtolower(trim($fieldKey));

        // Check prohibited unsafe keys
        $unsafePatterns = ['password', 'secret', 'credential', 'api_key', 'remember_token', 'private_key'];
        foreach ($unsafePatterns as $unsafe) {
            if (str_contains($keyLower, $unsafe)) {
                return false;
            }
        }

        // Shape/static elements are always resolvable if element_type matches
        if (in_array($elementType, ['STATIC_TEXT', 'LINE', 'RECTANGLE', 'IMAGE', 'QR_CODE', 'BARCODE'], true)) {
            return true;
        }

        if (str_starts_with($keyLower, 'line_') || str_starts_with($keyLower, 'rect_') || str_starts_with($keyLower, 'img_') || str_starts_with($keyLower, 'image_')) {
            return true;
        }

        // Registered fields
        $registered = self::getRegisteredFieldKeys();
        return in_array($keyLower, array_map('strtolower', $registered), true);
    }

    /**
     * Get the default human-readable display label for a field key.
     */
    public static function getDefaultLabelForField(string $fieldKey): string
    {
        $keyTrim = trim($fieldKey);
        $keyLower = strtolower($keyTrim);

        $exactMap = [
            'token_no' => 'Token No.',
            'visit.token_no' => 'Token No.',
            'patient_name' => 'Patient Name',
            'patient.name' => 'Patient Name',
            'patient_id' => 'Patient ID',
            'patient.patient_id' => 'Patient ID',
            'doctor_name' => 'Doctor Name',
            'doctor.name' => 'Doctor Name',
            'appointment_date' => 'Appointment Date',
            'visit.appointment_date' => 'Appointment Date',
            'age' => 'Age',
            'patient.age' => 'Age',
            'sex' => 'Sex',
            'patient.sex' => 'Sex',
            'age_sex' => 'Age / Sex',
            'patient.age_sex' => 'Age / Sex',
            'mobile' => 'Mobile',
            'patient.mobile' => 'Mobile',
            'address' => 'Address',
            'patient.address' => 'Address',
            'city' => 'City',
            'patient.city' => 'City',
            'full_address_with_city_name' => 'Full Address with City',
            'patient.full_address_with_city_name' => 'Full Address with City',
            'visit_date' => 'Visit Date',
            'visit.date' => 'Visit Date',
            'visit_time' => 'Visit Time',
            'visit.time' => 'Visit Time',
            'session' => 'Session',
            'visit.session' => 'Session',
            'bp' => 'Blood Pressure (BP)',
            'vitals.bp' => 'Blood Pressure (BP)',
            'pulse' => 'Pulse Rate',
            'vitals.pulse' => 'Pulse Rate',
            'spo2' => 'SpO2',
            'vitals.spo2' => 'SpO2',
            'temperature' => 'Temperature',
            'vitals.temperature' => 'Temperature',
            'fever' => 'Fever',
            'vitals.fever' => 'Fever',
            'respiratory_rate' => 'Respiratory Rate (RR)',
            'vitals.respiratory_rate' => 'Respiratory Rate (RR)',
            'height' => 'Height',
            'vitals.height' => 'Height',
            'weight' => 'Weight',
            'vitals.weight' => 'Weight',
            'rbs' => 'Random Blood Sugar (RBS)',
            'vitals.rbs' => 'Random Blood Sugar (RBS)',
            'doctor.qualification' => 'Doctor Qualification',
            'doctor.registration_no' => 'Registration No',
            'doctor.specialization' => 'Specialization',
            'clinic.name' => 'Clinic Name',
            'clinic.address' => 'Clinic Address',
            'clinic.phone' => 'Clinic Phone',
        ];

        if (isset($exactMap[$keyLower])) {
            return $exactMap[$keyLower];
        }

        // Search catalog
        $catalog = self::getFieldCatalog();
        foreach ($catalog as $category => $fields) {
            foreach ($fields as $k => $def) {
                if (strtolower($k) === $keyLower) {
                    return $def['label'] === 'Token No' ? 'Token No.' : $def['label'];
                }
            }
        }

        // Cleaned fallback
        $cleaned = str_replace(['patient.', 'visit.', 'vitals.', 'doctor.', 'clinic.', '_', '.'], ' ', $keyTrim);
        return ucwords(trim($cleaned));
    }
}
