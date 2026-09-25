#!/usr/bin/env bash
# Fresh E2E database with E2E_SITES generated sites (default 1000) plus one draft, and the site
# list the Playwright suite samples from. Idempotent: every run starts from an empty database.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."
. tests/E2E/env.sh
: "${E2E_SITES:=1000}"

if command -v psql >/dev/null 2>&1; then
  if ! PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d postgres -Atc "SELECT 1 FROM pg_database WHERE datname = '${DB_DATABASE}'" | grep -q 1; then
    PGPASSWORD="$DB_PASSWORD" createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" "$DB_DATABASE"
  fi
fi

test -f public/build/manifest.json || npm run build

php artisan migrate:fresh --force --no-interaction
php artisan cache:clear --no-interaction   # file store: keeps the directory and its .gitignore
rm -rf storage/app/private/mail-sink storage/app/private/whatsapp-sink storage/framework/sessions/*   # fresh sinks and sessions for the onboarding flow
php artisan platform:site:generate --count="$E2E_SITES" --seed=7 --out=storage/app/e2e-agents.csv --provision --json > tests/E2E/.import.json
php artisan platform:site:create --name "Draft Agent" --whatsapp +971509999999 --slug draft-agent --draft --json > tests/E2E/.draft.json
php artisan platform:site:list --limit=0 --json > tests/E2E/.sites.json

python3 - <<'EOF'
import json
imp = json.load(open('tests/E2E/.import.json'))['import']
sites = json.load(open('tests/E2E/.sites.json'))
print(f"E2E data ready: import #{imp['id']} {imp['status']} — {imp['created_rows']} created, {imp['error_rows']} errors in {imp['seconds']}s; {sites['count']} sites listed")
EOF
