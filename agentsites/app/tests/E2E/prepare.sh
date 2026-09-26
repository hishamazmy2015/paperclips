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
# one live site per theme for the Lighthouse gates and the theme checks (spec §10)
n=1
for theme in atlas marina palm; do
  php artisan platform:site:create --name "Lighthouse ${theme^}" --whatsapp "+97150100000${n}" --slug "lh-${theme}" --theme "$theme" --areas "Downtown,Marina" --json > /dev/null
  n=$((n + 1))
done
# two testimonials per gate site: palm is testimonials-forward (spec §10), the others keep the config order
php artisan tinker --no-interaction --execute='
  foreach (App\Models\Tenant::query()->whereIn("slug", ["lh-atlas", "lh-marina", "lh-palm"])->get() as $t) App\Tenancy\TenantContext::with($t, function () use ($t) {
    foreach ([["Sara K.", "Buyer, Downtown", "Found our apartment in two weeks and negotiated a great price.", "وجدنا شقتنا خلال أسبوعين وحصلنا على سعر ممتاز.", 5], ["Omar H.", "Landlord, Marina", "Rented my unit in four days with zero hassle.", "أجّرت وحدتي خلال أربعة أيام دون أي متاعب.", 5]] as $i => [$name, $role, $en, $ar, $rating]) {
      App\Models\Testimonial::query()->create(["tenant_id" => $t->id, "author_name" => $name, "author_role" => $role, "text_en" => $en, "text_ar" => $ar, "rating" => $rating, "featured" => true, "sort_order" => $i]);
    }
  });
' > /dev/null
php artisan platform:site:list --limit=0 --json > tests/E2E/.sites.json

python3 - <<'EOF'
import json
imp = json.load(open('tests/E2E/.import.json'))['import']
sites = json.load(open('tests/E2E/.sites.json'))
print(f"E2E data ready: import #{imp['id']} {imp['status']} — {imp['created_rows']} created, {imp['error_rows']} errors in {imp['seconds']}s; {sites['count']} sites listed")
EOF
