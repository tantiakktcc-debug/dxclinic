<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Clinics table updates
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('clinic_code', 20)->nullable()->after('id');
            $table->integer('max_advance_booking_days')->default(1)->after('phone');
            $table->integer('max_patients_per_mobile')->default(4)->after('max_advance_booking_days');
            $table->string('activation_status', 30)->default('ACTIVATED')->after('max_patients_per_mobile');
            $table->timestamp('activated_at')->nullable()->after('activation_status');
            $table->unsignedBigInteger('activated_by_user_id')->nullable()->after('activated_at');
        });

        // Seed NX0001 for existing clinic
        DB::table('clinics')->where('id', 1)->update([
            'clinic_code' => 'NX0001',
            'activation_status' => 'ACTIVATED',
            'activated_at' => now(),
            'max_advance_booking_days' => 1,
            'max_patients_per_mobile' => 4,
        ]);

        Schema::table('clinics', function (Blueprint $table) {
            $table->unique('clinic_code', 'clinics_clinic_code_unique');
        });

        // 2. Clinic sequences table
        Schema::create('clinic_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('current_value')->default(0);
            $table->string('prefix', 10)->default('NX');
            $table->timestamps();
        });

        DB::table('clinic_sequences')->insert([
            'current_value' => 1,
            'prefix' => 'NX',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Users table updates
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->nullable()->after('id');
            $table->string('login_id', 30)->nullable()->after('email');
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        // Assign existing users to clinic 1 and generate login_ids
        DB::table('users')->whereNull('clinic_id')->update(['clinic_id' => 1]);

        $roleCounters = [];
        $existingUsers = DB::table('users')->where('clinic_id', 1)->orderBy('id')->get();
        foreach ($existingUsers as $u) {
            $rolePrefix = match (strtoupper((string) $u->role)) {
                'ADMIN' => 'ADM',
                'OPERATOR' => 'OPR',
                'DOCTOR' => 'DOC',
                default => 'USR',
            };
            $roleCounters[$rolePrefix] = ($roleCounters[$rolePrefix] ?? 0) + 1;
            $loginId = sprintf('%s%04d', $rolePrefix, $roleCounters[$rolePrefix]);
            DB::table('users')->where('id', $u->id)->update(['login_id' => $loginId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $table->unique(['clinic_id', 'login_id'], 'users_clinic_id_login_id_unique');
        });

        // 4. Journals table updates
        Schema::table('journals', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->nullable()->after('id');
        });
        DB::table('journals')->whereNull('clinic_id')->update(['clinic_id' => 1]);
        Schema::table('journals', function (Blueprint $table) {
            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
        });

        // 5. Backups table updates
        Schema::table('backups', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->nullable()->after('id');
        });
        DB::table('backups')->whereNull('clinic_id')->update(['clinic_id' => 1]);
        Schema::table('backups', function (Blueprint $table) {
            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
        });

        // 6. Patients table unique index update
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('patients_patient_id_unique');
            $table->unique(['clinic_id', 'patient_id'], 'patients_clinic_id_patient_id_unique');
        });

        // 7. Subscription plans table
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->unsignedInteger('duration_months');
            $table->decimal('price', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        $plans = [
            ['code' => 'TRIAL', 'name' => 'Trial Plan', 'duration_months' => 1, 'price' => 0.00, 'display_order' => 1],
            ['code' => 'QUARTERLY', 'name' => 'Quarterly Plan', 'duration_months' => 3, 'price' => 4499.00, 'display_order' => 2],
            ['code' => 'HALF_YEARLY', 'name' => 'Half-Yearly Plan', 'duration_months' => 6, 'price' => 7999.00, 'display_order' => 3],
            ['code' => 'YEARLY', 'name' => 'Yearly Plan', 'duration_months' => 12, 'price' => 14999.00, 'display_order' => 4],
            ['code' => 'THREE_YEAR', 'name' => '3-Year Plan', 'duration_months' => 36, 'price' => 34999.00, 'display_order' => 5],
            ['code' => 'FIVE_YEAR', 'name' => '5-Year Plan', 'duration_months' => 60, 'price' => 49999.00, 'display_order' => 6],
        ];
        foreach ($plans as $p) {
            DB::table('subscription_plans')->insert(array_merge($p, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 8. Developer users (Superadmin)
        Schema::create('developer_users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // 9. Licenses table
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('license_key_hash', 255);
            $table->text('license_key_encrypted');
            $table->string('license_key_prefix', 10)->default('NXCL');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('net_amount', 10, 2)->default(0.00);
            $table->string('status', 30)->default('ACTIVE'); // TRIAL, ACTIVE, EXPIRED, SUSPENDED, CANCELLED, REVOKED
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->foreign('issued_by')->references('id')->on('developer_users')->nullOnDelete();
        });

        // 10. License activations table
        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_id');
            $table->unsignedBigInteger('clinic_id');
            $table->unsignedBigInteger('user_id');
            $table->string('activation_code_hash', 255);
            $table->timestamp('activated_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('license_id')->references('id')->on('licenses')->cascadeOnDelete();
            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // 11. License events table (audit trail)
        Schema::create('license_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_id');
            $table->string('event_type', 50); // PROVISIONED_BY_MIGRATION, ISSUED, ACTIVATED, RENEWED, etc.
            $table->string('performed_by_type', 30)->nullable(); // developer_user, user, system
            $table->unsignedBigInteger('performed_by_id')->nullable();
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->date('old_end_date')->nullable();
            $table->date('new_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('license_id')->references('id')->on('licenses')->cascadeOnDelete();
        });

        // Provision initial active license for primary clinic if clinic exists
        if (DB::table('clinics')->where('id', 1)->exists()) {
            $yearlyPlan = DB::table('subscription_plans')->where('code', 'YEARLY')->first();
            $initialKey = \App\Services\LicenseService::generateLicenseKey('NXCL');
            $licenseId = DB::table('licenses')->insertGetId([
                'clinic_id' => 1,
                'plan_id' => $yearlyPlan ? $yearlyPlan->id : 1,
                'license_key_hash' => hash('sha256', strtoupper($initialKey)),
                'license_key_encrypted' => Crypt::encryptString($initialKey),
                'license_key_prefix' => 'NXCL',
                'start_date' => now()->subDays(30)->toDateString(),
                'end_date' => now()->addDays(335)->toDateString(),
                'amount' => 14999.00,
                'discount_amount' => 0.00,
                'net_amount' => 14999.00,
                'status' => 'ACTIVE',
                'issued_by' => null,
                'issued_at' => now(),
                'activated_at' => now(),
                'notes' => 'Default platform license provisioned for primary clinic',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('license_events')->insert([
                'license_id' => $licenseId,
                'event_type' => 'PROVISIONED_BY_MIGRATION',
                'performed_by_type' => 'system',
                'performed_by_id' => null,
                'old_status' => null,
                'new_status' => 'ACTIVE',
                'old_end_date' => null,
                'new_end_date' => now()->addDays(335)->toDateString(),
                'notes' => 'Initial active license provisioned automatically during platform migration',
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
            ]);
        }

        // 12. Doctor consultation types table
        Schema::create('doctor_consultation_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');
            $table->unsignedBigInteger('doctor_id');
            $table->string('consultation_mode', 50); // CLINIC_PHYSICAL, ONLINE, VISITING_CONSULTANCY
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->cascadeOnDelete();
            $table->unique(['clinic_id', 'doctor_id', 'consultation_mode'], 'unique_clinic_doctor_mode');
        });

        // Seed consultation types for existing doctors
        $doctors = DB::table('doctors')->get();
        foreach ($doctors as $d) {
            DB::table('doctor_consultation_types')->insert([
                [
                    'clinic_id' => $d->clinic_id,
                    'doctor_id' => $d->id,
                    'consultation_mode' => 'CLINIC_PHYSICAL',
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'clinic_id' => $d->clinic_id,
                    'doctor_id' => $d->id,
                    'consultation_mode' => 'ONLINE',
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'clinic_id' => $d->clinic_id,
                    'doctor_id' => $d->id,
                    'consultation_mode' => 'VISITING_CONSULTANCY',
                    'is_enabled' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_consultation_types');
        Schema::dropIfExists('license_events');
        Schema::dropIfExists('license_activations');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('developer_users');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('clinic_sequences');

        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('patients_clinic_id_patient_id_unique');
            $table->unique('patient_id', 'patients_patient_id_unique');
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropColumn('clinic_id');
        });

        Schema::table('journals', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropColumn('clinic_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropUnique('users_clinic_id_login_id_unique');
            $table->unique('email', 'users_email_unique');
            $table->dropColumn(['clinic_id', 'login_id', 'must_change_password']);
        });

        Schema::table('clinics', function (Blueprint $table) {
            $table->dropUnique('clinics_clinic_code_unique');
            $table->dropColumn([
                'clinic_code',
                'max_advance_booking_days',
                'max_patients_per_mobile',
                'activation_status',
                'activated_at',
                'activated_by_user_id',
            ]);
        });
    }
};
