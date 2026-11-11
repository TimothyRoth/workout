#!/usr/bin/env bash
# Puts freshly pulled code into service: run it right after "git pull".
# Usage, as root from anywhere:  bin/update.sh [php-binary]
# On production pass the FPM version (the CLI default there is newer):  bin/update.sh php8.4
set -euo pipefail
cd "$(dirname "$0")/.."

APP_USER=www-data
PHP=${1:-php}
DATABASE=${WORKOUT_DB:-workout.db}

if [[ $EUID -ne 0 ]]; then
    echo "Run as root: sudo bin/update.sh" >&2
    exit 1
fi

# Composer gets a home inside var/, because the app user's own home is not writable.
as_app_user() {
    sudo -H -u "$APP_USER" -- env COMPOSER_HOME="$PWD/var/composer" WORKOUT_DB="$DATABASE" "$@"
}

# VACUUM INTO writes a consistent copy even while the app is serving requests (WAL included).
# Runs before anything that could migrate the schema.
backup_database() {
    local target

    [[ -e $DATABASE ]] || return 0
    target="var/backup/$(date +%Y%m%d-%H%M%S)"

    as_app_user mkdir -p "$target"
    as_app_user "$PHP" -r '
        $database = new PDO("sqlite:".$argv[1]);
        $database->exec("VACUUM INTO ".$database->quote($argv[2]));
    ' "$DATABASE" "$target/$(basename "$DATABASE")"
    echo "Backed up $DATABASE to $target/"
}

chown -R "$APP_USER:$APP_USER" .
backup_database

as_app_user "$PHP" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction

# Opens the database like the first request would, which applies pending schema migrations,
# then renders the login page.
as_app_user "$PHP" bin/health-check.php

echo "Update finished."
