<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->date('session_date')->index();
            $table->string('session_type', 30); // MORNING, EVENING, SUNDAY_SINGLE
            $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_physical_cash', 12, 2)->default(0.00);
            $table->decimal('expected_closing_cash', 12, 2)->default(0.00);
            $table->decimal('closing_physical_cash', 12, 2)->nullable();
            $table->decimal('cash_variance', 12, 2)->default(0.00);
            $table->text('variance_reason')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'session_date', 'session_type'], 'cash_session_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
