<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            if (!Schema::hasColumn('clinics', 'default_doctor_id')) {
                $table->foreignId('default_doctor_id')->nullable()->after('is_onboarded')->constrained('doctors')->nullOnDelete();
            }
            if (!Schema::hasColumn('clinics', 'logo_position')) {
                $table->string('logo_position', 20)->default('left')->after('default_doctor_id');
            }
            if (!Schema::hasColumn('clinics', 'print_branding_config')) {
                $table->json('print_branding_config')->nullable()->after('logo_position');
            }
            if (!Schema::hasColumn('clinics', 'booking_qr_local_url')) {
                $table->string('booking_qr_local_url')->default('http://192.168.1.2:8000/book')->after('print_branding_config');
            }
            if (!Schema::hasColumn('clinics', 'booking_qr_prod_url')) {
                $table->string('booking_qr_prod_url')->default('https://rxclinic.rxdoctor.co.in:8000/book')->after('booking_qr_local_url');
            }
            if (!Schema::hasColumn('clinics', 'backup_schedule_time')) {
                $table->string('backup_schedule_time', 10)->default('22:00')->after('booking_qr_prod_url');
            }
            if (!Schema::hasColumn('clinics', 'backup_auto_enabled')) {
                $table->boolean('backup_auto_enabled')->default(true)->after('backup_schedule_time');
            }
        });

        Schema::table('doctors', function (Blueprint $table) {
            if (!Schema::hasColumn('doctors', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('contact_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropForeign(['default_doctor_id']);
            $table->dropColumn([
                'default_doctor_id',
                'logo_position',
                'print_branding_config',
                'booking_qr_local_url',
                'booking_qr_prod_url',
                'backup_schedule_time',
                'backup_auto_enabled',
            ]);
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
