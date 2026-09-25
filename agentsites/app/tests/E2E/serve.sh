#!/usr/bin/env bash
# The app server Playwright drives (php's built-in server behind `artisan serve`). Tenant hosts
# reach it because Chromium maps *.example.test to 127.0.0.1 (playwright.config.js).
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."
. tests/E2E/env.sh
exec php artisan serve --host=127.0.0.1 --port="$E2E_PORT" --no-reload
