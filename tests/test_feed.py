"""Ported from the PHP v1.1.0 tests/CalendarFeedTest.php, one test per PHP test.

The PHP tests drove the script over HTTP. Here the same checks run against
what the build writes. The token gate, the label and ?health are gone: their
tests now check that the published files are public, that the calendar name
is fixed, and that the published feed's age can be checked.
"""

import datetime as dt
import io
import urllib.error
import urllib.request
from contextlib import contextmanager

import pytest

from day_of_year_calendar import cli, render_calendar, today_in

ICS_URL = "https://mbologna.github.io/day-of-year-calendar/day-of-year.ics"


@pytest.fixture
def site(tmp_path):
    def build(*args):
        assert cli.main(["build", "--output-dir", str(tmp_path), *args]) == 0
        feed = (tmp_path / "day-of-year.ics").read_bytes().decode("utf-8")
        return feed, (tmp_path / "index.html").read_bytes().decode("utf-8")

    build.path = tmp_path
    return build


def test_page_shows_subscription_links_without_a_token_form(site):
    _, page = site("--today", "2026-05-04")
    assert "<form" not in page
    assert 'type="password"' not in page
    assert 'name="location"' not in page
    assert 'href="webcal://mbologna.github.io/day-of-year-calendar/day-of-year.ics"' in page
    assert "Add calendar &rarr; From URL" in page
    assert "124/365 · 33%" in page  # today's event, as the preview


def test_page_links_the_public_feed_url(site):
    _, page = site("--today", "2026-05-04")
    assert f'id="sub-url">{ICS_URL}</div>' in page
    assert "token=" not in page
    assert "location=" not in page


def test_build_writes_only_the_public_files(site):
    feed, _ = site("--today", "2026-05-04")
    assert sorted(p.name for p in site.path.iterdir()) == ["day-of-year.ics", "index.html"]
    assert "token" not in feed.lower()


def test_build_writes_calendar_feed(site):
    feed, _ = site("--today", "2026-05-04")
    assert feed.startswith("BEGIN:VCALENDAR\r\n")
    assert feed.endswith("END:VCALENDAR\r\n")
    assert "BEGIN:VEVENT\r\n" in feed
    assert "DTSTART;VALUE=DATE:20260504\r\n" in feed
    assert "X-WR-CALDESC:" in feed


def test_feed_starts_on_first_of_january(site):
    feed, _ = site()
    # The feed works out today in its default timezone, not the test's.
    year = today_in("Europe/Rome").year
    assert f"DTSTART;VALUE=DATE:{year}0101\r\n" in feed


def test_calendar_name_is_fixed():
    feed = render_calendar(dt.date(2026, 5, 4), dt.datetime(2026, 5, 4, tzinfo=dt.UTC))
    assert "X-WR-CALNAME:📅 Day of Year\r\n" in feed


@contextmanager
def serve(body: bytes):
    """Make urlopen return body, as the published feed."""
    original = urllib.request.urlopen

    @contextmanager
    def fake_urlopen(req, timeout):
        yield io.BytesIO(body)

    urllib.request.urlopen = fake_urlopen
    try:
        yield
    finally:
        urllib.request.urlopen = original


def test_check_passes_on_a_fresh_published_feed(capsys):
    feed = render_calendar(dt.date.today(), dt.datetime.now(dt.UTC) - dt.timedelta(days=7))
    with serve(feed.encode()):
        assert cli.main(["check", ICS_URL]) == 0
    assert "7.0 days ago (limit 8)" in capsys.readouterr().out


def test_build_honours_the_timezone_option(site):
    feed, _ = site("--timezone", "America/New_York")
    assert "X-WR-TIMEZONE:America/New_York\r\n" in feed


# ── beyond the PHP tests: the freshness check ───────────────────────────────


def test_check_fails_on_a_stale_published_feed(capsys):
    feed = render_calendar(dt.date.today(), dt.datetime.now(dt.UTC) - dt.timedelta(days=9))
    with serve(feed.encode()):
        assert cli.main(["check", ICS_URL]) == 1
    assert "9.0 days ago (limit 8)" in capsys.readouterr().err


def test_check_fails_without_dtstamp(capsys):
    with serve(b"<html>not a feed</html>"):
        assert cli.main(["check", ICS_URL]) == 1
    assert "no DTSTAMP" in capsys.readouterr().err


def test_check_fails_when_the_feed_is_unreachable(capsys):
    original = urllib.request.urlopen

    def unreachable(req, timeout):
        raise urllib.error.HTTPError(ICS_URL, 404, "Not Found", None, None)

    urllib.request.urlopen = unreachable
    try:
        assert cli.main(["check", ICS_URL]) == 1
    finally:
        urllib.request.urlopen = original
    assert "404" in capsys.readouterr().err


def test_build_falls_back_to_rome_on_an_unknown_timezone(site):
    feed, _ = site("--today", "2026-05-04", "--timezone", "Not/ATimezone")
    assert "X-WR-TIMEZONE:Europe/Rome\r\n" in feed
