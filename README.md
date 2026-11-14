# Day of Year Calendar

[![CI](https://github.com/mbologna/day-of-year-calendar/actions/workflows/ci.yml/badge.svg)](https://github.com/mbologna/day-of-year-calendar/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

An iCalendar (`.ics`) feed where each day shows its position in the year as an all-day event.

**Subscribe:** <https://mbologna.github.io/day-of-year-calendar/>

- Apple Calendar, Outlook and most desktop apps: `webcal://mbologna.github.io/day-of-year-calendar/day-of-year.ics`
- Google Calendar: *Add calendar → From URL* with `https://mbologna.github.io/day-of-year-calendar/day-of-year.ics`, on a computer (the mobile app can't add calendars by URL).

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

## How it's published

The feed is a static file. Each event depends only on its own date, so a file built once a week serves the same events a per-request server would.

A Python script (standard library only) writes `day-of-year.ics` and `index.html`, the subscription page with a preview of the day's event. The feed starts on 1 January of the current year (or 30 days back, if that is earlier) and runs 400 days ahead. "Today" is the current date in Europe/Rome.

The [CI workflow](.github/workflows/ci.yml) runs the tests, then builds and deploys both files to GitHub Pages:

- on every push to `main`;
- every Monday at 06:00 UTC, so the feed always runs at least 393 days ahead;
- on a manual run.

After deploying, the workflow fetches the published feed and fails if its `DTSTAMP` (the build time) is more than 8 days old. GitHub disables a public repo's scheduled workflows after 60 days without activity. Renovate's commits keep this one active. A disabled schedule runs nothing, so nothing fails either: if that ever happens, re-enable the workflow from the Actions tab.

The feed tells calendar apps to refresh every 24 hours (`REFRESH-INTERVAL`, `X-PUBLISHED-TTL`).

Version 1 was a PHP app on Cloud Run behind an access token. Version 2 generates the same events: the tests compare its output with the PHP app's, byte for byte except `DTSTAMP`. The token, the `zone` and `location` parameters and the `?health` endpoint are gone.

## Development

```sh
uv run pytest
uv run ruff check . && uv run ruff format --check .

# Build into public/, optionally as if today were another date
uv run day-of-year-calendar build --output-dir public --today 2027-01-01

# Check the published feed's age
uv run day-of-year-calendar check --max-age-days 8
```

`tests/test_golden.py` compares the feed with `tests/fixtures/php-v1.1.0/`, the output of the PHP v1.1.0 app for eight fixed dates (leap day, year ends, the halfway day, the DST change night, and UTC evenings that are already the next day in Rome).

Dependencies resolve only to releases at least 7 days old (`exclude-newer` in `pyproject.toml`). The script itself needs none.

## License

MIT, see [LICENSE](LICENSE).
