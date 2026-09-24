# DISCOVERY.md — Phase 0

**Status: partial.** Everything here that comes from the repositories is verified. The server
inventory (§3.1), the DNS check (§6) and the pre-platform backup (§3.4) are **pending**: the
build environment for this session has no SSH access to the production server and its network
policy blocks DNS and HTTPS lookups of the base domain. Two read-only scripts close that gap
in one sitting on the server:

```sh
make discover BASE=<base-domain>      # → discovery-<date>.md (paste its findings into §1 and §4 below)
make pre-platform-backup              # → /root/backups/pre-platform-<date>/ + off-server copy (see script header)
```

## 1. Server inventory (§3.1) — PENDING

Server address: as given in the build spec §2 (kept out of this repository — see §2 for the
discrepancy). Fill from `discovery-<date>.md`:

| Item | Finding |
| --- | --- |
| OS / kernel | _pending_ |
| CPU / RAM / disk | _pending_ |
| Web server(s) on 80/443 | _pending_ (expected: nginx, see §2) |
| PHP / Node / Python / DB / Redis versions | _pending_ |
| Mail | _pending_ |
| Listening ports and services | _pending_ |
| vhosts | _pending_ |
| Certificates (issuer, expiry) | _pending_ |
| Cron / timers | _pending_ |
| Firewall | _pending_ |
| Backups in place | _pending_ |
| Docker available | _pending_ |

## 2. Existing platform (§3.2) — from the `Taskeen-Capital` repository (verified)

- **Product:** "BENTLA Homes" — a real-estate marketing site plus WhatsApp campaign/CRM
  tooling for the same business. Evidence that it serves the current base domain's apex (and
  `www`): the site's `<title>`/OpenGraph tags and 40 files in that repository hardcode the domain.
- **Stack:** React 18 + Vite static build served by **nginx** from `/var/www/taskeen-capital`
  (`server_name _;` catch-all on port 80 in the deploy script; the TLS block, if any, is
  managed outside the repo — inventory will tell); **Express** portal backend (default port 5100);
  **FastAPI** insights backend under `/opt/taskeen-insights` on port 8000 (systemd service +
  two timers, daily 04:00 and weekly Sunday 03:00 Asia/Dubai); **OpenWA** WhatsApp gateway under
  `/opt/taskeen-openwa` on port 3010 (pm2, headless Chromium, paired sessions on disk);
  **Supabase** (hosted Postgres + auth); Python scrapers/pipelines; Android and iOS apps.
- **Deploy method:** rsync from a laptop (`deploy.sh`, `deploy-openwa.sh`, `deploy-insights.sh`);
  `deploy.sh` regenerates the nginx site config on every run.
- **Dependencies on ports 80/443:** the static site and two nginx `location` blocks —
  `/openwa-gateway/` → `127.0.0.1:3010` and `/api/insights/` → `127.0.0.1:8000`. The mobile
  apps and the WhatsApp portal call those paths on the public host, so **the public paths must
  survive the cutover unchanged** (the Caddy passthrough proxies whole hosts, so they do).
- **Stateful data on the box:** pm2 WhatsApp sessions (`/opt/taskeen-openwa/.sessions`),
  insights warehouse files (`/opt/taskeen-insights`), any `.env` files — all inside the paths
  the pre-platform backup archives.
- **Server address discrepancy (must be resolved first):** the legacy deploy scripts target a
  different server (a Contabo IP in the `157.x` range) than the address in the build spec §2.
  Either the site moved and the scripts are stale, or two servers exist. `make discover` on the
  spec's server settles it (the nginx vhost list will or will not show the legacy site).
- **Not the legacy platform:** the `A2APropertyMarket` repository (WhatsApp group scraping +
  GPT parsing, last pushed Nov 2025) and the `paperclips` repository (an AI-agent control
  plane, unrelated) contain nothing that runs on the base domain.

## 3. Coexistence plan

1. **Phases 1–6 — no contact with the legacy platform.** The new platform runs in Docker
   with Caddy on alternate host ports (`CADDY_HTTP_PORT`/`CADDY_HTTPS_PORT`, defaults
   8081/8444). The wildcard certificate is issued via Cloudflare DNS-01, which needs no port
   80/443, so tenant sites are fully testable on `https://{slug}.{base}:8444`. DNS for
   `*.{base}` can point at the server at any time: on the standard ports those hosts simply hit
   the legacy nginx catch-all until cutover.
2. **Cutover (Phase 7, an explicitly approved step, ~5 minutes, reversible):**
   1. legacy nginx: change `listen 80` to `listen 127.0.0.1:8088` (one line, reload);
   2. `.env`: `CADDY_HTTP_PORT=80`, `CADDY_HTTPS_PORT=443`, `LEGACY_HOSTS="<apex>, www.<apex>"`,
      `LEGACY_UPSTREAM=host.docker.internal:8088`;
   3. `make render-caddy && docker compose up -d caddy` — Caddy now owns 80/443, serves
      `*.{base}` + custom domains, and reverse-proxies the legacy hosts unchanged (same paths,
      headers, cookies; TLS terminated by Caddy with a DNS-01 certificate).
   Rollback: revert the nginx `listen`, `docker compose stop caddy`.
3. **Apex ownership:** while the legacy site owns the apex, the S0 landing page is served from
   `app.{base}`. When the base domain changes (§4) the new apex is platform-owned and
   `LEGACY_HOSTS` keeps the old apex on the old platform until it is retired.
4. **Custom-domain on-demand TLS (Phase 4)** needs port 443 for ACME challenges; it is the one
   feature that can only be exercised after cutover (or on the staging domain if that is cut over
   first).

## 4. DNS (§6) — PENDING

Run on any machine: `dig +short NS <base-domain>`. If the answer is not two
`*.ns.cloudflare.com` hosts, the domain is not on Cloudflare and must be moved before the
wildcard certificate can be issued — the exact steps are in the Phase 0 report
(`docs/reports/2026-09-24-phase-0.md`, "Blockers").

## 5. Risks

- **Credentials committed in the legacy repository** (`H_config.md`: two third-party logins and
  an API key, in plain text, still present on `main` at the time of writing). Rotate them, then
  remove the file from history. Not fixed here: it is the legacy repository.
- **The legacy site hardcodes the base domain in 40 files.** It will not follow a
  `platform domain:change`; keep it on the old domain behind `LEGACY_HOSTS` + redirect rules,
  or retire it first.
- **Legacy bundle ships a Supabase anon key fallback** (noted in that repo's own decisions log);
  a hardening item for the legacy owners, not this platform.
- **Unknown TLS setup on the legacy host** (the deploy script only writes a port-80 vhost). If
  certbot renews via HTTP-01 on port 80 today, the cutover must take those hosts' certificates
  over in Caddy (DNS-01), which the passthrough block already does.
- **Two candidate servers** (see §2). Bootstrapping on the wrong one wastes a day; discovery first.
- **Alternate ports until cutover** delay real-world testing of custom domains (§3.4 above).

## 6. Proposed deviations from §5 / §7 / §11 (need approval)

**D1 — Laravel 13.x instead of Laravel 11.** Laravel 11 stopped receiving security fixes in
March 2026 and Laravel 12 stopped receiving bug fixes in August 2026; starting a new platform
on either means an immediate major upgrade. Laravel 13 (current, requires PHP ≥ 8.3 — the
spec's PHP version) is the boring choice today, and the skeleton is generated from its
official tag (`v13.10.1`). Nothing in the architecture (§8–§16) depends on the major version.

**D2 — Alternate host ports until the cutover is approved.** §3 forbids touching the legacy
platform during discovery, and §11 has Caddy own 80/443; the two are reconciled by running
Caddy on 8081/8444 with DNS-01 certificates (no port 80 needed) until the one approved cutover
described in §3 above. Zero risk to the legacy platform during Phases 1–6.

**D3 — On-demand TLS `ask` served by a loopback listener inside the Caddy container.** §11
names `http://app:8080/internal/tls/allow`, but php-fpm speaks FastCGI, not HTTP; adding an HTTP
server to the app container just for this is an extra process to run and secure. Instead Caddy
asks `http://127.0.0.1:9080/internal/tls/allow` on a listener bound to loopback inside its own
container, which fastcgi-proxies that one path to the app. Public site blocks answer 404 for
`/internal/*`. Same guarantee (reachable only from the Caddy container), one fewer moving part.

**D4 — `lang/` instead of `resources/lang/`.** Laravel ≥ 9 keeps translations in `lang/` at
the project root; `resources/lang/` would need a custom path. No functional difference.

**D5 — Repository location.** The code lives in `agentsites/` inside the `paperclips`
repository (the only one this session may push to). It should move to a private, dedicated
repository (`git subtree split -P agentsites` preserves history); the `Taskeen-Capital`
repository is not a candidate because its 40 hardcoded domain references fail the §4 gate.
