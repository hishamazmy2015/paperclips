# AgentSites — real-estate agent websites platform

One codebase, one deployment, one database. Every tenant website is a data row: creating,
editing, cloning, suspending or regenerating a site is a data change — never a deploy,
config file or restart (goal G1). An agent goes from landing page to a live HTTPS site in
under two minutes typing at most six fields (goal G2).

`§n` below refers to sections of the master build specification.

**Status: Phase 0 (discovery) — partially complete.** The latest phase report is in
`docs/reports/`; what is known about the production server and what is still pending is in
`docs/DISCOVERY.md`; every decision is in `docs/DECISIONS.md`.

## Layout (§7)

| Path | Purpose |
| --- | --- |
| `docker-compose.yml`, `docker-compose.staging.yml` | Runtime: caddy, app (php-fpm), horizon, scheduler, postgres, redis (+ mailpit on staging) |
| `.env.example` | **The only place the base domain lives** (§4) and the full credentials checklist |
| `Makefile` | `make up/down/deploy/test/backup/…` (idempotent operator entry points) |
| `infra/caddy/` | `Caddyfile.tmpl` + snippets (rendered by `infra/scripts/render-caddy.sh`), xcaddy `Dockerfile` with the Cloudflare DNS module |
| `infra/app/` | PHP 8.3 FPM image, `php.ini`, pool config |
| `infra/scripts/` | Idempotent bash: `bootstrap`, `deploy`, `backup`, `restore`, `render-caddy`, `rotate-logs`, `check-base-domain`, plus Phase 0 `discover` and `pre-platform-backup` |
| `infra/cron/` | Host crontab (backups, log hygiene) |
| `app/` | Laravel application (`app/Platform`, `config/platform.php`, `config/plans.php`, `config/themes.php`, `config/reserved_slugs.php`, `config/tenant-defaults.php`, `database/schemas/tenant-config.schema.json`, `lang/{ar,en}`) |
| `docs/` | `DISCOVERY.md`, `DECISIONS.md`, phase reports |
| `samples/` | `agents.csv`, `agent.json`, `listings.csv` |

## Run it

```sh
make env        # .env from .env.example (edit PLATFORM_BASE_DOMAIN, DB_PASSWORD, tokens) + app/.env link
make up         # build images, render the Caddyfile, start the stack on the ports in .env
make test       # pint + phpstan (level 6) + pest + the base-domain gate (§4)
make help       # everything else
```

Ports stay off 80/443 (defaults 8081/8444) until the legacy-platform cutover is approved —
see the coexistence plan in `docs/DISCOVERY.md`.

## Phase 0 on the production server (read-only)

```sh
make discover BASE=<base-domain>   # writes discovery-<date>.md (never committed) → paste into docs/DISCOVERY.md
make pre-platform-backup           # /root/backups/pre-platform-<date>/ + off-server copy (see script header)
```
