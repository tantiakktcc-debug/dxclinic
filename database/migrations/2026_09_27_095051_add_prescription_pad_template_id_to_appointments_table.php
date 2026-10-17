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
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('prescription_pad_template_id')
                ->nullable()
                ->after('status')
                ->constrained('prescription_pad_templates')
                ->onDelete('restrict');

            $table->boolean('is_legacy_pad')
                ->default(false)
                ->after('prescription_pad_template_id');
        });

        // Set all existing historical appointments to is_legacy_pad = true
        // to preserve legacy Doctor Pad rendering behavior unconditionally.
        \Illuminate\Support\Facades\DB::table('appointments')->update(['is_legacy_pad' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['prescription_pad_template_id']);
            $table->dropColumn(['prescription_pad_template_id', 'is_legacy_pad']);
        });
    }
};
