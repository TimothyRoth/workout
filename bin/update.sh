#!/usr/bin/env bash
# Puts freshly pulled code into service: run it right after "git pull".
# Usage, as root from anywhere:  bin/update.sh [php-binary]
# On production pass the FPM version (the CLI default there is newer):  bin/update.sh php8.4
# A rehearsal without root runs as the current user:  APP_USER=$USER bin/update.sh php8.4
set -euo pipefail
cd "$(dirname "$0")/.."

APP_USER=${APP_USER:-www-data}
PHP=${1:-php}
DATABASE=var/workout_prod.db

if [[ $(id -un) != "$APP_USER" && $EUID -ne 0 ]]; then
    echo "Run as root: sudo bin/update.sh" >&2
    exit 1
fi

# Composer gets a home inside var/, because the app user's own home is not writable.
as_app_user() {
    if [[ $(id -un) == "$APP_USER" ]]; then
        env COMPOSER_HOME="$PWD/var/composer" "$@"
    else
        sudo -H -u "$APP_USER" -- env COMPOSER_HOME="$PWD/var/composer" "$@"
    fi
}

# VACUUM INTO writes a consistent copy (WAL included) even while the app is serving requests.
copy_database() {
    as_app_user "$PHP" -r '
        $database = new PDO("sqlite:".$argv[1]);
        $database->exec("VACUUM INTO ".$database->quote($argv[2]));
    ' "$1" "$2"
}

backup_databases() {
    local target database
    target="var/backup/$(date +%Y%m%d-%H%M%S)"

    for database in workout.db var/workout_*.db; do
        [[ -e $database ]] || continue
        as_app_user mkdir -p "$target"
        copy_database "$database" "$target/$(basename "$database")"
        echo "Backed up $database to $target/"
    done
}

has_tables() {
    as_app_user "$PHP" -r '
        $database = new PDO("sqlite:".$argv[1]);
        exit((int) $database->query("SELECT COUNT(*) FROM sqlite_master WHERE type = \"table\"")->fetchColumn() > 0 ? 0 : 1);
    ' "$1"
}

# Once, when the Symfony version is deployed for the first time: the database moves from the project
# root to var/. Runs before composer install, so no request of the new code can have used var/ yet;
# an empty file there (created by a request anyway) is replaced.
adopt_root_database() {
    [[ -e workout.db ]] || return 0
    if [[ -e $DATABASE ]] && has_tables "$DATABASE"; then
        echo "Both workout.db and $DATABASE hold data; resolve this by hand." >&2
        exit 1
    fi

    local parked=var/backup/pre-symfony
    [[ -e $parked ]] && parked+="-$(date +%Y%m%d-%H%M%S)"

    copy_database workout.db "$DATABASE.new"
    rm -f "$DATABASE" "$DATABASE-wal" "$DATABASE-shm"
    as_app_user mv "$DATABASE.new" "$DATABASE"
    as_app_user mkdir -p "$parked"
    for file in workout.db workout.db-wal workout.db-shm; do
        if [[ -e $file ]]; then
            mv "$file" "$parked/"
        fi
    done
    echo "Moved workout.db to $DATABASE (the original is in $parked/)."
}

# The value committed in .env does not count: what is in git is no secret.
has_app_secret() {
    as_app_user "$PHP" -r '
        require "vendor/autoload.php";
        $dotenv = new Symfony\Component\Dotenv\Dotenv();
        $shipped = $dotenv->parse(file_get_contents(".env"))["APP_SECRET"] ?? "";
        $dotenv->bootEnv(".env");
        exit(in_array($_SERVER["APP_SECRET"] ?? "", ["", $shipped], true) ? 1 : 0);
    '
}

# The login needs a secret and the repository ships none. A machine without
# its own gets one, once, in .env.local (which git ignores).
ensure_app_secret() {
    if has_app_secret; then
        return
    fi

    printf 'APP_SECRET=%s\n' "$("$PHP" -r 'echo bin2hex(random_bytes(32));')" >> .env.local
    chown "$APP_USER:$APP_USER" .env.local
    chmod 640 .env.local
    echo "Created an APP_SECRET in .env.local."
}

chown -R "$APP_USER:$APP_USER" .
mkdir -p var && chown "$APP_USER:$APP_USER" var
backup_databases
adopt_root_database

# The cache is deleted by hand: cache:clear boots the old compiled container
# first and crashes when the pull removed a bundle.
rm -rf var/cache

as_app_user "$PHP" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction
ensure_app_secret
as_app_user "$PHP" bin/console doctrine:migrations:migrate --no-interaction
as_app_user "$PHP" bin/health-check.php

echo "Update finished."
