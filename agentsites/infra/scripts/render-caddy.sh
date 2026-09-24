#!/usr/bin/env bash
# Render infra/caddy/Caddyfile.tmpl (+ snippets) into infra/caddy/rendered/ using the
# non-secret values from .env, then optionally validate and reload Caddy gracefully.
# Secrets never enter the rendered files: {env.*} placeholders resolve inside Caddy.
#   render-caddy.sh [--reload] [--validate] [--out DIR]
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

reload=0
validate=0
out="${PLATFORM_ROOT}/infra/caddy/rendered"
while [[ $# -gt 0 ]]; do
  case "$1" in
    --reload) reload=1 ;;
    --validate) validate=1 ;;
    --out) out="$2"; shift ;;
    *) die "unknown argument: $1" ;;
  esac
  shift
done

if [[ -f "$ENV_FILE" ]]; then
  load_env "$ENV_FILE"
fi

base="${PLATFORM_BASE_DOMAIN:-}"
domain_re='^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$'
[[ "$base" =~ $domain_re ]] || die "PLATFORM_BASE_DOMAIN is not a valid lowercase domain: '${base}'"
legacy_hosts="$(echo "${LEGACY_HOSTS:-}" | tr -s ' ' | sed 's/^ //; s/ $//')"
legacy_upstream="${LEGACY_UPSTREAM:-}"
for v in "$legacy_hosts" "$legacy_upstream"; do
  [[ "$v" != *'#'* && "$v" != *'{'* && "$v" != *'}'* ]] || die "unsafe character in LEGACY_* value: '${v}'"
done

tmpl_dir="${PLATFORM_ROOT}/infra/caddy"
mkdir -p "$out"

render() {
  sed -e "s#\${PLATFORM_BASE_DOMAIN}#${base}#g" \
      -e "s#\${LEGACY_HOSTS}#${legacy_hosts}#g" \
      -e "s#\${LEGACY_UPSTREAM}#${legacy_upstream}#g" "$1"
}

render "${tmpl_dir}/Caddyfile.tmpl" > "${out}/Caddyfile.tmp"

# Apex ownership follows LEGACY_HOSTS: if the apex is listed there, the legacy platform keeps it.
if [[ ",${legacy_hosts// /}," == *",${base},"* ]]; then
  : > "${out}/apex.caddy.tmp"
  log "apex ${base} stays on the legacy platform (listed in LEGACY_HOSTS)"
else
  render "${tmpl_dir}/snippets/apex.caddy.tmpl" > "${out}/apex.caddy.tmp"
fi

if [[ -n "$legacy_hosts" ]]; then
  [[ -n "$legacy_upstream" ]] || die "LEGACY_HOSTS is set but LEGACY_UPSTREAM is empty"
  render "${tmpl_dir}/snippets/legacy.caddy.tmpl" > "${out}/legacy.caddy.tmp"
else
  : > "${out}/legacy.caddy.tmp"
fi

for f in Caddyfile apex.caddy legacy.caddy; do
  mv -f "${out}/${f}.tmp" "${out}/${f}"
done
log "rendered ${out}/{Caddyfile,apex.caddy,legacy.caddy} for ${base}"

caddy_running() { compose ps --status running --services 2>/dev/null | grep -qx caddy; }

if (( validate )); then
  caddy_running || die "cannot validate: the caddy container is not running (the Cloudflare module lives in that image)"
  compose exec -T caddy caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
fi

if (( reload )); then
  caddy_running || die "cannot reload: the caddy container is not running"
  compose exec -T caddy caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
  compose exec -T caddy caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile
  log "caddy reloaded"
fi
