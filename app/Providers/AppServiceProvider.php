<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * WhatsApp Provider Selection (Section 5 — Provider Architecture):
     *
     *   WHATSAPP_PROVIDER=BAILEYS → BaileysWhatsAppProvider (development, local gateway)
     *   WHATSAPP_PROVIDER=META    → MetaWhatsAppProvider    (production, Meta Cloud API)
     *   (testing + !live_enabled) → MockWhatsAppProvider    (unit/feature tests)
     *
     * Meta architecture is PRESERVED regardless of active provider.
     * Baileys is DEVELOPMENT ONLY — never deploy as production provider.
     */
    public function register(): void
    {
        // WhatsApp Provider Interface — provider-agnostic binding
        $this->app->bind(
            \App\Modules\WhatsApp\Infrastructure\WhatsAppProviderInterface::class,
            function ($app) {
                // Testing environment always uses Mock (unless explicitly live-enabled)
                if ($app->environment('testing') && !config('dxclinic.whatsapp.live_enabled')) {
                    return $app->make(\App\Modules\WhatsApp\Infrastructure\MockWhatsAppProvider::class);
                }

                $provider = strtoupper(trim(config('dxclinic.whatsapp.provider', 'meta')));

                if ($provider === 'BAILEYS') {
                    // Development provider: delegates to local Baileys gateway (127.0.0.1:9090)
                    return $app->make(\App\Modules\WhatsApp\Infrastructure\BaileysWhatsAppProvider::class);
                }

                // Default: Meta Cloud API (production)
                return $app->make(\App\Modules\WhatsApp\Infrastructure\MetaWhatsAppProvider::class);
            }
        );

        // BaileysConnectionService (singleton — manages connection state)
        $this->app->singleton(\App\Services\BaileysConnectionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            if (!class_exists(\App\Console\Commands\CreateSuperadmin::class)) {
                require_once __DIR__ . '/../Console/Commands/CreateSuperadmin.php';
            }
            $this->commands([
                \App\Console\Commands\CreateSuperadmin::class,
            ]);
        }

        view()->composer('*', function ($view) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('clinics')) {
                    $clinic = \App\Services\TenantContext::clinic();
                    if ($clinic && !empty($clinic->public_slug)) {
                        \Illuminate\Support\Facades\URL::defaults(['public_slug' => $clinic->public_slug]);
                    }
                }
            } catch (\Throwable $e) {
                // Ignore DB errors when running tests or before migrations
            }
        });
    }
}
