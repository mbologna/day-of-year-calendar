"""The feed matches the PHP v1.1.0 app byte for byte, except DTSTAMP.

tests/fixtures/php-v1.1.0/<date>.ics is what day-of-year-calendar.php v1.1.0
(PHP 8.5.12 CLI) served when libfaketime pinned the clock to the instant
below; <date> is that instant's date in Europe/Rome, the feed's "today".
"""

import datetime as dt
import re
from pathlib import Path

import pytest

from day_of_year_calendar import render_calendar, today_in

FIXTURES = Path(__file__).parent / "fixtures" / "php-v1.1.0"

# Feed date -> the UTC instant the PHP app ran at.
INSTANTS = {
    "2026-01-01": "2026-01-01T12:00:00",
    "2026-02-28": "2026-02-28T12:00:00",
    "2026-03-29": "2026-03-28T23:30:00",  # the night clocks go forward
    "2026-07-02": "2026-07-02T12:00:00",
    "2026-11-14": "2026-11-14T12:00:00",
    "2026-12-31": "2026-12-31T12:00:00",
    "2027-01-01": "2026-12-31T23:30:00",  # still 2026 in UTC
    "2028-02-29": "2028-02-29T12:00:00",
}

DTSTAMP = re.compile(rb"^DTSTAMP:\d{8}T\d{6}Z\r$", re.MULTILINE)


def test_every_fixture_is_covered():
    assert sorted(p.stem for p in FIXTURES.glob("*.ics")) == sorted(INSTANTS)


@pytest.mark.parametrize("name", sorted(INSTANTS))
def test_feed_matches_php(name):
    expected = (FIXTURES / f"{name}.ics").read_bytes()
    instant = dt.datetime.fromisoformat(INSTANTS[name]).replace(tzinfo=dt.UTC)
    today = today_in("Europe/Rome", instant)
    assert today.isoformat() == name

    # Built an hour later than the PHP run: DTSTAMP is the only line allowed
    # to differ.
    actual = render_calendar(today, instant + dt.timedelta(hours=1)).encode("utf-8")
    assert actual != expected
    assert DTSTAMP.sub(b"DTSTAMP:X\r", actual) == DTSTAMP.sub(b"DTSTAMP:X\r", expected)
