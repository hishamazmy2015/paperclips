#!/usr/bin/env bash
# Idempotent server bootstrap for the NEW platform (spec §7, §22.7): Docker if missing,
# /srv/platform → this checkout, .env with generated secrets, media/backup dirs, cron,
# images, dependencies, migrations, stack up on the ports set in .env.
# It never touches the legacy platform and refuses to bind a port something else owns.
set -euo pipefail
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
[[ $EUID -eq 0 ]] || die "run as root"

# 1. Docker Engine + Compose plugin (Docker's apt repository)
if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
  log "installing Docker Engine + Compose plugin"
  . /etc/os-release
  case "${ID}" in
    debian|ubuntu) ;;
    *) die "unsupported distro '${ID}': install Docker Engine + the compose plugin manually, then re-run" ;;
  esac
  apt-get update -qq
  apt-get install -y -qq ca-certificates curl gnupg
  install -m 0755 -d /etc/apt/keyrings
  [[ -f /etc/apt/keyrings/docker.asc ]] || curl -fsSL "https://download.docker.com/linux/${ID}/gpg" -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/${ID} ${VERSION_CODENAME} stable" \
    > /etc/apt/sources.list.d/docker.list
  apt-get update -qq
  apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  systemctl enable --now docker
fi
apt-get install -y -qq git rsync openssl >/dev/null

# 2. /srv/platform → this checkout (never replace something else living there)
if [[ -L /srv/platform ]]; then
  [[ "$(readlink -f /srv/platform)" == "$PLATFORM_ROOT" ]] || die "/srv/platform links elsewhere ($(readlink -f /srv/platform)); refusing to change it"
elif [[ -e /srv/platform ]]; then
  [[ "$(cd /srv/platform && pwd -P)" == "$PLATFORM_ROOT" ]] || die "/srv/platform exists and is not this checkout; refusing to touch it"
else
  mkdir -p /srv
  ln -s "$PLATFORM_ROOT" /srv/platform
  log "linked /srv/platform → ${PLATFORM_ROOT}"
fi
mkdir -p "${PLATFORM_ROOT}/media" "${PLATFORM_ROOT}/app/vendor" /root/backups/platform
chmod 700 /root/backups

# 3. .env — created once; generated secrets are never regenerated
if [[ ! -f "$ENV_FILE" ]]; then
  cp "${PLATFORM_ROOT}/.env.example" "$ENV_FILE"
  chmod 600 "$ENV_FILE"
  sed -i "s#^DB_PASSWORD=.*#DB_PASSWORD=$(openssl rand -hex 24)#" "$ENV_FILE"
  sed -i "s#^APP_KEY=.*#APP_KEY=base64:$(openssl rand -base64 32)#" "$ENV_FILE"
  log "created ${ENV_FILE} — set PLATFORM_BASE_DOMAIN, ACME_EMAIL, CLOUDFLARE_API_TOKEN and LEGACY_*, then re-run"
  exit 0
fi
[[ -e "${PLATFORM_ROOT}/app/.env" ]] || ln -s ../.env "${PLATFORM_ROOT}/app/.env"
load_env
[[ -n "${PLATFORM_BASE_DOMAIN:-}" && "${PLATFORM_BASE_DOMAIN}" != "example.com" ]] || die "PLATFORM_BASE_DOMAIN is not set in ${ENV_FILE}"
grep -qE '^APP_KEY=base64:' "$ENV_FILE" || die "APP_KEY is not set in ${ENV_FILE} (APP_KEY=base64:\$(openssl rand -base64 32))"

# 4. Port guard: 80/443 stay with the legacy platform until the cutover is approved
for p in "${CADDY_HTTP_PORT:-80}" "${CADDY_HTTPS_PORT:-443}"; do
  if ss -tlnH "( sport = :${p} )" | grep -q . && ! compose ps --status running --services 2>/dev/null | grep -qx caddy; then
    die "port ${p} is in use by another service; set CADDY_HTTP_PORT/CADDY_HTTPS_PORT in .env (moving to 80/443 is the approved cutover step)"
  fi
done

# 5. Host cron (backups, log hygiene)
install -m 0644 "${PLATFORM_ROOT}/infra/cron/platform.crontab" /etc/cron.d/platform

# 6. Build, dependencies, render, start, migrate, health
chown -R 33:33 "${PLATFORM_ROOT}/app/storage" "${PLATFORM_ROOT}/app/bootstrap/cache" "${PLATFORM_ROOT}/app/vendor" "${PLATFORM_ROOT}/media"
compose build --pull app caddy
compose run --rm --no-deps -T --user www-data app composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
"${SCRIPT_DIR}/build-assets.sh"
"${SCRIPT_DIR}/render-caddy.sh"
compose up -d --remove-orphans
app_exec php artisan migrate --force --no-interaction
app_exec php artisan optimize
app_exec php artisan platform:health

# 7. Demo tenant (spec §21 P1 DoD): idempotent, data only — re-running changes nothing.
log "ensuring the demo site exists"
app_exec php artisan platform:site:create --name "Demo Agent" --whatsapp "+971500000001" --slug demo \
  --agency "AgentSites Demo" --areas "Downtown,Marina,Business Bay" --json
log "bootstrap complete — demo site: https://demo.${PLATFORM_BASE_DOMAIN}:${CADDY_HTTPS_PORT:-443}/"
