<?php
/**
 * Parity harness for the Symfony port (temporary, removed before the merge).
 *
 *   php8.4 tests/parity/parity.php <legacy|symfony> <app root> <source db|empty> <out dir>
 *
 * Boots one app on a copy of the source database, replays a fixed sequence of requests that
 * touches every route, and writes one file per response (status, relevant headers, normalised
 * body) plus a dump of every table. Run it for the old and the new app and `diff -ru` the two
 * output directories: every difference is a behaviour change.
 */

declare(strict_types=1);

[$_, $kind, $root, $source, $out] = $argv + [null, null, null, null, null];
if (!in_array($kind, ['legacy', 'symfony'], true) || !is_dir((string)$root) || $source === null || $out === null) {
    fwrite(STDERR, "Usage: parity.php <legacy|symfony> <app root> <source db|empty> <out dir>\n");
    exit(2);
}
$root = realpath($root);
if (is_dir($out)) {
    exec('rm -rf ' . escapeshellarg($out));
}
mkdir($out . '/responses', 0777, true);
$out = realpath($out);
$work = $out . '/work';
mkdir($work);
$dbPath = $work . '/app.db';
if ($source !== 'empty') {
    $source = realpath($source) ?: $source;
    copy($source, $dbPath) || exit("Could not copy $source\n");
}

$env = $kind === 'legacy'
    ? ['WORKOUT_DB' => $dbPath]
    : [
        'APP_ENV' => 'prod',
        'APP_DEBUG' => '0',
        'APP_SECRET' => 'parity-secret-parity-secret-0123',
        'DATABASE_URL' => 'sqlite:///' . $dbPath,
        'APP_CACHE_DIR' => $work . '/cache',
        'APP_LOG_DIR' => $work . '/log',
    ];
$envPrefix = implode(' ', array_map(fn($k, $v) => $k . '=' . escapeshellarg($v), array_keys($env), $env));

function sh(string $command): string
{
    exec($command . ' 2>&1', $lines, $code);
    if ($code !== 0) {
        fwrite(STDERR, "Command failed ($code): $command\n" . implode("\n", $lines) . "\n");
        exit(1);
    }
    return implode("\n", $lines);
}

if ($kind === 'symfony') {
    sh("cd " . escapeshellarg($root) . " && $envPrefix php8.4 bin/console doctrine:migrations:migrate --no-interaction");
}
sh("cd " . escapeshellarg($root) . " && $envPrefix php8.4 bin/console app:user:create parity 'parity-geheim'");

$port = 19000 + random_int(0, 999);
$router = $kind === 'legacy' ? $root . '/public/index.php' : $root . '/tests/router.php';
$pid = (int)trim(sh(sprintf(
    '%s php8.4 -d variables_order=EGPCS -S 127.0.0.1:%d -t %s %s > %s 2>&1 & echo $!',
    $envPrefix, $port, escapeshellarg($root . '/public'), escapeshellarg($router), escapeshellarg($work . '/server.log')
)));
register_shutdown_function(fn() => $pid > 0 && posix_kill($pid, SIGTERM));

$base = "http://127.0.0.1:$port";
for ($i = 0; $i < 50 && @file_get_contents($base . '/login') === false; $i++) {
    usleep(100_000);
}

$jar = $work . '/cookies.txt';
$step = 0;

/**
 * One request; writes responses/NNN-<label>.txt and returns [status, location, body].
 */
function req(string $label, string $method, string $path, array|string|null $body = null, array $headers = []): array
{
    global $base, $jar, $out, $step;
    if ($method === 'POST' && !array_key_exists('Origin', $headers)) {
        $headers['Origin'] = $base;
    }
    if (is_string($body) && !isset($headers['Content-Type'])) {
        $headers['Content-Type'] = 'application/json';
    }
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_HTTPHEADER => array_map(fn($k, $v) => "$k: $v", array_keys(array_filter($headers, fn($v) => $v !== null)), array_filter($headers, fn($v) => $v !== null)),
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
    }
    $raw = (string)curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $rawHeaders = substr($raw, 0, $size);
    $content = substr($raw, $size);
    $location = preg_match('/^Location:\s*(.+)$/mi', $rawHeaders, $m) ? trim(preg_replace('#^https?://[^/]+#', '', trim($m[1]))) : null;

    $report = sprintf("%s %s\nstatus: %d\n", $method, $path, $status);
    foreach (['Location', 'Content-Type', 'Cache-Control', 'Content-Disposition', 'X-Content-Type-Options', 'Referrer-Policy', 'X-Frame-Options', 'Service-Worker-Allowed'] as $name) {
        if (preg_match('/^' . $name . ':\s*(.+)$/mi', $rawHeaders, $h)) {
            $value = $name === 'Location' ? $location : trim($h[1]);
            $report .= strtolower($name) . ': ' . mask((string)$value) . "\n";
        }
    }
    preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]*)(.*)$/mi', $rawHeaders, $cookies, PREG_SET_ORDER);
    foreach ($cookies as $c) {
        $flags = strtolower(implode(';', array_map('trim', array_filter(explode(';', $c[3]), fn($f) => !preg_match('/^\s*(expires|max-age)=/i', $f)))));
        $report .= 'set-cookie: ' . $c[1] . ($c[2] === '' || $c[2] === 'deleted' ? ' (cleared)' : '') . ' ' . $flags . "\n";
    }
    $report .= "\n" . normalise($content, $rawHeaders);

    $step++;
    file_put_contents(sprintf('%s/responses/%03d-%s.txt', $out, $step, $label), $report);
    return [$status, $location, $content];
}

function mask(string $text): string
{
    $text = preg_replace('/\?v=[0-9a-z]+/', '?v=VERSION', $text);
    $text = preg_replace('/workout-export-\d{4}-\d{2}-\d{2}/', 'workout-export-DATE', $text);
    $text = preg_replace('/\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}Z?/', 'TIMESTAMP', $text);
    return preg_replace('/(\d{2}\.\d{2}\.\d{4}) \d{2}:\d{2}/', '$1 HH:MM', $text);
}

/** HTML: one tag or text run per line, whitespace-only text dropped; JSON: re-indented; JS: as is. */
function normalise(string $content, string $headers): string
{
    $content = mask($content);
    $content = preg_replace("/const CACHE = 'workout-[0-9a-z]+'/", "const CACHE = 'workout-VERSION'", $content);
    $content = preg_replace('/version [0-9a-z]+ \(/', 'version VERSION (', $content);
    if (stripos($headers, 'application/json') !== false) {
        $data = json_decode($content, true);
        return $data === null ? $content : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) . "\n";
    }
    if (stripos($headers, 'text/html') === false) {
        return $content;
    }
    $lines = [];
    $inTextarea = false;
    foreach (preg_split('/(<[^>]*>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $token) {
        if ($token[0] === '<') {
            $lines[] = preg_replace('/\s+/', ' ', $token);
            $inTextarea = preg_match('/^<textarea\b/i', $token) ? true : (preg_match('#^</textarea#i', $token) ? false : $inTextarea);
            continue;
        }
        if ($inTextarea) {
            $lines[] = 'TEXTAREA:' . json_encode($token, JSON_UNESCAPED_UNICODE);
            continue;
        }
        if (trim($token) !== '') {
            $lines[] = preg_replace('/\s+/', ' ', $token);
        }
    }
    return implode("\n", $lines) . "\n";
}

function db(): PDO
{
    global $dbPath;
    return new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}

function ids(string $sql): array
{
    return array_map('intval', db()->query($sql)->fetchAll(PDO::FETCH_COLUMN));
}

function pages(string $label): void
{
    req("$label-front", 'GET', '/');
    foreach (ids('SELECT id FROM workouts ORDER BY id') as $id) {
        req("$label-workout-$id", 'GET', "/workout?workout_id=$id");
        req("$label-api-workout-$id", 'GET', "/api/workout/$id", null, ['Accept' => 'application/json']);
    }
    req("$label-logs", 'GET', '/logs');
    req("$label-export", 'GET', '/export.json');
    req("$label-sw", 'GET', '/sw.js');
    req("$label-404", 'GET', '/nope');
    req("$label-400", 'GET', '/workout?workout_id=abc');
    req("$label-workout-missing", 'GET', '/workout?workout_id=999999');
    req("$label-api-missing", 'GET', '/api/workout/999999', null, ['Accept' => 'application/json']);
}

$json = fn(array $data) => json_encode($data, JSON_UNESCAPED_UNICODE);

// --- anonymous -----------------------------------------------------------------
req('anon-login', 'GET', '/login');
req('anon-api', 'GET', '/api/workout/1', null, ['Accept' => 'application/json']);
req('anon-sw', 'GET', '/sw.js');
req('anon-page', 'GET', '/workout?workout_id=1');
req('login-wrong', 'POST', '/login', ['username' => 'parity', 'password' => 'falsch']);
req('login-error-shown', 'GET', '/login');
req('login-error-gone', 'GET', '/login');
req('login', 'POST', '/login', ['username' => 'PARITY', 'password' => 'parity-geheim', 'remember' => '1']);
req('login-when-logged-in', 'GET', '/login');
file_put_contents($jar, implode("\n", array_filter(file($jar, FILE_IGNORE_NEW_LINES) ?: [], fn($l) => !str_contains($l, 'workout_session'))) . "\n");
req('remembered', 'GET', '/');

pages('before');

// --- plan editing --------------------------------------------------------------
$name = 'Parität <b>Test</b> „Ü“ & Co';
req('add-workout', 'POST', '/addWorkout', ['workout_name' => "  $name  "]);
req('add-workout-empty', 'POST', '/addWorkout', ['workout_name' => '   ']);
req('add-workout-foreign', 'POST', '/addWorkout', ['workout_name' => 'evil'], ['Origin' => 'https://attacker.example']);
req('add-workout-no-origin', 'POST', '/addWorkout', ['workout_name' => 'evil'], ['Origin' => null]);
$w = max(ids('SELECT id FROM workouts'));

foreach (['Bankdrücken' => " Sitz auf 4\r\nenger Griff ", 'Klimmzüge' => '', 'Plank' => '', 'Laufband' => '', 'Leer' => ''] as $exercise => $notes) {
    req('add-exercise', 'POST', '/addExercise', ['workout_id' => $w, 'exercise_name' => "  $exercise ", 'exercise_notes' => $notes]);
}
[$e1, $e2, $e3, $e4, $e5] = ids("SELECT id FROM exercises WHERE workout_id = $w ORDER BY id");
$set = fn(int $e, int $amount, $reps, string $unit, $load, int $rest) => ['exercise_id' => $e, 'amount' => $amount, 'repetitions' => $reps, 'unit' => $unit, 'load' => $load, 'rest_time' => $rest];
req('add-sets-kg', 'POST', '/addSet', $set($e1, 3, 8, 'kg', 80, 120));
req('add-set-kg-comma', 'POST', '/addSet', $set($e1, 1, 10, 'kg', '82,5', 90));
req('add-sets-bw', 'POST', '/addSet', $set($e2, 3, 12, 'bw', 50, 60));
req('add-sets-s', 'POST', '/addSet', $set($e3, 2, 1, 's', 45, 30));
req('add-set-min', 'POST', '/addSet', $set($e4, 1, 1, 'min', 20, 0));
req('add-set-too-many', 'POST', '/addSet', $set($e1, 500, 8, 'kg', 80, 120));
req('add-set-bad-reps', 'POST', '/addSet', $set($e1, 1, 'abc', 'kg', 80, 120));
req('add-set-bad-unit', 'POST', '/addSet', $set($e1, 1, 8, 'lbs', 80, 120));
req('add-set-missing-load', 'POST', '/addSet', ['exercise_id' => $e1, 'amount' => 1, 'repetitions' => 8, 'unit' => 'kg', 'rest_time' => 120]);
req('add-set-unknown-exercise', 'POST', '/addSet', $set(999999, 1, 8, 'kg', 80, 120));

$s1 = ids("SELECT id FROM sets WHERE exercise_id = $e1 ORDER BY position, id");
req('edit-set', 'POST', '/editSet', ['set_id' => $s1[0], 'repetitions' => 10, 'unit' => 'kg', 'load' => '82,5', 'rest_time' => 90]);
req('edit-set-to-min', 'POST', '/editSet', ['set_id' => $s1[3], 'repetitions' => 1, 'unit' => 'min', 'load' => 3, 'rest_time' => 30]);
req('duplicate-set', 'POST', '/duplicateSet', ['set_id' => $s1[0]]);
$s1 = ids("SELECT id FROM sets WHERE exercise_id = $e1 ORDER BY position, id");
req('reorder-sets', 'POST', '/api/reorder', $json(['type' => 'sets', 'parent_id' => $e1, 'ids' => array_reverse($s1)]), ['Accept' => 'application/json']);
req('reorder-exercises', 'POST', '/api/reorder', $json(['type' => 'exercises', 'parent_id' => $w, 'ids' => [$e2, $e1, $e3, $e4, $e5]]), ['Accept' => 'application/json']);
req('reorder-missing-ids', 'POST', '/api/reorder', $json(['type' => 'sets', 'parent_id' => $e1, 'ids' => [$s1[0]]]), ['Accept' => 'application/json']);
req('reorder-bad-ids', 'POST', '/api/reorder', $json(['type' => 'sets', 'parent_id' => $e1, 'ids' => 'nope']), ['Accept' => 'application/json']);
req('reorder-bad-type', 'POST', '/api/reorder', $json(['type' => 'workouts', 'parent_id' => $e1, 'ids' => [1]]), ['Accept' => 'application/json']);
req('reorder-unknown-parent', 'POST', '/api/reorder', $json(['type' => 'exercises', 'parent_id' => 999999, 'ids' => [1]]), ['Accept' => 'application/json']);
req('reorder-bad-json', 'POST', '/api/reorder', '{nope', ['Accept' => 'application/json']);
req('edit-exercise-notes-too-long', 'POST', '/editExercise', ['exercise_id' => $e1, 'exercise_name' => 'Bankdrücken', 'exercise_notes' => str_repeat('x', 501)]);
req('edit-exercise', 'POST', '/editExercise', ['exercise_id' => $e2, 'exercise_name' => 'Klimmzüge  eng', 'exercise_notes' => '']);
req('edit-workout', 'POST', '/editWorkout', ['workout_id' => $w, 'workout_name' => $name . ' (neu)']);
req('edit-workout-unknown', 'POST', '/editWorkout', ['workout_id' => 999999, 'workout_name' => 'x']);

// --- sessions ----------------------------------------------------------------------
$planSet = ids("SELECT id FROM sets WHERE exercise_id = $e1 AND unit = 'kg' ORDER BY position, id")[0];
$session = fn(int $workout, array $exercises, bool $updatePlan, string $notes) => $json([
    'workout_id' => $workout, 'duration' => 42, 'notes' => $notes, 'update_plan' => $updatePlan, 'exercises' => $exercises,
]);
$exercisesA = [
    ['exercise_id' => $e1, 'name' => 'Bankdrücken', 'sets' => [
        ['set_id' => $planSet, 'reps' => 8, 'unit' => 'kg', 'load' => '80', 'rest_time' => 120],
        ['reps' => 7, 'unit' => 'kg', 'load' => '80,5', 'rest_time' => 120],
    ]],
    ['exercise_id' => $e2, 'name' => 'Klimmzüge eng', 'sets' => [['reps' => 12, 'unit' => 'bw', 'load' => null, 'rest_time' => 60]]],
    ['exercise_id' => $e3, 'name' => 'Plank', 'sets' => [['reps' => 1, 'unit' => 's', 'load' => 45, 'rest_time' => 30]]],
    ['exercise_id' => null, 'name' => 'Spontan', 'sets' => [['reps' => 0, 'unit' => 'bw', 'load' => null, 'rest_time' => 0]]],
    ['exercise_id' => $e4, 'name' => 'Laufband', 'sets' => []],
];
req('api-plan-first', 'GET', "/api/workout/$w", null, ['Accept' => 'application/json']);
req('log-a', 'POST', '/api/workout/log', $session($w, $exercisesA, false, " Lief gut,\r\nSchulter zwickt "), ['Accept' => 'application/json']);
$exercisesB = $exercisesA;
$exercisesB[0]['sets'][0] = ['set_id' => $planSet, 'reps' => 11, 'unit' => 'kg', 'load' => '85', 'rest_time' => 90];
$exercisesB[0]['sets'][] = ['set_id' => 999999, 'reps' => 5, 'unit' => 'kg', 'load' => '50', 'rest_time' => 90];
req('log-b-update-plan', 'POST', '/api/workout/log', $session($w, $exercisesB, true, ''), ['Accept' => 'application/json']);
req('log-no-exercises', 'POST', '/api/workout/log', $json(['workout_id' => $w, 'duration' => 42]), ['Accept' => 'application/json']);
req('log-bad-unit', 'POST', '/api/workout/log', $session($w, [['exercise_id' => $e1, 'name' => 'X', 'sets' => [['reps' => 1, 'unit' => 'lbs', 'load' => 1, 'rest_time' => 0]]]], false, ''), ['Accept' => 'application/json']);
req('log-no-done-sets', 'POST', '/api/workout/log', $session($w, [['exercise_id' => $e1, 'name' => 'X', 'sets' => []]], false, ''), ['Accept' => 'application/json']);
req('log-unknown-workout', 'POST', '/api/workout/log', $session(999999, $exercisesA, false, ''), ['Accept' => 'application/json']);
req('api-plan-after-logs', 'GET', "/api/workout/$w", null, ['Accept' => 'application/json']);

req('duplicate-workout', 'POST', '/duplicateWorkout', ['workout_id' => $w]);
$copy = max(ids('SELECT id FROM workouts'));
[$logA] = ids("SELECT id FROM workoutLog WHERE workout_id = $w ORDER BY id");
$logSets = ids("SELECT ls.id FROM logSets ls JOIN logExercises le ON le.id = ls.log_exercise_id WHERE le.log_id = $logA ORDER BY le.position, ls.position");
req('edit-log', 'POST', '/editLog', ['log_id' => $logA, 'duration' => 25, 'notes' => "korrigiert\r\nzweite Zeile", 'sets' => [
    $logSets[0] => ['reps' => 9, 'load' => '81,5'], $logSets[2] => ['reps' => 13, 'load' => '999'], 999999 => ['reps' => 1, 'load' => '1'],
]]);
req('edit-log-bad-reps', 'POST', '/editLog', ['log_id' => $logA, 'duration' => 25, 'sets' => [$logSets[0] => ['reps' => 'acht', 'load' => '80']]]);
req('edit-log-unknown', 'POST', '/editLog', ['log_id' => 999999, 'duration' => 25]);

pages('middle');

$copyExercises = ids("SELECT id FROM exercises WHERE workout_id = $copy ORDER BY position, id");
req('log-copy', 'POST', '/api/workout/log', $session($copy, [['exercise_id' => $copyExercises[1], 'name' => 'Bankdrücken', 'sets' => [['reps' => 5, 'unit' => 'kg', 'load' => 100, 'rest_time' => 180]]]], false, 'Kopie'), ['Accept' => 'application/json']);
req('log-to-delete', 'POST', '/api/workout/log', $session($w, $exercisesA, false, 'weg damit'), ['Accept' => 'application/json']);
req('delete-log', 'POST', '/deleteLog', ['log_id' => max(ids('SELECT id FROM workoutLog'))]);
req('delete-log-unknown', 'POST', '/deleteLog', ['log_id' => 999999]);
req('delete-workout-with-logs', 'POST', '/deleteWorkout', ['workout_id' => $copy]);
req('delete-set', 'POST', '/deleteSet', ['set_id' => ids("SELECT id FROM sets WHERE exercise_id = $e3 ORDER BY id")[0]]);
req('delete-exercise', 'POST', '/deleteExercise', ['exercise_id' => $e5]);
req('old-move-route', 'POST', '/moveSet', ['set_id' => $planSet, 'direction' => 'up']);
req('get-on-post-route', 'GET', '/addWorkout');

pages('after');

req('logout', 'POST', '/logout');
req('after-logout', 'GET', '/');
req('api-after-logout', 'GET', "/api/workout/$w", null, ['Accept' => 'application/json']);

// --- database dump -----------------------------------------------------------------
$pdo = db();
$dump = 'user_version ' . $pdo->query('PRAGMA user_version')->fetchColumn() . "\n";
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name <> 'doctrine_migration_versions' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $dump .= "\n== $table\n";
    foreach ($pdo->query("PRAGMA table_info(\"$table\")") as $col) {
        $dump .= sprintf("col %s %s notnull=%d default=%s pk=%d\n", $col['name'], $col['type'], $col['notnull'], var_export($col['dflt_value'], true), $col['pk']);
    }
    foreach ($pdo->query("PRAGMA foreign_key_list(\"$table\")") as $fk) {
        $dump .= sprintf("fk %s -> %s(%s) on delete %s\n", $fk['from'], $fk['table'], $fk['to'], $fk['on_delete']);
    }
    $indexes = $pdo->query("PRAGMA index_list(\"$table\")")->fetchAll();
    usort($indexes, fn($a, $b) => strcmp($a['name'], $b['name']));
    foreach ($indexes as $index) {
        $columns = array_column($pdo->query("PRAGMA index_info(\"{$index['name']}\")")->fetchAll(), 'name');
        $dump .= sprintf("index %s unique=%d (%s)\n", str_starts_with($index['name'], 'sqlite_autoindex') ? 'auto' : $index['name'], $index['unique'], implode(', ', $columns));
    }
    if ($table === 'auth_tokens') {
        $dump .= "rows not compared (remember-me tokens are replaced by a signed cookie)\n";
        continue;
    }
    foreach ($pdo->query("SELECT * FROM \"$table\" ORDER BY 1") as $row) {
        if (isset($row['password_hash'])) {
            $row['password_hash'] = password_verify('parity-geheim', $row['password_hash']) ? 'HASH(parity)' : 'HASH';
        }
        $dump .= mask(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)) . "\n";
    }
}
file_put_contents($out . '/database.txt', $dump);

$log = (string)@file_get_contents($work . '/server.log');
$problems = preg_match_all('/(Warning|Notice|Fatal|Deprecated|Uncaught)/', $log);
echo "$kind: $step responses, " . count($tables) . " tables, $problems PHP problems in the server log\n";
