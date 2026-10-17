<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->date('appointment_date')->index();
            $table->string('session_type', 30); // MORNING, EVENING, SUNDAY_SINGLE
            $table->enum('booking_source', ['ONLINE', 'TELEPHONIC', 'CLINIC', 'WHATSAPP']);
            $table->unsignedInteger('token_number');
            $table->string('formatted_token', 10); // e.g. '001'
            $table->enum('status', ['BOOKED', 'ARRIVED', 'COMPLETED', 'CANCELLED', 'ABSENT'])->default('BOOKED');
            $table->string('secure_token', 64)->unique();
            $table->foreignId('grace_parent_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->timestamps();

            $table->unique(['clinic_id', 'appointment_date', 'token_number'], 'clinic_date_token_unique');
            $table->index(['appointment_date', 'session_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
