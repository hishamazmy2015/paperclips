# THEMES.md — the three V1 themes and how a theme works

Spec §10. One codebase renders every tenant site; a theme is a folder of Blade views plus one
Tailwind entry, selected per tenant by `tenants.theme_key`. All three ship in
`app/resources/themes/` and are discovered by `ThemeRegistry` from their `manifest.json`.

| Theme | Layout | Listing cards | Dark mode | Fonts |
| --- | --- | --- | --- | --- |
| **atlas** | full-bleed hero (photo or gradient), sections in config order | grid cards (`grid` variant) | yes (`dark_mode` honoured) | Inter Variable / IBM Plex Sans Arabic |
| **marina** | split hero (photo one side, text the other), horizontal scroll rows | horizontal row cards (`row` variant) | no (`dark_capable: false`) | Inter Variable / IBM Plex Sans Arabic |
| **palm** | editorial: centred display headline, rules, gold accents, **testimonials right after the hero** | editorial cards (`editorial` variant) | no | Inter Variable / IBM Plex Sans Arabic |

Every theme renders the same eight pages (`home`, `listings`, `listing`, `about`, `area`,
`contact`, `404`, `paused`) and the same twelve sections (`hero`, `featured`, `about`, `areas`,
`testimonials`, `services`, `stats`, `cta`, `contact`, `map`, `instagram`, `blog`), takes the
same six palettes (`sand`, `navy`, `emerald`, `charcoal`, `rose`, `gold`, plus `custom` from a
logo), and is RTL-correct in Arabic (logical properties only: `start-*`, `end-*`, `ms-*`, `ps-*`).

## Manifest

```json
{
  "key": "marina", "name": "Marina", "version": "1.0.0",
  "description": "Split hero (photo left, text right), horizontal listing cards, light.",
  "rtl": true, "dark_capable": false,
  "pages": ["home", "listings", "listing", "about", "area", "contact", "404", "paused"],
  "sections": ["hero", "featured", "about", "areas", "testimonials", "services", "stats", "cta", "contact", "map", "instagram", "blog"],
  "palettes": ["sand", "navy", "emerald", "charcoal", "rose", "gold"],
  "fonts": { "latin": "Inter Variable", "arabic": "IBM Plex Sans Arabic" },
  "preview": "preview.svg"
}
```

`dark_capable` is the only behavioural flag: `SiteContext::darkMode()` returns the tenant's
`branding.dark_mode` only when the manifest allows it (DECISIONS #53). `pages` and `sections`
are what `tests/Unit/ThemeManifestsTest` checks against the views on disk, so a theme cannot
claim a page it does not ship.

## How a page is rendered

1. `ResolveTenant` finds the tenant by host, `SitePageCache` answers from the full-page cache
   when it can (see ADD-SITE.md → "Regenerating"), otherwise the site controller runs.
2. `SiteRenderer` builds a `SiteContext` (merged config, locale, palette, sections in the
   tenant's order, URL helpers, SEO helpers) and renders `theme-{key}::{page}`.
3. The page wraps itself in the shared `<x-site.layout>` (`resources/views/components/site/`):
   `<html lang dir>`, the theme stylesheet, palette CSS variables, canonical + hreflang
   (`ar`, `en`, `x-default`), Open Graph / Twitter tags, JSON-LD when the page passes one,
   the `noindex` robots meta when the site is not indexable, header, sticky WhatsApp bar,
   footer with licence / BRN / "Website by".
4. Shared components every theme uses: `x-site.listing-card` (three variants), `x-site.section-heading`
   (`level="1"` on the page heading — every page has exactly one `h1`), `x-site.header`,
   `x-site.footer`, `x-site.whatsapp-bar`.

The home page walks `$site->sections` in the tenant's configured order and includes
`theme-{key}::sections.{key}` when the view exists; palm additionally includes `testimonials`
immediately after `hero` whatever the order says (DECISIONS #54).

## CSS

One Tailwind v4 entry per theme (`resources/themes/{key}/theme.css`, listed in
`vite.config.js`), `@source`-scoped to that theme's views plus the shared site components, so a
class only used by palm never reaches atlas's stylesheet. Fonts are self-hosted through
`@fontsource` (no third-party requests on tenant sites, spec §16); the layout preloads the
locale's primary font files (`SiteContext::fontPreloads()`, read from the Vite manifest) so the
first paint already has them and nothing shifts. Palette contrast (every text pair ≥ 4.5:1) is a
unit test; a theme that uses the accent as text darkens it the way palm does (`color-mix()`). Palette colours are CSS
variables set on `<html>` from the tenant config (`--c-primary`, `--c-secondary`, `--c-accent`,
`--c-surface`, `--c-ink`, `--c-muted`, `--c-line`), so the same stylesheet serves every palette.

After changing a view or a stylesheet on a running platform, run
`php artisan platform:site:regenerate --all` (deploy.sh does) so cached pages are rebuilt.

## The wizard and the landing page

The onboarding theme cards (`app/Livewire/Onboarding/Wizard`) render each theme's card with the
agent's own name, photo and palette; the previews on the landing page are
`public/themes/{key}-preview.svg`. A theme is selectable as soon as its manifest is installed.

## Quality gates (spec §10, §19)

`npm run lighthouse` (`tests/E2E/lighthouse.mjs`) runs Lighthouse on a mobile profile against the
three gate sites `tests/E2E/prepare.sh` creates (`lh-atlas`, `lh-marina`, `lh-palm`) in both
locales and fails below **performance 90 / accessibility 95 / SEO 95**. The Playwright file
`tests/E2E/themes.spec.js` checks each theme's layout, both locales, every page, the section order,
the SEO surface and the full-page cache in a 375×667 Chromium. CI runs both after the Pest suite.

## Adding a theme

1. Copy `resources/themes/atlas` to `resources/themes/{key}`, edit `manifest.json`.
2. Keep the eight pages and twelve sections; use the shared components and only logical CSS
   properties; test in Arabic first.
3. Add the `theme.css` entry to `vite.config.js` and a `public/themes/{key}-preview.svg`.
4. `tests/Unit/ThemeManifestsTest` and `tests/Feature/Site/ThemesTest` pick the theme up from the
   registry; add the key to `tests/E2E/prepare.sh` and `tests/E2E/lighthouse.mjs` so the gates cover it.
