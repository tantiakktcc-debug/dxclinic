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
        $isMysql = DB::getDriverName() === 'mysql';

        // 1. Expand appointments.status enum to include IN_PROGRESS
        if ($isMysql) {
            DB::statement("ALTER TABLE `appointments` MODIFY COLUMN `status` ENUM('BOOKED', 'ARRIVED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'ABSENT') NOT NULL DEFAULT 'BOOKED'");
        }

        // 2. Add started_at column to visits table if not present
        Schema::table('visits', function (Blueprint $table) {
            if (!Schema::hasColumn('visits', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('arrived_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'started_at')) {
                $table->dropColumn('started_at');
            }
        });

        if ($isMysql) {
            DB::statement("ALTER TABLE `appointments` MODIFY COLUMN `status` ENUM('BOOKED', 'ARRIVED', 'COMPLETED', 'CANCELLED', 'ABSENT') NOT NULL DEFAULT 'BOOKED'");
        }
    }
};
