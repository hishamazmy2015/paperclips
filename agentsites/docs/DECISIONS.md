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
