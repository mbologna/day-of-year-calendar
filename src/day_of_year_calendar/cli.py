"""Command line: build the site, or check the published feed's age."""

from __future__ import annotations

import argparse
import datetime as dt
import re
import sys
import urllib.error
import urllib.request
from pathlib import Path

from . import DEFAULT_TIMEZONE, ICS_NAME, PAGE_URL, render_calendar, sanitize_timezone, today_in
from .page import render_page

DTSTAMP_RE = re.compile(rb"^DTSTAMP:(\d{8}T\d{6}Z)\r?$", re.MULTILINE)


def build(output_dir: Path, today: dt.date, now: dt.datetime, page_url: str, timezone: str) -> None:
    """Write the feed and the subscription page into output_dir."""
    output_dir.mkdir(parents=True, exist_ok=True)
    ics_url = page_url.rstrip("/") + "/" + ICS_NAME
    # Bytes, not text mode: the CRLF line endings must reach the file as is.
    (output_dir / ICS_NAME).write_bytes(render_calendar(today, now, timezone).encode("utf-8"))
    (output_dir / "index.html").write_bytes(render_page(today, ics_url).encode("utf-8"))


def generated_at(feed: bytes) -> dt.datetime:
    """When a feed was built: its first DTSTAMP. Raises ValueError without one."""
    match = DTSTAMP_RE.search(feed)
    if not match:
        raise ValueError("no DTSTAMP in the feed")
    return dt.datetime.strptime(match.group(1).decode(), "%Y%m%dT%H%M%SZ").replace(tzinfo=dt.UTC)


def check_fresh(feed: bytes, max_age: dt.timedelta, now: dt.datetime) -> tuple[bool, str]:
    """Whether the feed was built within max_age of now, and a message saying so."""
    try:
        built = generated_at(feed)
    except ValueError as e:
        return False, str(e)
    age = now - built
    days = age.total_seconds() / 86400
    message = f"built {built:%Y-%m-%d %H:%M:%S} UTC, {days:.1f} days ago (limit {max_age.days})"
    return age <= max_age, message


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="day-of-year-calendar", description=__doc__)
    sub = parser.add_subparsers(dest="command", required=True)

    b = sub.add_parser("build", help="write day-of-year.ics and index.html")
    b.add_argument("--output-dir", type=Path, default=Path("public"))
    b.add_argument("--today", type=dt.date.fromisoformat, help="build as if today were this date (YYYY-MM-DD)")
    b.add_argument("--timezone", default=DEFAULT_TIMEZONE, help="the timezone that decides which day is today")
    b.add_argument("--page-url", default=PAGE_URL, help="where the site is published")

    c = sub.add_parser("check", help="fail when the published feed is older than --max-age-days")
    c.add_argument("url", nargs="?", default=PAGE_URL + ICS_NAME)
    c.add_argument("--max-age-days", type=int, default=8)

    args = parser.parse_args(argv)
    now = dt.datetime.now(dt.UTC)

    if args.command == "build":
        timezone = sanitize_timezone(args.timezone)
        today = args.today or today_in(timezone, now)
        build(args.output_dir, today, now, args.page_url, timezone)
        print(f"built {args.output_dir / ICS_NAME} and index.html for {today.isoformat()} ({timezone})")
        return 0

    req = urllib.request.Request(args.url, headers={"User-Agent": "day-of-year-calendar-check"})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            feed = r.read()
    except (urllib.error.URLError, OSError) as e:
        print(f"{args.url}: {e}", file=sys.stderr)
        return 1
    ok, message = check_fresh(feed, dt.timedelta(days=args.max_age_days), now)
    print(f"{args.url}: {message}", file=sys.stdout if ok else sys.stderr)
    return 0 if ok else 1
