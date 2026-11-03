# Workout Tracker

Lightweight PHP + SQLite workout tracker, built for one person on a phone.

## Run locally

```bash
composer install
php -S 127.0.0.1:8080 -t public
```

The SQLite file is `workout.db` in the project root (override with the `WORKOUT_DB`
environment variable). Schema migrations run automatically on the first request;
the current schema version is stored in SQLite's `PRAGMA user_version`.

## Test

```bash
php tests/smoke.php                       # fresh database
php tests/smoke.php path/to/copy.db       # against a COPY of real data (it is modified)
```

The smoke test boots the built-in server on a random port and checks routing,
validation, origin checks, escaping, cascade deletes, the JSON API and the log page.

## Deploy

Production is a plain `git pull` on the server. Static assets are cache-busted with
the short hash of the checked-out commit (read from `.git/HEAD` at runtime, see
`src/Version.php`), so every deploy automatically invalidates CSS and JS.

Back up `workout.db` before deploying a release that bumps the schema version.
