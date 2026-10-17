<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('bill_number')->unique();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->date('bill_date')->index();
            $table->decimal('gross_amount', 10, 2)->default(600.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('waiver_amount', 10, 2)->default(0.00);
            $table->decimal('net_amount', 10, 2)->default(600.00);
            $table->enum('status', ['PENDING', 'PAID', 'WAIVED', 'CANCELLED', 'REFUNDED'])->default('PENDING');
            $table->timestamps();
        });

        Schema::create('diagnostic_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('bill_number')->unique();
            $table->foreignId('visit_id')->nullable()->constrained('visits')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->date('bill_date')->index();
            $table->decimal('gross_amount', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('waiver_amount', 10, 2)->default(0.00);
            $table->decimal('net_amount', 10, 2)->default(0.00);
            $table->enum('status', ['PENDING', 'PAID', 'CANCELLED', 'REFUNDED'])->default('PENDING');
            $table->timestamps();
        });

        Schema::create('diagnostic_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_bill_id')->constrained('diagnostic_bills')->cascadeOnDelete();
            $table->foreignId('diagnostic_item_id')->nullable()->constrained('diagnostic_items')->nullOnDelete();
            $table->string('item_type', 20); // TEST, PACKAGE
            $table->string('item_name');
            $table->decimal('rate', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_bill_items');
        Schema::dropIfExists('diagnostic_bills');
        Schema::dropIfExists('consultation_bills');
    }
};
