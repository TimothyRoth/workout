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
