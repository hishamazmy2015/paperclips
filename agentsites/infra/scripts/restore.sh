#!/usr/bin/env bash
# Restore a snapshot made by backup.sh. DESTRUCTIVE for the platform database and media
# (the .env is NOT replaced; copy <snapshot>/env by hand if you need it).
#   restore.sh /root/backups/platform/<stamp> --yes
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

src="${1:-}"
[[ -d "$src" ]] || die "usage: restore.sh <snapshot-dir> --yes"
[[ "${2:-}" == "--yes" ]] || die "refusing without --yes (this replaces the platform database and media)"
load_env

log "verifying checksums"
( cd "$src" && sha256sum --quiet -c SHA256SUMS ) || die "checksum mismatch in ${src}"

log "stopping workers"
compose stop horizon scheduler

log "restoring database"
compose exec -T postgres pg_restore -U "${DB_USERNAME}" -d "${DB_DATABASE}" --clean --if-exists --no-owner < "${src}/db.dump"

log "restoring media"
rsync -a --delete "${src}/media/" "${PLATFORM_ROOT}/media/"
chown -R 33:33 "${PLATFORM_ROOT}/media"

if [[ -f "${src}/caddy_data.tar.gz" ]]; then
  log "restoring caddy data (certificates)"
  compose exec -T caddy tar -xzf - -C /data < "${src}/caddy_data.tar.gz"
fi

compose start horizon scheduler
app_exec php artisan optimize:clear
app_exec php artisan platform:health
log "restore complete from ${src}"
