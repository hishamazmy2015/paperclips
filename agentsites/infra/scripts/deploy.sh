#!/usr/bin/env bash
# Zero-downtime deploy (spec §18): update source, build images, install dependencies,
# run (backward-compatible) migrations, cache config/routes/views, reload php-fpm and
# Horizon gracefully, re-render and reload Caddy, verify health. Re-runnable.
#   deploy.sh [git-ref]
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
load_env
ref="${1:-}"

cd "$PLATFORM_ROOT"
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  log "updating source"
  git fetch --prune origin
  if [[ -n "$ref" ]]; then git checkout --quiet "$ref"; fi
  git pull --ff-only
  log "at $(git rev-parse --short HEAD)"
fi

log "building images"
compose build --pull app caddy

log "installing PHP dependencies"
mkdir -p "${PLATFORM_ROOT}/app/vendor"
chown -R 33:33 "${PLATFORM_ROOT}/app/storage" "${PLATFORM_ROOT}/app/bootstrap/cache" "${PLATFORM_ROOT}/app/vendor"
compose run --rm --no-deps -T --user www-data app composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

log "building theme assets (Tailwind, self-hosted fonts) — once per deploy, spec §5"
"${SCRIPT_DIR}/build-assets.sh"

log "starting / refreshing containers"
compose up -d --remove-orphans

log "migrating (backward-compatible migrations only — see docs/RUNBOOK.md once written)"
app_exec php artisan migrate --force --no-interaction

log "caching config, routes, views, events"
app_exec php artisan optimize

log "graceful reload: php-fpm (USR2) and Horizon (terminate; supervisor restarts it)"
compose kill -s USR2 app
compose exec -T horizon php artisan horizon:terminate || true

log "rendering the Caddyfile and reloading Caddy"
"${SCRIPT_DIR}/render-caddy.sh" --reload

log "health"
app_exec php artisan platform:health --json
log "deploy complete"
