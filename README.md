# Workout Tracker

Lightweight PHP + SQLite workout tracker, built for one person on a phone.

## Stack

Symfony 7.4 LTS on PHP 8.4, SQLite through Doctrine DBAL (hand-written SQL in `src/Repository`,
no ORM), Twig templates, vanilla JS modules and one CSS file in `public/` (no build step).

## Run locally

```bash
composer install
echo 'APP_ENV=dev' > .env.local
php8.4 bin/console doctrine:migrations:migrate
php8.4 -S 127.0.0.1:8080 -t public tests/router.php
```

The database is `var/workout_<env>.db` (`DATABASE_URL` in `.env`). `tests/router.php` is the router
for the built-in server, because `/sw.js` and `/export.json` are routes, not files.
`http://workouts.local` serves the checkout through a PHP 8.4 FPM pool running as your user
(`.claude/setup-workouts-local.sh`, run once with sudo).

The schema is managed by Doctrine Migrations. The first migration is the baseline: it creates the
schema the app had before Symfony on an empty database and adopts an existing database without
changing it. `PRAGMA user_version` stays at 5, the old app's marker.

## Login

Every page and the API need a logged-in user; `/login` and `/sw.js` are public. Users live in the
`users` table and are managed with `bin/console`. On the server, run it as the web user so the
SQLite files keep their owner:

```bash
sudo -u www-data php8.4 bin/console app:user:create <username> <password>   # at least 8 characters
sudo -u www-data php8.4 bin/console app:user:password <username> <password>
sudo -u www-data php8.4 bin/console app:user:list
sudo -u www-data php8.4 bin/console app:user:delete <username>
```

## Training session

`public/js/session.js` runs the live workout: the whole plan on screen, the current set
expanded, a rest timer with ±30 s and skip that also raises a notification through the
service worker when the app is in the background, and progress cached in `localStorage`
so an interrupted session resumes. The plan API (`GET /api/workout/<id>`) delivers the
last performance per set and the best logged set per exercise name and unit
(`LogRepository::records()`, scored by `Unit::recordScore()`: estimated 1RM for
weights, reps for bodyweight, duration for timed sets); a set that beats its record is
flagged on the spot. The summary offers "Plan aktualisieren", which posts `update_plan`
with the log and writes today's reps and loads back into the plan sets
(`SetRepository::applyPerformance()`), pre-checked only when no set was below plan.
The front page shows per workout when it was last trained and how often.

Exercises carry a free-text note (plan sheet, shown on the workout page and in the
session), sessions carry one too (summary sheet, shown on the logs page); both are
schema v5 columns read through `RequestInput::text()`, which keeps line breaks. The exercise
name inputs get a `<datalist>` of every name ever used in plans or logs
(`ExerciseRepository::names()`), so the same exercise keeps the same spelling and its
history and records stay matched.

Logged sessions can be corrected from the logs page (edit sheet per entry: duration, reps
and loads of every set, note; the workload is recomputed) or deleted; `POST /editLog`
and `POST /deleteLog`. In the session a set in seconds or minutes offers a countdown of
its duration inside the current-set card, with the same vibration and background
notification as the rest timer.

The history disclosure of an exercise on the logs page shows two small charts, rendered
client-side by `public/js/charts.js` from `History::chartSeries()`: the best set per session
(estimated 1RM, reps or duration, only while every session used the same unit) and the
volume. Single series, accent hue, endpoint label, crosshair tooltip; the history table
below is the table view. `GET /export.json` (download icon on the logs page) delivers all
workouts, exercises, sets and logs as a JSON attachment, `LogRepository::exportAll()`.

The logs page starts with a training calendar (`App\Training\Calendar`, pure function of the
log list): weekly streak, sessions this week and in the last four weeks, and a 16-week
Monday-first heatmap of trained days (one hue, darker for two or more sessions). For kg
sets the session offers "Scheiben pro Seite", a plate calculator sheet
(`public/js/plates.js`) with a remembered bar weight.

Login is Symfony's `form_login` on the `users` table (`App\Security\UserProvider`); hashes made by
the old app keep working and are upgraded on the next login. The session cookie is `workout_session`
(HttpOnly, SameSite=Lax, Secure on HTTPS). "Angemeldet bleiben" adds a signed `workout_remember`
cookie for 30 days; a password change invalidates it, logout deletes it. Logout is POST only. Pages
of anonymous visitors redirect to `/login` and come back afterwards, the API answers 401 JSON
(`App\Security\LoginEntryPoint`). State-changing requests from another origin are rejected before
anything else runs (`App\EventListener\SameOriginListener`); there are no CSRF tokens.

## Test

```bash
bin/verify.sh                                  # the gate: lint, PHPUnit, production-mode health check, smoke test
php8.4 bin/phpunit                             # unit, repository and HTTP tests
php8.4 tests/smoke.php                         # end to end against the built-in server, fresh database
php8.4 tests/smoke.php path/to/copy.db         # against a COPY of real data (it is modified)
```

PHPUnit builds one migrated SQLite database per run (`var/workout_test.template.db`) and gives every
database test a fresh copy. The smoke test boots the app in production mode on the built-in server
and checks routing, login, validation, origin checks, escaping, cascade deletes, the JSON API and
the log page.

## Data model

A set is `repetitions` × a load. `sets.unit` is one of the `App\Unit` cases (`kg`,
`bw` for bodyweight, `s` for seconds) and `sets.load` is the number that goes with it
(kg or seconds, NULL for bodyweight). Workload per set is reps × load, or just reps for
bodyweight.

A finished session is stored in `workoutLog` (one row per session, workload computed on
the server) with its exercises in `logExercises` and their sets in `logSets`. Schema v3
migrated the earlier JSON summaries into these tables, matching exercises by name within
the workout. `LogRepository::lastPerformance()` feeds the "letztes Mal" column in the
session; `App\History` groups logged exercises for the history on the logs page.

## Front end

No build step. `public/css/app.css` is the whole design system (tokens on `:root`,
components below). JavaScript lives in ES modules under `public/js/`; `app.js` is the
entry and the other modules are imported through an import map in the layout
(`import ... from "app/session"`), which is what keeps them cache-busted. Inline SVG
icons come from the `icon()` Twig function in `src/Twig/AppExtension.php`. Edit forms are native
`<dialog>` bottom sheets opened with `data-dialog="<id>"`; delete forms ask with
`data-confirm="..."`. `public/manifest.webmanifest` makes the app installable; the PNG
icons were generated from `public/img/icon.svg`.

On the workout page every exercise is a collapsible `<details>` card with a one-line
summary of its sets (the `exercise_summary` Twig filter); `public/js/exercises.js` keeps
the open state per workout in `localStorage`. Exercises and sets are reordered by dragging
their grip handle (`public/js/reorder.js`, Pointer Events, no library), which posts the
new id order to `POST /api/reorder`.

## Installable app (PWA)

`/sw.js` is a service worker rendered from `templates/pwa/sw.js.twig`, so its cache
name carries the deployed commit and every release drops the old cache. It serves the
versioned assets cache-first and the three pages network-first with a cache fallback, so
the last seen workout stays readable offline (saving a session still needs the network).
`public/js/pwa.js` registers it (HTTPS only), shows the install card on the front page
for phones (native prompt where the browser offers one, otherwise platform hints, dismiss
is remembered in `localStorage`) and toggles the offline banner in the layout.

## Deploy

On the server, as root:

```bash
git -c safe.directory="$PWD" pull --ff-only
bin/update.sh php8.4
```

`bin/update.sh` gives the files back to `www-data` and backs up the database to
`var/backup/<timestamp>/` (with `VACUUM INTO`, safe while the app is running). It then clears the
cache, runs `composer install --no-dev`, creates an `APP_SECRET` in `.env.local` the first time,
runs the migrations and finally `bin/health-check.php`, which fails the update if the login page
does not render. Pass `php8.4` because PHP-FPM runs 8.4 while the CLI default is newer.

The first deploy of the Symfony version also moves `workout.db` from the project root to
`var/workout_prod.db` and parks the old file in `var/backup/pre-symfony/`. Everybody logs in once
more afterwards.

Rollback to the pre-Symfony app (the schema is the same, no data conversion):

```bash
git -c safe.directory="$PWD" checkout -B main c2f74c1
php8.4 -r '$d = new PDO("sqlite:var/workout_prod.db"); $d->exec("VACUUM INTO \"workout.db\"");'
bin/update.sh php8.4    # the old script: chown, backup, composer install, health check
```

Never open the database from the CLI as your own user: SQLite creates `-wal`/`-shm` files owned
by you that PHP-FPM cannot write, and every page returns 500 until they are removed. Use
`sudo -u www-data` or work on a copy.
