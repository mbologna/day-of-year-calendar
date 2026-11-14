"""Day of Year iCalendar generator.

Writes an iCalendar feed where each day has an all-day event showing its
position in the year ("124/365 · 33%"), plus a static subscription page.

Each event depends only on its own date. The day the feed is built only
moves the window: from 1 January (or 30 days back, if that is earlier) to
400 days ahead of "today" in Europe/Rome.
"""

from __future__ import annotations

import calendar
import datetime as dt
import zoneinfo

DEFAULT_TIMEZONE = "Europe/Rome"
WINDOW_DAYS = 400
PAST_DAYS = 30
UPDATE_INTERVAL = 86400  # 24 hours, the refresh hint in the feed

CALENDAR_TITLE = "📅 Day of Year"
CALENDAR_DESC = (
    "Where each day falls in the year: day number, percentage of the year done, days left, quarter and ISO week."
)

PAGE_URL = "https://mbologna.github.io/day-of-year-calendar/"
ICS_NAME = "day-of-year.ics"


def sanitize_timezone(value: str) -> str:
    """Return value if it names a known timezone, else the default one."""
    return value if value in zoneinfo.available_timezones() else DEFAULT_TIMEZONE


def today_in(timezone: str = DEFAULT_TIMEZONE, now: dt.datetime | None = None) -> dt.date:
    """The current date in timezone (now must be timezone-aware)."""
    now = now or dt.datetime.now(dt.UTC)
    return now.astimezone(zoneinfo.ZoneInfo(timezone)).date()


def escape_text(text: str) -> str:
    """Escape a TEXT property value per RFC 5545 §3.3.11."""
    text = text.replace("\\", "\\\\")
    text = text.replace("\r\n", "\\n").replace("\r", "\\n").replace("\n", "\\n")
    return text.replace(",", "\\,").replace(";", "\\;")


def fold_line(line: str) -> str:
    """Fold a content line to 75 bytes per RFC 5545 §3.1, without splitting
    multi-byte characters, and terminate it with CRLF."""
    result = ""
    current = ""
    byte_count = 0
    for char in line:
        char_bytes = len(char.encode("utf-8"))
        if byte_count + char_bytes > 75:
            result += current + "\r\n"
            current = " " + char
            byte_count = 1 + char_bytes
        else:
            current += char
            byte_count += char_bytes
    return result + current + "\r\n"


def format_description(text: str) -> str:
    """Escape and fold a DESCRIPTION value per RFC 5545."""
    return fold_line("DESCRIPTION:" + escape_text(text))


def is_leap_year(year: int) -> bool:
    return (year % 4 == 0 and year % 100 != 0) or year % 400 == 0


def year_percent(day_of_year: int, total_days: int) -> int:
    """Share of the year completed by the end of day_of_year, rounded down
    so that 100% only ever appears on the last day."""
    return day_of_year * 100 // total_days


def day_stats(day: dt.date) -> dict[str, int]:
    """Everything the event strings need to know about day."""
    year = day.year
    day_of_year = day.timetuple().tm_yday
    total = 366 if is_leap_year(year) else 365

    quarter = (day.month - 1) // 3 + 1
    first_month = (quarter - 1) * 3 + 1
    quarter_start = dt.date(year, first_month, 1).timetuple().tm_yday
    quarter_total = sum(calendar.monthrange(year, m)[1] for m in range(first_month, first_month + 3))
    iso_year, iso_week, _ = day.isocalendar()

    return {
        "day": day_of_year,
        "total": total,
        "percent": year_percent(day_of_year, total),
        "left": total - day_of_year,
        "week": iso_week,
        "weekYear": iso_year,
        "year": year,
        "quarter": quarter,
        "quarterDay": day_of_year - quarter_start + 1,
        "quarterTotal": quarter_total,
    }


def event_summary(day_of_year: int, total_days: int, percent: int) -> str:
    """The SUMMARY shown in the calendar grid, e.g. "124/365 · 33%"."""
    return f"{day_of_year}/{total_days} · {percent}%"


def progress_bar(percent: int, width: int = 20) -> str:
    """Render percent as a text progress bar, e.g. "▓▓▓▓▓▓░░░░░░░░░░░░░░"."""
    filled = max(0, min(100, percent)) * width // 100
    return "▓" * filled + "░" * (width - filled)


def milestone(day_of_year: int, total_days: int, year: int) -> str | None:
    """Name the notable day day_of_year is, or return None on an ordinary day.
    No two milestones share a day.

    - Halfway is day 183 in both year lengths: the first day whose
      rounded-down percentage reaches 50%.
    - "Final 100 days" falls on the day with 99 days left, since the
      days-left line counts the days after today; on the day before, that
      line already reads "100 days left".
    - The last day of the year needs no milestone, nor does the last day of
      Q4: the days-left line already reads "Last day of <year>".
    """
    leap = total_days - 365  # 1 in a leap year, else 0
    if day_of_year == 1:
        return f"First day of {year}"
    if leap == 1 and day_of_year == 60:
        return "Leap day"
    if day_of_year == (total_days + 1) // 2:
        return f"Halfway through {year}"
    if day_of_year in (100, 200, 300):
        return f"{day_of_year}th day of {year}"
    if day_of_year == total_days - 99:
        return f"Final 100 days of {year}"
    if day_of_year == 90 + leap:
        return "Last day of Q1"
    if day_of_year == 181 + leap:
        return "Last day of Q2"
    if day_of_year == 273 + leap:
        return "Last day of Q3"
    return None


def event_description(d: dict[str, int]) -> str:
    """The DESCRIPTION shown in event detail views, one fact per line, from a
    day_stats() dict. Example (2 July 2026):

        Halfway through 2026
        Day 183 of 365
        ▓▓▓▓▓▓▓▓▓▓░░░░░░░░░░ 50%
        182 days left in 2026
        Q3 · day 2 of 92
        Week 27

    The milestone line only appears on notable days (see milestone()). The
    week line names its ISO year only when it differs from the calendar year,
    e.g. "Week 53 of 2026" on 1 January 2027.
    """
    if d["left"] == 0:
        left = f"Last day of {d['year']}"
    elif d["left"] == 1:
        left = f"1 day left in {d['year']}"
    else:
        left = f"{d['left']} days left in {d['year']}"
    week = f"Week {d['week']}" if d["weekYear"] == d["year"] else f"Week {d['week']} of {d['weekYear']}"

    lines = [
        milestone(d["day"], d["total"], d["year"]),
        f"Day {d['day']} of {d['total']}",
        f"{progress_bar(d['percent'])} {d['percent']}%",
        left,
        f"Q{d['quarter']} · day {d['quarterDay']} of {d['quarterTotal']}",
        week,
    ]
    return "\n".join(line for line in lines if line)


def feed_window(today: dt.date) -> tuple[dt.date, dt.date]:
    """First and last day of the feed, both included: from 1 January, or
    PAST_DAYS back if that is earlier (early January reaches into last
    December), to WINDOW_DAYS ahead."""
    start = min(dt.date(today.year, 1, 1), today - dt.timedelta(days=PAST_DAYS))
    return start, today + dt.timedelta(days=WINDOW_DAYS)


def render_calendar(today: dt.date, now: dt.datetime, timezone: str = DEFAULT_TIMEZONE) -> str:
    """The whole feed for the window around today. now (timezone-aware) is
    the generation time, written as every event's DTSTAMP."""
    ttl_hours = UPDATE_INTERVAL // 3600
    dtstamp = now.astimezone(dt.UTC).strftime("%Y%m%dT%H%M%SZ")
    out = [
        "BEGIN:VCALENDAR\r\n",
        "VERSION:2.0\r\n",
        "PRODID:-//Day of Year Calendar//EN\r\n",
        "CALSCALE:GREGORIAN\r\n",
        "METHOD:PUBLISH\r\n",
        fold_line("X-WR-CALNAME:" + escape_text(CALENDAR_TITLE)),
        fold_line("X-WR-CALDESC:" + escape_text(CALENDAR_DESC)),
        f"X-WR-TIMEZONE:{timezone}\r\n",
        f"X-PUBLISHED-TTL:PT{ttl_hours}H\r\n",
        f"REFRESH-INTERVAL;VALUE=DURATION:PT{ttl_hours}H\r\n",
    ]

    current, end = feed_window(today)
    one_day = dt.timedelta(days=1)
    while current <= end:
        d = day_stats(current)
        date_str = current.strftime("%Y%m%d")
        out += [
            "BEGIN:VEVENT\r\n",
            f"UID:doy-{date_str}@day-of-year-calendar\r\n",
            f"DTSTAMP:{dtstamp}\r\n",
            f"DTSTART;VALUE=DATE:{date_str}\r\n",
            f"DTEND;VALUE=DATE:{(current + one_day).strftime('%Y%m%d')}\r\n",
            f"SUMMARY:{event_summary(d['day'], d['total'], d['percent'])}\r\n",
            format_description(event_description(d)),
            "TRANSP:TRANSPARENT\r\n",
            "END:VEVENT\r\n",
        ]
        current += one_day

    out.append("END:VCALENDAR\r\n")
    return "".join(out)
