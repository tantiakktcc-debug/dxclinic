<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_id')->unique(); // e.g. DXP-00001
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('age');
            $table->enum('sex', ['MALE', 'FEMALE', 'OTHER']);
            $table->string('mobile', 15)->index();
            $table->text('address');
            $table->string('city', 100)->index();
            $table->string('profile_status')->default('ACTIVE');
            $table->timestamps();

            $table->index(['mobile', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
