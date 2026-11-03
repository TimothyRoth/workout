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
    'WORKOUT_DB=%s php -S 127.0.0.1:%d -t %s > %s 2>&1 & echo $!',
    escapeshellarg($dbPath), $port, escapeshellarg($root . '/public'), escapeshellarg($tmpDir . '/server.log')
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
    return ['status' => $status, 'body' => substr((string)$raw, $headerSize), 'location' => $location];
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

// --- pages render --------------------------------------------------------
$r = request('GET', '/');
check('frontpage 200', $r['status'] === 200, "got {$r['status']}");
check('entry module is versioned', preg_match('/\/js\/app\.js\?v=[0-9a-z]+/', $r['body']) === 1);
check('stylesheet is versioned', preg_match('/\/css\/app\.css\?v=[0-9a-z]+/', $r['body']) === 1);
check('manifest is linked', str_contains($r['body'], 'rel="manifest"'));
$jsModules = array_map(fn($f) => basename($f, '.js'), glob($root . '/public/js/*.js') ?: []);
$importMap = preg_match('/<script type="importmap">(.*?)<\/script>/s', $r['body'], $m) ? json_decode($m[1], true) : null;
check('import map versions every js module', is_array($importMap) && count(array_filter($jsModules, fn($name) => preg_match('/\?v=[0-9a-z]+$/', $importMap['imports']["app/$name"] ?? '') === 1)) === count($jsModules), json_encode($importMap));

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
$r = request('GET', '/logs');
check('legacy log summary still shows its free-text unit', $r['status'] === 200 && str_contains($r['body'], '>90kg<'));
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

$r = request('POST', '/addExercise', ['workout_id' => $workoutId, 'exercise_name' => '  Bankdrücken  ']);
$exerciseId = (int)db()->query("SELECT id FROM exercises WHERE workout_id=$workoutId")->fetchColumn();
check('add exercise redirects to anchor', $r['status'] === 303 && $r['location'] === "/workout?workout_id=$workoutId#exercise-$exerciseId", "loc {$r['location']}");
check('exercise name trimmed', db()->query("SELECT name FROM exercises WHERE id=$exerciseId")->fetchColumn() === 'Bankdrücken');

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

$r = request('POST', '/deleteSet', ['set_id' => $setId]);
check('delete set', $r['status'] === 303 && (int)db()->query("SELECT COUNT(*) FROM sets WHERE id=$setId")->fetchColumn() === 0);

$r = request('GET', "/workout?workout_id=$workoutId");
check('workout page 200', $r['status'] === 200);
check('workout page has stable exercise anchor', str_contains($r['body'], "id=\"exercise-$exerciseId\""));
check('workout page escapes names', str_contains($r['body'], 'Smoke &lt;b&gt;Test&lt;/b&gt;'));
check('workout page shows set labels', str_contains($r['body'], '>80 kg<'));
check('workout page has unit selects', substr_count($r['body'], 'data-unit-select') >= 3);

// --- api -------------------------------------------------------------------
$r = request('GET', "/api/workout/$workoutId");
$plan = json_decode($r['body'], true);
check('api plan 200 json', $r['status'] === 200 && ($plan['status'] ?? '') === 'success');
check('api plan nests sets', count($plan['workout']['exercises'][0]['sets'] ?? []) === 2);
check('api plan exposes unit and load', ($plan['workout']['exercises'][0]['sets'][1]['unit'] ?? '') === 'kg' && (float)($plan['workout']['exercises'][0]['sets'][1]['load'] ?? 0) === 80.0);

$r = request('GET', '/api/workout/999999');
check('api unknown workout 404 json', $r['status'] === 404 && str_contains($r['body'], '"status":"error"'));

$summary = ['exercises' => [['name' => 'Bankdrücken', 'sets' => [['reps' => '8', 'unit' => 'kg', 'load' => '80', 'breaktime' => '120'], ['reps' => '12', 'unit' => 'bw', 'load' => null, 'breaktime' => '60']]]]];
$r = request('POST', '/api/workout/log', json_encode(['workout_id' => $workoutId, 'duration' => 42, 'workload' => 640.5, 'summary' => $summary]), ['Content-Type' => 'application/json']);
check('api log 200', $r['status'] === 200, "got {$r['status']} {$r['body']}");
$logId = (int)db()->query("SELECT id FROM workoutLog WHERE workout_id=$workoutId ORDER BY id DESC LIMIT 1")->fetchColumn();
check('log stored with workout name snapshot', $logId > 0 && db()->query("SELECT workout_name FROM workoutLog WHERE id=$logId")->fetchColumn() === $name);

$r = request('POST', '/api/workout/log', json_encode(['workout_id' => $workoutId, 'duration' => 42, 'workload' => 640]), ['Content-Type' => 'application/json']);
check('api log without summary 400', $r['status'] === 400, "got {$r['status']}");

$r = request('POST', '/api/workout/log', json_encode(['workout_id' => $workoutId, 'duration' => 30, 'workload' => 0, 'summary' => $summary]), ['Content-Type' => 'application/json']);
check('api log with zero workload accepted', $r['status'] === 200);

// --- logs page math -------------------------------------------------------
$r = request('POST', '/api/workout/log', json_encode(['workout_id' => $workoutId, 'duration' => 30, 'workload' => 700, 'summary' => $summary]), ['Content-Type' => 'application/json']);
$r = request('GET', '/logs');
check('logs page 200 with zero-workload entry', $r['status'] === 200, "got {$r['status']}");
check('logs page shows first entry label', str_contains($r['body'], 'Erster Eintrag'));
check('logs page shows new record label', str_contains($r['body'], 'Neuer Bestwert!'));
check('logs page shows below-best percentage', str_contains($r['body'], '-100,0% vom Bestwert'));
check('logs page renders structured set labels', str_contains($r['body'], '>80 kg<') && str_contains($r['body'], '>Körpergewicht<'));

// --- cascade delete --------------------------------------------------------
$r = request('POST', '/deleteWorkout', ['workout_id' => $workoutId]);
check('delete workout', $r['status'] === 303);
check('exercises cascade-deleted', (int)db()->query("SELECT COUNT(*) FROM exercises WHERE workout_id=$workoutId")->fetchColumn() === 0);
check('sets cascade-deleted', (int)db()->query("SELECT COUNT(*) FROM sets WHERE exercise_id=$exerciseId")->fetchColumn() === 0);
check('logs survive workout deletion', (int)db()->query("SELECT COUNT(*) FROM workoutLog WHERE workout_id=$workoutId")->fetchColumn() === 3);

$r = request('GET', '/logs');
check('logs of deleted workout still listed', $r['status'] === 200 && str_contains($r['body'], '>gelöscht<'));

// --- schema state ----------------------------------------------------------
check('schema version set', (int)db()->query('PRAGMA user_version')->fetchColumn() === 2);
check('sets table has no legacy column', !in_array('measure_unit', array_column(db()->query('PRAGMA table_info(sets)')->fetchAll(PDO::FETCH_ASSOC), 'name'), true));
check('no orphan exercises', (int)db()->query('SELECT COUNT(*) FROM exercises WHERE workout_id NOT IN (SELECT id FROM workouts)')->fetchColumn() === 0);
check('no orphan sets', (int)db()->query('SELECT COUNT(*) FROM sets WHERE exercise_id NOT IN (SELECT id FROM exercises)')->fetchColumn() === 0);

$log = file_get_contents($tmpDir . '/server.log') ?: '';
check('no PHP warnings/errors in server log', !preg_match('/(Warning|Notice|Fatal|Deprecated|Uncaught)/', $log), substr($log, 0, 500));

echo "\n$passes passed, $failures failed\n";
exit($failures === 0 ? 0 : 1);
