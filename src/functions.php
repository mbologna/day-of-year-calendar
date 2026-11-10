<?php

declare(strict_types=1);

/**
 * Pure helper functions for the Day of Year iCalendar generator.
 * No side-effects, no I/O — safe to require in tests.
 */

function doy_verify_token(string $provided): bool
{
    return hash_equals(AUTH_TOKEN, $provided);
}

function doy_sanitize_timezone(string $value): string
{
    return in_array($value, timezone_identifiers_list(), true) ? $value : 'Europe/Rome';
}

function doy_sanitize_text(string $value, int $maxLength = 200): string
{
    $clean = strip_tags($value);
    $clean = str_replace(["\r\n", "\r", "\n"], ' ', $clean);
    return substr($clean, 0, $maxLength);
}

/**
 * Escape a TEXT property value per RFC 5545 §3.3.11.
 */
function doy_escape_text(string $text): string
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(["\r\n", "\r", "\n"], '\n', $text);
    return str_replace([',', ';'], ['\\,', '\\;'], $text);
}

/**
 * Fold a content line to 75 bytes per RFC 5545 §3.1, without splitting
 * multi-byte characters, and terminate it with CRLF.
 */
function doy_fold_line(string $line): string
{
    $result = '';
    $current_line = '';
    $byte_count = 0;
    $char_count = mb_strlen($line, 'UTF-8');

    for ($i = 0; $i < $char_count; $i++) {
        $char = mb_substr($line, $i, 1, 'UTF-8');
        $char_bytes = strlen($char);
        if ($byte_count + $char_bytes > 75) {
            $result .= $current_line . "\r\n";
            $current_line = ' ' . $char;
            $byte_count = 1 + $char_bytes;
        } else {
            $current_line .= $char;
            $byte_count += $char_bytes;
        }
    }

    return $result . $current_line . "\r\n";
}

/**
 * Escape and fold a DESCRIPTION value per RFC 5545.
 */
function doy_format_description(string $text): string
{
    return doy_fold_line('DESCRIPTION:' . doy_escape_text($text));
}

/**
 * Return true when $year is a leap year.
 */
function doy_is_leap_year(int $year): bool
{
    return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
}

/**
 * Share of the year completed by the end of day $dayOfYear, rounded down
 * so that 100% only ever appears on the last day.
 */
function doy_year_percent(int $dayOfYear, int $totalDays): int
{
    return intdiv($dayOfYear * 100, $totalDays);
}

/**
 * Everything the event strings need to know about the day at $timestamp,
 * in the current default timezone.
 *
 * @return array{day: int, total: int, percent: int, left: int, week: int, weekYear: int, year: int}
 */
function doy_day_stats(int $timestamp): array
{
    $year  = (int) date('Y', $timestamp);
    $day   = (int) date('z', $timestamp) + 1; // date('z') is 0-indexed
    $total = doy_is_leap_year($year) ? 366 : 365;

    return [
        'day'      => $day,
        'total'    => $total,
        'percent'  => doy_year_percent($day, $total),
        'left'     => $total - $day,
        'week'     => (int) date('W', $timestamp),
        'weekYear' => (int) date('o', $timestamp), // ISO week-numbering year
        'year'     => $year,
    ];
}

/**
 * Build the SUMMARY line shown in the calendar grid.
 * Example: "124/365 · 33%"
 */
function doy_event_summary(int $dayOfYear, int $totalDays, int $percent): string
{
    return "{$dayOfYear}/{$totalDays} · {$percent}%";
}

/**
 * Render $percent as a 20-cell text progress bar, e.g. "▓▓▓▓▓▓░░░░░░░░░░░░░░".
 */
function doy_progress_bar(int $percent, int $width = 20): string
{
    $filled = intdiv(max(0, min(100, $percent)) * $width, 100);
    return str_repeat('▓', $filled) . str_repeat('░', $width - $filled);
}

/**
 * Build the DESCRIPTION shown in event detail views, one fact per line.
 * Example:
 *   Day 124 of 365
 *   ▓▓▓▓▓▓░░░░░░░░░░░░░░ 33%
 *   241 days left in 2026
 *   Week 19
 *
 * The week line names its ISO year only when it differs from the calendar
 * year, e.g. "Week 53 of 2026" on 1 January 2027.
 */
function doy_event_description(int $dayOfYear, int $totalDays, int $percent, int $daysLeft, int $isoWeek, int $isoYear, int $year): string
{
    $left = match ($daysLeft) {
        0       => "Last day of {$year}",
        1       => "1 day left in {$year}",
        default => "{$daysLeft} days left in {$year}",
    };
    $week = $isoYear === $year ? "Week {$isoWeek}" : "Week {$isoWeek} of {$isoYear}";

    return implode("\n", [
        "Day {$dayOfYear} of {$totalDays}",
        doy_progress_bar($percent) . " {$percent}%",
        $left,
        $week,
    ]);
}
