#!/usr/bin/env bash
# Environment for the E2E database and server. Every value can be overridden from outside
# (CI sets DB_* to its service container). Source this; do not execute it.
: "${APP_NAME:=AgentSites}"
: "${APP_ENV:=local}"
: "${APP_DEBUG:=true}"
: "${APP_KEY:=base64:63ZdgVcH7yNB0UQ/gCbQo6jrz/3rDuQEVgOt5mBe3lU=}"
: "${PLATFORM_BASE_DOMAIN:=example.test}"
: "${DB_CONNECTION:=pgsql}"
: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:=platform_e2e}"
: "${DB_USERNAME:=platform}"
: "${DB_PASSWORD:=platform}"
: "${CACHE_STORE:=file}"
: "${QUEUE_CONNECTION:=sync}"
: "${SESSION_DRIVER:=file}"
: "${MEDIA_ROOT:=storage/app}"
: "${MAIL_MAILER:=file}"
: "${WHATSAPP_PROVIDER:=file}"
: "${CONTENT_GENERATOR:=template}"
# every E2E sign-up comes from 127.0.0.1: lift the per-IP abuse limits (per-identifier ones stay)
: "${OTP_SENDS_PER_IP:=500}"
: "${OTP_VERIFIES_PER_IP:=1000}"
: "${LOG_CHANNEL:=stderr}"
: "${LOG_LEVEL:=warning}"
: "${PHP_CLI_SERVER_WORKERS:=4}"
: "${E2E_PORT:=8123}"
# the origin visitors (and the cache warmer) use for every host: http on the E2E port
: "${APP_URL:=http://app.${PLATFORM_BASE_DOMAIN}:${E2E_PORT}}"
export APP_NAME APP_ENV APP_DEBUG APP_KEY PLATFORM_BASE_DOMAIN DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
  CACHE_STORE QUEUE_CONNECTION SESSION_DRIVER MEDIA_ROOT MAIL_MAILER WHATSAPP_PROVIDER CONTENT_GENERATOR OTP_SENDS_PER_IP OTP_VERIFIES_PER_IP LOG_CHANNEL LOG_LEVEL PHP_CLI_SERVER_WORKERS E2E_PORT APP_URL
