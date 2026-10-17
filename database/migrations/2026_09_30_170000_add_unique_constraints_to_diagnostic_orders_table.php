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
        Schema::table('diagnostic_orders', function (Blueprint $table) {
            // Guarantee exactly one diagnostic order per diagnostic bill at the database layer
            $table->unique('diagnostic_bill_id', 'uniq_diag_order_bill');
            $table->unique('order_number', 'uniq_diag_order_number');
        });

        Schema::table('diagnostic_reports', function (Blueprint $table) {
            // Guarantee exactly one report per diagnostic order
            $table->unique('diagnostic_order_id', 'uniq_diag_report_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('diagnostic_orders', function (Blueprint $table) {
            $table->dropUnique('uniq_diag_order_bill');
            $table->dropUnique('uniq_diag_order_number');
        });

        Schema::table('diagnostic_reports', function (Blueprint $table) {
            $table->dropUnique('uniq_diag_report_order');
        });
    }
};
