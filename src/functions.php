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
 * Escape and fold a DESCRIPTION value per RFC 5545.
 */
function doy_format_description(string $text): string
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(["\r\n", "\r", "\n"], '\n', $text);
    $text = str_replace([',', ';'], ['\\,', '\\;'], $text);

    $line = 'DESCRIPTION:' . $text;
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
 * Return true when $year is a leap year.
 */
function doy_is_leap_year(int $year): bool
{
    return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
}

/**
 * Build the SUMMARY line shown in the calendar grid.
 * Example: "124/365 · 34%"
 */
function doy_event_summary(int $dayOfYear, int $totalDays, int $percent): string
{
    return "{$dayOfYear}/{$totalDays} · {$percent}%";
}

/**
 * Build the DESCRIPTION shown in event detail views.
 * Example: "Day 124 of 365 · 34% complete — 241 days remaining · Week 20 · 2026"
 */
function doy_event_description(int $dayOfYear, int $totalDays, int $percent, int $daysLeft, int $isoWeek, int $year): string
{
    return "Day {$dayOfYear} of {$totalDays} · {$percent}% complete"
        . " — {$daysLeft} days remaining · Week {$isoWeek} · {$year}";
}
