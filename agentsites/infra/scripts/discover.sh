#!/usr/bin/env bash
# Phase 0 discovery (spec §3.1–3.2). READ-ONLY: it only reads and prints. No installs, no
# writes, no restarts. Run on the production server as root:
#   bash discover.sh <base-domain> > /root/discovery-$(date +%F).md
# and paste the relevant parts into docs/DISCOVERY.md. Values that look like credentials are
# masked by pattern; review the output before sharing it anyway.
set -uo pipefail   # no -e: every probe is best-effort and reports its own failure

base="${1:-${PLATFORM_BASE_DOMAIN:-}}"

section() { printf '\n## %s\n' "$*"; }
probe() {   # probe <title> <shell command string>
  local title="$1"
  shift
  printf '\n### %s\n\n```\n' "$title"
  bash -c "$*" 2>&1 | head -n 400 || true
  printf '```\n'
}
redact() {
  sed -E "s/((password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key|private[_-]?key)[^=: ]*[=:][[:space:]]*)[^[:space:]]+/\\1<redacted>/Ig"
}

main() {
  printf '# Server discovery — %s\n\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  printf -- '- host: `%s`\n- base domain probed: `%s`\n' "$(hostname -f 2>/dev/null || hostname)" "${base:-<none given>}"

  section "1. System"
  probe "OS" 'cat /etc/os-release; uname -a'
  probe "CPU / RAM / disk" 'nproc; free -h; df -hT -x tmpfs -x devtmpfs -x overlay; lsblk -o NAME,SIZE,TYPE,MOUNTPOINT 2>/dev/null'
  probe "Uptime / load" 'uptime'
  probe "Addresses" 'ip -4 -o addr show scope global; ip -6 -o addr show scope global'

  section "2. Listening services"
  probe "Listening sockets" 'ss -tulpnH 2>/dev/null || netstat -tulpn'
  probe "Running services (systemd)" 'systemctl list-units --type=service --state=running --no-pager --no-legend'
  probe "Timers" 'systemctl list-timers --all --no-pager --no-legend'

  section "3. Web servers, vhosts, certificates"
  probe "nginx" 'nginx -v; nginx -T 2>/dev/null | grep -E "^\s*(server_name|listen|root|proxy_pass|ssl_certificate|include)" | sed "s/^\s*//" | sort | uniq -c | sort -rn'
  probe "nginx sites" 'ls -la /etc/nginx/sites-enabled /etc/nginx/conf.d 2>/dev/null'
  probe "apache" 'apache2ctl -S 2>/dev/null || httpd -S'
  probe "caddy" 'caddy version; ls -la /etc/caddy 2>/dev/null'
  probe "Let's Encrypt / certbot" 'ls -la /etc/letsencrypt/live 2>/dev/null; certbot certificates 2>/dev/null'
  probe "Other certificate stores" 'ls -la /var/lib/caddy/.local/share/caddy/certificates /root/.acme.sh 2>/dev/null'

  section "4. Runtimes and data stores"
  probe "PHP" 'php -v; ls /etc/php 2>/dev/null'
  probe "Node / pm2" 'node -v; npm -v; pm2 -v; pm2 ls 2>/dev/null'
  probe "Python" 'python3 -V; ls -d /opt/*/venv 2>/dev/null'
  probe "PostgreSQL" 'psql --version; systemctl is-active postgresql; su -s /bin/sh postgres -c "psql -Atc \"select version()\"" 2>/dev/null; su -s /bin/sh postgres -c "psql -Atc \"select datname, pg_size_pretty(pg_database_size(datname)) from pg_database\"" 2>/dev/null'
  probe "MySQL / MariaDB" 'mysql --version; systemctl is-active mysql mariadb; mysql -e "show databases" 2>/dev/null'
  probe "Redis" 'redis-server --version; redis-cli ping; redis-cli info server 2>/dev/null | grep -E "redis_version|tcp_port"'
  probe "Mail" 'systemctl is-active postfix exim4; postconf -n 2>/dev/null | head -20'

  section "5. Docker"
  probe "Docker" 'docker --version; docker compose version; docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Ports}}\t{{.Status}}"; docker volume ls'

  section "6. Cron, firewall, backups"
  probe "Crontabs" 'for u in $(cut -f1 -d: /etc/passwd); do crontab -l -u "$u" 2>/dev/null | sed "s/^/[$u] /"; done; ls -la /etc/cron.d 2>/dev/null; grep -rH "" /etc/cron.d 2>/dev/null | grep -v "^[^:]*:#"'
  probe "Firewall" 'ufw status verbose 2>/dev/null; iptables -S 2>/dev/null | head -40; nft list ruleset 2>/dev/null | head -60'
  probe "Existing backups" 'ls -la /root/backups /backup /var/backups 2>/dev/null; du -sh /root/backups 2>/dev/null'

  section "7. Existing platform footprint"
  probe "Web roots and app dirs" 'ls -la /var/www /opt /srv 2>/dev/null; du -sh /var/www/* /opt/* /srv/* 2>/dev/null'
  probe "Env files (paths only, never contents)" 'find /var/www /opt /srv /root -maxdepth 4 -name ".env*" -printf "%p %s bytes\n" 2>/dev/null'
  probe "Git checkouts" 'find /var/www /opt /srv /root -maxdepth 4 -name .git -type d -printf "%h\n" 2>/dev/null | while read -r d; do printf "%s  " "$d"; git -C "$d" remote get-url origin 2>/dev/null || echo "(no origin)"; done'
  probe "Custom systemd units" 'ls -la /etc/systemd/system/*.service 2>/dev/null'

  section "8. DNS and edge for the base domain"
  if [[ -n "$base" ]]; then
    probe "Nameservers (Cloudflare = *.ns.cloudflare.com)" "dig +short NS ${base}"
    probe "A records (apex, www, platform hosts, wildcard probe)" "for h in ${base} www.${base} app.${base} admin.${base} api.${base} probe-\$RANDOM.${base}; do printf '%-45s ' \"\$h\"; dig +short A \"\$h\" | tr '\\n' ' '; echo; done"
    probe "Who answers HTTPS today" "curl -sSI --max-time 10 https://${base} | grep -iE '^(HTTP|server|via|cf-ray|x-powered-by)'"
    probe "MX / TXT (mail + verification records)" "dig +short MX ${base}; dig +short TXT ${base}"
  else
    printf '\n_(no base domain given — pass it as the first argument to get the DNS checks)_\n'
  fi

  printf '\n---\n_Generated by infra/scripts/discover.sh (read-only). Credential-looking values are masked; review before sharing._\n'
}

main | redact
