<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revision 42: Platform Sales, Invoicing, Collections & Commercial Accounting
     *
     * Creates the commercial invoicing and reporting subsystem WITHOUT creating
     * a duplicate payment ledger (no platform_payments table).
     * Authoritative sources: licenses, license_renewals, clinic_doctor_seat_addons, platform_expenses.
     */
    public function up(): void
    {
        // 1. Platform Invoice Sequences (INV-YYYY-XXXXX)
        if (!Schema::hasTable('platform_invoice_sequences')) {
            Schema::create('platform_invoice_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('prefix', 10)->default('INV');
                $table->unsignedSmallInteger('year');
                $table->unsignedInteger('current_value')->default(0);
                $table->timestamps();

                $table->unique(['prefix', 'year']);
            });

            DB::table('platform_invoice_sequences')->insert([
                'prefix' => 'INV',
                'year' => (int) date('Y'),
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Platform Sales Invoices Table
        if (!Schema::hasTable('platform_sales_invoices')) {
            Schema::create('platform_sales_invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number', 30)->unique();
                $table->date('invoice_date');
                $table->unsignedBigInteger('clinic_id');
                $table->unsignedBigInteger('license_id')->nullable();
                $table->string('sale_type', 30); // INITIAL_LICENSE, LICENSE_RENEWAL, DOCTOR_ADDON
                $table->string('source_type', 100); // Model class or type identifier
                $table->unsignedBigInteger('source_id');
                $table->decimal('subtotal', 10, 2)->default(0.00);
                $table->decimal('discount', 10, 2)->default(0.00);
                $table->decimal('net_amount', 10, 2)->default(0.00);
                $table->string('status', 20)->default('ISSUED'); // ISSUED, PAID, CANCELLED
                
                // Payment reporting from authoritative source
                $table->string('payment_method', 50)->nullable(); // CASH, UPI, BANK_TRANSFER, CHEQUE, OTHER
                $table->string('payment_reference', 100)->nullable();
                $table->date('payment_date')->nullable();
                
                $table->text('notes')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                // Foreign Keys
                $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
                $table->foreign('license_id')->references('id')->on('licenses')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('developer_users')->nullOnDelete();
                $table->foreign('cancelled_by')->references('id')->on('developer_users')->nullOnDelete();

                // Idempotency: strictly one invoice per source transaction
                $table->unique(['source_type', 'source_id'], 'platform_invoices_source_unique');

                // Performance Indexes
                $table->index(['clinic_id', 'invoice_date']);
                $table->index(['sale_type', 'status']);
                $table->index('payment_date');
                $table->index('status');
            });
        }

        // 3. Platform Invoice Items Table
        if (!Schema::hasTable('platform_invoice_items')) {
            Schema::create('platform_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('invoice_id');
                $table->string('item_type', 50); // BASE_PLAN, ADDITIONAL_DOCTOR_SEATS, RENEWAL_PLAN, EXISTING_DOCTOR_SEATS, NEW_DOCTOR_SEATS, TRIAL, DISCOUNT
                $table->string('description', 255);
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 10, 2)->default(0.00);
                $table->decimal('amount', 10, 2)->default(0.00);
                $table->timestamps();

                $table->foreign('invoice_id')->references('id')->on('platform_sales_invoices')->cascadeOnDelete();
                $table->index(['invoice_id', 'item_type']);
            });
        }

        // 4. Platform Expenses Table (Strictly NO clinic_id - Provider level only)
        if (!Schema::hasTable('platform_expenses')) {
            Schema::create('platform_expenses', function (Blueprint $table) {
                $table->id();
                $table->string('category', 50); // SERVER_HOSTING, SMS_GATEWAY, DOMAIN_SSL, MARKETING, SALARIES, OFFICE_RENT, LEGAL_COMPLIANCE, OTHER
                $table->string('description', 255);
                $table->decimal('amount', 10, 2)->default(0.00);
                $table->date('expense_date');
                $table->string('payment_method', 50)->default('BANK_TRANSFER');
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('developer_users')->nullOnDelete();
                $table->index(['expense_date', 'category']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_expenses');
        Schema::dropIfExists('platform_invoice_items');
        Schema::dropIfExists('platform_sales_invoices');
        Schema::dropIfExists('platform_invoice_sequences');
    }
};
