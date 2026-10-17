<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revision 36: License Request & Renewal Request — Public Commercial Layer
     *
     * Creates the public license request subsystem that feeds into the
     * existing LicenseService::issueLicense() and LicenseService::renewLicense()
     * engines without modifying them structurally.
     */
    public function up(): void
    {
        // 1. Add pincode to clinics (needed for request-seeded clinic creation)
        if (!Schema::hasColumn('clinics', 'pincode')) {
            Schema::table('clinics', function (Blueprint $table) {
                $table->string('pincode', 10)->nullable()->after('city');
            });
        }

        // 2. License request sequences (auto-generate LR-000001, LR-000002, ...)
        if (!Schema::hasTable('license_request_sequences')) {
            Schema::create('license_request_sequences', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('current_value')->default(0);
                $table->string('prefix', 10)->default('LR');
                $table->timestamps();
            });

            DB::table('license_request_sequences')->insert([
                'current_value' => 0,
                'prefix' => 'LR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. License requests table
        if (!Schema::hasTable('license_requests')) {
            Schema::create('license_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_number', 20)->unique();
                $table->string('request_type', 20); // INITIAL, RENEWAL
                $table->string('status', 30)->default('SUBMITTED');
                // SUBMITTED, UNDER_REVIEW, FULFILLED, REJECTED, CANCELLED

                // Contact / Owner Info (public form data)
                $table->string('clinic_name', 255);
                $table->string('owner_name', 255);
                $table->string('mobile', 20);
                $table->string('email', 255);
                $table->text('address')->nullable();
                $table->string('city', 100)->nullable();
                $table->string('pincode', 10)->nullable();

                // Renewal Verification (RENEWAL only)
                $table->unsignedBigInteger('clinic_id')->nullable();
                $table->string('clinic_code', 20)->nullable();

                // Plan Selection
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->boolean('wants_trial')->default(false); // INITIAL only
                $table->unsignedInteger('requested_doctor_count')->default(1); // Customer's TOTAL

                // Pricing Snapshot (immutable — frozen at submission/fulfillment)
                $table->string('plan_name_snapshot', 100)->nullable();
                $table->unsignedInteger('plan_duration_months')->nullable();
                $table->unsignedInteger('trial_months')->default(0);
                $table->unsignedInteger('included_doctor_count')->default(1);
                $table->unsignedInteger('additional_doctor_count')->default(0);
                $table->decimal('doctor_rate_snapshot', 10, 2)->default(0.00);
                $table->decimal('base_amount', 10, 2)->default(0.00);
                $table->decimal('additional_doctor_amount', 10, 2)->default(0.00);
                $table->decimal('trial_amount', 10, 2)->default(0.00); // Always ₹0
                $table->decimal('discount_amount', 10, 2)->default(0.00);
                $table->decimal('net_amount', 10, 2)->default(0.00);

                // Superadmin Processing
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('payment_confirmed_by')->nullable();
                $table->timestamp('payment_confirmed_at')->nullable();
                $table->string('payment_method', 50)->nullable();
                $table->string('payment_reference', 100)->nullable();
                $table->date('payment_date')->nullable();
                $table->unsignedBigInteger('fulfilled_license_id')->nullable();
                $table->unsignedBigInteger('fulfilled_clinic_id')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('admin_notes')->nullable();

                // Request metadata
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                // Foreign keys
                $table->foreign('clinic_id')->references('id')->on('clinics')->nullOnDelete();
                $table->foreign('plan_id')->references('id')->on('subscription_plans')->nullOnDelete();
                $table->foreign('reviewed_by')->references('id')->on('developer_users')->nullOnDelete();
                $table->foreign('payment_confirmed_by')->references('id')->on('developer_users')->nullOnDelete();
                $table->foreign('fulfilled_license_id')->references('id')->on('licenses')->nullOnDelete();
                $table->foreign('fulfilled_clinic_id')->references('id')->on('clinics')->nullOnDelete();

                // Indexes
                $table->index(['status', 'created_at']);
                $table->index(['request_type', 'status']);
                $table->index('clinic_code');
            });
        }

        // 4. License request events (immutable audit trail)
        if (!Schema::hasTable('license_request_events')) {
            Schema::create('license_request_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('license_request_id');
                $table->string('event_type', 50);
                // SUBMITTED, UNDER_REVIEW, PAYMENT_CONFIRMED, FULFILLED, REJECTED, CANCELLED
                $table->string('performed_by_type', 30)->nullable();
                // public, developer_user, system
                $table->unsignedBigInteger('performed_by_id')->nullable();
                $table->string('old_status', 30)->nullable();
                $table->string('new_status', 30);
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('license_request_id')
                    ->references('id')->on('license_requests')
                    ->cascadeOnDelete();

                $table->index(['license_request_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('license_request_events');
        Schema::dropIfExists('license_requests');
        Schema::dropIfExists('license_request_sequences');

        if (Schema::hasColumn('clinics', 'pincode')) {
            Schema::table('clinics', function (Blueprint $table) {
                $table->dropColumn('pincode');
            });
        }
    }
};
