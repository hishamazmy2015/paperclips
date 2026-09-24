#!/usr/bin/env bash
# Shared helpers for infra/scripts. Source it; do not execute it.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLATFORM_ROOT="${PLATFORM_ROOT:-$(cd "${SCRIPT_DIR}/../.." && pwd)}"
ENV_FILE="${ENV_FILE:-${PLATFORM_ROOT}/.env}"
export SCRIPT_DIR PLATFORM_ROOT ENV_FILE

log() { printf '[%s] %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }
require_cmd() { local c; for c in "$@"; do command -v "$c" >/dev/null 2>&1 || die "missing command: $c"; done; }

# load_env [file]: export KEY=VALUE pairs from a .env file. Handles comments, blank lines,
# `export KEY=`, single/double quotes and unquoted trailing comments. Never sources the file.
load_env() {
  local file="${1:-$ENV_FILE}" line key value
  [[ -f "$file" ]] || die "env file not found: ${file} (run: make env)"
  while IFS= read -r line || [[ -n "$line" ]]; do
    line="${line%$'\r'}"
    [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
    [[ "$line" =~ ^[[:space:]]*(export[[:space:]]+)?([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]] || continue
    key="${BASH_REMATCH[2]}"
    value="${BASH_REMATCH[3]}"
    if [[ "$value" =~ ^\"(.*)\"[[:space:]]*(#.*)?$ ]]; then
      value="${BASH_REMATCH[1]}"
    elif [[ "$value" =~ ^\'(.*)\'[[:space:]]*(#.*)?$ ]]; then
      value="${BASH_REMATCH[1]}"
    else
      value="${value%%[[:space:]]#*}"
      value="${value%"${value##*[![:space:]]}"}"
    fi
    export "${key}=${value}"
  done < "$file"
}

# compose ...: docker compose bound to the platform root, whatever the caller's cwd is.
compose() { docker compose --project-directory "$PLATFORM_ROOT" "$@"; }

# app_exec ...: run a command in the running app container as the php-fpm user.
app_exec() { compose exec -T --user www-data app "$@"; }
