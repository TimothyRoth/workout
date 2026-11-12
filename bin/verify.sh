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

step "PHPUnit"
check "phpunit" "$PHP" bin/phpunit --no-progress

echo
if ((${#failed[@]})); then
    printf 'FAILED: %s\n' "${failed[@]}"
    exit 1
fi
echo "All checks passed."
