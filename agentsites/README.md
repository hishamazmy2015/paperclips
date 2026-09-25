# AgentSites — real-estate agent websites platform

One codebase, one deployment, one database. Every tenant website is a data row: creating,
editing, cloning, suspending or regenerating a site is a data change — never a deploy,
config file or restart (goal G1). An agent goes from landing page to a live HTTPS site in
under two minutes typing at most six fields (goal G2).

`§n` below refers to sections of the master build specification.

**Status: Phase 2 (Onboarding) built and tested — sign-in without passwords, the three-step
wizard with live preview, publish, success screen, reminders and funnel; Phase 0's server steps
still pending.**
The latest phase report is in `docs/reports/`; what is known about the production server
and what is still pending is in `docs/DISCOVERY.md`; every decision is in `docs/DECISIONS.md`.

```sh
php artisan platform:site:create --name "Ahmed Al Falasi" --whatsapp +971501234567
# → https://ahmed-al-falasi.{base}/  live, bilingual, demo listings, no restart (A1)

php artisan platform:site:import samples/agents.csv          # one site per CSV row, batched, resumable (A5)
php artisan platform:site:generate --count=1000 --provision  # 1000 different agents → 1000 live sites
npm run e2e                                                  # Playwright: sites by host + the whole onboarding flow (A2) on a phone-sized Chromium
```

An agent's own path: `app.{base}/start` → email code (or Google / WhatsApp code) → about you →
your website → go live → published. See `docs/ONBOARDING.md`.

## Layout (§7)

| Path | Purpose |
| --- | --- |
| `docker-compose.yml`, `docker-compose.staging.yml` | Runtime: caddy, app (php-fpm), horizon, scheduler, postgres, redis (+ mailpit on staging) |
| `.env.example` | **The only place the base domain lives** (§4) and the full credentials checklist |
| `Makefile` | `make up/deploy/test/backup/site/…` (idempotent operator entry points) |
| `infra/caddy/` | `Caddyfile.tmpl` + snippets (rendered by `infra/scripts/render-caddy.sh`), xcaddy `Dockerfile` with the Cloudflare DNS module |
| `infra/app/` | PHP 8.3 FPM image, `php.ini`, pool config |
| `infra/scripts/` | Idempotent bash: `bootstrap`, `deploy`, `build-assets`, `backup`, `restore`, `render-caddy`, `rotate-logs`, `check-base-domain`, plus Phase 0 `discover` and `pre-platform-backup` |
| `app/` | Laravel 13: `app/Tenancy` (context, scope, host cache, resolver), `app/Provisioning` (the single write path), `app/Auth` (codes, magic link, Google, Turnstile), `app/Livewire/Onboarding` (the wizard), `app/Content` (template + Claude generators), `app/Messaging` (WhatsApp notifiers), `app/Media`, `app/Themes`, `app/Http`, `app/Console/Commands/Platform` (`platform:*` CLI), 23 migrations, `resources/themes/atlas`, `lang/{ar,en}` |
| `docs/` | `DISCOVERY.md`, `DECISIONS.md`, `ADD-SITE.md`, `ONBOARDING.md`, phase reports |
| `samples/` | `agents.csv`, `agent.json`, `listings.csv` |

## Run it

```sh
make env        # .env from .env.example (edit PLATFORM_BASE_DOMAIN, DB_PASSWORD, tokens) + app/.env link
make up         # build images, render the Caddyfile, start the stack on the ports in .env
make assets     # build theme CSS (Tailwind, self-hosted fonts) in a throwaway Node container
make site NAME="Ahmed Al Falasi" WHATSAPP=+971501234567
make test       # pint + phpstan (level 6) + pest (PostgreSQL) + the base-domain gate (§4)
make help       # everything else
```

Ports stay off 80/443 (defaults 8081/8444) until the legacy-platform cutover is approved —
see the coexistence plan in `docs/DISCOVERY.md`. On a server, `infra/scripts/bootstrap.sh`
does all of the above and creates the `demo` site.

## Tests

`cd app && vendor/bin/pest` runs against a local PostgreSQL 16 (`platform_test`, user/password
`platform`, see `phpunit.xml`). Suites: `tests/Isolation` (no cross-tenant access at the model
and HTTP layers), `tests/Feature/Tenancy` (resolver, host cache, redirects, TLS allow),
`tests/Feature/Provisioning`, `tests/Feature/Site` (both locales, RTL, sections, demo
listings), `tests/Feature/Console` (the CLI, acceptance test A1), `tests/Unit`.

## Phase 0 on the production server (read-only)

```sh
make discover BASE=<base-domain>   # writes discovery-<date>.md (never committed) → paste into docs/DISCOVERY.md
make pre-platform-backup           # /root/backups/pre-platform-<date>/ + off-server copy (see script header)
```
