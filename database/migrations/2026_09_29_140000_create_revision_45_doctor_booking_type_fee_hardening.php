<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revision 45 — Strict Clinic + Doctor + Booking Type + Fee Mapping Hardening.
     *
     * Core Rule:
     *   Clinic -> Doctor -> Mapped Booking Types -> Mapped Fee -> Appointment
     *
     * Invariants:
     *   1. Fee master explicitly supports clinic_id + doctor_id + booking_type_id.
     *   2. Unique constraint on (clinic_id, doctor_id, booking_type_id).
     *   3. Appointments snapshot resolved fee in `fee_amount`.
     *   4. Existing historical appointments and bills remain 100% immutable.
     *   5. Additive only — zero destructive column or table drops.
     */
    public function up(): void
    {
        // 1. Extend fee_masters with booking_type_id and effective dating
        Schema::table('fee_masters', function (Blueprint $table) {
            if (!Schema::hasColumn('fee_masters', 'booking_type_id')) {
                $table->foreignId('booking_type_id')
                    ->nullable()
                    ->after('doctor_id')
                    ->constrained('booking_types')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('fee_masters', 'effective_from')) {
                $table->date('effective_from')->nullable()->after('amount');
            }

            if (!Schema::hasColumn('fee_masters', 'effective_to')) {
                $table->date('effective_to')->nullable()->after('effective_from');
            }

            // Unique constraint on (clinic_id, doctor_id, booking_type_id)
            $table->unique(['clinic_id', 'doctor_id', 'booking_type_id'], 'fee_masters_clinic_doc_bt_unique');
        });

        // 2. Add fee_amount snapshot to appointments
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'fee_amount')) {
                $table->decimal('fee_amount', 10, 2)->nullable()->after('booking_type_id');
            }
        });

        // 3. Seed initial doctor-specific booking type fees from existing rates
        $availabilities = DB::table('doctor_booking_type_availability')
            ->where('is_enabled', 1)
            ->get();

        foreach ($availabilities as $avail) {
            $bt = DB::table('booking_types')->where('id', $avail->booking_type_id)->first();
            $doc = DB::table('doctors')->where('id', $avail->doctor_id)->first();
            if (!$bt || !$doc) {
                continue;
            }

            $alreadyConfigured = DB::table('fee_masters')
                ->where('clinic_id', $avail->clinic_id)
                ->where('doctor_id', $avail->doctor_id)
                ->where('booking_type_id', $avail->booking_type_id)
                ->exists();

            if (!$alreadyConfigured) {
                // Determine appropriate initial fee based on doctor's existing fee or clinic fee
                $docFee = DB::table('fee_masters')
                    ->where('clinic_id', $avail->clinic_id)
                    ->where('doctor_id', $avail->doctor_id)
                    ->where('is_active', 1)
                    ->where(function ($q) use ($bt) {
                        if ($bt->code === 'ONLINE_CONSULTANCY') {
                            $q->where('code', 'CONSULTATION_ONLINE');
                        } elseif ($bt->code === 'EMERGENCY_CONSULTANCY') {
                            $q->where('code', 'CONSULTATION_EMERGENCY');
                        } else {
                            $q->whereIn('code', ['CONSULTATION_PHYSICAL', 'CONSULTATION_REGULAR']);
                        }
                    })
                    ->first();

                $clinicFee = DB::table('fee_masters')
                    ->where('clinic_id', $avail->clinic_id)
                    ->whereNull('doctor_id')
                    ->where('is_active', 1)
                    ->where(function ($q) use ($bt) {
                        if ($bt->code === 'ONLINE_CONSULTANCY') {
                            $q->where('code', 'CONSULTATION_ONLINE');
                        } elseif ($bt->code === 'EMERGENCY_CONSULTANCY') {
                            $q->where('code', 'CONSULTATION_EMERGENCY');
                        } else {
                            $q->whereIn('code', ['CONSULTATION_PHYSICAL', 'CONSULTATION_REGULAR']);
                        }
                    })
                    ->first();

                $amount = $docFee?->amount ?? $clinicFee?->amount ?? ($bt->code === 'ONLINE_CONSULTANCY' ? 700.00 : 600.00);
                $code = 'BT_' . $bt->code . '_DOC' . $doc->id;

                DB::table('fee_masters')->insert([
                    'clinic_id'       => $avail->clinic_id,
                    'doctor_id'       => $avail->doctor_id,
                    'booking_type_id' => $avail->booking_type_id,
                    'code'            => $code,
                    'name'            => $bt->name,
                    'amount'          => $amount,
                    'is_active'       => 1,
                    'effective_from'  => null,
                    'effective_to'    => null,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }

        // 4. Backfill historical appointments fee_amount from consultation_bills or default
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE appointments a
                LEFT JOIN consultation_bills cb ON cb.appointment_id = a.id
                SET a.fee_amount = COALESCE(cb.gross_amount, 600.00)
                WHERE a.fee_amount IS NULL
            ");
        } else {
            DB::statement("
                UPDATE appointments
                SET fee_amount = COALESCE(
                    (SELECT gross_amount FROM consultation_bills WHERE consultation_bills.appointment_id = appointments.id LIMIT 1),
                    600.00
                )
                WHERE fee_amount IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'fee_amount')) {
                $table->dropColumn('fee_amount');
            }
        });

        Schema::table('fee_masters', function (Blueprint $table) {
            $table->dropUnique('fee_masters_clinic_doc_bt_unique');

            if (Schema::hasColumn('fee_masters', 'effective_to')) {
                $table->dropColumn('effective_to');
            }

            if (Schema::hasColumn('fee_masters', 'effective_from')) {
                $table->dropColumn('effective_from');
            }

            if (Schema::hasColumn('fee_masters', 'booking_type_id')) {
                $table->dropForeign(['booking_type_id']);
                $table->dropColumn('booking_type_id');
            }
        });
    }
};
