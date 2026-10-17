<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Extend diagnostic_items with laboratory result & specimen metadata
        if (!Schema::hasColumn('diagnostic_items', 'result_type')) {
            Schema::table('diagnostic_items', function (Blueprint $table) {
                $table->string('result_type', 20)->default('NUMERIC')->after('rate');
                $table->string('specimen_type', 50)->nullable()->default('Blood')->after('result_type');
                $table->string('unit', 50)->nullable()->after('specimen_type');
                $table->string('reference_range', 100)->nullable()->after('unit');
                $table->text('reference_text')->nullable()->after('reference_range');
                $table->tinyInteger('decimal_precision')->unsigned()->nullable()->default(1)->after('reference_text');
                $table->decimal('critical_low', 10, 2)->nullable()->after('decimal_precision');
                $table->decimal('critical_high', 10, 2)->nullable()->after('critical_low');
            });
        }

        // 2. Diagnostic Sequences (concurrency-safe monotonic allocator for orders, specimens, reports)
        if (!Schema::hasTable('diagnostic_sequences')) {
            Schema::create('diagnostic_sequences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->string('type', 20); // 'ORDER', 'SPECIMEN', 'REPORT'
                $table->date('sequence_date');
                $table->unsignedInteger('current_number')->default(0);
                $table->timestamps();

                $table->unique(['clinic_id', 'type', 'sequence_date'], 'uniq_diag_seq');
            });
        }

        // 3. Diagnostic Orders
        if (!Schema::hasTable('diagnostic_orders')) {
            Schema::create('diagnostic_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
                $table->foreignId('visit_id')->nullable()->constrained('visits')->nullOnDelete();
                $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
                $table->foreignId('diagnostic_bill_id')->nullable()->constrained('diagnostic_bills')->nullOnDelete();
                $table->string('order_number', 100)->unique();
                $table->dateTime('order_date');
                $table->enum('status', [
                    'ORDERED',
                    'SAMPLE_PENDING',
                    'COLLECTED',
                    'PROCESSING',
                    'RESULT_ENTERED',
                    'VERIFIED',
                    'RELEASED',
                    'CANCELLED'
                ])->default('ORDERED');
                $table->enum('priority', ['ROUTINE', 'URGENT', 'STAT'])->default('ROUTINE');
                $table->text('clinical_notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['clinic_id', 'status']);
                $table->index(['patient_id', 'order_date']);
            });
        }

        // 4. Diagnostic Order Items
        if (!Schema::hasTable('diagnostic_order_items')) {
            Schema::create('diagnostic_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('diagnostic_order_id')->constrained('diagnostic_orders')->cascadeOnDelete();
                $table->foreignId('diagnostic_item_id')->nullable()->constrained('diagnostic_items')->nullOnDelete();
                $table->string('test_name', 255);
                $table->string('item_type', 20)->default('TEST');
                $table->unsignedInteger('quantity')->default(1);
                $table->string('specimen_type', 50)->nullable()->default('Blood');
                $table->enum('status', [
                    'PENDING',
                    'COLLECTED',
                    'PROCESSING',
                    'RESULT_ENTERED',
                    'VERIFIED',
                    'RELEASED',
                    'CANCELLED'
                ])->default('PENDING');
                $table->timestamps();

                $table->index(['diagnostic_order_id', 'status']);
            });
        }

        // 5. Diagnostic Specimens
        if (!Schema::hasTable('diagnostic_specimens')) {
            Schema::create('diagnostic_specimens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('diagnostic_order_id')->constrained('diagnostic_orders')->cascadeOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained('diagnostic_order_items')->nullOnDelete();
                $table->string('specimen_identifier', 100)->unique();
                $table->string('specimen_type', 50)->default('Blood');
                $table->enum('status', [
                    'SAMPLE_PENDING',
                    'COLLECTED',
                    'RECEIVED',
                    'PROCESSING',
                    'RESULT_ENTERED',
                    'VERIFIED',
                    'RELEASED',
                    'REJECTED',
                    'CANCELLED'
                ])->default('SAMPLE_PENDING');
                $table->dateTime('collected_at')->nullable();
                $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('received_at')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->text('notes')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_recollection')->default(false);
                $table->foreignId('parent_specimen_id')->nullable()->constrained('diagnostic_specimens')->nullOnDelete();
                $table->timestamps();

                $table->index(['clinic_id', 'status']);
                $table->index(['diagnostic_order_id', 'status']);
            });
        }

        // 6. Diagnostic Results
        if (!Schema::hasTable('diagnostic_results')) {
            Schema::create('diagnostic_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('diagnostic_order_id')->constrained('diagnostic_orders')->cascadeOnDelete();
                $table->foreignId('diagnostic_order_item_id')->constrained('diagnostic_order_items')->cascadeOnDelete();
                $table->foreignId('diagnostic_specimen_id')->nullable()->constrained('diagnostic_specimens')->nullOnDelete();
                $table->foreignId('diagnostic_item_id')->nullable()->constrained('diagnostic_items')->nullOnDelete();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->string('parameter_name', 255);
                $table->enum('result_type', ['NUMERIC', 'TEXT', 'QUALITATIVE'])->default('NUMERIC');
                $table->string('result_value', 255);
                $table->decimal('numeric_value', 12, 4)->nullable();
                $table->string('unit', 50)->nullable();
                $table->string('reference_range', 100)->nullable();
                $table->enum('abnormal_flag', ['NORMAL', 'HIGH', 'LOW', 'ABNORMAL', 'CRITICAL'])->default('NORMAL');
                $table->text('remarks')->nullable();
                $table->enum('status', ['DRAFT', 'ENTERED', 'VERIFIED', 'RELEASED', 'CORRECTED'])->default('ENTERED');
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_corrected')->default(false);
                $table->text('correction_reason')->nullable();
                $table->foreignId('previous_result_id')->nullable()->constrained('diagnostic_results')->nullOnDelete();
                $table->dateTime('entered_at');
                $table->foreignId('entered_by')->constrained('users')->cascadeOnDelete();
                $table->dateTime('verified_at')->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('released_at')->nullable();
                $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('corrected_at')->nullable();
                $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['clinic_id', 'status']);
                $table->index(['diagnostic_order_id', 'status']);
                $table->index(['patient_id', 'status']);
            });
        }

        // 7. Diagnostic Reports
        if (!Schema::hasTable('diagnostic_reports')) {
            Schema::create('diagnostic_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
                $table->foreignId('diagnostic_order_id')->constrained('diagnostic_orders')->cascadeOnDelete();
                $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
                $table->string('report_number', 100)->unique();
                $table->enum('status', ['DRAFT', 'VERIFIED', 'FINAL'])->default('DRAFT');
                $table->dateTime('finalized_at')->nullable();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('released_at')->nullable();
                $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('report_notes')->nullable();
                $table->string('verification_hash', 64);
                $table->timestamps();

                $table->index(['clinic_id', 'status']);
                $table->index(['patient_id', 'status']);
            });
        }

        // Populate sample clinical metadata for common diagnostic tests
        DB::table('diagnostic_items')->where('name', 'LIKE', '%Hemoglobin%')->update([
            'result_type' => 'NUMERIC',
            'specimen_type' => 'Blood (EDTA)',
            'unit' => 'g/dL',
            'reference_range' => '12.0 - 16.0',
            'decimal_precision' => 1,
            'critical_low' => 7.0,
            'critical_high' => 19.0,
        ]);

        DB::table('diagnostic_items')->where('name', 'LIKE', '%White Blood Cell%')->update([
            'result_type' => 'NUMERIC',
            'specimen_type' => 'Blood (EDTA)',
            'unit' => 'cells/cumm',
            'reference_range' => '4000 - 11000',
            'decimal_precision' => 0,
            'critical_low' => 2000.0,
            'critical_high' => 30000.0,
        ]);

        DB::table('diagnostic_items')->where('name', 'LIKE', '%Red Blood Cell%')->update([
            'result_type' => 'NUMERIC',
            'specimen_type' => 'Blood (EDTA)',
            'unit' => 'mil/cumm',
            'reference_range' => '3.8 - 5.8',
            'decimal_precision' => 2,
        ]);

        DB::table('diagnostic_items')->where('name', 'LIKE', '%Complete Blood Count%')->update([
            'result_type' => 'NUMERIC',
            'specimen_type' => 'Blood (EDTA)',
            'unit' => 'g/dL',
            'reference_range' => '12.0 - 16.0',
            'decimal_precision' => 1,
        ]);

        DB::table('diagnostic_items')->where('name', 'LIKE', '%NCV%')->orWhere('name', 'LIKE', '%F-Wave%')->update([
            'result_type' => 'TEXT',
            'specimen_type' => 'Nerve Conduction',
            'reference_range' => 'Normal conduction velocities',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnostic_reports');
        Schema::dropIfExists('diagnostic_results');
        Schema::dropIfExists('diagnostic_specimens');
        Schema::dropIfExists('diagnostic_order_items');
        Schema::dropIfExists('diagnostic_orders');
        Schema::dropIfExists('diagnostic_sequences');

        if (Schema::hasColumn('diagnostic_items', 'result_type')) {
            Schema::table('diagnostic_items', function (Blueprint $table) {
                $table->dropColumn([
                    'result_type',
                    'specimen_type',
                    'unit',
                    'reference_range',
                    'reference_text',
                    'decimal_precision',
                    'critical_low',
                    'critical_high',
                ]);
            });
        }
    }
};
