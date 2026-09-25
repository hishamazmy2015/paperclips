<?php

declare(strict_types=1);

namespace App\Livewire\Onboarding;

use App\Media\ImageProcessor;
use App\Media\MediaStore;
use App\Models\Tenant;
use App\Platform\EventLog;
use App\Platform\Hosts;
use App\Provisioning\Exceptions\CannotPublish;
use App\Provisioning\Exceptions\SlugUnavailable;
use App\Provisioning\PhoneNormalizer;
use App\Provisioning\ProvisionTenant;
use App\Provisioning\PublishTenant;
use App\Provisioning\Slugs;
use App\Provisioning\TenantConfig;
use App\Provisioning\TenantLifecycle;
use App\Themes\Palettes;
use App\Themes\ThemeRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Onboarding S2–S4 (spec §13): one screen per step, autosave on every change into the draft
 * tenant created at sign-in, back allowed, progress 1/3 → 3/3. Typed fields: name, WhatsApp,
 * agency, licence (optional), subdomain (prefilled) — areas are chips. Everything the agent
 * changes is a data change on the tenant row (goal G1); the preview iframe shows the real site.
 */
#[Layout('components.layouts.app')]
final class Wizard extends Component
{
    use WithFileUploads;

    public const STEPS = 3;

    /** 0 = "resume where I left off" (no ?step in the URL). */
    #[Url(as: 'step')]
    public int $step = 0;

    // S2 — about you
    public string $name = '';

    public string $whatsapp = '';

    /** '' | ok | invalid */
    public string $whatsappState = '';

    public string $agency = '';

    public string $license = '';

    public ?TemporaryUploadedFile $photo = null;

    public string $photoUrl = '';

    public string $photoError = '';

    // S3 — your website
    public string $slug = '';

    /** '' | available | taken | reserved | invalid */
    public string $slugState = '';

    /** @var list<string> */
    public array $suggestions = [];

    public string $theme = 'atlas';

    public string $palette = 'sand';

    public ?TemporaryUploadedFile $logo = null;

    public string $logoUrl = '';

    public string $logoError = '';

    /** @var array<string, string> */
    public array $customPalette = [];

    // S4 — go live
    /** @var list<string> */
    public array $areas = [];

    public string $publishError = '';

    public function mount(): void
    {
        $tenant = $this->tenant();
        if ($tenant->isLive()) {
            $this->redirectRoute('onboarding.success');

            return;
        }

        $config = $tenant->mergedConfig();
        $this->name = trim((string) ($tenant->config['identity']['display_name'] ?? ''));
        if ($this->name === Auth::user()?->name && (bool) preg_match('/^Agent$|@/', $this->name)) {
            $this->name = '';
        }
        $this->whatsapp = (string) ($tenant->config['contact']['whatsapp'] ?? '');
        $this->whatsappState = $this->whatsapp !== '' ? 'ok' : '';
        $this->agency = (string) ($config['identity']['agency_name'] ?? '');
        $this->license = (string) ($config['identity']['license_no'] ?? '');
        $this->photoUrl = (string) ($config['identity']['photo'] ?? '');
        $this->logoUrl = (string) ($config['identity']['logo'] ?? '');
        $this->slug = $tenant->slug;
        $this->slugState = 'available';
        $this->theme = $tenant->theme_key;
        $this->palette = (string) ($config['branding']['palette'] ?? 'sand');
        $this->customPalette = array_map('strval', (array) ($config['branding']['custom_palette'] ?? []));
        $this->areas = array_values(array_map('strval', (array) ($config['content']['service_areas'] ?? [])));

        // resume at the last step reached (spec §13), never beyond it
        $reached = max(1, min(self::STEPS, $tenant->onboarding_step));
        $this->step = max(1, min($reached, $this->step > 0 ? $this->step : $reached));
        $this->viewed();
    }

    // ── autosave (spec §13: on every change) ─────────────────────────────

    public function updated(string $property): void
    {
        match ($property) {
            'name' => $this->saveName(),
            'whatsapp' => $this->saveWhatsapp(),
            'agency' => $this->saveIdentity('agency_name', $this->agency, refresh: true),
            'license' => $this->saveIdentity('license_no', $this->license),
            'slug' => $this->checkSlug(),
            'photo' => $this->savePhoto(),
            'logo' => $this->saveLogo(),
            default => null,
        };
    }

    public function checkSlug(): void
    {
        $tenant = $this->tenant();
        $slug = strtolower(trim($this->slug));
        $this->slug = $slug;
        $this->suggestions = [];

        if ($slug === $tenant->slug) {
            $this->slugState = 'available';

            return;
        }
        $reason = app(Slugs::class)->reason($slug);
        $this->slugState = $reason ?? 'available';
        if ($reason !== null) {
            $this->suggestions = app(Slugs::class)->suggest($slug !== '' ? $slug : $this->name);
        }
        $this->event('slug.checked', ['available' => $reason === null, 'slug' => $slug]);
    }

    public function useSuggestion(string $slug): void
    {
        $this->slug = $slug;
        $this->checkSlug();
    }

    public function selectTheme(string $key): void
    {
        $registry = app(ThemeRegistry::class);
        if (! in_array($key, $registry->installed(), true)) {
            return;
        }
        $tenant = $this->tenant();
        $this->theme = $key;
        if ($tenant->theme_key !== $key) {
            $tenant->theme_key = $key;
            $tenant->save();
            app(TenantConfig::class)->save($tenant, $tenant->config ?? []); // bumps the version, purges the host cache
        }
        $this->event('theme.selected', ['theme' => $key]);
    }

    public function selectPalette(string $key): void
    {
        /** @var list<string> $palettes */
        $palettes = config('themes.palettes', []);
        if (! in_array($key, $palettes, true)) {
            return;
        }
        $this->palette = $key;
        $this->customPalette = [];
        $this->saveConfig(function (array &$stored) use ($key): void {
            data_set($stored, 'branding.palette', $key);
            unset($stored['branding']['custom_palette']);
        });
    }

    public function toggleArea(string $area): void
    {
        $area = trim($area);
        /** @var list<string> $known */
        $known = config('onboarding.areas', []);
        if (! in_array($area, $known, true) && ! in_array($area, $this->areas, true)) {
            return;
        }
        $this->areas = in_array($area, $this->areas, true)
            ? array_values(array_filter($this->areas, static fn (string $a): bool => $a !== $area))
            : [...$this->areas, $area];
        $areas = $this->areas;
        $this->saveConfig(static function (array &$stored) use ($areas): void {
            data_set($stored, 'content.service_areas', $areas);
        });
        app(ProvisionTenant::class)->refreshTemplateContent($this->tenant());
    }

    // ── navigation ────────────────────────────────────────────────────────

    public function next(): void
    {
        if (! $this->stepComplete()) {
            return;
        }
        $tenant = $this->tenant();
        if ($this->step === 2 && $this->slug !== $tenant->slug) {
            try {
                app(TenantLifecycle::class)->changeDraftSlug($tenant, $this->slug);
            } catch (SlugUnavailable $e) {
                $this->slugState = $e->reason;
                $this->suggestions = $e->suggestions;

                return;
            }
        }

        $this->event('step.completed', ['n' => $this->step]);
        $this->step = min(self::STEPS, $this->step + 1);
        if ($tenant->onboarding_step < $this->step) {
            $tenant->onboarding_step = $this->step;
            $tenant->save();
        }
        $this->viewed();
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->viewed();
    }

    public function publish(): void
    {
        $tenant = $this->tenant();
        $this->publishError = '';
        try {
            $locale = app()->getLocale();
            $this->saveConfig(static function (array &$stored) use ($locale): void {
                data_set($stored, 'locale.default', $locale);
            });
            app(PublishTenant::class)->handle($tenant->refresh());
            app(ProvisionTenant::class)->scheduleAiContent($tenant);
        } catch (CannotPublish $e) {
            $this->publishError = __('platform.wizard.cannot_publish');
            $this->step = 1;

            return;
        }

        $this->redirectRoute('onboarding.success');
    }

    public function finishLater(): void
    {
        $this->redirectRoute('home');
    }

    public function render(): View
    {
        $tenant = $this->tenant();
        $registry = app(ThemeRegistry::class);
        $installed = $registry->installed();
        $themes = [];
        /** @var array<string, array<string, mixed>> $configured */
        $configured = config('themes.themes', []);
        foreach ($configured as $key => $theme) {
            $themes[$key] = ['name' => (string) ($theme['name'] ?? $key), 'description' => (string) ($theme['description'] ?? ''), 'installed' => in_array($key, $installed, true)];
        }
        $titles = [1 => __('platform.wizard.about_you'), 2 => __('platform.wizard.your_website'), 3 => __('platform.wizard.go_live')];

        return view('livewire.onboarding.wizard', [
            'tenant' => $tenant,
            'themes' => $themes,
            'palettes' => Palettes::all(),
            'paletteVars' => Palettes::cssVariables($this->palette, $this->customPalette),
            'areaOptions' => array_values(array_unique([...(array) config('onboarding.areas', []), ...$this->areas])),
            'brokerages' => (array) config('onboarding.brokerages', []),
            'siteHost' => $tenant->subdomainHost(),
            'base' => Hosts::base(),
            'previewUrl' => Hosts::browserUrl($tenant->subdomainHost(), '/'.app()->getLocale().'?preview='.$tenant->previewToken().'&v='.$tenant->config_version),
            'canContinue' => $this->stepComplete(),
            'titles' => $titles,
        ])->title($titles[$this->step].' — '.__('platform.wizard.progress', ['step' => $this->step, 'total' => self::STEPS]));
    }

    // ── internals ─────────────────────────────────────────────────────────

    private function tenant(): Tenant
    {
        $user = Auth::user();
        $tenant = Tenant::query()->where('account_id', $user?->getAttribute('account_id'))->orderBy('id')->first();
        if ($tenant === null) {
            abort(404);
        }

        return $tenant;
    }

    private function stepComplete(): bool
    {
        return match ($this->step) {
            1 => mb_strlen(trim($this->name)) >= 2 && $this->whatsappState === 'ok',
            2 => $this->slugState === 'available' && $this->theme !== '',
            default => true,
        };
    }

    private function saveName(): void
    {
        $name = trim($this->name);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            return;
        }
        $this->saveConfig(static function (array &$stored) use ($name): void {
            data_set($stored, 'identity.display_name', $name);
        });
        $user = Auth::user();
        if ($user !== null) {
            $user->setAttribute('name', $name);
            $user->save();
        }
        app(ProvisionTenant::class)->refreshTemplateContent($this->tenant());
    }

    private function saveWhatsapp(): void
    {
        $normalized = app(PhoneNormalizer::class)->normalize($this->whatsapp);
        if ($normalized === null) {
            $this->whatsappState = trim($this->whatsapp) === '' ? '' : 'invalid';

            return;
        }
        $this->whatsapp = $normalized;
        $this->whatsappState = 'ok';
        $this->saveConfig(static function (array &$stored) use ($normalized): void {
            data_set($stored, 'contact.whatsapp', $normalized);
        });
    }

    private function saveIdentity(string $key, string $value, bool $refresh = false): void
    {
        $value = mb_substr(trim($value), 0, 120);
        $this->saveConfig(static function (array &$stored) use ($key, $value): void {
            if ($value === '') {
                unset($stored['identity'][$key]);
            } else {
                data_set($stored, 'identity.'.$key, $value);
            }
        });
        if ($refresh) {
            app(ProvisionTenant::class)->refreshTemplateContent($this->tenant());
        }
    }

    private function savePhoto(): void
    {
        $this->photoError = '';
        if ($this->photo === null) {
            return;
        }
        try {
            $binary = (string) file_get_contents($this->photo->getRealPath());
            $webp = app(ImageProcessor::class)->squareWebp($binary, 512);
            $media = app(MediaStore::class)->put($this->tenant(), $webp, 'photo');
            $path = MediaStore::publicPath($media);
            $this->saveConfig(static function (array &$stored) use ($path): void {
                data_set($stored, 'identity.photo', $path);
            });
            $this->photoUrl = $path;
        } catch (Throwable $e) {
            $this->photoError = __('platform.wizard.image_rejected');
        } finally {
            $this->photo?->delete();
            $this->photo = null;
        }
    }

    private function saveLogo(): void
    {
        $this->logoError = '';
        if ($this->logo === null) {
            return;
        }
        try {
            $processor = app(ImageProcessor::class);
            $binary = (string) file_get_contents($this->logo->getRealPath());
            $webp = $processor->fitWebp($binary, 640);
            $media = app(MediaStore::class)->put($this->tenant(), $webp, 'logo');
            $path = MediaStore::publicPath($media);
            $color = $processor->dominantColor($binary);
            $custom = $color !== null ? ImageProcessor::paletteFrom($color) : [];
            $this->saveConfig(static function (array &$stored) use ($path, $custom): void {
                data_set($stored, 'identity.logo', $path);
                if ($custom !== []) {
                    data_set($stored, 'branding.palette', 'custom');
                    data_set($stored, 'branding.custom_palette', $custom);
                }
            });
            $this->logoUrl = $path;
            if ($custom !== []) {
                $this->palette = 'custom';
                $this->customPalette = $custom;
            }
        } catch (Throwable $e) {
            $this->logoError = __('platform.wizard.image_rejected');
        } finally {
            $this->logo?->delete();
            $this->logo = null;
        }
    }

    /** @param  callable(array<string, mixed>&): void  $mutate */
    private function saveConfig(callable $mutate): void
    {
        $tenant = $this->tenant();
        /** @var array<string, mixed> $stored */
        $stored = $tenant->config ?? [];
        $mutate($stored);
        app(TenantConfig::class)->save($tenant, $stored);
    }

    private function viewed(): void
    {
        $this->event('step.viewed', ['n' => $this->step]);
    }

    /** @param  array<string, mixed>  $properties */
    private function event(string $name, array $properties): void
    {
        $tenant = $this->tenant();
        app(EventLog::class)->record($name, $properties, tenant: $tenant, account: $tenant->account, user: Auth::user());
    }
}
