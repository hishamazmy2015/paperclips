#!/usr/bin/env bash
# Phase 0 full backup of the EXISTING platform (spec §3.4): files, databases and server
# configs → /root/backups/pre-platform-<date>/ plus an off-server copy.
# Non-destructive: reads everything, restarts nothing, overwrites nothing. Re-runnable
# (finished archives are skipped; a COMPLETE marker ends the run early).
#
# Usage (as root on the server), with one of the off-server targets:
#   OFFSITE_RCLONE_REMOTE=b2:bucket/backups   bash pre-platform-backup.sh
#   OFFSITE_SSH_TARGET=user@host:/backups     bash pre-platform-backup.sh
# Optional: BACKUP_DEST=/mnt/big/pre-platform-<date> when /root is short on space.
set -euo pipefail

date_tag="$(date +%F)"
dest="${BACKUP_DEST:-/root/backups/pre-platform-${date_tag}}"
paths=(/etc/nginx /etc/apache2 /etc/caddy /etc/letsencrypt /etc/systemd/system /etc/cron.d
       /etc/crontab /var/spool/cron /etc/ufw /etc/ssh /var/www /opt /srv /root/.pm2 /root/.config /home)

log() { printf '[%s] %s\n' "$(date -u +%H:%M:%S)" "$*" >&2; }
[[ $EUID -eq 0 ]] || { log "run as root"; exit 1; }

if [[ -f "${dest}/COMPLETE" ]]; then
  log "already complete: ${dest}"
  cat "${dest}/COMPLETE"
  exit 0
fi
mkdir -p "$dest"
chmod 700 "$dest"

# 1. Estimate and check free space (2x the estimate on the destination filesystem)
existing=()
for p in "${paths[@]}"; do [[ -e "$p" ]] && existing+=("$p"); done
est_kb="$(du -skx "${existing[@]}" 2>/dev/null | awk '{s+=$1} END {print s+0}')"
avail_kb="$(df -Pk "$dest" | awk 'NR==2 {print $4}')"
log "estimated ${est_kb} KB to back up; ${avail_kb} KB free at ${dest}"
if (( avail_kb < est_kb * 2 )); then
  log "ERROR: not enough free space (need ~$((est_kb * 2)) KB). Set BACKUP_DEST to a larger filesystem."
  exit 1
fi

# 2. Files and configs — one archive per path so a partial failure is visible
for p in "${existing[@]}"; do
  name="${p#/}"; name="${name//\//_}"
  archive="${dest}/files_${name}.tar.gz"
  if [[ -f "$archive" ]]; then log "skip (exists) ${p}"; continue; fi
  log "archiving ${p}"
  # tar exit 1 = a file changed while being read (live services); still a usable archive
  tar --warning=no-file-changed --one-file-system -czf "${archive}.part" -C / "${p#/}" || [[ $? -eq 1 ]]
  mv "${archive}.part" "$archive"
done

# 3. Databases — only what is installed and running
if command -v pg_dumpall >/dev/null 2>&1 && systemctl is-active --quiet postgresql 2>/dev/null; then
  log "dumping PostgreSQL (all databases)"
  if ! su -s /bin/sh postgres -c "pg_dumpall" | gzip > "${dest}/postgres_all.sql.gz"; then
    log "WARN: pg_dumpall failed"; rm -f "${dest}/postgres_all.sql.gz"
  fi
fi
if command -v mysqldump >/dev/null 2>&1 && { systemctl is-active --quiet mysql 2>/dev/null || systemctl is-active --quiet mariadb 2>/dev/null; }; then
  log "dumping MySQL/MariaDB (all databases)"
  if ! mysqldump --all-databases --single-transaction --routines --events | gzip > "${dest}/mysql_all.sql.gz"; then
    log "WARN: mysqldump failed (credentials?)"; rm -f "${dest}/mysql_all.sql.gz"
  fi
fi
if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
  log "recording Docker state (containers, images, volumes — volume contents are NOT exported here)"
  docker ps -a --no-trunc > "${dest}/docker_ps.txt"
  docker images > "${dest}/docker_images.txt"
  docker volume ls > "${dest}/docker_volumes.txt"
fi

# 4. State needed to rebuild the box
dpkg -l > "${dest}/dpkg.txt" 2>/dev/null || true
ss -tulpn > "${dest}/listening.txt" 2>/dev/null || true
crontab -l > "${dest}/root_crontab.txt" 2>/dev/null || true
if command -v pm2 >/dev/null 2>&1; then pm2 jlist > "${dest}/pm2.json" 2>/dev/null || true; fi
if command -v nginx >/dev/null 2>&1; then nginx -T > "${dest}/nginx_T.txt" 2>/dev/null || true; fi

# 5. Integrity manifest
( cd "$dest" && find . -type f ! -name SHA256SUMS -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS )
size="$(du -sh "$dest" | cut -f1)"

# 6. Off-server copy
offsite="none — set OFFSITE_RCLONE_REMOTE or OFFSITE_SSH_TARGET and re-run"
if [[ -n "${OFFSITE_RCLONE_REMOTE:-}" ]]; then
  log "copying off-server with rclone → ${OFFSITE_RCLONE_REMOTE}"
  rclone copy --checksum "$dest" "${OFFSITE_RCLONE_REMOTE}/pre-platform-${date_tag}" && offsite="${OFFSITE_RCLONE_REMOTE}/pre-platform-${date_tag}"
elif [[ -n "${OFFSITE_SSH_TARGET:-}" ]]; then
  log "copying off-server with rsync → ${OFFSITE_SSH_TARGET}"
  rsync -a "$dest" "${OFFSITE_SSH_TARGET}/" && offsite="${OFFSITE_SSH_TARGET}/pre-platform-${date_tag}"
fi

printf 'completed_at=%s\nlocation=%s\nsize=%s\noffsite=%s\n' \
  "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$dest" "$size" "$offsite" | tee "${dest}/COMPLETE"
