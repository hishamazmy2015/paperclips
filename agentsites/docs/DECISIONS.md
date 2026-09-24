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
