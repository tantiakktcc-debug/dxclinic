<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enhances license_requests with audit snapshot, cancellation, and rejection columns.
     */
    public function up(): void
    {
        Schema::table('license_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('license_requests', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('reviewed_at');
                $table->foreign('rejected_by')->references('id')->on('developer_users')->nullOnDelete();
            }
            if (!Schema::hasColumn('license_requests', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (!Schema::hasColumn('license_requests', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('rejected_at');
            }
            if (!Schema::hasColumn('license_requests', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('license_requests', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('license_requests', 'account_issuance_metadata')) {
                $table->json('account_issuance_metadata')->nullable()->after('admin_notes');
            }
            if (!Schema::hasColumn('license_requests', 'snapshot_data')) {
                $table->json('snapshot_data')->nullable()->after('account_issuance_metadata');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('license_requests', function (Blueprint $table) {
            if (Schema::hasColumn('license_requests', 'rejected_by')) {
                $table->dropForeign(['rejected_by']);
                $table->dropColumn('rejected_by');
            }
            if (Schema::hasColumn('license_requests', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
            if (Schema::hasColumn('license_requests', 'cancelled_by')) {
                $table->dropColumn('cancelled_by');
            }
            if (Schema::hasColumn('license_requests', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('license_requests', 'cancellation_reason')) {
                $table->dropColumn('cancellation_reason');
            }
            if (Schema::hasColumn('license_requests', 'account_issuance_metadata')) {
                $table->dropColumn('account_issuance_metadata');
            }
            if (Schema::hasColumn('license_requests', 'snapshot_data')) {
                $table->dropColumn('snapshot_data');
            }
        });
    }
};
