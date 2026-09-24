# AgentSites — Laravel application

The application half of the platform; the runtime (Docker Compose, Caddy, scripts) and all
documentation live one directory up. Start with `../README.md`, `../docs/DISCOVERY.md` and
`../docs/DECISIONS.md`.

Configuration comes from the single `../.env` (linked here as `.env` by `make env`).
Platform-specific entry points: `config/platform.php`, `config/plans.php`,
`config/themes.php`, `config/reserved_slugs.php`, `config/tenant-defaults.php`,
`database/schemas/tenant-config.schema.json`, `app/Platform/Hosts.php`,
`php artisan platform:health`.
