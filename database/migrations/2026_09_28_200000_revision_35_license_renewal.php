<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add renewal tracking columns to licenses table if not present
        Schema::table('licenses', function (Blueprint $table) {
            if (!Schema::hasColumn('licenses', 'previous_license_id')) {
                $table->foreignId('previous_license_id')->nullable()->after('clinic_id')->constrained('licenses')->nullOnDelete();
            }
            if (!Schema::hasColumn('licenses', 'renewal_number')) {
                $table->unsignedInteger('renewal_number')->default(0)->after('status');
            }
            if (!Schema::hasColumn('licenses', 'is_renewal')) {
                $table->boolean('is_renewal')->default(false)->after('renewal_number');
            }
            if (!Schema::hasColumn('licenses', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('net_amount');
            }
            if (!Schema::hasColumn('licenses', 'payment_reference')) {
                $table->string('payment_reference', 100)->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('licenses', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('payment_reference');
            }
        });

        // 2. Create immutable license_renewals table
        if (!Schema::hasTable('license_renewals')) {
            Schema::create('license_renewals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
                $table->foreignId('previous_license_id')->nullable()->constrained('licenses')->nullOnDelete();
                $table->unsignedInteger('renewal_number')->default(1);
                $table->foreignId('plan_id')->constrained('subscription_plans');
                $table->date('previous_end_date')->nullable();
                $table->date('new_start_date');
                $table->date('new_end_date');
                $table->unsignedInteger('duration_months');
                $table->decimal('base_amount', 10, 2);
                $table->boolean('renew_existing_doctor_seats')->default(true);
                $table->unsignedInteger('existing_doctor_seats_renewed')->default(0);
                $table->decimal('existing_doctor_seat_amount', 10, 2)->default(0.00);
                $table->unsignedInteger('new_doctor_seats_purchased')->default(0);
                $table->decimal('new_doctor_seat_amount', 10, 2)->default(0.00);
                $table->decimal('total_doctor_seat_amount', 10, 2)->default(0.00);
                $table->decimal('discount_amount', 10, 2)->default(0.00);
                $table->decimal('net_amount', 10, 2);
                $table->boolean('payment_received')->default(true);
                $table->string('payment_method', 50)->default('CASH');
                $table->date('payment_date');
                $table->string('payment_reference', 100)->nullable();
                $table->string('idempotency_key', 64)->nullable()->unique();
                $table->unsignedBigInteger('renewed_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['clinic_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('license_renewals');

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropForeign(['previous_license_id']);
            $table->dropColumn([
                'previous_license_id',
                'renewal_number',
                'is_renewal',
                'payment_method',
                'payment_reference',
                'payment_date',
            ]);
        });
    }
};
