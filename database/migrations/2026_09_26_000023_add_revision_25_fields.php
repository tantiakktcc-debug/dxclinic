<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Appointments: Add consultation_mode
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'consultation_mode')) {
                $table->string('consultation_mode', 30)->default('CLINIC_PHYSICAL')->after('booking_source')->index();
            }
        });

        // 2. Visits: Add consultation_mode, visit_type, revisit_parent_visit_id
        Schema::table('visits', function (Blueprint $table) {
            if (!Schema::hasColumn('visits', 'consultation_mode')) {
                $table->string('consultation_mode', 30)->default('CLINIC_PHYSICAL')->after('session_type')->index();
            }
            if (!Schema::hasColumn('visits', 'visit_type')) {
                $table->string('visit_type', 20)->default('NEW')->after('consultation_mode')->index();
            }
            if (!Schema::hasColumn('visits', 'revisit_parent_visit_id')) {
                $table->foreignId('revisit_parent_visit_id')->nullable()->after('visit_type')->constrained('visits')->nullOnDelete();
            }
        });

        // 3. Consultation Bills: Add consultation_mode, waiver_type
        Schema::table('consultation_bills', function (Blueprint $table) {
            if (!Schema::hasColumn('consultation_bills', 'consultation_mode')) {
                $table->string('consultation_mode', 30)->default('CLINIC_PHYSICAL')->after('net_amount')->index();
            }
            if (!Schema::hasColumn('consultation_bills', 'waiver_type')) {
                $table->string('waiver_type', 50)->nullable()->after('consultation_mode');
            }
        });

        // 4. Patients: Increase mobile length if needed
        Schema::table('patients', function (Blueprint $table) {
            $table->string('mobile', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('mobile', 15)->change();
        });

        Schema::table('consultation_bills', function (Blueprint $table) {
            if (Schema::hasColumn('consultation_bills', 'waiver_type')) {
                $table->dropColumn('waiver_type');
            }
            if (Schema::hasColumn('consultation_bills', 'consultation_mode')) {
                $table->dropColumn('consultation_mode');
            }
        });

        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'revisit_parent_visit_id')) {
                $table->dropForeign(['revisit_parent_visit_id']);
                $table->dropColumn('revisit_parent_visit_id');
            }
            if (Schema::hasColumn('visits', 'visit_type')) {
                $table->dropColumn('visit_type');
            }
            if (Schema::hasColumn('visits', 'consultation_mode')) {
                $table->dropColumn('consultation_mode');
            }
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'consultation_mode')) {
                $table->dropColumn('consultation_mode');
            }
        });
    }
};
