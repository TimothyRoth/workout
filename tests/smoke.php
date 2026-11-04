<?php
/**
 * End-to-end smoke test against the built-in PHP server.
 *
 *   php tests/smoke.php                 # fresh database
 *   php tests/smoke.php path/to/copy.db # run against a COPY of a real database (it will be modified)
 *
 * Exits non-zero on the first failing assertion.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$tmpDir = sys_get_temp_dir() . '/workout-smoke-' . getmypid();
mkdir($tmpDir);
$dbPath = $tmpDir . '/smoke.db';

if (isset($argv[1])) {
    copy($argv[1], $dbPath) || exit("Could not copy {$argv[1]}\n");
} else {
    seedLegacyDatabase($dbPath);
}

/**
 * A schema v1 database with free-text units, so the server has to run the v2 migration.
 */
function seedLegacyDatabase(string $path): void
{
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE TABLE workouts (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE exercises (id INTEGER PRIMARY KEY AUTOINCREMENT, workout_id INTEGER NOT NULL, name TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, position INTEGER NOT NULL DEFAULT 0, FOREIGN KEY(workout_id) REFERENCES workouts(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE sets (id INTEGER PRIMARY KEY AUTOINCREMENT, exercise_id INTEGER NOT NULL, measure_unit TEXT NOT NULL, repetitions INTEGER, rest_time TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, position INTEGER NOT NULL DEFAULT 0, FOREIGN KEY(exercise_id) REFERENCES exercises(id) ON DELETE CASCADE)");
    $pdo->exec("CREATE TABLE workoutLog (id INTEGER PRIMARY KEY AUTOINCREMENT, workout_id INTEGER NOT NULL, workload INTEGER NOT NULL, duration INTEGER NOT NULL, summary TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, workout_name TEXT)");
    $pdo->exec("INSERT INTO workouts(id, name) VALUES (1, 'Legacy')");
    $pdo->exec("INSERT INTO exercises(id, workout_id, name, position) VALUES (1, 1, 'Altbestand', 1)");
    $i = 0;
    foreach (['90kg', '22,5 kg', 'Körpergewicht', '60 s', '2 min', 'Bänder'] as $unit) {
        $i++;
        $pdo->exec("INSERT INTO sets(exercise_id, measure_unit, repetitions, rest_time, position) VALUES (1, " . $pdo->quote($unit) . ", 8, '120', $i)");
    }
    $pdo->exec("INSERT INTO workoutLog(workout_id, workout_name, workload, duration, summary) VALUES (1, 'Legacy', 720, 30, '{\"exercises\":[{\"name\":\"Altbestand\",\"sets\":[{\"reps\":\"8\",\"measureUnit\":\"90kg\",\"breaktime\":\"120\"}]}]}')");
    $pdo->exec("PRAGMA user_version = 1");
}

$port = 18000 + random_int(0, 999);
$cmd = sprintf(
    'WORKOUT_DB=%s php -S 127.0.0.1:%d -t %s %s > %s 2>&1 & echo $!',
    escapeshellarg($dbPath), $port, escapeshellarg($root . '/public'), escapeshellarg($root . '/public/index.php'), escapeshellarg($tmpDir . '/server.log')
);
$pid = (int)trim((string)shell_exec($cmd));
register_shutdown_function(function () use ($pid, $tmpDir) {
    if ($pid > 0) {
        posix_kill($pid, SIGTERM);
    }
    foreach (glob($tmpDir . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tmpDir);
});
usleep(400_000);

$base = "http://127.0.0.1:$port";
$cookieJar = $tmpDir . '/cookies.txt';
$failures = 0;
$passes = 0;

function request(string $method, string $path, array|string|null $body = null, array $headers = []): array
{
    global $base;
    $ch = curl_init($base . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE => $GLOBALS['cookieJar'],
        CURLOPT_COOKIEJAR => $GLOBALS['cookieJar'],
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = is_array($body) ? http_build_query($body) : $body;
    }
    if ($method === 'POST' && !isset($headers['Origin']) && !array_key_exists('Origin', $headers)) {
        $headers['Origin'] = $base;
    }
    $headerLines = [];
    foreach ($headers as $k => $v) {
        if ($v !== null) {
            $headerLines[] = "$k: $v";
        }
    }
    $opts[CURLOPT_HTTPHEADER] = $headerLines;
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $rawHeaders = substr((string)$raw, 0, $headerSize);
    $location = preg_match('/^Location:\s*(.+)$/mi', $rawHeaders, $m) ? trim($m[1]) : null;
    return ['status' => $status, 'headers' => $rawHeaders, 'body' => substr((string)$raw, $headerSize), 'location' => $location];
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $passes;
    if ($ok) {
        $passes++;
        echo "  ok   $name\n";
    } else {
        $failures++;
        echo "  FAIL $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function db(): PDO
{
    global $dbPath;
    static $pdo = null;
    return $pdo ??= new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

echo "Smoke test against $base\n";

// --- login ------------------------------------------------------------------
$console = fn(string $args) => trim((string)shell_exec(sprintf('WORKOUT_DB=%s php %s %s 2>&1', escapeshellarg($dbPath), escapeshellarg($root . '/bin/console'), $args)));
check('console creates a user', str_contains($console('app:user:create smoke "geheim123"'), 'created'));
check('console rejects a duplicate user', str_contains($console('app:user:create smoke "geheim123"'), 'already exists'));
check('console rejects a short password', str_contains($console('app:user:create kurz "abc"'), 'at least 8'));
check('console lists the user', preg_match('/^\\d+\\s+smoke\\s/m', $console('app:user:list')) === 1);

$r = request('GET', '/api/workout/1');
check('anonymous api gets 401 json', $r['status'] === 401 && str_contains($r['body'], '"status":"error"'), "got {$r['status']}");
$r = request('GET', '/sw.js');
check('service worker is public', $r['status'] === 200, "got {$r['status']}");
$r = request('GET', '/login');
check('login page 200', $r['status'] === 200 && str_contains($r['body'], 'name="password"'));
check('login page has no install card', !str_contains($r['body'], 'install-card'));

$r = request('POST', '/login', ['username' => 'smoke', 'password' => 'falsch']);
check('wrong password redirects back', $r['status'] === 303 && $r['location'] === '/login');
$r = request('GET', '/login');
check('wrong password shows german error', str_contains($r['body'], 'Fehlerhafte Zugangsdaten.') && str_contains($r['body'], 'value="smoke"'));
$r = request('GET', '/login');
check('error is shown once', !str_contains($r['body'], 'Fehlerhafte Zugangsdaten.'));
$r = request('GET', '/workout?workout_id=1');
check('still anonymous after failed login, page bounces to login', $r['status'] === 303 && $r['location'] === '/login', "got {$r['status']} {$r['location']}");

$r = request('POST', '/login', ['username' => 'SMOKE', 'password' => 'geheim123', 'remember' => '1']);
check('login redirects to the remembered page', $r['status'] === 303 && $r['location'] === '/workout?workout_id=1', "loc {$r['location']}");
check('login sets a remember cookie', preg_match('/^Set-Cookie: workout_remember=[0-9a-f]{88};.*HttpOnly/mi', $r['headers']) === 1, $r['headers']);
$r = request('GET', '/login');
check('login page redirects when logged in', $r['status'] === 303 && $r['location'] === '/');

// drop the session cookie, keep the remember cookie: the next request must log in again from it
file_put_contents($cookieJar, implode("\n", array_filter(file($cookieJar, FILE_IGNORE_NEW_LINES), fn($l) => !str_contains($l, 'workout_session'))) . "\n");
$r = request('GET', '/');
check('remember cookie restores the login', $r['status'] === 200, "got {$r['status']}");
check('remember token is rotated', preg_match('/^Set-Cookie: workout_remember=/mi', $r['headers']) === 1);

// --- pages render --------------------------------------------------------
$r = request('GET', '/');
check('frontpage 200', $r['status'] === 200, "got {$r['status']}");
check('entry module is versioned', preg_match('/\/js\/app\.js\?v=[0-9a-z]+/', $r['body']) === 1);
check('stylesheet is versioned', preg_match('/\/css\/app\.css\?v=[0-9a-z]+/', $r['body']) === 1);
check('manifest is linked', str_contains($r['body'], 'rel="manifest"'));
check('apple touch icon is linked', str_contains($r['body'], 'rel="apple-touch-icon"'));
check('install card is rendered hidden', preg_match('/<section class="install-card" data-install-card hidden/', $r['body']) === 1);
check('offline banner is rendered hidden', preg_match('/<div class="offline-banner" data-offline-banner hidden/', $r['body']) === 1);

$jsModules = array_map(fn($f) => basename($f, '.js'), glob($root . '/public/js/*.js') ?: []);
$importMap = preg_match('/<script type="importmap">(.*?)<\/script>/s', $r['body'], $m) ? json_decode($m[1], true) : null;
check('import map versions every js module', is_array($importMap) && count(array_filter($jsModules, fn($name) => preg_match('/\?v=[0-9a-z]+$/', $importMap['imports']["app/$name"] ?? '') === 1)) === count($jsModules), json_encode($importMap));

$r = request('GET', '/sw.js');
check('service worker 200', $r['status'] === 200, "got {$r['status']}");
check('service worker is javascript', stripos($r['headers'], 'Content-Type: application/javascript') !== false, $r['headers']);
check('service worker is not browser-cached', stripos($r['headers'], 'Cache-Control: no-cache') !== false);
check('service worker cache name carries the version', preg_match('/const CACHE = \'workout-[0-9a-z]+\';/', $r['body']) === 1);
check('service worker has a fetch handler', str_contains($r['body'], "addEventListener('fetch'"));
check('service worker has no leaked php', !str_contains($r['body'], '<?'));

$r = request('GET', '/logs');
check('logs page 200', $r['status'] === 200, "got {$r['status']}");

$r = request('GET', '/nope');
check('unknown route 404', $r['status'] === 404, "got {$r['status']}");

$r = request('GET', '/workout?workout_id=999999');
check('unknown workout 404', $r['status'] === 404, "got {$r['status']}");

$r = request('GET', '/workout?workout_id=abc');
check('non-numeric id 400', $r['status'] === 400, "got {$r['status']}");

// --- v2 migration of legacy units (seeded data only) ----------------------
if (!isset($argv[1])) {
$migrated = db()->query("SELECT unit, load, rest_time FROM sets WHERE exercise_id = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$expected = [['kg', 90.0], ['kg', 22.5], ['bw', null], ['s', 60.0], ['s', 120.0], ['kg', null]];
check('legacy units migrated to enum + load', array_map(fn($r) => [$r['unit'], $r['load'] === null ? null : (float)$r['load']], $migrated) === $expected, json_encode($migrated));
check('legacy rest_time is now an integer', (int)$migrated[0]['rest_time'] === 120);
$r = request('GET', '/workout?workout_id=1');
check('legacy workout page renders migrated labels', $r['status'] === 200 && str_contains($r['body'], '22,5 kg') && str_contains($r['body'], 'Körpergewicht') && str_contains($r['body'], '60 s') && str_contains($r['body'], '– kg'));
$legacyLog = db()->query("SELECT le.exercise_id, le.name, ls.repetitions, ls.unit, ls.load, ls.rest_time FROM logExercises le JOIN logSets ls ON ls.log_exercise_id = le.id JOIN workoutLog wl ON wl.id = le.log_id WHERE wl.workout_id = 1")->fetchAll(PDO::FETCH_ASSOC);
check('legacy JSON log migrated into logExercises/logSets', count($legacyLog) === 1 && (int)$legacyLog[0]['exercise_id'] === 1 && $legacyLog[0]['name'] === 'Altbestand' && (int)$legacyLog[0]['repetitions'] === 8 && $legacyLog[0]['unit'] === 'kg' && (float)$legacyLog[0]['load'] === 90.0 && (int)$legacyLog[0]['rest_time'] === 120, json_encode($legacyLog));
check('workoutLog has no summary column anymore', !in_array('summary', array_column(db()->query('PRAGMA table_info(workoutLog)')->fetchAll(PDO::FETCH_ASSOC), 'name'), true));
$r = request('GET', '/logs');
check('legacy log renders from tables', $r['status'] === 200 && str_contains($r['body'], 'Altbestand') && str_contains($r['body'], '>90 kg<'));
}

// --- csrf -----------------------------------------------------------------
$r = request('POST', '/addWorkout', ['workout_name' => 'evil'], ['Origin' => 'https://attacker.example']);
check('foreign origin POST rejected', $r['status'] === 403, "got {$r['status']}");
$r = request('POST', '/addWorkout', ['workout_name' => 'evil'], ['Origin' => null, 'Referer' => null]);
check('POST without origin rejected', $r['status'] === 403, "got {$r['status']}");
check('no workout created by rejected posts', (int)db()->query("SELECT COUNT(*) FROM workouts WHERE name='evil'")->fetchColumn() === 0);

// --- validation -----------------------------------------------------------
$r = request('POST', '/addWorkout', ['workout_name' => '   ']);
check('empty workout name rejected', $r['status'] === 400, "got {$r['status']}");

// --- create workout / exercise / sets ------------------------------------
$name = 'Smoke <b>Test</b> ' . bin2hex(random_bytes(3));
$r = request('POST', '/addWorkout', ['workout_name' => $name]);
check('add workout redirects', $r['status'] === 303 && $r['location'] === '/', "status {$r['status']} loc {$r['location']}");
$workoutId = (int)db()->query("SELECT id FROM workouts WHERE name=" . db()->quote($name))->fetchColumn();
check('workout stored trimmed', $workoutId > 0);

$r = request('GET', '/');
check('workout name is html-escaped', str_contains($r['body'], 'Smoke &lt;b&gt;Test&lt;/b&gt;') && !str_contains($r['body'], 'Smoke <b>Test</b>'));
check('frontpage shows never-trained workouts', str_contains($r['body'], 'Noch nie trainiert'));

$r = request('POST', '/addExercise', ['workout_id' => $workoutId, 'exercise_name' => '  Bankdrücken  ', 'exercise_notes' => " Sitz auf 4\r\nenger Griff "]);
$exerciseId = (int)db()->query("SELECT id FROM exercises WHERE workout_id=$workoutId")->fetchColumn();
check('add exercise redirects to anchor', $r['status'] === 303 && $r['location'] === "/workout?workout_id=$workoutId#exercise-$exerciseId", "loc {$r['location']}");
check('exercise name trimmed', db()->query("SELECT name FROM exercises WHERE id=$exerciseId")->fetchColumn() === 'Bankdrücken');
check('exercise notes keep their line breaks', db()->query("SELECT notes FROM exercises WHERE id=$exerciseId")->fetchColumn() === "Sitz auf 4\nenger Griff");
$r = request('POST', '/editExercise', ['exercise_id' => $exerciseId, 'exercise_name' => 'Bankdrücken', 'exercise_notes' => str_repeat('x', 501)]);
check('too long notes rejected', $r['status'] === 400);
$r = request('POST', '/editExercise', ['exercise_id' => $exerciseId, 'exercise_name' => 'Bankdrücken', 'exercise_notes' => 'Sitz auf 4']);
check('edit exercise stores notes', $r['status'] === 303 && db()->query("SELECT notes FROM exercises WHERE id=$exerciseId")->fetchColumn() === 'Sitz auf 4');
$r = request('GET', "/workout?workout_id=$workoutId");
check('workout page shows exercise notes', str_contains($r['body'], '<p class="exercise-notes">') && str_contains($r['body'], 'Sitz auf 4'));
check('workout page suggests known exercise names', preg_match('/<datalist id="exercise-names">.*<option value="Bankdrücken">.*<\/datalist>/s', $r['body']) === 1 && substr_count($r['body'], 'list="exercise-names"') >= 2);

$r = request('POST', '/addSet', ['exercise_id' => $exerciseId, 'amount' => 3, 'repetitions' => 8, 'unit' => 'kg', 'load' => 80, 'rest_time' => 120]);
check('add 3 sets', $r['status'] === 303 && (int)db()->query("SELECT COUNT(*) FROM sets WHERE exercise_id=$exerciseId")->fetchColumn() === 3);

$r = request('POST', '/addSet', ['exercise_id' => $exerciseId, 'amount' => 500, 'repetitions' => 8, 'unit' => 'kg', 'load' => 80, 'rest_time' => 120]);
check('amount above limit rejected', $r['status'] === 400, "got {$r['status']}");

$r = request('POST', '/addSet', ['exercise_id' => $exerciseId, 'amount' => 1, 'repetitions' => 'abc', 'unit' => 'kg', 'load' => 80, 'rest_time' => 120]);
check('non-numeric reps rejected with 400 (not 500)', $r['status'] === 400, "got {$r['status']}");

$r = request('POST', '/addSet', ['exercise_id' => $exerciseId, 'amount' => 1, 'repetitions' => 8, 'unit' => 'lbs', 'load' => 80, 'rest_time' => 120]);
check('unknown unit rejected', $r['status'] === 400, "got {$r['status']}");

$r = request('POST', '/addSet', ['exercise_id' => $exerciseId, 'amount' => 1, 'repetitions' => 8, 'unit' => 'kg', 'rest_time' => 120]);
check('kg without load rejected', $r['status'] === 400, "got {$r['status']}");

$r = request('POST', '/addSet', ['exercise_id' => $exerciseId, 'amount' => 1, 'repetitions' => 12, 'unit' => 'bw', 'load' => 50, 'rest_time' => 60]);
$bw = db()->query("SELECT unit, load FROM sets WHERE exercise_id=$exerciseId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('bodyweight set ignores load', $r['status'] === 303 && $bw['unit'] === 'bw' && $bw['load'] === null, json_encode($bw));
$bwId = (int)db()->query("SELECT id FROM sets WHERE exercise_id=$exerciseId ORDER BY id DESC LIMIT 1")->fetchColumn();
request('POST', '/deleteSet', ['set_id' => $bwId]);

$setId = (int)db()->query("SELECT id FROM sets WHERE exercise_id=$exerciseId ORDER BY id LIMIT 1")->fetchColumn();
$r = request('POST', '/editSet', ['set_id' => $setId, 'repetitions' => 10, 'unit' => 'kg', 'load' => '82,5', 'rest_time' => 90]);
$set = db()->query("SELECT * FROM sets WHERE id=$setId")->fetch(PDO::FETCH_ASSOC);
check('edit set persists (decimal comma accepted)', $r['status'] === 303 && (int)$set['repetitions'] === 10 && $set['unit'] === 'kg' && (float)$set['load'] === 82.5 && (int)$set['rest_time'] === 90, json_encode($set));

$r = request('POST', '/editSet', ['set_id' => $setId, 'repetitions' => 1, 'unit' => 's', 'load' => 45, 'rest_time' => 30]);
$set = db()->query("SELECT unit, load FROM sets WHERE id=$setId")->fetch(PDO::FETCH_ASSOC);
check('set can switch to seconds', $r['status'] === 303 && $set['unit'] === 's' && (float)$set['load'] === 45.0);

$r = request('POST', '/editSet', ['set_id' => $setId, 'repetitions' => 1, 'unit' => 'min', 'load' => 20, 'rest_time' => 30]);
$set = db()->query("SELECT unit, load FROM sets WHERE id=$setId")->fetch(PDO::FETCH_ASSOC);
check('set can use minutes', $r['status'] === 303 && $set['unit'] === 'min' && (float)$set['load'] === 20.0);
$r = request('GET', "/workout?workout_id=$workoutId");
check('minutes rendered', str_contains($r['body'], '>20 min<'));

$r = request('POST', '/deleteSet', ['set_id' => $setId]);
check('delete set', $r['status'] === 303 && (int)db()->query("SELECT COUNT(*) FROM sets WHERE id=$setId")->fetchColumn() === 0);

$r = request('GET', "/workout?workout_id=$workoutId");
check('workout page 200', $r['status'] === 200);
check('workout page has stable exercise anchor', str_contains($r['body'], "id=\"exercise-$exerciseId\""));
check('exercise row summarises its sets', str_contains($r['body'], '2 × 8 · 80 kg · 120 s'), substr((string)strstr($r['body'], 'exercise-sets-summary'), 0, 120));
check('workout page escapes names', str_contains($r['body'], 'Smoke &lt;b&gt;Test&lt;/b&gt;'));
check('workout page shows set labels', str_contains($r['body'], '>80 kg<'));
check('workout page has unit selects', substr_count($r['body'], 'data-unit-select') >= 3);

// --- api -------------------------------------------------------------------
$r = request('GET', "/api/workout/$workoutId");
$plan = json_decode($r['body'], true);
check('api plan 200 json', $r['status'] === 200 && ($plan['status'] ?? '') === 'success');
check('api plan nests sets', count($plan['workout']['exercises'][0]['sets'] ?? []) === 2);
check('api plan carries exercise notes', ($plan['workout']['exercises'][0]['notes'] ?? null) === 'Sitz auf 4');
check('api plan exposes unit and load', ($plan['workout']['exercises'][0]['sets'][1]['unit'] ?? '') === 'kg' && (float)($plan['workout']['exercises'][0]['sets'][1]['load'] ?? 0) === 80.0);

check('api plan carries last performance (none yet)', array_key_exists('last', $plan) && $plan['last'] === null);

$r = request('GET', '/api/workout/999999');
check('api unknown workout 404 json', $r['status'] === 404 && str_contains($r['body'], '"status":"error"'));

// --- reorder & duplicate ------------------------------------------------------
$setIds = db()->query("SELECT id FROM sets WHERE exercise_id=$exerciseId ORDER BY position, id")->fetchAll(PDO::FETCH_COLUMN);
$r = request('POST', '/moveSet', ['set_id' => $setIds[1], 'direction' => 'up']);
check('old move route is gone', $r['status'] === 404, "got {$r['status']}");

$r = request('POST', '/duplicateSet', ['set_id' => $setIds[0]]);
$after = db()->query("SELECT id, repetitions, unit, load FROM sets WHERE exercise_id=$exerciseId ORDER BY position, id")->fetchAll(PDO::FETCH_ASSOC);
check('duplicate set inserts a copy right after the original', $r['status'] === 303 && count($after) === 3 && (int)$after[0]['id'] === (int)$setIds[0] && (int)$after[2]['id'] === (int)$setIds[1] && $after[1]['unit'] === $after[0]['unit'] && (float)$after[1]['load'] === (float)$after[0]['load'], json_encode($after));
request('POST', '/deleteSet', ['set_id' => $after[1]['id']]);

// --- drag-and-drop order ---------------------------------------------------
$setIds = db()->query("SELECT id FROM sets WHERE exercise_id=$exerciseId ORDER BY position, id")->fetchAll(PDO::FETCH_COLUMN);
$reorder = fn(array $payload) => request('POST', '/api/reorder', json_encode($payload), ['Content-Type' => 'application/json']);
$r = $reorder(['type' => 'sets', 'parent_id' => $exerciseId, 'ids' => [(int)$setIds[1], (int)$setIds[0]]]);
$after = db()->query("SELECT id FROM sets WHERE exercise_id=$exerciseId ORDER BY position, id")->fetchAll(PDO::FETCH_COLUMN);
check('reorder sets persists the new order', $r['status'] === 200 && $after === [$setIds[1], $setIds[0]], "got {$r['status']} " . json_encode($after));
$r = $reorder(['type' => 'sets', 'parent_id' => $exerciseId, 'ids' => [(int)$setIds[0]]]);
check('reorder with missing ids is rejected', $r['status'] === 400 && str_contains($r['body'], 'neu laden'), "got {$r['status']}");
$r = $reorder(['type' => 'sets', 'parent_id' => $exerciseId, 'ids' => [(int)$setIds[0], (int)$setIds[1], 999999]]);
check('reorder with foreign ids is rejected', $r['status'] === 400);
$r = $reorder(['type' => 'sets', 'parent_id' => $exerciseId, 'ids' => 'nope']);
check('reorder with malformed ids is rejected', $r['status'] === 400);
$r = $reorder(['type' => 'workouts', 'parent_id' => $exerciseId, 'ids' => [1]]);
check('reorder with unknown type is rejected', $r['status'] === 400);
$r = $reorder(['type' => 'exercises', 'parent_id' => 999999, 'ids' => [1]]);
check('reorder of unknown workout is 404', $r['status'] === 404);
$reorder(['type' => 'sets', 'parent_id' => $exerciseId, 'ids' => [(int)$setIds[0], (int)$setIds[1]]]);

request('POST', '/addExercise', ['workout_id' => $workoutId, 'exercise_name' => 'Zweite']);
$exerciseIds = db()->query("SELECT id FROM exercises WHERE workout_id=$workoutId ORDER BY position, id")->fetchAll(PDO::FETCH_COLUMN);
$r = $reorder(['type' => 'exercises', 'parent_id' => $workoutId, 'ids' => [(int)$exerciseIds[1], (int)$exerciseIds[0]]]);
$after = db()->query("SELECT id FROM exercises WHERE workout_id=$workoutId ORDER BY position, id")->fetchAll(PDO::FETCH_COLUMN);
check('reorder exercises persists the new order', $r['status'] === 200 && $after === [$exerciseIds[1], $exerciseIds[0]], "got {$r['status']} " . json_encode($after));
$reorder(['type' => 'exercises', 'parent_id' => $workoutId, 'ids' => [(int)$exerciseIds[0], (int)$exerciseIds[1]]]);
$reorder(['type' => 'exercises', 'parent_id' => $workoutId, 'ids' => [(int)$exerciseIds[1], (int)$exerciseIds[0]]]);
$plan = json_decode(request('GET', "/api/workout/$workoutId")['body'], true);
check('reordered exercises show up in the plan', array_column($plan['workout']['exercises'], 'id') === [(int)$exerciseIds[1], (int)$exerciseIds[0]], json_encode(array_column($plan['workout']['exercises'], 'id')));
$reorder(['type' => 'exercises', 'parent_id' => $workoutId, 'ids' => [(int)$exerciseIds[0], (int)$exerciseIds[1]]]);
request('POST', '/deleteExercise', ['exercise_id' => $exerciseIds[1]]);

$r = request('POST', '/duplicateWorkout', ['workout_id' => $workoutId]);
$copyId = (int)db()->query("SELECT id FROM workouts WHERE name=" . db()->quote($name . ' (Kopie)'))->fetchColumn();
check('duplicate workout redirects to the copy', $r['status'] === 303 && $copyId > 0 && $r['location'] === "/workout?workout_id=$copyId", "loc {$r['location']}");
check('duplicate workout copies exercises and sets', (int)db()->query("SELECT COUNT(*) FROM sets s JOIN exercises e ON e.id = s.exercise_id WHERE e.workout_id=$copyId")->fetchColumn() === 2);
request('POST', '/deleteWorkout', ['workout_id' => $copyId]);

$logPayload = fn(array $sets) => json_encode(['workout_id' => $workoutId, 'duration' => 42, 'notes' => " Lief gut,\r\nSchulter zwickt ", 'exercises' => [['exercise_id' => $exerciseId, 'name' => 'Bankdrücken', 'sets' => $sets]]]);
$setsA = [['reps' => 8, 'unit' => 'kg', 'load' => '80', 'rest_time' => 120], ['reps' => 12, 'unit' => 'bw', 'load' => null, 'rest_time' => 60]];
$r = request('POST', '/api/workout/log', $logPayload($setsA), ['Content-Type' => 'application/json']);
check('api log 200', $r['status'] === 200, "got {$r['status']} {$r['body']}");
$logId = (int)db()->query("SELECT id FROM workoutLog WHERE workout_id=$workoutId ORDER BY id DESC LIMIT 1")->fetchColumn();
check('log stored with workout name snapshot', $logId > 0 && db()->query("SELECT workout_name FROM workoutLog WHERE id=$logId")->fetchColumn() === $name);
check('log stores the session notes', db()->query("SELECT notes FROM workoutLog WHERE id=$logId")->fetchColumn() === "Lief gut,\nSchulter zwickt");
check('workload computed on the server (8×80 + 12 reps bodyweight)', (float)db()->query("SELECT workload FROM workoutLog WHERE id=$logId")->fetchColumn() === 652.0);
$stored = db()->query("SELECT ls.repetitions, ls.unit, ls.load, ls.rest_time, le.exercise_id FROM logSets ls JOIN logExercises le ON le.id = ls.log_exercise_id WHERE le.log_id=$logId ORDER BY ls.position")->fetchAll(PDO::FETCH_ASSOC);
check('logged sets stored in tables', count($stored) === 2 && (int)$stored[0]['exercise_id'] === $exerciseId && $stored[1]['unit'] === 'bw' && $stored[1]['load'] === null && (int)$stored[0]['rest_time'] === 120, json_encode($stored));

$r = request('GET', "/api/workout/$workoutId");
$plan = json_decode($r['body'], true);
check('api plan carries last performance', ($plan['last']['workload'] ?? null) == 652 && count($plan['last']['exercises']["id:$exerciseId"] ?? []) === 2, json_encode($plan['last'] ?? null));
$kgRecord = $plan['records']['name:bankdrücken|kg'] ?? null;
$bwRecord = $plan['records']['name:bankdrücken|bw'] ?? null;
check('api plan carries exercise records (estimated 1RM for kg, reps for bodyweight)', $kgRecord !== null && $kgRecord['repetitions'] === 8 && (float)$kgRecord['load'] === 80.0 && abs($kgRecord['score'] - 101.33) < 0.01 && $bwRecord !== null && $bwRecord['repetitions'] === 12 && (float)$bwRecord['score'] === 12.0, json_encode($plan['records'] ?? null));

$r = request('GET', '/');
check('frontpage shows last training and count', str_contains($r['body'], 'Zuletzt heute · 1 Training<'), substr((string)strstr($r['body'], 'subtitle'), 0, 80));

$r = request('POST', '/api/workout/log', json_encode(['workout_id' => $workoutId, 'duration' => 42]), ['Content-Type' => 'application/json']);
check('api log without exercises 400', $r['status'] === 400, "got {$r['status']}");
$r = request('POST', '/api/workout/log', $logPayload([['reps' => 8, 'unit' => 'lbs', 'load' => 80, 'rest_time' => 0]]), ['Content-Type' => 'application/json']);
check('api log with unknown unit 400', $r['status'] === 400, "got {$r['status']}");
$r = request('POST', '/api/workout/log', $logPayload([]), ['Content-Type' => 'application/json']);
check('api log without done sets 400', $r['status'] === 400, "got {$r['status']}");

$r = request('POST', '/api/workout/log', $logPayload([['reps' => 0, 'unit' => 'bw', 'load' => null, 'rest_time' => 0]]), ['Content-Type' => 'application/json']);
check('api log with zero workload accepted', $r['status'] === 200);

// --- logs page math -------------------------------------------------------
$r = request('POST', '/api/workout/log', $logPayload([['reps' => 10, 'unit' => 'kg', 'load' => '80', 'rest_time' => 120]]), ['Content-Type' => 'application/json']);
$r = request('GET', '/logs');
check('logs page 200 with zero-workload entry', $r['status'] === 200, "got {$r['status']}");
check('logs page shows first entry label', str_contains($r['body'], 'Erster Eintrag'));
check('logs page shows session notes with line break', str_contains($r['body'], "<span>Lief gut,\nSchulter zwickt</span>"));
check('logs page shows new record label', str_contains($r['body'], 'Neuer Bestwert!'));
check('logs page shows below-best percentage', str_contains($r['body'], '-100,0% vom Bestwert'));
check('logs page renders structured set labels', str_contains($r['body'], '>80 kg<') && str_contains($r['body'], '>Körpergewicht<'));
check('logs page shows per-exercise history', str_contains($r['body'], 'Verlauf (3)') && str_contains($r['body'], '8 × 80 kg, 12 × KG'));

// --- edit & delete log entries ---------------------------------------------
$r = request('POST', '/api/workout/log', json_encode(['workout_id' => $workoutId, 'duration' => 20, 'notes' => 'Tippfehler', 'exercises' => [['exercise_id' => $exerciseId, 'name' => 'Bankdrücken', 'sets' => [['reps' => 8, 'unit' => 'kg', 'load' => 800, 'rest_time' => 60], ['reps' => 10, 'unit' => 'bw', 'load' => null, 'rest_time' => 60]]]]]), ['Content-Type' => 'application/json']);
$fixLogId = (int)json_decode($r['body'], true)['id'];
$fixSets = db()->query("SELECT ls.id, ls.unit FROM logSets ls JOIN logExercises le ON le.id = ls.log_exercise_id WHERE le.log_id=$fixLogId ORDER BY ls.position")->fetchAll(PDO::FETCH_ASSOC);
$r = request('GET', '/logs');
check('logs page has an edit sheet per entry', str_contains($r['body'], "id=\"edit-log-$fixLogId\"") && str_contains($r['body'], "name=\"sets[{$fixSets[0]['id']}][load]\""));
$r = request('POST', '/editLog', ['log_id' => $fixLogId, 'duration' => 25, 'notes' => 'korrigiert', 'sets' => [$fixSets[0]['id'] => ['reps' => 8, 'load' => '80,5'], $fixSets[1]['id'] => ['reps' => 11, 'load' => '999'], 999999 => ['reps' => 1, 'load' => '1']]]);
$fixed = db()->query("SELECT duration, notes, workload FROM workoutLog WHERE id=$fixLogId")->fetch(PDO::FETCH_ASSOC);
$fixedSets = db()->query("SELECT ls.repetitions, ls.load FROM logSets ls JOIN logExercises le ON le.id = ls.log_exercise_id WHERE le.log_id=$fixLogId ORDER BY ls.position")->fetchAll(PDO::FETCH_ASSOC);
check('edit log redirects to the entry', $r['status'] === 303 && $r['location'] === "/logs#log-$fixLogId", "got {$r['status']} {$r['location']}");
check('edit log updates duration, notes and sets, recomputes workload', (int)$fixed['duration'] === 25 && $fixed['notes'] === 'korrigiert' && (float)$fixed['workload'] === 655.0 && (float)$fixedSets[0]['load'] === 80.5 && (int)$fixedSets[1]['repetitions'] === 11 && $fixedSets[1]['load'] === null, json_encode([$fixed, $fixedSets]));
$r = request('POST', '/editLog', ['log_id' => $fixLogId, 'duration' => 25, 'notes' => '', 'sets' => [$fixSets[0]['id'] => ['reps' => 'acht', 'load' => '80']]]);
check('edit log rejects invalid reps', $r['status'] === 400, "got {$r['status']}");
$r = request('POST', '/editLog', ['log_id' => 999999, 'duration' => 25]);
check('edit unknown log 404', $r['status'] === 404);
$r = request('POST', '/deleteLog', ['log_id' => $fixLogId]);
check('delete log removes the entry and its sets', $r['status'] === 303 && (int)db()->query("SELECT COUNT(*) FROM workoutLog WHERE id=$fixLogId")->fetchColumn() === 0 && (int)db()->query("SELECT COUNT(*) FROM logSets WHERE id={$fixSets[0]['id']}")->fetchColumn() === 0);

// --- plan write-back -------------------------------------------------------
$planSet = db()->query("SELECT id, repetitions, load FROM sets WHERE exercise_id=$exerciseId AND unit='kg' ORDER BY position, id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$writeBack = fn(array $sets, bool $update) => json_encode(['workout_id' => $workoutId, 'duration' => 30, 'update_plan' => $update, 'exercises' => [['exercise_id' => $exerciseId, 'name' => 'Bankdrücken', 'sets' => $sets]]]);
$r = request('POST', '/api/workout/log', $writeBack([['set_id' => $planSet['id'], 'reps' => 11, 'unit' => 'kg', 'load' => '85', 'rest_time' => 90]], false), ['Content-Type' => 'application/json']);
$unchanged = db()->query("SELECT repetitions, load FROM sets WHERE id={$planSet['id']}")->fetch(PDO::FETCH_ASSOC);
check('log without update_plan leaves the plan alone', $r['status'] === 200 && str_contains($r['body'], '"plan_updated":0') && $unchanged == ['repetitions' => $planSet['repetitions'], 'load' => $planSet['load']], json_encode($unchanged));
$r = request('POST', '/api/workout/log', $writeBack([
    ['set_id' => $planSet['id'], 'reps' => 11, 'unit' => 'kg', 'load' => '85', 'rest_time' => 90],
    ['set_id' => 999999, 'reps' => 5, 'unit' => 'kg', 'load' => '50', 'rest_time' => 90],
    ['set_id' => $planSet['id'], 'reps' => 3, 'unit' => 'bw', 'load' => null, 'rest_time' => 90],
], true), ['Content-Type' => 'application/json']);
$changed = db()->query("SELECT repetitions, unit, load FROM sets WHERE id={$planSet['id']}")->fetch(PDO::FETCH_ASSOC);
check('update_plan writes reps and load back into the matching set only', $r['status'] === 200 && str_contains($r['body'], '"plan_updated":1') && (int)$changed['repetitions'] === 11 && (float)$changed['load'] === 85.0 && $changed['unit'] === 'kg', "{$r['body']} " . json_encode($changed));

// --- cascade delete --------------------------------------------------------
$r = request('POST', '/deleteWorkout', ['workout_id' => $workoutId]);
check('delete workout', $r['status'] === 303);
check('exercises cascade-deleted', (int)db()->query("SELECT COUNT(*) FROM exercises WHERE workout_id=$workoutId")->fetchColumn() === 0);
check('sets cascade-deleted', (int)db()->query("SELECT COUNT(*) FROM sets WHERE exercise_id=$exerciseId")->fetchColumn() === 0);
check('logs survive workout deletion', (int)db()->query("SELECT COUNT(*) FROM workoutLog WHERE workout_id=$workoutId")->fetchColumn() === 5);
check('logged sets survive workout deletion', (int)db()->query("SELECT COUNT(*) FROM logSets ls JOIN logExercises le ON le.id = ls.log_exercise_id JOIN workoutLog wl ON wl.id = le.log_id WHERE wl.workout_id=$workoutId")->fetchColumn() === 8);

$r = request('GET', '/logs');
check('logs of deleted workout still listed', $r['status'] === 200 && str_contains($r['body'], '>gelöscht<'));

// --- logout ------------------------------------------------------------------
$r = request('POST', '/logout');
check('logout redirects to login', $r['status'] === 303 && $r['location'] === '/login');
$r = request('GET', '/');
check('logged out afterwards', $r['status'] === 303 && $r['location'] === '/login');
check('logout revokes the remember token', (int)db()->query('SELECT COUNT(*) FROM auth_tokens')->fetchColumn() === 0);
check('console deletes the user', str_contains($console('app:user:delete smoke'), 'deleted'));

// --- schema state ----------------------------------------------------------
check('schema version set', (int)db()->query('PRAGMA user_version')->fetchColumn() === 5);
check('sets table has no legacy column', !in_array('measure_unit', array_column(db()->query('PRAGMA table_info(sets)')->fetchAll(PDO::FETCH_ASSOC), 'name'), true));
check('no orphan exercises', (int)db()->query('SELECT COUNT(*) FROM exercises WHERE workout_id NOT IN (SELECT id FROM workouts)')->fetchColumn() === 0);
check('no orphan sets', (int)db()->query('SELECT COUNT(*) FROM sets WHERE exercise_id NOT IN (SELECT id FROM exercises)')->fetchColumn() === 0);

$log = file_get_contents($tmpDir . '/server.log') ?: '';
check('no PHP warnings/errors in server log', !preg_match('/(Warning|Notice|Fatal|Deprecated|Uncaught)/', $log), substr($log, 0, 500));

echo "\n$passes passed, $failures failed\n";
exit($failures === 0 ? 0 : 1);
