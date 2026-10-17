<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->date('token_date');
            $table->unsignedInteger('current_token')->default(0);
            $table->timestamps();

            $table->unique(['clinic_id', 'token_date'], 'clinic_token_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_sequences');
    }
};
