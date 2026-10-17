<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clinic_knowledge_documents')) {
            Schema::create('clinic_knowledge_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->string('document_key', 100);
                $table->string('title', 255);
                $table->enum('category', [
                    'CLINIC_PROFILE',
                    'DOCTORS',
                    'SERVICES',
                    'FEES',
                    'TIMINGS',
                    'LOCATION',
                    'DIAGNOSTICS',
                    'PACKAGES',
                    'FAQ',
                    'POLICIES',
                    'BOOKING_HELP',
                    'PATIENT_INSTRUCTIONS'
                ])->default('FAQ');
                $table->longText('content');
                $table->json('keywords')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['clinic_id', 'document_key'], 'ckd_clinic_doc_unique');
                $table->index(['clinic_id', 'category'], 'ckd_clinic_cat_index');
                $table->index(['clinic_id', 'is_active'], 'ckd_clinic_active_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_knowledge_documents');
    }
};
