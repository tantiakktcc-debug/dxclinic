<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Revision 46: Database-Level Composite Foreign Keys & Versioned Fee Master Integrity
     */
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        // 1. Add composite UNIQUE(id, clinic_id) on parent tables: doctors and booking_types
        Schema::table('doctors', function (Blueprint $table) {
            $table->unique(['id', 'clinic_id'], 'doctors_id_clinic_id_unique');
        });

        Schema::table('booking_types', function (Blueprint $table) {
            $table->unique(['id', 'clinic_id'], 'booking_types_id_clinic_id_unique');
        });

        // 2. Harden doctor_booking_type_availability with composite foreign keys
        Schema::table('doctor_booking_type_availability', function (Blueprint $table) use ($isSqlite) {
            if (!$isSqlite) {
                $table->dropForeign('doctor_booking_type_availability_doctor_id_foreign');
                $table->dropForeign('doctor_booking_type_availability_booking_type_id_foreign');

                $table->foreign(['doctor_id', 'clinic_id'], 'fk_dbta_doctor_clinic')
                    ->references(['id', 'clinic_id'])
                    ->on('doctors')
                    ->onDelete('cascade');

                $table->foreign(['booking_type_id', 'clinic_id'], 'fk_dbta_booking_type_clinic')
                    ->references(['id', 'clinic_id'])
                    ->on('booking_types')
                    ->onDelete('cascade');
            }
        });

        // 3. Set default effective_from for existing mapped fees before unique index creation
        DB::table('fee_masters')
            ->whereNotNull('booking_type_id')
            ->whereNull('effective_from')
            ->update(['effective_from' => '2026-01-01']);

        // 4. Harden fee_masters: replace unversioned unique key with versioned unique key, and add composite FKs
        Schema::table('fee_masters', function (Blueprint $table) use ($isSqlite) {
            if (!$isSqlite) {
                $table->dropForeign('fee_masters_doctor_id_foreign');
                $table->dropForeign('fee_masters_booking_type_id_foreign');
                $table->dropUnique('fee_masters_clinic_doc_bt_unique');
                $table->dropUnique('fee_masters_clinic_doc_code_unique');

                $table->unique(
                    ['clinic_id', 'doctor_id', 'booking_type_id', 'effective_from'],
                    'fee_masters_clinic_doc_bt_version_unique'
                );

                $table->foreign(['doctor_id', 'clinic_id'], 'fk_fee_doctor_clinic')
                    ->references(['id', 'clinic_id'])
                    ->on('doctors')
                    ->onDelete('cascade');

                $table->foreign(['booking_type_id', 'clinic_id'], 'fk_fee_booking_type_clinic')
                    ->references(['id', 'clinic_id'])
                    ->on('booking_types')
                    ->onDelete('cascade');
            } else {
                // SQLite: drop old unversioned indexes and add versioned unique key
                $table->dropUnique('fee_masters_clinic_doc_bt_unique');
                $table->dropUnique('fee_masters_clinic_doc_code_unique');
                $table->unique(
                    ['clinic_id', 'doctor_id', 'booking_type_id', 'effective_from'],
                    'fee_masters_clinic_doc_bt_version_unique'
                );
            }
        });

        // 5. Harden appointments with composite foreign keys
        Schema::table('appointments', function (Blueprint $table) use ($isSqlite) {
            if (!$isSqlite) {
                $table->dropForeign('appointments_doctor_id_foreign');
                $table->dropForeign('appointments_booking_type_id_foreign');

                $table->foreign(['doctor_id', 'clinic_id'], 'fk_appointments_doctor_clinic')
                    ->references(['id', 'clinic_id'])
                    ->on('doctors')
                    ->onDelete('restrict');

                $table->foreign(['booking_type_id', 'clinic_id'], 'fk_appointments_booking_type_clinic')
                    ->references(['id', 'clinic_id'])
                    ->on('booking_types')
                    ->onDelete('restrict');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        // 5. Revert appointments composite FKs
        Schema::table('appointments', function (Blueprint $table) use ($isSqlite) {
            if (!$isSqlite) {
                $table->dropForeign('fk_appointments_doctor_clinic');
                $table->dropForeign('fk_appointments_booking_type_clinic');

                $table->foreign('doctor_id', 'appointments_doctor_id_foreign')
                    ->references('id')
                    ->on('doctors')
                    ->onDelete('set null');

                $table->foreign('booking_type_id', 'appointments_booking_type_id_foreign')
                    ->references('id')
                    ->on('booking_types')
                    ->onDelete('set null');
            }
        });

        // 4. Revert fee_masters composite FKs and versioned unique key
        Schema::table('fee_masters', function (Blueprint $table) use ($isSqlite) {
            if (!$isSqlite) {
                $table->dropForeign('fk_fee_doctor_clinic');
                $table->dropForeign('fk_fee_booking_type_clinic');
                $table->dropUnique('fee_masters_clinic_doc_bt_version_unique');

                $table->unique(
                    ['clinic_id', 'doctor_id', 'booking_type_id'],
                    'fee_masters_clinic_doc_bt_unique'
                );

                $table->foreign('doctor_id', 'fee_masters_doctor_id_foreign')
                    ->references('id')
                    ->on('doctors')
                    ->onDelete('set null');

                $table->foreign('booking_type_id', 'fee_masters_booking_type_id_foreign')
                    ->references('id')
                    ->on('booking_types')
                    ->onDelete('cascade');
            } else {
                $table->dropUnique('fee_masters_clinic_doc_bt_version_unique');
            }
        });

        // 2. Revert doctor_booking_type_availability composite FKs
        Schema::table('doctor_booking_type_availability', function (Blueprint $table) use ($isSqlite) {
            if (!$isSqlite) {
                $table->dropForeign('fk_dbta_doctor_clinic');
                $table->dropForeign('fk_dbta_booking_type_clinic');

                $table->foreign('doctor_id', 'doctor_booking_type_availability_doctor_id_foreign')
                    ->references('id')
                    ->on('doctors')
                    ->onDelete('restrict');

                $table->foreign('booking_type_id', 'doctor_booking_type_availability_booking_type_id_foreign')
                    ->references('id')
                    ->on('booking_types')
                    ->onDelete('restrict');
            }
        });

        // 1. Drop parent composite unique keys
        Schema::table('booking_types', function (Blueprint $table) {
            $table->dropUnique('booking_types_id_clinic_id_unique');
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropUnique('doctors_id_clinic_id_unique');
        });
    }
};
