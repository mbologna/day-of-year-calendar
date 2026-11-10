# Day of Year Calendar

[![CI](https://github.com/mbologna/day-of-year-calendar/actions/workflows/ci.yml/badge.svg)](https://github.com/mbologna/day-of-year-calendar/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

A self-contained PHP script that generates an iCalendar (`.ics`) feed where each day shows its position in the year as an all-day event.

**Event format** (4 May 2026):

- Calendar grid: `124/365 · 33%`
- Event detail:

  ```
  Day 124 of 365
  ▓▓▓▓▓▓░░░░░░░░░░░░░░ 33%
  241 days left in 2026
  Q2 · day 34 of 91
  Week 19
  ```

The percentage is rounded down, so 100% only appears on the last day of the year, which reads `Last day of 2026` instead of a day count. The week is the ISO week; when its year differs from the calendar year, the line says so, e.g. `Week 53 of 2026` on 1 January 2027.

Notable days get an extra first line:

- `First day of 2026`
- `Leap day` on 29 February
- `Halfway through 2026` on day 183 (2 July, or 1 July in a leap year), the first day the percentage reaches 50%
- `100th day of 2026`, and likewise the 200th and 300th
- `Final 100 days of 2026` on 23 September, the day with 99 days left after it
- `Last day of Q1`, Q2 and Q3 (the last day of Q4 is already `Last day of 2026`)

The feed starts on 1 January of the current year (or 30 days back, if that is earlier) and runs 400 days ahead, so the whole year is always filled.

Subscribe from any calendar app (Google Calendar, Apple Calendar, Outlook, etc.) using a URL.

## Requirements

- PHP 8.5+
- A web server (Apache, nginx, or PHP's built-in server for testing)

## Configuration

The `AUTH_TOKEN` can be set two ways, pick one:

**Environment variable** (Cloud Run, Docker):
```bash
export AUTH_TOKEN=$(openssl rand -hex 32)
```

**Config file** (self-hosted):
```
day-of-year-calendar/
├── day-of-year-calendar.php
├── src/
└── config/
    └── config.php   ← copy from config.example.php
```

```php
<?php
define('AUTH_TOKEN', 'your-secret-token-here');
```

## Deployment

### Docker / Cloud Run

```bash
docker build -t day-of-year-calendar .
docker run -p 8080:8080 -e AUTH_TOKEN=your-token day-of-year-calendar
```

Infrastructure is managed via Terraform in `../infra/terraform/day-of-year-calendar/`. A push to `main` builds, mirrors to Artifact Registry, and deploys to Cloud Run automatically.

### Self-hosted (Apache / nginx)

1. Copy `day-of-year-calendar.php` and `src/` to your document root
2. Create `config/config.php` with `AUTH_TOKEN`
3. Point your web server at `day-of-year-calendar.php` as the directory index

## Web interface & URL builder

Opening the root URL without a token shows a form that previews today's event and builds the subscription link. Enter the `AUTH_TOKEN` as the access token and click **Get subscription link**. The result offers a **Subscribe** button (a `webcal://` link for Apple Calendar, Outlook and other desktop apps) and the `https://` link to paste into Google Calendar.

Health check (no auth required, returns JSON with `status`, `php` and `timestamp`):

```
https://your-host/?health=1
```

## Subscribing

```
https://your-host/day-of-year-calendar.php?token=YOUR_TOKEN
```

| Parameter  | Required | Default       | Description                                       |
|------------|----------|---------------|---------------------------------------------------|
| `token`    | Yes      | (none)        | Must match `AUTH_TOKEN`                           |
| `zone`     | No       | `Europe/Rome` | PHP timezone identifier (e.g. `America/New_York`); see below |
| `location` | No       | (none)        | Label appended to the calendar name               |

Events are all-day, so they fall on the same calendar date in every timezone: `zone` only decides which day counts as today when the feed picks its date range. The form no longer offers it, and links that already carry it keep working.

`location` is likewise no longer offered by the form, since every subscription shows the same events; rename the calendar in your calendar app instead. Links that carry it keep the label in the calendar name.

## Local development

```bash
# Start a local server
AUTH_TOKEN=test php -S localhost:8000

# Health check
curl "http://localhost:8000/?health=1"

# Fetch the feed
curl "http://localhost:8000/day-of-year-calendar.php?token=test"
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests cover all pure helper functions in `src/functions.php`: leap year detection, token verification, timezone/text sanitisation, RFC 5545 escaping and line folding, and event string formatting (percent rounding, singular/last-day wording, ISO week-year, quarters, milestones). `tests/CalendarFeedTest.php` additionally exercises `day-of-year-calendar.php` end to end over HTTP (token rejection, feed output, calendar name escaping, health check).

## License

MIT, see [LICENSE](LICENSE).