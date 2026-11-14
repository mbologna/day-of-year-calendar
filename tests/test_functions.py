"""Ported from the PHP v1.1.0 tests/FunctionsTest.php, one test per PHP test.

The PHP token and text-sanitising helpers have no counterpart any more (the
feed is public and takes no label): their tests became tests of what replaced
them, "today" in Europe/Rome and the remaining RFC 5545 escaping rules.
"""

import datetime as dt

import pytest

from day_of_year_calendar import (
    day_stats,
    escape_text,
    event_description,
    event_summary,
    fold_line,
    format_description,
    is_leap_year,
    milestone,
    progress_bar,
    sanitize_timezone,
    today_in,
    year_percent,
)


def stats_for(date: str) -> dict[str, int]:
    return day_stats(dt.date.fromisoformat(date))


# ── is_leap_year ─────────────────────────────────────────────────────────────


@pytest.mark.parametrize(
    ("year", "expected"),
    [
        pytest.param(2024, True, id="divisible by 4"),
        pytest.param(2000, True, id="divisible by 400"),
        pytest.param(1900, False, id="divisible by 100 not 400"),
        pytest.param(2100, False, id="divisible by 100 not 400 (2100)"),
        pytest.param(2023, False, id="not divisible by 4"),
        pytest.param(2025, False, id="common year"),
    ],
)
def test_is_leap_year(year, expected):
    assert is_leap_year(year) is expected


# ── today_in (replaces doy_verify_token) ─────────────────────────────────────


def test_today_in_rome_at_noon_utc_is_the_same_date():
    assert today_in("Europe/Rome", dt.datetime(2026, 7, 2, 12, 0, tzinfo=dt.UTC)) == dt.date(2026, 7, 2)


def test_today_in_rome_late_evening_utc_is_the_next_date():
    assert today_in("Europe/Rome", dt.datetime(2026, 12, 31, 23, 30, tzinfo=dt.UTC)) == dt.date(2027, 1, 1)


def test_today_in_rome_follows_summer_time():
    # 22:30 UTC is 00:30 CEST in summer, but still 23:30 CET in winter.
    assert today_in("Europe/Rome", dt.datetime(2026, 7, 1, 22, 30, tzinfo=dt.UTC)) == dt.date(2026, 7, 2)
    assert today_in("Europe/Rome", dt.datetime(2026, 1, 1, 22, 30, tzinfo=dt.UTC)) == dt.date(2026, 1, 1)


# ── sanitize_timezone ────────────────────────────────────────────────────────


def test_sanitize_timezone_accepts_valid_zone():
    assert sanitize_timezone("America/New_York") == "America/New_York"


def test_sanitize_timezone_accepts_europe_rome():
    assert sanitize_timezone("Europe/Rome") == "Europe/Rome"


def test_sanitize_timezone_falls_back_on_invalid():
    assert sanitize_timezone("Not/ATimezone") == "Europe/Rome"


def test_sanitize_timezone_falls_back_on_empty():
    assert sanitize_timezone("") == "Europe/Rome"


# ── escape_text (replaces doy_sanitize_text) ─────────────────────────────────


def test_escape_text_doubles_backslash():
    assert escape_text("a\\b") == "a\\\\b"


def test_escape_text_turns_crlf_into_one_newline_escape():
    assert escape_text("a\r\nb") == "a\\nb"


def test_escape_text_turns_cr_into_newline_escape():
    assert escape_text("a\rb") == "a\\nb"


def test_escape_text_turns_lf_into_newline_escape():
    assert escape_text("a\nb") == "a\\nb"


# ── format_description ───────────────────────────────────────────────────────


def test_format_description_starts_with_prefix():
    assert format_description("hello").startswith("DESCRIPTION:hello")


def test_format_description_ends_with_crlf():
    assert format_description("hello").endswith("\r\n")


def test_format_description_escapes_comma():
    assert "a\\,b" in format_description("a,b")


def test_format_description_escapes_semicolon():
    assert "a\\;b" in format_description("a;b")


def test_format_description_folds_long_lines():
    # A line > 75 bytes must be folded with CRLF + space continuation
    assert "\r\n " in format_description("x" * 100)


def test_format_description_folded_lines_respect_75_byte_limit():
    output = format_description("a" * 200)
    for i, line in enumerate(output.rstrip("\r\n").split("\r\n")):
        assert len(line.encode()) <= 75, f"Line {i} exceeds 75 bytes"


# ── event_summary ────────────────────────────────────────────────────────────


@pytest.mark.parametrize(
    ("day", "total", "pct", "expected"),
    [
        pytest.param(1, 365, 0, "1/365 · 0%", id="day 1 of 365"),
        pytest.param(124, 365, 33, "124/365 · 33%", id="day 124 of 365"),
        pytest.param(365, 365, 100, "365/365 · 100%", id="day 365 of 365"),
        pytest.param(200, 366, 55, "200/366 · 55%", id="leap year"),
    ],
)
def test_event_summary_format(day, total, pct, expected):
    assert event_summary(day, total, pct) == expected


# ── escape_text / fold_line ──────────────────────────────────────────────────


def test_escape_text_escapes_comma_and_semicolon():
    assert escape_text("Milan, Italy; HQ") == "Milan\\, Italy\\; HQ"


def test_fold_line_does_not_split_multibyte_characters():
    output = fold_line("X:" + "▓" * 40)
    for line in output.rstrip("\r\n").encode().split(b"\r\n"):
        assert len(line) <= 75
        line.decode("utf-8")  # raises on a split character


# ── year_percent ─────────────────────────────────────────────────────────────


@pytest.mark.parametrize(
    ("day", "total", "expected"),
    [
        pytest.param(1, 365, 0, id="first day"),
        pytest.param(124, 365, 33, id="day 124"),
        pytest.param(364, 365, 99, id="second to last day"),
        pytest.param(365, 365, 100, id="last day"),
        pytest.param(366, 366, 100, id="leap year last day"),
    ],
)
def test_year_percent(day, total, expected):
    assert year_percent(day, total) == expected


# ── day_stats ────────────────────────────────────────────────────────────────


def test_day_stats_uses_iso_week_year():
    stats = stats_for("2027-01-01")
    assert stats["day"] == 1
    assert stats["week"] == 53
    assert stats["weekYear"] == 2026
    assert stats["year"] == 2027


@pytest.mark.parametrize(
    ("date", "quarter", "quarter_day", "quarter_total"),
    [
        pytest.param("2026-01-01", 1, 1, 90, id="first day of Q1"),
        pytest.param("2028-03-31", 1, 91, 91, id="last day of leap Q1"),
        pytest.param("2026-04-01", 2, 1, 91, id="first day of Q2"),
        pytest.param("2026-10-01", 4, 1, 92, id="first day of Q4"),
        pytest.param("2026-12-31", 4, 92, 92, id="last day of Q4"),
    ],
)
def test_day_stats_quarter(date, quarter, quarter_day, quarter_total):
    stats = stats_for(date)
    assert stats["quarter"] == quarter
    assert stats["quarterDay"] == quarter_day
    assert stats["quarterTotal"] == quarter_total


# ── milestone ────────────────────────────────────────────────────────────────


@pytest.mark.parametrize(
    ("date", "expected"),
    [
        pytest.param("2026-01-01", "First day of 2026", id="first day"),
        pytest.param("2028-02-29", "Leap day", id="leap day"),
        pytest.param("2026-07-02", "Halfway through 2026", id="halfway, common"),
        pytest.param("2028-07-01", "Halfway through 2028", id="halfway, leap"),
        pytest.param("2026-07-01", None, id="day before halfway"),
        pytest.param("2026-12-31", None, id="last day"),
        pytest.param("2026-05-04", None, id="ordinary day"),
        pytest.param("2026-03-01", None, id="1 March, common year"),
        pytest.param("2026-04-10", "100th day of 2026", id="100th day"),
        pytest.param("2028-04-09", "100th day of 2028", id="100th day, leap"),
        pytest.param("2026-07-19", "200th day of 2026", id="200th day"),
        pytest.param("2026-10-27", "300th day of 2026", id="300th day"),
        pytest.param("2026-09-22", None, id="100 days left"),
        pytest.param("2026-09-23", "Final 100 days of 2026", id="final 100 days"),
        pytest.param("2028-09-23", "Final 100 days of 2028", id="final 100 days, leap"),
        pytest.param("2026-03-31", "Last day of Q1", id="end of Q1"),
        pytest.param("2028-03-31", "Last day of Q1", id="end of Q1, leap"),
        pytest.param("2026-06-30", "Last day of Q2", id="end of Q2"),
        pytest.param("2028-09-30", "Last day of Q3", id="end of Q3, leap"),
        pytest.param("2026-10-01", None, id="day after end of Q3"),
    ],
)
def test_milestone(date, expected):
    stats = stats_for(date)
    assert milestone(stats["day"], stats["total"], stats["year"]) == expected


@pytest.mark.parametrize(
    ("total_days", "expected"),
    [pytest.param(365, 9, id="common year"), pytest.param(366, 10, id="leap year")],
)
def test_milestones_fall_on_distinct_days(total_days, expected):
    """Every milestone lands on its own day: a collision would hide one and
    lower the count."""
    days = [day for day in range(1, total_days + 1) if milestone(day, total_days, 2026) is not None]
    assert len(days) == expected


# ── progress_bar ─────────────────────────────────────────────────────────────


def test_progress_bar():
    assert progress_bar(0) == "░" * 20
    assert progress_bar(33) == "▓" * 6 + "░" * 14
    assert progress_bar(100) == "▓" * 20


# ── event_description ────────────────────────────────────────────────────────


def test_event_description_format():
    assert event_description(stats_for("2026-05-04")) == (
        f"Day 124 of 365\n{progress_bar(33)} 33%\n241 days left in 2026\nQ2 · day 34 of 91\nWeek 19"
    )


def test_event_description_leads_with_milestone():
    assert event_description(stats_for("2026-07-02")) == (
        f"Halfway through 2026\nDay 183 of 365\n{progress_bar(50)} 50%\n182 days left in 2026\nQ3 · day 2 of 92\nWeek 27"
    )


def test_event_description_singular_day_left():
    assert "1 day left in 2026" in event_description(stats_for("2026-12-30"))


def test_event_description_last_day_of_year():
    desc = event_description(stats_for("2026-12-31"))
    assert "Last day of 2026" in desc
    assert "days left" not in desc


def test_event_description_names_iso_year_when_it_differs():
    assert "Week 53 of 2026" in event_description(stats_for("2027-01-01"))
