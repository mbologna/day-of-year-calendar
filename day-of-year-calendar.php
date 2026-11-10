<?php

declare(strict_types=1);

/**
 * Day of Year iCalendar Generator
 *
 * Generates an iCalendar feed where each day has an all-day event
 * showing its position in the year: "124/365 · 33%".
 *
 * Standalone application — can be moved to any directory.
 * Configuration via AUTH_TOKEN environment variable (Cloud Run)
 * or config/config.php (self-hosted).
 *
 * URL parameters:
 *   token    – authentication token (required)
 *   zone     – timezone identifier (default: Europe/Rome). Only decides which
 *              day counts as "today" for the feed's date range: events are
 *              all-day, so they fall on the same date everywhere. The form no
 *              longer offers it; older links that carry it keep working.
 *   location – optional label appended to the calendar name. The form no
 *              longer offers it; older links that carry it keep working.
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
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    die('Server misconfigured: AUTH_TOKEN is not set, or still has the example value. '
        . 'Set the AUTH_TOKEN environment variable, or set it in config/config.php.');
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
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; base-uri 'none'; form-action 'self'");
}

// ============================================================================
// HEALTH CHECK
// ============================================================================

if (isset($_GET['health'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status'    => 'ok',
        'php'       => PHP_VERSION,
        'timestamp' => time(),
    ]);
    exit;
}

// ============================================================================
// URL GENERATION FORM (POST)
// ============================================================================

if (isset($_POST['generate_url']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (!doy_verify_token($password)) {
        $error = 'That access token is not valid. Check the AUTH_TOKEN set on this server.';
    } else {
        $params           = ['token' => AUTH_TOKEN];
        $protocol         = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host             = $_SERVER['HTTP_HOST'];
        $script           = $_SERVER['SCRIPT_NAME'];
        $subscription_url = $protocol . '://' . $host . $script . '?' . http_build_query($params);
        $webcal_url       = str_replace(['https://', 'http://'], 'webcal://', $subscription_url);
    }
    require_once __DIR__ . '/assets/index.html.php';
    exit;
}

// ============================================================================
// WEB INTERFACE (no token → show URL builder)
// ============================================================================

if (!isset($_GET['token'])) {
    require_once __DIR__ . '/assets/index.html.php';
    exit;
}

// ============================================================================
// TOKEN CHECK
// ============================================================================

$token = $_GET['token'] ?? '';
if (!doy_verify_token($token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    die('Invalid access token');
}

// ============================================================================
// PARAMETERS
// ============================================================================

$timezone = doy_sanitize_timezone($_GET['zone'] ?? DOY_DEFAULT_TIMEZONE);
$location_name = doy_sanitize_text($_GET['location'] ?? '');

date_default_timezone_set($timezone);

$calendar_title = $location_name
    ? "📅 Day of Year – {$location_name}"
    : '📅 Day of Year';
$calendar_desc = 'Where each day falls in the year: day number, '
    . 'percentage of the year done, days left, quarter and ISO week.';

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
echo doy_fold_line('X-WR-CALNAME:' . doy_escape_text($calendar_title));
echo doy_fold_line('X-WR-CALDESC:' . doy_escape_text($calendar_desc));
echo "X-WR-TIMEZONE:{$timezone}\r\n";
echo "X-PUBLISHED-TTL:PT{$ttl_hours}H\r\n";
echo "REFRESH-INTERVAL;VALUE=DURATION:PT{$ttl_hours}H\r\n";

$start = strtotime('-' . DOY_PAST_DAYS . ' days', strtotime('today'));
$current = $start;
$end = strtotime('+' . DOY_WINDOW_DAYS . ' days', strtotime('today'));

while ($current <= $end) {
    $d = doy_day_stats($current);

    $summary     = doy_event_summary($d['day'], $d['total'], $d['percent']);
    $description = doy_event_description($d);

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
