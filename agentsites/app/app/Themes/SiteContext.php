<?php

declare(strict_types=1);

namespace App\Themes;

use App\Models\Listing;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Everything a theme needs to render a page for one tenant in one locale (spec §9, §10, §16):
 * merged config, locale/direction, palette variables, enabled sections, contact links, URLs.
 */
final class SiteContext
{
    /** @var list<string> */
    public readonly array $areas;

    /** @var list<string> */
    public readonly array $sections;

    /** @var array<string, string> */
    public readonly array $palette;

    public readonly string $otherLocale;

    public readonly string $dir;

    public readonly string $name;

    /**
     * @param  array<string, mixed>  $config  merged config
     * @param  array<string, mixed>  $manifest  theme manifest
     */
    private function __construct(
        public readonly Tenant $tenant,
        public readonly array $config,
        public readonly string $locale,
        public readonly array $manifest,
        public readonly string $cssEntry,
    ) {
        $this->dir = $locale === 'ar' ? 'rtl' : 'ltr';
        $this->otherLocale = $locale === 'ar' ? 'en' : 'ar';
        $this->name = (string) ($config['identity']['display_name'] ?? $tenant->slug);
        $this->areas = array_values(array_map('strval', (array) ($config['content']['service_areas'] ?? [])));

        $enabled = [];
        foreach ((array) ($config['content']['sections'] ?? []) as $section) {
            if (is_array($section) && ($section['enabled'] ?? false) === true) {
                $enabled[] = (string) $section['key'];
            }
        }
        $this->sections = $enabled;
        $this->palette = Palettes::cssVariables((string) ($config['branding']['palette'] ?? 'sand'), (array) ($config['branding']['custom_palette'] ?? []));
    }

    public static function for(Tenant $tenant, string $locale): self
    {
        $registry = app(ThemeRegistry::class);

        return new self($tenant, $tenant->mergedConfig(), $locale, $registry->manifest($tenant->theme_key), $registry->cssEntry($tenant->theme_key));
    }

    // ── text helpers ─────────────────────────────────────────────────────

    /** A {en, ar} object → the text for this locale, falling back to the other one. */
    public function t(mixed $localized, string $fallback = ''): string
    {
        if (is_string($localized)) {
            return $localized;
        }
        if (! is_array($localized)) {
            return $fallback;
        }
        $text = (string) ($localized[$this->locale] ?? '');
        if ($text === '') {
            $text = (string) ($localized[$this->otherLocale] ?? '');
        }

        return $text !== '' ? $text : $fallback;
    }

    public function tagline(): string
    {
        return $this->t($this->config['identity']['tagline'] ?? null);
    }

    public function bio(): string
    {
        return $this->t($this->config['identity']['bio'] ?? null);
    }

    public function about(): string
    {
        return $this->t($this->config['content']['about'] ?? null, $this->bio());
    }

    public function metaDescription(): string
    {
        return Str::limit($this->t($this->config['seo']['meta_description'] ?? null, $this->tagline()), 160, '');
    }

    public function agency(): string
    {
        return (string) ($this->config['identity']['agency_name'] ?? '');
    }

    /** @return list<array{title: string, text: string, icon: string}> */
    public function whyMe(): array
    {
        $items = [];
        foreach ((array) ($this->config['content']['why_me'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $items[] = ['title' => $this->t($item['title'] ?? null), 'text' => $this->t($item['text'] ?? null), 'icon' => (string) ($item['icon'] ?? 'star')];
        }

        return $items;
    }

    // ── contact ──────────────────────────────────────────────────────────

    public function whatsapp(): string
    {
        return (string) ($this->config['contact']['whatsapp'] ?? '');
    }

    public function whatsappUrl(?string $text = null): string
    {
        $number = ltrim($this->whatsapp(), '+');
        $text ??= __('site.whatsapp_default', ['name' => $this->name]);

        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }

    public function phone(): string
    {
        return (string) ($this->config['contact']['phone'] ?? $this->whatsapp());
    }

    public function email(): string
    {
        return (string) ($this->config['contact']['email'] ?? '');
    }

    /** @return array<string, string> */
    public function socials(): array
    {
        return array_filter(array_map('strval', (array) ($this->config['contact']['socials'] ?? [])));
    }

    // ── urls ─────────────────────────────────────────────────────────────

    public function url(string $path = ''): string
    {
        return '/'.$this->locale.($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    public function switchLocaleUrl(string $currentPath): string
    {
        $path = preg_replace('#^/(ar|en)(/|$)#', '/', $currentPath) ?? '/';

        return '/'.$this->otherLocale.($path === '/' ? '' : rtrim($path, '/'));
    }

    public function areaUrl(string $area): string
    {
        return $this->url('areas/'.Str::slug($area));
    }

    public function canonical(string $path = ''): string
    {
        return 'https://'.$this->tenant->primaryHost().$this->url($path);
    }

    // ── presentation ─────────────────────────────────────────────────────

    public function price(float|int|string $amount, string $currency = 'AED'): string
    {
        $formatted = number_format((float) $amount, 0, '.', ',');

        return $this->locale === 'ar' ? $formatted.' '.__('site.currency.'.$currency) : $currency.' '.$formatted;
    }

    public function noindex(): bool
    {
        return $this->tenant->isDraft() || (bool) ($this->config['seo']['noindex'] ?? false);
    }

    public function title(string $pageTitle = ''): string
    {
        $base = $this->agency() !== '' ? $this->name.' — '.$this->agency() : $this->name;

        return $pageTitle === '' ? $base : $pageTitle.' | '.$base;
    }

    public function hasSection(string $key): bool
    {
        return in_array($key, $this->sections, true);
    }

    /**
     * schema.org Offer for a real listing (spec §16). Never called for demo listings (§14).
     *
     * @return array<string, mixed>
     */
    public function offerJsonLd(Listing $listing): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Offer',
            'name' => $listing->title($this->locale),
            'url' => $this->canonical('listings/'.$listing->ref),
            'price' => (string) $listing->price,
            'priceCurrency' => $listing->currency,
            'availability' => 'https://schema.org/InStock',
            'seller' => ['@type' => 'RealEstateAgent', 'name' => $this->name],
        ];
    }

    public function darkMode(): string
    {
        return (string) ($this->config['branding']['dark_mode'] ?? 'auto');
    }
}
