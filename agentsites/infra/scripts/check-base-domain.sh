#!/usr/bin/env bash
# Spec §4 gate: the base domain may appear only in .env (and DISCOVERY.md). Runs in CI and
# in `make test`. The domain is taken from the first argument, else $PLATFORM_BASE_DOMAIN,
# else the local .env — it is never written into this repository.
#   check-base-domain.sh [domain]
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

domain="${1:-${PLATFORM_BASE_DOMAIN:-}}"
if [[ -z "$domain" && -f "${ROOT}/.env" ]]; then
  domain="$(grep -E '^PLATFORM_BASE_DOMAIN=' "${ROOT}/.env" | tail -1 | cut -d= -f2- | tr -d "'\"" | xargs || true)"
fi
if [[ -z "$domain" ]]; then
  echo "check-base-domain: no domain given (argument, PLATFORM_BASE_DOMAIN, or .env)" >&2
  exit 2
fi

# The registrable label (e.g. "example" for example.com) catches every TLD variant too.
label="${domain%%.*}"
if (( ${#label} < 4 )); then
  echo "check-base-domain: label '${label}' is too short to grep for safely" >&2
  exit 2
fi

cd "$ROOT"
matches="$(grep -rIl -i \
  --exclude-dir=.git --exclude-dir=vendor --exclude-dir=node_modules \
  --exclude-dir=rendered --exclude-dir=storage \
  --exclude=.env --exclude=.env.staging --exclude=.env.local --exclude=DISCOVERY.md \
  -- "$label" . || true)"

if [[ -n "$matches" ]]; then
  echo "Base-domain leak (spec §4): '${label}' must live only in .env / DISCOVERY.md, but was found in:" >&2
  echo "$matches" >&2
  exit 1
fi
echo "OK: '${label}' appears nowhere but .env / DISCOVERY.md"
