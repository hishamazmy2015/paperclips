# AgentSites platform — operator entry points (spec §7). Every target is idempotent.
SHELL := /bin/bash
.DEFAULT_GOAL := help
COMPOSE ?= docker compose
SCRIPTS := infra/scripts

.PHONY: help env build up down restart ps logs shell deploy test lint stan pest check-domain render-caddy backup restore discover pre-platform-backup

help: ## List targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

env: ## Create .env from .env.example (if missing) and link it into app/
	@test -f .env || { cp .env.example .env; chmod 600 .env; echo "created .env — set PLATFORM_BASE_DOMAIN, DB_PASSWORD, ACME_EMAIL, tokens"; }
	@test -e app/.env || ln -s ../.env app/.env

build: ## Build the app and caddy images
	$(COMPOSE) build --pull app caddy

up: env build ## Render the Caddyfile and start the stack (ports from .env; not 80/443 until cutover)
	$(SCRIPTS)/render-caddy.sh
	$(COMPOSE) up -d --remove-orphans
	$(COMPOSE) ps

down: ## Stop containers (volumes are kept)
	$(COMPOSE) down

restart: ## Graceful reload of php-fpm (USR2) and Horizon (terminate → supervisor restarts it)
	$(COMPOSE) kill -s USR2 app
	$(COMPOSE) exec -T horizon php artisan horizon:terminate || true

ps: ## Container status
	$(COMPOSE) ps

logs: ## Tail logs (LOGS=<service> to filter)
	$(COMPOSE) logs -f --tail=200 $(LOGS)

shell: ## Shell into the app container (as www-data)
	$(COMPOSE) exec --user www-data app bash

deploy: ## Zero-downtime deploy: pull, build, composer, migrate, caches, graceful reloads, health
	$(SCRIPTS)/deploy.sh

test: lint stan pest check-domain ## Local quality gates (spec §19 subset)

lint: ## Laravel Pint (style)
	cd app && vendor/bin/pint --test

stan: ## PHPStan level 6 (larastan)
	cd app && vendor/bin/phpstan analyse --memory-limit=1G

pest: ## Pest test suite
	cd app && vendor/bin/pest

check-domain: ## Base-domain leak gate (spec §4): the domain may appear only in .env / DISCOVERY.md
	$(SCRIPTS)/check-base-domain.sh

render-caddy: ## Render the Caddyfile from its template and reload Caddy gracefully
	$(SCRIPTS)/render-caddy.sh --reload

backup: ## Backup now (postgres, media incremental, caddy data, .env) → /root/backups/platform/<stamp>
	$(SCRIPTS)/backup.sh

restore: ## Restore a backup: make restore FROM=/root/backups/platform/<stamp>
	$(SCRIPTS)/restore.sh "$(FROM)" --yes

discover: ## Phase 0: read-only server inventory → discovery-<date>.md (BASE=<base-domain>)
	$(SCRIPTS)/discover.sh "$(BASE)" > "discovery-$$(date +%F).md" && echo "wrote discovery-$$(date +%F).md (git-ignored; paste into docs/DISCOVERY.md)"

pre-platform-backup: ## Phase 0: full backup of the legacy platform → /root/backups/pre-platform-<date>/ (+ off-server copy)
	$(SCRIPTS)/pre-platform-backup.sh
