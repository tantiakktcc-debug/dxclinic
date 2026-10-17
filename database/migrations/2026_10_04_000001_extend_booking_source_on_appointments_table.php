<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `appointments` MODIFY COLUMN `booking_source` ENUM('ONLINE', 'TELEPHONIC', 'CLINIC', 'WHATSAPP') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `appointments` MODIFY COLUMN `booking_source` ENUM('ONLINE', 'TELEPHONIC', 'CLINIC') NOT NULL");
        }
    }
};
