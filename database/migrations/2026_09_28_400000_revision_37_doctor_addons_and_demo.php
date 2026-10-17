<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revision 37: Mid-Term Doctor Add-On Licensing & Permanent Demo Tenant Schema
     */
    public function up(): void
    {
        // 1. Add demo flags to clinics table
        if (!Schema::hasColumn('clinics', 'is_demo')) {
            Schema::table('clinics', function (Blueprint $table) {
                $table->boolean('is_demo')->default(false)->after('name');
                $table->string('demo_status', 20)->default('ACTIVE')->after('is_demo'); // ACTIVE, PAUSED
            });
        }

        // 2. Create clinic_doctor_seat_addons table
        if (!Schema::hasTable('clinic_doctor_seat_addons')) {
            Schema::create('clinic_doctor_seat_addons', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('clinic_id');
                $table->unsignedBigInteger('license_id');
                $table->unsignedBigInteger('doctor_id')->nullable(); // Optional assigned doctor
                $table->unsignedInteger('seats_count')->default(1);
                $table->date('purchase_date');
                $table->date('valid_from');
                $table->date('valid_until');
                $table->decimal('price', 10, 2)->default(2999.00);
                $table->string('currency', 10)->default('INR');
                $table->string('payment_method', 50)->default('CASH'); // CASH, UPI, BANK_TRANSFER, CHEQUE, OTHER
                $table->string('payment_reference', 100)->nullable();
                $table->date('payment_date')->nullable();
                $table->string('status', 20)->default('ACTIVE'); // ACTIVE, EXPIRED, CANCELLED
                $table->unsignedBigInteger('created_by_developer_user_id')->nullable();
                $table->text('notes')->nullable();
                $table->string('idempotency_key', 100)->nullable()->unique();
                $table->timestamps();

                $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
                $table->foreign('license_id')->references('id')->on('licenses')->cascadeOnDelete();
                $table->foreign('doctor_id')->references('id')->on('doctors')->nullOnDelete();
                $table->foreign('created_by_developer_user_id')->references('id')->on('developer_users')->nullOnDelete();

                $table->index(['clinic_id', 'status', 'valid_until']);
                $table->index(['license_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_doctor_seat_addons');

        if (Schema::hasColumn('clinics', 'is_demo')) {
            Schema::table('clinics', function (Blueprint $table) {
                $table->dropColumn(['is_demo', 'demo_status']);
            });
        }
    }
};
