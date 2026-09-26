# ADD-SITE.md — creating, publishing and managing a site

Every operation below is a **data change** (goal G1): no deploy, no config file, no restart.
A new site is reachable the moment the command returns; the wildcard certificate already covers
it. `{base}` is `PLATFORM_BASE_DOMAIN` from `.env`.

## The one-liner (acceptance test A1)

```sh
docker compose exec -T --user www-data app php artisan platform:site:create \
  --name "Ahmed Al Falasi" --whatsapp +971501234567
```

Result: a **live** site at `https://ahmed-al-falasi.{base}/` with a bilingual (ar/en) home
page, generated tagline/bio/about/why-me copy, demo listings, and an account + owner user for the
agent (trial plan, 14 days). Re-running the same command returns the same site and creates nothing
(idempotency key: account + slug).

`make site NAME="Ahmed Al Falasi" WHATSAPP=+971501234567` does the same from the host.

## Options

| Option | Meaning |
| --- | --- |
| `--slug=ahmed` | Wanted subdomain label. Derived from the name when omitted (Arabic is transliterated). If taken, the command fails with three suggestions. |
| `--theme=atlas` | Theme key from `config/themes.php` (atlas today; marina and palm in Phase 3). |
| `--locale=ar` | Default site locale; both `ar` and `en` stay enabled. |
| `--agency=`, `--license=`, `--email=`, `--areas="Downtown,Marina"` | Identity, contact and service areas. |
| `--account=<id>` | Attach to an existing account (brokerage). Otherwise the agent's account is found by phone/email or created. |
| `--draft` | Keep the site unpublished. The output includes a `preview_url` (signed token) for the owner. |
| `--config=file.json` | Everything above plus any schema field, in the `samples/agent.json` shape. `-` reads stdin. |
| `--json` | Machine-readable output (all `platform:*` commands). |

Phone numbers are normalised to E.164 with the UAE as the default region (`050 123 4567`,
`00971…`, Arabic-Indic digits all work). Invalid numbers, unknown themes and schema violations
are rejected before anything is written.

## Lifecycle

```sh
php artisan platform:site:publish  <slug>              # draft → live in ≤ 5 s; welcome WhatsApp on first publish
php artisan platform:site:suspend  <slug> --reason=…   # paused page (503 + Retry-After, noindex)
php artisan platform:site:restore  <slug>              # back to live (or draft if never published); also un-deletes
php artisan platform:site:rename   <slug> <new-slug>   # old host 301s to the new one for 90 days
php artisan platform:site:clone    <slug> --to=<slug> --name=… --whatsapp=…   # same config minus identity/contact
php artisan platform:site:delete   <slug> --reason=…   # soft delete; platform:purge removes it after 30 days
php artisan platform:site:export   <slug> [--out=f]    # config, domains, listings, testimonials, leads as JSON
php artisan platform:stats                              # tenants by status, accounts, listings, leads, events
php artisan platform:purge [--dry-run]                  # daily by the scheduler
php artisan platform:events:partitions                  # monthly by the scheduler
```

All commands are idempotent: repeating one reports `created: false` and changes nothing.

## Many sites at once: CSV import (spec §12, acceptance test A5)

```sh
php artisan platform:site:import samples/agents.csv --dry-run     # validate every row, create nothing
php artisan platform:site:import samples/agents.csv               # one live site per row, batches of 100
php artisan platform:site:import --resume=<run id>                # continue a killed run from its checkpoint
php artisan platform:import:status [<run id>]                     # progress, counters, error file
```

- Columns: `name, whatsapp` (required), `agency, license, slug, theme, areas` (`;`-separated),
  `email, locale, palette, years, languages, instagram, dark_mode, photo, tagline_en/ar,
  bio_en/ar`. Anything else the schema knows can be added through `--config` JSON per site.
- Rows run in batches through the queue (`--batch=100`); `import_runs` keeps the checkpoint
  (`last_row`), so `--resume` picks up exactly where a killed worker stopped.
- Bad rows never stop the run: they are listed in `<file>.errors.csv` (`row, slug, error`)
  and counted in `error_rows`.
- Re-importing the same file creates nothing (`existing_rows`), so the file can double as the
  source of truth for a brokerage roster.

### Generating a thousand different sites from one command

```sh
php artisan platform:site:generate --count=1000 --seed=42 --provision
# → storage/app/generated-agents-42-1000.csv, imported: 1000 live sites, each a different agent
php artisan platform:site:list --random --limit=10 --json         # sample them
```

The generator varies names (Arabic script, Arab and international Latin names), agencies,
licenses, service areas, theme (only installed themes), palette, dark mode, default locale,
languages, Instagram and years of experience; the same seed always yields the same CSV, so a
data set is reproducible. `--draft` keeps them unpublished, `--out=` chooses the file. The
Playwright suite (`npm run e2e`) uses exactly this to open generated sites in a mobile browser.

## What a site is made of

- `tenants.config` (JSONB) holds only what was set; defaults from `config/tenant-defaults.php`
  merge at read time, so a change there updates every site (spec §9).
- `domains` holds `{slug}.{base}` (primary, verified, covered by the wildcard certificate) plus
  any custom domains the agent connects (Phase 4).
- Listings marked `source=demo` show until the first real listing exists and never carry
  structured data (spec §14).
- Copy generated by `TemplateGenerator` is marked in `tenants.ai_generated_fields`; the Claude
  generator (Phase 2) replaces exactly those fields and never touches what the agent typed.

## Seeing a draft

`https://<slug>.{base}/?preview=<token>` — the token is in the `--draft` command output
(`preview_url`) and is remembered in the browser session. Everyone else gets a 404 until publish.

## Removing a site

`platform:site:delete` keeps the data 30 days (`platform:site:restore` brings it back);
`platform:purge` then hard-deletes it. The slug and host stay reserved until the purge.

## Regenerating (page cache, spec §16, acceptance test A5)

Every live site is served from a full-page cache (host + path + locale + query, 1 h, `ETag`,
`Cache-Control: public, max-age=60`; `X-Cache: HIT|MISS`). The cache is purged for one site on
every config save, publish, lifecycle change, listing, testimonial, domain or media write, so
normal operation never needs a command. A template or stylesheet change on a running platform
does (`deploy.sh` runs it):

```sh
php artisan platform:site:regenerate ahmed-al-falasi         # one site: purge + warm its main pages
php artisan platform:site:regenerate --all                    # every live site, batches of 50 through the queue
php artisan platform:site:regenerate --all --no-warm          # purge only
php artisan platform:site:regenerate --resume=3               # continue a killed --all run
php artisan platform:site:regenerate --status=3               # progress of a run
```

`--all` records a `regenerate_runs` row (last tenant id, counts) so a run survives a restart and
resumes where it stopped; warming renders every enabled locale's home, listings, about, contact
and area pages in-process through the public origin (`app.url` scheme + port), so a warmed
page is byte-for-byte what a visitor gets. Timing: see the Phase 3 report.

## Listings and feeds

See [LISTINGS.md](LISTINGS.md): `platform:listings:import {slug} {file} [--dry-run] [--no-media]`
and `platform:feeds:sync [--feed=] [--tenant=] [--due]`. Themes: [THEMES.md](THEMES.md).
