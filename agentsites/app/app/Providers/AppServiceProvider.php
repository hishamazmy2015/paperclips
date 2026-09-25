<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Content\ClaudeGenerator;
use App\Content\ContentGenerator;
use App\Content\TemplateGenerator;
use App\Mail\FileTransport;
use App\Messaging\Dialog360Notifier;
use App\Messaging\FileNotifier;
use App\Messaging\Notifier;
use App\Messaging\NullNotifier;
use App\Provisioning\PhoneNormalizer;
use App\Tenancy\TenantContext;
use App\Themes\ThemeRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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

        // Provider-neutral abstractions (spec §6), chosen by config/providers.php. The fallbacks
        // never fail, so publishing never depends on an external API (§22.3).
        $this->app->bind(ContentGenerator::class, function (Application $app): ContentGenerator {
            $config = $app->make('config');
            $key = (string) $config->get('providers.content.anthropic.key', '');
            if ($config->get('providers.content.generator') === 'claude' && $key !== '') {
                return new ClaudeGenerator(
                    new AnthropicClient(apiKey: $key, requestOptions: ['timeout' => (float) $config->get('providers.content.anthropic.timeout', 60)]),
                    (string) $config->get('providers.content.anthropic.model', 'claude-opus-5'),
                );
            }

            return $app->make(TemplateGenerator::class);
        });

        $this->app->bind(Notifier::class, function (Application $app): Notifier {
            $config = $app->make('config');

            return match ((string) $config->get('providers.whatsapp.provider', 'null')) {
                'dialog360' => new Dialog360Notifier(
                    (string) $config->get('providers.whatsapp.dialog360.key', ''),
                    (string) $config->get('providers.whatsapp.dialog360.base_url', 'https://waba-v2.360dialog.io'),
                ),
                'file' => new FileNotifier,
                default => new NullNotifier,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // No silent N+1 queries outside production (spec §19).
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->app->make(ThemeRegistry::class)->registerViewNamespaces();

        // MAIL_MAILER=file: messages land in storage/app/private/mail-sink (dev, staging, E2E).
        Mail::extend('file', fn (array $config): FileTransport => new FileTransport);

        // Sign-in abuse limits per IP (spec §17); per-identifier limits live in OtpService.
        RateLimiter::for('otp-send', fn (Request $request): Limit => Limit::perMinutes(10, 10)->by((string) $request->ip()));
        RateLimiter::for('otp-verify', fn (Request $request): Limit => Limit::perMinutes(10, 30)->by((string) $request->ip()));
    }
}
