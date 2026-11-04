# Workout Tracker

Lightweight PHP + SQLite workout tracker, built for one person on a phone.

## Run locally

```bash
composer install
php -S 127.0.0.1:8080 -t public public/index.php
```

The SQLite file is `workout.db` in the project root (override with the `WORKOUT_DB`
environment variable). `public/index.php` is passed as the router script because the built-in
server would otherwise answer `/sw.js` (a route, not a file) with its own 404. Schema migrations run automatically on the first request;
the current schema version is stored in SQLite's `PRAGMA user_version`.

## Login

Every page and the API need a logged-in user; `/login` and `/sw.js` are public. Users
live in the `users` table and are managed with `bin/console`. Run it as the web user,
so the SQLite files keep their owner (see the WAL note under Deploy):

```bash
sudo -u www-data php bin/console app:user:create <username> <password>   # at least 8 characters
sudo -u www-data php bin/console app:user:password <username> <password>
sudo -u www-data php bin/console app:user:list
sudo -u www-data php bin/console app:user:delete <username>
```

## Training session

`public/js/session.js` runs the live workout: the whole plan on screen, the current set
expanded, a rest timer with ±30 s and skip that also raises a notification through the
service worker when the app is in the background, and progress cached in `localStorage`
so an interrupted session resumes. The plan API (`GET /api/workout/<id>`) delivers the
last performance per set and the best logged set per exercise name and unit
(`Database::getExerciseRecords()`, scored by `Unit::recordScore()`: estimated 1RM for
weights, reps for bodyweight, duration for timed sets); a set that beats its record is
flagged on the spot. The summary offers "Plan aktualisieren", which posts `update_plan`
with the log and writes today's reps and loads back into the plan sets
(`Database::applyPerformanceToPlan()`), pre-checked only when no set was below plan.
The front page shows per workout when it was last trained and how often.

Exercises carry a free-text note (plan sheet, shown on the workout page and in the
session), sessions carry one too (summary sheet, shown on the logs page); both are
schema v5 columns read through `Request::text()`, which keeps line breaks. The exercise
name inputs get a `<datalist>` of every name ever used in plans or logs
(`Database::getExerciseNames()`), so the same exercise keeps the same spelling and its
history and records stay matched.

`App\Http\Auth` keeps the login in a PHP session (`workout_session`, HttpOnly, SameSite=Lax,
Secure on HTTPS). "Angemeldet bleiben" adds a `workout_remember` cookie for 30 days, backed
by the `auth_tokens` table (selector + hashed validator, rotated on every use, revoked on
logout and password change). State-changing requests are still protected by the
same-origin check in `App\Http\Security`.

## Test

```bash
php tests/smoke.php                       # fresh database
php tests/smoke.php path/to/copy.db       # against a COPY of real data (it is modified)
```

The smoke test boots the built-in server on a random port and checks routing,
login, validation, origin checks, escaping, cascade deletes, the JSON API and the log page.

## Data model

A set is `repetitions` × a load. `sets.unit` is one of the `App\Unit` cases (`kg`,
`bw` for bodyweight, `s` for seconds) and `sets.load` is the number that goes with it
(kg or seconds, NULL for bodyweight). Workload per set is reps × load, or just reps for
bodyweight. Schema v2 rebuilt the sets table from the old free-text `measure_unit`
column; `Unit::fromLegacy()` holds the parsing rules.

A finished session is stored in `workoutLog` (one row per session, workload computed on
the server) with its exercises in `logExercises` and their sets in `logSets`. Schema v3
migrated the earlier JSON summaries into these tables, matching exercises by name within
the workout. `Database::getLastPerformance()` feeds the "letztes Mal" column in the
session; `App\History` groups logged exercises for the history on the logs page.

## Front end

No build step. `public/css/app.css` is the whole design system (tokens on `:root`,
components below). JavaScript lives in ES modules under `public/js/`; `app.js` is the
entry and the other modules are imported through an import map in the layout
(`import ... from "app/session"`), which is what keeps them cache-busted. Inline SVG
icons come from the `icon()` helper in `src/helpers.php`. Edit forms are native
`<dialog>` bottom sheets opened with `data-dialog="<id>"`; delete forms ask with
`data-confirm="..."`. `public/manifest.webmanifest` makes the app installable; the PNG
icons were generated from `public/img/icon.svg`.

On the workout page every exercise is a collapsible `<details>` card with a one-line
summary of its sets (`exercise_summary()` in the helpers); `public/js/exercises.js` keeps
the open state per workout in `localStorage`. Exercises and sets are reordered by dragging
their grip handle (`public/js/reorder.js`, Pointer Events, no library), which posts the
new id order to `POST /api/reorder`.

## Installable app (PWA)

`/sw.js` is a service worker rendered by PHP from `src/view/pwa/sw.js.php`, so its cache
name carries the deployed commit and every release drops the old cache. It serves the
versioned assets cache-first and the three pages network-first with a cache fallback, so
the last seen workout stays readable offline (saving a session still needs the network).
`public/js/pwa.js` registers it (HTTPS only), shows the install card on the front page
for phones (native prompt where the browser offers one, otherwise platform hints, dismiss
is remembered in `localStorage`) and toggles the offline banner in the layout.

## Deploy

Production is a plain `git pull` on the server. Static assets are cache-busted with
the short hash of the checked-out commit (read from `.git/HEAD` at runtime, see
`src/Version.php`), so every deploy automatically invalidates CSS and JS.

Back up `workout.db` before deploying a release that bumps the schema version.

Never open `workout.db` from the CLI as your own user: SQLite creates `-wal`/`-shm` files
owned by you that PHP-FPM cannot write, and every page returns 500 until they are removed.
Use `sudo -u www-data` or work on a copy with `WORKOUT_DB`.
