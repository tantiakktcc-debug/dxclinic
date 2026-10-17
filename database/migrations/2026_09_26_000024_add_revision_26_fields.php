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
        // 1. CREATE refunds table
        if (!Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->string('refund_number', 50)->unique();
                $table->foreignId('original_payment_id')->constrained('payments')->cascadeOnDelete();
                $table->foreignId('consultation_bill_id')->nullable()->constrained('consultation_bills')->nullOnDelete();
                $table->foreignId('diagnostic_bill_id')->nullable()->constrained('diagnostic_bills')->nullOnDelete();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->string('refund_type', 30); // FULL, PARTIAL, REFUND_AS_DISCOUNT
                $table->decimal('refund_amount', 10, 2);
                $table->string('refund_method', 10); // CASH, UPI
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete();
                $table->string('reference_number', 255)->nullable();
                $table->text('reason');
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('requested_at')->nullable();
                $table->foreignId('doctor_instruction_by')->nullable()->constrained('doctors')->nullOnDelete();
                $table->timestamp('doctor_instruction_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('processed_at')->nullable();
                $table->string('status', 20)->default('REQUESTED'); // REQUESTED, APPROVED, REJECTED, PROCESSED, VOIDED
                $table->timestamps();

                // Composite and lookup indexes
                $table->index(['original_payment_id', 'status']);
                $table->index(['clinic_id', 'processed_at', 'status']);
                $table->index('patient_id');
                $table->index(['refund_method', 'status']);
            });
        }

        // 2. CREATE receipt_revisions table (immutable history rows, created_at only, no updated_at)
        if (!Schema::hasTable('receipt_revisions')) {
            Schema::create('receipt_revisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
                $table->unsignedInteger('revision_number');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('reason');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('financial_impact')->nullable();
                $table->foreignId('linked_refund_id')->nullable()->constrained('refunds')->nullOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->unique(['receipt_id', 'revision_number']);
            });
        }

        // 3. ALTER consultation_bills.status ENUM
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE consultation_bills MODIFY COLUMN status ENUM('PENDING', 'PAID', 'WAIVED', 'CANCELLED', 'REFUNDED') NOT NULL DEFAULT 'PENDING'");
        }

        // 4. ALTER diagnostic_bills.status ENUM
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE diagnostic_bills MODIFY COLUMN status ENUM('PENDING', 'PAID', 'CANCELLED', 'REFUNDED') NOT NULL DEFAULT 'PENDING'");
        }

        // 5. ALTER audit_logs table (additive fields referencing refunds)
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'reason')) {
                $table->text('reason')->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('audit_logs', 'change_category')) {
                $table->string('change_category', 50)->nullable()->after('reason');
            }
            if (!Schema::hasColumn('audit_logs', 'financial_impact')) {
                $table->text('financial_impact')->nullable()->after('change_category');
            }
            if (!Schema::hasColumn('audit_logs', 'linked_refund_id')) {
                $table->foreignId('linked_refund_id')->nullable()->after('financial_impact')->constrained('refunds')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Revert audit_logs
        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'linked_refund_id')) {
                $table->dropForeign(['linked_refund_id']);
                $table->dropColumn('linked_refund_id');
            }
            if (Schema::hasColumn('audit_logs', 'financial_impact')) {
                $table->dropColumn('financial_impact');
            }
            if (Schema::hasColumn('audit_logs', 'change_category')) {
                $table->dropColumn('change_category');
            }
            if (Schema::hasColumn('audit_logs', 'reason')) {
                $table->dropColumn('reason');
            }
        });

        // 2. Revert diagnostic_bills.status ENUM
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE diagnostic_bills MODIFY COLUMN status ENUM('PENDING', 'PAID', 'CANCELLED') NOT NULL DEFAULT 'PENDING'");
        }

        // 3. Revert consultation_bills.status ENUM
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE consultation_bills MODIFY COLUMN status ENUM('PENDING', 'PAID', 'WAIVED', 'CANCELLED') NOT NULL DEFAULT 'PENDING'");
        }

        // 4. Drop receipt_revisions
        Schema::dropIfExists('receipt_revisions');

        // 5. Drop refunds
        Schema::dropIfExists('refunds');
    }
};
