# DECISIONS.md

Every decision taken while building the platform: date, decision, alternatives, reason.
Append only; newest at the bottom. Deviations from the build spec that need approval are
also listed in `DISCOVERY.md` §6.

## 2026-09-24

1. **Code lives in `agentsites/` inside the `paperclips` repository** (branch
   `claude/sweet-ritchie-9mlloh`). Alternatives: a new private repository (preferred; needs
   creating), `Taskeen-Capital` (rejected: 40 files hardcode the base domain, so the §4 gate
   fails on day one). Reason: it is the only repository this session is allowed to push to.
   Move with `git subtree split -P agentsites` once a private repository exists.
2. **Laravel 13.10 skeleton (framework ^13.17, PHP ^8.3) instead of Laravel 11.**
   Alternatives: 11 (security fixes ended 2026-03), 12 (bug fixes ended 2026-08). Reason: 11 is
   end-of-life; 13 is the current major and matches the spec's PHP 8.3. Deviation D1, awaiting
   approval.
3. **One `.env` at the platform root, bind-mounted read-only into the app/horizon/scheduler
   containers** rather than passed through compose `env_file`/`environment`. Reason: container
   environment is frozen at creation; a mounted file lets `platform:domain:change` take effect
   with `config:cache` + a php-fpm reload, no container recreate (§4, §22.2). Caddy and Postgres
   receive only the few values they need through their environment.
4. **Caddyfile rendering with `sed` over a fixed token list** (`${PLATFORM_BASE_DOMAIN}`,
   `${LEGACY_HOSTS}`, `${LEGACY_UPSTREAM}`); secrets stay as Caddy `{env.*}` placeholders
   resolved at runtime. Alternatives: `envsubst` (needs gettext, would inline the Cloudflare
   token into the rendered file). Reason: no secret on disk outside `.env` (§22.6).
5. **On-demand TLS permission check via a loopback listener in the Caddy container**
   (`http://127.0.0.1:9080/internal/tls/allow` → php_fastcgi). Alternative: `http://app:8080` as
   written in §11 (php-fpm is not an HTTP server). Deviation D3. Issuance rate limiting (§11) will
   be enforced by the endpoint itself: Caddy ≥ 2.8 removed the `interval`/`burst` options.
6. **Apex ownership follows `LEGACY_HOSTS`.** If the apex is listed, `apex.caddy` renders
   empty and the legacy platform keeps it; the landing page is served from `app.{base}` until
   the base domain changes. Alternative: a separate flag. Reason: one source of truth.
7. **Caddy publishes `CADDY_HTTP_PORT`/`CADDY_HTTPS_PORT` (defaults 8081/8444) until the
   cutover is approved.** Reason: §3 forbids touching the legacy platform; DNS-01 needs no port
   80. Deviation D2. Custom-domain on-demand TLS (Phase 4) waits for 443.
8. **Images pinned:** `caddy:2.11.4` (+ `caddy-dns/cloudflare` via xcaddy), `php:8.3-fpm-bookworm`,
   `postgres:16-alpine`, `redis:7-alpine`, `axllent/mailpit:v1`. Reason: rebuildable from the
   repository (§22.7); bumped deliberately, never by a floating tag.
9. **Redis through the `phpredis` extension** (no predis), `maxmemory-policy noeviction`
   (queue jobs must never be evicted), AOF on.
10. **Image processing: GD with WebP + AVIF compiled into the PHP image.** Alternative: libvips
    (faster, more native dependencies). Reason: boring default; Intervention Image's driver is a
    config switch if libvips is needed later.
11. **Translations under `lang/`** (Laravel ≥ 9 default) rather than `resources/lang/` (§7).
    Deviation D4; no functional difference.
12. **The CI base-domain gate reads the domain from the GitHub Actions repository variable
    `PLATFORM_BASE_DOMAIN`** and fails loudly when it is unset. Alternative: a literal in the
    workflow file (which would itself violate §4). Reason: CI's equivalent of `.env`.
13. **Discovery and the pre-platform backup are read-only scripts the operator runs on the
    server** (`make discover`, `make pre-platform-backup`) because this build environment has
    no SSH access; their output is pasted into `DISCOVERY.md`. The backup script refuses to run
    without 2× the estimated free space and never restarts anything.
14. **`platform:health` skips the Redis probe when no Redis-backed driver is configured** (the
    test suite runs with array/sync drivers) and treats `PLATFORM_BASE_DOMAIN=example.com` as
    unset, so a copied `.env.example` fails health instead of silently "working".
15. **Session cookies stay host-scoped** (`SESSION_DOMAIN` unset) even though §4 lists cookie
    domains among derived values: sharing cookies across `app.`, `admin.` and tenant hosts would
    weaken isolation (§17). The derived value is therefore "current host".
16. **Plan prices in `config/plans.php` are placeholders** (AED, ex-VAT) until a product
    decision is recorded here; limits and features follow §14 exactly.

## 2026-09-25 (Phase 1)

17. **Tests run on PostgreSQL 16, never SQLite.** Alternatives: SQLite in memory (faster, but no
    JSONB, GIN or declarative partitioning, so migrations would need two code paths). Reason:
    production fidelity; a local `postgresql-16` package and a CI service container cost nothing.
18. **`TenantScope` throws whenever no tenant is bound, unless platform code explicitly opens a
    `TenantContext::global()` block** — stricter than §8 ("throws in non-console contexts").
    Alternatives: allow unscoped queries in console/jobs implicitly. Reason: every cross-tenant
    query is a reviewable call site; jobs and commands bind a tenant with `TenantContext::with`.
    Factories for tenant-scoped models run inside a global block (`TenantScopedFactory`).
19. **The host cache entry carries the tenant's raw attributes** (`HostCache::HostEntry.tenant`)
    so a warm request hydrates the model without a query (§11 budget p95 ≤ 2 ms). Every config
    save, status change and domain write invalidates the tenant's hosts. TTL 10 min, negative
    entries 60 s.
20. **Subdomain rows store the full host `{slug}.{base}`** as §12 literally prescribes (§4's
    "tenants store slug only" applies to the tenants table). `platform:domain:change` (Phase 7)
    rewrites them and writes the redirect rules.
21. **Slug conflicts are detected against tenants (deleted ones included), domain hosts and
    active redirect sources.** A renamed-away or deleted slug therefore stays unavailable until
    its redirect expires (90 days) or the purge runs (30 days) — nobody can take over a URL that
    still redirects.
22. **Two version numbers on a config:** `tenants.config_version` is the revision counter (+1 on
    every save, per §9), `config["_schema"]` is the schema version `ConfigMigrator` upgrades
    lazily (also §9). Reason: the spec describes both behaviours under one name.
23. **JSON Schema validation with `opis/json-schema` (draft 2020-12) and phone normalisation
    with `giggsey/libphonenumber-for-php`.** Alternatives: `justinrainbow/json-schema` (draft
    7 only), a hand-written UAE regex. Reason: the schema in §9 is draft 2020-12; libphonenumber
    is the reference implementation.
24. **Locale on tenant sites: URL segment, else the first `Accept-Language` entry the site has
    enabled, else the site's default.** `/` is a 302 (not 301) so a changed default is not stuck
    in browser caches.
25. **The on-demand TLS endpoint also checks the `Host` header is loopback** (Caddy's internal
    listener sends `127.0.0.1:9080`), in addition to Caddy answering 404 for `/internal/*` on
    public blocks — defence in depth for §11.
26. **PHPStan does not analyse `tests/`** (Pest binds `$this` in closures, which static analysis
    cannot see); tests are checked by Pint and by running them. Application code stays at level 6.
27. **Coverage is measured on `app/Tenancy`, `app/Provisioning`, `app/Domains`, `app/Billing`
    only** (phpunit.xml `<source>`), so `pest --coverage --min=90` enforces exactly the §19 gate.
28. **Theme assets are built in a throwaway `node:22-alpine` container** (`build-assets.sh`)
    during deploy; the host needs no Node.js, and fonts come from fontsource packages
    (Inter Variable, IBM Plex Sans Arabic) so no request leaves the site (§10).
29. **Demo listing images are generated SVG placeholders** (`public/demo/*.svg`) rather than
    stock photos: no licensing question, 1 KB each, replaceable per theme in Phase 3.
30. **The S0 landing page is served on the apex, `www` and `app.` hosts**; whichever of those
    Caddy routes to the platform works, so the legacy-apex period (decision 6) needs no code change.
31. **CSV import checkpoints per row in `import_runs.last_row`** and chains one queued job per
    batch (`ImportBatch`), rather than one job per row or one job for the file. Alternatives:
    Laravel job batches (heavier, no natural resume point). Reason: §12 asks for batches of 100
    with a checkpoint table; a killed worker resumes with `--resume=<id>` and never re-creates a
    site (provisioning is idempotent per account + slug anyway).
32. **Uninstalled themes are import errors, not silently replaced** (`ThemeRegistry::installed()`
    = themes that ship views). `config/themes.php` still lists marina and palm for Phase 3, so the
    sample CSV uses atlas until they exist.
33. **Synthetic agents come from a seeded generator (`platform:site:generate`)** using PHP's
    `Random\Randomizer` with Xoshiro256** so the same seed reproduces the same CSV; names mix Arabic
    script, Arab Latin and international names so slug transliteration and RTL get exercised.
34. **Playwright runs against `php artisan serve` with Chromium's `--host-resolver-rules`** mapping
    `*.example.test` to the local server, so sites are opened by host on a 375×667 mobile viewport
    like a phone would. Alternatives: rewriting `Host` headers (Chromium ignores them for
    navigation), `/etc/hosts` (not wildcard-capable). CI seeds 200 sites; locally 1000 (`E2E_SITES`).

## 2026-09-26 (Phase 2)

35. **A sign-in creates a *partial* draft tenant through the same `ProvisionTenant` path**
    (`ProvisionInput::partial`): name placeholder from Google/the email local part, WhatsApp only
    when the sign-in verified a phone. The JSON schema therefore requires only `identity`;
    `contact.whatsapp` is required **to publish** (`PublishTenant::missingForPublish`, `CannotPublish`).
    Alternatives: a separate onboarding-draft table (two write paths), creating the tenant at S2
    (autosave would have no target, against §13 S1). Reason: one write path, autosave from the
    first keystroke, and publishing still guarantees a complete site.
36. **Template copy is regenerated as the facts change during onboarding**
    (`ProvisionTenant::refreshTemplateContent`): only fields still marked `template` are rewritten,
    typed text and AI text are never touched; the Claude generator is queued once, at publish.
37. **One-time codes are HMAC-SHA256 of `identifier|code` under `APP_KEY`, one live code per
    identifier**, 6 digits, 10 min, 5 attempts, 3 sends / identifier / 10 min, 10 sends and 30
    verifications / IP / 10 min (`OtpService`). The magic link is a Laravel temporary signed route
    carrying the code row id: single use, same 10-minute life. Alternative: bcrypt per code
    (slower, no benefit at 6 digits with attempt limits).
38. **Livewire 3.8 for the wizard** (Livewire 4 exists; the spec names 3, and 3.8 supports
    Laravel 13). One full-page component, `?step=` in the URL, autosave on every change through
    `TenantConfig::save` (so the host cache is purged and the preview is always current).
39. **The preview iframe loads the real draft site by its own host with the preview token**;
    `ResolveTenant` marks such requests and `SiteHeaders` answers with
    `Content-Security-Policy: frame-ancestors 'self' <app origin>` instead of `X-Frame-Options: DENY`
    (§17). Browser-facing URLs come from `Hosts::browserUrl`, which keeps the scheme and port of
    the current request (http://…:8123 in dev, https://…:8444 before the cutover).
40. **Media: GD re-encodes every upload to WebP** (square 512 for photos, fit 640 for logos, EXIF
    dropped), stored as `{tenant_id}/{name}-{sha12}.webp` on the `media` disk and served by
    `MediaController` on the tenant host (own directory only) and the app host (owner only), with
    immutable caching. "From my logo" = dominant saturated hue → `branding.palette = custom`.
41. **Providers switch by config, never by code** (`config/providers.php`): `CONTENT_GENERATOR`
    template|claude, `WHATSAPP_PROVIDER` null|file|dialog360, `MAIL_MAILER` incl. a `file` transport;
    Turnstile is off until both keys exist and fails closed when on. The `file` sinks
    (`storage/app/private/{mail,whatsapp}-sink`) are what staging without Mailpit and the Playwright
    suite read.
42. **ClaudeGenerator uses the official `anthropic-ai/sdk`**, model `claude-opus-5` by default
    (`ANTHROPIC_MODEL`), structured JSON output (one key per field × locale), the server-side
    refusal fallback (`fallbacks: default`), and runs only inside the queued `GenerateContent` job.
43. **Abandonment reminders are two nullable timestamps on `tenants`** (`reminder_1h_sent_at`,
    `reminder_24h_sent_at`) plus `users.reminders_opted_out_at`, sent by a 15-minute scheduled job
    from `updated_at`. Alternative: a reminders table (more rows, nothing extra to show). The funnel
    numbers are a CLI report (`platform:funnel`) until the Filament admin (Phase 6) renders them.
44. **The site's default locale becomes the UI language the agent published in.** The wizard
    exposes no locale field (≤ 6 typed fields); the dashboard (Phase 4) can change it.

45. **Full-page cache = per-tenant version keys, not cache tags** (2026-09-26, Phase 3). Every
    page key folds in `page:ver:{tenant}` + `page:ver:all`; a purge is one write and works the
    same on Redis, file and array stores (tests, staging without Redis). TTL 1 h, `ETag`/304,
    `Cache-Control: public, max-age=60` (a visitor's browser may keep a page for a minute after
    an edit; the ETag revalidates after that). Only anonymous GET/HEAD on live sites are cached;
    previews, signed-in requests and the app host never are. Alternative: Redis tags (one store
    only, scans on purge).
46. **Warming happens only in a console process** (`SiteWarmer::available()`), as in-process
    kernel requests, never from a web request; the request URL uses the public origin derived
    from `app.url` (scheme + port) so warmed pages carry the same absolute asset URLs as pages
    rendered for a visitor (`Hosts::publicOrigin`). `APP_URL` is therefore set to
    `http://app.{base}:8123` in the E2E environment.
47. **`regenerate_runs` is the same checkpoint pattern as `import_runs`**: `--all` walks tenants by
    id in batches of 50 through queued jobs, records `last_tenant_id` and counts, resumes by id;
    a run is final only when completed (a failed one can be resumed).
48. **Listing media entries are `{path, variants{thumb,card,hero}, width, height, source}` or a plain
    URL string.** Variants 400 / 800 / 1600 WebP from one upload; feed and CSV photos are fetched
    and cached into the tenant directory by default (`--no-media` / "cache photos" keeps the URL).
    Alternative: a `media` table row per photo — more joins, nothing extra to show in V1.
49. **Plan limits are read from `config/plans.php`** (`PlanLimits`); the `plans` table only mirrors
    them. The listings limit counts real listings only and is enforced in the form, the importer
    and the feed sync with one message. Billing (Phase 5) changes the account's plan, nothing else.
50. **A listing that leaves a feed is hidden, not deleted** (status `hidden`, `feed_ref` kept), so a
    broken export never destroys photos or URLs; the agent can delete it. Feeds sync every 30
    minutes (`SyncDueFeeds`), credentials are an `encrypted:array` cast, filters are per agent
    (offering, price range, property types, communities).
51. **SEO surface per tenant from routes, not files**: `sitemap.xml` (real listings and areas,
    hreflang alternates, demo listings excluded) and `robots.txt` (Allow + Sitemap when live and
    indexable, `Disallow: /` otherwise). Laravel's static `public/robots.txt` was removed because
    Caddy's `file_server` (and `artisan serve`) would serve it before the route.
52. **`seo.title_pattern`** (`{name} — {agency} | {area}` by default; `{tagline}` available): empty
    parts and their separators collapse, so a solo agent gets "Name | Area", not "Name —  | Area".
53. **Dark mode only where the manifest says `dark_capable`** (atlas today). Marina and palm are
    designed light; the tenant's `dark_mode` flag is kept and ignored there.
54. **Palm is testimonials-forward by code, not by config**: the home page includes the
    testimonials section right after the hero whatever the section order says. Alternative: a
    theme-specific default order — would drift the moment the agent reorders sections.
55. **Sample listings stay only until the agent has any real listing** (hidden and sold ones count).
    Previously only *available* real listings counted, so hiding your only listing brought the
    samples back — misleading on a managed site.
56. **Every page has exactly one `h1`**: `x-site.section-heading` takes `level="1"` for the page
    heading (listings, about, area, contact). Needed for the accessibility gate and plain semantics.
57. **Lighthouse runs through the node API** (`tests/E2E/lighthouse.mjs`, `npm run lighthouse`):
    three gate sites × two locales on the mobile profile, thresholds 90 / 95 / 95, results in
    `tests/E2E/lighthouse.json`, non-zero exit on a miss; CI runs it after Playwright. Alternative:
    Lighthouse CI with its server — more moving parts for the same numbers.
58. **Accessibility and CLS fixes are structural, not per-site** (Lighthouse gate run, 2026-09-26):
    palette contrast is a unit test (`tests/Unit/PalettesContrastTest`, every text pair ≥ 4.5:1;
    sand's primary darkened from `#9a6b2f` to `#8f6229`); palm darkens the palette accent with
    `color-mix()` wherever it is text (eyebrows, prices, stars) and keeps the light accent for
    button hovers; phone links render only when the site has a phone (an empty `tel:` link has
    no accessible name); the locale's primary font files are `<link rel="preload">`ed from the
    Vite manifest so the web font is there at first paint (CLS from font swap was 0.11–0.30);
    the first featured listing image loads eagerly with `fetchpriority="high"` (it is the LCP
    element whenever the hero has no photo). The cache warmer runs through the public origin
    (#46) after a first run cached pages whose asset URLs pointed at `https://host` on an http
    dev server — the styled-page check in `tests/E2E/themes.spec.js` would have caught it in CI.
