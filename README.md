# Day of Year Calendar

A self-contained PHP script that generates an iCalendar (`.ics`) feed where each day shows its number within the year — e.g. **Day 42 of 365** — as an all-day event.

Subscribe to it from any calendar application (Google Calendar, Apple Calendar, Outlook, etc.) using a URL.

## Requirements

- PHP 8.2+
- A web server (Apache, nginx, Caddy, or PHP's built-in server for testing)

## Installation

1. **Deploy the file** — copy `day-of-year-calendar.php` to your web server's document root (or any public directory).

2. **Create the config file** — the script expects `config/config.php` relative to its own directory:

   ```
   day-of-year-calendar/
   ├── day-of-year-calendar.php
   └── config/
       └── config.php
   ```

   Create `config/config.php` with at minimum:

   ```php
   <?php
   define('AUTH_TOKEN', 'your-secret-token-here');
   ```

   Generate a secure token:

   ```bash
   openssl rand -hex 32
   ```

3. **Subscribe** — use the following URL pattern in your calendar app:

   ```
   https://your-server.example.com/day-of-year-calendar.php?token=YOUR_TOKEN
   ```

## URL Parameters

| Parameter  | Required | Default       | Description                                      |
|------------|----------|---------------|--------------------------------------------------|
| `token`    | Yes      | —             | Authentication token (must match `AUTH_TOKEN`)   |
| `zone`     | No       | `Europe/Rome` | PHP timezone identifier (e.g. `America/New_York`)|
| `location` | No       | —             | Display name appended to the calendar title      |

### Example URLs

```
# Basic
https://your-server.example.com/day-of-year-calendar.php?token=abc123

# With timezone and location name
https://your-server.example.com/day-of-year-calendar.php?token=abc123&zone=America/New_York&location=New+York
```

## Calendar Output

- One **all-day event per day** for the next 365 days (starting today)
- Event title: `Day 42 of 365`
- Feed refreshes every 24 hours
- Leap years automatically show `Day X of 366`

## Local Testing

```bash
php -S localhost:8000
# Then open: http://localhost:8000/day-of-year-calendar.php?token=YOUR_TOKEN
```
