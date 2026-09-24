#!/usr/bin/env bash
# Platform backup (spec §18): pg_dump (custom format), media as hard-linked incremental
# snapshots, Caddy data (certificates), .env → /root/backups/platform/<stamp>/; prunes
# snapshots older than BACKUP_RETENTION_DAYS; optional off-server sync via rclone.
# Nightly from /etc/cron.d/platform; safe to run by hand any time.
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
load_env

root="${BACKUP_ROOT:-/root/backups/platform}"
keep="${BACKUP_RETENTION_DAYS:-30}"
stamp="$(date -u +%Y-%m-%dT%H%M%SZ)"
dest="${root}/${stamp}"
mkdir -p "$dest"
chmod 700 "$root"

log "postgres → ${dest}/db.dump"
compose exec -T postgres pg_dump -U "${DB_USERNAME}" -d "${DB_DATABASE}" -Fc > "${dest}/db.dump"

log "media (incremental against the previous snapshot)"
previous="$(find "$root" -mindepth 1 -maxdepth 1 -type d -name '20*' ! -name "$stamp" | sort | tail -1 || true)"
link_opt=()
if [[ -n "$previous" && -d "${previous}/media" ]]; then link_opt=(--link-dest="${previous}/media"); fi
rsync -a --delete ${link_opt[@]+"${link_opt[@]}"} "${PLATFORM_ROOT}/media/" "${dest}/media/"

log "caddy data (certificates) and .env"
compose exec -T caddy tar -czf - -C /data . > "${dest}/caddy_data.tar.gz"
install -m 600 "$ENV_FILE" "${dest}/env"

( cd "$dest" && find . -type f ! -name SHA256SUMS ! -path './media/*' -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS )

log "pruning snapshots older than ${keep} days"
find "$root" -mindepth 1 -maxdepth 1 -type d -name '20*' -mtime +"$keep" -exec rm -rf {} +

if [[ -n "${BACKUP_RCLONE_REMOTE:-}" ]]; then
  log "off-server sync → ${BACKUP_RCLONE_REMOTE}"
  rclone sync --checksum "$root" "$BACKUP_RCLONE_REMOTE"
fi

log "backup complete: ${dest} ($(du -sh "$dest" | cut -f1))"
