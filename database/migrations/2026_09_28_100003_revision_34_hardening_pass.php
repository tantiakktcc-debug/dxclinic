<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Clinics table: explicit onboarding_status (Correction 2)
        if (!Schema::hasColumn('clinics', 'onboarding_status')) {
            Schema::table('clinics', function (Blueprint $table) {
                $table->string('onboarding_status', 30)->default('NOT_STARTED')->after('is_onboarded');
            });
        }

        // Migrate existing clinics: if is_onboarded is true, set COMPLETED, else NOT_STARTED
        DB::table('clinics')->where('is_onboarded', true)->update(['onboarding_status' => 'COMPLETED']);
        DB::table('clinics')->where('is_onboarded', false)->update(['onboarding_status' => 'NOT_STARTED']);
        // Ensure NX0001 is COMPLETED
        DB::table('clinics')->where('id', 1)->update(['onboarding_status' => 'COMPLETED']);

        // 2. Subscription plans: doctor seat pricing and default included seats (Correction 4)
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_plans', 'included_doctor_seats')) {
                $table->unsignedInteger('included_doctor_seats')->default(1)->after('price');
            }
            if (!Schema::hasColumn('subscription_plans', 'additional_doctor_seat_price')) {
                $table->decimal('additional_doctor_seat_price', 10, 2)->default(2999.00)->after('included_doctor_seats');
            }
        });

        // 3. Licenses table: doctor seat capacity & billing fields (Correction 4)
        Schema::table('licenses', function (Blueprint $table) {
            if (!Schema::hasColumn('licenses', 'included_doctor_seats')) {
                $table->unsignedInteger('included_doctor_seats')->default(1)->after('net_amount');
            }
            if (!Schema::hasColumn('licenses', 'additional_doctor_seats')) {
                $table->unsignedInteger('additional_doctor_seats')->default(0)->after('included_doctor_seats');
            }
            if (!Schema::hasColumn('licenses', 'additional_doctor_fee_per_year')) {
                $table->decimal('additional_doctor_fee_per_year', 10, 2)->default(2999.00)->after('additional_doctor_seats');
            }
            if (!Schema::hasColumn('licenses', 'additional_doctor_amount')) {
                $table->decimal('additional_doctor_amount', 10, 2)->default(0.00)->after('additional_doctor_fee_per_year');
            }
        });

        // Set NX0001 license to 5 included doctor seats so existing doctors are never blocked
        DB::table('licenses')->where('clinic_id', 1)->update([
            'included_doctor_seats' => 5,
            'additional_doctor_seats' => 0,
            'additional_doctor_fee_per_year' => 2999.00,
            'additional_doctor_amount' => 0.00,
        ]);

        // 4. Patients table: composite uniqueness UNIQUE(clinic_id, patient_id) (Correction 11)
        try {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropUnique('patients_patient_id_unique');
            });
        } catch (\Throwable $e) {
            // Already dropped or different driver
        }

        try {
            Schema::table('patients', function (Blueprint $table) {
                $table->unique(['clinic_id', 'patient_id'], 'patients_clinic_patient_unique');
            });
        } catch (\Throwable $e) {
            // Index already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropUnique('patients_clinic_patient_unique');
                $table->unique('patient_id', 'patients_patient_id_unique');
            });
        } catch (\Throwable $e) {}

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn([
                'included_doctor_seats',
                'additional_doctor_seats',
                'additional_doctor_fee_per_year',
                'additional_doctor_amount',
            ]);
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'included_doctor_seats',
                'additional_doctor_seat_price',
            ]);
        });

        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('onboarding_status');
        });
    }
};
