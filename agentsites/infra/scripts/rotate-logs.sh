#!/usr/bin/env bash
# Log hygiene. Containers log JSON to Docker, which rotates them (docker-compose.yml
# x-logging); this prunes stray Laravel log files and dangling images. Weekly via cron.
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

find "${PLATFORM_ROOT}/app/storage/logs" -type f -name '*.log' -mtime +14 -delete 2>/dev/null || true
docker image prune -f --filter "until=168h" >/dev/null 2>&1 || true
log "log rotation done"
