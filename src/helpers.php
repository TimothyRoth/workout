<?php

/**
 * HTML-escape a value for output in a template.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Versioned URL for a static asset under /public.
 */
function asset(string $path): string
{
    return e($path) . '?v=' . e(VERSION);
}

/**
 * JSON import map that maps every module in public/js to its versioned URL,
 * so "import ... from 'app/session'" is cache-busted like the entry script.
 */
function import_map(): string
{
    $imports = [];
    foreach (glob(__DIR__ . '/../public/js/*.js') ?: [] as $file) {
        $name = basename($file, '.js');
        $imports['app/' . $name] = '/js/' . $name . '.js?v=' . VERSION;
    }
    return json_encode(['imports' => $imports], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR);
}

/**
 * Inline SVG icon (24px grid, stroked with currentColor).
 */
function icon(string $name, string $class = ''): string
{
    static $paths = [
        'back'    => '<path d="M15 18l-6-6 6-6"/>',
        'chevron' => '<path d="M9 18l6-6-6-6"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'minus'   => '<path d="M5 12h14"/>',
        'close'   => '<path d="M6 6l12 12M6 18L18 6"/>',
        'edit'    => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'play'    => '<path d="M7 4v16l13-8z" fill="currentColor" stroke="none"/>',
        'check'   => '<path d="M20 6 9 17l-5-5"/>',
        'trash'   => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2"/>',
        'timer'   => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 2h6"/>',
        'copy'    => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
        'phone'   => '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>',
        'share'   => '<path d="M12 3v12M8 7l4-4 4 4"/><path d="M4 13v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/>',
        'more'    => '<circle cx="12" cy="5" r="1" fill="currentColor"/><circle cx="12" cy="12" r="1" fill="currentColor"/><circle cx="12" cy="19" r="1" fill="currentColor"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'lock'    => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'eye'     => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M3 3l18 18"/><path d="M10.6 10.6a3 3 0 0 0 4.2 4.2"/><path d="M9.9 5.2A10.7 10.7 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17.4 17.4 0 0 0 2 12s3.5 7 10 7a10 10 0 0 0 4.4-1"/>',
        'logout'  => '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M13 4h6a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-6"/>',
        'alert'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
        'grip'    => '<circle cx="9" cy="6" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="6" r="1.2" fill="currentColor" stroke="none"/><circle cx="9" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="9" cy="18" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="18" r="1.2" fill="currentColor" stroke="none"/>',
        'note'    => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
        'offline' => '<path d="M2 2l20 20"/><path d="M8.5 16.5a5 5 0 0 1 7 0M5 13a10 10 0 0 1 5.2-2.8M12 20h.01"/><path d="M16.7 11.5A10 10 0 0 1 19 13M1.4 8.6A16 16 0 0 1 8 5M22.6 8.6a16 16 0 0 0-8-4.3"/>',
    ];

    $classes = trim('icon ' . $class);
    return sprintf(
        '<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
        e($classes),
        $paths[$name] ?? ''
    );
}

/**
 * Wrap a numeric <input data-step="..."> in minus/plus buttons (see js/stepper.js).
 */
function stepper(string $input, string $class = ''): string
{
    return sprintf(
        '<div class="%s"><button class="stepper-btn" type="button" data-step="-1" aria-label="Weniger" tabindex="-1">%s</button>%s<button class="stepper-btn" type="button" data-step="1" aria-label="Mehr" tabindex="-1">%s</button></div>',
        e(trim('stepper ' . $class)),
        icon('minus', 'icon-sm'),
        $input,
        icon('plus', 'icon-sm')
    );
}

/**
 * Format a number the German way, dropping trailing zeros ("640,5", "12").
 */
function format_number(float|int|string|null $value, int $decimals = 2): string
{
    $formatted = number_format((float)$value, $decimals, ',', '.');
    return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
}

/**
 * Format a UTC "Y-m-d H:i:s" timestamp (as SQLite stores it) in local time.
 */
function local_datetime(?string $utc, string $format = 'd.m.Y H:i'): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone(date_default_timezone_get()))->format($format);
    } catch (Exception) {
        return $utc;
    }
}

/**
 * "heute", "gestern", "vor 3 Tagen", "vor 2 Wochen", "vor 4 Monaten", "vor 1 Jahr" for a UTC timestamp.
 */
function relative_day(?string $utc): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    try {
        $day = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->setTime(0, 0);
    } catch (Exception) {
        return $utc;
    }
    $days = (int)$day->diff(new DateTimeImmutable('today'))->format('%r%a');

    return match (true) {
        $days <= 0 => 'heute',
        $days === 1 => 'gestern',
        $days < 7 => "vor $days Tagen",
        $days < 30 => intdiv($days, 7) === 1 ? 'vor 1 Woche' : 'vor ' . intdiv($days, 7) . ' Wochen',
        $days < 365 => intdiv($days, 30) === 1 ? 'vor 1 Monat' : 'vor ' . intdiv($days, 30) . ' Monaten',
        default => intdiv($days, 365) === 1 ? 'vor 1 Jahr' : 'vor ' . intdiv($days, 365) . ' Jahren',
    };
}

/**
 * Subtitle of a workout row: "Zuletzt gestern · 12 Trainings" or "Noch nie trainiert".
 */
function workout_subtitle(array $workout): string
{
    $count = (int)($workout['session_count'] ?? 0);
    if ($count === 0 || empty($workout['last_trained_at'])) {
        return 'Noch nie trainiert';
    }
    return 'Zuletzt ' . relative_day($workout['last_trained_at']) . ' · ' . $count . ($count === 1 ? ' Training' : ' Trainings');
}

/**
 * <option> list for a unit select; each option carries the label of its load input.
 */
function unit_options(\App\Unit $selected): string
{
    $html = '';
    foreach (\App\Unit::cases() as $unit) {
        $html .= sprintf(
            '<option value="%s" data-load-label="%s" data-load-step="%s"%s>%s</option>',
            e($unit->value),
            e($unit->loadLabel() ?? ''),
            e((string)$unit->loadStep()),
            $unit === $selected ? ' selected' : '',
            e($unit->label())
        );
    }
    return $html;
}

/**
 * Display label for a planned set ("80 kg", "Körpergewicht", "60 s").
 */
function set_label(array $set): string
{
    $unit = \App\Unit::tryFrom((string)($set['unit'] ?? '')) ?? \App\Unit::Kg;
    return $unit->format($set['load'] ?? null);
}

/**
 * One line for a collapsed exercise: "3 × 8 · 80 kg · 90 s", with ranges
 * ("3 × 8–12 · 60–80 kg") when the sets differ and "gemischt" when the units do.
 */
function exercise_summary(array $sets): string
{
    $count = count($sets);
    if ($count === 0) {
        return 'Noch keine Sätze';
    }

    $range = function (array $values, string $suffix = ''): string {
        $min = min($values);
        $max = max($values);
        $text = $min === $max ? format_number($min) : format_number($min) . '–' . format_number($max);
        return $text . $suffix;
    };

    $units = array_unique(array_map(fn($set) => (string)($set['unit'] ?? ''), $sets));
    if (count($units) === 1) {
        $unit = \App\Unit::tryFrom($units[0]) ?? \App\Unit::Kg;
        $loads = array_map(fn($set) => (float)($set['load'] ?? 0), $sets);
        $load = !$unit->needsLoad() ? $unit->short(null)
            : (min($loads) === max($loads) ? $unit->format(min($loads)) : format_number(min($loads)) . '–' . $unit->format(max($loads)));
    } else {
        $load = 'gemischt';
    }

    $reps = $range(array_map(fn($set) => (int)$set['repetitions'], $sets));
    $rest = $range(array_map(fn($set) => (int)$set['rest_time'], $sets), ' s');

    return "$count × $reps · $load · $rest";
}

/**
 * Display label for a set inside a log summary. Summaries written before
 * schema v2 only carry the free-text "measureUnit"; show that unchanged.
 */
function log_set_label(array $set): string
{
    if (isset($set['unit'])) {
        return set_label($set);
    }
    return (string)($set['measureUnit'] ?? '');
}
