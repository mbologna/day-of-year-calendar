<?php

declare(strict_types=1);

/**
 * Day of Year iCalendar Generator
 *
 * Generates an iCalendar feed where each day has an all-day event
 * showing its position in the year: "124/365 · 34%".
 *
 * Standalone application — can be moved to any directory.
 * Configuration via AUTH_TOKEN environment variable (Cloud Run)
 * or config/config.php (self-hosted).
 *
 * URL parameters:
 *   token    – authentication token (required)
 *   zone     – timezone identifier (default: Europe/Rome)
 *   location – optional display name shown in the calendar title
 */

// ============================================================================
// CONFIGURATION
// Environment variables take precedence (Cloud Run / container deployments).
// config/config.php is loaded afterwards as an override for local/self-hosted use.
// ============================================================================

foreach ([
    'AUTH_TOKEN'        => fn($v) => $v,
    'DOY_WINDOW_DAYS'   => fn($v) => (int) $v,
    'DOY_PAST_DAYS'     => fn($v) => (int) $v,
    'DOY_UPDATE_INTERVAL' => fn($v) => (int) $v,
] as $name => $cast) {
    $env = getenv($name);
    if ($env !== false && !defined($name)) {
        define($name, $cast($env));
    }
}

$configFile = __DIR__ . '/config/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

if (!defined('AUTH_TOKEN') || AUTH_TOKEN === 'CHANGE_ME_TO_A_RANDOM_STRING') {
    die('Error: AUTH_TOKEN not set. Use the AUTH_TOKEN environment variable or config/config.php.');
}

if (!defined('DOY_WINDOW_DAYS')) {
    define('DOY_WINDOW_DAYS', 400);
}
if (!defined('DOY_PAST_DAYS')) {
    define('DOY_PAST_DAYS', 30);
}
if (!defined('DOY_UPDATE_INTERVAL')) {
    define('DOY_UPDATE_INTERVAL', 86400); // 24 hours
}

// ============================================================================
// HELPERS
// ============================================================================

require_once __DIR__ . '/src/functions.php';

// ============================================================================
// SECURITY HEADERS
// ============================================================================

if (php_sapi_name() !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
}

// ============================================================================
// TOKEN CHECK
// ============================================================================

$token = $_GET['token'] ?? '';
if (!doy_verify_token($token)) {
    http_response_code(403);
    die('Invalid authentication token');
}

// ============================================================================
// PARAMETERS
// ============================================================================

$timezone = doy_sanitize_timezone($_GET['zone'] ?? 'Europe/Rome');
$location_name = doy_sanitize_text($_GET['location'] ?? '');

date_default_timezone_set($timezone);

$calendar_title = $location_name
    ? "📅 Day of Year – {$location_name}"
    : '📅 Day of Year';

// ============================================================================
// OUTPUT
// ============================================================================

header('Content-Type: text/calendar; charset=utf-8');
header('Cache-Control: max-age=' . DOY_UPDATE_INTERVAL);

$ttl_hours = (int) (DOY_UPDATE_INTERVAL / 3600);

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Day of Year Calendar//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:{$calendar_title}\r\n";
echo "X-WR-TIMEZONE:{$timezone}\r\n";
echo "X-PUBLISHED-TTL:PT{$ttl_hours}H\r\n";
echo "REFRESH-INTERVAL;VALUE=DURATION:PT{$ttl_hours}H\r\n";

$start = strtotime('-' . DOY_PAST_DAYS . ' days');
$current = $start;
$end = strtotime('+' . DOY_WINDOW_DAYS . ' days', strtotime('today'));

while ($current <= $end) {
    $year      = (int) date('Y', $current);
    $dayOfYear = (int) date('z', $current) + 1; // date('z') is 0-indexed
    $totalDays = doy_is_leap_year($year) ? 366 : 365;

    $percent  = (int) round($dayOfYear / $totalDays * 100);
    $daysLeft = $totalDays - $dayOfYear;
    $isoWeek  = (int) date('W', $current);

    $summary     = doy_event_summary($dayOfYear, $totalDays, $percent);
    $description = doy_event_description($dayOfYear, $totalDays, $percent, $daysLeft, $isoWeek, $year);

    $dateStr  = date('Ymd', $current);
    $nextDateStr = date('Ymd', strtotime('+1 day', $current));

    // UID: stable identifier for this calendar day
    $uid = "doy-{$dateStr}@day-of-year-calendar";

    echo "BEGIN:VEVENT\r\n";
    echo "UID:{$uid}\r\n";
    echo 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\n";
    echo "DTSTART;VALUE=DATE:{$dateStr}\r\n";
    echo "DTEND;VALUE=DATE:{$nextDateStr}\r\n";
    echo "SUMMARY:{$summary}\r\n";
    echo doy_format_description($description);
    echo "TRANSP:TRANSPARENT\r\n";
    echo "END:VEVENT\r\n";

    $current = strtotime('+1 day', $current);
}

echo "END:VCALENDAR\r\n";
