<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('payment_number')->unique();
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete();
            
            // Financial source integrity (Section 72):
            $table->foreignId('consultation_bill_id')->nullable()->constrained('consultation_bills')->nullOnDelete();
            $table->foreignId('diagnostic_bill_id')->nullable()->constrained('diagnostic_bills')->nullOnDelete();
            
            $table->enum('payment_method', ['CASH', 'UPI']);
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['SETTLED', 'VOIDED'])->default('SETTLED');
            $table->boolean('soundbox_confirmed')->default(false);
            $table->string('reference_number')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('settled_at')->useCurrent();
            $table->timestamps();

            $table->index(['payment_method', 'status', 'settled_at']);
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->foreignId('payment_id')->unique()->constrained('payments')->cascadeOnDelete();
            $table->enum('receipt_type', ['CONSULTATION', 'DIAGNOSTIC']);
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payments');
    }
};
