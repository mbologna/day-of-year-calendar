# Day of Year Calendar

A self-contained PHP script that generates an iCalendar (`.ics`) feed where each day shows its position in the year as an all-day event.

**Event format:**
- Calendar grid: `124/365 · 34%`
- Event detail: `Day 124 of 365 · 34% complete — 241 days remaining · Week 20 · 2026`

Subscribe from any calendar app (Google Calendar, Apple Calendar, Outlook, etc.) using a URL.

## Requirements

- PHP 8.2+
- A web server (Apache, nginx, or PHP's built-in server for testing)

## Configuration

The `AUTH_TOKEN` can be set two ways — pick one:

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

## Subscribing

```
https://your-host/day-of-year-calendar.php?token=YOUR_TOKEN
```

| Parameter  | Required | Default       | Description                                       |
|------------|----------|---------------|---------------------------------------------------|
| `token`    | Yes      | —             | Must match `AUTH_TOKEN`                           |
| `zone`     | No       | `Europe/Rome` | PHP timezone identifier (e.g. `America/New_York`) |
| `location` | No       | —             | Appended to the calendar title                    |

## Local development

```bash
# Start a local server
AUTH_TOKEN=test php -S localhost:8000

# Fetch the feed
curl "http://localhost:8000/day-of-year-calendar.php?token=test"
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests cover all pure helper functions in `src/functions.php`: leap year detection, token verification, timezone/text sanitisation, RFC 5545 line folding, and event string formatting.
