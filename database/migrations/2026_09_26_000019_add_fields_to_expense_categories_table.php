<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_categories', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('ledger_account_id');
            }
            if (!Schema::hasColumn('expense_categories', 'display_order')) {
                $table->integer('display_order')->default(0)->after('is_active');
            }
            if (!Schema::hasColumn('expense_categories', 'description')) {
                $table->text('description')->nullable()->after('display_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'display_order', 'description']);
        });
    }
};
