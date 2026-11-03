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

## Data model

A set is `repetitions` × a load. `sets.unit` is one of the `App\Unit` cases (`kg`,
`bw` for bodyweight, `s` for seconds) and `sets.load` is the number that goes with it
(kg or seconds, NULL for bodyweight). Workload per set is reps × load, or just reps for
bodyweight. Schema v2 rebuilt the sets table from the old free-text `measure_unit`
column; `Unit::fromLegacy()` holds the parsing rules. Log summaries written before v2
still carry `measureUnit` as text and are displayed unchanged.

## Front end

No build step. `public/css/app.css` is the whole design system (tokens on `:root`,
components below). JavaScript lives in ES modules under `public/js/`; `app.js` is the
entry and the other modules are imported through an import map in the layout
(`import ... from "app/session"`), which is what keeps them cache-busted. Inline SVG
icons come from the `icon()` helper in `src/helpers.php`. Edit forms are native
`<dialog>` bottom sheets opened with `data-dialog="<id>"`; delete forms ask with
`data-confirm="..."`. `public/manifest.webmanifest` makes the app installable; the PNG
icons were generated from `public/img/icon.svg`.

## Deploy

Production is a plain `git pull` on the server. Static assets are cache-busted with
the short hash of the checked-out commit (read from `.git/HEAD` at runtime, see
`src/Version.php`), so every deploy automatically invalidates CSS and JS.

Back up `workout.db` before deploying a release that bumps the schema version.
