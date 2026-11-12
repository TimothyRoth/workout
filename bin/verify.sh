#!/usr/bin/env bash
# Gate before every commit: bin/verify.sh
# Runs every check even if an earlier one fails; exit code 1 if any failed.
set -uo pipefail
cd "$(dirname "$0")/.."

PHP=${PHP:-php8.4}
failed=()

step() { printf '\n== %s\n' "$1"; }
check() {
    local name=$1
    shift
    if ! "$@"; then
        failed+=("$name")
    fi
}

step "PHP ($PHP)"
check "php version" "$PHP" -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);'

step "Lint: YAML, Twig, container"
check "lint:yaml" env APP_ENV=dev "$PHP" bin/console lint:yaml config --parse-tags --quiet
check "lint:twig" env APP_ENV=dev "$PHP" bin/console lint:twig templates --quiet
check "lint:container" env APP_ENV=dev "$PHP" bin/console lint:container --quiet

step "PHPUnit (unit, repository, HTTP)"
check "phpunit" "$PHP" bin/phpunit --no-progress

step "Production mode: migrations on an empty database, then the login page"
production_login_page() {
    local tmp status
    tmp=$(mktemp -d)
    APP_ENV=prod APP_DEBUG=0 APP_SECRET=gate-check DATABASE_URL="sqlite:///$tmp/gate.db" APP_CACHE_DIR="$tmp/cache" APP_LOG_DIR="$tmp/log" \
        "$PHP" bin/console doctrine:migrations:migrate --no-interaction --quiet \
        && APP_ENV=prod APP_DEBUG=0 APP_SECRET=gate-check DATABASE_URL="sqlite:///$tmp/gate.db" APP_CACHE_DIR="$tmp/cache" APP_LOG_DIR="$tmp/log" \
        "$PHP" bin/health-check.php
    status=$?
    rm -rf "$tmp"
    return $status
}
check "production login page" production_login_page

step "Smoke test (built-in server, production mode)"
check "smoke" "$PHP" tests/smoke.php

echo
if ((${#failed[@]})); then
    printf 'FAILED: %s\n' "${failed[@]}"
    exit 1
fi
echo "All checks passed."
