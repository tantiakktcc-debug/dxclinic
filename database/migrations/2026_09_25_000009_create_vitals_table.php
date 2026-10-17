<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vitals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained('visits')->cascadeOnDelete();
            $table->decimal('weight', 5, 2)->nullable(); // kg
            $table->decimal('height', 5, 2)->nullable(); // cm
            $table->unsignedSmallInteger('bp_systolic')->nullable(); // mmHg
            $table->unsignedSmallInteger('bp_diastolic')->nullable(); // mmHg
            $table->unsignedSmallInteger('pulse')->nullable(); // bpm
            $table->unsignedSmallInteger('spo2')->nullable(); // %
            $table->decimal('temperature', 4, 1)->nullable(); // Fahrenheit
            $table->boolean('fever')->default(false);
            $table->unsignedSmallInteger('respiratory_rate')->nullable(); // breaths/min
            $table->decimal('rbs', 6, 2)->nullable(); // mg/dL
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vitals');
    }
};
