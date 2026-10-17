<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revision 44 — Multiple Prescription Pad Templates Per Doctor + Extensible Booking Types.
     *
     * ADDITIVE ONLY. Zero existing columns/tables modified destructively.
     * booking_source, consultation_mode semantics remain exactly as-is.
     * Token numbering remains unchanged (one common daily numeric sequence).
     * Historical appointment.prescription_pad_template_id invariant preserved.
     */
    public function up(): void
    {
        // ==========================================================
        // 1. BOOKING_TYPES master table (clinic-scoped, extensible)
        // ==========================================================
        Schema::create('booking_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->string('code', 60);          // Internal machine code e.g. CLINIC_CONSULTANCY
            $table->string('name', 100);          // Customer-facing label e.g. "Clinic Consultancy"
            $table->string('consultation_mode', 50)->default('CLINIC_PHYSICAL');
            $table->boolean('is_system')->default(false);   // Built-in types protected from deletion
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public_bookable')->default(true); // Show on public PWA?
            $table->unsignedSmallInteger('display_order')->default(100);
            $table->timestamps();

            // A clinic cannot have two booking types with the same code
            $table->unique(['clinic_id', 'code'], 'unique_clinic_booking_type_code');
            $table->index(['clinic_id', 'is_active', 'display_order'], 'idx_booking_types_active_order');
        });

        // ==========================================================
        // 2. DOCTOR_BOOKING_TYPE_AVAILABILITY
        //    Per-doctor availability toggle for each booking type.
        //    Evolves existing doctor_consultation_types pattern.
        // ==========================================================
        Schema::create('doctor_booking_type_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('booking_type_id')->constrained('booking_types')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['clinic_id', 'doctor_id', 'booking_type_id'], 'unique_doctor_booking_type');
            $table->index(['clinic_id', 'doctor_id', 'is_enabled'], 'idx_dbt_enabled');
        });

        // ==========================================================
        // 3. Add booking_type_id to appointments (immutable reference)
        //    FK deliberately nullable — existing records have no booking type yet.
        // ==========================================================
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('booking_type_id')
                ->nullable()
                ->after('consultation_mode')
                ->constrained('booking_types')
                ->nullOnDelete();   // If BT deleted (never for system types), appointment remains valid

            $table->index(['clinic_id', 'booking_type_id'], 'idx_appointments_booking_type');
        });

        // ==========================================================
        // 4. Add template_name snapshot to appointments
        //    Allows display of historical template name even if template row renamed.
        // ==========================================================
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('prescription_template_name', 150)->nullable()->after('is_legacy_pad');
        });

        // ==========================================================
        // 5. Add human-readable name to prescription_pad_templates
        //    (already has 'name' field — just adding 'label' for display grouping)
        //    SKIP — name is sufficient, no new column needed.
        // ==========================================================

        // ==========================================================
        // 6. SEED: Built-in system booking types for every existing clinic
        // ==========================================================
        $systemTypes = [
            [
                'code'              => 'CLINIC_CONSULTANCY',
                'name'              => 'Clinic Consultancy',
                'consultation_mode' => 'CLINIC_PHYSICAL',
                'is_system'         => true,
                'is_active'         => true,
                'is_public_bookable'=> true,
                'display_order'     => 10,
            ],
            [
                'code'              => 'ONLINE_CONSULTANCY',
                'name'              => 'Online Consultancy',
                'consultation_mode' => 'ONLINE',
                'is_system'         => true,
                'is_active'         => true,
                'is_public_bookable'=> true,
                'display_order'     => 20,
            ],
            [
                'code'              => 'EMERGENCY_CONSULTANCY',
                'name'              => 'Emergency Consultancy',
                'consultation_mode' => 'CLINIC_PHYSICAL',
                'is_system'         => true,
                'is_active'         => true,
                'is_public_bookable'=> false,
                'display_order'     => 30,
            ],
        ];

        $clinics = DB::table('clinics')->pluck('id');
        $now = now();

        foreach ($clinics as $clinicId) {
            foreach ($systemTypes as $type) {
                // Idempotent: skip if already exists (in case migration re-run)
                $exists = DB::table('booking_types')
                    ->where('clinic_id', $clinicId)
                    ->where('code', $type['code'])
                    ->exists();

                if (!$exists) {
                    DB::table('booking_types')->insert(array_merge($type, [
                        'clinic_id'  => $clinicId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]));
                }
            }
        }

        // ==========================================================
        // 7. SEED: Default doctor availability for all existing doctors
        //    All doctors get CLINIC_CONSULTANCY and EMERGENCY_CONSULTANCY enabled.
        //    ONLINE_CONSULTANCY seeded based on existing doctor_consultation_types.
        // ==========================================================
        $doctors = DB::table('doctors')->select('id', 'clinic_id')->get();

        foreach ($doctors as $doctor) {
            $clinicId = $doctor->clinic_id;

            // Fetch the seeded booking types for this clinic
            $btMap = DB::table('booking_types')
                ->where('clinic_id', $clinicId)
                ->pluck('id', 'code');

            // Clinic Consultancy — always enabled for all doctors
            if (isset($btMap['CLINIC_CONSULTANCY'])) {
                $this->upsertDoctorBTA($clinicId, $doctor->id, $btMap['CLINIC_CONSULTANCY'], true);
            }

            // Online Consultancy — enabled based on existing doctor_consultation_types
            if (isset($btMap['ONLINE_CONSULTANCY'])) {
                $hasOnline = DB::table('doctor_consultation_types')
                    ->where('clinic_id', $clinicId)
                    ->where('doctor_id', $doctor->id)
                    ->where('consultation_mode', 'ONLINE')
                    ->where('is_enabled', true)
                    ->exists();
                $this->upsertDoctorBTA($clinicId, $doctor->id, $btMap['ONLINE_CONSULTANCY'], $hasOnline);
            }

            // Emergency Consultancy — enabled for all doctors by default
            if (isset($btMap['EMERGENCY_CONSULTANCY'])) {
                $this->upsertDoctorBTA($clinicId, $doctor->id, $btMap['EMERGENCY_CONSULTANCY'], true);
            }
        }

        // ==========================================================
        // 8. SEED: Fee Master entries for EMERGENCY_CONSULTANCY
        //    Uses CONSULTATION_PHYSICAL rate as base — no hard-coded amount.
        // ==========================================================
        $clinicFeeMasters = DB::table('fee_masters')
            ->whereNull('doctor_id')
            ->where('code', 'CONSULTATION_PHYSICAL')
            ->where('is_active', true)
            ->get();

        foreach ($clinicFeeMasters as $fm) {
            $emergencyExists = DB::table('fee_masters')
                ->where('clinic_id', $fm->clinic_id)
                ->whereNull('doctor_id')
                ->where('code', 'CONSULTATION_EMERGENCY')
                ->exists();

            if (!$emergencyExists) {
                DB::table('fee_masters')->insert([
                    'clinic_id'  => $fm->clinic_id,
                    'doctor_id'  => null,
                    'code'       => 'CONSULTATION_EMERGENCY',
                    'name'       => 'Emergency Consultancy',
                    'amount'     => $fm->amount, // Inherits physical consultation fee
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        // Remove added columns from appointments
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['booking_type_id']);
            $table->dropIndex('idx_appointments_booking_type');
            $table->dropColumn(['booking_type_id', 'prescription_template_name']);
        });

        // Drop new tables in reverse dependency order
        Schema::dropIfExists('doctor_booking_type_availability');
        Schema::dropIfExists('booking_types');
    }

    /**
     * Idempotent upsert for doctor_booking_type_availability.
     */
    private function upsertDoctorBTA(int $clinicId, int $doctorId, int $bookingTypeId, bool $enabled): void
    {
        $exists = DB::table('doctor_booking_type_availability')
            ->where('clinic_id', $clinicId)
            ->where('doctor_id', $doctorId)
            ->where('booking_type_id', $bookingTypeId)
            ->exists();

        if (!$exists) {
            DB::table('doctor_booking_type_availability')->insert([
                'clinic_id'       => $clinicId,
                'doctor_id'       => $doctorId,
                'booking_type_id' => $bookingTypeId,
                'is_enabled'      => $enabled,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }
};
