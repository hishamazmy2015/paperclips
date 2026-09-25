#!/usr/bin/env bash
# Build the platform and per-theme CSS with Vite/Tailwind (spec §5: per-theme CSS built once at
# deploy). Runs in a throwaway Node container so the host needs no Node.js. Output lands in
# app/public/build (git-ignored) and is served by Caddy's file_server.
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
require_cmd docker

NODE_IMAGE="${NODE_IMAGE:-node:22-alpine}"
log "building assets with ${NODE_IMAGE}"
docker run --rm \
  -v "${PLATFORM_ROOT}/app:/app" -w /app \
  -e npm_config_cache=/tmp/npm-cache \
  "${NODE_IMAGE}" sh -c 'npm ci --no-audit --no-fund && npm run build'

test -f "${PLATFORM_ROOT}/app/public/build/manifest.json" || die "asset build produced no manifest"
chmod -R a+rX "${PLATFORM_ROOT}/app/public/build"
log "assets built: $(find "${PLATFORM_ROOT}/app/public/build/assets" -type f | wc -l) files"
