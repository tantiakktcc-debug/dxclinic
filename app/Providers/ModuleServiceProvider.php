<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\PatientAddressFormatter::class);
        $this->app->singleton(\App\Services\PatientIdentityService::class);
        $this->app->singleton(\App\Services\ClinicScheduleService::class);
        $this->app->singleton(\App\Services\TokenAllocationService::class);
        $this->app->singleton(\App\Services\AppointmentService::class);
        $this->app->singleton(\App\Services\VisitService::class);
        $this->app->singleton(\App\Services\VitalService::class);
        $this->app->singleton(\App\Services\BankLedgerService::class);
        $this->app->singleton(\App\Services\OpeningBalanceService::class);
        $this->app->singleton(\App\Services\CashSessionService::class);
        $this->app->singleton(\App\Services\CashReconciliationService::class);
        $this->app->singleton(\App\Services\ReceiptService::class);
        $this->app->singleton(\App\Services\PaymentService::class);
        $this->app->singleton(\App\Services\ConsultationBillingService::class);
        $this->app->singleton(\App\Services\DiagnosticBillingService::class);
        $this->app->singleton(\App\Services\LabCollectionSlipService::class);
        $this->app->singleton(\App\Services\TokenPdfService::class);
        $this->app->singleton(\App\Services\IdempotencyService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register module-specific routes, views, etc. if needed
    }
}
