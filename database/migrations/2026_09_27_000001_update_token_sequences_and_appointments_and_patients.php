<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update token_sequences table
        if (!Schema::hasColumn('token_sequences', 'session_type')) {
            Schema::table('token_sequences', function (Blueprint $table) {
                $table->string('session_type', 30)->default('MORNING')->after('token_date');
            });
        }

        // Add new unique index if not present
        try {
            Schema::table('token_sequences', function (Blueprint $table) {
                $table->unique(['clinic_id', 'token_date', 'session_type'], 'clinic_token_date_session_unique');
            });
        } catch (\Throwable $e) {}

        // Drop old unique constraint on token_sequences
        try {
            Schema::table('token_sequences', function (Blueprint $table) {
                $table->dropUnique('clinic_token_date_unique');
            });
        } catch (\Throwable $e) {}

        // 2. Update appointments table unique constraint
        try {
            Schema::table('appointments', function (Blueprint $table) {
                $table->unique(['clinic_id', 'appointment_date', 'session_type', 'token_number'], 'clinic_date_session_token_unique');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropUnique('clinic_date_token_unique');
            });
        } catch (\Throwable $e) {}

        // 3. Update patients table to support canonical ID series and legacy alias
        if (!Schema::hasColumn('patients', 'legacy_alias')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->string('legacy_alias')->nullable()->after('patient_id')->index();
            });
        }

        // 4. Safely migrate legacy non-canonical IDs (e.g. DXP-GRACE-xxxx) to canonical DXP-%05d while preserving legacy_alias
        $allPatients = DB::table('patients')->orderBy('id')->get();
        $legacyPatients = $allPatients->filter(function ($p) {
            return !preg_match('/^DXP-\d{5}$/', $p->patient_id);
        });

        if ($legacyPatients->isNotEmpty()) {
            // Find current max integer
            $maxNum = 0;
            foreach ($allPatients as $p) {
                if (preg_match('/^DXP-(\d+)$/', $p->patient_id, $matches)) {
                    $num = (int) $matches[1];
                    if ($num > $maxNum) {
                        $maxNum = $num;
                    }
                }
            }

            foreach ($legacyPatients as $lp) {
                $maxNum++;
                $canonicalId = sprintf('DXP-%05d', $maxNum);
                DB::table('patients')
                    ->where('id', $lp->id)
                    ->update([
                        'legacy_alias' => $lp->patient_id,
                        'patient_id' => $canonicalId,
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Restore legacy aliases back if needed
        $aliasedPatients = DB::table('patients')->whereNotNull('legacy_alias')->get();
        foreach ($aliasedPatients as $ap) {
            DB::table('patients')
                ->where('id', $ap->id)
                ->update(['patient_id' => $ap->legacy_alias]);
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('legacy_alias');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('clinic_date_session_token_unique');
            $table->unique(['clinic_id', 'appointment_date', 'token_number'], 'clinic_date_token_unique');
        });

        Schema::table('token_sequences', function (Blueprint $table) {
            $table->dropUnique('clinic_token_date_session_unique');
            $table->dropColumn('session_type');
            $table->unique(['clinic_id', 'token_date'], 'clinic_token_date_unique');
        });
    }
};
