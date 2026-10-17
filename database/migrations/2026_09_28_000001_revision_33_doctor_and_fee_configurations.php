<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Fee Masters: add doctor_id, drop global code unique, add scoped unique
        Schema::table('fee_masters', function (Blueprint $table) {
            if (!Schema::hasColumn('fee_masters', 'doctor_id')) {
                $table->foreignId('doctor_id')->nullable()->after('clinic_id')->constrained('doctors')->nullOnDelete();
            }
            $table->dropUnique('fee_masters_code_unique');
            $table->unique(['clinic_id', 'doctor_id', 'code'], 'fee_masters_clinic_doc_code_unique');
        });

        // 2. Doctors: add doctor-specific schedule_config
        Schema::table('doctors', function (Blueprint $table) {
            if (!Schema::hasColumn('doctors', 'schedule_config')) {
                $table->json('schedule_config')->nullable()->after('contact_number');
            }
        });

        // 3. Users: add doctor_id binding for DOCTOR role
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'doctor_id')) {
                $table->foreignId('doctor_id')->nullable()->after('role')->constrained('doctors')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'doctor_id')) {
                $table->dropForeign(['doctor_id']);
                $table->dropColumn('doctor_id');
            }
        });

        Schema::table('doctors', function (Blueprint $table) {
            if (Schema::hasColumn('doctors', 'schedule_config')) {
                $table->dropColumn('schedule_config');
            }
        });

        Schema::table('fee_masters', function (Blueprint $table) {
            $table->dropUnique('fee_masters_clinic_doc_code_unique');
            if (Schema::hasColumn('fee_masters', 'doctor_id')) {
                $table->dropForeign(['doctor_id']);
                $table->dropColumn('doctor_id');
            }
            $table->unique('code', 'fee_masters_code_unique');
        });
    }
};
