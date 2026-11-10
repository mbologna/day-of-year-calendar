<?php

declare(strict_types=1);

/**
 * Pure helper functions for the Day of Year iCalendar generator.
 * No side-effects, no I/O — safe to require in tests.
 */

const DOY_DEFAULT_TIMEZONE = 'Europe/Rome';

function doy_verify_token(string $provided): bool
{
    return hash_equals(AUTH_TOKEN, $provided);
}

function doy_sanitize_timezone(string $value): string
{
    return in_array($value, timezone_identifiers_list(), true) ? $value : DOY_DEFAULT_TIMEZONE;
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
 * @return array{day: int, total: int, percent: int, left: int, week: int, weekYear: int, year: int, quarter: int, quarterDay: int, quarterTotal: int}
 */
function doy_day_stats(int $timestamp): array
{
    $year  = (int) date('Y', $timestamp);
    $day   = (int) date('z', $timestamp) + 1; // date('z') is 0-indexed
    $total = doy_is_leap_year($year) ? 366 : 365;

    $quarter      = intdiv((int) date('n', $timestamp) - 1, 3) + 1;
    $firstMonth   = ($quarter - 1) * 3 + 1;
    $quarterStart = (int) date('z', mktime(0, 0, 0, $firstMonth, 1, $year)) + 1;
    $quarterTotal = 0;
    for ($month = $firstMonth; $month < $firstMonth + 3; $month++) {
        $quarterTotal += (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    }

    return [
        'day'          => $day,
        'total'        => $total,
        'percent'      => doy_year_percent($day, $total),
        'left'         => $total - $day,
        'week'         => (int) date('W', $timestamp),
        'weekYear'     => (int) date('o', $timestamp), // ISO week-numbering year
        'year'         => $year,
        'quarter'      => $quarter,
        'quarterDay'   => $day - $quarterStart + 1,
        'quarterTotal' => $quarterTotal,
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
 * Name the notable day $dayOfYear is, or return null on an ordinary day.
 * No two milestones share a day.
 *
 * - Halfway is day 183 in both year lengths: the first day whose
 *   rounded-down percentage reaches 50%.
 * - "Final 100 days" falls on the day with 99 days left, since the
 *   days-left line counts the days after today; on the day before, that
 *   line already reads "100 days left".
 * - The last day of the year needs no milestone, nor does the last day of
 *   Q4: the days-left line already reads "Last day of <year>".
 */
function doy_milestone(int $dayOfYear, int $totalDays, int $year): ?string
{
    $leap = $totalDays - 365; // 1 in a leap year, else 0

    return match (true) {
        $dayOfYear === 1                            => "First day of {$year}",
        $leap === 1 && $dayOfYear === 60            => 'Leap day',
        $dayOfYear === intdiv($totalDays + 1, 2)    => "Halfway through {$year}",
        in_array($dayOfYear, [100, 200, 300], true) => "{$dayOfYear}th day of {$year}",
        $dayOfYear === $totalDays - 99              => "Final 100 days of {$year}",
        $dayOfYear === 90 + $leap                   => 'Last day of Q1',
        $dayOfYear === 181 + $leap                  => 'Last day of Q2',
        $dayOfYear === 273 + $leap                  => 'Last day of Q3',
        default                                     => null,
    };
}

/**
 * Build the DESCRIPTION shown in event detail views, one fact per line,
 * from a doy_day_stats() array.
 * Example (2 July 2026):
 *   Halfway through 2026
 *   Day 183 of 365
 *   ▓▓▓▓▓▓▓▓▓▓░░░░░░░░░░ 50%
 *   182 days left in 2026
 *   Q3 · day 2 of 92
 *   Week 27
 *
 * The milestone line only appears on notable days (see doy_milestone()).
 * The week line names its ISO year only when it differs from the calendar
 * year, e.g. "Week 53 of 2026" on 1 January 2027.
 *
 * @param array{day: int, total: int, percent: int, left: int, week: int, weekYear: int, year: int, quarter: int, quarterDay: int, quarterTotal: int} $d
 */
function doy_event_description(array $d): string
{
    $left = match ($d['left']) {
        0       => "Last day of {$d['year']}",
        1       => "1 day left in {$d['year']}",
        default => "{$d['left']} days left in {$d['year']}",
    };
    $week = $d['weekYear'] === $d['year']
        ? "Week {$d['week']}"
        : "Week {$d['week']} of {$d['weekYear']}";

    return implode("\n", array_filter([
        doy_milestone($d['day'], $d['total'], $d['year']),
        "Day {$d['day']} of {$d['total']}",
        doy_progress_bar($d['percent']) . " {$d['percent']}%",
        $left,
        "Q{$d['quarter']} · day {$d['quarterDay']} of {$d['quarterTotal']}",
        $week,
    ]));
}
