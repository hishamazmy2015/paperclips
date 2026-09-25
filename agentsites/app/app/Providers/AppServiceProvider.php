<?php

namespace App\Providers;

use App\Content\ContentGenerator;
use App\Content\TemplateGenerator;
use App\Messaging\Notifier;
use App\Messaging\NullNotifier;
use App\Provisioning\PhoneNormalizer;
use App\Tenancy\TenantContext;
use App\Themes\ThemeRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(ThemeRegistry::class);
        $this->app->singleton(PhoneNormalizer::class, fn (): PhoneNormalizer => PhoneNormalizer::make());

        // Provider-neutral abstractions (spec §6). The AI and WhatsApp providers arrive in
        // Phase 2; the fallbacks never fail, so publishing never depends on them (§22.3).
        $this->app->bind(ContentGenerator::class, TemplateGenerator::class);
        $this->app->bind(Notifier::class, NullNotifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // No silent N+1 queries outside production (spec §19).
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->app->make(ThemeRegistry::class)->registerViewNamespaces();
    }
}
