<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prescription_pad_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('paper_width_mm', 8, 2)->default(210.00); // Default A4
            $table->decimal('paper_height_mm', 8, 2)->default(297.00); // Default A4
            $table->string('orientation', 20)->default('portrait'); // portrait, landscape
            $table->string('background_path', 500)->nullable();
            $table->unsignedInteger('background_width_px')->nullable();
            $table->unsignedInteger('background_height_px')->nullable();
            $table->string('template_type', 50)->default('CUSTOM');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 30)->default('DRAFT'); // DRAFT, ACTIVE, ARCHIVED
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['clinic_id', 'doctor_id', 'version'], 'ppt_clinic_doc_version_unique');
            $table->index(['clinic_id', 'doctor_id', 'is_active'], 'ppt_clinic_doc_active_idx');
            $table->index(['doctor_id', 'status'], 'ppt_doc_status_idx');
        });

        Schema::create('prescription_pad_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('prescription_pad_templates')->cascadeOnDelete();
            $table->string('field_key', 100);
            $table->string('element_type', 50)->default('FIELD'); // FIELD, STATIC_TEXT, LINE, RECTANGLE, IMAGE, QR, BARCODE
            $table->decimal('x_mm', 8, 2)->default(0.00);
            $table->decimal('y_mm', 8, 2)->default(0.00);
            $table->decimal('width_mm', 8, 2)->default(50.00);
            $table->decimal('height_mm', 8, 2)->default(10.00);
            $table->string('font_family', 100)->default('Helvetica');
            $table->decimal('font_size', 5, 2)->default(10.00);
            $table->string('font_weight', 30)->default('normal');
            $table->string('font_style', 30)->default('normal');
            $table->string('text_align', 20)->default('left');
            $table->decimal('line_height', 5, 2)->default(1.20);
            $table->string('color', 30)->default('#000000');
            $table->integer('rotation')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->json('config_json')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'sort_order'], 'ppe_template_sort_idx');
            $table->index(['template_id', 'field_key'], 'ppe_template_field_idx');
        });

        Schema::create('printer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name', 150);
            $table->decimal('paper_width_mm', 8, 2)->default(210.00);
            $table->decimal('paper_height_mm', 8, 2)->default(297.00);
            $table->decimal('offset_x_mm', 8, 2)->default(0.00);
            $table->decimal('offset_y_mm', 8, 2)->default(0.00);
            $table->decimal('scale_x', 6, 4)->default(1.0000);
            $table->decimal('scale_y', 6, 4)->default(1.0000);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['clinic_id', 'is_default'], 'pp_clinic_default_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescription_pad_elements');
        Schema::dropIfExists('prescription_pad_templates');
        Schema::dropIfExists('printer_profiles');
    }
};
